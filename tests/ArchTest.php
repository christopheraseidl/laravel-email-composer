<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Support\Facades\Gate;

/** @return list<class-string> */
function classesIn(string $directory, string $namespace): array
{
    return array_map(
        fn (string $file) => $namespace.'\\'.basename($file, '.php'),
        glob(__DIR__."/../{$directory}/*.php"),
    );
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

arch('models do not depend on Filament')
    ->expect('CSeidl\EmailComposer\Models')
    ->not->toUse('Filament');

arch('models declare their factory')
    ->expect('CSeidl\EmailComposer\Models')
    ->toHaveAttribute(UseFactory::class);

arch('factories declare their model')
    ->expect('CSeidl\EmailComposer\Database\Factories')
    ->classes()
    ->toHaveAttribute(UseModel::class);

it('has a policy for every model', function (string $model) {
    $policy = str_replace('\\Models\\', '\\Policies\\', $model).'Policy';

    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with(classesIn('src/Models', 'CSeidl\EmailComposer\Models'));

it('returns bool from every policy method', function (ReflectionMethod $method) {
    expect((string) $method->getReturnType())->toBe('bool');
})->with(policyMethods());

it('delegates every policy method to EmailComposer::userCan', function (ReflectionMethod $method) {
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->toContain('EmailComposer::userCan(');
})->with(policyMethods());
