<?php

namespace CSeidl\EmailComposer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CSeidl\EmailComposer\EmailComposer
 */
class EmailComposer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CSeidl\EmailComposer\EmailComposer::class;
    }
}
