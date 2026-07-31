<?php

namespace CSeidl\EmailComposer\Models;

use Carbon\Carbon;
use CSeidl\EmailComposer\Database\Factories\EmailDraftFactory;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Services\DraftPublisher;
use CSeidl\EmailComposer\Templates\FileTemplate;
use CSeidl\EmailComposer\Templates\TemplateRenderer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Spatie\Translatable\HasTranslations;

/**
 * @property int|null $author_id
 * @property int|null $email_template_id
 * @property string|null $template_key
 * @property array<string, string> $subject
 * @property array<string, array<string, string>> $placeholders
 * @property EmailDraftStatus $status
 * @property array|null $public_files
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $sent_at
 * @property-read EmailTemplate|null $template
 */
#[UseFactory(EmailDraftFactory::class)]
class EmailDraft extends Model
{
    /** @use HasFactory<EmailDraftFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    protected $table = 'email_composer_drafts';

    protected $fillable = [
        'author_id',
        'email_template_id',
        'template_key',
        'subject',
        'placeholders',
        'status',
        'public_files',
        'submitted_at',
        'approved_at',
        'approved_by',
        'sent_at',
    ];

    /** @var array<int, string> spatie/laravel-translatable */
    public array $translatable = ['subject', 'placeholders'];

    protected function casts(): array
    {
        return [
            'status' => EmailDraftStatus::class,
            'public_files' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'approved_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'email_composer_draft_recipient')
            ->withPivot(['delivery_status', 'locale', 'sent_at', 'error', 'attempts'])
            ->withTimestamps();
    }

    public function resolveTemplate(): EmailTemplate|FileTemplate|null
    {
        if ($this->email_template_id) {
            return $this->template;
        }

        if ($this->template_key) {
            return EmailComposer::templates()->find($this->template_key);
        }

        return null;
    }

    public function subjectFor(string $locale): string
    {
        $fallback = config('email-composer.default_locale', 'en');

        return $this->getTranslation('subject', $locale, true)
            ?: $this->getTranslation('subject', $fallback, true)
            ?: '';
    }

    /**
     * Get the localized subject for the current application locale.
     */
    public function getLocalizedSubjectAttribute(): string
    {
        // $this->subject is the current locale's string, not the per-locale
        // array, so it cannot be read by locale key. subjectFor() already
        // walks locale then default_locale.
        return $this->subjectFor(app()->getLocale()) ?: 'Untitled Draft';
    }

    public function getTitleAttribute(): string
    {
        return $this->localized_subject;
    }

    /** @return array<string, string> placeholder name => value for locale (with fallback) */
    public function placeholderValues(string $locale): array
    {
        $fallback = config('email-composer.default_locale', 'en');
        $perLocale = $this->getTranslations('placeholders'); // [locale => [name => value]]
        $names = $this->resolveTemplate()?->placeholderNames() ?? [];

        $out = [];
        foreach ($names as $name) {
            $out[$name] = $perLocale[$locale][$name]
                ?? $perLocale[$fallback][$name]
                ?? $this->firstNotEmpty($perLocale, $name)
                ?? '';
        }

        return $out;
    }

    /** @param array<string, array<string, string>> $perLocale */
    protected function firstNotEmpty(array $perLocale, string $name): ?string
    {
        foreach ($perLocale as $values) {
            if (! empty($values[$name])) {
                return $values[$name];
            }
        }

        return null;
    }

    public function renderFor(string $locale): string
    {
        $template = $this->resolveTemplate();

        if (! $template) {
            throw new \DomainException("Draft #{$this->id} has no resolvable template.");
        }

        return app(TemplateRenderer::class)->render($template, $this->placeholderValues($locale), $locale);
    }

    /** Declared placeholders with no value in the given locale (nor any fallback). */
    public function missingPlaceholders(string $locale): array
    {
        $values = $this->placeholderValues($locale);

        return array_keys(array_filter($values, fn ($v) => $v === ''));
    }

    public function viewInBrowserUrl(string $locale): string
    {
        return URL::signedRoute('email-composer.view-in-browser', [
            'draft' => $this->getKey(),
            'locale' => $locale,
        ]);
    }

    public function submit(): void
    {
        $this->assertStatus(EmailDraftStatus::Draft, 'submit');
        $this->update([
            'status' => EmailDraftStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }

    public function approve(Authenticatable $approver): void
    {
        $this->assertStatus(EmailDraftStatus::UnderReview, 'approve');
        $this->update([
            'status' => EmailDraftStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->getAuthIdentifier(),
        ]);

        app(DraftPublisher::class)->publish($this->refresh());
    }

    public function markSent(): void
    {
        $this->assertStatus(EmailDraftStatus::Approved, 'mark as sent');
        $this->update([
            'status' => EmailDraftStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function send(): void
    {
        $this->markSent();
    }

    public function returnToDraft(): void
    {
        $this->assertStatus(EmailDraftStatus::UnderReview, 'return to draft');
        $this->update([
            'status' => EmailDraftStatus::Draft,
            'submitted_at' => null,
        ]);
    }

    protected function assertStatus(EmailDraftStatus $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw new \DomainException(
                "Cannnot {$action} draft #{$this->id}: status is '{$this->status->value}', expected '{$expected->value}'."
            );
        }
    }

    // --- Scopes (#[Scope]) ---

    #[Scope]
    protected function ownedBy(Builder $query, int $userId): void
    {
        $query->where('author_id', $userId);
    }

    #[Scope]
    protected function inStatus(Builder $query, EmailDraftStatus $status): void
    {
        $query->where('status', $status->value);
    }
}
