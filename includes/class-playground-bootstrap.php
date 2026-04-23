<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Playground-only one-shot bootstrap.
 *
 * Runs once per install when we detect we're on WordPress Playground:
 * - Skips the WooCommerce onboarding wizard
 * - Imports the bundled sample products CSV from the WooCommerce plugin
 *
 * No-op outside Playground.
 */
class Playground_Bootstrap {

    const DONE_OPTION = 'csd_playground_bootstrap_done';
    const LOG_BASENAME = 'csd-playground-debug.log';

    public static function register() {
        add_action( 'plugins_loaded', array( __CLASS__, 'block_wc_activation_redirect' ), 1 );
        add_action( 'wp_loaded', array( __CLASS__, 'maybe_run' ), 99 );
        add_action( 'admin_notices', array( __CLASS__, 'show_log_notice' ) );
    }

    public static function is_playground() {
        return defined( 'PLAYGROUND_BLUEPRINT_RUNNER' )
            || isset( $_SERVER['HTTP_HOST'] ) && strpos( (string) $_SERVER['HTTP_HOST'], 'playground.wordpress.net' ) !== false
            || ( defined( 'WP_HOME' ) && strpos( (string) WP_HOME, 'playground.wordpress.net' ) !== false )
            || file_exists( '/wordpress/wp-config.php' );
    }

    public static function block_wc_activation_redirect() {
        if ( ! self::is_playground() ) {
            return;
        }
        delete_transient( '_wc_activation_redirect' );
    }

    private static function log( $msg ) {
        $line = '[' . gmdate( 'H:i:s' ) . '] ' . $msg . "\n";
        @file_put_contents( WP_CONTENT_DIR . '/' . self::LOG_BASENAME, $line, FILE_APPEND );
    }

    public static function maybe_run() {
        if ( ! self::is_playground() ) {
            return;
        }
        self::log( 'maybe_run fired' );

        if ( get_option( self::DONE_OPTION ) ) {
            self::log( 'already done, skipping' );
            return;
        }
        if ( ! class_exists( 'WooCommerce' ) ) {
            self::log( 'WooCommerce class missing' );
            return;
        }

        // Belt-and-braces onboarding skip.
        update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );
        update_option( 'woocommerce_task_list_hidden', 'yes' );
        update_option( 'woocommerce_extended_task_list_hidden', 'yes' );

        $csv = WP_PLUGIN_DIR . '/woocommerce/sample-data/sample_products.csv';
        $importer_file = WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php';

        self::log( 'csv exists: ' . ( file_exists( $csv ) ? 'yes' : 'NO' ) . ' (' . $csv . ')' );
        self::log( 'importer file exists: ' . ( file_exists( $importer_file ) ? 'yes' : 'NO' ) );

        if ( ! is_readable( $csv ) || ! is_readable( $importer_file ) ) {
            self::log( 'required file not readable, bailing' );
            return;
        }

        require_once $importer_file;

        if ( ! class_exists( 'WC_Product_CSV_Importer' ) ) {
            self::log( 'WC_Product_CSV_Importer class still missing after require' );
            return;
        }

        try {
            $importer = new \WC_Product_CSV_Importer( $csv, array(
                'parse'            => true,
                'update_existing'  => false,
                'delimiter'        => ',',
                'enclosure'        => '"',
                'escape'           => "\0",
                'prevent_timeouts' => true,
            ) );
            $result = $importer->import();
            $imported = isset( $result['imported'] ) ? count( $result['imported'] ) : 0;
            $failed   = isset( $result['failed'] ) ? count( $result['failed'] ) : 0;
            $updated  = isset( $result['updated'] ) ? count( $result['updated'] ) : 0;
            $skipped  = isset( $result['skipped'] ) ? count( $result['skipped'] ) : 0;
            self::log( "import done: imported=$imported updated=$updated skipped=$skipped failed=$failed" );
        } catch ( \Throwable $e ) {
            self::log( 'EXCEPTION: ' . $e->getMessage() );
            return;
        }

        update_option( self::DONE_OPTION, 1 );
        self::log( 'bootstrap marked done' );
    }

    public static function show_log_notice() {
        if ( ! self::is_playground() ) {
            return;
        }
        $log_file = WP_CONTENT_DIR . '/' . self::LOG_BASENAME;
        if ( ! file_exists( $log_file ) ) {
            echo '<div class="notice notice-warning"><p><strong>CSD bootstrap:</strong> log file not found at ' . esc_html( $log_file ) . '</p></div>';
            return;
        }
        $log = @file_get_contents( $log_file );
        echo '<div class="notice notice-info"><p><strong>CSD Playground bootstrap log:</strong></p><pre style="white-space:pre-wrap;max-height:200px;overflow:auto">' . esc_html( $log ) . '</pre></div>';
    }
}
