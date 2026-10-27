<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EmailDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label(__('email-composer::drafts.columns.subject'))
                    ->wrap()
                    ->description(fn (EmailDraft $record): Htmlable => new HtmlString(Blade::render(
                        '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
                        ['color' => $record->status->color(), 'label' => $record->status->label()],
                    )), position: 'above'),
                TextColumn::make('author_id')
                    ->label(__('email-composer::drafts.columns.author'))
                    ->formatStateUsing(function ($record): string {
                        if (! $record->author) {
                            return __('email-composer::drafts.columns.no_author');
                        }

                        $name = $record->author->name ?? '';
                        $email = $record->author->email ? " <{$record->author->email}>" : '';
                        $label = $name.$email;

                        return $label !== '' ? $label : __('email-composer::drafts.columns.author_fallback', ['id' => $record->author_id]);
                    })
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label(__('email-composer::drafts.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
                TextColumn::make('updated_at')
                    ->label(__('email-composer::drafts.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label(__('email-composer::drafts.filters.status'))
                    ->options(collect(EmailDraftStatus::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),
            ])
            ->recordUrl(fn (EmailDraft $record): ?string => EmailDraftResource::canView($record) ? EmailDraftResource::getUrl('edit', ['record' => $record]) : null)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('author'));
    }
}
