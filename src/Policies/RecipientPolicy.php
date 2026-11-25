<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Contracts\Auth\Authenticatable;

class RecipientPolicy extends ResourcePolicy
{
    protected function singular(): string
    {
        return 'recipient';
    }

    protected function plural(): string
    {
        return 'recipients';
    }

    /**
     * Determine whether the user can import models from a CSV.
     */
    public function import(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'import-recipients');
    }
}
