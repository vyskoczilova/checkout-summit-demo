# CLAUDE.md — Checkout Summit Demo

Project notes for AI assistants working in this repo.

## What this is

A demo WordPress plugin for Checkout Summit Palermo. Adds an "AI Gallery
Generator" metabox on WooCommerce product edit screens: pick a source image
(or fall back to the product's featured image) → calls the WordPress 7.0
AI Client with two scene templates → attaches both generated images to the
product gallery.

## WordPress 7.0 AI Client — API reference

This plugin **must** call the AI Client through the WordPress wrapper. Do not
call any AI provider directly. Provider selection is delegated to whatever the
site has configured under **Settings → AI Connectors**.

### Method naming — snake_case, NOT camelCase

The WordPress wrapper class `WP_AI_Client_Prompt_Builder` exposes **snake_case**
method names. The underlying `WordPress\AiClient` PHP library uses camelCase,
but those methods are not proxied by the WP wrapper. Calling camelCase methods
on the WP wrapper does NOT throw — it silently returns the builder, which then
shows up downstream as `Unrecognised image object returned by AI Client:
WP_AI_Client_Prompt_Builder`.

Confirmed snake_case methods:
- `with_file( string $file, string $mime_type )` — attach a reference image
- `using_system_instruction( string $text )`
- `using_model_preference( string ...$model_ids )`
- `using_temperature( float $t )`
- `using_max_tokens( int $n )`
- `as_json_response()`
- `generate_text(): string|WP_Error`
- `generate_image(): object|WP_Error` — returns a file object
- `generate_images( int $count ): array|WP_Error`
- `generate_result(): object|WP_Error`

### Return shape of `generate_image()`

Returns either `WP_Error` or a file object. Documented accessors on the file
object include at least `getDataUri()` (returns a `data:image/...;base64,...`
URI). Other accessors (`getBase64Data()`, `getBytes()`, `getPath()`,
`getUrl()`) may or may not be present depending on the connector — the upstream
docs are still sparse. The plugin's `Generator::extract_bytes()` probes for all
of these in priority order before failing.

### Canonical usage

```php
$image = wp_ai_client_prompt( $prompt_text )
    ->with_file( $source_path, $source_mime )   // e.g. 'image/png', 'image/jpeg'
    ->generate_image();

if ( is_wp_error( $image ) ) {
    // bail with $image->get_error_message()
}

// Then turn the returned file object into bytes via getDataUri()/getBase64Data()/etc.
```

**Always** check `is_wp_error()` after a `generate_*` call. The wrapper does
not throw on failure.

### What we do NOT do

- Do **not** call `->using_provider()` / `->usingProvider()`. Let WP core's
  configured connector pick.
- Do **not** import the underlying `WordPress\AiClient\AiClient` directly.
- Do **not** use any provider SDK (OpenAI, Anthropic, Google) directly.

### Reference

- Repo: <https://github.com/WordPress/wp-ai-client>
- Underlying PHP lib: <https://github.com/WordPress/php-ai-client>
- Make/AI announcement: <https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/>
- Practical examples (Rich Tabor): <https://rich.blog/wordpress-ai-client/>

## Code organization

- `checkout-summit-demo.php` — bootstrap, constants, hook wiring on `init`
- `includes/class-prompt-builder.php` — pure PHP, unit-tested, flattens scene JSON to text
- `includes/class-generator.php` — calls `wp_ai_client_prompt()`, saves to Media Library, appends to gallery meta
- `includes/class-ajax.php` — `wp_ajax_csd_generate_gallery` handler; nonce + cap + source resolution
- `includes/class-metabox.php` — registers metabox, enqueues `wp.media` + admin assets
- `assets/admin.{js,css}` — vanilla JS, opens `wp.media` frame, posts to AJAX
- `json/*.json` — scene templates (one per output image); add a file here and list it in `class-ajax.php` to add a scene

## Tests

```bash
composer install
vendor/bin/phpunit
```

Only `Prompt_Builder` is unit-tested. Everything else is verified by manual
smoke test on a real WordPress install — see Task 8 in
`docs/superpowers/plans/2026-04-23-ai-gallery-generator.md`.

## Conventions

- Namespace: `CheckoutSummitDemo\` for all PHP classes under `includes/`.
- File naming: `class-<thing>.php`, lowercase with hyphens.
- Class file structure: `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of
  every PHP file in `includes/`.
- No build step. Vanilla JS, no jQuery beyond what `wp.media` brings in.
- Synchronous AJAX is intentional — generation takes ~10–30s; no Action
  Scheduler / queue for the demo.
