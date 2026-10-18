<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\EmailThemeFactory;
use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string|null $css
 * @property string|null $logo_url
 * @property string|null $logo_path
 * @property string|null $logo_alt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(EmailThemeFactory::class)]
class EmailTheme extends Model
{
    /** @use HasFactory<EmailThemeFactory> */
    use HasFactory;

    protected $table = 'email_composer_theme';

    protected $fillable = ['css', 'logo_url', 'logo_path', 'logo_alt'];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['css' => static::defaultCss()]);
    }

    /** The uploaded logo, else the logo URL. */
    public function logoSrc(): ?string
    {
        if ($this->logo_path) {
            return Storage::disk(EmailComposer::config()->publicDisk())->url($this->logo_path);
        }

        return $this->logo_url;
    }

    public function logoAlt(): string
    {
        return $this->logo_alt ?: config()->string('app.name');
    }

    public static function defaultCss(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/dist/css/email-default.css');
    }
}
