<?php

namespace CSeidl\EmailComposer\Data;

readonly class SendSummary
{
    /**
     * @param  int  $total  the audience, unsubscribed recipients included
     * @param  int  $skipped  unsubscribed recipients
     * @param  array<string, int>  $perLocale  delivery locale to recipients sent in it
     * @param  array<string, list<string>>  $missingPlaceholders  delivery locale to placeholders with no value
     */
    public function __construct(
        public int $total = 0,
        public int $skipped = 0,
        public array $perLocale = [],
        public array $missingPlaceholders = [],
    ) {}
}
