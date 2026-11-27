<?php

use CSeidl\EmailComposer\Actions\Drafts\ApproveDraft;
use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Actions\Drafts\ResumeSend;
use CSeidl\EmailComposer\Actions\Drafts\RetryDeliveries;
use CSeidl\EmailComposer\Actions\Drafts\ReturnDraftToDraft;
use CSeidl\EmailComposer\Actions\Drafts\SendDraft;
use CSeidl\EmailComposer\Actions\Drafts\SendTestEmail;
use CSeidl\EmailComposer\Actions\Drafts\SubmitDraft;
use CSeidl\EmailComposer\Actions\Recipients\UnsubscribeRecipient;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\EmailComposerServiceProvider;
use CSeidl\EmailComposer\Filament\EmailComposerPlugin;
use CSeidl\EmailComposer\Jobs\ImportRecipientsJob;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Models\EmailTheme;
use CSeidl\EmailComposer\Models\SendFailure;
use CSeidl\EmailComposer\Review\ReviewLink;
use CSeidl\EmailComposer\Templates\FileTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/** @return list<class-string> */
function classesIn(string $directory, string $namespace): array
{
    return array_map(
        fn (SplFileInfo $file) => $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()),
        (new Filesystem)->allFiles(__DIR__."/../{$directory}"),
    );
}

function hasTag(ReflectionClass|ReflectionMethod $reflection, string $tag): bool
{
    return str_contains((string) $reflection->getDocComment(), $tag);
}

/** @return array<string, array{ReflectionMethod}> */
function policyMethods(): array
{
    return collect(classesIn('src/Policies', 'CSeidl\EmailComposer\Policies'))
        ->flatMap(fn (string $policy) => (new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => str_starts_with($method->class, 'CSeidl\EmailComposer\Policies\\'))
        ->mapWithKeys(fn (ReflectionMethod $method) => [class_basename($method->class)."::{$method->name}" => [$method]])
        ->all();
}

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('statuses are enums')
    ->expect('CSeidl\EmailComposer\Enums')
    ->toBeEnums();

arch('jobs are queued')
    ->expect('CSeidl\EmailComposer\Jobs')
    ->toImplement(ShouldQueue::class);

arch('models declare their factory')
    ->expect('CSeidl\EmailComposer\Models')
    ->toHaveAttribute(UseFactory::class);

arch('factories declare their model')
    ->expect('CSeidl\EmailComposer\Database\Factories')
    ->classes()
    ->toHaveAttribute(UseModel::class);

// SendFailure is a log shown only through its draft, and goes with its draft or recipient.
it('has a policy for every model except SendFailure', function (string $model) {
    $policy = str_replace('\\Models\\', '\\Policies\\', $model).'Policy';

    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with(array_values(array_diff(
    classesIn('src/Models', 'CSeidl\EmailComposer\Models'),
    [SendFailure::class],
)));

it('soft deletes every model except EmailTheme and SendFailure', function (string $model) {
    expect(class_uses_recursive($model))->toHaveKey(SoftDeletes::class);
})->with(array_values(array_diff(
    classesIn('src/Models', 'CSeidl\EmailComposer\Models'),
    [EmailTheme::class, SendFailure::class],
)));

it('delegates every policy method to EmailComposer::userCan', function (ReflectionMethod $method) {
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->toContain('EmailComposer::userCan(');
})->with(policyMethods());

arch('actions are invokable')
    ->expect('CSeidl\EmailComposer\Actions')
    ->classes()
    ->toBeInvokable();

arch('data classes are readonly')
    ->expect('CSeidl\EmailComposer\Data')
    ->toBeReadonly();

// arch()->not->toUse('Filament') misses Filament's other packages, such as Filament\Notifications.
it('uses Filament only in src/Filament', function () {
    $files = collect(File::allFiles(__DIR__.'/../src'))
        ->reject(fn (SplFileInfo $file) => str_starts_with($file->getRelativePath(), 'Filament'))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getContents(), 'use Filament\\'))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname());

    expect($files->values()->all())->toBe([]);
});

it('marks the public API final and every other class internal', function () {
    $publicClasses = [
        EmailComposer::class,
        EmailComposerServiceProvider::class,
        EmailComposerPlugin::class,
        FileTemplate::class,
        ReviewLink::class,
        ImportRecipientsJob::class,
        ComposedEmail::class,
        UnsubscribeRecipient::class,
        SubmitDraft::class,
        ApproveDraft::class,
        ReturnDraftToDraft::class,
        SendDraft::class,
        CancelSend::class,
        ResumeSend::class,
        RetryDeliveries::class,
        SendTestEmail::class,
    ];

    $publicNamespaces = [
        'CSeidl\EmailComposer\Contracts\\',
        'CSeidl\EmailComposer\Data\\',
        'CSeidl\EmailComposer\Enums\\',
        'CSeidl\EmailComposer\Exceptions\\',
        'CSeidl\EmailComposer\Models\\',
    ];

    foreach (classesIn('src', 'CSeidl\EmailComposer') as $class) {
        $public = in_array($class, $publicClasses, true) || Str::startsWith($class, $publicNamespaces);
        $reflection = new ReflectionClass($class);

        expect(hasTag($reflection, '@internal'))->toBe(! $public, $class);

        if (! $public) {
            continue;
        }

        if (! $reflection->isEnum() && ! $reflection->isInterface()) {
            expect(hasTag($reflection, '@final'))->toBeTrue($class);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor?->class !== $class) {
            continue;
        }

        $takesInternal = collect($constructor->getParameters())
            ->map(fn (ReflectionParameter $parameter) => $parameter->getType())
            ->contains(fn (?ReflectionType $type) => $type instanceof ReflectionNamedType
                && Str::startsWith($type->getName(), 'CSeidl\EmailComposer\\')
                && hasTag(new ReflectionClass($type->getName()), '@internal'));

        expect(hasTag($constructor, '@internal'))
            ->toBe($takesInternal || Str::startsWith($class, 'CSeidl\EmailComposer\Actions\\'), $class.'::__construct');
    }
});
