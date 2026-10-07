<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Exceptions;

use Throwable;

/**
 * Thrown when a gateway rejects a request or answers with an error, including errors sent with HTTP 200.
 */
class ApiException extends PaymentException
{
    /**
     * @param  string|null  $gatewayCode  The gateway's own error code, e.g. `ORDER_ID_USED` or `09`.
     * @param  string|null  $gatewayMessage  The gateway's own error message.
     * @param  int  $httpStatus  The HTTP status of the gateway's response, or 0 when no response was received.
     * @param  array<array-key, mixed>  $raw  The decoded response body.
     */
    public function __construct(
        string $message,
        public readonly ?string $gatewayCode = null,
        public readonly ?string $gatewayMessage = null,
        public readonly int $httpStatus = 0,
        public readonly array $raw = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }
}
