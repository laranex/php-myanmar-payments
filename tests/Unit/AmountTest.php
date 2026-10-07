<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyItem;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyPaymentData;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrPaymentData;

it('parses plain decimal strings exactly as given', function (string $input, int $decimals, string $whole) {
    $amount = Amount::parse($input);

    expect($amount->toString())->toBe($input)
        ->and((string) $amount)->toBe($input)
        ->and($amount->decimalPlaces())->toBe($decimals)
        ->and($amount->wholePart())->toBe($whole);
})->with([
    ['1000', 0, '1000'],
    ['1000.50', 2, '1000'],
    ['0.001', 3, '0'],
    ['007.5', 1, '7'],
]);

it('rejects anything that is not plain digits', function (string $input) {
    Amount::parse($input);
})->with(['1e5', '-1', '1,000', ' 10', '10 ', '10.', '.5', '', 'abc', '+10', '0x10'])
    ->throws(InvalidPaymentDataException::class, 'plain digits');

it('builds whole amounts from integers', function () {
    expect(Amount::kyat(1000)->toString())->toBe('1000')
        ->and(Amount::from(250)->toString())->toBe('250')
        ->and(Amount::from(Amount::parse('1.50'))->toString())->toBe('1.50');
});

it('rejects negative integers', function () {
    Amount::kyat(-1);
})->throws(InvalidPaymentDataException::class, 'negative');

it('knows zero from positive', function (string $input, bool $zero) {
    expect(Amount::parse($input)->isZero())->toBe($zero)
        ->and(Amount::parse($input)->isPositive())->toBe(! $zero);
})->with([['0', true], ['0.00', true], ['000', true], ['0.01', false], ['10', false]]);

it('is rejected with decimals by gateways whose docs only allow whole kyat', function (Closure $build, string $gateway) {
    try {
        $build();
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors()['amount'] ?? null)->toBe("{$gateway} does not accept decimal amounts.");

        return;
    }

    $this->fail('No validation error was thrown.');
})->with([
    'Wave Money item' => [fn () => new WaveMoneyItem('A', Amount::parse('10.50')), 'Wave Money'],
    'Wave Money total' => [fn () => new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)], Amount::parse('10.50')), 'Wave Money'],
    'AYA Pay' => [fn () => new AyaPayPaymentData('ORDER123', Amount::parse('10.50'), 'aya_pay', AyaPayMethod::Qr), 'AYA Pay'],
    'Yoma MMQR' => [fn () => new YomaMmqrPaymentData('ORD-1', Amount::parse('10.50'), 'x'), 'Yoma MMQR'],
]);

it('sums Wave items without floats', function () {
    $data = new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [
        new WaveMoneyItem('A', 600), new WaveMoneyItem('B', Amount::kyat(400)), new WaveMoneyItem('C', Amount::parse('5')),
    ]);

    expect($data->amount->toString())->toBe('1005')
        ->and($data->items[1]->toArray())->toBe(['name' => 'B', 'amount' => 400]);
});
