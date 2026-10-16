<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use Laranex\PhpMyanmarPayments\Http\CallbackRequest;

it('builds from a PSR-7 server request with its body, headers and query string', function () {
    $psr = (new ServerRequest('POST', 'https://shop.test/callback?orderId=ORDER_1', [
        'Content-Type' => 'application/json',
        'X-Signature' => ['abc', 'def'],
    ], '{"status":"PAID"}'))->withQueryParams(['orderId' => 'parsed by the framework']);

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
    $_SERVER['QUERY_STRING'] = 'merchant_order_id=ORDER_1';
    $_GET = ['merchant_order_id' => 'parsed by PHP'];

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

it('reads the query string like a urlencoded body: exact names, first value wins, bad escapes kept', function () {
    $query = 'a.b=1&a.b=2&c%5B%5D=x&d+e=%zz&plus=a+b&tilde=~&&empty=';
    $psr = new ServerRequest('GET', "https://shop.test/return?{$query}");
    $server = $_SERVER;
    $_SERVER['QUERY_STRING'] = $query;

    try {
        $globals = CallbackRequest::fromGlobals();
    } finally {
        $_SERVER = $server;
    }

    $expected = ['a.b' => '1', 'c[]' => 'x', 'd e' => '%zz', 'plus' => 'a b', 'tilde' => '~', 'empty' => ''];

    expect(CallbackRequest::fromPsr7($psr)->query)->toBe($expected)
        ->and($globals->query)->toBe($expected);
});

it('reads no query when the server has no query string', function () {
    $server = $_SERVER;
    unset($_SERVER['QUERY_STRING']);

    try {
        expect(CallbackRequest::fromGlobals()->query)->toBe([]);
    } finally {
        $_SERVER = $server;
    }
});

it('trims only ASCII whitespace around the body, so a byte order mark makes JSON a form', function () {
    expect((new CallbackRequest(" \t\n{\"a\":1}\r\n\x0B\0"))->parsedBody())->toBe(['a' => '1'])
        ->and((new CallbackRequest("\u{FEFF}{\"a\":1}"))->parsedBody())->toBe(["\u{FEFF}{\"a\":1}" => '']);
});

it('matches header names case-insensitively and returns null for a missing header', function () {
    $request = new CallbackRequest('', ['X-Signature' => 'sig']);

    expect($request->header('x-signature'))->toBe('sig')
        ->and($request->header('X-SIGNATURE'))->toBe('sig')
        ->and($request->header('Authorization'))->toBeNull()
        ->and($request->headers())->toBe(['X-Signature' => 'sig']);
});

it('decodes a JSON body, a form body and an empty body', function () {
    expect((new CallbackRequest('{"a":"1","b":{"c":2}}'))->parsedBody())->toBe(['a' => '1', 'b' => ['c' => '2']])
        ->and((new CallbackRequest('a=1&b[c]=2'))->parsedBody())->toBe(['a' => '1', 'b[c]' => '2'])
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
        ->and($request->input())->toBe(['orderId' => 'ORDER_1', 'amount' => '1000']);
});

it('keeps JSON numbers as their exact text so signatures match what the gateway sent', function () {
    $body = '{"amount":1000.50,"big":12345678901234567890,"neg":-1.5e3,"zero":0,"flag":true,"none":null,"text":"9.10 \\"q\\"","list":[1,2.0]}';

    expect((new CallbackRequest($body))->parsedBody())->toBe([
        'amount' => '1000.50',
        'big' => '12345678901234567890',
        'neg' => '-1.5e3',
        'zero' => '0',
        'flag' => true,
        'none' => null,
        'text' => '9.10 "q"',
        'list' => ['1', '2.0'],
    ]);
});

it('falls back to form decoding for a body that is not JSON', function () {
    expect((new CallbackRequest('{"a":1'))->parsedBody())->toBe(['{"a":1' => '']);
});

it('keeps the first value of a repeated form field and the names exactly as sent', function () {
    expect((new CallbackRequest('a=1&a=2&b.c=x+y&d%20e=%2B&flag&&=empty'))->parsedBody())
        ->toBe(['a' => '1', 'b.c' => 'x y', 'd e' => '+', 'flag' => '', '' => 'empty']);
});

it('only decodes a JSON object as JSON', function () {
    expect((new CallbackRequest('[1,2]'))->parsedBody())->toBe(['[1,2]' => ''])
        ->and((new CallbackRequest('"text"'))->parsedBody())->toBe(['"text"' => ''])
        ->and((new CallbackRequest('{}'))->parsedBody())->toBe([]);
});
