<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Contracts\Auth\Authenticatable;

class RecipientListPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'view-recipient-lists');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, RecipientList $recipientList): bool
    {
        return EmailComposer::userCan($user, 'view-recipient-list', $recipientList);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'create-recipient-lists');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, RecipientList $recipientList): bool
    {
        return EmailComposer::userCan($user, 'update-recipient-list', $recipientList);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, RecipientList $recipientList): bool
    {
        return EmailComposer::userCan($user, 'delete-recipient-list', $recipientList);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, RecipientList $recipientList): bool
    {
        return EmailComposer::userCan($user, 'restore-recipient-list', $recipientList);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, RecipientList $recipientList): bool
    {
        return EmailComposer::userCan($user, 'force-delete-recipient-list', $recipientList);
    }
}
