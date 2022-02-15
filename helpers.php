<?php

require_once(__DIR__. '/inc/upload/index.php');


// Fixes h1-h6 heading issues
function bbc_fix_headings($content) {
	$pattern = '/<h\d>(.*?)<\/h\d>/';

	preg_match_all($pattern, $content, $results);
	if(!empty($results)) {

		if(mb_strpos($results[0][0], 'h2') == false) {
			$h2 = preg_replace($pattern, '<h2>$1</h2>', $results[0][0]);
			
			$old_tag = preg_replace('/\//', '\/', $results[0][0]);
			$old_tag = preg_replace('/\?/', '\?', $old_tag);
			$content = preg_replace('/'.$old_tag . '/', $h2, $content);
		}	
	}
    return $content;
}


// Set an excerpt to post
function bbc_set_excerpt($content) {
    $excerpt = '';

    if (preg_match('/<div[^>]*id="toc"[^>]*>.*?<\/div>/', $content))
    {
        $filtered_content = strip_tags(preg_replace('#<div[^>]*id="toc"[^>]*>.*?</div>#is', '', $content));
    }

    
    elseif (preg_match('/<p>(.*?)<\/p>/', $content))
    {
        $excerpt_raw = preg_match_all('/<p>(.*?)<\/p>/', $content, $results);
        if (!empty($results[0][0]))
        {
            $filtered_content = strip_tags($results[0][0]);
        }
    }

    if(!empty($filtered_content)) {
        $excerpt = mb_substr(strip_tags($filtered_content), 0, 250) . '...';
    }
    return $excerpt;
}


// Get first image and attach it to post
function bbc_attach_first_image($post)
{
    $attachments = get_attached_media('image', $post->ID);
    foreach($attachments as $attachment) {
        $meta_data = wp_get_attachment_metadata($attachment->ID);
        if($meta_data['height'] > 100) {
          set_post_thumbnail($post, $attachment->ID);
          return;
        }
    }
}

// Fixes broken Base64 images
function bbc_base64_fixer($src)
{
    if (strpos($src, 'data:image') === false)
    {
        return preg_replace('/image\//', "data:image/", $src);
    }
    return $src;
}

// URL Status Code Checker
function bbc_check_url_status($url, $condition = null)
{

    $meeting_conditions = true;

    if ($condition)
    {
        switch ($condition)
        {
            case 'local-only':

                if (!preg_match('/' . $_SERVER['SERVER_NAME'] . '/'))
                {
                    $meeting_conditions = false;
                }
            break;

            default:
            break;
        }
    }

    // Checks the existence of URL
    if ($meeting_conditions && @fopen($url, "r"))
    {
        return true;
    }
    else
    {
        return false;
    }
}

// Sets jpg & webp post attachments
function bbc_upload_images($content = null, $post = null)
{
    $has_converted_images = false; 
    
    if(get_post_meta($post->ID, 'hasConvertedImages', true)) {
        $has_converted_images = true;
    }
    
    $buffer = stripslashes($content);

    // Check if this is not converted post
    if(!$has_converted_images) {

        // Get all images
        $pattern1 = '/<img(?:[^>])*+>/i';
        preg_match_all($pattern1, $buffer, $first_match);

        $all_images = array_merge($first_match[0]);

        foreach ($all_images as $image)
        {
            $tmp = $image;
            // Removing existing width/height attributes
            $clean_image = preg_replace('/\swidth="(\d*(px%)?)"(\sheight="(\w+)")?/', '', $tmp);
            $clean_image = preg_replace('/loading="lazy"/', '', $clean_image);

            if ($clean_image)
            {

                // Blob-case variables
                $is_blob = false;

                // Get link of the file
                preg_match('/src=[\'"]([^\'"]+)/', $clean_image, $src_match);

                if (!empty($src_match))
                {
                    // If image is BLOB encoded
                    if (strpos($src_match[1], 'base64') !== false)
                    {
                        $is_blob = true;
                        $image_url = bbc_base64_fixer($src_match[1]);
                        $binary = base64_decode(explode(',', $image_url) [1]);
                        $image_data = getimagesizefromstring($binary) ?: false;
                        
                        bbc_upload_image($post, $src_match);
                    }
                    // Regular src case
                    else
                    {
                        $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https://' : 'http://';

                        // If src doesn`t contains SERVER NAME then add it
                        if (strpos($src_match[1], 'wp-content') && strpos($src_match[1], $protocol) === false)
                        {
                            $src_match[1] = $protocol . $_SERVER['SERVER_NAME'] . $src_match[1] . '';
                        }
                        // If image src returns 200 status then get image size
                        if (bbc_check_url_status($src_match[1]))
                        {
                            $image_data = getimagesize($src_match[1]);
                            bbc_upload_image($post, $src_match);
                        }
                    }

                    // Prepares and output picture element
                    $attachments = get_attached_media('image', $post->ID);
                    $attach_webp_id = array_key_last($attachments);
                    $attach_jpg_id = $attach_webp_id - 1;

                    $src_jpg = wp_get_attachment_url($attach_jpg_id);
                    $src_webp = wp_get_attachment_url($attach_webp_id);
                    $width = $image_data[0];
                    $height = $image_data[1];

                    if($src_jpg && $src_webp && $width && $height) {
                        $image = "<picture><source srcset='${src_webp}' type='image/webp'><img loading='lazy' src='${src_jpg}' width='${width}' height='${height}'></picture>";
                        $buffer = str_replace($tmp, $image, $buffer);
                        update_post_meta($post->ID, 'hasConvertedImages', '1');
                    }
                    else {
                        $buffer = str_replace($tmp, '', $buffer);
                    }
                }
            }
            elseif (!bbc_check_url_status($src_match[1]))
            {
                $buffer = str_replace($tmp, '', $buffer);
            }
        }
    }

    // Inserts </p>{}<p> template around picture for better view
    $pattern = '/(?<![<div>|<p>])(<picture>.*?<\/picture>)(?!<\/[div|p]>)/';
    if(preg_match($pattern, $buffer)) {
        $buffer = preg_replace($pattern, "</p>$1<p>", $buffer);
    }
    return $buffer;
}


// Auto width/height attributes
function bbc_set_image_dimension($content)
{

    $buffer = stripslashes($content);

    // Get all images
    $pattern1 = '/<img(?:[^>])*+>/i';
    preg_match_all($pattern1, $buffer, $first_match);

    $all_images = array_merge($first_match[0]);

    foreach ($all_images as $image)
    {

        $tmp = $image;
        // Removing existing width/height attributes
        $clean_image = preg_replace('/\swidth="(\d*(px%)?)"(\sheight="(\w+)")?/', '', $tmp);
        $clean_image = preg_replace('/loading="lazy"/', '', $clean_image);

        if ($clean_image)
        {

            // Blob-case variables
            $is_blob = false;
            $clean_blob_image;

            // Get link of the file
            preg_match('/src=[\'"]([^\'"]+)/', $clean_image, $src_match);

            if (!empty($src_match))
            {
                // Compares src with banned hosts
                $in_block_list = false;
                $exceptions = get_option('wdss_excluded_hosts_dictionary', '');
                // chemistryland.com, fin.gc.ca, support.revelsystems.com
                if (!empty($exceptions) && is_array($exceptions))
                {
                    foreach ($exceptions as $exception)
                    {
                        if (strpos($src_match[1], $exception) !== false)
                        {
                            $in_block_list = true;
                        }
                    }
                }

                // If image is BLOB encoded
                if (strpos($src_match[1], 'base64') !== false)
                {
                    $is_blob = true;
                    $image_url = bbc_base64_fixer($src_match[1]);
                    $clean_blob_image = '<img src="' . $image_url . '">';
                    $binary = base64_decode(explode(',', $image_url) [1]);
                    $image_data = getimagesizefromstring($binary) ? getimagesizefromstring($binary) : false;

                    if ($image_data)
                    {
                        $width = $image_data[0];
                        $height = $image_data[1];
                    }
                }
                // Regular src case
                else
                {
                    // If image`s host in block list then remove it
                    if ($in_block_list)
                    {
                        $buffer = str_replace($tmp, '', $buffer);
                        return $buffer;
                    }

                    $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https://' : 'http://';

                    // If src doesn`t contains SERVER NAME then add it
                    if (strpos($src_match[1], 'wp-content') && strpos($src_match[1], $protocol) === false)
                    {
                        $src_match[1] = $protocol . $_SERVER['SERVER_NAME'] . $src_match[1] . '';
                    }
                    // If image src returns 200 status then get image size
                    if (bbc_check_url_status($src_match[1]))
                    {
                        list($width, $height) = getimagesize($src_match[1]);
                    }
                }

            }

            // Checks if width & height are defined
            if (!empty($width) && !empty($height))
            {
                $dimension = 'width="' . $width . '" height="' . $height . '" ';

                // Add width and width attribute
                if ($is_blob)
                {
                    $image = str_replace('<img', '<img loading="lazy" ' . $dimension, $clean_blob_image);
                }
                else
                {
                    $image = str_replace('<img', '<img loading="lazy" ' . $dimension, $clean_image);
                }

                // Replace image with new attributes
                $buffer = str_replace($tmp, $image, $buffer);

            }
            else
            {
                $buffer = str_replace($tmp, '', $buffer);
            }
        }
        elseif (!bbc_check_url_status($src_match[1]))
        {
            $buffer = str_replace($tmp, '', $buffer);
        }
    }
    return $buffer;
}


// Filters post content from validation errors
function bbc_regex_post_content_filters($content)
{
    $pattern1 = '/\n/';
    $pattern2 = '/<!--(.*?)-->/';
    $pattern3 = '/<div[^>]*>|<\/div>/';
    $pattern4 = '/<noscript>.*<\/noscript><img.*?>/';
    $pattern5 = '/<figure[^>]*><\/figure[^>]*>/';
    $pattern6 = '/<\w{1,4}>\s?<\/\w{1,4}>/';
    $pattern7 = '/<\/p>\s?<p>/';
    $pattern8 = '/<p>(<iframe[^>]*><\/iframe[^>]*>)<\/p>/';
    $pattern9 = '/[^ -\x{2122}]\s+|\s*[^ -\x{2122}]/u';

    
    $pattern10 = '/<\/p>\s?<\/p>/';
    $pattern11 = '/(<\/h2>)<\/p>/';
	$pattern12 = '/(<\/?strong>)/';
	$pattern13 = '/<p>([\w|\s|\n]*?)<\/h2>/';

    $filtered1 = preg_replace($pattern1, "", $content);
    $filtered2 = preg_replace($pattern2, '', $filtered1);
    $filtered3 = preg_replace($pattern3, '', $filtered2);
    $filtered4 = preg_replace($pattern4, '', $filtered3);
    $filtered5 = preg_replace($pattern5, "", $filtered4);
    $filtered6 = preg_replace($pattern6, "", $filtered5);
    $filtered7 = preg_replace($pattern7, "", $filtered6);
    $filtered8 = preg_replace($pattern8, '$1', $filtered7);
    $filtered9 = preg_replace($pattern9, '', $filtered8);

    $filtered10 = preg_replace($pattern10, '', $filtered9);
    $filtered11 = preg_replace($pattern11, '$1', $filtered10);
    $filtered12 = preg_replace($pattern12, '', $filtered11);
    $filtered13 = preg_replace($pattern13, '<h2>$1</h2>', $filtered12);

    return $filtered13;
}

// Adds alts for post content images
function bbc_alt_singlepage_autocomplete($content, $post)
{
    $old_content = $content;

    $any_alt_pattern = '/alt="(.*)"/';
    $empty_alt_pattern = '/alt=["\']\s?["\']/';
    $image_pattern = '/<img[^>]+>/';

    $post_title = str_replace('"', "", $post->post_title);
    $post_title = mb_strtolower($post_title);

    preg_match_all($image_pattern, $content, $images);

    if (!is_null($images))
    {
        foreach ($images[0] as $index => $value)
        {
            if (!preg_match('/alt=/', $value) || function_exists('pll_current_language') && preg_match($any_alt_pattern, $value))
            {
                if (function_exists('pll_current_language'))
                {
                    $new_img = preg_replace('/alt=".*?"/', '', $images[0][$index]);
                    $new_img = str_replace('<img', '<img alt="' . $post_title . '"', $new_img);
                }
                else
                {
                    $new_img = str_replace('<img', '<img alt="' . $post_title . '"', $images[0][$index]);
                }
                $content = str_replace($images[0][$index], $new_img, $content);
            }
            else if (preg_match($empty_alt_pattern, $value))
            {
                $new_img = preg_replace($empty_alt_pattern, 'alt="' . $post_title . '"', $images[0][$index]);
                $content = str_replace($images[0][$index], $new_img, $content);
            }
        }
    }

    if (empty($content))
    {
        return $old_content;
    }

    return $content;
}

