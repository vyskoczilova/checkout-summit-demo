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

    // Import the beanies.
    $csv = '/wordpress/wp-content/uploads/csd-import.csv';
    $importer_file = WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php';
    if ( is_readable( $csv ) && is_readable( $importer_file ) ) {
        require_once $importer_file;
        $importer = new WC_Product_CSV_Importer(
            $csv,
            array( 'parse' => true, 'update_existing' => false )
        );
        $importer->import();
    }

    update_option( 'csd_playground_bootstrap_done', 1 );
}, 20 );
