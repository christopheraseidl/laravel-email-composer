<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('email-composer::unsubscribe.done_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem; color: #1f2937; }
    </style>
</head>
<body>
    <h1>{{ __('email-composer::unsubscribe.done_title') }}</h1>

    <p>{{ __('email-composer::unsubscribe.done', ['email' => $email]) }}</p>
</body>
</html>
