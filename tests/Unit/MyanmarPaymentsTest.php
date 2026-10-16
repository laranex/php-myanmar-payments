<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\WavePay\WavePay;
use Laranex\PhpMyanmarPayments\WavePay\WavePayConfig;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrConfig;

it('builds each configured gateway once', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => gatewayConfig('kbz_pay'),
        'yoma_mmqr' => gatewayConfig('yoma_mmqr'),
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)->toBe($payments->kbzPay())
        ->and($payments->yomaMmqr())->toBeInstanceOf(YomaMmqr::class);
});

it('builds every gateway from one configuration array', function () {
    $payments = new MyanmarPayments([
        'kbz_pay' => gatewayConfig('kbz_pay'),
        'wave_pay' => gatewayConfig('wave_pay'),
        'aya_pay' => gatewayConfig('aya_pay'),
        'yoma_mmqr' => gatewayConfig('yoma_mmqr'),
        'cyber_source' => gatewayConfig('cyber_source'),
    ], mockHttp());

    expect($payments->kbzPay())->toBeInstanceOf(KbzPay::class)
        ->and($payments->wavePay())->toBeInstanceOf(WavePay::class)->toBe($payments->wavePay())
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
    (new MyanmarPayments([], mockHttp()))->wavePay();
})->throws(ConfigurationException::class, 'wave_pay configuration is missing [merchant_id]');

it('requires every setting except the HTTP timeout', function (string $key) {
    $config = gatewayConfig('aya_pay');
    unset($config[$key]);

    expect(fn () => AyaPayConfig::fromArray($config))->toThrow(ConfigurationException::class, "[{$key}]");
})->with(['app_key', 'app_secret', 'base_url']);

it('defaults the HTTP timeout to 30 seconds when it is missing or blank', function (array $config) {
    expect(AyaPayConfig::fromArray(array_replace(gatewayConfig('aya_pay'), ['timeout_in_seconds' => null], $config))->timeoutSeconds)->toBe(30)
        ->and(MyanmarPayments::DEFAULT_TIMEOUT_SECONDS)->toBe(30);
})->with([[[]], [['timeout_in_seconds' => null]], [['timeout_in_seconds' => ' ']]]);

it('defaults the HTTP timeout when the constructor is not given one', function () {
    $without = fn (string $gateway): array => array_diff_key(gatewayConfig($gateway), ['timeout_in_seconds' => true]);

    expect((new KbzPayConfig('a', 'b', 'c', 'https://kbz.test', 'https://pwa.test/#/', 15))->timeoutSeconds)->toBe(MyanmarPayments::DEFAULT_TIMEOUT_SECONDS)
        ->and((new WavePayConfig('m', 's', 'Shop', 'https://wave.test', 'https://wave.test', 300))->timeoutSeconds)->toBe(MyanmarPayments::DEFAULT_TIMEOUT_SECONDS)
        ->and((new AyaPayConfig('k', 's', 'https://aya.test'))->timeoutSeconds)->toBe(MyanmarPayments::DEFAULT_TIMEOUT_SECONDS)
        ->and((new YomaMmqrConfig('M', 'c', 's', 'h', 'v1rc', 'shared', 'https://yoma.test'))->timeoutSeconds)->toBe(MyanmarPayments::DEFAULT_TIMEOUT_SECONDS)
        ->and(KbzPayConfig::fromArray($without('kbz_pay'))->timeoutSeconds)->toBe(30)
        ->and(WavePayConfig::fromArray($without('wave_pay'))->timeoutSeconds)->toBe(30)
        ->and(YomaMmqrConfig::fromArray($without('yoma_mmqr'))->timeoutSeconds)->toBe(30);
});

it('reads whole numbers of seconds from integers and integer text', function (mixed $value, int $seconds) {
    expect(AyaPayConfig::fromArray(gatewayConfig('aya_pay', ['timeout_in_seconds' => $value]))->timeoutSeconds)->toBe($seconds);
})->with([[30, 30], ['30', 30], [' 45 ', 45], ['+10', 10]]);

it('rejects seconds that are not a whole number greater than 0', function (mixed $value) {
    expect(fn () => AyaPayConfig::fromArray(gatewayConfig('aya_pay', ['timeout_in_seconds' => $value])))
        ->toThrow(ConfigurationException::class, 'The aya_pay configuration [timeout_in_seconds] must be a whole number greater than 0.');
})->with([0, -5, '0', '-5', 'five', '1.5', 1.5, true]);
