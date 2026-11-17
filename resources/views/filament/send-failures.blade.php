<table style="width: 100%; border-collapse: collapse; text-align: left;">
    <thead>
        <tr>
            <th style="padding: .5rem; border-bottom: 1px solid #d1d5db;">{{ __('email-composer::drafts.deliveries.failures.failed_at') }}</th>
            <th style="padding: .5rem; border-bottom: 1px solid #d1d5db;">{{ __('email-composer::drafts.deliveries.failures.error') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($failures as $failure)
            <tr>
                <td style="padding: .5rem; vertical-align: top; white-space: nowrap;">{{ $failure->failed_at->translatedFormat('M j, Y H:i:s') }}</td>
                <td style="padding: .5rem; vertical-align: top;">{{ $failure->error }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
