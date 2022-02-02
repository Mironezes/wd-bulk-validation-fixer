<?php
    // Image Upload In Format Loop Handler 
    function bbc_upload_loop($post, $data) {
      $output_formats = ['jpg', 'webp'];

      foreach($output_formats as $format) {
        $name = $post->post_name;
        
        $directory = "/" . date('Y') . "/" . date('m') . "/";
        $wp_upload_dir = wp_upload_dir();

        $filename = $name . '-' .  bin2hex(random_bytes(2)) .  ".$format";
        
        $fileurl_rel =  "../wp-content/uploads" . $directory . $filename;
        $filedir = $wp_upload_dir['basedir'] . $directory . $filename;
        
        $filetype = wp_check_filetype(basename($fileurl_rel) , null);
        file_put_contents($fileurl_rel, $data);

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
                $webp = imagewebp($jpg, $filedir, 80);

                $attach_id = wp_insert_attachment($attachment, $filedir, $post->ID);
                $attach_data = wp_generate_attachment_metadata($attach_id, $filedir);
                wp_update_attachment_metadata($attach_id, $attach_data);
              }
            }
        }
      }
    }

