<?php
/**
 * Template for rendering artifacts
 * Outputs raw HTML from artifact_html meta field
 * Includes Content-Security-Policy headers for safety
 */

if (!defined('ABSPATH')) exit;

// Get the artifact HTML
$artifact_html = get_post_meta(get_the_ID(), 'artifact_html', true);

if (empty($artifact_html)) {
    // Fallback: show message in theme wrapper
    get_header();
    echo '<div class="artifact-empty" style="padding: 2rem; text-align: center;">';
    echo '<h1>' . esc_html(get_the_title()) . '</h1>';
    echo '<p>This artifact has no content yet.</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Content-Security-Policy: limit what artifact JS can do
// - 'self' 'unsafe-inline': allows inline scripts/styles (required for single-file apps)
// - img-src allows data: URIs for embedded images
// - connect-src 'self': blocks external fetch/XHR (prevents data exfiltration)
// - frame-ancestors 'self': prevents embedding artifact in external iframes
$csp = implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    "connect-src 'self'",
    "frame-src 'none'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "base-uri 'self'",
]);

// Apply CSP - can be filtered by theme/plugins if needed
$csp = apply_filters('oc_artifacts_csp', $csp, get_the_ID());

// Set headers
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: $csp");

// Output the artifact
echo $artifact_html;
