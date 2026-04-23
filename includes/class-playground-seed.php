<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seeds the three handcrafted Sicilian-ceramic products on the first admin
 * request after boot, gated by the `csd_seed_products` option that the
 * Playground blueprint sets via setSiteOptions.
 *
 * We do it here instead of `wp-cli` because Playground passes the blueprint
 * through a shell-style tokenizer that strips single-quotes out of the
 * command string, mangling any non-trivial PHP one-liner. Running inside WP
 * avoids that layer entirely.
 */
class Playground_Seed {

    const TRIGGER_OPTION = 'csd_seed_products';
    const DONE_OPTION    = 'csd_seed_products_done';

    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ), 1 );
    }

    public static function maybe_seed() {
        if ( get_option( self::DONE_OPTION ) ) {
            return;
        }
        if ( ! get_option( self::TRIGGER_OPTION ) ) {
            return;
        }
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        require_once CHECKOUT_SUMMIT_DEMO_DIR . 'playground/seed-products.php';
        update_option( self::DONE_OPTION, time() );
    }
}
