<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-generator.php';

class Ajax {

    const ACTION = 'csd_generate_gallery';

    public static function register() {
        add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
    }

    public static function handle() {
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Missing product_id.' ), 400 );
        }
        check_ajax_referer( self::ACTION . '_' . $product_id, 'nonce' );

        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to edit this product.' ), 403 );
        }

        if ( empty( $_FILES['source_image'] ) || empty( $_FILES['source_image']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => 'No source image uploaded.' ), 400 );
        }

        $check = wp_check_filetype_and_ext(
            $_FILES['source_image']['tmp_name'],
            $_FILES['source_image']['name']
        );
        if ( $check['type'] !== 'image/png' ) {
            wp_send_json_error( array( 'message' => 'Source image must be a PNG.' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $source_attachment_id = media_handle_upload( 'source_image', $product_id );
        if ( is_wp_error( $source_attachment_id ) ) {
            wp_send_json_error( array( 'message' => $source_attachment_id->get_error_message() ), 400 );
        }

        $templates = array(
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/living_room_front.json',
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/table_surface_side.json',
        );

        try {
            $generator = new Generator( $templates );
            $new_ids   = $generator->generate_for_product( $product_id, $source_attachment_id );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }

        $attachments = array();
        foreach ( $new_ids as $id ) {
            $attachments[] = array(
                'id'  => $id,
                'url' => wp_get_attachment_image_url( $id, 'medium' ),
            );
        }

        wp_send_json_success( array( 'attachments' => $attachments ) );
    }
}
