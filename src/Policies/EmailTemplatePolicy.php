<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'view-templates');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, EmailTemplate $template): bool
    {
        return EmailComposer::userCan($user, 'view-template', $template);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'create-templates');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, EmailTemplate $template): bool
    {
        return EmailComposer::userCan($user, 'update-template', $template);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, EmailTemplate $template): bool
    {
        return EmailComposer::userCan($user, 'delete-template', $template);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, EmailTemplate $template): bool
    {
        return EmailComposer::userCan($user, 'restore-template', $template);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, EmailTemplate $template): bool
    {
        return EmailComposer::userCan($user, 'force-delete-template', $template);
    }
}
