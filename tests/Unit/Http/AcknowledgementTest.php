<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Http\Acknowledgement;

it('defaults to a 200 plain text response with an empty body', function () {
    $acknowledgement = new Acknowledgement;

    expect($acknowledgement->status)->toBe(200)
        ->and($acknowledgement->body)->toBe('')
        ->and($acknowledgement->headers)->toBe(['Content-Type' => 'text/plain']);
});

it('sends its body with PHP\'s native output functions', function () {
    $acknowledgement = new Acknowledgement(201, '{"ok":true}', ['Content-Type' => 'application/json']);

    ob_start();

    try {
        $acknowledgement->send();
        $output = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    expect($output)->toBe('{"ok":true}')
        ->and(http_response_code())->toBe(201);

    http_response_code(200);
});
