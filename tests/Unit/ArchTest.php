<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Exceptions\PaymentException;

arch('the core package does not depend on any framework')
    ->expect('Laranex\PhpMyanmarPayments')
    ->not->toUse(['Illuminate', 'Symfony\Component\HttpFoundation']);

arch('exceptions extend the package base exception')
    ->expect('Laranex\PhpMyanmarPayments\Exceptions')
    ->toExtend(PaymentException::class)
    ->ignoring(PaymentException::class);
