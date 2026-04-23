<?php
/**
 * Plugin Name: CSD Playground Bootstrap
 * Description: One-time Playground setup — skips the WC onboarding wizard and imports the beanie sample products.
 */

// Block the WC activation-redirect that would otherwise hijack the first page load.
add_action( 'plugins_loaded', function () {
	delete_transient( '_wc_activation_redirect' );
}, 1 );

add_action( 'init', function () {
	if ( get_option( 'csd_playground_bootstrap_done' ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Belt-and-braces onboarding skip (in addition to setSiteOptions in the blueprint).
	update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );

	// Mark the bootstrap as attempted *before* the import runs — a second,
	// cascading PHP fatal on a later request would otherwise keep re-triggering
	// the whole heavy path and mask the original error.
	update_option( 'csd_playground_bootstrap_done', 1 );

	try {
		csd_playground_import_products();
	} catch ( \Throwable $e ) {
		error_log( '[CSD] Bootstrap failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
	}
}, 20 );

function csd_playground_import_products() {
	// Playground's WP-Cron doesn't run on boot, so WooCommerce's scheduled
	// table creation never fires and ~14 tables are missing (woo#57703).
	// Force it synchronously before the importer touches lookup tables.
	if ( class_exists( 'WC_Install' ) ) {
		\WC_Install::install();
	}

	// The importer needs manage_product_terms; `init` fires as user 0.
	wp_set_current_user( 1 );

	$csv = '/wordpress/wp-content/uploads/csd-import.csv';
	if ( ! is_readable( $csv ) ) {
		return;
	}

	// WooCommerce's autoloader doesn't map the includes/import/ or
	// admin/importers/ subdirectories, so the concrete classes below cannot
	// resolve their parents via autoload — load the chain explicitly.
	$wc = WP_PLUGIN_DIR . '/woocommerce';
	$deps = array(
		$wc . '/includes/import/abstract-wc-product-importer.php',
		$wc . '/includes/import/class-wc-product-csv-importer.php',
		$wc . '/includes/admin/importers/class-wc-product-csv-importer-controller.php',
	);
	foreach ( $deps as $dep ) {
		if ( ! is_readable( $dep ) ) {
			error_log( '[CSD] Missing WC importer file: ' . $dep );
			return;
		}
		require_once $dep;
	}

	// wc_rest_upload_image_from_url() pulls in media_handle_sideload() and
	// friends, which live in wp-admin/includes and aren't loaded on a
	// frontend `init`.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Read the header row so we can auto-map CSV columns to product fields.
	// Without a mapping the importer silently produces zero products.
	$headers = array();
	$fh      = fopen( $csv, 'r' );
	if ( false !== $fh ) {
		$headers = fgetcsv( $fh );
		fclose( $fh );
	}
	if ( empty( $headers ) ) {
		return;
	}

	$controller = new \WC_Product_CSV_Importer_Controller();
	$mapping    = $controller->auto_map_columns( $headers, false );

	$importer = new \WC_Product_CSV_Importer(
		$csv,
		array(
			'parse'            => true,
			'mapping'          => $mapping,
			'update_existing'  => false,
			'delimiter'        => ',',
			'prevent_timeouts' => false,
			'lines'            => -1,
		)
	);
	$results = $importer->import();

	error_log( sprintf(
		'[CSD] CSV import: imported=%d updated=%d skipped=%d failed=%d',
		isset( $results['imported'] ) ? count( $results['imported'] ) : 0,
		isset( $results['updated'] )  ? count( $results['updated'] )  : 0,
		isset( $results['skipped'] )  ? count( $results['skipped'] )  : 0,
		isset( $results['failed'] )   ? count( $results['failed'] )   : 0
	) );
}
