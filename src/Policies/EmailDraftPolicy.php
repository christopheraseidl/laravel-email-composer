<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailDraftPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'view-any-draft')
            || EmailComposer::userCan($user, 'view-draft');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, EmailDraft $draft): bool
    {
        if (EmailComposer::userCan($user, 'view-any-draft')) {
            return true;
        }

        return EmailComposer::userCan($user, 'view-draft', $draft);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'create-draft');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, EmailDraft $draft): bool
    {
        if ($draft->status !== EmailDraftStatus::Draft) {
            return false; // only drafts in 'draft' status are editable
        }

        return EmailComposer::userCan($user, 'update-draft', $draft);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, EmailDraft $draft): bool
    {
        if ($draft->status === EmailDraftStatus::Sent) {
            return false; // cannot delete a sent draft
        }

        return EmailComposer::userCan($user, 'delete-draft', $draft);
    }

    /**
     * Determine whether the user can submit the draft for review.
     */
    public function submit(Authenticatable $user, EmailDraft $draft): bool
    {
        return EmailComposer::userCan($user, 'submit-draft', $draft)
            && $draft->status === EmailDraftStatus::Draft;
    }

    /**
     * Determine whether the user can approve the draft for sending.
     */
    public function approve(Authenticatable $user, EmailDraft $draft): bool
    {
        return EmailComposer::userCan($user, 'approve-draft', $draft)
            && $draft->status === EmailDraftStatus::UnderReview;
    }

    /**
     * Determine whether the user can send the draft.
     */
    public function send(Authenticatable $user, EmailDraft $draft): bool
    {
        return EmailComposer::userCan($user, 'send-draft', $draft)
            && $draft->status === EmailDraftStatus::Approved;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, EmailDraft $draft): bool
    {
        return EmailComposer::userCan($user, 'restore-draft', $draft);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, EmailDraft $draft): bool
    {
        if ($draft->status === EmailDraftStatus::Sent) {
            return false; // cannot force delete a sent draft
        }

        return EmailComposer::userCan($user, 'force-delete-draft', $draft);
    }
}
