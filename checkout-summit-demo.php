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

/**
 * Register the [checkout_summit_demo] shortcode.
 */
function checkout_summit_demo_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'title' => __( 'Checkout Summit Palermo', 'checkout-summit-demo' ),
		),
		$atts,
		'checkout_summit_demo'
	);

	return sprintf(
		'<div class="checkout-summit-demo"><h3>%s</h3><p>%s</p></div>',
		esc_html( $atts['title'] ),
		esc_html__( 'Hello from a simple WordPress plugin demo!', 'checkout-summit-demo' )
	);
}
add_shortcode( 'checkout_summit_demo', 'checkout_summit_demo_shortcode' );

/**
 * Add an admin menu entry for the demo.
 */
function checkout_summit_demo_admin_menu() {
	add_menu_page(
		__( 'Checkout Summit Demo', 'checkout-summit-demo' ),
		__( 'Summit Demo', 'checkout-summit-demo' ),
		'manage_options',
		'checkout-summit-demo',
		'checkout_summit_demo_admin_page',
		'dashicons-megaphone',
		80
	);
}
add_action( 'admin_menu', 'checkout_summit_demo_admin_menu' );

/**
 * Render the admin page.
 */
function checkout_summit_demo_admin_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Checkout Summit Demo', 'checkout-summit-demo' ); ?></h1>
		<p><?php esc_html_e( 'A simple plugin built for the Checkout Summit in Palermo.', 'checkout-summit-demo' ); ?></p>
		<p>
			<?php
			printf(
				/* translators: %s: shortcode tag */
				esc_html__( 'Use the %s shortcode to display the demo block on any page.', 'checkout-summit-demo' ),
				'<code>[checkout_summit_demo]</code>'
			);
			?>
		</p>
	</div>
	<?php
}
