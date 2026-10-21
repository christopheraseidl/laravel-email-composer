<?php

namespace CSeidl\EmailComposer\Templates;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class TemplateRegistry
{
    /** @var array<int, string> */
    protected array $searchPaths;

    /** @var Collection<string, FileTemplate>|null */
    protected ?Collection $fileTemplates = null;

    /** @param array<int, string>|null $searchPaths */
    public function __construct(?array $searchPaths = null)
    {
        $this->searchPaths = $searchPaths ?? array_values(array_filter([
            EmailComposer::config()->templatesPath(),
            __DIR__.'/../../resources/views/templates',
        ]));
    }

    /** @return Collection<string, EmailTemplate|FileTemplate> keyed by `key` */
    public function all(): Collection
    {
        // File templates win on key collision (repo-controlled / canonical).
        return EmailTemplate::all()->keyBy('key')->toBase()->merge($this->fileTemplates());
    }

    public function find(string $key): EmailTemplate|FileTemplate|null
    {
        return $this->fileTemplates()->get($key) ?? EmailTemplate::where('key', $key)->first();
    }

    public function resolve(?int $id, ?string $key): EmailTemplate|FileTemplate|null
    {
        if ($id) {
            return EmailTemplate::find($id);
        }

        if ($key) {
            return $this->find($key);
        }

        return null;
    }

    /**
     * An earlier search path wins over a later one on a shared key, so app
     * templates override the bundled ones. Read once per instance.
     *
     * @return Collection<string, FileTemplate> keyed by `key`
     */
    public function fileTemplates(): Collection
    {
        return $this->fileTemplates ??= collect($this->searchPaths)
            ->filter(fn ($path) => is_dir($path))
            ->reverse()
            ->reduce(fn (Collection $templates, string $path) => $templates->merge($this->pathTemplates($path)), collect());
    }

    /** @return Collection<string, FileTemplate> keyed by `key` */
    protected function pathTemplates(string $path): Collection
    {
        $files = collect(File::glob(rtrim($path, '/').'/*.html'))
            ->map(fn ($file) => $this->loadFile($file));

        $dupes = $files->pluck('key')->duplicates();
        if ($dupes->isNotEmpty()) {
            throw new \RuntimeException(
                "Duplicate file template keys in [{$path}]: ".$dupes->unique()->implode(', ')
            );
        }

        return $files->keyBy('key');
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

        $css = null;
        if (preg_match('/<style\b[^>]*>(.*?)<\/style>/is', $body, $match)) {
            $css = trim($match[1]);
            $body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $body)
                ?? throw new \RuntimeException("Could not strip <style> from [{$path}]: ".preg_last_error_msg());
            $body = trim($body);
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
