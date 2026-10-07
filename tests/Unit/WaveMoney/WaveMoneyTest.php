<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoney;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyConfig;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyItem;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyPaymentData;

beforeEach(function () {
    $this->config = new WaveMoneyConfig(merchantId: 'testmerchantID', secretKey: 'test-secret', merchantName: 'Shop');
    $this->data = new WaveMoneyPaymentData(
        orderId: '100',
        callbackUrl: 'https://shop.test/wave/callback',
        returnUrl: 'https://shop.test/done',
        description: 'Order 100',
        items: [new WaveMoneyItem('Shoes', 600), new WaveMoneyItem('Socks', 400)],
        merchantReferenceId: 'ref-001',
    );
});

/**
 * @param  array<string, mixed>  $payload
 */
function signWaveCallback(array $payload, string $secret = 'test-secret'): CallbackRequest
{
    $fields = ['status', 'timeToLiveSeconds', 'merchantId', 'orderId', 'amount', 'backendResultUrl', 'merchantReferenceId', 'initiatorMsisdn', 'transactionId', 'paymentRequestId', 'requestTime'];
    $payload['hashValue'] = hash_hmac('sha256', implode('', array_map(fn ($field) => $payload[$field] ?? 'null', $fields)), $secret);

    return CallbackRequest::fromArray($payload);
}

it('posts a form encoded, hashed payment request and redirects to authenticate', function () {
    $http = mockHttp(jsonResponse(['message' => 'success', 'transaction_id' => 'enc/123+abc']));

    $payment = (new WaveMoney($this->config, $http))->initiate($this->data);

    $request = $http->getLastRequest();
    $form = requestForm($request);
    expect((string) $request->getUri())->toBe('https://testpayments.wavemoney.io:8107/payment')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($form)->toMatchArray(['order_id' => '100', 'merchant_reference_id' => 'ref-001', 'amount' => '1000', 'merchant_name' => 'Shop'])
        ->and(json_decode($form['items'], true))->toBe([['name' => 'Shoes', 'amount' => 600], ['name' => 'Socks', 'amount' => 400]])
        ->and($form['hash'])->toBe(hash_hmac('sha256', '300testmerchantID1001000https://shop.test/wave/callbackref-001', 'test-secret'))
        ->and($payment->url)->toBe('https://testpayments.wavemoney.io/authenticate?transaction_id=enc%2F123%2Babc')
        ->and($payment->gatewayReference)->toBe('enc/123+abc');
});

it('surfaces Wave errors', function (int $status, array $body, string $code) {
    $http = mockHttp(jsonResponse($body, $status));

    try {
        (new WaveMoney($this->config, $http))->initiate($this->data);
    } catch (ApiException $e) {
        expect($e->httpStatus)->toBe($status)->and($e->gatewayCode)->toBe($code);

        return;
    }

    $this->fail('No exception thrown.');
})->with([
    'duplicate' => [409, ['message' => 'Record already exists'], 'Record already exists'],
    'bad hash' => [400, ['message' => 'INVALID_HASH'], 'INVALID_HASH'],
    'validation' => [422, ['errors' => ['amount' => ['The amount must be an integer.']]], 'VALIDATION_ERROR'],
]);

it('defaults the amount to the item total and generates a unique reference per attempt', function () {
    $first = new WaveMoneyPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 250)]);
    $second = new WaveMoneyPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 250)]);

    expect($first->amount)->toBe(250)
        ->and($first->merchantReferenceId)->not->toBe($second->merchantReferenceId);
});

it('hashes the callback in the documented order with null as "null"', function () {
    $vector = testVector('wave_money/callback.json');
    $payload = $vector['payload'];
    $payload['hashValue'] = hash_hmac('sha256', $vector['hash_string'], $vector['secret_key']);

    $callback = (new WaveMoney($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));

    expect($callback->isSuccessful())->toBeTrue()
        ->and($callback->orderId)->toBe('100')
        ->and($callback->gatewayReference)->toBe('360')
        ->and($callback->amount)->toBe('1000');
});

it('maps every documented callback status', function (string $status, PaymentStatus $expected) {
    $callback = (new WaveMoney($this->config, mockHttp()))->handleCallback(signWaveCallback([
        'status' => $status, 'merchantId' => 'testmerchantID', 'orderId' => '100', 'amount' => '1000', 'merchantReferenceId' => 'ref-001', 'timeToLiveSeconds' => 300,
    ]));

    expect($callback->status)->toBe($expected)->and($callback->gatewayStatus)->toBe($status);
})->with([
    ['PAYMENT_CONFIRMED', PaymentStatus::Successful],
    ['INSUFFICIENT_BALANCE', PaymentStatus::Pending],
    ['ACCOUNT_LOCKED', PaymentStatus::Failed],
    ['BILL_COLLECTION_FAILED', PaymentStatus::Failed],
    ['PAYMENT_REQUEST_CANCELLED', PaymentStatus::Cancelled],
    ['TRANSACTION_TIMED_OUT', PaymentStatus::Expired],
    ['SCHEDULER_TRANSACTION_TIMED_OUT', PaymentStatus::Expired],
    ['NEW_STATUS', PaymentStatus::Unknown],
]);

it('falls back to merchantReferenceId when the callback has no orderId', function () {
    $callback = (new WaveMoney($this->config, mockHttp()))->handleCallback(signWaveCallback([
        'status' => 'PAYMENT_CONFIRMED', 'merchantReferenceId' => 'ref-001', 'amount' => '1000',
    ]));

    expect($callback->orderId)->toBe('ref-001');
});

it('rejects a callback signed with another key', function () {
    (new WaveMoney($this->config, mockHttp()))->handleCallback(signWaveCallback(['status' => 'PAYMENT_CONFIRMED', 'orderId' => '100'], 'wrong'));
})->throws(SignatureVerificationException::class);

it('requires at least one item', function () {
    new WaveMoneyPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', []);
})->throws(InvalidPaymentDataException::class, 'at least one item');

it('requires an HTTPS callback on the standard port, as Wave documents', function (string $url) {
    new WaveMoneyPaymentData('100', $url, 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 250)]);
})->with(['http://shop.test/cb', 'https://shop.test:8443/cb'])->throws(InvalidPaymentDataException::class, 'callbackUrl');
