<?php
/**
 * Plugin Name: CSD Playground Bootstrap
 * Description: One-time Playground setup — skips the WC onboarding wizard and imports the beanie sample products.
 */

add_action( 'init', function () {
    if ( get_option( 'csd_playground_bootstrap_done' ) ) {
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Mark WC onboarding as complete so the setup wizard doesn't nag.
    update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );
    update_option( 'woocommerce_task_list_hidden', 'yes' );
    update_option( 'woocommerce_task_list_complete', 'yes' );
    update_option( 'woocommerce_admin_install_timestamp', time() );

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
