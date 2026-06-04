<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\EmailThemeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $css
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(EmailThemeFactory::class)]
class EmailTheme extends Model
{
    /** @use HasFactory<EmailThemeFactory> */
    use HasFactory;

    protected $table = 'email_composer_theme';

    protected $fillable = ['css'];

    public static function current(): self
    {
        return app(static::class);
    }

    public static function defaultCss(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/dist/css/email-default.css');
    }
}
