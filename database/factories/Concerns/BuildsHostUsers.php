<?php

namespace CSeidl\EmailComposer\Database\Factories\Concerns;

use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

trait BuildsHostUsers
{
    /**
     * Get a new factory for the host User.
     *
     * @return Factory<Model>
     */
    protected function userFactory(): Factory
    {
        $userModel = EmailComposer::config()->userModel();

        if (! method_exists($userModel, 'factory')) {
            throw new \LogicException("{$userModel} must use HasFactory to build drafts in factories.");
        }

        return $userModel::factory();
    }
}
