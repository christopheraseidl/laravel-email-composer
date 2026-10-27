<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Storage;

class DraftPublisher
{
    /**
     * Render the draft once per locale and store each as private text. Only the
     * app reads these, never by URL. Returns the
     * { locale: storage-relative path } map (also saved on the draft).
     *
     * @return array<string, string>
     */
    public function publish(EmailDraft $draft): array
    {
        $disk = Storage::disk(EmailComposer::config()->storageDisk());
        $directory = $this->directoryFor($draft);

        $paths = [];
        foreach ($this->localesFor($draft) as $locale) {
            $path = "{$directory}/{$locale}.txt";
            $disk->put($path, $draft->renderFor($locale), [
                'visibility' => 'private',
                'ContentType' => 'text/plain; charset=UTF-8',
            ]);
            $paths[$locale] = $path;
        }

        $draft->forceFill(['public_files' => $paths])->save();

        return $paths;
    }

    /**
     * Delete the draft's published files and clear them from the draft.
     */
    public function unpublish(EmailDraft $draft): void
    {
        Storage::disk(EmailComposer::config()->storageDisk())->deleteDirectory($this->directoryFor($draft));

        $draft->forceFill(['public_files' => null])->save();
    }

    protected function directoryFor(EmailDraft $draft): string
    {
        $base = trim(EmailComposer::config()->storagePath(), '/');

        return "{$base}/drafts/{$this->folderFor($draft)}";
    }

    /**
     * Unguessable without the app key, so files on a public disk cannot be
     * found by walking draft IDs. Stable per draft, so republishing overwrites.
     */
    protected function folderFor(EmailDraft $draft): string
    {
        return hash_hmac('sha256', (string) $draft->getKey(), (string) config('app.key'));
    }

    /**
     * Locales the draft actually has content in, always including the default.
     *
     * @return array<int, string>
     */
    protected function localesFor(EmailDraft $draft): array
    {
        return array_values(array_unique(array_merge(
            array_keys($draft->getTranslations('subject')),
            array_keys($draft->getTranslations('placeholders')),
            [EmailComposer::config()->defaultLocale()],
        )));
    }
}
