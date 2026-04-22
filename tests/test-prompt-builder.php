<?php
use PHPUnit\Framework\TestCase;
use CheckoutSummitDemo\Prompt_Builder;

class Prompt_Builder_Test extends TestCase {

    public function test_builds_prompt_from_template_with_substituted_subject() {
        $template_path = __DIR__ . '/fixtures/scene.json';
        if ( ! is_dir( __DIR__ . '/fixtures' ) ) {
            mkdir( __DIR__ . '/fixtures', 0777, true );
        }
        file_put_contents( $template_path, json_encode( array(
            'scene_id' => 'test_scene',
            '_note'    => 'ignored',
            'subject'  => array(
                'description' => '[VARIABLE] Replace with your product name',
                'placement'   => '[FIXED] centered on a table',
            ),
            'camera'   => array(
                'angle' => '[VARIABLE] front-facing OR 45-degree side – pick one per render',
                'lens'  => '[FIXED] 85mm equivalent',
            ),
            'negative_prompts' => '[FIXED] no people, no text',
        ) ) );

        $prompt = Prompt_Builder::from_template_file(
            $template_path,
            'Espresso Cup',
            'Small ceramic espresso cup, matte black.'
        );

        $this->assertStringContainsString( 'Espresso Cup', $prompt );
        $this->assertStringContainsString( 'Small ceramic espresso cup, matte black.', $prompt );
        $this->assertStringContainsString( 'centered on a table', $prompt );
        $this->assertStringContainsString( '85mm equivalent', $prompt );
        $this->assertStringNotContainsString( '[FIXED]', $prompt );
        $this->assertStringNotContainsString( '[VARIABLE]', $prompt );
        $this->assertStringContainsString( 'front-facing', $prompt );
        $this->assertStringNotContainsString( ' OR ', $prompt, 'should resolve to one camera angle' );
        $this->assertStringContainsString( 'Avoid:', $prompt );
        $this->assertStringContainsString( 'no people, no text', $prompt );
        $this->assertStringContainsString( 'Use the attached PNG', $prompt );
    }

    public function test_throws_when_template_missing() {
        $this->expectException( RuntimeException::class );
        Prompt_Builder::from_template_file( '/nonexistent/path.json', 'X', '' );
    }
}
