<?php
require_once(__DIR__ . '/loop.php');

// Image Upload Handler 
function bbc_upload_image($post = null, $src = null, $dimensions = null)
{
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
        else {
            $data = file_get_contents($src[1]);
        }

        bbc_upload_loop($post, $data);
    }
}

