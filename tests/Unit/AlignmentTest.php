<?php

declare(strict_types=1);

use GuzzleHttp\Client as Guzzle;
use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceConfig;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourcePaymentData;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceTransactionType;
use Laranex\PhpMyanmarPayments\Enums\PaymentStatus;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Http\Acknowledgement;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\Http\Transport;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPaySigner;
use Laranex\PhpMyanmarPayments\MyanmarPayments;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoney;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyConfig;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyItem;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyPaymentData;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrConfig;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrPaymentData;

/**
 * Every variable the SDKs read, with the same names in Go, Node and Python.
 *
 * @return array<string, string>
 */
function fullEnv(): array
{
    return [
        'KBZ_PAY_APP_ID' => 'app', 'KBZ_PAY_APP_KEY' => 'key', 'KBZ_PAY_MERCHANT_CODE' => 'merchant', 'MYANMAR_PAYMENTS_HTTP_TIMEOUT' => ' 20 ',
        'KBZ_PAY_BASE_URL' => 'https://kbz.test/', 'KBZ_PAY_PWA_BASE_REDIRECT_URL' => 'https://pwa.test/#', 'KBZ_PAY_TIME_TO_LIVE_IN_MINUTES' => ' 30 ',
        'WAVE_MONEY_MERCHANT_ID' => 'm', 'WAVE_MONEY_SECRET_KEY' => 's', 'WAVE_MONEY_MERCHANT_NAME' => 'Shop', 'WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS' => ' 600 ',
        'WAVE_MONEY_BASE_URL' => 'https://wave.test', 'WAVE_MONEY_BASE_REDIRECT_URL' => 'https://pay.wave.test/',
        'AYA_PAY_APP_KEY' => 'k', 'AYA_PAY_APP_SECRET' => 's', 'AYA_PAY_BASE_URL' => 'https://aya.test',
        'YOMA_MMQR_MERCHANT_ID' => 'm', 'YOMA_MMQR_CLIENT_ID' => 'c', 'YOMA_MMQR_CLIENT_SECRET' => 's', 'YOMA_MMQR_WEBHOOK_HASHKEY' => 'h',
        'YOMA_MMQR_WEBHOOK_SECRET' => 'shared', 'YOMA_MMQR_BASE_URL' => 'https://yoma.test', 'YOMA_MMQR_API_VERSION' => 'v2',
        'CYBER_SOURCE_PROFILE_ID' => 'p', 'CYBER_SOURCE_ACCESS_KEY' => 'a', 'CYBER_SOURCE_SECRET_KEY' => 's',
        'CYBER_SOURCE_BASE_URL' => 'https://cs.test',
    ];
}

it('reads every gateway from the same environment variables as the other SDKs', function () {
    $env = fullEnv();
    $kbz = KbzPayConfig::fromEnv($env);
    $wave = WaveMoneyConfig::fromEnv($env);
    $aya = AyaPayConfig::fromEnv($env);
    $yoma = YomaMmqrConfig::fromEnv($env);
    $cs = CyberSourceConfig::fromEnv($env);

    expect([$kbz->appId, $kbz->appKey, $kbz->merchantCode, $kbz->timeoutSeconds, $kbz->apiUrl, $kbz->pwaUrl, $kbz->timeToLiveMinutes])->toBe(['app', 'key', 'merchant', 20, 'https://kbz.test', 'https://pwa.test/#/', 30])
        ->and([$wave->merchantName, $wave->timeToLiveSeconds, $wave->timeoutSeconds, $wave->baseUrl, $wave->baseRedirectUrl])->toBe(['Shop', 600, 20, 'https://wave.test', 'https://pay.wave.test'])
        ->and([$aya->appKey, $aya->timeoutSeconds, $aya->baseUrl])->toBe(['k', 20, 'https://aya.test'])
        ->and([$yoma->webhookSecret, $yoma->timeoutSeconds, $yoma->baseUrl, $yoma->apiVersion])->toBe(['shared', 20, 'https://yoma.test', 'v2'])
        ->and([$cs->profileId, $cs->baseUrl])->toBe(['p', 'https://cs.test']);
});

it('builds gateways and the facade from the environment', function () {
    $env = fullEnv();
    $payments = MyanmarPayments::fromEnv($env, mockHttp());

    expect(KbzPay::fromEnv($env, mockHttp())->config->appId)->toBe('app')
        ->and(WaveMoney::fromEnv($env, mockHttp())->config->merchantId)->toBe('m')
        ->and(AyaPay::fromEnv($env, mockHttp())->config->appKey)->toBe('k')
        ->and(YomaMmqr::fromEnv($env, mockHttp())->config->clientId)->toBe('c')
        ->and(CyberSource::fromEnv($env)->config->profileId)->toBe('p')
        ->and($payments->kbzPay()->config->apiUrl)->toBe('https://kbz.test')
        ->and($payments->waveMoney()->config->baseUrl)->toBe('https://wave.test')
        ->and($payments->ayaPay()->config->baseUrl)->toBe('https://aya.test')
        ->and($payments->yomaMmqr()->config->apiVersion)->toBe('v2')
        ->and($payments->cyberSource()->config->baseUrl)->toBe('https://cs.test')
        ->and(fn () => MyanmarPayments::fromEnv([])->kbzPay())->toThrow(ConfigurationException::class, 'kbz_pay configuration is missing [app_id]');
});

it('reads the real environment by default', function () {
    putenv('CYBER_SOURCE_PROFILE_ID=from-getenv');
    $_ENV['CYBER_SOURCE_ACCESS_KEY'] = 'from-env-array';
    putenv('CYBER_SOURCE_SECRET_KEY=s');
    putenv('CYBER_SOURCE_BASE_URL=https://cs.test');

    try {
        $config = CyberSourceConfig::fromEnv();
    } finally {
        putenv('CYBER_SOURCE_PROFILE_ID');
        putenv('CYBER_SOURCE_SECRET_KEY');
        putenv('CYBER_SOURCE_BASE_URL');
        unset($_ENV['CYBER_SOURCE_ACCESS_KEY']);
    }

    expect($config->profileId)->toBe('from-getenv')->and($config->accessKey)->toBe('from-env-array');
});

it('accepts a config array or object for every gateway and facade entry', function () {
    $kbz = KbzPayConfig::fromArray(gatewayConfig('kbz_pay', ['app_id' => 'app']));
    $payments = new MyanmarPayments(['kbz_pay' => $kbz, 'cyber_source' => gatewayConfig('cyber_source', ['profile_id' => 'p'])], mockHttp());

    expect($payments->kbzPay()->config)->toBe($kbz)
        ->and($payments->cyberSource()->config->profileId)->toBe('p')
        ->and((new KbzPay(gatewayConfig('kbz_pay', ['app_id' => 'a']), mockHttp()))->config->appId)->toBe('a')
        ->and((new WaveMoney(gatewayConfig('wave_money'), mockHttp()))->config->merchantName)->toBe('Shop')
        ->and((new AyaPay(gatewayConfig('aya_pay', ['app_key' => 'k']), mockHttp()))->config->appKey)->toBe('k')
        ->and((new YomaMmqr(gatewayConfig('yoma_mmqr', ['merchant_id' => 'm']), mockHttp()))->config->merchantId)->toBe('m')
        ->and((new CyberSource(gatewayConfig('cyber_source', ['access_key' => 'a'])))->config->accessKey)->toBe('a');
});

it('rejects blank credentials in config constructors, naming the gateway and key', function (Closure $build, string $gateway, string $key) {
    try {
        $build();
    } catch (ConfigurationException $e) {
        expect($e->gateway)->toBe($gateway)->and($e->key)->toBe($key)
            ->and($e->getMessage())->toBe("The {$gateway} configuration is missing [{$key}].");

        return;
    }

    test()->fail('Expected a ConfigurationException.');
})->with([
    [fn () => new KbzPayConfig('a', ' ', 'm', 'https://kbz.test', 'https://pwa.test/#/', 15), 'kbz_pay', 'app_key'],
    [fn () => new KbzPayConfig('a', 'k', 'm', ' ', 'https://pwa.test/#/', 15), 'kbz_pay', 'api_url'],
    [fn () => new KbzPayConfig('a', 'k', 'm', 'https://kbz.test', '', 15), 'kbz_pay', 'pwa_url'],
    [fn () => new WaveMoneyConfig('m', 's', '', 'https://wave.test', 'https://wave.test', 300), 'wave_money', 'merchant_name'],
    [fn () => new WaveMoneyConfig('m', 's', 'Shop', '', 'https://wave.test', 300), 'wave_money', 'base_url'],
    [fn () => new WaveMoneyConfig('m', 's', 'Shop', 'https://wave.test', ' ', 300), 'wave_money', 'base_redirect_url'],
    [fn () => new AyaPayConfig('', 's', 'https://aya.test'), 'aya_pay', 'app_key'],
    [fn () => new AyaPayConfig('k', 's', ' '), 'aya_pay', 'base_url'],
    [fn () => new YomaMmqrConfig('m', 'c', 's', '', 'v1rc', 'shared', 'https://yoma.test'), 'yoma_mmqr', 'webhook_hashkey'],
    [fn () => new YomaMmqrConfig('m', 'c', 's', 'h', '', 'shared', 'https://yoma.test'), 'yoma_mmqr', 'api_version'],
    [fn () => new YomaMmqrConfig('m', 'c', 's', 'h', 'v1rc', '', 'https://yoma.test'), 'yoma_mmqr', 'webhook_secret'],
    [fn () => new YomaMmqrConfig('m', 'c', 's', 'h', 'v1rc', 'shared', ' '), 'yoma_mmqr', 'base_url'],
    [fn () => new CyberSourceConfig('p', 'a', "\t", 'https://cs.test'), 'cyber_source', 'secret_key'],
    [fn () => new CyberSourceConfig('p', 'a', 's', ''), 'cyber_source', 'base_url'],
    [fn () => WaveMoneyConfig::fromArray(['merchant_id' => 'm', 'secret_key' => '  ']), 'wave_money', 'secret_key'],
]);

it('uses the URLs it is given without their trailing slash', function () {
    $yoma = new YomaMmqrConfig('m', 'c', 's', 'h', 'v1rc', 'shared', 'https://yoma.test/');

    expect((new KbzPayConfig('a', 'k', 'm', 'https://kbz.test/', 'https://pwa.test/#', 15))->apiUrl)->toBe('https://kbz.test')
        ->and((new WaveMoneyConfig('m', 's', 'Shop', 'https://wave.test/', 'https://pay.wave.test/', 300))->baseRedirectUrl)->toBe('https://pay.wave.test')
        ->and((new AyaPayConfig('k', 's', 'https://aya.test/'))->baseUrl)->toBe('https://aya.test')
        ->and((new CyberSourceConfig('p', 'a', 's', 'https://cs.test/'))->baseUrl)->toBe('https://cs.test')
        ->and([$yoma->webhookSecret, $yoma->baseUrl, $yoma->apiVersion])->toBe(['shared', 'https://yoma.test', 'v1rc']);
});

it('reads the time to live only from whole numbers greater than 0', function (mixed $value, ?int $seconds) {
    $build = fn () => WaveMoneyConfig::fromArray(gatewayConfig('wave_money', ['time_to_live_in_seconds' => $value]));

    if ($seconds === null) {
        expect($build)->toThrow(ConfigurationException::class, '[time_to_live_in_seconds] must be a whole number greater than 0.');

        return;
    }

    expect($build()->timeToLiveSeconds)->toBe($seconds);
})->with([[600, 600], ['600', 600], [' 600 ', 600], ['60abc', null], ['6e2', null], [-5, null], [true, null]]);

it('uses Guzzle with the configured timeout when no client is given', function () {
    $client = Transport::defaultClient(12);

    expect($client)->toBeInstanceOf(Guzzle::class)
        ->and((fn (): mixed => $this->config['timeout'])->call($client))->toBe(12)
        ->and(new KbzPay(KbzPayConfig::fromArray(gatewayConfig('kbz_pay'))))->toBeInstanceOf(KbzPay::class);
});

it('keys Wave item errors by their position', function () {
    try {
        new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10), new WaveMoneyItem(' ', 0)]);
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors())->toBe([
            'items.1.name' => 'The items.1.name field is required.',
            'items.1.amount' => 'The items.1.amount field must be greater than 0.',
        ]);

        return;
    }

    test()->fail('Expected an InvalidPaymentDataException.');
});

it('exposes the KBZ signer and signs false as text', function () {
    $kbz = new KbzPay(KbzPayConfig::fromArray(gatewayConfig('kbz_pay')), mockHttp());

    expect($kbz->signer)->toBeInstanceOf(KbzPaySigner::class)
        ->and($kbz->signer->signString(['b' => false, 'a' => true, 'c' => '', 'd' => null]))->toBe('a=true&b=false');
});

it('ends gateway error messages without a trailing space', function () {
    $http = mockHttp(jsonResponse(['Response' => ['result' => 'FAIL', 'code' => 'AUTH_ERROR']]));

    expect(fn () => (new KbzPay(KbzPayConfig::fromArray(gatewayConfig('kbz_pay')), $http))->status('O1'))
        ->toThrow(ApiException::class, 'KBZ Pay queryorder failed: [AUTH_ERROR]');

    try {
        (new AyaPay(AyaPayConfig::fromArray(gatewayConfig('aya_pay')), mockHttp(jsonResponse(['status' => '09']))))->services();
    } catch (ApiException $e) {
        expect($e->getMessage())->toBe('AYA Pay services failed: [09]');
    }
});

it('falls back to the requested id or key when the gateway sends an empty one', function () {
    $services = (new AyaPay(AyaPayConfig::fromArray(gatewayConfig('aya_pay')), mockHttp(jsonResponse([
        'status' => '00', 'data' => [['key' => 'visa', 'name' => '', 'methods' => ['WEB', ['x']]]],
    ]))))->services();

    $yoma = (new YomaMmqr(YomaMmqrConfig::fromArray(gatewayConfig('yoma_mmqr')), mockHttp(
        jsonResponse(['access_token' => 't', 'expires_in' => 60]),
        jsonResponse(['refLabel' => '', 'paymentStatus' => 'SUCCESS']),
    )))->status('REF-1');

    expect($services[0]->name)->toBe('visa')
        ->and($services[0]->methods)->toHaveCount(1)
        ->and($yoma->gatewayReference)->toBe('REF-1');
});

it('lists Wave validation errors in field order', function () {
    $http = mockHttp(jsonResponse(['errors' => ['order_id' => ['Taken.'], 'amount' => ['Too low.', 'Not whole.']]], 422));
    $data = new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)]);

    expect(fn () => (new WaveMoney(WaveMoneyConfig::fromArray(gatewayConfig('wave_money')), $http))->initiate($data))
        ->toThrow(ApiException::class, 'Wave Money payment request failed with HTTP 422: amount: Too low. Not whole.; order_id: Taken.');
});

it('builds Wave items for any whole amount', function () {
    expect((new WaveMoneyItem('A', Amount::kyat(5)))->toArray())->toBe(['name' => 'A', 'amount' => 5]);
});

it('reads form fields by name and as an array', function () {
    $payment = new FormPayment('O1', 'https://pay.test', ['a' => '1', 'b' => '2']);

    expect($payment->field('a'))->toBe('1')
        ->and($payment->field('missing'))->toBeNull()
        ->and($payment->values())->toBe(['a' => '1', 'b' => '2']);
});

it('merges the query string over the body in queryInput()', function () {
    $request = new CallbackRequest('{"a":"body","b":"body"}', [], ['a' => 'query']);

    expect($request->queryInput())->toBe(['a' => 'query', 'b' => 'body'])
        ->and($request->input())->toBe(['a' => 'body', 'b' => 'body']);
});

it('builds the default acknowledgement', function () {
    $ack = Acknowledgement::default();

    expect([$ack->status, $ack->body, $ack->headers])->toBe([200, '', ['Content-Type' => 'text/plain']]);
});

it('resolves gateway statuses through a public map', function () {
    $map = ['PAID' => PaymentStatus::Successful];

    expect(PaymentStatus::resolve($map, ' PAID '))->toBe(PaymentStatus::Successful)
        ->and(PaymentStatus::resolve($map, null))->toBe(PaymentStatus::Unknown);
});

it('validates payment data again on demand', function () {
    $kbz = new KbzPayPaymentData('ORDER_1', 1000, 'https://shop.test/cb');
    $kbz->validate();

    expect(fn () => (new YomaMmqrPaymentData('O1', 1, 'x'))->validate())->not->toThrow(InvalidPaymentDataException::class)
        ->and(fn () => (new AyaPayPaymentData('ORDER1', 1, 'aya_pay', AyaPayMethod::Qr))->validate())->not->toThrow(InvalidPaymentDataException::class)
        ->and(fn () => (new CyberSourcePaymentData('O1', 1, 'https://shop.test/cb', 'MMK', CyberSourceTransactionType::Sale, 'en-us'))->validate())->not->toThrow(InvalidPaymentDataException::class)
        ->and(fn () => (new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)]))->validate())->not->toThrow(InvalidPaymentDataException::class);
});
