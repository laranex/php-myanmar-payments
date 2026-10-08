<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

it('builds from a PSR-7 server request with its body, headers and query string', function () {
    $psr = (new ServerRequest('POST', 'https://shop.test/callback?orderId=ORDER_1', [
        'Content-Type' => 'application/json',
        'X-Signature' => ['abc', 'def'],
    ], '{"status":"PAID"}'))->withQueryParams(['orderId' => 'ORDER_1']);

    $request = CallbackRequest::fromPsr7($psr);

    expect($request->body)->toBe('{"status":"PAID"}')
        ->and($request->query)->toBe(['orderId' => 'ORDER_1'])
        ->and($request->headers())->toBe(['Host' => 'shop.test', 'Content-Type' => 'application/json', 'X-Signature' => 'abc, def'])
        ->and($request->input())->toBe(['status' => 'PAID', 'orderId' => 'ORDER_1']);
});

it('builds from the superglobals of a plain PHP endpoint', function () {
    $server = $_SERVER;
    $get = $_GET;
    $_SERVER['HTTP_X_SIGNATURE'] = 'sig';
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_SERVER['CONTENT_LENGTH'] = '0';
    $_GET = ['merchant_order_id' => 'ORDER_1'];

    try {
        $request = CallbackRequest::fromGlobals();
    } finally {
        $_SERVER = $server;
        $_GET = $get;
    }

    expect($request->body)->toBe('')
        ->and($request->header('x-signature'))->toBe('sig')
        ->and($request->header('content-type'))->toBe('application/x-www-form-urlencoded')
        ->and($request->header('Content-Length'))->toBe('0')
        ->and($request->query)->toBe(['merchant_order_id' => 'ORDER_1'])
        ->and($request->input())->toBe(['merchant_order_id' => 'ORDER_1']);
});

it('matches header names case-insensitively and returns null for a missing header', function () {
    $request = new CallbackRequest('', ['X-Signature' => 'sig']);

    expect($request->header('x-signature'))->toBe('sig')
        ->and($request->header('X-SIGNATURE'))->toBe('sig')
        ->and($request->header('Authorization'))->toBeNull()
        ->and($request->headers())->toBe(['X-Signature' => 'sig']);
});

it('decodes a JSON body, a form body and an empty body', function () {
    expect((new CallbackRequest('{"a":"1","b":{"c":2}}'))->parsedBody())->toBe(['a' => '1', 'b' => ['c' => 2]])
        ->and((new CallbackRequest('a=1&b[c]=2'))->parsedBody())->toBe(['a' => '1', 'b' => ['c' => '2']])
        ->and((new CallbackRequest("  \n"))->parsedBody())->toBe([])
        ->and((new CallbackRequest('', [], ['q' => '1']))->input())->toBe(['q' => '1']);
});

it('lets the body win over the query string in input()', function () {
    $request = new CallbackRequest('{"orderId":"BODY"}', [], ['orderId' => 'QUERY', 'extra' => 'x']);

    expect($request->input())->toBe(['orderId' => 'BODY', 'extra' => 'x']);
});

it('builds from an already decoded payload as JSON', function () {
    $request = CallbackRequest::fromArray(['orderId' => 'ORDER_1', 'amount' => 1000], ['X-Signature' => 'sig']);

    expect($request->body)->toBe('{"orderId":"ORDER_1","amount":1000}')
        ->and($request->header('Content-Type'))->toBe('application/json')
        ->and($request->header('X-Signature'))->toBe('sig')
        ->and($request->input())->toBe(['orderId' => 'ORDER_1', 'amount' => 1000]);
});
