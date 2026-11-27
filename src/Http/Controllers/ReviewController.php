<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use Carbon\Carbon;
use CSeidl\EmailComposer\Actions\Feedback\StoreReviewFeedback;
use CSeidl\EmailComposer\Http\Requests\StoreReviewFeedbackRequest;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Review\ReviewLink;
use CSeidl\EmailComposer\Services\ConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @internal
 */
class ReviewController
{
    public function __construct(protected ConfigService $config) {}

    public function show(Request $request, EmailDraft $draft): Response
    {
        $locales = $this->config->locales();
        $locale = $this->locale($request);

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

    public function store(StoreReviewFeedbackRequest $request, EmailDraft $draft): RedirectResponse
    {
        app(StoreReviewFeedback::class)($draft, $request->validated());

        $locale = $this->locale($request);

        return redirect()->to($request->fullUrl())
            ->with('status', __('email-composer::review.notifications.thank_you', [], $locale));
    }

    /** The link's locale, then the app's, then default_locale; the first one configured wins. */
    protected function locale(Request $request): string
    {
        $locale = $request->query('locale');

        return $this->config->localeOrDefault(in_array($locale, $this->config->locales(), true) ? $locale : app()->getLocale());
    }
}
