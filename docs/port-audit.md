# Gutenberg 23.9.1 port audit

## Source baseline

This plugin is ported from the official Gutenberg `v23.9.1` tag at commit `c29617a19a0197efdf3f53a820833c2d68c0b405`, the final release containing the experiment before its removal.

The original source audit covered all 40 files in the four `packages/block-library/src/form*` directories, the relevant KSES and script-module-data functions under `lib/experimental`, and all 60 form serialization fixture files. Gutenberg contains no dedicated form-block PHP unit test or form-block E2E spec in that tag.

## Literal source fidelity

The implementation remains a direct source port rather than a reimplementation. The behavioral bodies, attributes, variations, templates, editor controls, save output structure, server rendering, email transport, privacy processing, and visibility rules come from Gutenberg `v23.9.1`. The deliberate standalone changes are enumerated below.

The namespace change necessarily touches every serialized fixture and many otherwise-identical source files: plugin blocks now serialize as `formblox/*`, their generated classes use `wp-block-formblox-*`, and plugin-owned runtime identifiers use `formblox`. Apart from those systematic substitutions and the standalone changes listed below, the port preserves the upstream implementation.

The two historical JavaScript deprecation implementations (`form/deprecated.js` and `form-input/deprecated.js`) and their 32 migration-only fixture files were subsequently removed. The retained 28 fixtures cover the seven canonical current-format cases under the standalone `formblox` namespace. This plugin supports one canonical block schema and does not register aliases or migrate markup saved with the former experimental `core/form*` names.

## Standalone-only changes

The maintained differences from Gutenberg are limited to:

1. PHP files use the `WPFormsBlocks` namespace to avoid global function collisions.
2. Gutenberg experiment-flag guards are removed; activating the plugin is the opt-in mechanism.
3. PHP metadata paths point at each standalone build directory.
4. PHP callback strings include the namespace.
5. The standalone asset loader registers and enqueues the consolidated editor bundle, `formblox`-scoped front-end style handles, and the `@formblox/form/view` script-module ID.
6. `src/index.js` imports the four original `init.js` entry points into one plugin editor bundle.
7. Plugin bootstrap, build configuration, package metadata, and consolidated Sass entry points are added around the port.
8. A minimal PHP registration file is added for `formblox/form-submit-button`, which Gutenberg registered through its shared block-library loader and therefore did not give a per-block PHP file.
9. WordPress 7.0 is the minimum version because the unchanged Gutenberg `view.js` relies on WordPress's native script-module data API.
10. Historical deprecation registrations and migration-only fixtures are omitted so the standalone plugin exposes only its canonical schema.
11. Current WordPress no longer applies a block type's registered `template` setting implicitly. The three container edit components pass their unchanged registered templates to `useInnerBlocksProps`, preserving Gutenberg's original default fields and nested content.
12. The standalone product adds a default Contact Form variation and removes the historical `Experimental` prefix from the Comment Form and Privacy Request Form titles. Their active-state checks use form attributes so each variation resolves distinctly.
13. The four block manifests and generated block documentation no longer mark the blocks as experimental; the experiment status applied to their former Gutenberg lifecycle, not to this standalone product.
14. The plugin blocks use `formblox/form`, `formblox/form-input`, `formblox/form-submit-button`, and `formblox/form-submission-notification`. The same namespace scopes their generated classes, PHP callbacks and filters, editor hook, AJAX action and nonce, result query parameter, style handles, and view-module ID. Genuine WordPress child blocks retain their `core/*` names.
15. Email forms no longer expose a configurable `mailto:` recipient or trust the request's `formAction`. They are identified by a rendered submission-method marker and always send to WordPress's server-side Administration Email Address. This is an intentional security deviation from the upstream experiment.
16. Standalone localization uses the plugin's `wp-forms-blocks` text domain in manifests and translation calls, connects editor script translations, and ships a generated POT file.
17. Shipped PHP entry files have direct-access guards, and standalone global bootstrap identifiers use the plugin-owned `formblox` prefix. These hardening changes do not alter form behavior.
18. PHP documentation was normalized for WPCS and static analysis. The tag-processor call uses the modern array query accepted by the same WordPress API instead of its deprecated string shorthand.
19. Repository-only quality infrastructure adds Gutenberg-aligned Prettier, ESLint, Stylelint, Markdownlint, PHPCS/WPCS, PHPCompatibilityWP, PHPStan, PHPUnit, Plugin Check, a complete Lefthook commit gate, dependency updates, and CI. It is excluded from the production archive and does not alter runtime behavior.
20. Email submission failures return HTTP 500, and the browser requires both an HTTP success response and a JSON `success: true` result before showing the success notification. This corrects the upstream experiment's false-success response handling.
21. Rendered privacy forms receive a unique instance UUID and purpose-specific nonce. Privacy confirmation-mail failures are reported as errors and moved to an auditable `request-failed` state with the unusable confirmation key cleared, allowing a later retry to create a fresh request.
22. Text Input is the sole default input variation, checkbox/radio editor previews do not mutate text-placeholder state, and the error-notification variation requires an explicit error type. These narrowly correct inherited editor and variation inconsistencies without adding grouped-field features.

The saved block markup, input and notification variation labels, KSES allowlist, and view-module data shape otherwise remain unchanged.

## Tests

`tests/fixtures/blocks/` contains all seven canonical current-format cases with four files per case, rewritten only for the standalone namespace. The JavaScript suite checks parser output, block registration, all variations, exact reserialization, and the view module's namespaced data and submission contract.

The unit suite additionally covers the metadata and templates for all four blocks, every variation and activation branch, editor rendering and every settings callback, current save branches, shared hooks, and all front-end response outcomes. Coverage gates require at least 95% statements/lines and 90% branches/functions across the executable ported JavaScript.

The standalone WordPress integration runner uses a disposable SQLite database and checks asset/module registration, rendering, administrator-only email delivery, recipient-override rejection, successful and failed email handling, comments, custom actions, visibility permissions, notifications, token-bound privacy requests, mail failure, partial success, duplicates, malformed email, and retry behavior, plus KSES.

The Playwright suite boots a separate disposable WordPress/SQLite site and verifies eight complete browser workflows: default Contact Form variation insertion and round-trip persistence, all field types and successful email submission, a real `wp_mail()` failure returning HTTP 500 through the AJAX endpoint, a custom method/action submission, logged-in/logged-out visibility, both successful privacy-request types, privacy mail failure followed by a successful retry, and comment submission. Neither integration runner touches the development site's database.
