<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * An incoming request from a gateway (a server callback or a browser return), independent of any framework.
 *
 * Signatures are verified against what the gateway actually sent, so build this from the real request
 * rather than from re-serialized input.
 */
final class CallbackRequest
{
    /**
     * @param  string  $body  The raw request body exactly as received.
     * @param  array<string, string>  $headers  Request headers. Names are matched case-insensitively.
     * @param  array<string, mixed>  $query  Query string parameters.
     */
    public function __construct(
        public readonly string $body = '',
        private readonly array $headers = [],
        public readonly array $query = [],
    ) {}

    /**
     * Build from PHP's superglobals, for plain PHP endpoints.
     */
    public static function fromGlobals(): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $key)] = (string) $value;
            }
        }

        return new self((string) file_get_contents('php://input'), $headers, $_GET);
    }

    /**
     * Build from a PSR-7 server request.
     */
    public static function fromPsr7(ServerRequestInterface $request): self
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return new self((string) $request->getBody(), $headers, $request->getQueryParams());
    }

    /**
     * Build from an already decoded payload, e.g. a callback stored in your database and replayed later.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public static function fromArray(array $payload, array $headers = []): self
    {
        return new self((string) json_encode($payload), ['Content-Type' => 'application/json'] + $headers);
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * The body decoded as JSON or as a urlencoded form, merged over the query string.
     *
     * @return array<array-key, mixed>
     */
    public function input(): array
    {
        return $this->parsedBody() + $this->query;
    }

    /**
     * The body decoded as JSON or as a urlencoded form.
     *
     * @return array<array-key, mixed>
     */
    public function parsedBody(): array
    {
        $body = trim($this->body);

        if ($body === '') {
            return [];
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            return $json;
        }

        parse_str($body, $form);

        return $form;
    }
}
