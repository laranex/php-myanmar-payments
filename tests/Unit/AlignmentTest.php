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
        'KBZ_PAY_APP_ID' => 'app', 'KBZ_PAY_APP_KEY' => 'key', 'KBZ_PAY_MERCHANT_CODE' => 'merchant', 'KBZ_PAY_SANDBOX' => 'false',
        'KBZ_PAY_BASE_URL' => 'https://kbz.test/', 'KBZ_PAY_PWA_BASE_REDIRECT_URL' => 'https://pwa.test/#',
        'WAVE_MONEY_MERCHANT_ID' => 'm', 'WAVE_MONEY_SECRET_KEY' => 's', 'APP_NAME' => 'Shop', 'WAVE_MONEY_TIME_TO_LIVE_IN_SECONDS' => ' 600 ',
        'WAVE_MONEY_SANDBOX' => 'no', 'WAVE_MONEY_BASE_URL' => 'https://wave.test', 'WAVE_MONEY_AUTHENTICATE_URL' => 'https://wave-auth.test',
        'AYA_PGW_APP_KEY' => 'k', 'AYA_PAY_APP_SECRET' => 's', 'AYA_PAY_SANDBOX' => 'off', 'AYA_PGW_BASE_URL' => 'https://aya.test',
        'YOMA_MMQR_MERCHANT_ID' => 'm', 'YOMA_MMQR_CLIENT_ID' => 'c', 'YOMA_MMQR_CLIENT_SECRET' => 's', 'YOMA_MMQR_WEBHOOK_HASHKEY' => 'h',
        'YOMA_MMQR_WEBHOOK_SECRET' => 'shared', 'YOMA_MMQR_SANDBOX' => '0', 'YOMA_MMQR_BASE_URL' => 'https://yoma.test', 'YOMA_MMQR_API_VERSION' => 'v2',
        'CYBER_SOURCE_PROFILE_ID' => 'p', 'CYBER_SOURCE_ACCESS_KEY' => 'a', 'CYBER_SOURCE_SECRET_KEY' => 's', 'CYBER_SOURCE_SANDBOX' => 'F',
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

    expect([$kbz->appId, $kbz->appKey, $kbz->merchantCode, $kbz->sandbox, $kbz->apiUrl, $kbz->pwaUrl])->toBe(['app', 'key', 'merchant', false, 'https://kbz.test', 'https://pwa.test/#/'])
        ->and([$wave->merchantName, $wave->timeToLiveSeconds, $wave->sandbox, $wave->baseUrl, $wave->authenticateUrl])->toBe(['Shop', 600, false, 'https://wave.test', 'https://wave-auth.test'])
        ->and([$aya->appKey, $aya->sandbox, $aya->baseUrl])->toBe(['k', false, 'https://aya.test'])
        ->and([$yoma->webhookSecret, $yoma->sandbox, $yoma->baseUrl, $yoma->apiVersion])->toBe(['shared', false, 'https://yoma.test', 'v2'])
        ->and([$cs->profileId, $cs->sandbox, $cs->baseUrl])->toBe(['p', false, 'https://cs.test']);
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

    try {
        $config = CyberSourceConfig::fromEnv();
    } finally {
        putenv('CYBER_SOURCE_PROFILE_ID');
        putenv('CYBER_SOURCE_SECRET_KEY');
        unset($_ENV['CYBER_SOURCE_ACCESS_KEY']);
    }

    expect($config->profileId)->toBe('from-getenv')->and($config->accessKey)->toBe('from-env-array');
});

it('accepts a config array or object for every gateway and facade entry', function () {
    $kbz = new KbzPayConfig('app', 'key', 'merchant');
    $payments = new MyanmarPayments(['kbz_pay' => $kbz, 'cyber_source' => ['profile_id' => 'p', 'access_key' => 'a', 'secret_key' => 's']], mockHttp());

    expect($payments->kbzPay()->config)->toBe($kbz)
        ->and($payments->cyberSource()->config->profileId)->toBe('p')
        ->and((new KbzPay(['app_id' => 'a', 'app_key' => 'k', 'merchant_code' => 'm'], mockHttp()))->config->appId)->toBe('a')
        ->and((new WaveMoney(['merchant_id' => 'm', 'secret_key' => 's', 'merchant_name' => 'Shop'], mockHttp()))->config->merchantName)->toBe('Shop')
        ->and((new AyaPay(['app_key' => 'k', 'app_secret' => 's'], mockHttp()))->config->appKey)->toBe('k')
        ->and((new YomaMmqr(['merchant_id' => 'm', 'client_id' => 'c', 'client_secret' => 's', 'webhook_hashkey' => 'h'], mockHttp()))->config->merchantId)->toBe('m')
        ->and((new CyberSource(['profile_id' => 'p', 'access_key' => 'a', 'secret_key' => 's']))->config->accessKey)->toBe('a');
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
    [fn () => new KbzPayConfig('a', ' ', 'm'), 'kbz_pay', 'app_key'],
    [fn () => new WaveMoneyConfig('m', 's', ''), 'wave_money', 'merchant_name'],
    [fn () => new AyaPayConfig('', 's'), 'aya_pay', 'app_key'],
    [fn () => new YomaMmqrConfig('m', 'c', 's', ''), 'yoma_mmqr', 'webhook_hashkey'],
    [fn () => new CyberSourceConfig('p', 'a', "\t"), 'cyber_source', 'secret_key'],
    [fn () => WaveMoneyConfig::fromArray(['merchant_id' => 'm', 'secret_key' => '  ']), 'wave_money', 'secret_key'],
]);

it('treats blank overrides as unset', function () {
    $yoma = new YomaMmqrConfig('m', 'c', 's', 'h', webhookSecret: '', baseUrl: ' ', apiVersion: '');

    expect((new KbzPayConfig('a', 'k', 'm', apiUrl: '', pwaUrl: ''))->apiUrl)->toBe(KbzPayConfig::SANDBOX_API_URL)
        ->and((new WaveMoneyConfig('m', 's', 'Shop', baseUrl: '', authenticateUrl: ''))->authenticateUrl)->toBe(WaveMoneyConfig::SANDBOX_AUTHENTICATE_URL)
        ->and((new AyaPayConfig('k', 's', baseUrl: ''))->baseUrl)->toBe(AyaPayConfig::SANDBOX_URL)
        ->and((new CyberSourceConfig('p', 'a', 's', baseUrl: ''))->baseUrl)->toBe(CyberSourceConfig::SANDBOX_URL)
        ->and([$yoma->webhookSecret, $yoma->baseUrl, $yoma->apiVersion])->toBe([null, YomaMmqrConfig::SANDBOX_URL, 'v1rc']);
});

it('reads the time to live only from integer values', function (mixed $value, int $seconds) {
    expect(WaveMoneyConfig::fromArray(['merchant_id' => 'm', 'secret_key' => 's', 'merchant_name' => 'Shop', 'time_to_live_in_seconds' => $value])->timeToLiveSeconds)->toBe($seconds);
})->with([[600, 600], ['600', 600], [' 600 ', 600], ['60abc', 300], ['6e2', 300], [-5, 300], [true, 300]]);

it('uses Guzzle with a 30 second timeout when no client is given', function () {
    $client = Transport::defaultClient();

    expect($client)->toBeInstanceOf(Guzzle::class)
        ->and((fn (): mixed => $this->config['timeout'])->call($client))->toBe(30)
        ->and(new KbzPay(new KbzPayConfig('a', 'k', 'm')))->toBeInstanceOf(KbzPay::class);
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
    $kbz = new KbzPay(new KbzPayConfig('a', 'key', 'm'), mockHttp());

    expect($kbz->signer)->toBeInstanceOf(KbzPaySigner::class)
        ->and($kbz->signer->signString(['b' => false, 'a' => true, 'c' => '', 'd' => null]))->toBe('a=true&b=false');
});

it('ends gateway error messages without a trailing space', function () {
    $http = mockHttp(jsonResponse(['Response' => ['result' => 'FAIL', 'code' => 'AUTH_ERROR']]));

    expect(fn () => (new KbzPay(new KbzPayConfig('a', 'k', 'm'), $http))->status('O1'))
        ->toThrow(ApiException::class, 'KBZ Pay queryorder failed: [AUTH_ERROR]');

    try {
        (new AyaPay(new AyaPayConfig('k', 's'), mockHttp(jsonResponse(['status' => '09']))))->services();
    } catch (ApiException $e) {
        expect($e->getMessage())->toBe('AYA Pay services failed: [09]');
    }
});

it('falls back to the requested id or key when the gateway sends an empty one', function () {
    $services = (new AyaPay(new AyaPayConfig('k', 's'), mockHttp(jsonResponse([
        'status' => '00', 'data' => [['key' => 'visa', 'name' => '', 'methods' => ['WEB', ['x']]]],
    ]))))->services();

    $yoma = (new YomaMmqr(new YomaMmqrConfig('m', 'c', 's', 'h'), mockHttp(
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

    expect(fn () => (new WaveMoney(new WaveMoneyConfig('m', 's', 'Shop'), $http))->initiate($data))
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
        ->and(fn () => (new CyberSourcePaymentData('O1', 1, 'https://shop.test/cb'))->validate())->not->toThrow(InvalidPaymentDataException::class)
        ->and(fn () => (new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)]))->validate())->not->toThrow(InvalidPaymentDataException::class);
});
