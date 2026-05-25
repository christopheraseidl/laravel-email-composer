<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'plain',
                'name' => 'Plain message',
                'body' => '<p>{{ body }}</p>',
                'placeholders' => ['body'],
            ],
            [
                'key' => 'greeting-and-body',
                'name' => 'Greeting + body',
                'body' => "<p>{{ greeting }}</p>\n<p>{{ body }}</p>\n<p>{{ signature }}</p>",
                'placeholders' => ['greeting', 'body', 'signature'],
            ],
            [
                'key' => 'newsletter-2-column',
                'name' => 'Newsletter (two columns)',
                'body' => <<<'HTML'
                <h1>{{ heading }}</h1>
                <table role="presentation" width="100%">
                    <tr>
                        <td valign="top" width="50%">{{ left_column }}</td>
                        <td valign="top" width="50%">{{ right_column }}</td>
                    </tr>
                </table>
                <p>{{ footer }}</p>
                HTML,
                'placeholders' => ['heading', 'left_column', 'right_column', 'footer'],
            ],
            [
                'key' => 'announcement-cta',
                'name' => 'Announcement with call to action',
                'body' => <<<'HTML'
                <h1>{{ headline }}</h1>
                <p>{{ body }}</p>
                <p><a href="{{ cta_url }}">{{ cta_label }}</a></p>
                HTML,
                'placeholders' => ['headline', 'body', 'cta_url', 'cta_label'],
            ],
        ];

        foreach ($templates as $t) {
            EmailTemplate::updateOrCreate(['key' => $t['key']], $t);
        }
    }
}
