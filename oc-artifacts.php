<?php
/**
 * Plugin Name: OC Artifacts
 * Description: Custom post type for OpenClaw-deployed HTML/CSS/JS apps
 * Version: 0.1.0
 * Author: Bob (via OpenClaw)
 */

if (!defined('ABSPATH')) exit;

// Register the Artifact post type
add_action('init', function() {
    register_post_type('artifact', [
        'labels' => [
            'name' => 'Artifacts',
            'singular_name' => 'Artifact',
            'add_new' => 'Add New Artifact',
            'edit_item' => 'Edit Artifact',
            'view_item' => 'View Artifact',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'artifacts',
        'supports' => ['title', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'artifacts'],
        'capability_type' => 'post',  // POC: use post caps. Harden later.
        'menu_icon' => 'dashicons-art',
    ]);

    // Register meta field for raw HTML (no sanitization)
    register_post_meta('artifact', 'artifact_html', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    // Optional: description meta
    register_post_meta('artifact', 'artifact_description', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
    ]);
});

// Template override for single artifacts - render raw HTML
add_filter('template_include', function($template) {
    if (is_singular('artifact')) {
        // Check for custom template in theme first
        $custom = locate_template('single-artifact.php');
        if ($custom) return $custom;
        
        // Otherwise use our built-in renderer
        return plugin_dir_path(__FILE__) . 'single-artifact.php';
    }
    return $template;
});

// Add admin notice with instructions
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'artifact') {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>OC Artifacts:</strong> Add your HTML/CSS/JS app via the REST API. ';
        echo 'POST to <code>/wp-json/wp/v2/artifacts</code> with <code>title</code> and <code>meta.artifact_html</code>.';
        echo '</p></div>';
    }
});
