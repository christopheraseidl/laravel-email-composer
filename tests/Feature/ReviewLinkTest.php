<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Review\ReviewLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

/** @param  array<string, mixed>  $attributes */
function reviewableDraft(array $attributes = []): EmailDraft
{
    $template = EmailTemplate::factory()->create([
        'body' => '<div>[[ greeting ]]</div>',
        'placeholders' => ['greeting'],
    ]);

    return EmailDraft::factory()->underReview()->create([
        'email_template_id' => $template->id,
        'subject' => ['en' => 'Spring update', 'es' => 'Novedades de primavera'],
        'placeholders' => ['en' => ['greeting' => 'Hello there'], 'es' => ['greeting' => 'Hola a todos']],
        ...$attributes,
    ]);
}

function feedbackInput(): array
{
    return [
        'reviewer_name' => 'Ada Reviewer',
        'reviewer_email' => 'ada@example.com',
        'comment' => 'Shorten the greeting.',
    ];
}

it('shows the rendered draft in the default locale', function () {
    $this->get(ReviewLink::for(reviewableDraft()))
        ->assertOk()
        ->assertSee('Spring update')
        ->assertSee('Hello there')
        ->assertDontSee('Hola a todos');
});

it('shows another configured locale when its switch link is followed', function () {
    $draft = reviewableDraft();
    $page = $this->get(ReviewLink::for($draft))->assertOk();

    $esLink = $page->viewData('localeLinks')['es'];

    $this->get($esLink)
        ->assertOk()
        ->assertSee('Novedades de primavera')
        ->assertSee('Hola a todos');
});

it('expires locale switch links with the original link', function () {
    config()->set('email-composer.review.link_ttl_days', 3);
    $draft = reviewableDraft();
    $link = ReviewLink::for($draft);

    $this->travel(2)->days();
    $esLink = $this->get($link)->assertOk()->viewData('localeLinks')['es'];

    $this->travel(1)->days();
    $this->travel(1)->minute();

    $this->get($esLink)->assertForbidden();
});

it('refuses a tampered link', function () {
    $draft = reviewableDraft();
    $other = reviewableDraft();
    $tampered = str_replace("/review/{$draft->id}?", "/review/{$other->id}?", ReviewLink::for($draft));

    $this->get($tampered)->assertForbidden();
    $this->get(ReviewLink::for($draft).'&locale=es')->assertForbidden();
});

it('refuses an expired link', function () {
    config()->set('email-composer.review.link_ttl_days', 3);
    $link = ReviewLink::for(reviewableDraft());

    $this->travel(3)->days();
    $this->travel(1)->minute();

    $this->get($link)->assertForbidden();
});

it('stores feedback from the reviewer and redirects back with a message', function () {
    $draft = reviewableDraft();
    $link = ReviewLink::for($draft);

    $this->post($link, feedbackInput())
        ->assertRedirect($link)
        ->assertSessionHas('status');

    $feedback = DraftFeedback::sole();
    expect($feedback)
        ->email_draft_id->toBe($draft->id)
        ->user_id->toBeNull()
        ->reviewer_name->toBe('Ada Reviewer')
        ->reviewer_email->toBe('ada@example.com')
        ->comment->toBe('Shorten the greeting.')
        ->resolved->toBeFalse();
});

it('rejects feedback without a name, a valid email, or a comment', function () {
    $this->post(ReviewLink::for(reviewableDraft()), [
        'reviewer_name' => '',
        'reviewer_email' => 'not-an-email',
        'comment' => '',
    ])->assertSessionHasErrors(['reviewer_name', 'reviewer_email', 'comment']);

    expect(DraftFeedback::count())->toBe(0);
});

it('refuses posting through a tampered link', function () {
    $this->post(ReviewLink::for(reviewableDraft()).'x', feedbackInput())->assertForbidden();

    expect(DraftFeedback::count())->toBe(0);
});

it('refuses a draft that is not under review', function (EmailDraftStatus $status) {
    $link = ReviewLink::for(reviewableDraft(['status' => $status]));

    $this->get($link)->assertNotFound();
    $this->post($link, feedbackInput())->assertNotFound();

    expect(DraftFeedback::count())->toBe(0);
})->with([EmailDraftStatus::Draft, EmailDraftStatus::Approved, EmailDraftStatus::Sent]);
