<?php 

require_once(__DIR__ . '/../helpers.php');

// #NOT IN USE, REWORK
// Runs validation/upload/attch filters during All In One WP Import event
function wdbvf_on_save_post_validation_fix( $post_id, $xml, $is_update ) {
    $post = get_post($post_id);

    $filtered_content_stage1 = bbc_regex_post_content_filters($post->post_content);
    $filtered_content_stage2 = bbc_upload_images($filtered_content_stage1, $post);
    $filtered_content_stage3 = bbc_alt_singlepage_autocomplete($filtered_content_stage2, $post);
    $filtered_content_stage4 = bbc_fix_headings($filtered_content_stage3);

		$excerpt = bbc_set_excerpt($filtered_content_stage4);

      if(mb_strlen($filtered_content_stage4) <= 1) {
              $url = '/'.$post->post_name.'/';
            
              if(get_option('wdss_410s_dictionary')) {
                $values_arr = get_option('wdss_410s_dictionary');
            
                array_push($values_arr, $url);
                $values_arr = array_unique($values_arr);
                update_option('wdss_410s_dictionary', $values_arr);
              }
              else {
                $values_arr = [];
                array_push($values_arr, $url);
                update_option('wdss_410s_dictionary', $values_arr);
              }
          
              $args = array(
                'ID' => $post_id,
                'post_status' => 'draft',
                'tags_input' => 'no_content'  
              );
              wp_update_post($args);				
      }
      elseif(mb_strlen($filtered_content_stage4) > 50) {
              $args = array(
                'ID' => $post_id,
                'post_content' => $filtered_content_stage4,
								'post_excerpt' => $excerpt,
              );
              wp_update_post($args);
  
              if(!has_post_thumbnail($post)) {
                bbc_attach_first_image($post);
              }		
      }
}
// add_action( 'pmxi_saved_post', 'wdbvf_on_save_post_validation_fix', 10, 3 );
