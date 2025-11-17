<?php

declare(strict_types=1);

use App\Models\TermTree;

/**
 * Unit-тесты для модели TermTree.
 */

test('table name is term_tree', function () {
    $termTree = new TermTree();

    expect($termTree->getTable())->toBe('term_tree');
});

test('does not use timestamps', function () {
    $termTree = new TermTree();

    expect($termTree->timestamps)->toBeFalse();
});

test('does not use incrementing', function () {
    $termTree = new TermTree();

    expect($termTree->getIncrementing())->toBeFalse();
});

test('has no guarded attributes', function () {
    $termTree = new TermTree();

    expect($termTree->getGuarded())->toBe([]);
});

test('has no primary key', function () {
    $termTree = new TermTree();

    expect($termTree->getKeyName())->toBeNull();
});

