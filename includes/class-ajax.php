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

        $source_id = isset( $_POST['source_attachment_id'] ) ? absint( $_POST['source_attachment_id'] ) : 0;
        if ( ! $source_id ) {
            $source_id = (int) get_post_thumbnail_id( $product_id );
        }

        if ( ! $source_id ) {
            wp_send_json_error( array( 'message' => 'Pick a PNG from the media library, or set a featured image first.' ), 400 );
        }

        $attachment = get_post( $source_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            wp_send_json_error( array( 'message' => 'Selected source is not a media attachment.' ), 400 );
        }
        if ( get_post_mime_type( $source_id ) !== 'image/png' ) {
            wp_send_json_error( array( 'message' => 'Source image must be a PNG.' ), 400 );
        }

        $templates = array(
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/living_room_front.json',
            CHECKOUT_SUMMIT_DEMO_DIR . 'json/table_surface_side.json',
        );

        try {
            $generator = new Generator( $templates );
            $new_ids   = $generator->generate_for_product( $product_id, $source_id );
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
