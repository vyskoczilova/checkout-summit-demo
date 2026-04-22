# AI Gallery Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a WooCommerce product metabox that takes one source PNG, calls WordPress 7.0's AI Client with two prepared scene templates, and attaches both generated images to the product's gallery.

**Architecture:** Thin WordPress plugin with three PHP classes (Metabox, Ajax, Generator) plus a pure-PHP `Prompt_Builder` for the testable bit. Image generation is delegated to `wp_ai_client_prompt()->withFile()->generateImage()` — provider selection is whatever WordPress core has configured. Synchronous AJAX call, no queue.

**Tech Stack:** PHP 7.4+, WordPress 7.0+ (AI Client), WooCommerce, vanilla JS (no build step), PHPUnit for the prompt builder unit test.

---

## File Structure

```
checkout-summit-demo.php                       # bootstrap: constants, requires, hook wiring
includes/
  class-prompt-builder.php                     # pure: load JSON template, substitute subject, flatten to text
  class-generator.php                          # orchestrates one product: build prompt, call AI Client, save attachments
  class-metabox.php                            # registers metabox + enqueues assets, renders markup
  class-ajax.php                               # admin-ajax handler: nonce/cap checks, file intake, calls Generator
assets/
  admin.js                                     # form submit, fetch, render thumbnails + status
  admin.css                                    # tiny styles
tests/
  bootstrap.php                                # minimal PHPUnit bootstrap (no WP needed for prompt-builder tests)
  test-prompt-builder.php                      # unit tests for Prompt_Builder
phpunit.xml.dist                               # PHPUnit config
composer.json                                  # dev-only: phpunit/phpunit
```

`json/living_room_front.json` and `json/table_surface_side.json` already exist. Plugin bootstrap (`checkout-summit-demo.php`) already exists with header, constants, and a placeholder shortcode — we'll keep the header, drop the demo shortcode/admin page, and replace them with the new feature wiring.

---

## Task 1: Set up PHPUnit for the pure unit (prompt builder)

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`

- [ ] **Step 1: Create `composer.json`**

```json
{
    "name": "kybernaut/checkout-summit-demo",
    "description": "Checkout Summit Palermo demo plugin",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6"
    },
    "autoload-dev": {
        "psr-4": {
            "CheckoutSummitDemo\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <file>tests/test-prompt-builder.php</file>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `tests/bootstrap.php`**

```php
<?php
require_once __DIR__ . '/../includes/class-prompt-builder.php';
```

- [ ] **Step 4: Install dev deps**

Run: `composer install`
Expected: `vendor/bin/phpunit` exists.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/bootstrap.php
git commit -m "Add PHPUnit scaffolding for unit tests"
```

---

## Task 2: Prompt_Builder — failing test

**Files:**
- Create: `tests/test-prompt-builder.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
use PHPUnit\Framework\TestCase;
use CheckoutSummitDemo\Prompt_Builder;

class Prompt_Builder_Test extends TestCase {

    public function test_builds_prompt_from_template_with_substituted_subject() {
        $template_path = __DIR__ . '/fixtures/scene.json';
        if ( ! is_dir( __DIR__ . '/fixtures' ) ) {
            mkdir( __DIR__ . '/fixtures', 0777, true );
        }
        file_put_contents( $template_path, json_encode( array(
            'scene_id' => 'test_scene',
            '_note'    => 'ignored',
            'subject'  => array(
                'description' => '[VARIABLE] Replace with your product name',
                'placement'   => '[FIXED] centered on a table',
            ),
            'camera'   => array(
                'angle' => '[VARIABLE] front-facing OR 45-degree side – pick one per render',
                'lens'  => '[FIXED] 85mm equivalent',
            ),
            'negative_prompts' => '[FIXED] no people, no text',
        ) ) );

        $prompt = Prompt_Builder::from_template_file(
            $template_path,
            'Espresso Cup',
            'Small ceramic espresso cup, matte black.'
        );

        $this->assertStringContainsString( 'Espresso Cup', $prompt );
        $this->assertStringContainsString( 'Small ceramic espresso cup, matte black.', $prompt );
        $this->assertStringContainsString( 'centered on a table', $prompt );
        $this->assertStringContainsString( '85mm equivalent', $prompt );
        $this->assertStringNotContainsString( '[FIXED]', $prompt );
        $this->assertStringNotContainsString( '[VARIABLE]', $prompt );
        $this->assertStringContainsString( 'front-facing', $prompt );
        $this->assertStringNotContainsString( ' OR ', $prompt, 'should resolve to one camera angle' );
        $this->assertStringContainsString( 'Avoid:', $prompt );
        $this->assertStringContainsString( 'no people, no text', $prompt );
        $this->assertStringContainsString( 'Use the attached PNG', $prompt );
    }

    public function test_throws_when_template_missing() {
        $this->expectException( RuntimeException::class );
        Prompt_Builder::from_template_file( '/nonexistent/path.json', 'X', '' );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit`
Expected: FAIL — `Class "CheckoutSummitDemo\Prompt_Builder" not found`.

---

## Task 3: Prompt_Builder — implementation

**Files:**
- Create: `includes/class-prompt-builder.php`

- [ ] **Step 1: Implement `Prompt_Builder`**

```php
<?php
namespace CheckoutSummitDemo;

class Prompt_Builder {

    /**
     * Build a flattened text prompt from a scene-template JSON file.
     *
     * @param string $template_path Absolute path to JSON template.
     * @param string $product_title Product title (substituted into subject.description).
     * @param string $product_short_description Optional short description, appended after a dash.
     * @return string
     * @throws \RuntimeException If the file is missing or invalid JSON.
     */
    public static function from_template_file( $template_path, $product_title, $product_short_description = '' ) {
        if ( ! is_readable( $template_path ) ) {
            throw new \RuntimeException( "Scene template not readable: {$template_path}" );
        }
        $raw = file_get_contents( $template_path );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            throw new \RuntimeException( "Scene template is not valid JSON: {$template_path}" );
        }

        $subject_text = trim( $product_title );
        $short = trim( $product_short_description );
        if ( $short !== '' ) {
            $short = self::truncate( $short, 200 );
            $subject_text .= ' — ' . $short;
        }
        if ( isset( $data['subject']['description'] ) ) {
            $data['subject']['description'] = $subject_text;
        }

        $negative = '';
        if ( isset( $data['negative_prompts'] ) ) {
            $negative = self::strip_markers( (string) $data['negative_prompts'] );
            unset( $data['negative_prompts'] );
        }

        $sections = array();
        foreach ( $data as $key => $value ) {
            if ( $key === 'scene_id' || $key === '_note' ) {
                continue;
            }
            if ( ! is_array( $value ) ) {
                continue;
            }
            $sections[] = self::format_section( $key, $value );
        }

        $prompt = implode( "\n\n", $sections );
        if ( $negative !== '' ) {
            $prompt .= "\n\nAvoid: " . $negative;
        }
        $prompt .= "\n\nUse the attached PNG as the exact product to render in this scene.";

        return $prompt;
    }

    private static function format_section( $name, array $fields ) {
        $title = ucfirst( str_replace( '_', ' ', $name ) ) . ':';
        $lines = array( $title );
        foreach ( $fields as $field => $raw_value ) {
            $value   = self::strip_markers( (string) $raw_value );
            $value   = self::resolve_or_choice( $value );
            $lines[] = '  ' . $field . ': ' . $value;
        }
        return implode( "\n", $lines );
    }

    private static function strip_markers( $text ) {
        $text = preg_replace( '/\[(FIXED|VARIABLE)\]\s*/', '', $text );
        return trim( $text );
    }

    /**
     * Resolve "A OR B – pick one per render" style values to the first option.
     */
    private static function resolve_or_choice( $text ) {
        if ( stripos( $text, ' OR ' ) === false ) {
            return $text;
        }
        $without_tail = preg_replace( '/\s*[–-]\s*pick one.*$/i', '', $text );
        $parts        = preg_split( '/\s+OR\s+/i', $without_tail );
        return trim( $parts[0] );
    }

    private static function truncate( $text, $max ) {
        if ( strlen( $text ) <= $max ) {
            return $text;
        }
        return rtrim( substr( $text, 0, $max - 1 ) ) . '…';
    }
}
```

- [ ] **Step 2: Run tests**

Run: `vendor/bin/phpunit`
Expected: 2 tests, all PASS.

- [ ] **Step 3: Commit**

```bash
git add includes/class-prompt-builder.php tests/test-prompt-builder.php tests/fixtures
git commit -m "Add Prompt_Builder with unit tests"
```

---

## Task 4: Generator class

**Files:**
- Create: `includes/class-generator.php`

- [ ] **Step 1: Implement `Generator`**

This class is thin glue over WordPress + AI Client; we verify it via the manual smoke test in Task 8 rather than a unit test (mocking `wp_ai_client_prompt` and the entire media stack would be more code than the class itself).

```php
<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-prompt-builder.php';

class Generator {

    /** @var string[] Absolute paths to scene template JSONs. */
    private $templates;

    public function __construct( array $templates ) {
        $this->templates = $templates;
    }

    /**
     * Generate gallery images for one product from one source PNG attachment.
     *
     * @param int $product_id
     * @param int $source_attachment_id
     * @return int[] Newly created attachment IDs (in template order).
     * @throws \RuntimeException
     */
    public function generate_for_product( $product_id, $source_attachment_id ) {
        if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
            throw new \RuntimeException( 'WordPress 7.0 AI Client is not available (wp_ai_client_prompt missing).' );
        }

        $product = get_post( $product_id );
        if ( ! $product || $product->post_type !== 'product' ) {
            throw new \RuntimeException( 'Invalid product.' );
        }

        $source_path = get_attached_file( $source_attachment_id );
        if ( ! $source_path || ! is_readable( $source_path ) ) {
            throw new \RuntimeException( 'Source image is not readable.' );
        }

        $title = $product->post_title;
        $short = $product->post_excerpt;

        $new_attachment_ids = array();

        foreach ( $this->templates as $template_path ) {
            $prompt = Prompt_Builder::from_template_file( $template_path, $title, (string) $short );

            $image = \wp_ai_client_prompt( $prompt )
                ->withFile( $source_path, 'image/png' )
                ->generateImage();

            $attachment_id = $this->save_generated_image( $image, $product_id, basename( $template_path, '.json' ) );
            $new_attachment_ids[] = $attachment_id;
        }

        $this->append_to_gallery( $product_id, $new_attachment_ids );

        return $new_attachment_ids;
    }

    /**
     * Persist whatever the AI Client returned (file path, URL, or binary string) into the Media Library.
     */
    private function save_generated_image( $image, $product_id, $slug ) {
        $bytes = $this->extract_bytes( $image );
        if ( $bytes === '' ) {
            throw new \RuntimeException( 'AI Client returned empty image bytes.' );
        }

        $upload_dir = wp_upload_dir();
        $filename   = wp_unique_filename( $upload_dir['path'], 'csd-' . $slug . '-' . $product_id . '.png' );
        $full_path  = trailingslashit( $upload_dir['path'] ) . $filename;

        if ( file_put_contents( $full_path, $bytes ) === false ) {
            throw new \RuntimeException( 'Failed to write generated image to uploads dir.' );
        }

        $attachment = array(
            'post_mime_type' => 'image/png',
            'post_title'     => sanitize_text_field( get_the_title( $product_id ) . ' — ' . $slug ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $full_path, $product_id );
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            throw new \RuntimeException( 'wp_insert_attachment failed for generated image.' );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $attachment_id, $full_path );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return (int) $attachment_id;
    }

    /**
     * Accept whatever shape the AI Client returns: object with getBytes()/getPath()/getUrl(), a string path/URL, or raw bytes.
     */
    private function extract_bytes( $image ) {
        if ( is_object( $image ) ) {
            if ( method_exists( $image, 'getBytes' ) ) {
                return (string) $image->getBytes();
            }
            if ( method_exists( $image, 'getPath' ) ) {
                $p = $image->getPath();
                if ( $p && is_readable( $p ) ) {
                    return (string) file_get_contents( $p );
                }
            }
            if ( method_exists( $image, 'getUrl' ) ) {
                $url = $image->getUrl();
                if ( $url ) {
                    $resp = wp_remote_get( $url, array( 'timeout' => 60 ) );
                    if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                        return (string) wp_remote_retrieve_body( $resp );
                    }
                }
            }
            if ( method_exists( $image, '__toString' ) ) {
                return (string) $image;
            }
            throw new \RuntimeException( 'Unrecognised image object returned by AI Client: ' . get_class( $image ) );
        }
        if ( is_string( $image ) ) {
            if ( is_readable( $image ) ) {
                return (string) file_get_contents( $image );
            }
            if ( filter_var( $image, FILTER_VALIDATE_URL ) ) {
                $resp = wp_remote_get( $image, array( 'timeout' => 60 ) );
                if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                    return (string) wp_remote_retrieve_body( $resp );
                }
                throw new \RuntimeException( 'Failed to fetch generated image from URL.' );
            }
            return $image;
        }
        throw new \RuntimeException( 'Unsupported image return type from AI Client.' );
    }

    private function append_to_gallery( $product_id, array $new_ids ) {
        $existing     = get_post_meta( $product_id, '_product_image_gallery', true );
        $existing_ids = $existing ? array_filter( array_map( 'intval', explode( ',', $existing ) ) ) : array();
        $merged       = array_values( array_unique( array_merge( $existing_ids, array_map( 'intval', $new_ids ) ) ) );
        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $merged ) );
    }
}
```

- [ ] **Step 2: Lint check**

Run: `php -l includes/class-generator.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-generator.php
git commit -m "Add Generator: orchestrates AI Client call and gallery attachment"
```

---

## Task 5: AJAX handler

**Files:**
- Create: `includes/class-ajax.php`

- [ ] **Step 1: Implement `Ajax`**

```php
<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-generator.php';

class Ajax {

    const ACTION = 'csd_generate_gallery';

    public static function register() {
        add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
    }

    public static function handle() {
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Missing product_id.' ), 400 );
        }
        check_ajax_referer( self::ACTION . '_' . $product_id, 'nonce' );

        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to edit this product.' ), 403 );
        }

        if ( empty( $_FILES['source_image'] ) || empty( $_FILES['source_image']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => 'No source image uploaded.' ), 400 );
        }

        $check = wp_check_filetype( $_FILES['source_image']['name'] );
        if ( $check['type'] !== 'image/png' ) {
            wp_send_json_error( array( 'message' => 'Source image must be a PNG.' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $source_attachment_id = media_handle_upload( 'source_image', $product_id );
        if ( is_wp_error( $source_attachment_id ) ) {
            wp_send_json_error( array( 'message' => $source_attachment_id->get_error_message() ), 400 );
        }

        $templates = array(
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/living_room_front.json',
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/table_surface_side.json',
        );

        try {
            $generator = new Generator( $templates );
            $new_ids   = $generator->generate_for_product( $product_id, $source_attachment_id );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }

        $attachments = array();
        foreach ( $new_ids as $id ) {
            $attachments[] = array(
                'id'  => $id,
                'url' => wp_get_attachment_image_url( $id, 'medium' ),
            );
        }

        wp_send_json_success( array( 'attachments' => $attachments ) );
    }
}
```

- [ ] **Step 2: Lint**

Run: `php -l includes/class-ajax.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-ajax.php
git commit -m "Add Ajax handler for gallery generation"
```

---

## Task 6: Metabox + assets

**Files:**
- Create: `includes/class-metabox.php`
- Create: `assets/admin.js`
- Create: `assets/admin.css`

- [ ] **Step 1: Implement `Metabox`**

```php
<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Metabox {

    const ID = 'csd_ai_gallery_generator';

    public static function register() {
        add_action( 'add_meta_boxes_product', array( __CLASS__, 'add' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function add( $post ) {
        add_meta_box(
            self::ID,
            __( 'AI Gallery Generator', 'checkout-summit-demo' ),
            array( __CLASS__, 'render' ),
            'product',
            'side',
            'default'
        );
    }

    public static function render( $post ) {
        $nonce = wp_create_nonce( Ajax::ACTION . '_' . $post->ID );
        ?>
        <div class="csd-ai-gallery" data-product-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <p>
                <label for="csd-source-image"><?php esc_html_e( 'Source product PNG', 'checkout-summit-demo' ); ?></label><br>
                <input type="file" id="csd-source-image" accept="image/png">
            </p>
            <p>
                <button type="button" class="button button-primary" id="csd-generate-btn">
                    <?php esc_html_e( 'Generate gallery images', 'checkout-summit-demo' ); ?>
                </button>
            </p>
            <div class="csd-status" aria-live="polite"></div>
            <div class="csd-thumbs"></div>
        </div>
        <?php
    }

    public static function enqueue( $hook ) {
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'product' ) {
            return;
        }

        wp_enqueue_style(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.css',
            array(),
            CHECKOUT_SUMMIT_DEMO_VERSION
        );
        wp_enqueue_script(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.js',
            array(),
            CHECKOUT_SUMMIT_DEMO_VERSION,
            true
        );
        wp_localize_script( 'csd-admin', 'CSD_AI', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => Ajax::ACTION,
        ) );
    }
}
```

- [ ] **Step 2: Implement `assets/admin.js`** (uses textContent + replaceChildren — never innerHTML)

```javascript
(function () {
    const root = document.querySelector('.csd-ai-gallery');
    if (!root) return;

    const fileInput = root.querySelector('#csd-source-image');
    const button    = root.querySelector('#csd-generate-btn');
    const status    = root.querySelector('.csd-status');
    const thumbs    = root.querySelector('.csd-thumbs');

    button.addEventListener('click', async function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            status.textContent = 'Pick a PNG first.';
            return;
        }
        if (file.type !== 'image/png') {
            status.textContent = 'Only PNG files are supported.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Generating… this can take ~30 seconds.';
        thumbs.replaceChildren();

        const fd = new FormData();
        fd.append('action', window.CSD_AI.action);
        fd.append('product_id', root.dataset.productId);
        fd.append('nonce', root.dataset.nonce);
        fd.append('source_image', file);

        try {
            const res = await fetch(window.CSD_AI.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) {
                const msg = (json.data && json.data.message) ? json.data.message : 'unknown';
                status.textContent = 'Error: ' + msg;
                button.disabled = false;
                return;
            }
            status.textContent = 'Done. ' + json.data.attachments.length + ' image(s) added to the gallery.';
            json.data.attachments.forEach(function (a) {
                const img = document.createElement('img');
                img.src = a.url;
                img.alt = '';
                img.className = 'csd-thumb';
                thumbs.appendChild(img);
            });
        } catch (err) {
            status.textContent = 'Network error: ' + err.message;
        } finally {
            button.disabled = false;
        }
    });
})();
```

- [ ] **Step 3: Implement `assets/admin.css`**

```css
.csd-ai-gallery .csd-status { margin: 8px 0; font-style: italic; }
.csd-ai-gallery .csd-thumbs { display: flex; gap: 8px; flex-wrap: wrap; }
.csd-ai-gallery .csd-thumb  { max-width: 120px; height: auto; border: 1px solid #ddd; }
```

- [ ] **Step 4: Lint PHP**

Run: `php -l includes/class-metabox.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-metabox.php assets/admin.js assets/admin.css
git commit -m "Add metabox UI and admin assets"
```

---

## Task 7: Wire everything in the bootstrap

**Files:**
- Modify: `checkout-summit-demo.php`

- [ ] **Step 1: Replace the body of `checkout-summit-demo.php` (keep the plugin header)**

The existing file has a demo shortcode and admin page that we no longer need. Replace everything below the constants with the new wiring.

```php
<?php
/**
 * Plugin Name:       Checkout Summit Demo
 * Plugin URI:        https://kybernaut.cz/
 * Description:       A simple demo plugin showcasing WordPress plugin features for Checkout Summit Palermo.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Karolina Vyskocilova
 * Author URI:        https://kybernaut.cz/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       checkout-summit-demo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHECKOUT_SUMMIT_DEMO_VERSION', '0.1.0' );
define( 'CHECKOUT_SUMMIT_DEMO_FILE', __FILE__ );
define( 'CHECKOUT_SUMMIT_DEMO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHECKOUT_SUMMIT_DEMO_URL', plugin_dir_url( __FILE__ ) );

require_once CHECKOUT_SUMMIT_DEMO_DIR . 'includes/class-prompt-builder.php';
require_once CHECKOUT_SUMMIT_DEMO_DIR . 'includes/class-generator.php';
require_once CHECKOUT_SUMMIT_DEMO_DIR . 'includes/class-ajax.php';
require_once CHECKOUT_SUMMIT_DEMO_DIR . 'includes/class-metabox.php';

add_action( 'init', function () {
    \CheckoutSummitDemo\Metabox::register();
    \CheckoutSummitDemo\Ajax::register();
} );
```

- [ ] **Step 2: Lint**

Run: `php -l checkout-summit-demo.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Run unit tests one more time to make sure nothing regressed**

Run: `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add checkout-summit-demo.php
git commit -m "Wire metabox and AJAX handler in plugin bootstrap"
```

---

## Task 8: Manual smoke test in WordPress

This is the only end-to-end verification. The plugin can't be unit-tested against the real AI Client without standing up WP + a configured connector, so we verify on a real site.

- [ ] **Step 1: Activate the plugin in a WP 7.0 site with WooCommerce and a configured AI connector that supports image generation.**

- [ ] **Step 2: Open a WooCommerce product edit screen.**

Expected: "AI Gallery Generator" metabox appears in the sidebar.

- [ ] **Step 3: Pick a PNG and click "Generate gallery images".**

Expected: status reads "Generating…", then ~10–30s later switches to "Done. 2 image(s) added to the gallery." and two thumbnails appear.

- [ ] **Step 4: Save the product, reload, and confirm the two new images are present in the standard "Product gallery" panel.**

- [ ] **Step 5: Failure-path check — temporarily disable the AI connector (or test on WP < 7.0) and re-run.**

Expected: error message in the status area mentioning that the AI Client is not available. No silent fallback.

- [ ] **Step 6: Capability check — log in as a user without `edit_post` on that product and try the AJAX call from devtools.**

Expected: 403 with "You do not have permission…".

- [ ] **Step 7: If everything passes, tag the demo build (with explicit user approval before pushing).**

```bash
git tag -a demo-checkout-summit-palermo -m "Demo build for Checkout Summit Palermo"
```

---

## Self-review notes (for the engineer)

- Spec coverage: every spec section maps to a task — Prompt construction → Tasks 2–3, Generator/AI Client call → Task 4, AJAX (nonce/cap/upload) → Task 5, Metabox + JS → Task 6, bootstrap → Task 7, guardrails verified in Task 8.
- The spec said "delegate provider selection to WordPress" — Task 4's `wp_ai_client_prompt(...)->withFile(...)->generateImage()` deliberately does NOT call `->usingProvider()`.
- Type/method names used consistently: `Generator::generate_for_product`, `Ajax::ACTION`, `Prompt_Builder::from_template_file`, `_product_image_gallery` meta key.
- The `Generator::extract_bytes()` accommodates the three plausible return shapes from the AI Client (object with getters, file path string, URL string) because the upstream docs don't pin down a single shape yet — acceptable given the demo timeline; revisit when the API stabilizes.
- The admin JS uses `textContent` and `replaceChildren()` rather than `innerHTML` to avoid XSS risk, even though the only injected data are URLs we just minted server-side.
