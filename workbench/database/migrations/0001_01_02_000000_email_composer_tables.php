<?php

use CSeidl\EmailComposer\EmailComposerServiceProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (EmailComposerServiceProvider::MIGRATIONS as $migration) {
            (include __DIR__."/../../../database/migrations/{$migration}.php.stub")->up();
        }
    }
};
