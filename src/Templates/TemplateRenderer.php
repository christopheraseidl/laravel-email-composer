<?php

namespace CSeidl\EmailComposer\Templates;

use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\EmailTheme;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\View;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class TemplateRenderer
{
    /**
     * @param  array<string, string>  $values  placeholder name => value, already resolved to one locale
     */
    public function render(EmailTemplate|FileTemplate $template, array $values, string $locale): string
    {
        $theme = EmailTheme::current();
        $body = $this->interpolate($template->body, $values);
        $css = $this->assembleCss($theme, $template);

        $html = View::make('email-composer::layout', compact('body', 'css', 'locale', 'theme'))->render();
        $html = (new CssToInlineStyles)->convert($html, $css);

        return $this->sanitize($html);
    }

    /** @param array<string, string> $values */
    protected function interpolate(string $body, array $values): string
    {
        // Values are inserted raw to allow rich-text (HTML) fields; the final
        // sanitize() pass over the whole document is the XSS boundary. A single
        // replace pass means an injected value cannot smuggle in new tokens.
        return preg_replace_callback(
            '/\[\[\s*([a-zA-Z0-9_]+)\s*\]\]/',
            fn ($m) => $values[$m[1]] ?? '',
            $body
        ) ?? throw new \RuntimeException('Could not interpolate placeholders: '.preg_last_error_msg());
    }

    protected function assembleCss(EmailTheme $theme, EmailTemplate|FileTemplate $template): string
    {
        // Template CSS overrides the global theme.
        return trim($theme->css."\n".($template->css ?? ''));
    }

    protected function sanitize(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', null);
        $config->set('CSS.AllowImportant', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return (new HTMLPurifier($config))->purify($html);
    }
}
