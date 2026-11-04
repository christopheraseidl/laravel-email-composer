<?php

namespace CSeidl\EmailComposer\Data;

readonly class SendSummary
{
    /**
     * @param  int  $total  the audience, unsubscribed recipients included
     * @param  int  $skipped  unsubscribed recipients
     * @param  array<string, int>  $perLocale  delivery locale to recipients sent in it
     * @param  array<string, list<string>>  $missingPlaceholders  delivery locale to placeholders with no value
     * @param  int  $resent  sendable recipients this draft was already sent to
     */
    public function __construct(
        public int $total = 0,
        public int $skipped = 0,
        public array $perLocale = [],
        public array $missingPlaceholders = [],
        public int $resent = 0,
    ) {}
}
