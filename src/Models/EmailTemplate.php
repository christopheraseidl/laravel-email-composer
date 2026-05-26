<?php

namespace CSeidl\EmailComposer\Models;

use CSeidl\EmailComposer\Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(EmailTemplateFactory::class)]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'email_composer_templates';

    protected $fillable = ['key', 'name', 'body', 'placeholders', 'css',];

    protected $casts = [
        'placeholders' => 'array',
    ];

    /**
     * The list of declared placeholder names for this template.
     * Equivalent to $this->placeholders; provided as a method for symmetry with FileTemplate.
     *
     * @return array<int, string>
     */
    public function placeholderNames(): array
    {
        return $this->placeholders ?? [];
    }
}
