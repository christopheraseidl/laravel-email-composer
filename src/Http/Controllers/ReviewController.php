<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use Carbon\Carbon;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Review\ReviewLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController
{
    public function show(Request $request, EmailDraft $draft): View
    {
        $this->ensureUnderReview($draft);

        $locales = config('email-composer.locales');
        $locale = in_array($request->query('locale'), $locales, true)
            ? $request->query('locale')
            : config('email-composer.default_locale');

        // A locale appended to a signed URL would break its signature, so each
        // switch link is signed again with the original expiry.
        $expires = Carbon::createFromTimestamp((int) $request->query('expires'));
        $localeLinks = collect($locales)->mapWithKeys(fn (string $code) => [
            $code => ReviewLink::for($draft, $code, $expires),
        ]);

        return view('email-composer::review.show', [
            'locale' => $locale,
            'localeLinks' => $localeLinks,
            'subject' => $draft->subjectFor($locale),
            'html' => $draft->renderFor($locale),
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, EmailDraft $draft): RedirectResponse
    {
        $this->ensureUnderReview($draft);

        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:255'],
            'reviewer_email' => ['required', 'email', 'max:255'],
            'comment' => ['required', 'string', 'max:10000'],
        ]);

        $draft->feedback()->create($validated);

        return redirect()->to($request->fullUrl())
            ->with('status', 'Thank you, your feedback has been sent.');
    }

    protected function ensureUnderReview(EmailDraft $draft): void
    {
        abort_unless($draft->status === EmailDraftStatus::UnderReview, 404);
    }
}
