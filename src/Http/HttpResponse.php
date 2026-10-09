<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Http;

use Laranex\PhpMyanmarPayments\Support\Json;

/**
 * @internal
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * The body decoded as JSON, with numbers kept as their exact text.
     *
     * @return array<array-key, mixed>
     */
    public function json(): array
    {
        return Json::decode($this->body) ?? [];
    }
}
