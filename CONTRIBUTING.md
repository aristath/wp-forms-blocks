# Contributing

WP Forms Blocks uses the same public WordPress coding-standard packages that underpin Gutenberg, adapted to a standalone plugin repository.

## Install dependencies

```sh
pnpm install --ignore-scripts
composer install
```

Run `npm run prepare` once if Git hooks were skipped during installation. This installs the repository's Lefthook-managed pre-commit hook.

Every commit runs the complete repository gate, regardless of which files are staged:

1. All formatting, JavaScript, stylesheet, documentation, metadata, translation, PHP, coding-standard, Lefthook-configuration, and static-analysis checks.
2. A clean production rebuild, which must exactly match the staged `build/` files.
3. WordPress Plugin Check against the extracted production archive.
4. JavaScript coverage, PHPUnit, isolated WordPress integration, and Playwright end-to-end tests.

Run the exact same gate manually with either command:

```sh
npm run gate:commit
pnpm exec lefthook run pre-commit
```

The full gate intentionally requires the same local prerequisites as the integration and browser suites, including PHP, Composer dependencies, WP-CLI, Chromium, and network access for the isolated Plugin Check environment.

## Quality checks

```sh
npm run lint
npm run lint:format
npm run lint:js
npm run lint:css
npm run lint:md
npm run lint:pkg-json
npm run lint:i18n
npm run lint:lefthook
npm run lint:php
npm run lint:phpstan
```

`lint:lefthook` validates `lefthook.yml`. `lint:php` runs both PHP syntax checking and PHPCS with WordPress-Extra, PHPCompatibilityWP for PHP 7.4 and newer, and the plugin's `wp-forms-blocks` text domain. `lint:phpstan` runs WordPress-aware PHPStan at level 6.

To apply automatic formatting:

```sh
npm run format
```

To regenerate the translation template after changing user-facing strings:

```sh
npm run i18n:make-pot
```

## Tests

```sh
npm run test:unit
npm run test:coverage
npm run test:php
npm run test:wordpress
npm run test:e2e
```

`npm test` runs the complete test sequence. The WordPress and browser suites use disposable SQLite databases and do not touch the development database.

## Plugin Check

The Plugin Check runner builds the release archive, downloads the official checker, and checks the extracted distributable in an isolated WordPress/SQLite installation:

```sh
npm run lint:plugin
```

Nothing is installed into the development site. CI runs the same distributable-only check, along with every formatter, linter, unit suite, WordPress 7.0/current integration and browser suite, and a committed-build reproducibility check.

The sole ignored Plugin Check code is `trademarked_term`: the established plugin name and repository slug contain “WP”. All code, security, performance, accessibility, and experimental checks remain enabled, and strict mode is used in CI.
