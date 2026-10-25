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
        $body = $this->sanitize($this->interpolate($template->body, $values));
        $css = $this->assembleCss($theme, $template);

        $html = View::make('email-composer::layout', compact('body', 'css', 'locale', 'theme'))->render();

        return (new CssToInlineStyles)->convert($html, $css);
    }

    /** @param array<string, string> $values */
    protected function interpolate(string $body, array $values): string
    {
        return preg_replace_callback(
            '/\[\[\s*([a-zA-Z0-9_]+)\s*\]\]/',
            fn ($m) => $values[$m[1]] ?? '',
            $body
        ) ?? throw new \RuntimeException('Could not interpolate placeholders: '.preg_last_error_msg());
    }

    protected function assembleCss(EmailTheme $theme, EmailTemplate|FileTemplate $template): string
    {
        return str_replace('<', '', trim($theme->css."\n".($template->css ?? '')));
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
