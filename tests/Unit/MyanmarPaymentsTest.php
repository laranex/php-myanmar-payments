<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;

it('builds each configured gateway once', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => ['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c'],
        'yoma_mmqr' => ['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h'],
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)->toBe($payments->kbzPay())
        ->and($payments->yomaMmqr())->toBeInstanceOf(YomaMmqr::class);
});

it('only complains about a gateway when it is used without configuration', function () {
    (new MyanmarPayments([], mockHttp()))->waveMoney();
})->throws(ConfigurationException::class, 'wave_money configuration is missing [merchant_id]');
