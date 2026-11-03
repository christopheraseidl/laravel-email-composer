<?php

namespace CSeidl\EmailComposer\Tests\Fixtures;

use CSeidl\EmailComposer\Commands\InstallCommand;

class InstallCommandWithMissingClasses extends InstallCommand
{
    /** @var list<string> */
    public static array $missing = [];

    protected function classExists(string $class): bool
    {
        return ! in_array($class, static::$missing, true) && parent::classExists($class);
    }
}
