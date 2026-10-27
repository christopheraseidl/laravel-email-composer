<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\EmailTemplateResource;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\RecipientListResource;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\CreateRecipient;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;

use function Pest\Livewire\livewire;

beforeEach(fn () => app()->setLocale('es'));

it('translates the recipient table headers and filters', function () {
    $component = livewire(ListRecipients::class);

    foreach ([
        'name' => 'Nombre',
        'email' => 'Dirección de correo',
        'locale' => 'Idioma',
        'unsubscribed_at' => 'Fecha de baja',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Fecha de actualización',
    ] as $name => $label) {
        $component->assertTableColumnExists($name, fn (Column $column) => $column->getLabel() === $label);
    }

    $component
        ->assertTableFilterExists('unsubscribed_at', fn (BaseFilter $filter) => $filter->getLabel() === 'Suscripción')
        ->assertTableFilterExists('lists', fn (BaseFilter $filter) => $filter->getLabel() === 'Listas');
});

it('translates the recipient form labels', function () {
    $component = livewire(CreateRecipient::class);

    foreach ([
        'name' => 'Nombre',
        'email' => 'Dirección de correo',
        'locale' => 'Idioma',
        'lists' => 'Listas',
        'metadata' => 'Metadatos',
        'unsubscribed_at' => 'Fecha de baja',
    ] as $name => $label) {
        $component->assertFormFieldExists($name, fn (Field $field) => $field->getLabel() === $label);
    }
});

it('translates the draft table headers, filters and status badge', function () {
    $draft = EmailDraft::factory()->create(['status' => EmailDraftStatus::UnderReview]);

    $component = livewire(ListEmailDrafts::class);

    foreach ([
        'subject' => 'Asunto',
        'author_id' => 'Autor',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Fecha de actualización',
    ] as $name => $label) {
        $component->assertTableColumnExists($name, fn (Column $column) => $column->getLabel() === $label);
    }

    $component
        ->assertTableFilterExists('status', fn (BaseFilter $filter) => $filter->getLabel() === 'Estado')
        ->assertTableColumnExists('subject', fn (TextColumn $column) => str_contains(
            (string) $column->record($draft)->getDescriptionAbove(),
            'En revisión',
        ));
});

it('translates the draft form labels', function () {
    $component = livewire(CreateEmailDraft::class);

    foreach ([
        'template' => 'Plantilla',
        'subject' => 'Asunto',
        'audience' => 'Audiencia',
        'recipient_list_id' => 'Lista de destinatarios',
        'recipients' => 'Destinatarios',
    ] as $name => $label) {
        $component->assertFormFieldExists($name, fn (Field $field) => $field->getLabel() === $label);
    }

    $component
        ->assertFormFieldExists('template', fn (Select $field) => array_keys($field->getOptions()) === ['Base de datos', 'Archivos'])
        ->assertFormFieldExists('audience', fn (Radio $field) => $field->getOptions() === [
            'all' => 'Todos los destinatarios',
            'list' => 'Una lista de destinatarios',
            'selected' => 'Destinatarios seleccionados',
        ])
        ->assertSee('Marcadores')
        ->assertSee('Seleccione una plantilla para ver sus marcadores.');
});

it('translates the model labels', function (string $resource, string $singular, string $plural) {
    expect($resource::getModelLabel())->toBe($singular)
        ->and($resource::getPluralModelLabel())->toBe($plural);
})->with([
    [RecipientResource::class, 'destinatario', 'destinatarios'],
    [RecipientListResource::class, 'lista de destinatarios', 'listas de destinatarios'],
    [EmailTemplateResource::class, 'plantilla de correo', 'plantillas de correo'],
    [EmailDraftResource::class, 'borrador de correo', 'borradores de correo'],
]);

it('translates the theme page labels', function () {
    $component = livewire(ManageEmailTheme::class);

    foreach ([
        'logo_url' => 'URL del logotipo',
        'logo_alt' => 'Texto alternativo del logotipo',
        'css' => 'CSS',
    ] as $name => $label) {
        $component->assertFormFieldExists($name, 'form', fn (Field $field) => $field->getLabel() === $label);
    }

    $component->assertSee('Gestionar tema de correo')
        ->assertSee('Guardar');

    expect(ManageEmailTheme::getNavigationLabel())->toBe('Gestionar tema de correo');
});

it('translates the default navigation group', function (string $class) {
    expect($class::getNavigationGroup())->toBe('Correo');
})->with([
    RecipientResource::class,
    RecipientListResource::class,
    EmailTemplateResource::class,
    ManageEmailTheme::class,
]);
