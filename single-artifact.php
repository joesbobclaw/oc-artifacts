<?php
/**
 * Template for rendering artifacts
 * Outputs raw HTML from artifact_html meta field
 */

if (!defined('ABSPATH')) exit;

// Get the artifact HTML
$artifact_html = get_post_meta(get_the_ID(), 'artifact_html', true);

if (empty($artifact_html)) {
    // Fallback: show message in theme wrapper
    get_header();
    echo '<div class="artifact-empty" style="padding: 2rem; text-align: center;">';
    echo '<h1>' . get_the_title() . '</h1>';
    echo '<p>This artifact has no content yet.</p>';
    echo '</div>';
    get_footer();
    exit;
}

// For full-page apps: output raw HTML (no theme wrapper)
// This gives the artifact complete control of the page

// Set appropriate content type
header('Content-Type: text/html; charset=utf-8');

// Security headers (basic)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Output the artifact
echo $artifact_html;
