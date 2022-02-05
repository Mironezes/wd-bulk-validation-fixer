<?php 

require_once(__DIR__ . '/../helpers.php');

// #NOT IN USE, REWORK
// Runs validation/upload/attch filters during All In One WP Import event
function wdbvf_on_save_post_validation_fix( $post_id, $xml, $is_update ) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
  
    $post = get_post($post_id);

    // Logic from main file

}
// add_action( 'pmxi_saved_post', 'wdbvf_on_save_post_validation_fix', 10, 3 );
