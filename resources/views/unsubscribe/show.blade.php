<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('email-composer::unsubscribe.title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem; color: #1f2937; }
        button { margin-top: 1rem; padding: .5rem 1rem; }
    </style>
</head>
<body>
    <h1>{{ __('email-composer::unsubscribe.title') }}</h1>

    <p>{{ __('email-composer::unsubscribe.confirm', ['email' => $email]) }}</p>

    <form method="POST" action="{{ $action }}">
        @csrf
        <button type="submit">{{ __('email-composer::unsubscribe.button') }}</button>
    </form>
</body>
</html>
