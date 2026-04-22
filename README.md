# Checkout Summit Demo

A small WordPress plugin built for the Checkout Summit in Palermo. It adds an
"AI Gallery Generator" metabox to WooCommerce product edit screens: drop a
product PNG, click one button, get two AI-generated lifestyle images attached to
the product gallery.

Image generation is delegated to **WordPress 7.0's AI Client**
(`wp_ai_client_prompt()`) — the plugin doesn't talk to any AI provider directly.
Whichever connector you've configured in core handles the call.

## Requirements

- WordPress **7.0+** with the AI Client available
- An AI connector configured under **Settings → AI Connectors** that supports
  image generation
- WooCommerce active
- PHP 7.4+

## Install

```bash
cd wp-content/plugins
git clone <repo-url> checkout-summit-demo
```

Activate **Checkout Summit Demo** in **Plugins**.

## Use

1. Open any WooCommerce product edit screen.
2. In the sidebar, find the **AI Gallery Generator** metabox.
3. Pick a PNG of the product.
4. Click **Generate gallery images**. ~10–30s later, two thumbnails appear and
   the images are attached to the product's gallery.
5. Save the product as usual.

## How it works

```
checkout-summit-demo.php          bootstrap
includes/
  class-prompt-builder.php        loads JSON template, substitutes product, flattens to text
  class-generator.php             calls wp_ai_client_prompt()->withFile()->generateImage()
  class-ajax.php                  admin-ajax handler (nonce + cap + upload)
  class-metabox.php               metabox + asset enqueue
assets/admin.{js,css}             tiny vanilla-JS UI
json/*.json                       scene templates (one per output image)
```

Add another scene by dropping a JSON file into `json/` and listing it in
`includes/class-ajax.php` (the `$templates` array).

## Tests

```bash
composer install
vendor/bin/phpunit
```

Only the pure `Prompt_Builder` is unit-tested — the rest is thin glue over
WordPress and the AI Client and is verified manually in a real WP install.

## License

GPL-2.0-or-later. Author: Karolina Vyskočilová · <https://kybernaut.cz/>
