<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Exceptions\ApiException;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('turns an unreachable gateway into an ApiException with HTTP status 0', function () {
    $error = new class('Connection refused') extends RuntimeException implements ClientExceptionInterface {};
    $http = new class($error) implements ClientInterface
    {
        public function __construct(private readonly ClientExceptionInterface $error) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            throw $this->error;
        }
    };

    try {
        (new KbzPay(new KbzPayConfig('app', 'key', 'merchant'), $http))->status('ORDER_1');
    } catch (ApiException $e) {
        expect($e->getMessage())->toStartWith('Could not reach '.KbzPayConfig::SANDBOX_API_URL.'/queryorder: Connection refused')
            ->and($e->httpStatus)->toBe(0)
            ->and($e->getPrevious())->toBe($error);

        return;
    }

    test()->fail('Expected an ApiException.');
});
