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

- creates the `job_batches` migration if the table is missing (sending uses job batches),
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

| Tag | Publishes to | Contents |
| --- | --- | --- |
| `email-composer-views` | `resources/views/vendor/email-composer` | Layout, mail footer, review and unsubscribe pages, and `templates/default.html` |
| `email-composer-assets` | `public/vendor/email-composer` | `css/email-default.css`, the default theme CSS |
| `email-composer-translations` | `lang/vendor/email-composer` | `en` and `es` strings for the footer and unsubscribe pages |

Publishing the views also places the bundled default template in
`resources/views/vendor/email-composer/templates`, which is the default
`templates.path`, so it can be edited in place.

The published CSS file is a copy for reference. The theme itself is stored
in the `email_composer_theme` table, seeded from the package's
`email-default.css` the first time it is read, and edited in Filament (see
[Global theme](#global-theme)).

Sending is queued, so a queue worker must be running.

## Configuration

`config/email-composer.php`:

| Key | Default | Purpose |
| --- | --- | --- |
| `locales` | `['en', 'es']` | Locales offered for drafts, recipients, and the panel language switcher |
| `default_locale` | `'en'` | Fallback locale for subjects, placeholders, published files, and recipients with no locale |
| `storage.disk` | `env('EMAIL_COMPOSER_DISK', 'local')` | Disk for published draft HTML. `null` uses the app's default disk |
| `storage.path` | `'email-composer'` | Directory on that disk. Files go to `{path}/drafts/{hash}/{locale}.html` |
| `templates.path` | `resource_path('views/vendor/email-composer/templates')` | Directory scanned for file templates |
| `queue.connection` | `env('EMAIL_COMPOSER_QUEUE_CONNECTION')` | Queue connection for send batches. `null` uses the app's default |
| `queue.queue` | `env('EMAIL_COMPOSER_QUEUE_NAME')` | Queue name. `null` uses the connection's default |
| `unsubscribe.link_ttl_days` | `null` | Days before unsubscribe links expire. `null` never expires |
| `review.link_ttl_days` | `7` | Days before external review links expire |

The config file also contains `logo.path`, `logo.alt`, `queue.enabled`,
`bulk.chunk_size`, and `filament.navigation_group`. Version 1.0.0 does not
read them.

Drafts and feedback belong to the model named in
`auth.providers.users.model`, which must be an Eloquent model.

## Authorization

Every policy denies until an ability resolver is registered. Register one in
a service provider's `boot()` method:

```php
use CSeidl\EmailComposer\EmailComposer;

EmailComposer::resolveAbilityUsing(
    fn ($user, string $ability, $model = null) => $user->can("email-composer.{$ability}"),
);
```

The resolver receives the authenticated user, the ability name, and the model
instance when the check concerns one record (`null` otherwise). A guest is
always denied.

### Abilities

| Ability | Model passed | Grants |
| --- | --- | --- |
| `view-recipients` | no | List recipients |
| `view-recipient` | `Recipient` | View a recipient |
| `create-recipients` | no | Create recipients |
| `update-recipient` | `Recipient` | Edit a recipient |
| `delete-recipient` | `Recipient` | Delete a recipient |
| `restore-recipient` | `Recipient` | Restore a recipient |
| `force-delete-recipient` | `Recipient` | Permanently delete a recipient |
| `view-recipient-lists` | no | List recipient lists |
| `view-recipient-list` | `RecipientList` | View a recipient list |
| `create-recipient-lists` | no | Create recipient lists |
| `update-recipient-list` | `RecipientList` | Edit a recipient list |
| `delete-recipient-list` | `RecipientList` | Delete a recipient list |
| `restore-recipient-list` | `RecipientList` | Restore a deleted recipient list |
| `force-delete-recipient-list` | `RecipientList` | Permanently delete a recipient list |
| `view-templates` | no | List templates |
| `view-template` | `EmailTemplate` | View a template |
| `create-templates` | no | Create templates |
| `update-template` | `EmailTemplate` | Edit a template |
| `delete-template` | `EmailTemplate` | Delete a template |
| `restore-template` | `EmailTemplate` | Restore a template |
| `force-delete-template` | `EmailTemplate` | Permanently delete a template |
| `view-any-draft` | no | List and view every draft |
| `view-draft` | `EmailDraft` on view, none on list | View a draft, for example only the user's own |
| `create-draft` | no | Create drafts |
| `update-draft` | `EmailDraft` | Edit a draft. Only drafts in `draft` status are editable |
| `submit-draft` | `EmailDraft` | Submit a `draft` for review |
| `approve-draft` | `EmailDraft` | Approve a draft that is `under_review` |
| `send-draft` | `EmailDraft` | Send an `approved` draft |
| `delete-draft` | `EmailDraft` | Delete a draft that is not `sent` |
| `restore-draft` | `EmailDraft` | Restore a deleted draft |
| `force-delete-draft` | `EmailDraft` | Permanently delete a draft that is not `sent` |
| `view-feedback` | `DraftFeedback` on view, none on list | View review feedback |
| `comment-on-draft` | no | Create feedback |
| `update-feedback` | `DraftFeedback` | Resolve or reopen feedback |
| `view-theme` | `EmailTheme` | Open the theme page |
| `update-theme` | `EmailTheme` | Save the theme |

`view-draft` is also checked without a model to decide whether the draft list
is visible at all, so a resolver that scopes it to the author should allow the
model-less call:

```php
'view-draft' => $model === null || $model->author_id === $user->id,
```

## Filament plugin

Install the two packages the plugin depends on:

```bash
composer require lara-zeus/spatie-translatable bezhansalleh/filament-language-switch
```

Register the plugin on a panel:

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

- registers `SpatieTranslatablePlugin` with `locales` as its default locales, unless the panel already registers it,
- sets the language switcher's locales to `locales`, unless they were configured elsewhere.

Registration throws a `LogicException` if either dependency is missing.

## Recipient lists

A recipient list is a named group of recipients. A recipient can belong to
any number of lists. Members are chosen in the Recipient Lists resource or
from a recipient's form, and the Recipients table filters by list.

```php
use CSeidl\EmailComposer\Models\RecipientList;

$list = RecipientList::create(['name' => 'Staff']);
$list->recipients()->attach($recipientIds);

$recipient->lists;   // lists the recipient belongs to
```

Deleting a list soft-deletes it and keeps its memberships, so restoring it
brings them back. Force deleting a list, or deleting a recipient, removes
the memberships.

## Templates

A template is a body with `[[ name ]]` placeholder tokens, a list of declared
placeholder names, and optional CSS. Templates come from two sources:

- **Database templates**, created in the Email Templates resource.
- **File templates**, `.html` files in `templates.path` and in the package's
  bundled templates directory.

When keys collide, a file template wins over a database template, and a
template in `templates.path` wins over a bundled one. Two files with the same
key in one directory throw a `RuntimeException`.

### File template format

YAML front matter, then the HTML body. `key` and `name` are required.
`placeholders` lists the tokens the draft composer asks for. An optional
inline `<style>` block becomes the template's CSS and is removed from the
body.

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

Token names may use letters, digits, and underscores, with optional spaces
inside the brackets. A token with no value renders as an empty string.
Values are inserted as HTML, then the whole document is sanitized with
HTMLPurifier.

The draft form in Filament lists database templates only. To use a file
template, set the draft's `template_key` in code:

```php
EmailDraft::create([
    'template_key' => 'newsletter',
    'subject' => ['en' => 'October news', 'es' => 'Noticias de octubre'],
    'placeholders' => ['en' => ['heading' => 'Hello'], 'es' => ['heading' => 'Hola']],
]);
```

### Rendering

A draft renders by interpolating its placeholder values into the body,
wrapping the result in the `email-composer::layout` view, inlining CSS
(global theme first, then template CSS), and sanitizing the result.

## Global theme

The Manage Email Theme page edits one CSS stylesheet applied to every email
before the template's own CSS. Users with `view-theme` see it read-only, and
`update-theme` is required to save. The first read seeds it from the
package's `email-default.css`.

```php
use CSeidl\EmailComposer\Models\EmailTheme;

EmailTheme::current()->css;
```

## Drafts

A draft moves through four statuses:

| Status | Entered by | Effect |
| --- | --- | --- |
| `draft` | creation | Editable |
| `under_review` | Submit action, `$draft->submit()` | Read-only, open to review links |
| `approved` | Approve action, `$draft->approve($user)` | Renders and stores one HTML file per locale |
| `sent` | Send action, `EmailComposer::send($draft)` | Set when every recipient job has run |

`$draft->returnToDraft()` moves an `under_review` draft back to `draft`.

### Translations and the language switcher

`subject` and `placeholders` are translatable. On the draft pages, the
locale switcher in the page header selects which locale's subject and
placeholder values the form shows and saves. The form opens in the panel's
active language, chosen with the language switcher, when that language is
in `locales`.

A value missing in one locale falls back to `default_locale`, then to any
locale that has it. On approval, a file is published for each locale that
has a subject or placeholder values, plus `default_locale`.

### Review links

External reviewers can read an `under_review` draft and leave feedback
without an account through a signed link:

```php
use CSeidl\EmailComposer\Review\ReviewLink;

$url = ReviewLink::for($draft);          // expires after review.link_ttl_days
$url = ReviewLink::for($draft, 'es');    // opens in Spanish
```

The page offers a link per locale and a feedback form. Feedback appears in
the draft's Feedback tab, where it can be resolved. The link returns 404
once the draft leaves `under_review`.

### Sending

`EmailComposer::send($draft)` queues one job per recipient in a job batch
and returns the `Batch`. It throws a `DomainException` if the draft is not
`approved` or has no recipients.

Each job:

- skips recipients who have unsubscribed and marks them `skipped`,
- sends in the recipient's locale if the draft was published in it, otherwise in `default_locale`,
- retries 3 times, 30 seconds apart, then marks the recipient `failed` with the error.

Delivery status, locale, attempts, and errors are stored on the
`email_composer_draft_recipient` pivot. Sending goes through
`EmailSenderInterface`, bound to `MailableSender`. Rebind it to send another
way.

## View in browser and unsubscribe

Every sent email ends with a footer that has two signed links.

**View in browser** serves the HTML published for that draft and locale on
approval, falling back to `default_locale`. The link does not expire.
Published files are stored under a folder named by an HMAC of the draft ID
and `APP_KEY`, so they cannot be found by counting draft IDs on a public
disk. Changing `APP_KEY` moves future publishes to new folders.

**Unsubscribe** opens a confirmation page in the recipient's locale. On
confirmation it sets `unsubscribed_at`, keeping the first time if it is
already set. Later sends skip the recipient. The link expires after
`unsubscribe.link_ttl_days`, or never when that is `null`.

The routes are `email-composer.view-in-browser`,
`email-composer.unsubscribe.show`, and `email-composer.unsubscribe.store`,
all under the `web` and `signed` middleware.

## Troubleshooting

**Every Filament page returns 403, or the resources do not appear in the
navigation.** No ability resolver is registered, so every policy denies.
Register one with `EmailComposer::resolveAbilityUsing()` (see
[Authorization](#authorization)).

**`@media` rules have no effect.** HTMLPurifier removes the document's
`<style>` block, so only CSS that can be inlined onto elements survives.
Media queries, pseudo-classes such as `:hover`, and the mobile rules at the
end of the default theme are dropped. Write layouts that work without them.

**`RecipientFactory::new()` builds the wrong model on Laravel 12.** The
package's factories name their model with the `#[UseModel]` attribute, which
exists only from Laravel 13. On Laravel 12, reach the factories through the
model instead:

```php
Recipient::factory()->create();   // works on 12 and 13
RecipientFactory::new()->create(); // Laravel 13 only
```

On Laravel 12, PHPStan also reports `UseModel` as an unknown class.

**Signed links return 403.** The URL was changed after signing, or it
expired. Behind a proxy, configure trusted proxies so the scheme and host
match the signed URL.

## Testing

```bash
composer test
composer analyse
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Chris Seidl](https://github.com/christopheraseidl)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
