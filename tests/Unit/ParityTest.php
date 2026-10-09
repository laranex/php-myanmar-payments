<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\Contracts\PaymentGateway;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\Exceptions\ConfigurationException;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\Exceptions\SignatureVerificationException;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayPaymentData;
use Laranex\PhpMyanmarPayments\Results\FormPayment;
use Laranex\PhpMyanmarPayments\Support\ArrayCache;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoney;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyItem;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyPaymentData;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;

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
        'kbz_pay' => new KbzPay(['app_id' => 'kp123', 'app_key' => $secrets['kbz_pay_app_key'], 'merchant_code' => '100001'], mockHttp()),
        'wave_money' => new WaveMoney(['merchant_id' => 'merchant', 'secret_key' => $secrets['wave_money_secret_key'], 'merchant_name' => 'Shop'], mockHttp()),
        'aya_pay' => new AyaPay(['app_key' => 'app-key', 'app_secret' => $secrets['aya_pay_app_secret']], mockHttp()),
        'yoma_mmqr' => new YomaMmqr(['merchant_id' => 'M001', 'client_id' => 'client', 'client_secret' => 'secret', 'webhook_hashkey' => $secrets['yoma_mmqr_webhook_hashkey']], mockHttp()),
        'cyber_source' => new CyberSource(['profile_id' => 'profile', 'access_key' => 'access', 'secret_key' => $secrets['cyber_source_secret_key']]),
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
    $request = new CallbackRequest($case['body'], ['Content-Type' => $case['content_type']]);
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

it('reads the sandbox flag like every other SDK', function () {
    foreach (parity()['sandbox'] as $case) {
        $env = ['KBZ_PAY_APP_ID' => 'a', 'KBZ_PAY_APP_KEY' => 'b', 'KBZ_PAY_MERCHANT_CODE' => 'c', 'KBZ_PAY_SANDBOX' => $case['value']];

        expect(KbzPayConfig::fromEnv($env)->sandbox)->toBe($case['sandbox'], $case['value']);
    }
});

it('caches the Yoma MMQR token under the shared key', function () {
    $vector = parity()['yoma_mmqr_token_cache_key'];
    $cache = new ArrayCache;
    $http = mockHttp(jsonResponse(['access_token' => 'token-1', 'expires_in' => 28800]), jsonResponse(['refLabel' => 'R1', 'paymentStatus' => 'PENDING']));

    (new YomaMmqr(['merchant_id' => 'M001', 'client_id' => $vector['client_id'], 'client_secret' => 's', 'webhook_hashkey' => 'h', 'base_url' => $vector['base_url']], $http, $cache))->status('R1');

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

    expect($errors(fn () => new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)], Amount::parse('10.5')))['amount'])->toBe($messages['whole_amounts_only'])
        ->and($errors(fn () => new AyaPayPaymentData('ORDER123', Amount::parse('10.5'), 'aya_pay', AyaPayMethod::Qr))['amount'])->toBe($messages['aya_whole_amounts_only'])
        ->and($errors(fn () => new KbzPayPaymentData('ORDER_1', Amount::parse('1.505'), 'https://shop.test/cb'))['amount'])->toBe($messages['kbz_decimals'])
        ->and($errors(fn () => new KbzPayPaymentData('ORDER_1', 1000, 'https://shop.test/cb', timeoutMinutes: 0))['timeoutMinutes'])->toBe($messages['kbz_timeout_zero']);
});
