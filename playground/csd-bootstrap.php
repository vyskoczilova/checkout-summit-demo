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

	// Playground's WP-Cron doesn't run on boot, so WooCommerce's scheduled
	// table creation never fires and ~14 tables are missing (woo#57703).
	// Force it synchronously before the importer touches lookup tables.
	if ( class_exists( 'WC_Install' ) ) {
		\WC_Install::install();
	}

	// The importer needs manage_product_terms; `init` fires as user 0.
	wp_set_current_user( 1 );

	$csv              = '/wordpress/wp-content/uploads/csd-import.csv';
	$importer_file    = WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php';
	$controller_file  = WP_PLUGIN_DIR . '/woocommerce/includes/admin/importers/class-wc-product-csv-importer-controller.php';

	if ( ! is_readable( $csv ) || ! is_readable( $importer_file ) || ! is_readable( $controller_file ) ) {
		return;
	}

	require_once $importer_file;
	require_once $controller_file;

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
			'parse'             => true,
			'mapping'           => $mapping,
			'update_existing'   => false,
			'delimiter'         => ',',
			'prevent_timeouts'  => false,
			'lines'             => -1,
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

	// Action Scheduler jobs don't drain on their own inside a Blueprint boot,
	// which leaves lookup tables and some image sideloads half-done. Force it.
	if ( function_exists( 'as_run_queue' ) ) {
		as_run_queue();
	}

	update_option( 'csd_playground_bootstrap_done', 1 );
}, 20 );
