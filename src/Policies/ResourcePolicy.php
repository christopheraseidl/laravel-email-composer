<?php

namespace CSeidl\EmailComposer\Policies;

use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The CRUD checks, as abilities named from the resource's nouns: view-{plural},
 * view-{singular}, create-{plural}, update-{singular} and so on.
 */
abstract class ResourcePolicy
{
    /** The noun in abilities on one model, such as 'recipient'. */
    abstract protected function singular(): string;

    /** The noun in abilities on the resource as a whole, such as 'recipients'. */
    abstract protected function plural(): string;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, "view-{$this->plural()}");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, Model $model): bool
    {
        return EmailComposer::userCan($user, "view-{$this->singular()}", $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user): bool
    {
        return EmailComposer::userCan($user, "create-{$this->plural()}");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Model $model): bool
    {
        return EmailComposer::userCan($user, "update-{$this->singular()}", $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Model $model): bool
    {
        return EmailComposer::userCan($user, "delete-{$this->singular()}", $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authenticatable $user, Model $model): bool
    {
        return EmailComposer::userCan($user, "restore-{$this->singular()}", $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authenticatable $user, Model $model): bool
    {
        return EmailComposer::userCan($user, "force-delete-{$this->singular()}", $model);
    }
}
