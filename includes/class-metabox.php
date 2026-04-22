<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Metabox {

    const ID = 'csd_ai_gallery_generator';

    public static function register() {
        add_action( 'add_meta_boxes_product', array( __CLASS__, 'add' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function add( $post ) {
        add_meta_box(
            self::ID,
            __( 'AI Gallery Generator', 'checkout-summit-demo' ),
            array( __CLASS__, 'render' ),
            'product',
            'side',
            'default'
        );
    }

    public static function render( $post ) {
        $nonce = wp_create_nonce( Ajax::ACTION . '_' . $post->ID );
        ?>
        <div class="csd-ai-gallery" data-product-id="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <p>
                <label for="csd-source-image"><?php esc_html_e( 'Source product PNG', 'checkout-summit-demo' ); ?></label><br>
                <input type="file" id="csd-source-image" accept="image/png">
            </p>
            <p>
                <button type="button" class="button button-primary" id="csd-generate-btn">
                    <?php esc_html_e( 'Generate gallery images', 'checkout-summit-demo' ); ?>
                </button>
            </p>
            <div class="csd-status" aria-live="polite"></div>
            <div class="csd-thumbs"></div>
        </div>
        <?php
    }

    public static function enqueue( $hook ) {
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'product' ) {
            return;
        }

        wp_enqueue_style(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.css',
            array(),
            CHECKOUT_SUMMIT_DEMO_VERSION
        );
        wp_enqueue_script(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.js',
            array(),
            CHECKOUT_SUMMIT_DEMO_VERSION,
            true
        );
        wp_localize_script( 'csd-admin', 'CSD_AI', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => Ajax::ACTION,
        ) );
    }
}
