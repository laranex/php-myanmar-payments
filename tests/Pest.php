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
