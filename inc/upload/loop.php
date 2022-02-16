<?php
    // Image Upload In Format Loop Handler 
    function bbc_upload_loop($post, $data) {

      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';

      $output_formats = ['jpg', 'webp'];
    
      $hash = bin2hex(random_bytes(2));
    
      foreach($output_formats as $format) {
        $name = $post->post_name;
        
        $directory = "/" . date('Y') . "/" . date('m') . "/";
        $wp_upload_dir = wp_upload_dir();
    
        $filename = $name . '-' . $hash  .  ".$format";
        
        $fileurl_rel =  "../wp-content/uploads" . $directory . $filename;
        $filedir = $wp_upload_dir['basedir'] . $directory . $filename;
        
        $filetype = wp_check_filetype(basename($fileurl_rel) , null);
    
        var_dump($filetype);
        
        file_put_contents($filedir, $data);
    
        $attachment = array(
          'guid' => $wp_upload_dir['url'] . '/' . basename($filedir) ,
          'post_mime_type' => $filetype['type'],
          'post_title' => $post->post_title,
          'post_content' => '',
          'post_status' => 'inherit'
        );
    
        $media = get_attached_media('image', $post->ID);
        if (!array_search('image/webp', $media))
        {
      
    
            if($format === 'jpg') {
              $attach_id = wp_insert_attachment($attachment, $filedir, $post->ID);
              $attach_data = wp_generate_attachment_metadata($attach_id, $filedir);
              wp_update_attachment_metadata($attach_id, $attach_data);
            }
            elseif($format === 'webp') {
    
              $attachments = get_attached_media('image', $post->ID);
              $attach_jpg_id = array_key_first($attachments);
    
              if($attach_jpg_id) {
                $attach_url = wp_get_attachment_image_url($attach_id, 'medium');
                $jpg = imagecreatefromjpeg($attach_url);
                $webp = imagewebp($jpg, $filedir, 100);
    
                $attach_id = wp_insert_attachment($attachment, $filedir, $post->ID);
                $attach_data = wp_generate_attachment_metadata($attach_id, $filedir);
                wp_update_attachment_metadata($attach_id, $attach_data);
              }
            }
        }
      }
    }

