<?php

namespace CSeidl\EmailComposer\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Typed reads of the email-composer config. Fallbacks cover nested keys,
 * which mergeConfigFrom() does not fill in when a published config drops them.
 */
class ConfigService
{
    public function defaultLocale(): string
    {
        return config()->string('email-composer.default_locale', 'en');
    }

    /** @return array<int, string> */
    public function locales(): array
    {
        return config()->array('email-composer.locales', [$this->defaultLocale()]);
    }

    /** The given locale when it is configured, otherwise the default. */
    public function localeOrDefault(mixed $locale): string
    {
        return is_string($locale) && in_array($locale, $this->locales(), true)
            ? $locale
            : $this->defaultLocale();
    }

    /** null means the app's default disk. */
    public function storageDisk(): ?string
    {
        return $this->stringOrNull('email-composer.storage.disk');
    }

    public function storagePath(): string
    {
        return config()->string('email-composer.storage.path', 'email-composer');
    }

    public function publicDisk(): string
    {
        return config()->string('email-composer.storage.public_disk', 'public');
    }

    /**
     * 'public' only when the disk is configured that way. Uploading with 'public' sends a
     * public-read ACL, which R2 and S3 buckets with ACLs disabled refuse.
     */
    public function publicDiskVisibility(): string
    {
        return config("filesystems.disks.{$this->publicDisk()}.visibility") === 'public' ? 'public' : 'private';
    }

    public function templatesPath(): ?string
    {
        return $this->stringOrNull('email-composer.templates.path');
    }

    /** null means the app's default connection. */
    public function queueConnection(): ?string
    {
        return $this->stringOrNull('email-composer.queue.connection');
    }

    /** null means the connection's default queue. */
    public function queueName(): ?string
    {
        return $this->stringOrNull('email-composer.queue.queue');
    }

    public function bulkChunkSize(): int
    {
        return max(1, (int) config('email-composer.bulk.chunk_size', 100));
    }

    /** In kilobytes. */
    public function importMaxFileSize(): int
    {
        return max(1, (int) config('email-composer.import.max_file_size', 10240));
    }

    public function importRowsPerPart(): int
    {
        return max(1, (int) config('email-composer.import.rows_per_part', 2000));
    }

    /** Where queued imports' uploads wait, on the storage disk. */
    public function importsPath(): string
    {
        return $this->storagePath().'/imports';
    }

    /** null means unsubscribe links never expire. */
    public function unsubscribeLinkTtlDays(): ?int
    {
        $days = config('email-composer.unsubscribe.link_ttl_days');

        return $days === null ? null : (int) $days;
    }

    public function reviewLinkTtlDays(): int
    {
        return (int) config('email-composer.review.link_ttl_days', 7);
    }

    /** null means the Filament pages appear outside any navigation group. */
    public function navigationGroup(): ?string
    {
        return $this->stringOrNull('email-composer.filament.navigation_group', 'Email');
    }

    /**
     * The host app's user model, which drafts and feedback belong to.
     *
     * @return class-string<Model>
     */
    public function userModel(): string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new \UnexpectedValueException('auth.providers.users.model must name an Eloquent model class.');
        }

        return $model;
    }

    protected function stringOrNull(string $key, ?string $default = null): ?string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : null;
    }
}
