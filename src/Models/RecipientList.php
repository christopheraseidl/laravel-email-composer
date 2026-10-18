<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\RecipientListFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[UseFactory(RecipientListFactory::class)]
class RecipientList extends Model
{
    /** @use HasFactory<RecipientListFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'email_composer_recipient_lists';

    protected $fillable = ['name'];

    /** @return BelongsToMany<Recipient, $this> */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'email_composer_list_recipient')
            ->withTimestamps();
    }
}
