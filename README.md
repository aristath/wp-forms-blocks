# WP Forms Blocks

WP Forms Blocks provides native blocks for building forms in the WordPress block editor. Forms can send submissions to the site administrator, post to a custom URL, create comments, or start WordPress personal-data export and deletion requests.

## Requirements

- WordPress 7.0 or newer
- PHP 7.4 or newer

## Installation

1. Download or clone the plugin into `wp-content/plugins/wp-forms-blocks`.
2. Activate **WP Forms Blocks** from **Plugins** in the WordPress administration area.
3. Open a post or page in the block editor and insert a **Form** block.

Release archives include the compiled assets and do not require Node.js or Composer on the WordPress server.

## Create a contact form

The default **Contact Form** includes:

- Editable success and error messages
- Required Name, Email, and Comment fields
- A Submit button

To publish one:

1. Insert the **Form** block. The Contact Form is selected by default.
2. Edit the field labels and notification text directly in the canvas.
3. Select an Input Field to change whether it is required, make its label inline, or set its field name in **Advanced**.
4. Add, remove, or reorder Input Field blocks as needed.
5. Publish the post or page and submit a test entry.

Contact-form submissions are sent as plain-text email to the **Administration Email Address** configured under **Settings → General**. Each message contains the source page URL and the submitted field names and values. The recipient cannot be changed by a visitor or configured separately per form.

During submission, the form displays a localized status message, disables its submit controls, and prevents duplicate requests. The result page focuses the success or error notification so the outcome is available to keyboard and screen-reader users.

## Form types

### Contact Form

Sends the form fields to the site's Administration Email Address. This is the default form type and uses an asynchronous request before displaying the configured success or error notification.

### Comment Form

Submits a comment for the post or page containing the form. Name and Email fields are displayed to logged-out visitors; logged-in visitors use their account details. Submitted comments follow the site's normal WordPress discussion and moderation settings.

### Privacy Request Form

Allows a visitor to request a personal-data export, personal-data deletion, or both. WordPress sends the visitor its standard confirmation email before processing the request. The form displays an error notification if a request cannot be created or its confirmation email cannot be sent.

### Custom form action

To submit a form to another endpoint:

1. Select the Form block.
2. In **Settings**, set **Submissions method** to **Custom**.
3. In **Advanced**, choose `GET` or `POST` and enter the destination in **Form action**.

Custom forms use native browser submission. Their destination is responsible for validation, processing, responses, and redirects. The action supports two placeholders:

- `{SITE_URL}` expands to the site's public URL.
- `{ADMIN_URL}` expands to the WordPress administration URL.

## Available blocks

### Form

The form container controls the submission method and contains the fields, submit button, notifications, and supporting content. It accepts Paragraph, Heading, Group, and Columns blocks in addition to the plugin's form blocks.

The Form block supports the standard editor controls for anchors, colors, gradients, spacing, and typography.

### Input Field

Input Field variations are available for:

- Text
- Textarea
- Checkbox
- Email
- URL
- Telephone
- Number
- Hidden values

Visible fields support an editable label, an optional placeholder, required-field validation, and an explicit field name. If the field name is empty, the plugin generates one from the label. Hidden fields expose Name and Value controls under **Advanced**.

Required fields display a localized required indicator and retain the browser's native required-field semantics.

### Form Submit Button

Contains the button used to submit the form. Select the nested Button block to edit its text and use the normal WordPress button design controls.

### Form Submission Notification

Contains editable content shown after a successful or failed plugin-managed submission. Success notifications use polite status semantics; error notifications use assertive alert semantics.

## Styling

Use the block editor's design controls to style the form, fields, and nested content. Additional site-specific styling can target the public block classes:

```text
.wp-block-formblox-form
.wp-block-formblox-form-input
.wp-block-formblox-form-submit-button
.wp-block-formblox-form-submission-notification
```

## Privacy and data handling

- Contact forms send submitted values by email to the site administrator.
- Comment forms create normal WordPress comments.
- Privacy Request forms create normal WordPress export or deletion requests.
- Custom forms send their values to the configured destination.

Site owners are responsible for explaining these uses in their privacy notice, collecting only necessary information, securing the destination of custom forms, and configuring an appropriate email-delivery service for WordPress.

## Developer API

The plugin registers these block names:

```text
formblox/form
formblox/form-input
formblox/form-submit-button
formblox/form-submission-notification
```

The following PHP filters are available:

### `render_block_formblox_form_extra_fields`

Adds HTML immediately before a rendered form's closing tag.

```php
add_filter(
    'render_block_formblox_form_extra_fields',
    function ( $fields, $attributes ) {
        return $fields . '<input type="hidden" name="source" value="website">';
    },
    10,
    2
);
```

### `render_block_formblox_form_email_content`

Filters the complete plain-text contact-form email body. It receives the generated content and the submitted parameters.

```php
add_filter(
    'render_block_formblox_form_email_content',
    function ( $content, $params ) {
        return $content . "Processed by: Contact workflow\n";
    },
    10,
    2
);
```

### `formblox_show_form_submission_notification_block`

Controls whether a rendered success or error notification is displayed. It receives the current visibility decision, block attributes, and saved notification content.

```php
add_filter(
    'formblox_show_form_submission_notification_block',
    function ( $show, $attributes, $content ) {
        return $show;
    },
    10,
    3
);
```

## Development

Install the JavaScript and PHP dependencies:

```sh
pnpm install --ignore-scripts
composer install
npm run prepare
```

Common commands:

```sh
npm start              # Watch source files and rebuild assets.
npm run build          # Create a production build.
npm run format         # Apply JavaScript and PHP formatting.
npm run lint           # Run all code-quality checks.
npm run test:unit      # Run JavaScript unit tests.
npm run test:php       # Run PHPUnit tests.
npm run test:wordpress # Run isolated WordPress integration tests.
npm run test:e2e       # Run Playwright browser tests.
npm test               # Run every test suite.
npm run gate:commit    # Run the complete commit gate.
npm run plugin-zip     # Build an installable plugin archive.
```

The WordPress integration and Playwright suites use disposable SQLite databases and do not touch the development site's database. Browser tests require WP-CLI and Playwright's Chromium browser:

```sh
pnpm exec playwright install chromium
```

Lefthook runs the complete code-quality, build, Plugin Check, unit, integration, and browser gate before every commit. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

GPL-2.0-or-later.
