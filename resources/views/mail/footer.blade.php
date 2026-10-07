<div style="margin-top: 24px; padding: 16px; font-size: 12px; color: #6b7280; text-align: center;">
    <p style="margin: 0 0 8px;">
        <a href="{{ $viewInBrowserUrl }}" style="color: #6b7280;">{{ __('email-composer::mail.view_in_browser') }}</a>
    </p>
    <p style="margin: 0;">
        {{ __('email-composer::mail.sent_to', ['email' => $recipient->email]) }}
        <a href="{{ $unsubscribeUrl }}" style="color: #6b7280;">{{ __('email-composer::mail.unsubscribe') }}</a>
    </p>
</div>
