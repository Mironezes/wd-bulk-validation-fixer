<?php

function wdbvf_on_insert_post_handler( $post_id, $post, $update ) {

    if( get_option('wdbvf_auto_apply_on_publication') === '1' ) {

      $filtered_content_stage1 = bbc_regex_post_content_filters($post->post_content);
      $filtered_content_stage2 = bbc_set_image_dimension($filtered_content_stage1);
      $filtered_content_stage3 = bbc_alt_singlepage_autocomplete($filtered_content_stage2, $post);
      $filtered_content_stage4 = bbc_fix_headings($filtered_content_stage3);
  
      $excerpt = bbc_set_excerpt($filtered_content_stage4);
  
        if(mb_strlen($filtered_content_stage4) <= 10) {
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
            
                remove_action( 'wp_insert_post', 'wdbvf_on_insert_post_handler' );
                $args = array(
                  'ID' => $post_id,
                  'post_status' => 'draft',
                  'tags_input' => 'no_content'  
                );
                wp_update_post($args);				
                add_action( 'wp_insert_post', 'wdbvf_on_insert_post_handler', 12, 3 );
        }
        else {
                remove_action( 'wp_insert_post', 'wdbvf_on_insert_post_handler' );
                $args = array(
                  'ID' => $post_id,
                  'post_content' => $filtered_content_stage4,
                  'post_excerpt' => $excerpt,
                );
                wp_update_post($args);
  
                if(!has_post_thumbnail($post)) {
                  bbc_attach_first_image($post);
                }		
                add_action( 'wp_insert_post', 'wdbvf_on_insert_post_handler', 12, 3 );
        } 
      } 
}
add_action( 'wp_insert_post ', 'wdbvf_on_insert_post_handler', 10, 3 );


