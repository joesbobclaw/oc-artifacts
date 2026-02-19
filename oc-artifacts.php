<?php
/**
 * Plugin Name: OC Artifacts
 * Description: Custom post type for OpenClaw-deployed HTML/CSS/JS apps with proper WordPress script handling
 * Version: 1.0.0
 * Author: Bob (via OpenClaw)
 */

if (!defined('ABSPATH')) exit;

class OC_Artifacts {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('rest_api_init', [$this, 'register_rest_fields']);
        add_filter('template_include', [$this, 'template_include']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_artifact_assets']);
    }
    
    /**
     * Register the Artifact post type with custom capabilities
     */
    public function register_post_type() {
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
            'capability_type' => 'artifact',
            'map_meta_cap' => true,
        ]);

        // Register meta fields
        register_post_meta('artifact', 'artifact_html', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => [
                'schema' => ['type' => 'string'],
            ],
            'auth_callback' => fn() => current_user_can('edit_artifacts'),
        ]);
        
        register_post_meta('artifact', 'artifact_description', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        
        // Internal meta for processed assets
        register_post_meta('artifact', '_artifact_assets', [
            'type' => 'object',
            'single' => true,
            'show_in_rest' => false,
        ]);
        
        register_post_meta('artifact', '_artifact_body', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
        ]);
    }
    
    /**
     * Hook into REST API to process HTML on save
     */
    public function register_rest_fields() {
        add_action('rest_after_insert_artifact', [$this, 'process_artifact_html'], 10, 2);
    }
    
    /**
     * Process the HTML after artifact is saved via REST API
     */
    public function process_artifact_html($post, $request) {
        $raw_html = get_post_meta($post->ID, 'artifact_html', true);
        
        if (empty($raw_html)) {
            return;
        }
        
        // Parse and extract assets
        $parsed = $this->parse_html($raw_html, $post->ID);
        
        // Save processed data
        update_post_meta($post->ID, '_artifact_body', $parsed['body']);
        update_post_meta($post->ID, '_artifact_assets', $parsed['assets']);
    }
    
    /**
     * Parse HTML and extract scripts/styles to separate files
     */
    public function parse_html($html, $post_id) {
        $assets = [
            'styles' => [],
            'scripts' => [],
            'head' => '',
        ];
        
        // Create upload directory for this artifact
        $upload_dir = wp_upload_dir();
        $artifact_dir = $upload_dir['basedir'] . '/artifacts/' . $post_id;
        $artifact_url = $upload_dir['baseurl'] . '/artifacts/' . $post_id;
        
        if (!file_exists($artifact_dir)) {
            wp_mkdir_p($artifact_dir);
        }
        
        // Clear old files
        $old_files = glob($artifact_dir . '/*');
        foreach ($old_files as $file) {
            if (is_file($file)) unlink($file);
        }
        
        // Use DOMDocument for parsing
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Extract and save <style> tags
        $style_index = 0;
        $styles = $xpath->query('//style');
        foreach ($styles as $style) {
            $css_content = $style->textContent;
            if (trim($css_content)) {
                $filename = "style-{$style_index}.css";
                file_put_contents($artifact_dir . '/' . $filename, $css_content);
                $assets['styles'][] = [
                    'handle' => "artifact-{$post_id}-style-{$style_index}",
                    'url' => $artifact_url . '/' . $filename,
                ];
                $style_index++;
            }
            $style->parentNode->removeChild($style);
        }
        
        // Extract and save <script> tags
        $script_index = 0;
        $scripts = $xpath->query('//script');
        $script_nodes = [];
        foreach ($scripts as $script) {
            $script_nodes[] = $script; // Collect first to avoid modification during iteration
        }
        
        foreach ($script_nodes as $script) {
            $src = $script->getAttribute('src');
            
            if ($src) {
                // External script - keep reference
                $assets['scripts'][] = [
                    'handle' => "artifact-{$post_id}-ext-{$script_index}",
                    'url' => $src,
                    'external' => true,
                ];
            } else {
                // Inline script - save to file
                $js_content = $script->textContent;
                if (trim($js_content)) {
                    $filename = "script-{$script_index}.js";
                    file_put_contents($artifact_dir . '/' . $filename, $js_content);
                    $assets['scripts'][] = [
                        'handle' => "artifact-{$post_id}-script-{$script_index}",
                        'url' => $artifact_url . '/' . $filename,
                        'external' => false,
                    ];
                }
            }
            $script_index++;
            $script->parentNode->removeChild($script);
        }
        
        // Extract <head> content (meta tags, title, etc.)
        $heads = $xpath->query('//head');
        if ($heads->length > 0) {
            $head = $heads->item(0);
            $assets['head'] = $dom->saveHTML($head);
            $head->parentNode->removeChild($head);
        }
        
        // Get the remaining body content
        $bodies = $xpath->query('//body');
        if ($bodies->length > 0) {
            $body = $bodies->item(0);
            $body_html = '';
            foreach ($body->childNodes as $child) {
                $body_html .= $dom->saveHTML($child);
            }
        } else {
            // No body tag, use everything
            $body_html = $dom->saveHTML();
        }
        
        // Clean up XML declaration artifacts
        $body_html = preg_replace('/^<\?xml[^>]*\?>/', '', $body_html);
        $body_html = preg_replace('/<\/?html[^>]*>/', '', $body_html);
        $body_html = preg_replace('/<\/?body[^>]*>/', '', $body_html);
        
        return [
            'body' => trim($body_html),
            'assets' => $assets,
        ];
    }
    
    /**
     * Enqueue artifact assets on singular artifact pages
     */
    public function enqueue_artifact_assets() {
        if (!is_singular('artifact')) {
            return;
        }
        
        $post_id = get_the_ID();
        $assets = get_post_meta($post_id, '_artifact_assets', true);
        
        if (empty($assets)) {
            return;
        }
        
        // Enqueue styles
        if (!empty($assets['styles'])) {
            foreach ($assets['styles'] as $style) {
                wp_enqueue_style(
                    $style['handle'],
                    $style['url'],
                    [],
                    filemtime($this->url_to_path($style['url'])) ?: null
                );
            }
        }
        
        // Enqueue scripts (in footer)
        if (!empty($assets['scripts'])) {
            foreach ($assets['scripts'] as $script) {
                wp_enqueue_script(
                    $script['handle'],
                    $script['url'],
                    [],
                    $script['external'] ? null : (filemtime($this->url_to_path($script['url'])) ?: null),
                    true // Load in footer
                );
            }
        }
    }
    
    /**
     * Convert URL to filesystem path
     */
    private function url_to_path($url) {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
    }
    
    /**
     * Use custom template for artifacts
     */
    public function template_include($template) {
        if (is_singular('artifact')) {
            $custom = locate_template('single-artifact.php');
            if ($custom) return $custom;
            return plugin_dir_path(__FILE__) . 'single-artifact.php';
        }
        return $template;
    }
}

// Initialize
OC_Artifacts::instance();

// Activation hook - set up capabilities
register_activation_hook(__FILE__, function() {
    $admin = get_role('administrator');
    if ($admin) {
        foreach (['edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_others', 'edit_published', 'delete_published'] as $cap) {
            $admin->add_cap($cap . '_artifacts');
        }
    }
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/**
 * Helper function to grant artifact capabilities to additional roles
 */
function oc_artifacts_grant_to_role($role_name) {
    $role = get_role($role_name);
    if ($role) {
        foreach (['edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_others', 'edit_published', 'delete_published'] as $cap) {
            $role->add_cap($cap . '_artifacts');
        }
    }
}
