<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review: {{ $subject }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem; color: #1f2937; }
        iframe { width: 100%; height: 36rem; border: 1px solid #d1d5db; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input, textarea { width: 100%; padding: .5rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: .5rem 1rem; }
        .status { padding: .75rem; background: #ecfdf5; border: 1px solid #10b981; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $subject }}</h1>

    @if ($localeLinks->count() > 1)
        <nav>
            @foreach ($localeLinks as $code => $link)
                @if ($code === $locale)
                    <strong>{{ $code }}</strong>
                @else
                    <a href="{{ $link }}">{{ $code }}</a>
                @endif
            @endforeach
        </nav>
    @endif

    <iframe srcdoc="{{ $html }}" title="{{ $subject }}" sandbox></iframe>

    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf

        <label for="reviewer_name">Name</label>
        <input id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name') }}" required>
        @error('reviewer_name') <p class="error">{{ $message }}</p> @enderror

        <label for="reviewer_email">Email</label>
        <input id="reviewer_email" name="reviewer_email" type="email" value="{{ old('reviewer_email') }}" required>
        @error('reviewer_email') <p class="error">{{ $message }}</p> @enderror

        <label for="comment">Comment</label>
        <textarea id="comment" name="comment" rows="6" required>{{ old('comment') }}</textarea>
        @error('comment') <p class="error">{{ $message }}</p> @enderror

        <button type="submit">Send feedback</button>
    </form>
</body>
</html>
