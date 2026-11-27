<?php

namespace CSeidl\EmailComposer\Policies;

/**
 * @internal
 */
class EmailTemplatePolicy extends ResourcePolicy
{
    protected function singular(): string
    {
        return 'template';
    }

    protected function plural(): string
    {
        return 'templates';
    }
}
