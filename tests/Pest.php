<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

function mockHttp(Response ...$responses): Client
{
    $client = new Client;

    foreach ($responses as $response) {
        $client->addResponse($response);
    }

    return $client;
}

/**
 * @param  array<array-key, mixed>  $body
 */
function jsonResponse(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

/**
 * @return array<array-key, mixed>
 */
function requestJson(RequestInterface $request): array
{
    return json_decode((string) $request->getBody(), true);
}

/**
 * @return array<array-key, mixed>
 */
function requestForm(RequestInterface $request): array
{
    parse_str((string) $request->getBody(), $form);

    return $form;
}

/**
 * @return array<array-key, mixed>
 */
function testVector(string $path): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$path), true);
}

/**
 * A complete, valid configuration of one gateway with its production URLs; tests override only what they check.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gatewayConfig(string $gateway, array $overrides = []): array
{
    $defaults = match ($gateway) {
        'kbz_pay' => ['app_id' => 'kp123', 'app_key' => 'secret-key', 'merchant_code' => '100001', 'api_url' => 'https://api.kbzpay.com/payment/gateway', 'pwa_url' => 'https://wap.kbzpay.com/pgw/pwa/#/', 'time_to_live_in_minutes' => 15, 'timeout_in_seconds' => 30],
        'wave_pay' => ['merchant_id' => 'testmerchantID', 'secret_key' => 'test-secret', 'merchant_name' => 'Shop', 'base_url' => 'https://payments.wavemoney.io', 'base_redirect_url' => 'https://payments.wavemoney.io', 'time_to_live_in_seconds' => 300, 'timeout_in_seconds' => 30],
        'aya_pay' => ['app_key' => 'app-key', 'app_secret' => 'test-secret', 'base_url' => 'https://pgw.ayainnovation.com', 'timeout_in_seconds' => 30],
        'yoma_mmqr' => ['merchant_id' => 'M001', 'client_id' => 'client', 'client_secret' => 'secret', 'webhook_hashkey' => 'hash-key', 'api_version' => 'v1rc', 'webhook_secret' => 'webhook-secret', 'base_url' => 'https://paymenthubapi.yomabank.com', 'timeout_in_seconds' => 30],
        'cyber_source' => ['profile_id' => 'profile', 'access_key' => 'access', 'secret_key' => 'secret', 'base_url' => 'https://secureacceptance.cybersource.com'],
    };

    return array_replace($defaults, $overrides);
}
