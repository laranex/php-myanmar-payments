<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\WavePay\WavePay;
use Laranex\PhpMyanmarPayments\WavePay\WavePayConfig;
use Laranex\PhpMyanmarPayments\WavePay\WavePayItem;
use Laranex\PhpMyanmarPayments\WavePay\WavePayPaymentData;

beforeEach(function () {
    $this->config = WavePayConfig::fromArray(gatewayConfig('wave_pay'));
    $this->data = new WavePayPaymentData(
        orderId: '100',
        callbackUrl: 'https://shop.test/wave/callback',
        returnUrl: 'https://shop.test/done',
        description: 'Order 100',
        items: [new WavePayItem('Shoes', 600), new WavePayItem('Socks', 400)],
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

it('posts a hashed JSON payment request and redirects to authenticate', function () {
    $http = mockHttp(jsonResponse(['message' => 'success', 'transaction_id' => 'enc/123+abc']));

    $payment = (new WavePay($this->config, $http))->initiate($this->data);

    $request = $http->getLastRequest();
    expect((string) $request->getUri())->toBe('https://payments.wavemoney.io/payment')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and(requestJson($request))->toBe([
            'merchant_id' => 'testmerchantID',
            'order_id' => '100',
            'merchant_reference_id' => 'ref-001',
            'frontend_result_url' => 'https://shop.test/done',
            'backend_result_url' => 'https://shop.test/wave/callback',
            'amount' => 1000,
            'time_to_live_in_seconds' => 300,
            'payment_description' => 'Order 100',
            'currency' => 'MMK',
            'hash' => hash_hmac('sha256', '300testmerchantID1001000https://shop.test/wave/callbackref-001', 'test-secret'),
            'merchant_name' => 'Shop',
            'items' => '[{"name":"Shoes","amount":600},{"name":"Socks","amount":400}]',
        ])
        ->and($payment->url)->toBe('https://payments.wavemoney.io/authenticate?transaction_id=enc%2F123%2Babc')
        ->and($payment->gatewayReference)->toBe('enc/123+abc');
});

it('sends the additional fields only when they are set', function () {
    $http = mockHttp(jsonResponse(['message' => 'success', 'transaction_id' => 'tx1']));
    $data = new WavePayPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WavePayItem('A', 10)], additionalField3: 'Info 1', additionalField4: '', additionalField5: 'Info 3');

    (new WavePay($this->config, $http))->initiate($data);

    $body = requestJson($http->getLastRequest());
    expect($body)->toMatchArray(['additional_field_3' => 'Info 1', 'additional_field_5' => 'Info 3'])
        ->and($body)->not->toHaveKey('additional_field_4');
});

it('enforces the documented field limits', function (string $field, string $value, int $max) {
    $arguments = ['orderId' => '100', 'callbackUrl' => 'https://shop.test/cb', 'returnUrl' => 'https://shop.test/done', 'description' => 'x', 'items' => [new WavePayItem('A', 10)]];

    expect(fn () => new WavePayPaymentData(...[...$arguments, $field => $value]))
        ->toThrow(fn (InvalidPaymentDataException $e) => expect($e->errors())->toBe([$field => "The {$field} field must not be greater than {$max} characters."]));
})->with([
    ['orderId', str_repeat('1', 101), 100],
    ['merchantReferenceId', str_repeat('r', 101), 100],
    ['callbackUrl', 'https://shop.test/'.str_repeat('a', 238), 255],
    ['returnUrl', 'https://shop.test/'.str_repeat('a', 238), 255],
    ['description', str_repeat('d', 256), 255],
    ['additionalField3', str_repeat('x', 51), 50],
    ['additionalField4', str_repeat('x', 51), 50],
    ['additionalField5', str_repeat('x', 51), 50],
]);

it('requests payments on the base URL and sends the customer to the base redirect URL', function () {
    $http = mockHttp(jsonResponse(['message' => 'success', 'transaction_id' => 'tx1']));
    $config = WavePayConfig::fromArray(gatewayConfig('wave_pay', ['base_url' => 'https://preprodpayments.wavemoney.io:8107/', 'base_redirect_url' => 'https://preprodpayments.wavemoney.io:8107/']));

    $payment = (new WavePay($config, $http))->initiate($this->data);

    expect((string) $http->getLastRequest()->getUri())->toBe('https://preprodpayments.wavemoney.io:8107/payment')
        ->and($payment->url)->toBe('https://preprodpayments.wavemoney.io:8107/authenticate?transaction_id=tx1');
});

it('surfaces Wave errors', function (int $status, array $body, string $code) {
    $http = mockHttp(jsonResponse($body, $status));

    try {
        (new WavePay($this->config, $http))->initiate($this->data);
    } catch (ApiException $e) {
        expect($e->httpStatus)->toBe($status)->and($e->gatewayCode)->toBe($code);

        return;
    }

    $this->fail('No exception thrown.');
})->with([
    'duplicate' => [409, ['message' => 'Record already exists'], 'Record already exists'],
    'bad hash' => [400, ['message' => 'INVALID_HASH'], 'INVALID_HASH'],
    'unknown merchant' => [404, ['message' => 'No record found'], 'No record found'],
    'validation' => [422, ['errors' => ['amount' => ['The amount must be an integer.']]], 'VALIDATION_ERROR'],
]);

it('defaults the amount to the item total and generates a unique reference per attempt', function () {
    $first = new WavePayPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WavePayItem('A', 250)]);
    $second = new WavePayPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WavePayItem('A', 250)]);

    expect($first->amount->toString())->toBe('250')
        ->and($first->merchantReferenceId)->not->toBe($second->merchantReferenceId);
});

it('hashes the callback in the documented order with null as "null"', function () {
    $vector = testVector('wave_pay/callback.json');
    $payload = $vector['payload'];
    $payload['hashValue'] = hash_hmac('sha256', $vector['hash_string'], $vector['secret_key']);

    $callback = (new WavePay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));

    expect($callback->isSuccessful())->toBeTrue()
        ->and($callback->orderId)->toBe('100')
        ->and($callback->gatewayReference)->toBe('360')
        ->and($callback->amount)->toBe('1000');
});

it('maps every documented callback status', function (string $status, PaymentStatus $expected) {
    $callback = (new WavePay($this->config, mockHttp()))->handleCallback(signWaveCallback([
        'status' => $status, 'merchantId' => 'testmerchantID', 'orderId' => '100', 'amount' => '1000', 'merchantReferenceId' => 'ref-001', 'timeToLiveSeconds' => 300,
    ]));

    expect($callback->status)->toBe($expected)->and($callback->gatewayStatus)->toBe($status);
})->with([
    ['PAYMENT_CONFIRMED', PaymentStatus::Successful],
    ['BILL_COLLECTION_FAILED', PaymentStatus::Failed],
    ['INSUFFICIENT_BALANCE', PaymentStatus::Failed],
    ['ACCOUNT_LOCKED', PaymentStatus::Failed],
    ['PAYMENT_REQUEST_CANCELLED', PaymentStatus::Canceled],
    ['TRANSACTION_TIMED_OUT', PaymentStatus::Expired],
    ['SCHEDULER_TRANSACTION_TIMED_OUT', PaymentStatus::Expired],
    ['NEW_STATUS', PaymentStatus::Unknown],
]);

it('falls back to merchantReferenceId when the callback orderId is missing, null or empty', function (array $orderId) {
    $callback = (new WavePay($this->config, mockHttp()))->handleCallback(signWaveCallback([
        'status' => 'PAYMENT_CONFIRMED', 'merchantReferenceId' => 'ref-001', 'amount' => '1000', ...$orderId,
    ]));

    expect($callback->orderId)->toBe('ref-001');
})->with([
    'missing' => [[]],
    'null' => [['orderId' => null]],
    'empty' => [['orderId' => '']],
]);

it('rejects a callback signed with another key', function () {
    (new WavePay($this->config, mockHttp()))->handleCallback(signWaveCallback(['status' => 'PAYMENT_CONFIRMED', 'orderId' => '100'], 'wrong'));
})->throws(SignatureVerificationException::class);

it('requires at least one item', function () {
    new WavePayPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'x', []);
})->throws(InvalidPaymentDataException::class, 'at least one item');

it('accepts http and non-standard port callback URLs', function (string $url) {
    expect((new WavePayPaymentData('100', $url, 'http://shop.test/done', 'x', [new WavePayItem('A', 250)]))->callbackUrl)->toBe($url);
})->with(['http://shop.test/cb', 'https://shop.test:8443/cb']);

it('rejects a callback whose signed fields are not scalar', function () {
    $request = signWaveCallback(['status' => 'PAYMENT_CONFIRMED', 'orderId' => '100', 'merchantReferenceId' => 'ref-001']);
    $payload = $request->parsedBody();
    $payload['orderId'] = ['100'];

    (new WavePay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));
})->throws(SignatureVerificationException::class);

it('rejects a time to live that is not greater than 0', function () {
    $message = 'The wave_pay configuration [time_to_live_in_seconds] must be a whole number greater than 0.';

    expect(fn () => new WavePayConfig('m', 's', 'Shop', 'https://payments.wavemoney.io', 'https://payments.wavemoney.io', timeToLiveSeconds: 0))->toThrow(ConfigurationException::class, $message)
        ->and(fn () => WavePayConfig::fromArray(gatewayConfig('wave_pay', ['time_to_live_in_seconds' => '-5'])))->toThrow(ConfigurationException::class, $message);
});

it('requires the URLs and the time to live', function (string $key) {
    expect(WavePayConfig::fromArray(gatewayConfig('wave_pay', ['time_to_live_in_seconds' => '600']))->timeToLiveSeconds)->toBe(600);

    expect(fn () => WavePayConfig::fromArray(gatewayConfig('wave_pay', [$key => ' '])))
        ->toThrow(ConfigurationException::class, "The wave_pay configuration is missing [{$key}].");
})->with(['base_url', 'base_redirect_url', 'time_to_live_in_seconds']);

it('generates a merchant reference id when an empty one is given', function () {
    $data = new WavePayPaymentData('100', 'https://shop.test/cb', 'https://shop.test/done', 'Order', [new WavePayItem('A', 1000)], merchantReferenceId: '');

    expect($data->merchantReferenceId)->toMatch('/^[0-9a-f]{32}$/');
});

it('hashes boolean and numeric callback values as their JSON text', function () {
    $fields = ['status', 'timeToLiveSeconds', 'merchantId', 'orderId', 'amount', 'backendResultUrl', 'merchantReferenceId', 'initiatorMsisdn', 'transactionId', 'paymentRequestId', 'requestTime'];
    $payload = ['status' => 'PAYMENT_CONFIRMED', 'timeToLiveSeconds' => '300', 'orderId' => '100', 'amount' => '1000.00', 'merchantReferenceId' => 'ref-001', 'initiatorMsisdn' => 'true'];
    $payload['hashValue'] = hash_hmac('sha256', implode('', array_map(fn ($field) => $payload[$field] ?? 'null', $fields)), 'test-secret');
    $body = strtr((string) json_encode($payload), ['"timeToLiveSeconds":"300"' => '"timeToLiveSeconds":300', '"amount":"1000.00"' => '"amount":1000.00', '"initiatorMsisdn":"true"' => '"initiatorMsisdn":true']);

    $callback = (new WavePay($this->config, mockHttp()))->handleCallback(new CallbackRequest($body));

    expect($callback->amount)->toBe('1000.00')->and($callback->isSuccessful())->toBeTrue();
});

it('rejects a correctly hashed callback whose values are malformed', function (array $override) {
    $payload = array_replace(['status' => 'PAYMENT_CONFIRMED', 'orderId' => '100', 'amount' => '1000', 'merchantReferenceId' => 'ref-001', 'transactionId' => '360'], $override);

    (new WavePay($this->config, mockHttp()))->handleCallback(signWaveCallback($payload));
})->with([
    'missing amount' => [['amount' => null]],
    'negative amount' => [['amount' => '-1000']],
    'amount with a trailing dot' => [['amount' => '1000.']],
    'amount with text' => [['amount' => '1000https']],
    'order id with a space' => [['orderId' => '100 1']],
    'order id with a separator-like value' => [['orderId' => '100:1000']],
    'reference id with a slash' => [['merchantReferenceId' => 'ref/001']],
    'neither order id nor reference id' => [['orderId' => null, 'merchantReferenceId' => '']],
])->throws(SignatureVerificationException::class, 'Wave Pay callback is malformed.');

it('accepts a decimal amount and order ids with "_" and "-"', function () {
    $callback = (new WavePay($this->config, mockHttp()))->handleCallback(signWaveCallback([
        'status' => 'PAYMENT_CONFIRMED', 'orderId' => 'ORDER_1-a', 'amount' => '1000.00', 'merchantReferenceId' => 'Ref_1-b',
    ]));

    expect($callback->orderId)->toBe('ORDER_1-a')->and($callback->amount)->toBe('1000.00');
});

it('only creates payments whose ids its own callback check accepts', function (string $orderId, ?string $reference, string $field) {
    expect(fn () => new WavePayPaymentData($orderId, 'https://shop.test/cb', 'https://shop.test/done', 'Order', [new WavePayItem('A', 1000)], merchantReferenceId: $reference))
        ->toThrow(fn (InvalidPaymentDataException $e) => expect($e->errors())->toBe([$field => "The {$field} field may only contain letters, numbers, underscores and dashes."]));
})->with([
    ['Order 1', 'ref-001', 'orderId'],
    ['100:1', 'ref-001', 'orderId'],
    ['100', 'ref/001', 'merchantReferenceId'],
]);
