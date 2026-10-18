<!DOCTYPE html>
    <html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>{!! $css !!}</style>
    </head>
    <body>
        @php($logoSrc = $theme->logoSrc())
        @if ($logoSrc)
            <img src="{{ $logoSrc }}" alt="{{ $theme->logoAlt() }}">
        @endif

        {!! $body !!}
    </body>
</html>