<!DOCTYPE html>
    <html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>{!! $css !!}</style>
    </head>
    <body>
        @if (config('email-composer.path'))
            <img src="{{ config('email-composer.path')}}"
                 alt="{{ config('email-composer.alt')}}">
        @endif

        {!! $body !!}
    </body>
</html>