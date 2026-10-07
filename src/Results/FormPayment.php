<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments\Results;

use Laranex\PhpMyanmarPayments\Enums\PaymentFlow;

/**
 * POST `$fields` to `$action` from the customer's browser. `toHtml()` renders a page that does this automatically.
 */
final class FormPayment implements PaymentResult
{
    /**
     * @param  string  $orderId  Your order id, as sent to the gateway.
     * @param  string  $action  The gateway URL the form posts to.
     * @param  array<string, string>  $fields  The signed hidden fields. Post them unchanged.
     * @param  string  $enctype  The encoding the gateway expects for the form.
     * @param  string|null  $autoSubmitUrl  A URL on your app that renders `toHtml()`, set by framework integrations.
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $action,
        public readonly array $fields,
        public readonly string $enctype = 'application/x-www-form-urlencoded',
        public readonly ?string $autoSubmitUrl = null,
    ) {}

    public function flow(): PaymentFlow
    {
        return PaymentFlow::Form;
    }

    public function withAutoSubmitUrl(string $url): self
    {
        return new self($this->orderId, $this->action, $this->fields, $this->enctype, $url);
    }

    /**
     * A complete HTML page that posts the form as soon as it loads.
     */
    public function toHtml(): string
    {
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $inputs = '';

        foreach ($this->fields as $name => $value) {
            $inputs .= '<input type="hidden" name="'.$escape((string) $name).'" value="'.$escape((string) $value).'">';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Redirecting to payment</title></head><body>'
            .'<form id="payment-form" method="POST" action="'.$escape($this->action).'" enctype="'.$escape($this->enctype).'">'
            .$inputs
            .'<noscript><button type="submit">Continue to payment</button></noscript>'
            .'</form>'
            .'<script>document.getElementById("payment-form").submit();</script>'
            .'</body></html>';
    }
}
