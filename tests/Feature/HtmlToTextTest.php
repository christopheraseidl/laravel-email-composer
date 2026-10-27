<?php

use CSeidl\EmailComposer\Mail\HtmlToText;

function toText(string $html): string
{
    return (new HtmlToText)->convert($html);
}

it('drops head, style and script blocks', function () {
    $html = '<html><head><title>Title</title></head><body>'
        .'<style type="text/css">p { color: red; }</style>'
        .'<p>Body</p>'
        .'<SCRIPT>alert(1)</SCRIPT></body></html>';

    expect(toText($html))->toBe('Body');
});

it('writes a link as its text followed by its url', function () {
    expect(toText('<p>Read <a href="https://example.com/news" class="x">the <b>news</b></a> now</p>'))
        ->toBe('Read the news (https://example.com/news) now');
});

it('writes only the url when the link text is the url', function () {
    expect(toText("<a href='https://example.com'>https://example.com</a>"))
        ->toBe('https://example.com');
});

it('breaks the line at a line break', function (string $html) {
    expect(toText($html))->toBe("One\nTwo");
})->with([
    'br' => 'One<br>Two',
    'self-closing br' => 'One<br />Two',
]);

it('leaves a blank line between block elements', function (string $html) {
    expect(toText($html))->toBe("One\n\nTwo");
})->with([
    'p' => '<p>One</p><p>Two</p>',
    'div' => '<div>One</div><div>Two</div>',
    'tr' => '<table><tr><td>One</td></tr><tr><td>Two</td></tr></table>',
    'li' => '<ul><li>One</li><li>Two</li></ul>',
    'headings' => '<h1>One</h1><H3 class="x">Two</H3>',
]);

it('strips the remaining tags', function () {
    expect(toText('<span>One <strong>two</strong> <img src="x.png" alt="x"> three</span>'))
        ->toBe('One two  three');
});

it('decodes entities as utf-8', function () {
    expect(toText('<p>Caf&eacute; &amp; t&#233; &quot;now&quot; &#39;ok&#39;&nbsp;&lt;3</p>'))
        ->toBe("Café & té \"now\" 'ok'\u{00A0}<3");
});

it('trims each line', function () {
    expect(toText("<p>   One   </p>\n\t<p>\tTwo  </p>"))->toBe("One\n\nTwo");
});

it('collapses three or more newlines into two', function () {
    expect(toText('<div><p>One</p></div><div><p>Two</p></div>'))->toBe("One\n\nTwo")
        ->and(toText("One\n\n\n\n\nTwo"))->toBe("One\n\nTwo");
});
