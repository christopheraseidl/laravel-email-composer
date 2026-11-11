<?php

namespace CSeidl\EmailComposer\Commands;

use CSeidl\EmailComposer\Actions\Drafts\QueueSends;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Console\Command;

class QueueSendsCommand extends Command
{
    protected $signature = 'email-composer:queue-sends';

    protected $description = 'Queue the sends that each sending draft\'s rate limits have room for';

    public function handle(QueueSends $queueSends): int
    {
        EmailDraft::where('status', EmailDraftStatus::Sending)->each(fn (EmailDraft $draft) => $queueSends($draft));

        return self::SUCCESS;
    }
}
