<?php
/**
 * Template for rendering artifacts
 * Uses properly enqueued scripts/styles via WordPress
 */

if (!defined('ABSPATH')) exit;

$post_id = get_the_ID();
$body = get_post_meta($post_id, '_artifact_body', true);
$assets = get_post_meta($post_id, '_artifact_assets', true);

// Fallback: if no processed body, try raw HTML (backwards compatibility)
if (empty($body)) {
    $raw_html = get_post_meta($post_id, 'artifact_html', true);
    if (!empty($raw_html)) {
        // Legacy mode - output raw (pre-1.0 artifacts)
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $raw_html;
        exit;
    }
    
    // No content at all
    get_header();
    echo '<div class="artifact-empty" style="padding: 2rem; text-align: center;">';
    echo '<h1>' . esc_html(get_the_title()) . '</h1>';
    echo '<p>This artifact has no content yet.</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CSP - now we can be stricter since scripts are served as files
$upload_dir = wp_upload_dir();
$artifacts_url = $upload_dir['baseurl'] . '/artifacts/';

$csp_parts = [
    "default-src 'self'",
    "script-src 'self' {$artifacts_url}",   // Allow our artifact scripts
    "style-src 'self' 'unsafe-inline' {$artifacts_url}", // Allow inline styles + our CSS files
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    "connect-src 'self'",                    // Allow same-origin fetch
    "frame-src 'none'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "base-uri 'self'",
];

$csp = implode('; ', $csp_parts);
$csp = apply_filters('oc_artifacts_csp', $csp, $post_id);
header("Content-Security-Policy: $csp");

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_the_title()); ?> - <?php bloginfo('name'); ?></title>
    <?php
    // Output any preserved head content (meta tags, etc.)
    if (!empty($assets['head'])) {
        // Strip the <head> tags, just get inner content
        $head_content = preg_replace('/<\/?head[^>]*>/i', '', $assets['head']);
        echo $head_content;
    }
    
    // WordPress head (includes our enqueued styles)
    wp_head();
    ?>
</head>
<body <?php body_class('artifact-page'); ?>>
    <?php wp_body_open(); ?>
    
    <div class="artifact-container">
        <?php
        // Output the sanitized body content
        // Using wp_kses with expanded allowed tags for artifact content
        $allowed_html = wp_kses_allowed_html('post');
        
        // Add canvas and other app-necessary elements
        $allowed_html['canvas'] = [
            'id' => true,
            'class' => true,
            'width' => true,
            'height' => true,
            'style' => true,
        ];
        $allowed_html['svg'] = [
            'xmlns' => true,
            'viewbox' => true,
            'width' => true,
            'height' => true,
            'class' => true,
            'id' => true,
            'style' => true,
        ];
        $allowed_html['path'] = [
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'class' => true,
        ];
        $allowed_html['input'] = [
            'type' => true,
            'id' => true,
            'class' => true,
            'name' => true,
            'value' => true,
            'placeholder' => true,
            'disabled' => true,
            'readonly' => true,
            'checked' => true,
            'min' => true,
            'max' => true,
            'step' => true,
            'style' => true,
        ];
        $allowed_html['button'] = [
            'type' => true,
            'id' => true,
            'class' => true,
            'disabled' => true,
            'style' => true,
        ];
        $allowed_html['select'] = [
            'id' => true,
            'class' => true,
            'name' => true,
            'style' => true,
        ];
        $allowed_html['option'] = [
            'value' => true,
            'selected' => true,
        ];
        $allowed_html['label'] = [
            'for' => true,
            'class' => true,
        ];
        $allowed_html['video'] = [
            'src' => true,
            'controls' => true,
            'autoplay' => true,
            'loop' => true,
            'muted' => true,
            'width' => true,
            'height' => true,
            'class' => true,
            'id' => true,
        ];
        $allowed_html['audio'] = [
            'src' => true,
            'controls' => true,
            'autoplay' => true,
            'loop' => true,
            'class' => true,
            'id' => true,
        ];
        
        // Allow all data-* attributes on common elements
        foreach (['div', 'span', 'button', 'input', 'a', 'canvas'] as $tag) {
            if (isset($allowed_html[$tag])) {
                $allowed_html[$tag]['data-*'] = true;
            }
        }
        
        $allowed_html = apply_filters('oc_artifacts_allowed_html', $allowed_html);
        
        echo wp_kses($body, $allowed_html);
        ?>
    </div>
    
    <?php 
    // WordPress footer (includes our enqueued scripts)
    wp_footer(); 
    ?>
</body>
</html>
