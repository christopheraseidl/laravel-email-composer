<?php

use CSeidl\EmailComposer\Actions\Drafts\SendTestEmail;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(fn () => Mail::fake());

afterEach(fn () => EmailComposer::flushResolvers());

it('sends a test email to each address without storing recipients', function () {
    $draft = EmailDraft::factory()->create(['subject' => ['en' => 'Hello there', 'es' => 'Hola amigos']]);

    app(SendTestEmail::class)($draft, ['ada@example.com', 'grace@example.com'], 'es');

    Mail::assertSentCount(2);

    foreach (['ada@example.com', 'grace@example.com'] as $email) {
        Mail::assertSent(ComposedEmail::class, fn (ComposedEmail $mail) => $mail->hasTo($email)
            && $mail->draft->is($draft)
            && $mail->recipient->isTest()
            && $mail->recipient->locale === 'es'
            && $mail->locale === 'es');
    }

    expect(Recipient::withTrashed()->count())->toBe(0)
        ->and($draft->recipients()->count())->toBe(0);
});

it('rejects addresses that fail validation and sends nothing', function (array $emails, string $message) {
    $draft = EmailDraft::factory()->create();

    expect(fn () => app(SendTestEmail::class)($draft, $emails, 'en'))
        ->toThrow(DomainException::class, $message);

    Mail::assertNothingSent();
})->with([
    'none' => [[], 'The addresses field is required.'],
    'more than ten' => [
        array_map(fn (int $i) => "person{$i}@example.com", range(1, 11)),
        'The addresses field must have between 1 and 10 items.',
    ],
    'an invalid address' => [['ada@example.com', 'not-an-email'], 'not-an-email is not a valid email address.'],
]);

it('shows the send test action only on drafts the user may test', function () {
    $draft = EmailDraft::factory()->approved()->create();
    $sent = EmailDraft::factory()->sent()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionVisible('sendTest');

    livewire(EditEmailDraft::class, ['record' => $sent->getRouteKey()])
        ->assertActionHidden('sendTest');

    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability !== 'test-draft');

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionHidden('sendTest');
});

it('defaults to the current user and the edit page active locale', function () {
    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->set('activeLocale', 'es')
        ->mountAction('sendTest')
        ->assertSet('mountedActions.0.data.emails', [auth()->user()->email])
        ->assertSet('mountedActions.0.data.locale', 'es');
});

it('sends from the panel and lists the addresses', function () {
    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('sendTest', data: [
            'emails' => ['ada@example.com', 'grace@example.com'],
            'locale' => 'es',
        ])
        ->assertNotified(Notification::make()
            ->success()
            ->title('Test email sent')
            ->body('Sent to ada@example.com, grace@example.com.'));

    Mail::assertSent(ComposedEmail::class, fn (ComposedEmail $mail) => $mail->hasTo('grace@example.com') && $mail->locale === 'es');
    Mail::assertSentCount(2);
});

it('shows rejected addresses as an error notification', function () {
    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('sendTest', data: ['emails' => ['not-an-email']])
        ->assertNotified(Notification::make()
            ->danger()
            ->title('Test email not sent')
            ->body('not-an-email is not a valid email address.'));

    Mail::assertNothingSent();
});
