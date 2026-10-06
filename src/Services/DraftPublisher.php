<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Storage;

class DraftPublisher
{
    /**
     * Render the draft once per locale and store each as a public HTML file.
     * Returns the { locale: storage-relative path } map (also saved on the draft).
     *
     * @return array<string, string>
     */
    public function publish(EmailDraft $draft): array
    {
        $disk = Storage::disk(config('email-composer.storage.disk'));
        $base = trim((string) config('email-composer.storage.path', 'email-composer'), '/');
        $folder = $this->folderFor($draft);

        $paths = [];
        foreach ($this->localesFor($draft) as $locale) {
            $path = "{$base}/drafts/{$folder}/{$locale}.html";
            $disk->put($path, $draft->renderFor($locale));
            $paths[$locale] = $path;
        }

        $draft->forceFill(['public_files' => $paths])->save();

        return $paths;
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
            [config('email-composer.default_locale', 'en')],
        )));
    }
}
