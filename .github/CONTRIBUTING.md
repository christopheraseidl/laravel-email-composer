# Contributing

## Setup

- `composer install`
- `composer test` runs the suite in parallel.
- `composer analyse` runs PHPStan.
- `composer format` runs Pint.
- `composer serve` builds and serves the workbench app.

A pull request passes the tests, PHPStan and `vendor/bin/pint --test`.

## Branches and commits

- Branch from `develop` and open the pull request into `develop`.
- Name the branch `<type>/<short-description>`. The type sets the pull request's label:
  - `feat`: feature
  - `fix`: fix
  - `refactor`, `perf`, `style`, `test`, `build`, `ci`, `chore`, `docs`: internal
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org):
  `<type>(<scope>): <subject>`.

## Code

- Writes live in invokable action classes in `src/Actions/`, with dependencies injected
  through the constructor.
- Classes that only carry values are readonly and live in `src/Data/`.
- Filament code lives only in `src/Filament/`.
- Rules about what a draft may do live on `EmailDraft`.
- Logic is reused, not copied.
- Every user-facing string is translated in `resources/lang/en` and `resources/lang/es`.
- A class outside the public API is marked `@internal`.