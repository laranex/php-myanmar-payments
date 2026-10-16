<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceConfig;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\WavePay\WavePay;
use Laranex\PhpMyanmarPayments\WavePay\WavePayConfig;
use Laranex\PhpMyanmarPayments\WavePay\WavePayItem;
use Laranex\PhpMyanmarPayments\WavePay\WavePayPaymentData;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrConfig;

/*
 * tests/Fixtures/parity/vectors.json is shared byte for byte with go-, node- and python-myanmar-payments:
 * every SDK must give the same answer for every case.
 */

/**
 * @return array<string, mixed>
 */
function parity(): array
{
    return testVector('parity/vectors.json');
}

function parityGateway(string $gateway): PaymentGateway
{
    $secrets = parity()['secrets'];

    return match ($gateway) {
        'kbz_pay' => new KbzPay(gatewayConfig('kbz_pay', ['app_key' => $secrets['kbz_pay_app_key']]), mockHttp()),
        'wave_pay' => new WavePay(gatewayConfig('wave_pay', ['merchant_id' => 'merchant', 'secret_key' => $secrets['wave_pay_secret_key']]), mockHttp()),
        'aya_pay' => new AyaPay(gatewayConfig('aya_pay', ['app_secret' => $secrets['aya_pay_app_secret']]), mockHttp()),
        'yoma_mmqr' => new YomaMmqr(gatewayConfig('yoma_mmqr', ['webhook_hashkey' => $secrets['yoma_mmqr_webhook_hashkey'], 'webhook_secret' => $secrets['yoma_mmqr_webhook_secret']]), mockHttp()),
        'cyber_source' => new CyberSource(gatewayConfig('cyber_source', ['secret_key' => $secrets['cyber_source_secret_key']])),
    };
}

/**
 * @return array<string, array{string, array<string, mixed>}>
 */
function parityCallbacks(): array
{
    $cases = [];

    foreach (parity()['callbacks'] as $gateway => $list) {
        foreach ($list as $case) {
            $cases["{$gateway}: {$case['name']}"] = [$gateway, $case];
        }
    }

    return $cases;
}

it('verifies every shared callback vector', function (string $gateway, array $case) {
    $request = new CallbackRequest($case['body'], ['Content-Type' => $case['content_type'], ...($case['headers'] ?? [])]);
    $expected = $case['expected'];

    if (! $expected['valid']) {
        expect(fn () => parityGateway($gateway)->handleCallback($request))->toThrow(SignatureVerificationException::class);

        return;
    }

    $callback = parityGateway($gateway)->handleCallback($request);

    expect($callback->orderId)->toBe($expected['order_id'])
        ->and($callback->status->value)->toBe($expected['status'])
        ->and($callback->gatewayStatus)->toBe($expected['gateway_status'])
        ->and($callback->gatewayReference)->toBe($expected['gateway_reference'] ?? null)
        ->and($callback->amount)->toBe($expected['amount'] ?? null);
})->with(parityCallbacks());

it('parses amounts like every other SDK', function () {
    foreach (parity()['amount_parse'] as $case) {
        if ($case['value'] === null) {
            expect(fn () => Amount::parse($case['input']))->toThrow(InvalidPaymentDataException::class);
        } else {
            expect(Amount::parse($case['input'])->toString())->toBe($case['value']);
        }
    }

    $error = parity()['amount_parse_error'];

    try {
        Amount::parse($error['input']);
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors())->toBe($error['errors']);
    }
});

it('compares amounts like every other SDK', function () {
    foreach (parity()['amount_equals'] as $case) {
        expect(Amount::parse($case['amount'])->equals($case['other']))->toBe($case['equal'], json_encode($case) ?: '');
    }
});

/**
 * @param  array<string, mixed>  $env
 */
function parityConfig(string $gateway, array $env): KbzPayConfig|WavePayConfig|AyaPayConfig|YomaMmqrConfig|CyberSourceConfig
{
    return match ($gateway) {
        'kbz_pay' => KbzPayConfig::fromEnv($env),
        'wave_pay' => WavePayConfig::fromEnv($env),
        'aya_pay' => AyaPayConfig::fromEnv($env),
        'yoma_mmqr' => YomaMmqrConfig::fromEnv($env),
        'cyber_source' => CyberSourceConfig::fromEnv($env),
    };
}

/**
 * @return array<string, string>
 */
function parityUrls(KbzPayConfig|WavePayConfig|AyaPayConfig|YomaMmqrConfig|CyberSourceConfig $config): array
{
    return match (true) {
        $config instanceof KbzPayConfig => ['api_url' => $config->apiUrl, 'pwa_url' => $config->pwaUrl],
        $config instanceof WavePayConfig => ['base_url' => $config->baseUrl, 'base_redirect_url' => $config->baseRedirectUrl],
        default => ['base_url' => $config->baseUrl],
    };
}

it('reads the production and UAT URLs like every other SDK', function () {
    $vector = parity()['config'];

    foreach ($vector['urls'] as $gateway => $urls) {
        expect(parityUrls(parityConfig($gateway, $vector['env'])))->toBe($urls, $gateway)
            ->and(parityUrls(parityConfig($gateway, $vector['uat']['env'] + $vector['env'])))->toBe($vector['uat']['urls'][$gateway], $gateway);
    }

    $wave = WavePayConfig::fromEnv($vector['env']);
    $seconds = $vector['seconds'];

    expect($wave->timeToLiveSeconds)->toBe($seconds['time_to_live_in_seconds'])
        ->and([$wave->timeoutSeconds, KbzPayConfig::fromEnv($vector['env'])->timeoutSeconds, AyaPayConfig::fromEnv($vector['env'])->timeoutSeconds, YomaMmqrConfig::fromEnv($vector['env'])->timeoutSeconds])
        ->toBe(array_fill(0, 4, $seconds['timeout_in_seconds']));
});

it('defaults the HTTP timeout to 30 seconds like every other SDK', function () {
    $vector = parity()['config'];
    $default = $vector['default_timeout'];

    foreach ($default['values'] as $value) {
        $env = $vector['env'];

        if ($value === null) {
            unset($env[$default['variable']]);
        } else {
            $env[$default['variable']] = $value;
        }

        expect([KbzPayConfig::fromEnv($env)->timeoutSeconds, WavePayConfig::fromEnv($env)->timeoutSeconds, AyaPayConfig::fromEnv($env)->timeoutSeconds, YomaMmqrConfig::fromEnv($env)->timeoutSeconds])
            ->toBe(array_fill(0, 4, $default['timeout_in_seconds']), (string) json_encode($value));
    }
});

it('requires every setting like every other SDK', function () {
    $vector = parity()['config'];

    foreach ($vector['errors'] as $case) {
        $env = $vector['env'];

        if ($case['value'] === null) {
            unset($env[$case['variable']]);
        } else {
            $env[$case['variable']] = $case['value'];
        }

        try {
            parityConfig($case['gateway'], $env);
            test()->fail('Expected a ConfigurationException for '.json_encode($case));
        } catch (ConfigurationException $e) {
            expect([$e->gateway, $e->key, $e->getMessage()])->toBe([$case['gateway'], $case['key'], $case['message']], (string) json_encode($case));
        }
    }

    $invalid = parity()['messages']['configuration_invalid'];

    expect(ConfigurationException::invalid($invalid['gateway'], $invalid['key'])->getMessage())->toBe($invalid['message']);
});

it('caches the Yoma MMQR token under the shared key', function () {
    $vector = parity()['yoma_mmqr_token_cache_key'];
    $cache = new ArrayCache;
    $http = mockHttp(jsonResponse(['access_token' => 'token-1', 'expires_in' => 28800]), jsonResponse(['refLabel' => 'R1', 'paymentStatus' => 'PENDING']));

    (new YomaMmqr(gatewayConfig('yoma_mmqr', ['client_id' => $vector['client_id'], 'base_url' => $vector['base_url']]), $http, $cache))->status('R1');

    expect($cache->get($vector['key']))->toBe('token-1');
});

it('renders the same auto-submitting form page as every other SDK', function () {
    $vector = parity()['form_html'];
    $fields = [];

    foreach ($vector['fields'] as [$name, $value]) {
        $fields[$name] = $value;
    }

    expect((new FormPayment($vector['order_id'], $vector['action'], $fields, $vector['enctype']))->toHtml())->toBe($vector['html']);
});

it('uses the same messages as every other SDK', function () {
    $messages = parity()['messages'];

    $errors = function (Closure $build): array {
        try {
            $build();
        } catch (InvalidPaymentDataException $e) {
            return $e->errors();
        }

        return [];
    };

    expect((new InvalidPaymentDataException($messages['invalid_payment_data']['errors']))->getMessage())->toBe($messages['invalid_payment_data']['message']);

    try {
        KbzPayConfig::fromArray([]);
    } catch (ConfigurationException $e) {
        expect($e->getMessage())->toBe($messages['configuration']['message'])
            ->and($e->gateway)->toBe($messages['configuration']['gateway'])
            ->and($e->key)->toBe($messages['configuration']['key']);
    }

    expect($errors(fn () => new WavePayPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WavePayItem('A', 10)], Amount::parse('10.5')))['amount'])->toBe($messages['whole_amounts_only'])
        ->and($errors(fn () => new AyaPayPaymentData('ORDER123', Amount::parse('10.5'), 'aya_pay', AyaPayMethod::Qr))['amount'])->toBe($messages['aya_whole_amounts_only'])
        ->and($errors(fn () => new KbzPayPaymentData('ORDER_1', Amount::parse('1.505'), 'https://shop.test/cb'))['amount'])->toBe($messages['kbz_decimals'])
        ->and($errors(fn () => new KbzPayPaymentData('ORDER_1', 1000, 'https://shop.test/cb', timeToLiveMinutes: 0))['timeToLiveMinutes'])->toBe($messages['kbz_time_to_live_range']);
});

it('applies per-payment Wave Pay overrides like every other SDK', function () {
    $vector = parity()['wave_pay_request_overrides'];
    $config = $vector['config'];
    $data = $vector['data'];

    $build = fn (?string $merchantName, ?int $timeToLiveSeconds): WavePayPaymentData => new WavePayPaymentData(
        orderId: $data['order_id'],
        callbackUrl: $data['callback_url'],
        returnUrl: $data['return_url'],
        description: $data['description'],
        items: array_map(fn (array $item): WavePayItem => new WavePayItem($item[0], $item[1]), $data['items']),
        merchantReferenceId: $data['merchant_reference_id'],
        merchantName: $merchantName,
        timeToLiveSeconds: $timeToLiveSeconds,
    );

    foreach ($vector['cases'] as $case) {
        $http = mockHttp(jsonResponse(['message' => 'success', 'transaction_id' => 'T1']));
        $gateway = new WavePay(WavePayConfig::fromArray(gatewayConfig('wave_pay', $config)), $http);

        $gateway->initiate($build($case['merchant_name'], $case['time_to_live_in_seconds']));

        expect(requestJson($http->getLastRequest()))->toMatchArray($case['expected']);
    }

    foreach ($vector['invalid'] as $case) {
        $fields = ['merchant_name' => 'merchantName', 'time_to_live_in_seconds' => 'timeToLiveSeconds'];

        expect(fn () => $build($case['merchant_name'], $case['time_to_live_in_seconds']))
            ->toThrow(fn (InvalidPaymentDataException $e) => expect(array_keys($e->errors()))->toBe([$fields[$case['setting']]]));
    }
});

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $overrides
 */
function parityWavePayData(array $data, array $overrides = []): WavePayPaymentData
{
    return new WavePayPaymentData(...[
        'orderId' => $data['order_id'],
        'callbackUrl' => $data['callback_url'],
        'returnUrl' => $data['return_url'],
        'description' => $data['description'],
        'items' => array_map(fn (array $item): WavePayItem => new WavePayItem($item[0], $item[1]), $data['items']),
        'merchantReferenceId' => $data['merchant_reference_id'],
        'additionalField3' => $data['additional_field_3'] ?? null,
        'additionalField4' => $data['additional_field_4'] ?? null,
        'additionalField5' => $data['additional_field_5'] ?? null,
        ...$overrides,
    ]);
}

it('sends the same Wave Pay JSON request as every other SDK', function () {
    $vector = parity()['wave_pay_request'];
    $expected = $vector['expected'];

    foreach ($vector['cases'] as $case) {
        $http = mockHttp(jsonResponse($vector['response']['body'], $vector['response']['status']));

        $payment = (new WavePay(WavePayConfig::fromArray($vector['config']), $http))->initiate(parityWavePayData($case['data']));

        $request = $http->getLastRequest();

        expect($request->getMethod())->toBe($expected['method'], $case['name'])
            ->and((string) $request->getUri())->toBe($expected['url'], $case['name'])
            ->and(array_map(fn (string $name): string => $request->getHeaderLine($name), array_combine(array_keys($expected['headers']), array_keys($expected['headers']))))->toBe($expected['headers'], $case['name'])
            ->and(requestJson($request))->toBe($case['expected_body'], $case['name'])
            ->and($payment->url)->toBe($expected['redirect_url'], $case['name'])
            ->and($payment->gatewayReference)->toBe($expected['gateway_reference'], $case['name']);
    }
});

it('enforces the same Wave Pay field limits as every other SDK', function () {
    $vector = parity()['wave_pay_limits'];

    foreach ($vector['valid'] as $case) {
        expect(parityWavePayData($vector['data'], [$case['field'] => $case['value']])->{$case['field']})->toBe($case['value']);
    }

    foreach ($vector['invalid'] as $case) {
        expect(fn () => parityWavePayData($vector['data'], [$case['field'] => $case['value']]))
            ->toThrow(fn (InvalidPaymentDataException $e) => expect($e->errors())->toBe([$case['field'] => $case['error']]));
    }
});

it('takes KBZ Pay\'s time to live from the payment over the configuration like every other SDK', function () {
    $vector = parity()['kbz_pay_time_to_live'];
    $env = parity()['config']['env'];

    foreach ($vector['cases'] as $case) {
        $http = mockHttp(jsonResponse(['Response' => ['result' => 'SUCCESS', 'code' => '0', 'msg' => 'success', 'prepay_id' => 'PREPAY123', 'qrCode' => 'qr']]));
        $config = KbzPayConfig::fromEnv([$vector['variable'] => $case['config']] + $env);

        $payment = (new KbzPay($config, $http))->qr(new KbzPayPaymentData('ORDER_1', 1000, 'https://shop.test/cb', timeToLiveMinutes: $case['payment']));

        expect(requestJson($http->getLastRequest())['Request']['biz_content']['timeout_express'] ?? null)->toBe($case['timeout_express'], (string) json_encode($case))
            ->and($payment->expiresAt)->not->toBeNull();
    }
});
