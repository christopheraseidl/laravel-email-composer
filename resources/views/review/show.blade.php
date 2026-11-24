<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('email-composer::review.title', ['subject' => $subject], $locale) }}</title>
    {{-- Filament's bundled Inter, when the host app has published Filament's assets. --}}
    @if (is_file(public_path('fonts/filament/filament/inter/index.css')))
        <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">
    @endif
    {{-- Filament's default zinc grays and amber primary. --}}
    <style>
        :root {
            --gray-50: oklch(0.985 0 0);
            --gray-200: oklch(0.92 0.004 286.32);
            --gray-400: oklch(0.705 0.015 286.067);
            --gray-500: oklch(0.552 0.016 285.938);
            --gray-900: oklch(0.21 0.006 285.885);
            --gray-950: oklch(0.141 0.005 285.823);
            --primary-300: oklch(0.879 0.169 91.605);
            --primary-400: oklch(0.828 0.189 84.429);
            --primary-500: oklch(0.769 0.188 70.08);
            --primary-600: oklch(0.666 0.179 58.318);
            --primary-700: oklch(0.555 0.163 48.998);
            --primary-800: oklch(0.473 0.137 46.201);
            --primary-900: oklch(0.414 0.112 45.904);
            --primary-950: oklch(0.279 0.077 45.635);
            --danger-400: oklch(0.704 0.191 22.216);
            --danger-500: oklch(0.637 0.237 25.331);
            --danger-600: oklch(0.577 0.245 27.325);
            --success-50: oklch(0.982 0.018 155.826);
            --success-400: oklch(0.792 0.209 151.711);
            --success-600: oklch(0.627 0.194 149.214);

            --page-bg: var(--gray-50);
            --card-bg: #fff;
            --card-ring: oklch(0.141 0.005 285.823 / 0.05);
            --divider: var(--gray-200);
            --text: var(--gray-950);
            --muted: var(--gray-500);
            --input-bg: #fff;
            --input-ring: oklch(0.141 0.005 285.823 / 0.1);
            --focus-ring: var(--primary-600);
            --danger: var(--danger-600);
            --tab-active-bg: var(--gray-50);
            --tab-active-text: var(--primary-700);
            --btn-bg: var(--primary-400);
            --btn-hover-bg: var(--primary-300);
            --btn-text: var(--primary-900);
            --btn-hover-text: var(--primary-800);
            --success-bg: var(--success-50);
            --success-ring: oklch(0.627 0.194 149.214 / 0.2);
            --success-icon: var(--success-600);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --page-bg: var(--gray-950);
                --card-bg: var(--gray-900);
                --card-ring: rgb(255 255 255 / 0.1);
                --divider: rgb(255 255 255 / 0.1);
                --text: #fff;
                --muted: var(--gray-400);
                --input-bg: rgb(255 255 255 / 0.05);
                --input-ring: rgb(255 255 255 / 0.2);
                --focus-ring: var(--primary-500);
                --danger: var(--danger-400);
                --tab-active-bg: rgb(255 255 255 / 0.05);
                --tab-active-text: var(--primary-400);
                --btn-bg: var(--primary-600);
                --btn-hover-bg: var(--primary-500);
                --btn-text: var(--primary-950);
                --btn-hover-text: var(--primary-950);
                --success-bg: oklch(0.627 0.194 149.214 / 0.1);
                --success-ring: oklch(0.792 0.209 151.711 / 0.2);
                --success-icon: var(--success-400);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page-bg);
            color: var(--text);
            font-family: 'Inter Variable', Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 0.875rem;
            line-height: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .page { max-width: 80rem; margin: 0 auto; padding: 2rem 1rem; display: grid; gap: 2rem; }

        .header { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .header-text { display: grid; gap: 0.5rem; min-width: 0; }
        .eyebrow { margin: 0; font-weight: 500; color: var(--muted); }
        .heading { margin: 0; font-size: 1.5rem; line-height: 2rem; font-weight: 700; letter-spacing: -0.025em; overflow-wrap: anywhere; }
        .subheading { margin: 0; color: var(--muted); }

        .tabs { display: flex; gap: 0.25rem; max-width: 100%; overflow-x: auto; padding: 0.5rem; border-radius: 0.75rem; background: var(--card-bg); box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05), 0 0 0 1px var(--card-ring); }
        .tab { padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-weight: 500; color: var(--muted); text-decoration: none; text-transform: uppercase; white-space: nowrap; transition: background-color 75ms, color 75ms; }
        .tab:hover, .tab:focus-visible { background: var(--tab-active-bg); color: var(--text); outline: none; }
        .tab[aria-current] { background: var(--tab-active-bg); color: var(--tab-active-text); }

        .callout { display: flex; gap: 0.75rem; align-items: flex-start; padding: 1rem; border-radius: 0.75rem; background: var(--success-bg); box-shadow: 0 0 0 1px var(--success-ring); font-weight: 500; }
        .callout svg { flex-shrink: 0; width: 1.5rem; height: 1.5rem; color: var(--success-icon); }

        .layout { display: grid; gap: 1.5rem; align-items: start; }

        .section { border-radius: 0.75rem; background: var(--card-bg); box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05), 0 0 0 1px var(--card-ring); overflow: hidden; }
        .section-header { padding: 1rem 1.5rem; display: grid; gap: 0.25rem; border-bottom: 1px solid var(--divider); }
        .section-heading { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; }
        .section-description { margin: 0; color: var(--muted); }
        .section-content { padding: 1.5rem; }

        .preview { display: block; width: 100%; height: 70vh; min-height: 32rem; border: 0; background: #fff; }

        .form { display: grid; gap: 1.5rem; }
        .field { display: grid; gap: 0.5rem; }
        .label { font-weight: 500; }
        .required { color: var(--danger); font-weight: 500; }
        .input {
            display: block;
            width: 100%;
            margin: 0;
            padding: 0.375rem 0.75rem;
            border: 0;
            border-radius: 0.5rem;
            background: var(--input-bg);
            color: var(--text);
            font: inherit;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05), 0 0 0 1px var(--input-ring);
            transition: box-shadow 75ms;
        }
        textarea.input { resize: vertical; min-height: 9rem; }
        .input::placeholder { color: var(--gray-400); }
        .input:focus { outline: none; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05), 0 0 0 2px var(--focus-ring); }
        .input[aria-invalid] { box-shadow: 0 0 0 1px var(--danger); }
        .input[aria-invalid]:focus { box-shadow: 0 0 0 2px var(--danger); }
        .error { margin: 0; color: var(--danger); }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 0;
            border-radius: 0.5rem;
            background: var(--btn-bg);
            color: var(--btn-text);
            font: inherit;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            transition: background-color 75ms, color 75ms;
        }
        .button:hover { background: var(--btn-hover-bg); color: var(--btn-hover-text); }
        .button:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 2px; }

        @media (min-width: 640px) {
            .page { padding: 2rem 1.5rem; }
            .heading { font-size: 1.875rem; line-height: 2.25rem; }
        }

        @media (min-width: 1024px) {
            .page { padding: 2rem; }
            .layout { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
            .layout > .section:last-child { position: sticky; top: 2rem; }
            .preview { height: calc(100vh - 12rem); }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <div class="header-text">
                <p class="eyebrow">{{ __('email-composer::review.eyebrow', [], $locale) }}</p>
                <h1 class="heading">{{ $subject }}</h1>
                <p class="subheading">{{ __('email-composer::review.description', [], $locale) }}</p>
            </div>

            @if ($localeLinks->count() > 1)
                <nav class="tabs" aria-label="{{ __('email-composer::review.locale', [], $locale) }}">
                    @foreach ($localeLinks as $code => $link)
                        <a class="tab" href="{{ $link }}" hreflang="{{ $code }}" @if ($code === $locale) aria-current="page" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            @endif
        </header>

        @if (session('status'))
            <div class="callout" role="status">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="layout">
            <section class="section" aria-labelledby="preview-heading">
                <div class="section-header">
                    <h2 class="section-heading" id="preview-heading">{{ __('email-composer::review.sections.preview', [], $locale) }}</h2>
                </div>
                <iframe class="preview" srcdoc="{{ $html }}" title="{{ $subject }}" sandbox></iframe>
            </section>

            <section class="section" aria-labelledby="feedback-heading">
                <div class="section-header">
                    <h2 class="section-heading" id="feedback-heading">{{ __('email-composer::review.sections.feedback', [], $locale) }}</h2>
                    <p class="section-description">{{ __('email-composer::review.sections.feedback_description', [], $locale) }}</p>
                </div>

                <form class="section-content form" method="POST" action="{{ $action }}">
                    @csrf

                    <div class="field">
                        <label class="label" for="reviewer_name">{{ __('email-composer::review.fields.name', [], $locale) }}<span class="required">*</span></label>
                        <input class="input" id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name') }}" autocomplete="name" required @error('reviewer_name') aria-invalid="true" aria-describedby="reviewer_name-error" @enderror>
                        @error('reviewer_name') <p class="error" id="reviewer_name-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="reviewer_email">{{ __('email-composer::review.fields.email', [], $locale) }}<span class="required">*</span></label>
                        <input class="input" id="reviewer_email" name="reviewer_email" type="email" value="{{ old('reviewer_email') }}" autocomplete="email" required @error('reviewer_email') aria-invalid="true" aria-describedby="reviewer_email-error" @enderror>
                        @error('reviewer_email') <p class="error" id="reviewer_email-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="comment">{{ __('email-composer::review.fields.comment', [], $locale) }}<span class="required">*</span></label>
                        <textarea class="input" id="comment" name="comment" rows="6" required placeholder="{{ __('email-composer::review.placeholders.comment', [], $locale) }}" @error('comment') aria-invalid="true" aria-describedby="comment-error" @enderror>{{ old('comment') }}</textarea>
                        @error('comment') <p class="error" id="comment-error">{{ $message }}</p> @enderror
                    </div>

                    <button class="button" type="submit">{{ __('email-composer::review.actions.submit', [], $locale) }}</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
