<?php
namespace CheckoutSummitDemo;

class Prompt_Builder {

    /**
     * Build a flattened text prompt from a scene-template JSON file.
     *
     * @param string $template_path Absolute path to JSON template.
     * @param string $product_title Product title (substituted into subject.description).
     * @param string $product_short_description Optional short description, appended after a dash.
     * @return string
     * @throws \RuntimeException If the file is missing or invalid JSON.
     */
    public static function from_template_file( $template_path, $product_title, $product_short_description = '' ) {
        if ( ! is_readable( $template_path ) ) {
            throw new \RuntimeException( "Scene template not readable: {$template_path}" );
        }
        $raw = file_get_contents( $template_path );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            throw new \RuntimeException( "Scene template is not valid JSON: {$template_path}" );
        }

        $subject_text = trim( $product_title );
        $short = trim( $product_short_description );
        if ( $short !== '' ) {
            $short = self::truncate( $short, 200 );
            $subject_text .= ' — ' . $short;
        }
        if ( isset( $data['subject']['description'] ) ) {
            $data['subject']['description'] = $subject_text;
        }

        $negative = '';
        if ( isset( $data['negative_prompts'] ) ) {
            $negative = self::strip_markers( (string) $data['negative_prompts'] );
            unset( $data['negative_prompts'] );
        }

        $sections = array();
        foreach ( $data as $key => $value ) {
            if ( $key === 'scene_id' || $key === '_note' ) {
                continue;
            }
            if ( ! is_array( $value ) ) {
                continue;
            }
            $sections[] = self::format_section( $key, $value );
        }

        $prompt = implode( "\n\n", $sections );
        if ( $negative !== '' ) {
            $prompt .= "\n\nAvoid: " . $negative;
        }
        $prompt .= "\n\nUse the attached PNG as the exact product to render in this scene.";

        return $prompt;
    }

    private static function format_section( $name, array $fields ) {
        $title = ucfirst( str_replace( '_', ' ', $name ) ) . ':';
        $lines = array( $title );
        foreach ( $fields as $field => $raw_value ) {
            $value   = self::strip_markers( (string) $raw_value );
            $value   = self::resolve_or_choice( $value );
            $lines[] = '  ' . $field . ': ' . $value;
        }
        return implode( "\n", $lines );
    }

    private static function strip_markers( $text ) {
        $text = preg_replace( '/\[(FIXED|VARIABLE)\]\s*/', '', $text );
        return trim( $text );
    }

    /**
     * Resolve "A OR B – pick one per render" style values to the first option.
     */
    private static function resolve_or_choice( $text ) {
        if ( stripos( $text, ' OR ' ) === false ) {
            return $text;
        }
        $without_tail = preg_replace( '/\s*[–-]\s*pick one.*$/i', '', $text );
        $parts        = preg_split( '/\s+OR\s+/i', $without_tail );
        return trim( $parts[0] );
    }

    private static function truncate( $text, $max ) {
        if ( strlen( $text ) <= $max ) {
            return $text;
        }
        return rtrim( substr( $text, 0, $max - 1 ) ) . '…';
    }
}
