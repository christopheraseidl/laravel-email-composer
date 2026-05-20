<?php

it('successfully boots the service provider', function () {
    expect(config('email-composer.default_locale'))->toBe('en');
});
