<?php

namespace CSeidl\EmailComposer\Templates;

class FileTemplate
{
    /** @param array<int, string> $placeholders */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $body,
        public readonly array $placeholders,
        public readonly ?string $css,
        public readonly string $sourcePath,
    ) {}

    /** @return array<int, string> */
    public function placeholderNames(): array
    {
        return $this->placeholders;
    }
}
