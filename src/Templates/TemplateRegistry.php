<?php

namespace CSeidl\EmailComposer\Templates;

use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class TemplateRegistry
{
    /** @var array<int, string> */
    protected array $searchPaths;

    /** @param array<int, string>|null $searchPaths */
    public function __construct(?array $searchPaths = null)
    {
        $this->searchPaths = $searchPaths ?? array_values(array_filter([
            config('email-composer.templates.path'),
            __DIR__.'/../../resources/dist/templates',
        ]));
    }

    /** @return Collection<string, EmailTemplate|FileTemplate> keyed by `key` */
    public function all(): Collection
    {
        $files = $this->fileTemplates();

        $dupes = $files->pluck('key')->duplicates();
        if ($dupes->isNotEmpty()) {
            throw new \RuntimeException(
                'Duplicate file template keys: '.$dupes->unique()->implode(', ')
            );
        }

        // File templates win on key collision (repo-controlled / canonical).
        return EmailTemplate::all()->keyBy('key')->toBase()->merge($files->keyBy('key'));
    }

    public function find(string $key): EmailTemplate|FileTemplate|null
    {
        return $this->all()->get($key);
    }

    /** @return Collection<int, FileTemplate> */
    protected function fileTemplates(): Collection
    {
        return collect($this->searchPaths)
            ->filter(fn ($path) => is_dir($path))
            ->flatMap(fn ($path) => File::glob(rtrim($path, '/').'/*.html'))
            ->map(fn ($path) => $this->loadFile($path))
            ->values();
    }

    public function loadFile(string $path): FileTemplate
    {
        $doc = YamlFrontMatter::parse(File::get($path));
        $matter = $doc->matter();

        foreach (['key', 'name'] as $required) {
            if (blank($matter[$required] ?? null)) {
                throw new \InvalidArgumentException(
                    "File template [{$path}] is missing required front-matter key '{$required}'."
                );
            }
        }

        $body = trim($doc->body());

        // Pull an optional inline <style> block out as the template's css.
        $css = null;
        if (preg_match('/<style\b[^>]*>(.*?)<\/style>/is', $body, $match)) {
            $css = trim($match[1]);
            $body = trim(preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $body));
        }

        return new FileTemplate(
            key: (string) $matter['key'],
            name: (string) $matter['name'],
            body: $body,
            placeholders: array_map('strval', (array) ($matter['placeholders'] ?? [])),
            css: $css,
            sourcePath: $path,
        );
    }
}
