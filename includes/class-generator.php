<?php
namespace CheckoutSummitDemo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-prompt-builder.php';

class Generator {

    /** @var string[] Absolute paths to scene template JSONs. */
    private $templates;

    public function __construct( array $templates ) {
        $this->templates = $templates;
    }

    /**
     * Generate gallery images for one product from one source PNG attachment.
     *
     * @param int $product_id
     * @param int $source_attachment_id
     * @return int[] Newly created attachment IDs (in template order).
     * @throws \RuntimeException
     */
    public function generate_for_product( $product_id, $source_attachment_id ) {
        if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
            throw new \RuntimeException( 'WordPress 7.0 AI Client is not available (wp_ai_client_prompt missing).' );
        }

        $product = get_post( $product_id );
        if ( ! $product || $product->post_type !== 'product' ) {
            throw new \RuntimeException( 'Invalid product.' );
        }

        $source_path = get_attached_file( $source_attachment_id );
        if ( ! $source_path || ! is_readable( $source_path ) ) {
            throw new \RuntimeException( 'Source image is not readable.' );
        }
        $source_mime = get_post_mime_type( $source_attachment_id );
        if ( ! $source_mime ) {
            $source_mime = 'image/png';
        }

        $title = $product->post_title;
        $short = $product->post_excerpt;

        $new_attachment_ids = array();

        foreach ( $this->templates as $template_path ) {
            $prompt = Prompt_Builder::from_template_file( $template_path, $title, (string) $short );

            $image = \wp_ai_client_prompt( $prompt )
                ->with_file( $source_path, $source_mime )
                ->generate_image();

            if ( is_wp_error( $image ) ) {
                throw new \RuntimeException( 'AI Client error: ' . $image->get_error_message() );
            }

            $attachment_id = $this->save_generated_image( $image, $product_id, basename( $template_path, '.json' ) );
            $new_attachment_ids[] = $attachment_id;
        }

        $this->append_to_gallery( $product_id, $new_attachment_ids );

        return $new_attachment_ids;
    }

    /**
     * Persist whatever the AI Client returned (file path, URL, or binary string) into the Media Library.
     */
    private function save_generated_image( $image, $product_id, $slug ) {
        $bytes = $this->extract_bytes( $image );
        if ( $bytes === '' ) {
            throw new \RuntimeException( 'AI Client returned empty image bytes.' );
        }

        $upload_dir = wp_upload_dir();
        $filename   = wp_unique_filename( $upload_dir['path'], 'csd-' . $slug . '-' . $product_id . '.png' );
        $full_path  = trailingslashit( $upload_dir['path'] ) . $filename;

        if ( file_put_contents( $full_path, $bytes ) === false ) {
            throw new \RuntimeException( 'Failed to write generated image to uploads dir.' );
        }

        $attachment = array(
            'post_mime_type' => 'image/png',
            'post_title'     => sanitize_text_field( get_the_title( $product_id ) . ' — ' . $slug ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $full_path, $product_id );
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            throw new \RuntimeException( 'wp_insert_attachment failed for generated image.' );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $attachment_id, $full_path );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return (int) $attachment_id;
    }

    /**
     * Accept whatever shape the AI Client returns: object with getBytes()/getPath()/getUrl(), a string path/URL, or raw bytes.
     */
    private function extract_bytes( $image ) {
        if ( is_object( $image ) ) {
            if ( method_exists( $image, 'getBase64Data' ) ) {
                $b64 = (string) $image->getBase64Data();
                if ( $b64 !== '' ) {
                    $decoded = base64_decode( $b64, true );
                    if ( $decoded !== false ) {
                        return $decoded;
                    }
                }
            }
            if ( method_exists( $image, 'getDataUri' ) ) {
                $uri = (string) $image->getDataUri();
                $bytes = self::decode_data_uri( $uri );
                if ( $bytes !== '' ) {
                    return $bytes;
                }
            }
            if ( method_exists( $image, 'getBytes' ) ) {
                return (string) $image->getBytes();
            }
            if ( method_exists( $image, 'getPath' ) ) {
                $p = $image->getPath();
                if ( $p && is_readable( $p ) ) {
                    return (string) file_get_contents( $p );
                }
            }
            if ( method_exists( $image, 'getUrl' ) ) {
                $url = $image->getUrl();
                if ( $url ) {
                    $resp = wp_remote_get( $url, array( 'timeout' => 60 ) );
                    if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                        return (string) wp_remote_retrieve_body( $resp );
                    }
                }
            }
            if ( method_exists( $image, '__toString' ) ) {
                $candidate = (string) $image;
                $bytes     = self::decode_data_uri( $candidate );
                if ( $bytes !== '' ) {
                    return $bytes;
                }
                if ( $candidate !== '' ) {
                    return $candidate;
                }
            }
            throw new \RuntimeException( 'Unrecognised image object returned by AI Client: ' . get_class( $image ) );
        }
        if ( is_string( $image ) ) {
            if ( is_readable( $image ) ) {
                return (string) file_get_contents( $image );
            }
            if ( filter_var( $image, FILTER_VALIDATE_URL ) ) {
                $resp = wp_remote_get( $image, array( 'timeout' => 60 ) );
                if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                    return (string) wp_remote_retrieve_body( $resp );
                }
                throw new \RuntimeException( 'Failed to fetch generated image from URL.' );
            }
            return $image;
        }
        throw new \RuntimeException( 'Unsupported image return type from AI Client.' );
    }

    private static function decode_data_uri( $candidate ) {
        if ( strpos( $candidate, 'data:' ) !== 0 || strpos( $candidate, ';base64,' ) === false ) {
            return '';
        }
        $b64 = substr( $candidate, strpos( $candidate, ',' ) + 1 );
        $decoded = base64_decode( $b64, true );
        return $decoded === false ? '' : $decoded;
    }

    private function append_to_gallery( $product_id, array $new_ids ) {
        $existing     = get_post_meta( $product_id, '_product_image_gallery', true );
        $existing_ids = $existing ? array_filter( array_map( 'intval', explode( ',', $existing ) ) ) : array();
        $merged       = array_values( array_unique( array_merge( $existing_ids, array_map( 'intval', $new_ids ) ) ) );
        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $merged ) );
    }
}
