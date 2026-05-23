<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Contracts\Auth\Authenticatable;

class RecipientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'view-recipients');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, Recipient $recipient): bool
    {
        return EmailComposer::userCan($user, 'view-recipient', $recipient);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'create-recipients');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Recipient $recipient): bool
    {
        return EmailComposer::userCan($user, 'update-recipient', $recipient);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Recipient $recipient): bool
    {
        return EmailComposer::userCan($user, 'delete-recipient', $recipient);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, Recipient $recipient): bool
    {
        return EmailComposer::userCan($user, 'restore-recipient', $recipient);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, Recipient $recipient): bool
    {
        return EmailComposer::userCan($user, 'force-delete-recipient', $recipient);
    }
}
