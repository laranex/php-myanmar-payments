<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniApp;
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniAppConfig;
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniAppItem;
use Laranex\PhpMyanmarPayments\WavePayMiniApp\WavePayMiniAppPaymentData;

beforeEach(function () {
    $this->config = WavePayMiniAppConfig::fromArray(gatewayConfig('wave_pay_mini_app'));
    $this->callbackUrl = 'https://shop.test/wave-pay-mini-app/callback';
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function miniAppCallback(array $overrides = [], string $callbackUrl = 'https://shop.test/wave-pay-mini-app/callback'): array
{
    $payload = array_replace(['merchantRefId' => 'ref-1', 'orderId' => 'ORDER_1', 'amount' => '5000', 'msisdn' => '9791009039', 'transactionId' => 'T1', 'transactionStatus' => 'successful'], $overrides);
    $payload['hashValue'] = hash_hmac('sha256', $payload['amount'].$payload['orderId'].'MINI456'.$payload['merchantRefId'].$callbackUrl, 'test-secret');

    return $payload;
}

it('signs the makePayment payload without calling Wave', function () {
    $http = mockHttp();
    $payment = (new WavePayMiniApp($this->config, $http))->payment(new WavePayMiniAppPaymentData(
        orderId: 'ORDER123',
        callbackUrl: 'https://yourdomain.com/callback',
        items: [new WavePayMiniAppItem('Ticket', Amount::kyat(3000)), new WavePayMiniAppItem('Snack', 2000)],
        merchantReferenceId: 'MERCHANT789',
    ));

    expect($http->getRequests())->toBe([])
        ->and($payment->flow())->toBe(PaymentFlow::MiniApp)
        ->and([$payment->orderId, $payment->amount->toString(), $payment->miniAppId, $payment->merchantReferenceId, $payment->callbackUrl])
        ->toBe(['ORDER123', '5000', 'MINI456', 'MERCHANT789', 'https://yourdomain.com/callback'])
        ->and($payment->hashValue)->toBe(hash_hmac('sha256', '5000ORDER123MINI456MERCHANT789https://yourdomain.com/callback', 'test-secret'))
        ->and($payment->items[1]['amount']->toString())->toBe('2000')
        ->and(array_keys($payment->toArray()))->toBe(['amount', 'miniAppID', 'merchantRefID', 'orderID', 'callBackUrl', 'hashValue', 'items'])
        ->and($payment->toArray()['items'])->toBe([['name' => 'Ticket', 'amount' => 3000], ['name' => 'Snack', 'amount' => 2000]]);
});

it('generates a 32-character merchant reference when none is given', function () {
    $first = new WavePayMiniAppPaymentData('ORDER_1', $this->callbackUrl, [new WavePayMiniAppItem('A', 1000)]);
    $second = new WavePayMiniAppPaymentData('ORDER_1', $this->callbackUrl, [new WavePayMiniAppItem('A', 1000)], merchantReferenceId: '');

    expect($first->merchantReferenceId)->toMatch('/^[0-9a-f]{32}$/')
        ->and($second->merchantReferenceId)->toMatch('/^[0-9a-f]{32}$/')
        ->and($first->merchantReferenceId)->not->toBe($second->merchantReferenceId);
});

it('requires a list of items', function (array $items) {
    expect(fn () => new WavePayMiniAppPaymentData('ORDER_1', $this->callbackUrl, $items, 1000))
        ->toThrow(fn (InvalidPaymentDataException $e) => expect($e->errors())->toBe(['items' => 'The items field must be a list of '.WavePayMiniAppItem::class.'.']));
})->with([
    'not an item' => [['x']],
    'not a list' => [['a' => new WavePayMiniAppItem('A', 1000)]],
]);

it('validates the data again on demand', function () {
    $data = new WavePayMiniAppPaymentData('ORDER_1', $this->callbackUrl, [new WavePayMiniAppItem('A', 1000)]);

    $data->validate();

    expect($data->amount->toString())->toBe('1000');
});

it('requests the access token with the client credentials and caches it', function () {
    $cache = new ArrayCache;
    $http = mockHttp(jsonResponse(['token_type' => 'Bearer', 'access_token' => 'token-1', 'expires_in' => 300]));
    $gateway = new WavePayMiniApp($this->config, $http, $cache);

    expect($gateway->accessToken())->toBe('token-1')
        ->and($gateway->accessToken())->toBe('token-1')
        ->and($http->getRequests())->toHaveCount(1)
        ->and((string) $http->getLastRequest()->getUri())->toBe('https://api.wavemoney.io:8100/auth/v1/external-utility/token')
        ->and(requestForm($http->getLastRequest()))->toBe(['client_id' => 'client', 'client_secret' => 'client-secret', 'grant_type' => 'client_credentials']);
});

it('throws when the token request fails', function () {
    (new WavePayMiniApp($this->config, mockHttp(jsonResponse(['error' => 'invalid_client', 'error_description' => 'Bad client'], 401))))->accessToken();
})->throws(ApiException::class, 'Wave Pay Mini App token request failed: [invalid_client] Bad client');

it('maps the callback status', function (string $status, PaymentStatus $expected) {
    $callback = (new WavePayMiniApp($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(miniAppCallback(['transactionStatus' => $status])), $this->callbackUrl);

    expect($callback->status)->toBe($expected)
        ->and($callback->orderId)->toBe('ORDER_1')
        ->and($callback->gatewayReference)->toBe('T1')
        ->and($callback->amount)->toBe('5000')
        ->and($callback->raw['merchantRefId'])->toBe('ref-1')
        ->and($callback->acknowledgement->status)->toBe(200)
        ->and($callback->acknowledgement->body)->toBe('');
})->with([
    ['successful', PaymentStatus::Successful],
    ['PROCESSING', PaymentStatus::Pending],
    [' failed ', PaymentStatus::Failed],
    ['PAYMENT_COMPLETED', PaymentStatus::Unknown],
]);

it('rejects a callback signed for another callback URL', function () {
    (new WavePayMiniApp($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(miniAppCallback(callbackUrl: 'https://shop.test/other')), $this->callbackUrl);
})->throws(SignatureVerificationException::class, 'Wave Pay Mini App callback hash verification failed.');

it('rejects a malformed callback after the hash matches', function () {
    (new WavePayMiniApp($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(miniAppCallback(['amount' => '50.5'])), $this->callbackUrl);
})->throws(SignatureVerificationException::class, 'Wave Pay Mini App callback is malformed.');

it('reads its configuration from the environment and trims the base URL', function () {
    $env = [
        'WAVE_PAY_MINI_APP_CLIENT_ID' => 'c', 'WAVE_PAY_MINI_APP_CLIENT_SECRET' => 's', 'WAVE_PAY_MINI_APP_MINI_APP_ID' => 'm',
        'WAVE_PAY_MINI_APP_SECRET_KEY' => 'k', 'WAVE_PAY_MINI_APP_BASE_URL' => 'https://preprodnewapi.wavemoney.io/', 'MYANMAR_PAYMENTS_HTTP_TIMEOUT' => '20',
    ];
    $gateway = WavePayMiniApp::fromEnv($env, mockHttp());

    expect([$gateway->config->clientId, $gateway->config->clientSecret, $gateway->config->miniAppId, $gateway->config->secretKey, $gateway->config->baseUrl, $gateway->config->timeoutSeconds])
        ->toBe(['c', 's', 'm', 'k', 'https://preprodnewapi.wavemoney.io', 20])
        ->and(WavePayMiniAppConfig::PRODUCTION_URL)->toBe('https://api.wavemoney.io:8100')
        ->and(MyanmarPayments::fromEnv($env, mockHttp())->wavePayMiniApp()->config->miniAppId)->toBe('m');
});

it('rejects blank settings given to the constructor', function () {
    new WavePayMiniAppConfig('c', 's', ' ', 'k', 'https://api.wavemoney.io:8100');
})->throws(ConfigurationException::class, 'The wave_pay_mini_app configuration is missing [mini_app_id].');

it('builds the gateway once from the facade, from an array or a config object', function () {
    $payments = new MyanmarPayments(['wave_pay_mini_app' => gatewayConfig('wave_pay_mini_app')], mockHttp());
    $fromObject = new MyanmarPayments(['wave_pay_mini_app' => $this->config], mockHttp());

    expect($payments->wavePayMiniApp())->toBeInstanceOf(WavePayMiniApp::class)->toBe($payments->wavePayMiniApp())
        ->and($fromObject->wavePayMiniApp()->config)->toBe($this->config)
        ->and(fn () => (new MyanmarPayments([], mockHttp()))->wavePayMiniApp())->toThrow(ConfigurationException::class, 'wave_pay_mini_app configuration is missing [client_id]');
});
