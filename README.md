# Laravel Email Composer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/christopheraseidl/laravel-email-composer.svg?style=flat-square)](https://packagist.org/packages/christopheraseidl/laravel-email-composer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/christopheraseidl/laravel-email-composer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/christopheraseidl/laravel-email-composer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/christopheraseidl/laravel-email-composer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/christopheraseidl/laravel-email-composer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/christopheraseidl/laravel-email-composer.svg?style=flat-square)](https://packagist.org/packages/christopheraseidl/laravel-email-composer)

Compose translatable email drafts from templates, send them through review
and approval, and deliver them to a recipient list with one queued job per
recipient. Ships a Filament panel plugin for recipients, templates, drafts,
and a global email theme.

Requires PHP 8.4 and Laravel 12.4+ or 13. The Filament plugin requires
Filament 5.10+.

## Installation

```bash
composer require christopheraseidl/laravel-email-composer
php artisan email-composer:install
```

The install command:

- creates the `job_batches` migration if the table is missing, and the
  `notifications` migration when Filament is installed,
- publishes the config and migrations,
- offers to run the migrations,
- prints the remaining setup steps.

To publish by hand instead:

```bash
php artisan make:queue-batches-table   # if job_batches does not exist
php artisan vendor:publish --tag=email-composer-config
php artisan vendor:publish --tag=email-composer-migrations
php artisan migrate
```

Optional publish tags:

- `email-composer-views`: the layout, mail footer, review and unsubscribe pages, and `templates/default.html`, which lands in the default `templates.path` ready to edit
- `email-composer-assets`: the default theme CSS
- `email-composer-translations`: `en` and `es` strings

The published CSS is a reference copy, since the theme lives in the database
(see [Global theme](#global-theme)).

## Configuration

`config/email-composer.php`:

- `locales`: locales offered for drafts and recipients (`['en', 'es']`)
- `default_locale`: the fallback locale (`'en'`)
- `storage.disk`: disk for approved drafts' HTML (`EMAIL_COMPOSER_DISK`, default `local`)
- `storage.path`: directory on that disk (`'email-composer'`)
- `storage.public_disk`: disk for uploaded logos, which must serve files by URL (`EMAIL_COMPOSER_PUBLIC_DISK`, default `public`)
- `templates.path`: directory for file templates
- `queue.connection`: queue connection for sending (`EMAIL_COMPOSER_QUEUE_CONNECTION`)
- `queue.queue`: queue name for sending (`EMAIL_COMPOSER_QUEUE_NAME`)
- `bulk.chunk_size`: send jobs queued at a time (`100`)
- `import.max_file_size`: largest CSV the panel accepts, in KB (`10240`)
- `import.rows_per_part`: rows per queued import job (`2000`)
- `unsubscribe.link_ttl_days`: days before unsubscribe links expire (`null`, never)
- `review.link_ttl_days`: days before review links expire (`7`)
- `filament.navigation_group`: panel navigation group (`'Email'`, `null` for none)

Drafts and feedback belong to the model named in
`auth.providers.users.model`, which must be an Eloquent model.

## Authorization

Every policy denies until an ability resolver is registered once, in a
service provider's `boot()` method:

```php
use CSeidl\EmailComposer\EmailComposer;

EmailComposer::resolveAbilityUsing(
    fn ($user, string $ability, $model = null) => $user->can("email-composer.{$ability}"),
);
```

The resolver receives the authenticated user, the ability name, and the
record being checked, or `null`. A guest is always denied.

### Abilities

- **Recipients:** `view-recipients`, `view-recipient`, `create-recipients`, `import-recipients`, `update-recipient`, `delete-recipient`, `restore-recipient`, `force-delete-recipient`
- **Recipient lists:** `view-recipient-lists`, `view-recipient-list`, `create-recipient-lists`, `update-recipient-list`, `delete-recipient-list`, `restore-recipient-list`, `force-delete-recipient-list`
- **Templates:** `view-templates`, `view-template`, `create-templates`, `update-template`, `delete-template`, `restore-template`, `force-delete-template`
- **Drafts:** `view-any-draft`, `view-draft`, `create-draft`, `update-draft`, `submit-draft`, `approve-draft`, `share-draft` (get the review link), `send-draft`, `delete-draft`, `restore-draft`, `force-delete-draft`
- **Feedback:** `view-feedback`, `comment-on-draft`, `update-feedback`
- **Theme:** `view-theme`, `update-theme`

Draft status rules apply on top of the resolver. For example, only a `draft`
can be edited, and a draft that is `sending` or `sent` cannot be deleted.

A resolver that scopes `view-draft` to the author must allow the model-less
call, which decides whether the draft list is visible:

```php
'view-draft' => $model === null || $model->author_id === $user->id,
```

## Filament plugin

Install the plugin's dependencies, then register it on a panel:

```bash
composer require lara-zeus/spatie-translatable bezhansalleh/filament-language-switch
```

```php
use CSeidl\EmailComposer\Filament\EmailComposerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(EmailComposerPlugin::make());
}
```

The plugin adds the Recipients, Recipient Lists, Email Templates, and Email
Drafts resources and the Manage Email Theme page. It also:

- registers `SpatieTranslatablePlugin` with `locales`, unless the panel already registers it,
- sets the language switcher's locales to `locales`, unless they were configured elsewhere.

## Recipient lists

A recipient can belong to any number of named lists, managed in the panel.

```php
use CSeidl\EmailComposer\Models\RecipientList;

$list = RecipientList::create(['name' => 'Staff']);
$list->recipients()->attach($recipientIds);

$recipient->lists;   // lists the recipient belongs to
```

Deleting a list or a recipient soft-deletes it and keeps its memberships.

## Importing recipients

A CSV needs a header row with an `email` column. `name` and `locale` are
optional, and other columns go into `metadata`. Files must be UTF-8 or
Windows-1252.

```bash
php artisan email-composer:import-recipients recipients.csv --list=Staff
```

The panel's Import action queues the import instead. It needs:

- `->databaseNotifications()` on the panel, to show the result,
- `Notifiable` on the user model,
- a cache store shared by all queue workers.

Without Filament, store the upload on `storage.disk` and dispatch the job,
which deletes the upload when done:

```php
use CSeidl\EmailComposer\Jobs\ImportRecipientsJob;

ImportRecipientsJob::dispatch($user, $path, $listId);
```

Bind `CSeidl\EmailComposer\Contracts\ImportNotifierInterface` to report the
result. Without Filament or a binding of your own, nothing is reported.

## Templates

A template is an HTML body with `[[ name ]]` placeholders and optional CSS.
Templates come from two sources:

- **Database templates**, created in the Email Templates resource.
- **File templates**, `.html` files in `templates.path`.

A file template wins when keys collide.

### File template format

YAML front matter, then the HTML body. `key` and `name` are required.
`placeholders` lists the tokens the draft form asks for. An inline `<style>`
block becomes the template's CSS.

```html
---
key: newsletter
name: Monthly newsletter
placeholders: [heading, body, signoff]
---

<style>
  .content h1 { color: #0b5cad; }
</style>

<div class="email-container">
  <div class="content">
    <h1>[[ heading ]]</h1>
    [[ body ]]
    <p>[[ signoff ]]</p>
  </div>
</div>
```

Token names use letters, digits, and underscores.

## Global theme

The Manage Email Theme page edits the stylesheet and logo used by every
email. Theme and template CSS are trusted and skip HTMLPurifier.

```php
use CSeidl\EmailComposer\Models\EmailTheme;

EmailTheme::current()->css;
EmailTheme::current()->update(['logo_url' => $url]);
```

## Drafts

A draft moves through these statuses, each with a matching panel action:

1. `draft`: editable
2. `under_review`: read-only and open to review links (`$draft->submit()`)
3. `approved`: one HTML file stored per locale (`$draft->approve($user)`)
4. `sending`: recipient jobs queued (`EmailComposer::send($draft)`)
5. `sent`: every recipient job has run

`$draft->returnToDraft()` moves an `under_review` draft back to `draft`.

`subject` and `placeholders` are translatable. Edit each locale with the
locale switcher on the draft page. Missing values fall back to
`default_locale`.

### Review links

External reviewers can read an `under_review` draft and leave feedback
through a signed link, without an account. The panel's Review link action
produces the URL. In code:

```php
use CSeidl\EmailComposer\Review\ReviewLink;

$url = ReviewLink::for($draft);          // expires after review.link_ttl_days
$url = ReviewLink::for($draft, 'es');    // opens in Spanish
```

Feedback appears in the draft's Feedback tab.

### Sending

A draft's `audience` is one of:

- `all`: every recipient that is not deleted,
- `list`: the members of `recipient_list_id`,
- `selected`: the recipients chosen on the draft.

Sending queues one job per recipient. Delivery status is stored per
recipient on the `email_composer_draft_recipient` table.

## View in browser and unsubscribe

Every sent email ends with a footer that has two signed links:

- **View in browser** shows the published HTML for the recipient's locale.
- **Unsubscribe** opens a confirmation page. Confirming sets
  `unsubscribed_at`, and later sends skip the recipient.

Each email also carries `List-Unsubscribe` and `List-Unsubscribe-Post`
headers, so mail clients can unsubscribe in one click. The unsubscribe POST
route is exempt from CSRF verification so those requests succeed.

## Troubleshooting

**Every Filament page returns 403, or the resources do not appear in the
navigation.** No ability resolver is registered (see
[Authorization](#authorization)).

**`RecipientFactory::new()` builds the wrong model on Laravel 12.** The
package's factories name their model with the `#[UseModel]` attribute, which
exists only from Laravel 13. On Laravel 12, reach the factories through the
model instead:

```php
Recipient::factory()->create();   // works on 12 and 13
RecipientFactory::new()->create(); // Laravel 13 only
```

On Laravel 12, PHPStan also reports `UseModel` as an unknown class.

**"The email has already been taken" for a recipient that is not listed.** A
deleted recipient holds the address, so restore it from the Recipients
table's trashed filter.

**Signed links return 403.** The link expired or was altered, or a proxy in
front of the app changes the scheme or host it sees. Trust the proxy
(`$middleware->trustProxies(at: '*')`) and set `APP_URL` to the public URL.

## Testing

```bash
composer test
composer analyse
```

## Workbench app

A Filament panel with seeded data, for manual testing.

Once per clone:

- Copy `workbench/.env.example` to `workbench/.env`.
- Set `APP_KEY` in it to the output of `vendor/bin/testbench key:generate --show`.
- To send mail, fill in the `MAIL_*` keys.

```bash
composer run serve                # builds, then serves http://127.0.0.1:8000/admin
vendor/bin/testbench queue:work   # second terminal, sends mail and runs imports
```

- Log in as `test@example.com` / `password`.
- `composer run serve` wipes the database and seeds it again. `vendor/bin/testbench serve` keeps the data.

After a change:

- **Package code, views, routes, translations or config:** reload the page, and restart `queue:work`.
- **Migrations, seeders or factories:** run `composer run build`, which wipes the database and seeds it again.
- **`composer install`, `update` or `dump-autoload`:** run `composer run build`. These delete the Workbench database and uploaded files.
- **`workbench/.env`:** run `vendor/bin/testbench package:sync-skeleton`, then restart the server and `queue:work`.
- **`testbench.yaml.dist` or a local `testbench.yaml`:** restart the server and `queue:work`.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Chris Seidl](https://github.com/christopheraseidl)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
