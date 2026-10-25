<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> subscribed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> unsubscribed()
 */
#[UseFactory(RecipientFactory::class)]
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'email_composer_recipients';

    protected $fillable = ['name', 'email', 'locale', 'metadata', 'unsubscribed_at'];

    protected $casts = [
        'metadata' => 'array',
        'unsubscribed_at' => 'datetime',
    ];

    /** Set by forTestSend(); never stored. */
    protected bool $test = false;

    /**
     * An unsaved recipient for a test send. Mail to it carries no
     * unsubscribe link and a "[Test] " subject prefix.
     */
    public static function forTestSend(string $email, string $locale): self
    {
        $recipient = new self(['name' => $email, 'email' => $email, 'locale' => $locale]);
        $recipient->test = true;

        return $recipient;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    /** @return BelongsToMany<RecipientList, $this> */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(RecipientList::class, 'email_composer_list_recipient')
            ->withTimestamps();
    }

    /**
     * Mark the recipient as unsubscribed, keeping the first unsubscribe time.
     */
    public function unsubscribe(): void
    {
        if ($this->unsubscribed_at === null) {
            $this->update(['unsubscribed_at' => now()]);
        }
    }

    /**
     * Scope the query to only include subscribed recipients.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function subscribed(Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }

    /**
     * Scope the query to only include unsubscribed recipients.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unsubscribed(Builder $query): void
    {
        $query->whereNotNull('unsubscribed_at');
    }
}
