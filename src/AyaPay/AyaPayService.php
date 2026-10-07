<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\AyaPay;

/**
 * A payment channel enabled for your merchant account, e.g. AYA Pay, KBZ Pay, VISA or JCB.
 */
final class AyaPayService
{
    /**
     * @param  string  $name  Display name, e.g. "AYA Pay".
     * @param  string  $key  The `channel` value to send when initiating, e.g. `aya_pay`, `visa`.
     * @param  string|null  $imageUrl  The channel's logo.
     * @param  list<AyaPayMethod>  $methods  Methods this channel supports.
     * @param  list<string>  $unknownMethods  Methods the gateway listed that this package does not know yet.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $key,
        public readonly ?string $imageUrl,
        public readonly array $methods,
        public readonly array $unknownMethods = [],
    ) {}

    public function supports(AyaPayMethod $method): bool
    {
        return in_array($method, $this->methods, true);
    }
}
