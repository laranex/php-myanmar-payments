<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoney;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;

it('builds each configured gateway once', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => ['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c'],
        'yoma_mmqr' => ['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h'],
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)->toBe($payments->kbzPay())
        ->and($payments->yomaMmqr())->toBeInstanceOf(YomaMmqr::class);
});

it('builds every gateway from one configuration array', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => ['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c'],
        'wave_money' => ['merchant_id' => 'm', 'secret_key' => 's', 'merchant_name' => 'Shop'],
        'aya_pay' => ['app_key' => 'k', 'app_secret' => 's'],
        'yoma_mmqr' => ['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h'],
        'cyber_source' => ['profile_id' => 'p', 'access_key' => 'a', 'secret_key' => 's'],
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)
        ->and($payments->waveMoney())->toBeInstanceOf(WaveMoney::class)->toBe($payments->waveMoney())
        ->and($payments->ayaPay())->toBeInstanceOf(AyaPay::class)->toBe($payments->ayaPay())
        ->and($payments->yomaMmqr())->toBeInstanceOf(YomaMmqr::class)->toBe($payments->yomaMmqr())
        ->and($payments->cyberSource())->toBeInstanceOf(CyberSource::class)->toBe($payments->cyberSource());
});

it('reports the missing key for each unconfigured gateway', function (string $method, string $message) {
    $payments = new MyanmarPayments([], mockHttp());

    expect(fn () => $payments->{$method}())->toThrow(ConfigurationException::class, $message);
})->with([
    ['kbzPay', 'kbz_pay configuration is missing [app_id]'],
    ['ayaPay', 'aya_pay configuration is missing [app_key]'],
    ['yomaMmqr', 'yoma_mmqr configuration is missing [merchant_id]'],
    ['cyberSource', 'cyber_source configuration is missing [profile_id]'],
]);

it('only complains about a gateway when it is used without configuration', function () {
    (new MyanmarPayments([], mockHttp()))->waveMoney();
})->throws(ConfigurationException::class, 'wave_money configuration is missing [merchant_id]');

it('reads the sandbox flag from common boolean strings', function (mixed $value, bool $sandbox) {
    expect(AyaPayConfig::fromArray(['app_key' => 'k', 'app_secret' => 's', 'sandbox' => $value])->sandbox)->toBe($sandbox);
})->with([
    ['false', false], ['FALSE', false], ['0', false], ['f', false], ['no', false], [' off ', false], [false, false], [0, false],
    ['true', true], ['1', true], ['t', true], ['yes', true], ['on', true], [true, true],
    ['', true], [null, true], ['maybe', true],
]);
