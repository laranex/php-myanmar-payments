<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Http\Acknowledgement;
use Laranex\PhpMyanmarPayments\Results\AppPayment;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Results\PaymentCallback;
use Laranex\PhpMyanmarPayments\Results\PaymentStatusResult;
use Laranex\PhpMyanmarPayments\Results\QrPayment;
use Laranex\PhpMyanmarPayments\Results\RedirectPayment;

it('tags every result with its flow', function () {
    expect((new RedirectPayment('O1', 'https://pay.test'))->flow())->toBe(PaymentFlow::Redirect)
        ->and((new FormPayment('O1', 'https://pay.test', []))->flow())->toBe(PaymentFlow::Form)
        ->and((new QrPayment('O1'))->flow())->toBe(PaymentFlow::Qr)
        ->and((new AppPayment('O1', 'info', 'SIGN', 'SHA256'))->flow())->toBe(PaymentFlow::App);
});

it('renders an auto-submitting form with every value escaped', function () {
    $html = (new FormPayment('O1', 'https://pay.test/?a=1&b="2"', ['note' => '<script>"x"</script>', 'amount' => '1000'], 'multipart/form-data'))->toHtml();

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->toContain('action="https://pay.test/?a=1&amp;b=&quot;2&quot;"')
        ->toContain('enctype="multipart/form-data"')
        ->toContain('name="note" value="&lt;script&gt;&quot;x&quot;&lt;/script&gt;"')
        ->toContain('name="amount" value="1000"')
        ->toContain('document.getElementById("payment-form").submit();')
        ->not->toContain('<script>"x"');
});

it('returns a copy with the auto-submit url set', function () {
    $payment = new FormPayment('O1', 'https://pay.test', ['a' => 'b']);
    $copy = $payment->withAutoSubmitUrl('https://shop.test/pay/1');

    expect($payment->autoSubmitUrl)->toBeNull()
        ->and($copy->autoSubmitUrl)->toBe('https://shop.test/pay/1')
        ->and($copy->fields)->toBe(['a' => 'b'])
        ->and($copy->enctype)->toBe('application/x-www-form-urlencoded');
});

it('builds a data URI only when the gateway returned an image', function () {
    expect((new QrPayment('O1', qrString: 'payload'))->qrImageDataUri())->toBeNull()
        ->and((new QrPayment('O1', qrImage: 'AAAA'))->qrImageDataUri('image/jpeg'))->toBe('data:image/jpeg;base64,AAAA');
});

it('exposes the app payment values for the mobile app', function () {
    expect((new AppPayment('O1', 'appid=1', 'SIGN', 'SHA256', ['raw' => true]))->toArray())
        ->toBe(['orderId' => 'O1', 'orderInfo' => 'appid=1', 'sign' => 'SIGN', 'signType' => 'SHA256']);
});

it('knows which statuses are final', function (PaymentStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [PaymentStatus::Successful, true],
    [PaymentStatus::Pending, false],
    [PaymentStatus::Failed, true],
    [PaymentStatus::Canceled, true],
    [PaymentStatus::Expired, true],
    [PaymentStatus::Unknown, false],
]);

it('reports success only for the successful status', function () {
    $paid = new PaymentCallback('O1', PaymentStatus::Successful, 'PAY_SUCCESS');
    $pending = new PaymentStatusResult('O1', PaymentStatus::Pending, 'WAIT_PAY');

    expect($paid->isSuccessful())->toBeTrue()
        ->and($paid->acknowledgement())->toEqual(new Acknowledgement)
        ->and($pending->isSuccessful())->toBeFalse()
        ->and(new PaymentStatusResult('O1', PaymentStatus::Successful, 'PAY_SUCCESS'))->isSuccessful()->toBeTrue();
});
