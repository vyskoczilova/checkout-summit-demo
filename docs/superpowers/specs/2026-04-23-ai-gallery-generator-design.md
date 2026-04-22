# AI Gallery Generator — Design

**Date:** 2026-04-23
**Plugin:** checkout-summit-demo
**Audience:** Checkout Summit Palermo demo

## Goal

From a single source PNG uploaded on a WooCommerce product edit screen, generate two
lifestyle gallery images using prepared scene templates and attach them to the product
gallery. Image generation is delegated to WordPress 7.0's AI Client; the plugin does
not call any AI provider directly.

## User flow

1. Editor opens a WooCommerce product in wp-admin.
2. In the new "AI Gallery Generator" metabox, they upload a product PNG.
3. They click "Generate gallery images".
4. After ~10–30s, two new images appear as thumbnails in the metabox and are added
   to the product's gallery (`_product_image_gallery`).

## Architecture

### File layout

```
checkout-summit-demo.php         # bootstrap: constants, autoload-ish includes, hooks
includes/
  class-metabox.php              # registers metabox, enqueues admin JS, prints markup
  class-generator.php            # loads JSON, builds prompt, calls AI Client, saves to media
  class-ajax.php                 # admin-ajax handler: nonce + caps + orchestrates generator
assets/
  admin.js                       # file upload, AJAX call, thumbnail rendering, status UI
  admin.css                      # minimal styling for the metabox
json/
  living_room_front.json         # scene template (existing)
  table_surface_side.json        # scene template (existing)
```

### Components

**Metabox (`includes/class-metabox.php`)**
- Registered on `add_meta_boxes_product` for the `product` post type, side context.
- Renders: file input, "Generate" button, status area, thumbnail strip.
- Enqueues `admin.js` and `admin.css` only on the product edit screen.
- Localizes a JS object with the AJAX URL, nonce, and post ID.

**AJAX handler (`includes/class-ajax.php`)**
- Action: `wp_ajax_csd_generate_gallery`.
- Verifies nonce (`csd_generate_gallery_<post_id>`) and `current_user_can('edit_product', $post_id)`.
- Accepts the uploaded PNG via `$_FILES`, runs it through `wp_handle_upload` + `wp_insert_attachment` + `wp_generate_attachment_metadata`, attaches to the product.
- Calls `Generator::generate_for_product( $post_id, $source_attachment_id )`.
- Returns JSON: `{ success: true, attachments: [ { id, url }, ... ] }` or `{ success: false, message }`.

**Generator (`includes/class-generator.php`)**
- `generate_for_product( int $product_id, int $source_attachment_id ): array` — returns array of new attachment IDs.
- For each scene template file in `json/`:
  1. Load and decode JSON.
  2. Substitute `subject.description` with the product title (and short description if available, truncated).
  3. Resolve any `[VARIABLE]` `camera.angle` by picking the first listed option (deterministic for demo).
  4. Flatten the JSON into a structured text prompt (one section per top-level key, key/value lines underneath; include `negative_prompts` as a "Avoid:" line).
  5. Call:
     ```php
     $image = wp_ai_client_prompt( $prompt )
         ->withFile( $source_path, 'image/png' )
         ->generateImage();
     ```
     (No `->usingProvider()` — the active provider is whatever the site has configured in WordPress 7.0's AI Connectors screen.)
  6. Persist returned image bytes to the uploads directory, create attachment, generate metadata, attach to the product.
  7. Append the new attachment ID to `_product_image_gallery` (existing IDs preserved).
- Returns the list of new attachment IDs (with URLs assembled by the AJAX layer).

**Front-end JS (`assets/admin.js`)**
- On submit: read the file input, build `FormData`, POST to admin-ajax with the nonce.
- While in flight: disable the button, show "Generating… this can take ~30 seconds".
- On success: render thumbnails for the returned attachments.
- On failure: render the error message verbatim in the status area.

## Prompt construction

For a JSON template like `living_room_front.json`, the prompt becomes a plain-text
block of the form:

```
Subject:
  description: <product title> — <short description, truncated to ~200 chars>
  placement: centered in frame, resting on a light oak coffee table
  scale_hint: small object, approx. 10cm tall, occupying 15–20% of frame height

Environment:
  scene: modern Scandinavian living room
  ...

Camera:
  angle: front-facing straight-on
  ...

(... lighting, style sections ...)

Avoid: no people, no text, no logos, no watermarks, no cartoon, no CGI look, no overexposure, no clutter

Use the attached PNG as the exact product to render in this scene.
```

The `[FIXED]` / `[VARIABLE]` markers in the source JSON are stripped before flattening.

## Guardrails

- **Capability:** `current_user_can('edit_product', $post_id)` on every AJAX request.
- **Nonce:** unique per product (`csd_generate_gallery_<post_id>`).
- **Upload validation:** `wp_check_filetype` restricted to `image/png`.
- **Hard fail, no silent fallback:** if `function_exists('wp_ai_client_prompt')` is false, the AJAX handler returns an error explaining that WordPress 7.0's AI Client is required, with no attempt to call any other API. Same for any exception thrown by the AI Client — surfaced to the editor.
- **No background processing:** generation is synchronous within the AJAX request. Acceptable for the demo; documented as a known limitation.

## Out of scope (explicit)

- Background queue / Action Scheduler.
- Multiple source images, batch processing across products.
- Per-image regenerate / delete from inside the metabox (use the standard product gallery UI).
- Caching of generated images.
- Provider selection UI (WordPress core handles this).
- i18n beyond wrapping user-visible strings in `__()` with the existing text domain.

## Dependencies

- WordPress 7.0+ with the AI Client available (`wp_ai_client_prompt()`).
- A configured AI connector in the AI Connectors screen with image-generation capability.
- WooCommerce active (the metabox is only registered for the `product` post type).
