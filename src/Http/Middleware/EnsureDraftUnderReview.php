<?php

namespace CSeidl\EmailComposer\Http\Middleware;

use Closure;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class EnsureDraftUnderReview
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $draft = $request->route('draft');

        abort_unless($draft instanceof EmailDraft && $draft->status === EmailDraftStatus::UnderReview, 404);

        return $next($request);
    }
}
