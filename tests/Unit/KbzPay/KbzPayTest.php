<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPaySigner;

beforeEach(function () {
    $this->config = new KbzPayConfig(appId: 'kp123', appKey: 'secret-key', merchantCode: '100001');
    $this->signer = new KbzPaySigner('secret-key');
    $this->data = new KbzPayPaymentData(orderId: 'ORDER_1', amount: 1000, callbackUrl: 'https://shop.test/kbz/callback');
});

function precreateResponse(array $extra = []): array
{
    return ['Response' => ['result' => 'SUCCESS', 'code' => '0', 'msg' => 'success', 'prepay_id' => 'PREPAY123'] + $extra];
}

function signedCallback(KbzPaySigner $signer, array $fields): CallbackRequest
{
    $fields['sign_type'] = 'SHA256';
    $fields['sign'] = $signer->sign($fields);

    return CallbackRequest::fromArray(['Request' => $fields]);
}

it('builds the sign string exactly as the KBZ docs example', function () {
    $vector = testVector('kbz_pay/sign_string.json');

    expect((new KbzPaySigner('any'))->signString($vector['fields']))->toBe($vector['expected']);
});

it('does not url-encode values when signing', function () {
    expect($this->signer->signString(['callback_info' => 'title%3Diphonex', 'notify_url' => 'https://a.test/x y']))
        ->toBe('callback_info=title%3Diphonex&notify_url=https://a.test/x y');
});

it('sends a signed precreate request and returns the PWA redirect url', function () {
    $http = mockHttp(jsonResponse(precreateResponse()));

    $payment = (new KbzPay($this->config, $http))->pwa($this->data);

    $request = requestJson($http->getLastRequest())['Request'];
    expect((string) $http->getLastRequest()->getUri())->toBe(KbzPayConfig::SANDBOX_API_URL.'/precreate')
        ->and($request['method'])->toBe('kbz.payment.precreate')
        ->and($request['notify_url'])->toBe('https://shop.test/kbz/callback')
        ->and($request['biz_content'])->toMatchArray(['merch_order_id' => 'ORDER_1', 'total_amount' => '1000', 'trade_type' => 'PWAAPP', 'trans_currency' => 'MMK'])
        ->and($request['sign'])->toBe($this->signer->sign(array_diff_key($request, ['biz_content' => true]) + $request['biz_content']));

    parse_str(substr($payment->url, strpos($payment->url, '?') + 1), $query);
    expect($payment->url)->toStartWith(KbzPayConfig::SANDBOX_PWA_URL.'?')
        ->and($payment->orderId)->toBe('ORDER_1')
        ->and($payment->gatewayReference)->toBe('PREPAY123')
        ->and($query['prepay_id'])->toBe('PREPAY123')
        ->and($query['sign'])->toBe($this->signer->sign(array_diff_key($query, ['sign' => true])));
});

it('returns the QR payload for the QR flow', function () {
    $http = mockHttp(jsonResponse(precreateResponse(['qrCode' => 'kbzpay://qr/abc'])));

    $payment = (new KbzPay($this->config, $http))->qr($this->data);

    expect(requestJson($http->getLastRequest())['Request']['biz_content']['trade_type'])->toBe('PAY_BY_QRCODE')
        ->and($payment->qrString)->toBe('kbzpay://qr/abc')
        ->and($payment->qrImage)->toBeNull();
});

it('returns signed order info for the in-app flow', function () {
    $http = mockHttp(jsonResponse(precreateResponse()));

    $payment = (new KbzPay($this->config, $http))->app($this->data);

    parse_str($payment->orderInfo, $fields);
    expect(array_keys($fields))->toBe(['appid', 'merch_code', 'nonce_str', 'prepay_id', 'timestamp'])
        ->and($payment->sign)->toBe($this->signer->sign($fields))
        ->and($payment->signType)->toBe('SHA256');
});

it('sends optional order fields inside biz_content', function () {
    $http = mockHttp(jsonResponse(precreateResponse()));

    (new KbzPay($this->config, $http))->pwa(new KbzPayPaymentData(
        orderId: 'ORDER_1', amount: 1000, callbackUrl: 'https://shop.test/cb', title: 'Shoes', timeoutMinutes: 30, callbackInfo: 'cart=9',
    ));

    expect(requestJson($http->getLastRequest())['Request']['biz_content'])
        ->toMatchArray(['title' => 'Shoes', 'timeout_express' => '30m', 'callback_info' => 'cart%3D9']);
});

it('throws the gateway error when precreate fails', function () {
    $http = mockHttp(jsonResponse(['Response' => ['result' => 'FAIL', 'code' => 'ORDER_ID_USED', 'msg' => 'Order id used']]));

    (new KbzPay($this->config, $http))->pwa($this->data);
})->throws(ApiException::class, '[ORDER_ID_USED] Order id used');

it('maps every queryorder trade status', function (string $tradeStatus, PaymentStatus $expected) {
    $http = mockHttp(jsonResponse(['Response' => [
        'result' => 'SUCCESS', 'code' => '0', 'merch_order_id' => 'ORDER_1', 'trade_status' => $tradeStatus, 'total_amount' => '1000', 'mm_order_id' => 'MM1',
    ]]));

    $result = (new KbzPay($this->config, $http))->status('ORDER_1');

    expect(requestJson($http->getLastRequest())['Request'])->toMatchArray(['method' => 'kbz.payment.queryorder', 'version' => '3.0'])
        ->and($result->status)->toBe($expected)
        ->and($result->gatewayStatus)->toBe(trim($tradeStatus))
        ->and($result->gatewayReference)->toBe('MM1')
        ->and($result->amount)->toBe('1000');
})->with([
    ['PAY_SUCCESS', PaymentStatus::Successful],
    [' PAY_SUCCESS', PaymentStatus::Successful],
    ['WAIT_PAY', PaymentStatus::Pending],
    ['PAYING', PaymentStatus::Pending],
    ['PAY_FAILED', PaymentStatus::Failed],
    ['ORDER_CLOSED', PaymentStatus::Canceled],
    ['ORDER_EXPIRED', PaymentStatus::Expired],
    ['SOMETHING_NEW', PaymentStatus::Unknown],
]);

it('verifies a callback and acknowledges it with plain success', function () {
    $callback = (new KbzPay($this->config, mockHttp()))->handleCallback(signedCallback($this->signer, [
        'appid' => 'kp123',
        'notify_time' => 1536637503,
        'merch_code' => '100001',
        'merch_order_id' => 'ORDER_1',
        'mm_order_id' => '0112345',
        'total_amount' => '1000',
        'trans_currency' => 'MMK',
        'trade_status' => 'PAY_SUCCESS',
        'callback_info' => 'title%3Diphonex',
        'nonce_str' => 'abc',
    ]));

    expect($callback->isSuccessful())->toBeTrue()
        ->and($callback->orderId)->toBe('ORDER_1')
        ->and($callback->gatewayReference)->toBe('0112345')
        ->and($callback->amount)->toBe('1000')
        ->and($callback->acknowledgement->body)->toBe('success')
        ->and($callback->acknowledgement->status)->toBe(200);
});

it('rejects a tampered callback', function () {
    $request = signedCallback($this->signer, ['merch_order_id' => 'ORDER_1', 'total_amount' => '1000', 'trade_status' => 'PAY_SUCCESS']);
    $payload = $request->parsedBody();
    $payload['Request']['total_amount'] = '1';

    (new KbzPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));
})->throws(SignatureVerificationException::class);

it('validates the order against KBZ limits', function (array $overrides, string $field) {
    try {
        new KbzPayPaymentData(...$overrides + ['orderId' => 'ORDER_1', 'amount' => 1000, 'callbackUrl' => 'https://shop.test/cb']);
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail('No validation error was thrown.');
})->with([
    'order id with dashes' => [['orderId' => 'ORDER-1'], 'orderId'],
    'order id too long' => [['orderId' => str_repeat('a', 41)], 'orderId'],
    'zero amount' => [['amount' => 0], 'amount'],
    'three decimals' => [['amount' => Amount::parse('1000.505')], 'amount'],
    'negative' => [['amount' => -5], 'amount'],
    'callback url with query' => [['callbackUrl' => 'https://shop.test/cb?x=1'], 'callbackUrl'],
    'timeout above 120' => [['timeoutMinutes' => 121], 'timeoutMinutes'],
]);

it('builds config from an array and names a missing key', function () {
    expect(KbzPayConfig::fromArray(['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c', 'sandbox' => 'false'])->apiUrl)
        ->toBe(KbzPayConfig::PRODUCTION_API_URL);

    KbzPayConfig::fromArray(['app_id' => 'a', 'merchant_code' => 'c']);
})->throws(ConfigurationException::class, '[app_key]');

it('normalizes the PWA url so the query always follows "#/"', function (string $pwaUrl) {
    expect((new KbzPayConfig('a', 'b', 'c', pwaUrl: $pwaUrl))->pwaUrl)->toBe('https://static.kbzpay.com/pgw/uat/pwa/#/');
})->with(['https://static.kbzpay.com/pgw/uat/pwa/#', 'https://static.kbzpay.com/pgw/uat/pwa/#/']);

it('sends decimal amounts as KBZ allows up to two decimal places', function () {
    $http = mockHttp(jsonResponse(precreateResponse(['qrCode' => 'qr'])));

    (new KbzPay($this->config, $http))->qr(new KbzPayPaymentData(orderId: 'ORDER_1', amount: Amount::parse('1000.50'), callbackUrl: 'https://shop.test/cb'));

    expect(requestJson($http->getLastRequest())['Request']['biz_content']['total_amount'])->toBe('1000.50');
});

it('accepts up to two decimal places, as KBZ documents', function () {
    expect((new KbzPayPaymentData('ORDER_1', Amount::parse('1000.5'), 'https://shop.test/cb'))->amount->toString())->toBe('1000.5');

    new KbzPayPaymentData('ORDER_1', Amount::parse('1000.505'), 'https://shop.test/cb');
})->throws(InvalidPaymentDataException::class, 'KBZ Pay accepts at most 2 decimal places; the amount field has 3.');

it('rejects a callback that carries nested values', function () {
    $request = signedCallback($this->signer, ['merch_order_id' => 'ORDER_1', 'total_amount' => '1000', 'trade_status' => 'PAY_SUCCESS']);
    $payload = $request->parsedBody();
    $payload['Request']['mm_order_id'] = ['forged'];

    (new KbzPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));
})->throws(SignatureVerificationException::class);

it('includes a "0" error code in the exception message', function () {
    (new KbzPay($this->config, mockHttp(jsonResponse(['Response' => ['result' => 'FAIL', 'code' => '0', 'msg' => 'odd']]))))->status('ORDER_1');
})->throws(ApiException::class, 'KBZ Pay queryorder failed: [0] odd');

it('rejects an order id followed by a newline', function () {
    new KbzPayPaymentData(orderId: "ORDER_1\n", amount: 1000, callbackUrl: 'https://shop.test/kbz/callback');
})->throws(InvalidPaymentDataException::class, 'orderId');

it('verifies a callback whose amount is a JSON number against its exact text', function () {
    $fields = ['merch_order_id' => 'ORDER_1', 'total_amount' => '1000.50', 'trade_status' => 'PAY_SUCCESS', 'sign_type' => 'SHA256'];
    $fields['sign'] = $this->signer->sign($fields);
    $body = str_replace('"total_amount":"1000.50"', '"total_amount":1000.50', (string) json_encode(['Request' => $fields]));

    $callback = (new KbzPay($this->config, mockHttp()))->handleCallback(new CallbackRequest($body));

    expect($callback->amount)->toBe('1000.50')->and($callback->isSuccessful())->toBeTrue();
});
