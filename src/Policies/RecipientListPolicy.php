<?php

namespace CSeidl\EmailComposer\Policies;

/**
 * @internal
 */
class RecipientListPolicy extends ResourcePolicy
{
    protected function singular(): string
    {
        return 'recipient-list';
    }

    protected function plural(): string
    {
        return 'recipient-lists';
    }
}
