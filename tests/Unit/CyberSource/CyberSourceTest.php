<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceConfig;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourcePaymentData;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceTransactionType;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

beforeEach(function () {
    $this->gateway = new CyberSource(new CyberSourceConfig(profileId: 'profile', accessKey: 'access', secretKey: 'secret'));
});

/**
 * @param  array<string, string>  $fields
 */
function cyberSourceSignature(array $fields, string $secret = 'secret'): string
{
    $pairs = array_map(fn ($name) => $name.'='.$fields[$name], explode(',', $fields['signed_field_names']));

    return base64_encode(hash_hmac('sha256', implode(',', $pairs), $secret, true));
}

/**
 * @param  array<string, string>  $overrides
 */
function cyberSourceCallback(array $overrides = []): CallbackRequest
{
    $fields = $overrides + [
        'decision' => 'ACCEPT',
        'req_reference_number' => 'ORDER-1',
        'transaction_id' => '7000000000000000000000',
        'auth_amount' => '1000.00',
        'req_amount' => '1000.00',
        'signed_field_names' => 'decision,req_reference_number,transaction_id,auth_amount,signed_field_names',
    ];
    $fields['signature'] = cyberSourceSignature($fields);

    return new CallbackRequest(http_build_query($fields), ['Content-Type' => 'application/x-www-form-urlencoded']);
}

it('signs the hosted checkout fields', function () {
    $payment = $this->gateway->initiate(new CyberSourcePaymentData(
        orderId: 'ORDER-1', amount: 1000, callbackUrl: 'https://shop.test/cs/callback', returnUrl: 'https://shop.test/done',
        transactionType: CyberSourceTransactionType::Authorization,
    ));

    expect($payment->action)->toBe('https://testsecureacceptance.cybersource.com/pay')
        ->and($payment->fields)->toMatchArray(['reference_number' => 'ORDER-1', 'amount' => '1000', 'transaction_type' => 'authorization', 'locale' => 'en-us', 'currency' => 'MMK'])
        ->and($payment->fields['signature'])->toBe(cyberSourceSignature($payment->fields));
});

it('reads the order id from req_reference_number and the decision as the status', function (string $decision, PaymentStatus $expected) {
    $callback = $this->gateway->handleCallback(cyberSourceCallback(['decision' => $decision]));

    expect($callback->orderId)->toBe('ORDER-1')
        ->and($callback->gatewayReference)->toBe('7000000000000000000000')
        ->and($callback->amount)->toBe('1000.00')
        ->and($callback->status)->toBe($expected);
})->with([
    ['ACCEPT', PaymentStatus::Successful],
    ['REVIEW', PaymentStatus::Pending],
    ['DECLINE', PaymentStatus::Failed],
    ['ERROR', PaymentStatus::Failed],
    ['CANCEL', PaymentStatus::Cancelled],
]);

it('rejects a callback whose signed fields are missing', function () {
    $fields = ['decision' => 'ACCEPT', 'signed_field_names' => 'decision,req_amount,signed_field_names', 'signature' => 'x'];

    $this->gateway->handleCallback(new CallbackRequest(http_build_query($fields)));
})->throws(SignatureVerificationException::class);

it('rejects a tampered callback', function () {
    parse_str(cyberSourceCallback()->body, $fields);
    $fields['auth_amount'] = '1.00';

    $this->gateway->handleCallback(new CallbackRequest(http_build_query($fields)));
})->throws(SignatureVerificationException::class);

it('accepts decimal amounts and other currencies, as the Secure Acceptance spec allows', function () {
    $payment = $this->gateway->initiate(new CyberSourcePaymentData(orderId: 'ORDER-2', amount: Amount::parse('10.50'), callbackUrl: 'https://shop.test/cb', currency: 'USD'));

    expect($payment->fields)->toMatchArray(['amount' => '10.50', 'currency' => 'USD']);
});

it('enforces the Secure Acceptance field rules', function (array $overrides, string $field) {
    try {
        new CyberSourcePaymentData(...$overrides + ['orderId' => 'ORDER-1', 'amount' => 1000, 'callbackUrl' => 'https://shop.test/cb']);
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail('No validation error was thrown.');
})->with([
    'http callback' => [['callbackUrl' => 'http://shop.test/cb'], 'callbackUrl'],
    'url over 255' => [['returnUrl' => 'https://shop.test/'.str_repeat('a', 250)], 'returnUrl'],
    'amount over 15 chars' => [['amount' => Amount::parse('1234567890123.45')], 'amount'],
    'plain en locale' => [['locale' => 'en'], 'locale'],
    'order id over 50' => [['orderId' => str_repeat('A', 51)], 'orderId'],
]);

it('accepts a zero amount, as Secure Acceptance allows', function () {
    expect($this->gateway->initiate(new CyberSourcePaymentData('ORDER-3', Amount::parse('0.00'), 'https://shop.test/cb'))->fields['amount'])->toBe('0.00');
});
