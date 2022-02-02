<?php

function bbc_upload_image($post = null, $src = null, $dimensions = null)
{

    $output_formats = ['jpg', 'webp'];

    if (!empty($src) && $dimensions[1] > 100)
    {

        if (preg_match('/^data:image\/(\w+);base64,/', $src[1], $type))
        {
            $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $src[1]));
            $type = strtolower($type[1]); // jpg, png, gif
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp']))
            {
                throw new \Exception('invalid image type');
            }
        }

        foreach($output_formats as $format) {
          $name = $post->post_name;
          $directory = "/" . date('Y') . "/" . date('m') . "/";
          $wp_upload_dir = wp_upload_dir();
          $filename = $name . ".$format";
          
          $fileurl_rel =  "../wp-content/uploads" . $directory . $filename;
          $filedir = $wp_upload_dir['basedir'] . $directory . $filename;
          
          $filetype = wp_check_filetype(basename($fileurl_rel) , null);
          file_put_contents($fileurl_rel, $data);
  
          $attachment = array(
            'guid' => $wp_upload_dir['url'] . '/' . basename($filedir) ,
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filedir)) ,
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
}

