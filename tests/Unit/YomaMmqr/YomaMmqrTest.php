<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrConfig;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrPaymentData;

beforeEach(function () {
    $this->config = new YomaMmqrConfig(merchantId: 'M001', clientId: 'client', clientSecret: 'secret', webhookHashKey: 'hash-key');
});

function tokenResponse(string $token = 'token-1'): Response
{
    return jsonResponse(['access_token' => $token, 'scope' => 'default', 'token_type' => 'Bearer', 'expires_in' => 28800]);
}

it('checks out the order, generates the QR and returns the image with its expiry', function () {
    $http = mockHttp(
        tokenResponse(),
        jsonResponse(['checkOutStatus' => true, 'errorCode' => null, 'errorDescription' => null]),
        jsonResponse(['refLabel' => '100000083331', 'qrString' => 'iVBORw0KGgo=', 'errorCode' => null]),
    );

    $qr = (new YomaMmqr($this->config, $http))->initiate(new YomaMmqrPaymentData('ORD-2026-001', 1000, 'Order 1'));

    [$token, $checkout, $generate] = $http->getRequests();
    expect((string) $token->getUri())->toBe('https://devapi.yomabank.net/token')
        ->and($token->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('client:secret'))
        ->and(requestForm($token))->toBe(['grant_type' => 'client_credentials'])
        ->and((string) $checkout->getUri())->toBe('https://devapi.yomabank.net/payment-gateway/v1rc/api/payment/checkout')
        ->and($checkout->getHeaderLine('Authorization'))->toBe('Bearer token-1')
        ->and(requestJson($checkout))->toBe(['merchantId' => 'M001', 'orderNumber' => 'ORD-2026-001', 'amount' => '1000', 'description' => 'Order 1'])
        ->and(requestJson($generate))->toBe(['merchantId' => 'M001', 'orderNumber' => 'ORD-2026-001'])
        ->and($qr->qrImage)->toBe('iVBORw0KGgo=')
        ->and($qr->qrString)->toBeNull()
        ->and($qr->reference)->toBe('100000083331')
        ->and($qr->qrImageDataUri())->toBe('data:image/png;base64,iVBORw0KGgo=')
        ->and($qr->expiresAt->getTimestamp())->toBeGreaterThanOrEqual(time() + 119);
});

it('reuses the cached token across calls', function () {
    $cache = new ArrayCache;
    $http = mockHttp(
        tokenResponse(),
        jsonResponse(['refLabel' => '1', 'qrString' => 'a', 'errorCode' => null]),
        jsonResponse(['refLabel' => '1', 'paymentStatus' => 'PENDING', 'errorCode' => null]),
    );

    (new YomaMmqr($this->config, $http, $cache))->renewQr('ORD-1');
    (new YomaMmqr($this->config, $http, $cache))->status('1');

    expect($http->getRequests())->toHaveCount(3);
});

it('refreshes the token once when Yoma answers 401', function () {
    $http = mockHttp(
        tokenResponse('old'),
        jsonResponse(['message' => 'Unauthorized'], 401),
        tokenResponse('new'),
        jsonResponse(['refLabel' => '1', 'paymentStatus' => 'SUCCESS', 'errorCode' => null]),
    );

    $result = (new YomaMmqr($this->config, $http))->status('1');

    expect($http->getLastRequest()->getHeaderLine('Authorization'))->toBe('Bearer new')
        ->and($result->isSuccessful())->toBeTrue();
});

it('treats a business error sent with HTTP 200 as a failure', function () {
    $http = mockHttp(tokenResponse(), jsonResponse(['checkOutStatus' => false, 'errorCode' => 'PAYMENT ALREADY EXISTS', 'errorDescription' => 'Payment already exists']));

    (new YomaMmqr($this->config, $http))->initiate(new YomaMmqrPaymentData('ORD-1', 1000, 'Order 1'));
})->throws(ApiException::class, '[PAYMENT ALREADY EXISTS] Payment already exists');

it('maps status check results', function (array $body, PaymentStatus $expected) {
    $http = mockHttp(tokenResponse(), jsonResponse($body + ['refLabel' => '1']));

    expect((new YomaMmqr($this->config, $http))->status('1')->status)->toBe($expected);
})->with([
    [['paymentStatus' => 'SUCCESS', 'errorCode' => null], PaymentStatus::Successful],
    [['paymentStatus' => 'PENDING', 'errorCode' => null], PaymentStatus::Pending],
    [['paymentStatus' => 'FAILED', 'errorCode' => null], PaymentStatus::Failed],
    [['paymentStatus' => null, 'errorCode' => 'QR EXPIRED', 'errorDescription' => 'Qr has been expired.'], PaymentStatus::Expired],
]);

it('verifies the callback hash keyed with the order number and hash key', function (string $status, PaymentStatus $expected) {
    $hash = hash_hmac('sha256', "orderNumber=ORD-2026-001235&status={$status}", 'ORD-2026-001235hash-key');

    $callback = (new YomaMmqr($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray([
        'orderNumber' => 'ORD-2026-001235', 'status' => $status, 'hashValue' => $hash,
    ]));

    expect($callback->orderId)->toBe('ORD-2026-001235')->and($callback->status)->toBe($expected);
})->with([
    ['success', PaymentStatus::Successful],
    ['fail', PaymentStatus::Failed],
]);

it('rejects a callback with a wrong hash', function () {
    (new YomaMmqr($this->config, mockHttp()))->handleCallback(CallbackRequest::fromArray([
        'orderNumber' => 'ORD-1', 'status' => 'success', 'hashValue' => hash_hmac('sha256', 'orderNumber=ORD-1&status=fail', 'ORD-1hash-key'),
    ]));
})->throws(SignatureVerificationException::class);

it('checks the webhook secret header when one is configured', function () {
    $config = new YomaMmqrConfig(merchantId: 'M001', clientId: 'c', clientSecret: 's', webhookHashKey: 'hash-key', webhookSecret: 'shared');
    $payload = ['orderNumber' => 'ORD-1', 'status' => 'success', 'hashValue' => hash_hmac('sha256', 'orderNumber=ORD-1&status=success', 'ORD-1hash-key')];

    expect((new YomaMmqr($config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload, ['X-Webhook-Secret' => 'shared']))->isSuccessful())->toBeTrue();

    (new YomaMmqr($config, mockHttp()))->handleCallback(CallbackRequest::fromArray($payload));
})->throws(SignatureVerificationException::class, 'X-Webhook-Secret');

it('enforces the order number and description limits', function () {
    new YomaMmqrPaymentData(str_repeat('A', 21), 1000, str_repeat('d', 51));
})->throws(InvalidPaymentDataException::class);

it('defaults to the production payment hub when not in sandbox', function () {
    expect((new YomaMmqrConfig(merchantId: 'M', clientId: 'c', clientSecret: 's', webhookHashKey: 'h', sandbox: false))->baseUrl)
        ->toBe('https://paymenthubapi.yomabank.com');
});
