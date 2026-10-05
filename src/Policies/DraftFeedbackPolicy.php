<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\DraftFeedback;
use Illuminate\Contracts\Auth\Authenticatable;

class DraftFeedbackPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'view-feedback');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, DraftFeedback $feedback): bool
    {
        return EmailComposer::userCan($user, 'view-feedback', $feedback);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, 'comment-on-draft');
    }

    /**
     * Determine whether the user can update the model (mark it resolved).
     */
    public function update(Authenticatable $user, DraftFeedback $feedback): bool
    {
        return EmailComposer::userCan($user, 'update-feedback', $feedback);
    }
}
