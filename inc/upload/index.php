<?php

require_once(__DIR__ . '/loop.php');

// Image Upload Handler 
function bbc_upload_image($post = null, $src = null)
{
    // Check if there`s valid src and then runs convertation/attach logic
    if (!empty($src))
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
        else {

            $data = file_get_contents($src[1]);
            // $raw_data = base64_encode(file_get_contents($src[1]));
            // $data = base64_decode($raw_data);
        }

        // #WRONG PLACEMENT, REWORK
        // Removes all existing image attachments
        // $attachments_existing = get_attached_media('image', $post->ID);
        // delete_post_thumbnail($post->ID);
        // foreach($attachments_existing as $attachment) {
        //     wp_delete_attachment($attachment->ID, true);
        //     usleep(500);
        // }

        // Runs convertation/attach
        bbc_upload_loop($post, $data);
    }
}

