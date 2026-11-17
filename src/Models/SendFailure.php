<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\SendFailureFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One send that threw. Each used a rate limit slot.
 *
 * @property int $email_draft_id
 * @property int $recipient_id
 * @property string $error
 * @property Carbon $failed_at
 * @property-read EmailDraft $draft
 * @property-read Recipient $recipient
 */
#[UseFactory(SendFailureFactory::class)]
class SendFailure extends Model
{
    /** @use HasFactory<SendFailureFactory> */
    use HasFactory;

    protected $table = 'email_composer_send_failures';

    public $timestamps = false;

    protected $fillable = [
        'email_draft_id',
        'recipient_id',
        'error',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmailDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(EmailDraft::class, 'email_draft_id');
    }

    /** @return BelongsTo<Recipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'recipient_id')->withTrashed();
    }
}
