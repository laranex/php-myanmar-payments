<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Amount;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayMethod;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayPaymentData;
use Laranex\PhpMyanmarPayments\Exceptions\InvalidPaymentDataException;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyItem;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyPaymentData;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrPaymentData;

it('parses plain decimal strings, keeping the fraction exactly and dropping leading zeros', function (string $input, int $decimals, string $whole, string $expected) {
    $amount = Amount::parse($input);

    expect($amount->toString())->toBe($expected)
        ->and((string) $amount)->toBe($expected)
        ->and($amount->decimalPlaces())->toBe($decimals)
        ->and($amount->wholePart())->toBe($whole);
})->with([
    ['1000', 0, '1000', '1000'],
    ['1000.50', 2, '1000', '1000.50'],
    ['0.001', 3, '0', '0.001'],
    ['007.5', 1, '7', '7.5'],
    ['0100', 0, '100', '100'],
    ['000', 0, '0', '0'],
    ['00.00', 2, '0', '0.00'],
]);

it('rejects anything that is not plain digits', function (string $input) {
    Amount::parse($input);
})->with(['1e5', '-1', '1,000', ' 10', '10 ', '10.', '.5', '', 'abc', '+10', '0x10', "10\n", "10.5\n"])
    ->throws(InvalidPaymentDataException::class, 'The amount field must be a number such as 1000 or 1000.50, got');

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

it('is rejected with decimals by gateways whose docs only allow whole kyat', function (Closure $build, string $gateway, string $field) {
    try {
        $build();
    } catch (InvalidPaymentDataException $e) {
        expect($e->errors()[$field] ?? null)->toBe("{$gateway} does not accept decimal amounts; the {$field} field must be a whole number.");

        return;
    }

    $this->fail('No validation error was thrown.');
})->with([
    'Wave Money item' => [fn () => new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', Amount::parse('10.50'))]), 'Wave Money', 'items.0.amount'],
    'Wave Money total' => [fn () => new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [new WaveMoneyItem('A', 10)], Amount::parse('10.50')), 'Wave Money', 'amount'],
    'AYA Pay' => [fn () => new AyaPayPaymentData('ORDER123', Amount::parse('10.50'), 'aya_pay', AyaPayMethod::Qr), 'AYA Payment Gateway', 'amount'],
    'Yoma MMQR' => [fn () => new YomaMmqrPaymentData('ORD-1', Amount::parse('10.50'), 'x'), 'Yoma MMQR', 'amount'],
]);

it('sums Wave items without floats', function () {
    $data = new WaveMoneyPaymentData('1', 'https://shop.test/cb', 'https://shop.test/done', 'x', [
        new WaveMoneyItem('A', 600), new WaveMoneyItem('B', Amount::kyat(400)), new WaveMoneyItem('C', Amount::parse('5')),
    ]);

    expect($data->amount->toString())->toBe('1005')
        ->and($data->items[1]->toArray())->toBe(['name' => 'B', 'amount' => 400]);
});

it('compares amounts by value, ignoring leading and trailing zeros', function (string $other, bool $equal) {
    expect(Amount::parse('1000')->equals($other))->toBe($equal)
        ->and(Amount::parse('1000')->equals(Amount::parse('1000.00')))->toBeTrue()
        ->and(Amount::parse('1000')->equals(null))->toBeFalse();
})->with([['1000', true], ['01000', true], ['1000.0', true], ['1000.01', false], ['1,000', false], [' 1000', false], ['', false]]);

it('serializes to a JSON string', function () {
    expect(json_encode(['amount' => Amount::parse('1000.50')]))->toBe('{"amount":"1000.50"}');
});
