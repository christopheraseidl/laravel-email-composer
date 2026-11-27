<?php

namespace CSeidl\EmailComposer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * @internal
 */
class StoreReviewFeedbackRequest extends FormRequest
{
    /** The signed middleware is the authorization. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reviewer_name' => ['required', 'string', 'max:255'],
            'reviewer_email' => ['required', 'email', 'max:255'],
            'comment' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Field names follow the app locale, as the validation messages do.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reviewer_name' => Str::lower(__('email-composer::review.fields.name')),
            'reviewer_email' => Str::lower(__('email-composer::review.fields.email')),
            'comment' => Str::lower(__('email-composer::review.fields.comment')),
        ];
    }
}
