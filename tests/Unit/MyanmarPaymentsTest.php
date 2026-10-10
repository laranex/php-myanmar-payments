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
        'kbz_pay' => ['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c', 'timeout_in_seconds' => 30],
        'yoma_mmqr' => ['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h', 'api_version' => 'v1rc', 'timeout_in_seconds' => 30],
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)->toBe($payments->kbzPay())
        ->and($payments->yomaMmqr())->toBeInstanceOf(YomaMmqr::class);
});

it('builds every gateway from one configuration array', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => ['app_id' => 'a', 'app_key' => 'b', 'merchant_code' => 'c', 'timeout_in_seconds' => 30],
        'wave_money' => ['merchant_id' => 'm', 'secret_key' => 's', 'merchant_name' => 'Shop', 'time_to_live_in_seconds' => 300, 'timeout_in_seconds' => 30],
        'aya_pay' => ['app_key' => 'k', 'app_secret' => 's', 'timeout_in_seconds' => 30],
        'yoma_mmqr' => ['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h', 'api_version' => 'v1rc', 'timeout_in_seconds' => 30],
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

it('requires every setting except the URL overrides', function (array $config, string $key) {
    expect(fn () => AyaPayConfig::fromArray($config))->toThrow(ConfigurationException::class, "[{$key}]");
})->with([
    [['app_secret' => 's', 'timeout_in_seconds' => 30], 'app_key'],
    [['app_key' => 'k', 'timeout_in_seconds' => 30], 'app_secret'],
    [['app_key' => 'k', 'app_secret' => 's'], 'timeout_in_seconds'],
    [['app_key' => 'k', 'app_secret' => 's', 'timeout_in_seconds' => ' '], 'timeout_in_seconds'],
]);

it('reads whole numbers of seconds from integers and integer text', function (mixed $value, int $seconds) {
    expect(AyaPayConfig::fromArray(['app_key' => 'k', 'app_secret' => 's', 'timeout_in_seconds' => $value])->timeoutSeconds)->toBe($seconds);
})->with([[30, 30], ['30', 30], [' 45 ', 45], ['+10', 10]]);

it('rejects seconds that are not a whole number greater than 0', function (mixed $value) {
    expect(fn () => AyaPayConfig::fromArray(['app_key' => 'k', 'app_secret' => 's', 'timeout_in_seconds' => $value]))
        ->toThrow(ConfigurationException::class, 'The aya_pay configuration [timeout_in_seconds] must be a whole number greater than 0.');
})->with([0, -5, '0', '-5', 'five', '1.5', 1.5, true]);
