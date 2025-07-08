<?php
/**
 * Plugin Name: Auto Link Preview Cards
 * Description: Automatically converts pasted links in post content into preview cards (title, image, description) like Twitter/X.
 * Version: 1.2
 * Author: kevin@thehistorylist.com
 */

// Add Open Graph meta tags for social media previews (only if not already set by Yoast/other SEO plugins)
add_action('wp_head', 'alp_add_social_media_meta_tags', 20); // Lower priority to run after other SEO plugins

// Auto-generate link previews in content
add_filter('the_content', 'alp_auto_generate_link_previews');

function alp_add_social_media_meta_tags() {
    // Only add meta tags on single posts/pages
    if (!is_singular()) return;
    
    // Check if Yoast or other SEO plugins have already generated OG tags
    // We'll check for existing OG tags in the current output buffer
    $current_output = ob_get_contents();
    
    // Check which OG tags already exist
    $existing_og_tags = [];
    
    // Check for og:title
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:title'] = $match[1];
    }
    
    // Check for og:description
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:description'] = $match[1];
    }
    
    // Check for og:image
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:image'] = $match[1];
    }
    
    // Check for og:url
    if (preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:url'] = $match[1];
    }
    
    // Check for og:type
    if (preg_match('/<meta[^>]+property=["\']og:type["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:type'] = $match[1];
    }
    
    // Check for og:site_name
    if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_og_tags['og:site_name'] = $match[1];
    }
    
    // If all OG tags exist and have valid content, don't add anything
    if (count($existing_og_tags) >= 5 && 
        !empty($existing_og_tags['og:title']) && 
        !empty($existing_og_tags['og:description']) && 
        !empty($existing_og_tags['og:image']) && 
        !empty($existing_og_tags['og:url']) && 
        !empty($existing_og_tags['og:type'])) {
        return;
    }
    
    global $post;
    
    // Prepare fallback data
    $fallback_data = [];
    
    // Get post data for fallbacks
    $fallback_data['title'] = get_the_title();
    
    // Improved description fallback: excerpt, then first text from content, then title
    $excerpt = get_the_excerpt();
    if (empty($excerpt)) {
        global $post;
        // Try to get first meaningful text from post content
        $content = $post->post_content;
        // Remove shortcodes and HTML tags
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = trim(preg_replace('/\s+/', ' ', $content));
        if (!empty($content)) {
            $excerpt = $content;
        }
    }
    if (empty($excerpt)) {
        $excerpt = get_the_title();
    }
    $fallback_data['excerpt'] = substr($excerpt, 0, 160); // Limit to 160 characters
    $fallback_data['url'] = get_permalink();
    $fallback_data['type'] = 'article';
    $fallback_data['site_name'] = get_bloginfo('name');
    
    // Improved image fallback: featured image, then image near title, then first image in content, then default
    $image = '';
    if (has_post_thumbnail()) {
        $image = get_the_post_thumbnail_url($post->ID, 'large');
    }
    if (empty($image)) {
        // Try to find image near the title in content (first <img> after <h1> or <h2> or <p> with title)
        $content = $post->post_content;
        // Try to find <img> after a heading or paragraph with the title
        if (preg_match('/<h[12][^>]*>\s*'.preg_quote($fallback_data['title'], '/').'\s*<\/h[12]>.*?<img[^>]+src=["\']([^"\']+)["\'][^>]*>/is', $content, $matches)) {
            $image = $matches[1];
        } elseif (preg_match('/<p[^>]*>\s*'.preg_quote($fallback_data['title'], '/').'\s*<\/p>.*?<img[^>]+src=["\']([^"\']+)["\'][^>]*>/is', $content, $matches)) {
            $image = $matches[1];
        }
    }
    if (empty($image)) {
        // Fallback: first image in content
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches)) {
            $image = $matches[1];
        }
    }
    if (!empty($image) && strpos($image, 'http') !== 0) {
        $image = site_url($image);
    }
    if (empty($image)) {
        $image = 'https://historycamp.org/wp-content/uploads/hc-logo-social-media.png';
    }
    $fallback_data['image'] = $image;
    
    // Add only missing OG tags
    $added_tags = [];

    // --- Twitter Card Tag Detection ---
    $existing_twitter_tags = [];
    // Check for twitter:card
    if (preg_match('/<meta[^>]+name=["\']twitter:card["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_twitter_tags['twitter:card'] = $match[1];
    }
    // Check for twitter:title
    if (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_twitter_tags['twitter:title'] = $match[1];
    }
    // Check for twitter:description
    if (preg_match('/<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $current_output, $match)) {
        $existing_twitter_tags['twitter:description'] = $match[1];
    }
    // Check for twitter:image
    if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $current_output, $match)) {
        $existing_twitter_tags['twitter:image'] = $match[1];
    }

    // --- Add only missing Twitter tags ---
    // Twitter card type (always summary_large_image for best preview)
    if (empty($existing_twitter_tags['twitter:card'])) {
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }
    // Twitter title
    if (empty($existing_twitter_tags['twitter:title']) && !empty($fallback_data['title'])) {
        echo '<meta name="twitter:title" content="' . esc_attr($fallback_data['title']) . '" />' . "\n";
    }
    // Twitter description
    if (empty($existing_twitter_tags['twitter:description']) && !empty($fallback_data['excerpt'])) {
        echo '<meta name="twitter:description" content="' . esc_attr($fallback_data['excerpt']) . '" />' . "\n";
    }
    // Twitter image
    if (empty($existing_twitter_tags['twitter:image']) && !empty($fallback_data['image'])) {
        // Warn in code if image is too small for Twitter/X (min 300x157px, ideally 1200x628px)
        // This is a code comment only, not output to HTML
        // If you want to enforce, you could add a check here
        echo '<meta name="twitter:image" content="' . esc_url($fallback_data['image']) . '" />' . "\n";
        // Example check (not enforced, just a dev note):
        // list($img_width, $img_height) = @getimagesize($fallback_data['image']);
        // if ($img_width < 300 || $img_height < 157) { /* Consider warning or using a different image */ }
    }
    
    // Add og:title if missing
    if (empty($existing_og_tags['og:title'])) {
        echo '<meta property="og:title" content="' . esc_attr($fallback_data['title']) . '" />' . "\n";
        $added_tags[] = 'og:title';
    }
    
    // Add og:description if missing
    if (empty($existing_og_tags['og:description'])) {
        echo '<meta property="og:description" content="' . esc_attr($fallback_data['excerpt']) . '" />' . "\n";
        $added_tags[] = 'og:description';
    }
    
    // Add og:image if missing
    if (empty($existing_og_tags['og:image'])) {
        echo '<meta property="og:image" content="' . esc_url($fallback_data['image']) . '" />' . "\n";
        $added_tags[] = 'og:image';
    }
    
    // Add og:url if missing
    if (empty($existing_og_tags['og:url'])) {
        echo '<meta property="og:url" content="' . esc_url($fallback_data['url']) . '" />' . "\n";
        $added_tags[] = 'og:url';
    }
    
    // Add og:type if missing
    if (empty($existing_og_tags['og:type'])) {
        echo '<meta property="og:type" content="' . esc_attr($fallback_data['type']) . '" />' . "\n";
        $added_tags[] = 'og:type';
    }
    
    // Add og:site_name if missing
    if (empty($existing_og_tags['og:site_name'])) {
        echo '<meta property="og:site_name" content="' . esc_attr($fallback_data['site_name']) . '" />' . "\n";
        $added_tags[] = 'og:site_name';
    }
}

function alp_auto_generate_link_previews($content) {
    // Match any standalone URL on a line by itself
    $pattern = '/^(https?:\/\/[^\s<]+)$/im';

    return preg_replace_callback($pattern, function ($matches) {
        $url = esc_url($matches[1]);

        // Try native oEmbed (YouTube, Twitter, etc.)
        $embed = wp_oembed_get($url);
        if ($embed) return $embed;

        // Fetch metadata manually
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress Auto Link Preview)'
        ]);
        
        if (is_wp_error($response)) {
            // Return simple link if fetch fails
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        }

        $html = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // Only process HTML content
        if (strpos($content_type, 'text/html') === false) {
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        }

        // Extract Open Graph metadata with better regex patterns
        preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $title);
        preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $image);
        preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $desc);
        
        // Fallback to regular meta tags if OG tags not found
        if (empty($title[1])) {
            preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $title);
        }
        if (empty($desc[1])) {
            preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $desc);
        }
        
        // Try to find first image if no OG image
        if (empty($image[1])) {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $image);
        }

        $title = isset($title[1]) ? esc_html(trim($title[1])) : parse_url($url, PHP_URL_HOST);
        $image = isset($image[1]) ? esc_url($image[1]) : 'https://historycamp.org/wp-content/uploads/default-og-image.jpg';
        $desc  = isset($desc[1]) ? esc_html(trim($desc[1])) : '';

        // Clean up description
        $desc = substr($desc, 0, 200); // Limit description length

        // Build HTML preview card with improved styling
        $output = "<div class='auto-link-preview' style='border:1px solid #e1e8ed;padding:15px;border-radius:12px;margin:1em 0;max-width:600px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>";
        $output .= "<a href='$url' target='_blank' rel='noopener noreferrer' style='text-decoration:none;color:inherit;display:block;'>";
        if ($image && $image !== 'https://historycamp.org/wp-content/uploads/default-og-image.jpg') {
            $output .= "<img src='$image' style='width:100%;height:200px;object-fit:cover;border-radius:8px;margin-bottom:12px;'>";
        }
        $output .= "<h3 style='margin:0;font-size:1.1em;font-weight:600;color:#14171a;line-height:1.3;'>$title</h3>";
        if ($desc) {
            $output .= "<p style='margin:0.5em 0 0;font-size:0.9em;color:#657786;line-height:1.4;'>$desc</p>";
        }
        $output .= "<div style='margin-top:8px;font-size:0.8em;color:#657786;'>" . parse_url($url, PHP_URL_HOST) . "</div>";
        $output .= "</a></div>";

        return $output;
    }, $content);
}
?>
