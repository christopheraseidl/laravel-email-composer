<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static> subscribed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> unsubscribed()
 */
#[UseFactory(RecipientFactory::class)]
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory;

    protected $table = 'email_composer_recipients';

    protected $fillable = ['name', 'email', 'locale', 'metadata', 'unsubscribed_at'];

    protected $casts = [
        'metadata' => 'array',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Scope the query to only include subscribed recipients.
     */
    #[Scope]
    protected function subscribed(Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }

    /**
     * Scope the query to only include unscubscribed recipients.
     */
    #[Scope]
    protected function unsubscribed(Builder $query): void
    {
        $query->whereNotNull('unsubscribed_at');
    }
}
