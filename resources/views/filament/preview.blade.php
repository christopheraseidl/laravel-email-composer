@if ($error)
    <p style="color: #b91c1c;">{{ $error }}</p>
@else
    <div style="display: grid; gap: .75rem;">
        <p style="font-weight: 600;">{{ $subject }}</p>

        <iframe srcdoc="{{ $html }}" title="{{ $subject }}" sandbox="" style="width: 100%; height: 36rem; border: 1px solid #d1d5db; background: #fff;"></iframe>
    </div>
@endif
