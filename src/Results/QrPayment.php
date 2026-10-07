<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use DateTimeImmutable;
use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * Show a QR code for the customer to scan. Gateways return either a payload to encode or a ready-made image.
 */
final class QrPayment implements PaymentResult
{
    /**
     * @param  string  $orderId  Your order id, as sent to the gateway.
     * @param  string|null  $qrString  A QR payload to encode into an image yourself (KBZ Pay).
     * @param  string|null  $qrImage  A base64 encoded image to display as is (Yoma MMQR).
     * @param  DateTimeImmutable|null  $expiresAt  When the QR stops being payable, if the gateway limits it.
     * @param  string|null  $reference  The gateway's id for this QR, used to check its status (Yoma `refLabel`).
     * @param  array<array-key, mixed>  $raw  The gateway's response, for logging.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly ?string $qrString = null,
        public readonly ?string $qrImage = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly ?string $reference = null,
        public readonly array $raw = [],
    ) {}

    public function flow(): PaymentFlow
    {
        return PaymentFlow::Qr;
    }

    /**
     * The image as a data URI for an `<img src>`, when the gateway returned an image.
     */
    public function qrImageDataUri(string $mimeType = 'image/png'): ?string
    {
        return $this->qrImage === null ? null : "data:{$mimeType};base64,{$this->qrImage}";
    }
}
