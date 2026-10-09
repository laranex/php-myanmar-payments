<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Http;

/**
 * The HTTP response a gateway expects after it delivers a callback. Gateways retry until they receive it.
 */
final class Acknowledgement
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public readonly array $headers = ['Content-Type' => 'text/plain'],
    ) {}

    /**
     * An empty `200 text/plain` response, which most gateways expect.
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Send the acknowledgement with PHP's native output functions.
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
