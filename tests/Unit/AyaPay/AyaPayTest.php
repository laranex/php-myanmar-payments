<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

beforeEach(function () {
    $this->config = AyaPayConfig::fromArray(gatewayConfig('aya_pay'));
    $this->vector = testVector('aya_pay/callback_payload.json');
});

/**
 * @param  array<string, mixed>  $payload
 * @return array{payload: string, checkSum: string, merchOrderId: string}
 */
function ayaSigned(array $payload, string $checksumString, string $secret = 'test-secret'): array
{
    return [
        'payload' => base64_encode((string) json_encode($payload)),
        'checkSum' => hash_hmac('sha256', $checksumString, $secret),
        'merchOrderId' => (string) $payload['merchOrderId'],
    ];
}

it('signs the hosted checkout form in the documented field order', function () {
    $payment = (new AyaPay($this->config, mockHttp()))->initiate(new AyaPayPaymentData(
        orderId: 'ORD123456', amount: 1000, channel: 'kbz_pay', method: AyaPayMethod::Qr,
        returnUrl: 'https://shop.test/done', description: 'Order', userRefs: ['cart-9'],
    ));

    $fields = $payment->fields;
    $expected = hash_hmac('sha256', implode(':', [
        'ORD123456', '1000', 'app-key', $fields['timestamp'], 'cart-9', '', '', '', '', 'Order', '104', 'kbz_pay', 'QR', 'https://shop.test/done',
    ]), 'test-secret');

    expect($payment->action)->toBe('https://pgw.ayainnovation.com/v1/payment/request')
        ->and($payment->enctype)->toBe('multipart/form-data')
        ->and($fields['channel'])->toBe('kbz_pay')
        ->and($fields['checkSum'])->toBe($expected)
        ->and($payment->toHtml())->toContain('name="checkSum" value="'.$expected.'"');
});

it('lists the enabled payment services', function () {
    $http = mockHttp(jsonResponse(['status' => '00', 'message' => 'success', 'data' => [
        ['name' => 'AYA Pay', 'key' => 'aya_pay', 'image_url' => 'https://img.test/aya.png', 'methods' => ['QR', 'NOTI']],
        ['name' => 'JCB', 'key' => 'jcb', 'image_url' => null, 'methods' => ['WEB', 'TOKEN']],
    ]]));

    $services = (new AyaPay($this->config, $http))->services();

    $request = requestJson($http->getLastRequest());
    expect($request['checkSum'])->toBe(hash_hmac('sha256', "app-key:test-secret:{$request['timestamp']}", 'test-secret'))
        ->and($services)->toHaveCount(2)
        ->and($services[0]->key)->toBe('aya_pay')
        ->and($services[0]->supports(AyaPayMethod::Qr))->toBeTrue()
        ->and($services[1]->methods)->toBe([AyaPayMethod::Web])
        ->and($services[1]->unknownMethods)->toBe(['TOKEN']);
});

it('verifies the callback against the fixed field order, not the JSON key order', function () {
    $payload = array_reverse($this->vector['payload'], true);

    $callback = (new AyaPay($this->config, mockHttp()))->handleCallback(
        CallbackRequest::fromArray(ayaSigned($payload, $this->vector['checksum_string'])),
    );

    expect($callback->isSuccessful())->toBeTrue()
        ->and($callback->orderId)->toBe('ORD123456')
        ->and($callback->gatewayReference)->toBe('TRN0001')
        ->and($callback->amount)->toBe('1000');
});

it('maps every documented status code', function (string $code, PaymentStatus $expected) {
    $payload = ['statusCode' => $code] + $this->vector['payload'];
    $checksum = preg_replace('/:00:/', ":{$code}:", $this->vector['checksum_string'], 1);

    $callback = (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(ayaSigned($payload, $checksum)));

    expect($callback->status)->toBe($expected);
})->with([
    ['00', PaymentStatus::Successful],
    ['01', PaymentStatus::Pending],
    ['02', PaymentStatus::Failed],
    ['03', PaymentStatus::Failed],
    ['04', PaymentStatus::Expired],
    ['99', PaymentStatus::Unknown],
]);

it('verifies the signed return redirect query string', function () {
    $query = ayaSigned($this->vector['payload'], $this->vector['checksum_string']);

    $callback = (new AyaPay($this->config, mockHttp()))->verifyRedirect(new CallbackRequest(query: $query));

    expect($callback->orderId)->toBe('ORD123456');
});

it('rejects a tampered callback', function () {
    $signed = ayaSigned($this->vector['payload'], $this->vector['checksum_string']);
    $signed['payload'] = base64_encode((string) json_encode(['amount' => '1'] + $this->vector['payload']));

    (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($signed));
})->throws(SignatureVerificationException::class);

it('verifies the enquiry response payload', function () {
    $http = mockHttp(jsonResponse(['status' => '00', 'message' => 'success', 'data' => ayaSigned($this->vector['payload'], $this->vector['checksum_string'])]));

    $result = (new AyaPay($this->config, $http))->status('ORD123456');

    $request = requestJson($http->getLastRequest());
    expect($request['checkSum'])->toBe(hash_hmac('sha256', "ORD123456:{$request['timestamp']}:app-key", 'test-secret'))
        ->and($result->isSuccessful())->toBeTrue()
        ->and($result->gatewayReference)->toBe('TRN0001');
});

it('throws AYA API errors with their code', function () {
    (new AyaPay($this->config, mockHttp(jsonResponse(['status' => '20', 'message' => 'Transaction not found']))))->status('ORD123456');
})->throws(ApiException::class, '[20] Transaction not found');

it('requires a 6 to 40 character order id', function () {
    new AyaPayPaymentData(orderId: 'A1', amount: 1000, channel: 'aya_pay', method: AyaPayMethod::Qr);
})->throws(InvalidPaymentDataException::class, 'between 6 and 40');

it('allows at most five user references', function () {
    new AyaPayPaymentData(orderId: 'ORD123456', amount: 1000, channel: 'aya_pay', method: AyaPayMethod::Qr, userRefs: ['1', '2', '3', '4', '5', '6']);
})->throws(InvalidPaymentDataException::class, 'more than 5');

it('verifies a payload that leaves out fields, as AYA does for wallet payments', function () {
    $payload = [
        'merchOrderId' => 'LXAYA1007184542', 'tranId' => 'C17913987426393655', 'amount' => '1000', 'currenyCode' => '104', 'statusCode' => '01',
        'paymentCardNumber' => null, 'paymentMobileNumber' => null, 'userRef1' => null, 'userRef2' => null, 'userRef3' => null,
        'userRef4' => null, 'userRef5' => null, 'description' => null, 'dateTime' => '2026-10-08 01:15:43',
    ];
    $checksumString = 'LXAYA1007184542:C17913987426393655:1000:104:01:::::::::2026-10-08 01:15:43';

    $callback = (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(ayaSigned($payload, $checksumString)));

    expect($callback->status)->toBe(PaymentStatus::Pending)->and($callback->gatewayReference)->toBe('C17913987426393655');
});

it('rejects a callback whose payload or checksum is not a string', function (string $field) {
    $signed = ayaSigned($this->vector['payload'], $this->vector['checksum_string']);
    $signed[$field] = [$signed[$field]];

    (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($signed));
})->with(['payload', 'checkSum'])->throws(SignatureVerificationException::class);

it('throws when the enquiry payload checksum does not match', function () {
    $signed = ayaSigned($this->vector['payload'], $this->vector['checksum_string'], 'another-secret');

    (new AyaPay($this->config, mockHttp(jsonResponse(['status' => '00', 'message' => 'success', 'data' => $signed]))))->status('ORD123456');
})->throws(SignatureVerificationException::class, 'AYA Pay enquiry response checksum verification failed.');

it('reports the HTTP status when AYA answers without a status code', function () {
    (new AyaPay($this->config, mockHttp(jsonResponse([], 502))))->services();
})->throws(ApiException::class, 'AYA Pay services failed with HTTP 502.');

it('reads a return payload whose "+" arrived as a space', function () {
    $payload = ['merchOrderId' => 'ORD123456', 'tranId' => 'T1', 'amount' => '1000', 'currenyCode' => '104', 'statusCode' => '00', 'description' => '~~~>>>'];
    $signed = ayaSigned($payload, 'ORD123456:T1:1000:104:00:~~~>>>');
    expect($signed['payload'])->toContain('+');

    $query = ['payload' => str_replace('+', ' ', $signed['payload']), 'checkSum' => $signed['checkSum']];

    expect((new AyaPay($this->config, mockHttp()))->verifyRedirect(new CallbackRequest(query: $query))->isSuccessful())->toBeTrue();
});

it('verifies a payload whose amount is a JSON number against its exact text', function () {
    $json = '{"merchOrderId":"ORD123456","tranId":"T1","amount":1000.00,"currenyCode":104,"statusCode":"00","approved":true}';
    $input = ['payload' => base64_encode($json), 'checkSum' => hash_hmac('sha256', 'ORD123456:T1:1000.00:104:00', 'test-secret')];

    $callback = (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray($input));

    expect($callback->amount)->toBe('1000.00')->and($callback->isSuccessful())->toBeTrue();
});

it('rejects a genuine failed payload re-split to read as successful', function () {
    // AYA signs a failed payment (statusCode 02) whose dateTime ends in ":00".
    $genuine = ['merchOrderId' => 'ORD123456', 'tranId' => 'TRN0001', 'amount' => '1000', 'currenyCode' => '104', 'statusCode' => '02', 'description' => 'Order', 'dateTime' => '2026-10-07 10:00:00'];
    $checksumString = 'ORD123456:TRN0001:1000:104:02:Order:2026-10-07 10:00:00';
    $gateway = new AyaPay($this->config, mockHttp());

    expect($gateway->handleCallback(CallbackRequest::fromArray(ayaSigned($genuine, $checksumString)))->status)->toBe(PaymentStatus::Failed);

    // The attacker re-splits the same signed string so that statusCode reads the "00" seconds of the time.
    $forged = ['merchOrderId' => 'ORD123456', 'tranId' => 'TRN0001', 'amount' => '1000', 'currenyCode' => '104:02:Order:2026-10-07 10', 'statusCode' => '00', 'description' => '00'];

    expect(fn () => $gateway->handleCallback(CallbackRequest::fromArray(ayaSigned($forged, $checksumString))))
        ->toThrow(SignatureVerificationException::class, 'AYA Pay callback payload is malformed.');
});

it('rejects a callback without a field the result is read from', function (string $field) {
    $payload = $this->vector['payload'];
    $payload[$field] = '';
    $checksumString = implode(':', array_map(fn (mixed $value): string => (string) $value, array_values($payload)));

    (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(ayaSigned($payload, $checksumString)));
})->with(['merchOrderId', 'tranId', 'amount', 'currenyCode', 'statusCode'])->throws(SignatureVerificationException::class, 'malformed');

it('rejects a callback whose result fields are not strictly formatted', function (array $override) {
    $payload = array_replace($this->vector['payload'], $override);
    $checksumString = implode(':', array_map(fn (mixed $value): string => (string) $value, array_values($payload)));

    (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(ayaSigned($payload, $checksumString)));
})->with([
    'status code with a letter' => [['statusCode' => '0A']],
    'three-digit status code' => [['statusCode' => '000']],
    'other currency' => [['currenyCode' => '840']],
    'two-digit currency' => [['currenyCode' => '10']],
    'negative amount' => [['amount' => '-1000']],
    'amount with two dots' => [['amount' => '1.0.0']],
    'amount with a trailing dot' => [['amount' => '1000.']],
    'colon in a user reference' => [['userRef1' => 'a:b']],
    'colon in the description' => [['description' => 'Order: 1']],
    'colon in the order id' => [['merchOrderId' => 'ORD:1']],
    'malformed date time' => [['dateTime' => '2026-10-07T10:00:00']],
    'date time with extra parts' => [['dateTime' => '2026-10-07 10:00:00:00']],
])->throws(SignatureVerificationException::class, 'malformed');

it('reads the currencyCode spelling and an empty date time', function () {
    $payload = ['merchOrderId' => 'ORD123456', 'tranId' => 'T1', 'amount' => '1000.50', 'currencyCode' => '104', 'statusCode' => '00', 'dateTime' => ''];

    $callback = (new AyaPay($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray(ayaSigned($payload, 'ORD123456:T1:1000.50:104:00:')));

    expect($callback->isSuccessful())->toBeTrue()->and($callback->amount)->toBe('1000.50');
});

it('rejects a ":" in the signed order fields', function (array $data, string $field) {
    $build = fn () => new AyaPayPaymentData(...$data + ['orderId' => 'ORD123456', 'amount' => 1000, 'channel' => 'aya_pay', 'method' => AyaPayMethod::Qr]);

    expect($build)->toThrow(fn (InvalidPaymentDataException $e) => expect($e->errors())->toBe([$field => "The {$field} field must not contain \":\"."]));
})->with([
    [['orderId' => 'ORD:123456'], 'orderId'],
    [['description' => 'Order: 1'], 'description'],
    [['userRefs' => ['cart', 'a:b']], 'userRefs'],
]);
