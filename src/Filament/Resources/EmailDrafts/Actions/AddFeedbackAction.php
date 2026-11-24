<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\FeedbackRelationManager;
use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AddFeedbackAction
{
    public static function make(): Action
    {
        return Action::make('addFeedback')
            ->label(__('email-composer::drafts.actions.add_feedback'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            // Matches the review page, which takes feedback only under review.
            ->authorize(fn (EmailDraft $record) => $record->status === EmailDraftStatus::UnderReview
                && Gate::allows('create', DraftFeedback::class))
            ->modalHeading(__('email-composer::drafts.actions.add_feedback'))
            ->modalDescription(__('email-composer::drafts.helpers.add_feedback'))
            ->modalWidth('lg')
            ->schema([
                Textarea::make('comment')
                    ->label(__('email-composer::drafts.feedback.columns.comment'))
                    ->required()
                    ->maxLength(10000)
                    ->rows(6)
                    ->autosize(),
            ])
            ->modalSubmitActionLabel(__('email-composer::drafts.actions.send_feedback'))
            ->action(function (EmailDraft $record, array $data, Component $livewire) {
                $record->feedback()->create([
                    'user_id' => Filament::auth()->id(),
                    'comment' => $data['comment'],
                ]);

                $livewire->dispatch(FeedbackRelationManager::REFRESH_EVENT);

                Notification::make()
                    ->success()
                    ->title(__('email-composer::drafts.notifications.feedback_sent'))
                    ->send();
            });
    }
}
