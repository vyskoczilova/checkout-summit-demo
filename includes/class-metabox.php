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
                <button type="button" class="button" id="csd-pick-source">
                    <?php esc_html_e( 'Choose source image', 'checkout-summit-demo' ); ?>
                </button>
                <button type="button" class="button-link csd-clear-source" id="csd-clear-source" hidden>
                    <?php esc_html_e( 'Clear', 'checkout-summit-demo' ); ?>
                </button>
            </p>
            <input type="hidden" id="csd-source-id" value="">
            <div class="csd-source-preview"></div>
            <p class="description">
                <?php esc_html_e( 'PNG only. If no image is chosen, the product\'s featured image will be used.', 'checkout-summit-demo' ); ?>
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

        wp_enqueue_media();

        wp_enqueue_style(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.css',
            array(),
            CHECKOUT_SUMMIT_DEMO_VERSION
        );
        wp_enqueue_script(
            'csd-admin',
            CHECKOUT_SUMMIT_DEMO_URL . 'assets/admin.js',
            array( 'jquery' ),
            CHECKOUT_SUMMIT_DEMO_VERSION,
            true
        );
        wp_localize_script( 'csd-admin', 'CSD_AI', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'action'         => Ajax::ACTION,
            'mediaTitle'     => __( 'Choose a source PNG', 'checkout-summit-demo' ),
            'mediaButton'    => __( 'Use this image', 'checkout-summit-demo' ),
        ) );
    }
}
