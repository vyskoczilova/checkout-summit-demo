<?php
/**
 * Plugin Name: CSD Playground Bootstrap
 * Description: One-time Playground setup — skips the WC onboarding wizard and imports WooCommerce's bundled sample products.
 */

// Block the WC activation-redirect that would otherwise hijack the first page load.
add_action( 'plugins_loaded', function () {
    delete_transient( '_wc_activation_redirect' );
}, 1 );

/**
 * Append a line to a debug log so we can curl /wp-content/csd-debug.log
 * from the Playground iframe and see what happened.
 */
function csd_log( $msg ) {
    $line = '[' . gmdate( 'H:i:s' ) . '] ' . $msg . "\n";
    @file_put_contents( WP_CONTENT_DIR . '/csd-debug.log', $line, FILE_APPEND );
}

add_action( 'wp_loaded', function () {
    csd_log( 'wp_loaded fired' );

    if ( get_option( 'csd_playground_bootstrap_done' ) ) {
        csd_log( 'already done, skipping' );
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        csd_log( 'WooCommerce class missing' );
        return;
    }

    // Belt-and-braces onboarding skip (in addition to setSiteOptions in the blueprint).
    update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );

    $csv = WP_PLUGIN_DIR . '/woocommerce/sample-data/sample_products.csv';
    $importer_file = WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php';

    csd_log( 'csv exists: ' . ( file_exists( $csv ) ? 'yes' : 'no' ) . ' (' . $csv . ')' );
    csd_log( 'importer file exists: ' . ( file_exists( $importer_file ) ? 'yes' : 'no' ) );

    if ( ! is_readable( $csv ) || ! is_readable( $importer_file ) ) {
        csd_log( 'one of the required files is not readable, bailing' );
        return;
    }

    require_once $importer_file;

    if ( ! class_exists( 'WC_Product_CSV_Importer' ) ) {
        csd_log( 'WC_Product_CSV_Importer class still missing after require' );
        return;
    }

    $params = array(
        'parse'            => true,
        'update_existing'  => false,
        'delimiter'        => ',',
        'enclosure'        => '"',
        'escape'           => "\0",
        'prevent_timeouts' => true,
    );

    try {
        $importer = new WC_Product_CSV_Importer( $csv, $params );
        $result = $importer->import();
        $imported = isset( $result['imported'] ) ? count( $result['imported'] ) : 0;
        $failed = isset( $result['failed'] ) ? count( $result['failed'] ) : 0;
        $updated = isset( $result['updated'] ) ? count( $result['updated'] ) : 0;
        $skipped = isset( $result['skipped'] ) ? count( $result['skipped'] ) : 0;
        csd_log( "import done: imported=$imported updated=$updated skipped=$skipped failed=$failed" );
        if ( $failed > 0 ) {
            foreach ( $result['failed'] as $i => $err ) {
                csd_log( '  failed[' . $i . ']: ' . ( is_wp_error( $err ) ? $err->get_error_message() : print_r( $err, true ) ) );
                if ( $i > 5 ) break;
            }
        }
    } catch ( \Throwable $e ) {
        csd_log( 'EXCEPTION: ' . $e->getMessage() );
        return;
    }

    update_option( 'csd_playground_bootstrap_done', 1 );
    csd_log( 'bootstrap marked done' );
}, 99 );

// Show a dashboard notice with the log contents so the reviewer can see status.
add_action( 'admin_notices', function () {
    $log_file = WP_CONTENT_DIR . '/csd-debug.log';
    if ( ! file_exists( $log_file ) ) {
        return;
    }
    $log = @file_get_contents( $log_file );
    echo '<div class="notice notice-info"><p><strong>CSD bootstrap log:</strong></p><pre style="white-space:pre-wrap">' . esc_html( $log ) . '</pre></div>';
} );
