<?php

namespace CSeidl\EmailComposer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Bus\Batch send(\CSeidl\EmailComposer\Models\EmailDraft $draft)
 * @method static \CSeidl\EmailComposer\Services\ConfigService config()
 * @method static void resolveAbilityUsing(\Closure $callback)
 * @method static bool userCan(\Illuminate\Contracts\Auth\Authenticatable|null $user, string $ability, \Illuminate\Database\Eloquent\Model|null $model = null)
 * @method static void flushResolvers()
 * @method static \CSeidl\EmailComposer\Templates\TemplateRegistry templates()
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
