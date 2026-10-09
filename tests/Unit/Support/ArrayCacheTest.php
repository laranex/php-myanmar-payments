<?php

declare(strict_types=1);

use Laranex\PhpMyanmarPayments\Support\ArrayCache;

it('stores, reads and deletes values', function () {
    $cache = new ArrayCache;

    expect($cache->set('a', 'one'))->toBeTrue()
        ->and($cache->get('a'))->toBe('one')
        ->and($cache->has('a'))->toBeTrue()
        ->and($cache->get('missing', 'default'))->toBe('default');

    $cache->delete('a');

    expect($cache->has('a'))->toBeFalse();
});

it('expires values after their ttl', function () {
    $cache = new ArrayCache;
    $cache->set('expired', 'x', 0);
    $cache->set('past', 'x', -10);
    $cache->set('interval', 'x', new DateInterval('PT1H'));
    $cache->set('forever', 'x');

    expect($cache->has('expired'))->toBeFalse()
        ->and($cache->get('past', 'gone'))->toBe('gone')
        ->and($cache->has('interval'))->toBeTrue()
        ->and($cache->has('forever'))->toBeTrue();
});

it('handles multiple keys and clears everything', function () {
    $cache = new ArrayCache;

    expect($cache->setMultiple(['a' => 1, 'b' => 2]))->toBeTrue()
        ->and($cache->getMultiple(['a', 'b', 'c'], 0))->toBe(['a' => 1, 'b' => 2, 'c' => 0])
        ->and($cache->deleteMultiple(['a']))->toBeTrue()
        ->and($cache->has('a'))->toBeFalse()
        ->and($cache->clear())->toBeTrue()
        ->and($cache->has('b'))->toBeFalse();
});
