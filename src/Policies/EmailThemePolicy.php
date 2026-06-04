<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTheme;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailThemePolicy
{
    /**
     * Determine whether the user can manage the model.
     */
    public function view(Authenticatable $user, EmailTheme $emailTheme): bool
    {
        return EmailComposer::userCan($user, 'view-theme', $emailTheme);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, EmailTheme $emailTheme): bool
    {
        return EmailComposer::userCan($user, 'update-theme', $emailTheme);
    }
}
