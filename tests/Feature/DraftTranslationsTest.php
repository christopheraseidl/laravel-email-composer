<?php

use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\DeliveriesRelationManager;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;

use function Pest\Livewire\livewire;

beforeEach(fn () => app()->setLocale('es'));

it('translates the draft action labels', function () {
    $draft = EmailDraft::factory()->create();

    $component = livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()]);

    foreach ([
        'preview' => 'Vista previa',
        'sendTest' => 'Enviar prueba',
        'reviewLink' => 'Enlace de revisión',
        'submit' => 'Enviar a revisión',
        'approve' => 'Aprobar',
        'returnToDraft' => 'Devolver a borrador',
        'send' => 'Enviar',
    ] as $name => $label) {
        $component->assertActionHasLabel($name, $label);
    }
});

it('translates a refused send\'s notification', function () {
    Bus::fake();

    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient);

    // Another dispatch claims the draft after this one has read its audience.
    $this->partialMock(DraftDispatcher::class, fn (MockInterface $mock) => $mock
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('audienceIds')
        ->andReturnUsing(function () use ($draft, $recipient) {
            EmailDraft::whereKey($draft->getKey())->update(['status' => EmailDraftStatus::Sending]);

            return collect([$recipient->getKey()]);
        }));

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('send')
        ->assertNotified(Notification::make()
            ->danger()
            ->title('Borrador no enviado')
            ->body("No se puede enviar el borrador n.º {$draft->id}: ya se está enviando o ya se envió."));

    Bus::assertNothingBatched();
});

it('translates the deliveries tab headers and status badges', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $recipients = collect(EmailDeliveryStatus::cases())->mapWithKeys(function (EmailDeliveryStatus $status) use ($draft) {
        $recipient = Recipient::factory()->create();
        $draft->recipients()->attach($recipient, ['delivery_status' => $status->value]);

        return [$status->value => $recipient];
    });

    expect(DeliveriesRelationManager::getTitle($draft, EditEmailDraft::class))->toBe('Entregas');

    $component = livewire(DeliveriesRelationManager::class, [
        'ownerRecord' => $draft,
        'pageClass' => EditEmailDraft::class,
    ]);

    foreach ([
        'email' => 'Dirección de correo',
        'pivot.locale' => 'Idioma',
        'pivot.delivery_status' => 'Estado',
        'pivot.attempts' => 'Intentos',
        'pivot.sent_at' => 'Fecha de envío',
        'pivot.error' => 'Error',
    ] as $name => $label) {
        $component->assertTableColumnExists($name, fn (Column $column) => $column->getLabel() === $label);
    }

    $component->assertTableFilterExists('delivery_status', fn (BaseFilter $filter) => $filter->getLabel() === 'Estado');

    foreach ([
        'pending' => 'Pendiente',
        'sent' => 'Enviado',
        'failed' => 'Fallido',
        'skipped' => 'Omitido',
    ] as $status => $label) {
        $component->assertTableColumnFormattedStateSet('pivot.delivery_status', $label, $recipients[$status]);
    }
});
