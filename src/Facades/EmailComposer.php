<?php

namespace CSeidl\EmailComposer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Bus\Batch send(\CSeidl\EmailComposer\Models\EmailDraft $draft)
 *
 * @see \CSeidl\EmailComposer\EmailComposer
 */
class EmailComposer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CSeidl\EmailComposer\EmailComposer::class;
    }
}
