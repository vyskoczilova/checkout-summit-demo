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
