<?php

use Illuminate\Support\Arr;

dataset('language files', fn () => array_map(
    basename(...),
    glob(__DIR__.'/../../resources/lang/en/*.php') ?: [],
));

it('has the same keys in en and es', function (string $file) {
    $keys = fn (string $locale) => array_keys(Arr::dot(require __DIR__."/../../resources/lang/{$locale}/{$file}"));

    expect($keys('es'))->toEqualCanonicalizing($keys('en'));
})->with('language files');
