# Formblox codebase audit

Date: 2026-09-15  
Reviewed revision: `8e6de868a324ed9e430a026172497c1d516711b8`  
Upstream comparison baseline: Gutenberg `v23.9.1` (`c29617a19a0197efdf3f53a820833c2d68c0b405`)

## Verdict

The standalone port is substantially faithful to the final Gutenberg experiment, but it is not ready to be treated as a production forms product without further changes.

The audit found 14 confirmed issues. Subsequent administrator-recipient, localization, and documentation changes resolve findings 1, 7, 8, and 14, leaving 10 open findings. The quality-tooling portion of finding 9 is also resolved, but that finding remains open until its enumerated behavioral coverage gaps are closed:

| Severity | Original | Resolved | Open |
| --- | ---: | ---: | ---: |
| Critical | 1 | 1 | 0 |
| High | 8 | 2 | 6 |
| Medium | 4 | 0 | 4 |
| Low | 1 | 1 | 0 |
| Total | 14 | 4 | 10 |

Most runtime defects are inherited from the rejected Gutenberg experiment. That provenance explains why they exist, but it does not make them appropriate for a standalone product. The plugin-boundary work should remain as small and auditable as possible while correcting security, correctness, localization, testing, and release-readiness problems.

## Verification performed

The following checks were repeated against the reviewed revision:

- Inspected every tracked source, bootstrap, build configuration, test, fixture, and documentation file.
- Compared the implementation with the exact Gutenberg `v23.9.1` source baseline.
- Ran `pnpm test`: 42 JavaScript tests, the WordPress integration scripts, and 7 Playwright tests all passed.
- Confirmed JavaScript coverage of 100% statements, functions, and lines, and 98.87% branches.
- Ran the JavaScript, CSS, and PHP lint commands successfully.
- Ran `pnpm audit --prod --audit-level=low`; no known production dependency vulnerabilities were reported at the time of the audit.
- Built the plugin and generated a real `wp-scripts plugin-zip` archive in an isolated copy. The archive contained the production plugin files and excluded tests, specs, source files, and internal documentation.
- Ran isolated WordPress probes which reproduced the KSES context pollution, replacement of Core textarea attributes, query-string notification spoofing, privacy-mail false-success state, public AJAX module data, and duplicate front-end stylesheet output.
- Confirmed that the worktree was clean and matched `origin/main` before this audit document was added.

Post-audit quality remediation was then verified with the complete repository checks:

- `npm run lint` passed Prettier, ESLint, Stylelint, Markdownlint, package metadata, POT freshness, PHP syntax, PHPCS/WPCS/PHPCompatibilityWP, and PHPStan level 6.
- `npm run lint:plugin` produced no findings while checking the extracted production archive with all experimental checks enabled. The established product-name-only `trademarked_term` code is explicitly ignored.
- JavaScript unit coverage passed 42 tests across 7 suites with 100% statements, functions, and lines and 98.8% branches.
- PHPUnit passed 9 tests with 22 assertions; the isolated WordPress integration suite passed; and all 7 Playwright workflows passed on both the declared minimum WordPress 7.0 and current WordPress 7.1.
- Frozen pnpm installation, strict Composer manifest validation, production build, archive generation, and archive-content inspection all passed.

Passing tests do not invalidate the findings below. Several tests deliberately preserve the upstream experiment's behavior, and some mocks do not model the real WordPress response contract.

## Confirmed findings

### 1. Critical, resolved: the public email endpoint could be used as an arbitrary-recipient mail relay

Original evidence at the reviewed revision:

- `src/form/index.php:72-108` registers both authenticated and unauthenticated AJAX actions.
- `src/script-module-data.php:16-25` exposes the AJAX URL, action, and valid nonce to the public view module.
- `src/form/index.php:95-100` takes `formAction` directly from the request, removes `mailto:`, and passes the result to `wp_mail()`.
- At the audited revision, `tests/php/wordpress-ajax-smoke.php` explicitly verified that the request-provided `formAction` became the mail recipient.

The nonce is a CSRF control, not authorization, recipient binding, rate limiting, or spam prevention. Anyone able to load a public page containing the module data can submit a chosen recipient and chosen field content directly to the endpoint.

Required change:

- Give each email form a stable server-verifiable identity.
- Resolve recipients from trusted server-side configuration or verify a signed immutable configuration. Do not trust the browser-provided recipient.
- Validate every recipient with WordPress email validation and explicitly support or reject multiple recipients.
- Add payload-size and field-count limits, rate limiting, a honeypot, and spam-protection extension points.
- Add integration and E2E tests proving that a request cannot override the configured recipient.

Resolution:

- The recipient editor control and `email` block attribute were removed.
- Email-form rendering ignores configured or legacy `mailto:` actions and marks the form with `data-formblox-submission-method="email"`.
- The browser module no longer generates a `formAction` recipient, and PHP ignores any forged or legacy `formAction` submitted by a visitor.
- PHP always resolves the recipient from WordPress's server-side `admin_email` option.
- PHP integration and Playwright tests submit a forged `formAction=mailto:attacker-controlled@example.net` and require delivery to the WordPress administration email instead.

Provenance: inherited from Gutenberg, then deliberately changed at the plugin boundary to make the public standalone product safe from arbitrary-recipient relay abuse.

### 2. High: a real `wp_mail()` failure is shown as a successful submission

Evidence:

- `src/form/index.php:102-104` calls `wp_send_json_error( $result )` without an HTTP error status.
- WordPress `wp_send_json_error()` forwards the default `null` status to `wp_send_json()`, so the ordinary response remains HTTP 200.
- `src/form/view.js:40-52` checks only `response.ok` and never parses the JSON `success` property.
- `tests/js/view-response.test.js:50-59` mocks only the `ok` Boolean.
- `specs/forms.spec.js:337-369` simulates failure using a fabricated HTTP 500 response, which differs from the real PHP endpoint's HTTP 200 JSON failure.
- `tests/run-wordpress-tests.sh:36-37` asserts only that the body contains `"success":false`; it does not assert an error HTTP status or browser behavior.

Required change:

- Return an appropriate non-2xx status for mail failure and parse the JSON response in the browser.
- Treat a response as successful only when both the HTTP response and JSON contract indicate success.
- Replace the mocked failure E2E path with a real `pre_wp_mail` failure through the actual endpoint.

Provenance: inherited from Gutenberg.

### 3. High: the email submission handler has no strict request schema or resource limits

Evidence:

- `src/form/index.php:77-102` assumes `_wp_http_referer` exists and is a usable scalar value.
- Every remaining request value is passed to `wp_kses_post()` without first requiring a scalar string.
- `formAction` is retained only in the reserved-field skip list for hostile or legacy submissions; it can no longer select the recipient.
- An array submitted as a form value reaches string-oriented KSES processing and can raise a PHP `TypeError` on supported PHP versions.
- There is no maximum request size, field count, field-name length, or field-value length.
- The rendered method at `src/form/index.php:36-38` is not restricted server-side to the methods offered by the editor.
- `tests/run-wordpress-tests.sh:18` reduces reporting to `E_ERROR`, masking warnings and notices which should fail validation tests.

Required change:

- Define a strict request schema and reject missing, non-scalar, or malformed values with a 4xx JSON response.
- Validate and normalize the referer, method, field names, and field values. Continue validating the server-controlled administration recipient before mail delivery.
- Enforce request, field-count, name-length, and value-length limits.
- Run PHP tests with warnings and notices visible and treated as failures.
- Add adversarial tests for arrays, nested values, missing keys, oversized bodies, invalid recipients, and unsupported methods.

Provenance: inherited from Gutenberg.

### 4. High: the KSES filter corrupts unrelated WordPress sanitization contexts

Evidence:

- `src/kses-allowed-html.php:17-41` accepts only the allowed-tags array and is registered with the default one accepted argument.
- WordPress applies `wp_kses_allowed_html` to `post`, `user_description`, `strip`, `entities`, `data`, and explicit-array contexts.
- Isolated runtime probes confirmed that the plugin adds `input`, `label`, and `textarea` to both the `strip` and `entities` contexts.
- The callback assigns new `label` and `textarea` arrays rather than merging them. Runtime comparison confirmed that Core's allowed textarea attributes, including `cols`, are removed from the filtered `post` context.

Required change:

- Accept the `$context` argument by registering the callback with two accepted arguments.
- Modify only the intended post-content context.
- Merge attributes into existing elements instead of replacing Core definitions.
- Add tests for `post`, `strip`, `entities`, `data`, user-description, and explicit-array contexts.

Provenance: inherited from Gutenberg. Removing the Gutenberg experiment guard made this filter always active whenever the standalone plugin is active.

### 5. High: privacy-request email failures are ignored and reported as success

Evidence:

- `src/form/index.php:144-156` checks the result of `wp_create_user_request()` but ignores the return value of `wp_send_user_request()`.
- WordPress returns `true|WP_Error` from `wp_send_user_request()`.
- An isolated runtime probe forced `pre_wp_mail` to return false. The request was still added to `$actions_performed`, the success notification rendered, and the error notification remained hidden.
- The resulting pending request can also make a visitor's later retry behave differently because the database request was already created.

The privacy handler is attached globally to `wp` at `src/form/index.php:183` and recognizes only generic hidden POST fields. It has no form identity or request token tying the submission to an actual rendered privacy form.

Required change:

- Inspect and handle the result of `wp_send_user_request()`.
- Define deliberate cleanup or retry behavior for a created request whose confirmation email failed.
- Bind privacy submissions to a specific rendered form and validate a purpose-specific token.
- Test mail failure, duplicate requests, partial success when two actions are requested, malformed email values, and retry behavior.

Provenance: inherited from Gutenberg.

### 6. High: submission notifications are forgeable and not associated with a form instance

Evidence:

- `src/form-submission-notification/index.php:18-33` displays a result solely from the global `formblox-form-result` query parameter.
- An isolated runtime probe confirmed that requesting a page with `?formblox-form-result=success` renders success without any submission.
- `src/form/view.js:21-24` appends the same global result parameter for every form.
- The privacy handler installs a global render filter, so multiple forms or notification blocks rendered in the same request cannot identify which form produced the result.

Required change:

- Add a stable per-form identifier.
- Associate the result with that form, ideally through a signed and short-lived result token.
- Use `URLSearchParams.set()` instead of accumulating duplicate result parameters.
- Add tests for multiple forms, forged parameters, duplicate parameters, refresh behavior, and results intended for a different form.

Provenance: inherited from Gutenberg.

### 7. High, resolved: the default Contact Form variation was not functional after insertion

Original evidence at the reviewed revision:

- `src/form/variations.js:7-49` makes Contact Form the default and creates its fields, but supplies only `submissionMethod: 'email'`.
- Neither the variation nor `src/form/block.json:19-33` supplies a recipient or `mailto:` action.
- `src/form/view.js:12-19` intercepts only a form whose resolved action starts with `mailto:`. An empty HTML form action resolves to the current page URL, so the handler ignores a newly inserted form.
- `src/form/edit.js:111-127` marks the editor control as required, but that browser attribute does not prevent saving or publishing a block with no recipient.
- The default-variation E2E test checks the inserted inner blocks but does not publish and submit that newly inserted form. The successful email E2E test uses separately handcrafted content with an explicit recipient.

Required change:

- Decide on the standalone product behavior: either initialize the recipient from a trusted site setting or visibly mark the block incomplete until an address is configured.
- Prevent or clearly warn on publishing an email form without a valid recipient.
- Add an E2E test which inserts the default variation, configures it through the editor, publishes it, and submits it successfully.

Resolution:

- Email forms no longer require per-block recipient configuration.
- The default Contact Form is rendered with the explicit email-submission marker and sends to the WordPress Administration Email Address.
- The E2E submission fixture now uses the recipient-free default email configuration and verifies delivery to `admin@example.com`.

Provenance: introduced by the standalone Contact Form/default-variation addition, although the empty-recipient mechanics came from the upstream implementation.

### 8. High, resolved: plugin localization was not correctly connected

Evidence:

- All four manifests use `"textdomain": "default"`; examples include `src/form/block.json:18` and `src/form-input/block.json:10`.
- JavaScript and PHP translation calls omit the plugin text domain.
- `includes/assets.php:46-59` registers the editor script but never calls `wp_set_script_translations()`.
- The plugin header correctly declares `Text Domain: wp-forms-blocks`, so the runtime code and manifests disagree with the plugin metadata.

Required change:

- Use `wp-forms-blocks` consistently in every block manifest and translation call.
- Register script translations for the editor bundle.
- Add a POT-generation/check step and tests for metadata consistency.

Resolution:

- All block manifests, JavaScript translation calls, and PHP translation calls now use the `wp-forms-blocks` text domain.
- The consolidated editor bundle is connected with `wp_set_script_translations()`.
- `languages/wp-forms-blocks.pot` is tracked, and `npm run lint:i18n` fails when extractable strings or metadata drift from it.
- The metadata unit tests require the plugin text domain for every block.

Provenance: standalone-port integration gap.

### 9. High, partially resolved: the test suite does not yet cover the complete product contract

Evidence:

- JavaScript has enforced coverage thresholds, and a PHPUnit suite now covers isolated PHP callbacks and hook registration. PHP line coverage is not yet collected.
- The WordPress integration suite still uses WP-CLI evaluation scripts and does not yet convert every warning or notice into a hard test failure.
- The browser failure test does not exercise the real PHP failure contract.
- There are no tests for arbitrary recipient override, malformed/nested input, resource limits, KSES contexts, privacy mail failure, multiple-form correlation, notification forgery, localization, accessibility announcements, repeated field names, or archive activation.
- PHPCS with WPCS and PHPCompatibilityWP, PHPStan level 6, official Plugin Check, Prettier, ESLint, Stylelint, Markdownlint, translation validation, and Lefthook are now configured and passing. Lefthook gates every commit on all checks, a reproducible build, and every test suite.
- GitHub Actions now runs JavaScript coverage, PHP 7.4/8.5 lint/static-analysis/unit matrices, isolated WordPress 7.0/current integration and Playwright suites, build reproducibility, and Plugin Check against the release archive.

Required change:

- Extend PHPUnit and WordPress integration coverage to every behavioral and failure contract listed above.
- Stop suppressing warnings/notices in the integration harness and make them fail CI.
- Retain the existing serialization and E2E tests, but extend them to cover the actual contracts listed above.

Provenance: standalone productization gap.

### 10. Medium: field behavior is incomplete or internally inconsistent

Evidence:

- Every entry in `src/form-input/variations.js` declares `isDefault: true`; only the canonical text variation should be the default.
- `src/form-input/edit.js:33-34` and `src/form-input/save.js:51-52` mention radio inputs while explicitly stating that they are not implemented. There is no radio variation.
- `src/form-input/save.js:54-79` serializes the configurable `value` only for hidden fields, leaving checkbox/radio values unavailable.
- The editor renders checkbox/radio elements using the placeholder value and placeholder-change handler at `src/form-input/edit.js:155-174`, which is not a meaningful editor model for those controls.
- `src/form/view.js:30-33` converts `FormData` with `Object.fromEntries()`, discarding all but one value for repeated field names. That prevents checkbox groups, radio groups, and other repeated-value submissions from being represented faithfully.
- The notification error variation at `src/form-submission-notification/variations.js:51-52` treats a missing type as active even though the success variation does the same.

Required change:

- Keep only the text input variation as the default.
- Either implement radio and repeated-value behavior completely or remove unsupported/dead surface area.
- Add explicit checkbox/radio value controls and a suitable editor preview model.
- Serialize form data without losing repeated names.
- Make variation activation mutually exclusive and test the default/missing-attribute case.

Provenance: inherited from Gutenberg.

### 11. Medium: the submit-button style is ineffective and the shared stylesheet is printed twice

Evidence:

- `src/form-submit-button/edit.js:10-14` and `save.js:5-9` put an explicit wrapper class before a later props spread. The class from block props wins.
- Current serialized fixtures contain `wp-block-formblox-form-submit-button`, not `wp-block-formblox-form-submit-wrapper`.
- `src/form-submit-button/style.scss:1-3` targets only the missing wrapper class, so its margin rule does not affect saved markup.
- The component unit test mocks block props without their real class name, masking the spread-order behavior.
- `includes/assets.php:61-73` registers the same `build/style.css` URL under two different handles.
- An isolated WordPress render probe enqueued both handles and confirmed that the identical stylesheet URL was printed twice.

Required change:

- Supply any additional class through `useBlockProps()` or target the actual generated block class.
- Use one shared style handle for the consolidated stylesheet, or split the stylesheet into genuinely block-specific assets.
- Add an integration test using real block props/class merging and assert the final front-end markup and printed asset URLs.

Provenance: the class/spread problem is inherited; duplicate shared handles come from the standalone asset adapter.

### 12. Medium: email content mixes HTML markup with the default plain-text mail format

Evidence:

- `src/form/index.php:78-100` builds anchors and bold-capable KSES content and uses invalid `</br>` strings as separators.
- No HTML content-type header is passed to `wp_mail()`, whose default content type is plain text.
- The current E2E assertion at `specs/forms.spec.js:325-334` explicitly expects literal `</br>` strings, preserving the malformed format.

Required change:

- Prefer a clearly formatted plain-text message and sanitize values as text.
- If HTML email is a product requirement, use valid `<br>` markup, set the content type explicitly and locally, and tightly constrain user-controlled markup.
- Add exact tests for the chosen content type, line endings, escaping, and multi-line fields.

Provenance: inherited from Gutenberg.

### 13. Medium: submission feedback lacks essential accessibility and interaction states

Evidence:

- Saved submission notifications contain no `role="status"`, `role="alert"`, or `aria-live` semantics in `src/form-submission-notification/save.js`.
- `src/form/view.js:27-55` supplies no submitting state, button disabling, duplicate-submission guard, or error detail.
- After navigation, focus is not moved to or announced at the result message.
- Required fields have native required semantics but no consistent visible required indicator supplied by the block.

Required change:

- Give success and error messages appropriate live-region semantics.
- Prevent duplicate submissions and expose a visible and accessible submitting state.
- Move focus appropriately after the redirect or replace the redirect with an accessible in-page result flow.
- Add automated accessibility assertions and manual keyboard/screen-reader test cases.

Provenance: inherited from Gutenberg and incomplete for a standalone product.

### 14. Low, resolved: documentation and provenance metadata contained stale or false statements

Evidence:

- `tests/fixtures/README.md:3` says the retained fixtures are copied byte-for-byte, although their block namespace and serialized classes were necessarily rewritten.
- `docs/port-audit.md:29` calls `view.js` unchanged even though standalone identifiers were changed.
- Generated README relationship links under `src/form*/README.md` use `formblox/*` labels while pointing to nonexistent WordPress Core block documentation URLs.
- `src/kses-allowed-html.php:5` still declares `@package gutenberg`, and several PHP files still declare `@package WordPress` rather than the plugin package.
- `src/utils/init-block.js:4` refers to the custom block name as a historical name, which is no longer accurate after the namespace change.

Required change:

- Describe fixtures as upstream-derived and namespace-rewritten, not byte-identical.
- Keep the port-audit exception list exact and current.
- Point generated documentation to plugin-owned documentation or remove invalid relationship links.
- Normalize package annotations and comments without changing runtime behavior.

Resolution:

- Fixture documentation now identifies the corpus as upstream-derived and namespace-rewritten.
- The port audit enumerates all intentional standalone and tooling deviations, including view-module identifiers.
- Custom-block relationships point to plugin-owned local documentation, while genuine Core blocks retain WordPress documentation links.
- PHP package annotations and standalone registration comments are normalized to the plugin's current identity.

Provenance: standalone documentation drift.

## Positive findings

- All plugin-owned block names consistently use the `formblox/*` namespace; genuine WordPress child blocks correctly retain `core/*` names.
- Historical deprecation implementations and migration-only fixtures have been removed as requested.
- Contact, Comment, and Privacy Request variation titles no longer carry the Experimental prefix.
- The source port remains recognizably aligned with Gutenberg rather than being replaced by an unrelated implementation.
- The editor and front-end bundles are small; performance is not the primary release risk.
- Front-end view-module loading is render-driven, so the submission JavaScript is not unconditionally printed on every front-end page.
- The current production dependency audit is clean.
- The generated release archive is lean and excludes development-only files.
- Gutenberg-aligned formatting and linting, WPCS/PHPCompatibility, PHPStan, PHPUnit, distributable-only Plugin Check, a complete Lefthook commit gate, dependency updates, and CI are now configured.

## Recommended implementation order

1. Correct the AJAX response contract and add real failure-path tests.
2. Add strict request validation, limits, throttling, and spam controls.
3. Correct the KSES filter and add context-specific tests.
4. Correct and bind privacy/result workflows to individual forms.
5. Complete or remove unsupported field behavior.
6. Correct styles, email formatting, and accessibility.

These changes should be implemented as narrowly scoped, separately reviewable commits. Where behavior intentionally diverges from the Gutenberg baseline for security or standalone-product correctness, the divergence should be recorded in `docs/port-audit.md` and covered by tests.
