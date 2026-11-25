<?php

use CSeidl\EmailComposer\Support\NameWithEmail;

it('formats a name and email', function (?string $name, ?string $email, string $expected) {
    expect(NameWithEmail::format($name, $email))->toBe($expected);
})->with([
    ['Ada', 'ada@example.com', 'Ada <ada@example.com>'],
    ['Ada', null, 'Ada'],
    [null, 'ada@example.com', '<ada@example.com>'],
    ['', 'ada@example.com', '<ada@example.com>'],
    ['Ada', '', 'Ada'],
    [null, null, ''],
]);
