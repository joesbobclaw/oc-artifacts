<?php
/**
 * Plugin Name: OC Artifacts
 * Description: Custom post type for OpenClaw-deployed HTML/CSS/JS apps
 * Version: 0.2.0
 * Author: Bob (via OpenClaw)
 */

if (!defined('ABSPATH')) exit;

// Register custom capabilities on activation
register_activation_hook(__FILE__, function() {
    // Add artifact capabilities to Administrator role only
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('edit_artifacts');
        $admin->add_cap('edit_others_artifacts');
        $admin->add_cap('publish_artifacts');
        $admin->add_cap('read_private_artifacts');
        $admin->add_cap('delete_artifacts');
        $admin->add_cap('delete_others_artifacts');
        $admin->add_cap('edit_published_artifacts');
        $admin->add_cap('delete_published_artifacts');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Clean up on deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Register the Artifact post type
add_action('init', function() {
    register_post_type('artifact', [
        'labels' => [
            'name' => 'Artifacts',
            'singular_name' => 'Artifact',
            'add_new' => 'Add New Artifact',
            'edit_item' => 'Edit Artifact',
            'view_item' => 'View Artifact',
            'all_items' => 'All Artifacts',
            'not_found' => 'No artifacts found',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'artifacts',
        'supports' => ['title', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'artifacts'],
        'menu_icon' => 'dashicons-art',
        // Custom capabilities - only roles with these caps can manage artifacts
        'capability_type' => 'artifact',
        'map_meta_cap' => true,
        'capabilities' => [
            'edit_post' => 'edit_artifact',
            'read_post' => 'read_artifact',
            'delete_post' => 'delete_artifact',
            'edit_posts' => 'edit_artifacts',
            'edit_others_posts' => 'edit_others_artifacts',
            'publish_posts' => 'publish_artifacts',
            'read_private_posts' => 'read_private_artifacts',
            'delete_posts' => 'delete_artifacts',
            'delete_others_posts' => 'delete_others_artifacts',
            'edit_published_posts' => 'edit_published_artifacts',
            'delete_published_posts' => 'delete_published_artifacts',
        ],
    ]);

    // Register meta field for raw HTML (no sanitization)
    register_post_meta('artifact', 'artifact_html', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_artifacts');
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
        $custom = locate_template('single-artifact.php');
        if ($custom) return $custom;
        return plugin_dir_path(__FILE__) . 'single-artifact.php';
    }
    return $template;
});

// Admin notice with security warning
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'artifact') {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>⚠️ Security Note:</strong> Artifacts execute raw HTML/JS. ';
        echo 'Only Administrators can create artifacts by default. ';
        echo 'Use <code>oc_artifacts_allowed_roles</code> filter to modify.';
        echo '</p></div>';
    }
});

// Helper function to grant artifact capabilities to additional roles
function oc_artifacts_grant_to_role($role_name) {
    $role = get_role($role_name);
    if ($role) {
        $role->add_cap('edit_artifacts');
        $role->add_cap('edit_others_artifacts');
        $role->add_cap('publish_artifacts');
        $role->add_cap('read_private_artifacts');
        $role->add_cap('delete_artifacts');
        $role->add_cap('delete_others_artifacts');
        $role->add_cap('edit_published_artifacts');
        $role->add_cap('delete_published_artifacts');
    }
}

// Example: To also allow Editors, add this to your theme's functions.php:
// oc_artifacts_grant_to_role('editor');
