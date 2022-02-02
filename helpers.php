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
function bbc_attach_first_image($post, $content)
{

    $pattern1 = '/<img(?:[^>])*+>/i';
    preg_match($pattern1, $content, $first_image);
    preg_match('/src=[\'"]([^\'"]+)/', $first_image[0], $src_match);

    if(preg_match('/^data:image\/(\w+);base64,/', $src_match[1]) !== false) {
        $binary = base64_decode(explode(',', $src_match[1]) [1]);
        $image_data = getimagesizefromstring($binary) ?: false;
        bbc_upload_image($post, $src_match, $image_data);
    }
    elseif(preg_match('/http/', $src_match[1]) !== false) {
        if (bbc_check_url_status($src_match[1])) {
            $image_data = getimagesize($src_match[1]);
            bbc_upload_image($post, $src_match, $image_data);       
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

// Auto width/height attributes
function bbc_set_image_dimension($content = null, $post = null)
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

                    bbc_upload_image($post, $src_match, $image_data);
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
    $filtered7 = preg_replace($pattern6, "", $filtered6);
    $filtered8 = preg_replace($pattern8, '$1', $filtered7);
    $filtered9 = preg_replace($pattern9, '', $filtered8);

    $filtered10 = preg_replace($pattern10, '', $filtered9);
    $filtered11 = preg_replace($pattern11, '$1', $filtered10);
    $filtered12 = preg_replace($pattern12, '', $filtered11);
    $filtered13 = preg_replace($pattern13, '<h2>$1</h2>', $filtered12);

    return $filtered13;
}

// Adds alts for post content images
function bbc_alt_singlepage_autocomplete($id, $content)
{
    $post = get_post($id);
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

