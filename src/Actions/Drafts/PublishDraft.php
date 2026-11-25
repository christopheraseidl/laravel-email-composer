<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\LocatesPublishedFiles;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;

/**
 * Renders the draft once per locale and stores each as private text. Only the
 * app reads these, never by URL.
 */
class PublishDraft
{
    use LocatesPublishedFiles;

    public function __construct(protected ConfigService $config) {}

    /** @return array<string, string> the { locale: storage-relative path } map, also saved on the draft */
    public function __invoke(EmailDraft $draft): array
    {
        $disk = $this->config->storage();
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
     * Locales the draft actually has content in, always including the default.
     *
     * @return array<int, string>
     */
    protected function localesFor(EmailDraft $draft): array
    {
        return array_values(array_unique(array_merge(
            array_keys($draft->getTranslations('subject')),
            array_keys($draft->getTranslations('placeholders')),
            [$this->config->defaultLocale()],
        )));
    }
}
