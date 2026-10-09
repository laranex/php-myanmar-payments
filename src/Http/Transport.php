<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Http;

use GuzzleHttp\Client as Guzzle;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Thin wrapper over a PSR-18 client for the JSON and form posts the gateways use.
 *
 * @internal
 */
final class Transport
{
    private readonly ClientInterface $client;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    /**
     * Seconds before the default Guzzle client gives up, like the Go, Node and Python SDKs.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Without a client, Guzzle is used with a 30 second timeout when it is installed; otherwise any discovered
     * PSR-18 client, with its own timeout.
     */
    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->client = $client ?? self::defaultClient();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public static function defaultClient(): ClientInterface
    {
        if (class_exists(Guzzle::class)) {
            return new Guzzle(['timeout' => self::DEFAULT_TIMEOUT]);
        }

        return Psr18ClientDiscovery::find(); // @codeCoverageIgnore
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function postJson(string $url, array $data, array $headers = []): HttpResponse
    {
        return $this->post($url, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ] + $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function postForm(string $url, array $data, array $headers = []): HttpResponse
    {
        return $this->post($url, http_build_query($data), [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ] + $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function post(string $url, string $body, array $headers): HttpResponse
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withBody($this->streamFactory->createStream($body));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException("Could not reach {$url}: {$e->getMessage()}", previous: $e);
        }

        return new HttpResponse($response->getStatusCode(), (string) $response->getBody());
    }
}
