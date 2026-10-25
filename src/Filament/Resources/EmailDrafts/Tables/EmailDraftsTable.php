<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\PreviewDraftAction;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\ReviewLinkAction;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class EmailDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject'),
                TextColumn::make('author_id')
                    ->label('Author')
                    ->formatStateUsing(function ($record): string {
                        if (! $record->author) {
                            return 'N/A';
                        }

                        $name = $record->author->name ?? '';
                        $email = $record->author->email ? " <{$record->author->email}>" : '';
                        $label = $name.$email;

                        return $label !== '' ? $label : "Author #{$record->author_id}";
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($record) => $record->status->label())
                    ->badge()
                    ->color(fn ($record) => $record->status->color()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(EmailDraftStatus::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                PreviewDraftAction::make(),
                Action::make('submit')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('warning')
                    ->action(fn ($record) => $record->submit())
                    ->authorize(fn ($record) => Gate::allows('submit', $record)),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $record->approve(auth()->user());
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Draft not approved')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->authorize(fn ($record) => Gate::allows('approve', $record)),
                ReviewLinkAction::make(),
                Action::make('send')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(fn ($record) => static::lacksAudience($record))
                    ->tooltip(fn ($record) => static::lacksAudience($record) ? 'Add recipients before sending.' : null)
                    ->action(function ($record) {
                        try {
                            app(DraftDispatcher::class)->dispatch($record);
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Draft not sent')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->authorize(fn ($record) => Gate::allows('send', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('author'));
    }

    // Only approved drafts can be sent, so other rows skip the audience query.
    protected static function lacksAudience(EmailDraft $draft): bool
    {
        return $draft->status === EmailDraftStatus::Approved
            && ! $draft->hasAudience();
    }
}
