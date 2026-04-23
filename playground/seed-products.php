<?php
/**
 * Playground seed script — creates 3 handcrafted WooCommerce products
 * (Pigna Siciliana, Testa di Moro, Piatto Trinacria) with realistic titles,
 * descriptions, prices, categories and featured images.
 *
 * Invoked from the Playground blueprint via:
 *   wp eval-file wp-content/plugins/checkout-summit-demo/playground/seed-products.php
 *
 * Idempotent: re-running skips products that already exist (matched by SKU).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    if ( class_exists( 'WP_CLI' ) ) {
        WP_CLI::error( 'WooCommerce is not active — cannot seed products.' );
    }
    return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$images_dir = __DIR__ . '/images/';

$products = array(
    array(
        'sku'        => 'CSD-PIGNA-30',
        'title'      => 'Pigna Siciliana in Ceramica di Caltagirone — 30 cm',
        'price'      => '58.00',
        'weight'     => '1.4',
        'dimensions' => array( 'length' => '20', 'width' => '20', 'height' => '30' ),
        'image'      => 'pigna-siciliana',
        'image_alt'  => 'Sicilian ceramic pigna on white background',
        'short'     => 'Handcrafted ceramic pinecone from Caltagirone — Sicily’s timeless symbol of prosperity, fertility and good fortune. 100% made and hand-painted in Sicily.',
        'long'      => <<<HTML
<p>The <strong>Pigna Siciliana</strong> is one of the oldest symbols of the Mediterranean: in the Sicilian tradition it stands for <em>prosperity, fertility, immortality and good luck</em>. A ceramic <em>pigna</em> placed on a balcony, a gate or on the dining table is believed to protect the home and bring abundance to everyone who crosses the threshold.</p>
<p>Each piece is shaped and painted by hand in the <strong>workshops of Caltagirone</strong>, following a ceramic tradition recognised by UNESCO as part of Italy’s intangible cultural heritage. The petals are modelled one by one and applied to the body before firing, so no two pigne are ever identical.</p>
<ul>
  <li><strong>Material:</strong> white-body terracotta, high-fired majolica glaze</li>
  <li><strong>Height:</strong> approx. 30 cm (11.8")</li>
  <li><strong>Finish:</strong> hand-painted in antique gold with green foliage accents</li>
  <li><strong>Origin:</strong> Caltagirone (CT), Sicily — 100% handmade</li>
  <li><strong>Care:</strong> indoor and outdoor use; wipe clean with a soft, dry cloth</li>
</ul>
<p>A ceramic pigna is the gift Sicilians traditionally give for a new home, a wedding, or the birth of a child. Beautifully packaged and ready to offer.</p>
HTML,
        'categories' => array( 'Ceramica Siciliana', 'Home Decor' ),
        'tags'       => array( 'Caltagirone', 'pigna', 'handmade', 'Sicily' ),
    ),
    array(
        'sku'        => 'CSD-TDM-35',
        'title'      => 'Testa di Moro — Vaso in Ceramica di Caltagirone (Lui)',
        'price'      => '145.00',
        'weight'     => '3.2',
        'dimensions' => array( 'length' => '25', 'width' => '25', 'height' => '35' ),
        'image'      => 'testa-di-moro',
        'image_alt'  => 'Sicilian Testa di Moro ceramic head vase on white background',
        'short'     => 'Iconic Moor’s head ceramic vase, hand-painted in Caltagirone. Inspired by the legend of the Kalsa quarter in Palermo — a statement centrepiece for home, terrace or garden.',
        'long'      => <<<HTML
<p>The <strong>Testa di Moro</strong> is the most recognisable object of Sicilian ceramic tradition. Legend tells of a young Moor who, during the Arab rule of Palermo in the 11th century, fell in love with a Sicilian girl of the <em>Kalsa</em> quarter. When she discovered he had a wife and children waiting for him back home, she beheaded him in her rage and planted basil inside his head. The basil grew so lush that neighbours asked for their own ceramic copies — and the <em>teste di moro</em> were born.</p>
<p>This piece is the <strong>male half</strong> of the traditional pair (<em>lui e lei</em>). It is hand-thrown and hand-painted in <strong>Caltagirone</strong>, with a richly decorated turban inspired by Arab-Norman mosaics, a gilded crown and deep cobalt blue majolica glaze.</p>
<ul>
  <li><strong>Material:</strong> terracotta, tin-glazed majolica, 22kt gold lustre details</li>
  <li><strong>Height:</strong> approx. 35 cm (13.8") — also available as a matching pair</li>
  <li><strong>Finish:</strong> cobalt blue turban, gilded crown, hand-painted face</li>
  <li><strong>Origin:</strong> Caltagirone (CT), Sicily — 100% handmade</li>
  <li><strong>Use:</strong> decorative vase for fresh or dried flowers; perfect for basil, as per tradition</li>
</ul>
<p>Each Testa di Moro is signed and numbered by the <em>maestro ceramista</em>. Shipped with protective wooden crating.</p>
HTML,
        'categories' => array( 'Ceramica Siciliana', 'Home Decor' ),
        'tags'       => array( 'Caltagirone', 'testa di moro', 'handmade', 'Sicily' ),
    ),
    array(
        'sku'        => 'CSD-TRIN-35',
        'title'      => 'Piatto Trinacria — Ceramica Siciliana Decorativa 35 cm',
        'price'      => '85.00',
        'weight'     => '1.8',
        'dimensions' => array( 'length' => '35', 'width' => '35', 'height' => '4' ),
        'image'      => 'trinacria',
        'image_alt'  => 'Sicilian Trinacria decorative ceramic plate on white background',
        'short'     => 'Decorative Sicilian plate featuring the Trinacria — the three-legged triskelion at the heart of the Sicilian flag, crowned by Medusa and the golden ears of wheat.',
        'long'      => <<<HTML
<p>The <strong>Trinacria</strong> is the oldest known symbol of Sicily. Its three bent legs represent the three capes of the island — <em>Capo Peloro</em> to the north-east, <em>Capo Passero</em> to the south, and <em>Capo Lilibeo</em> to the west — radiating from a central head of Medusa, a protective talisman crowned by the <strong>golden ears of wheat</strong> that stand for the fertility of the Sicilian land.</p>
<p>This decorative plate is entirely <strong>hand-turned and hand-painted in Caltagirone</strong>, where ceramic has been produced continuously since the Arab period. The majolica glaze gives the piece its signature luminous depth.</p>
<ul>
  <li><strong>Material:</strong> white-body terracotta, tin-glazed majolica</li>
  <li><strong>Diameter:</strong> approx. 35 cm (13.8")</li>
  <li><strong>Finish:</strong> hand-painted with antique gold, cobalt blue rim and ochre centre</li>
  <li><strong>Origin:</strong> Caltagirone (CT), Sicily — 100% handmade</li>
  <li><strong>Use:</strong> wall plate, table centrepiece or display stand (stand not included)</li>
</ul>
<p>Every plate is a one-of-a-kind piece, signed by the artisan on the reverse.</p>
HTML,
        'categories' => array( 'Ceramica Siciliana', 'Home Decor' ),
        'tags'       => array( 'Caltagirone', 'trinacria', 'handmade', 'Sicily' ),
    ),
);

$upload_path = wp_upload_dir()['path'];

$attach_featured_image = static function ( $product_id, $image_base, $title, $alt ) use ( $upload_path, $images_dir ) {
    $matches = glob( $images_dir . $image_base . '.*' );
    if ( ! $matches ) {
        return;
    }
    $src      = $matches[0];
    $filename = basename( $src );
    $dest     = trailingslashit( $upload_path ) . wp_unique_filename( $upload_path, $filename );
    if ( ! copy( $src, $dest ) ) {
        return;
    }
    $filetype = wp_check_filetype( $filename );
    $mime     = $filetype['type'] ?: 'application/octet-stream';
    $attach_id = wp_insert_attachment(
        array(
            'post_mime_type' => $mime,
            'post_title'     => sanitize_text_field( $title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_excerpt'   => $alt,
        ),
        $dest,
        $product_id
    );
    if ( ! $attach_id || is_wp_error( $attach_id ) ) {
        return;
    }
    if ( $mime !== 'image/svg+xml' ) {
        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $dest ) );
    }
    update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
    set_post_thumbnail( $product_id, $attach_id );
};

$created = 0;
$skipped = 0;

foreach ( $products as $p ) {
    $existing_id = wc_get_product_id_by_sku( $p['sku'] );
    if ( $existing_id ) {
        $skipped++;
        if ( class_exists( 'WP_CLI' ) ) {
            WP_CLI::log( "Skipped (already exists): {$p['title']} [#{$existing_id}]" );
        }
        continue;
    }

    $product = new WC_Product_Simple();
    $product->set_name( $p['title'] );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_regular_price( $p['price'] );
    $product->set_description( $p['long'] );
    $product->set_short_description( $p['short'] );
    $product->set_sku( $p['sku'] );
    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );

    if ( ! empty( $p['weight'] ) ) {
        $product->set_weight( $p['weight'] );
    }
    if ( ! empty( $p['dimensions'] ) ) {
        $product->set_length( $p['dimensions']['length'] );
        $product->set_width( $p['dimensions']['width'] );
        $product->set_height( $p['dimensions']['height'] );
    }

    $product_id = $product->save();

    if ( ! empty( $p['categories'] ) ) {
        wp_set_object_terms( $product_id, $p['categories'], 'product_cat' );
    }
    if ( ! empty( $p['tags'] ) ) {
        wp_set_object_terms( $product_id, $p['tags'], 'product_tag' );
    }

    $attach_featured_image( $product_id, $p['image'], $p['title'], $p['image_alt'] );

    $created++;
    if ( class_exists( 'WP_CLI' ) ) {
        WP_CLI::log( "Created: {$p['title']} [#{$product_id}] — €{$p['price']}" );
    }
}

if ( class_exists( 'WP_CLI' ) ) {
    WP_CLI::success( "Seeded {$created} product(s), skipped {$skipped}." );
}
