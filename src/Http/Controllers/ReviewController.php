<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use Carbon\Carbon;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Review\ReviewLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReviewController
{
    public function show(Request $request, EmailDraft $draft): Response
    {
        $this->ensureUnderReview($draft);

        $locales = EmailComposer::config()->locales();
        $locale = EmailComposer::config()->localeOrDefault($request->query('locale'));

        $expires = Carbon::createFromTimestamp((int) $request->query('expires'));
        $localeLinks = collect($locales)->mapWithKeys(fn (string $code) => [
            $code => ReviewLink::for($draft, $code, $expires),
        ]);

        return response()->view('email-composer::review.show', [
            'locale' => $locale,
            'localeLinks' => $localeLinks,
            'subject' => $draft->subjectFor($locale),
            'html' => $draft->renderFor($locale),
            'action' => $request->fullUrl(),
        ])->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
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
