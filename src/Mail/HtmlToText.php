<?php

namespace CSeidl\EmailComposer\Mail;

class HtmlToText
{
    public function convert(string $html): string
    {
        $text = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;

        $text = preg_replace_callback(
            '#<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a\s*>#is',
            function (array $match): string {
                $url = trim($match[2]);
                $label = trim(strip_tags($match[3]));

                return $label === '' || $label === $url ? $url : "{$label} ({$url})";
            },
            $text,
        ) ?? $text;

        $text = preg_replace('#</?(br|p|div|tr|li|h[1-6])\b[^>]*>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_map(trim(...), preg_split('/\R/u', $text) ?: [$text]);

        return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)) ?? '');
    }
}
