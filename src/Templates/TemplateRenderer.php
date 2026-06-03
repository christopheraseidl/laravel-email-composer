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
        $body = $this->interpolate($template->body, $values);
        $css = $this->assembleCss($template);

        $html = View::make('email-composer::layout', compact('body', 'css', 'locale'))->render();
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
        );
    }

    protected function assembleCss(EmailTemplate|FileTemplate $template): string
    {
        // Template CSS overrides the global theme.
        return trim(EmailTheme::current()->css."\n".($template->css ?? ''));
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
