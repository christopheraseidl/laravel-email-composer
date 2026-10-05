<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\DraftFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $email_draft_id
 * @property int|null $user_id
 * @property string|null $reviewer_name
 * @property string|null $reviewer_email
 * @property string $comment
 * @property bool $resolved
 * @property-read EmailDraft $draft
 */
#[UseFactory(DraftFeedbackFactory::class)]
class DraftFeedback extends Model
{
    /** @use HasFactory<DraftFeedbackFactory> */
    use HasFactory;

    protected $table = 'email_composer_draft_feedback';

    protected $fillable = [
        'email_draft_id',
        'user_id',
        'reviewer_name',
        'reviewer_email',
        'comment',
        'resolved',
    ];

    protected function casts(): array
    {
        return [
            'resolved' => 'bool',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(EmailDraft::class, 'email_draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
