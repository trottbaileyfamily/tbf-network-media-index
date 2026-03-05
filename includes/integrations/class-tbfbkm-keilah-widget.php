<?php
/**
 * File: includes/integrations/class-tbfbkm-keilah-widget.php
 * Version: 6.9.27
 * Description: Registers the Princess Keilah Studio as an Elementor Widget and Shortcode.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Keilah_Widget {

    public static function init() {
        // Register Shortcode
        add_shortcode('princess_keilah_studio', [__CLASS__, 'render_shortcode']);

        // Hook Elementor Widget Registration
        add_action('elementor/widgets/register', [__CLASS__, 'register_elementor_widget']);
    }

    public static function render_shortcode($atts) {
        $a = shortcode_atts([
            'mode' => 'random',
            'images' => '',
            'duration' => 5,
            'autoplay' => 'false'
        ], $atts);

        $custom_config = [
            'mode' => $a['mode'],
            'specific_ids' => $a['images'],
            'duration' => (int)$a['duration'],
            'auto_start' => filter_var($a['autoplay'], FILTER_VALIDATE_BOOLEAN)
        ];

        ob_start();
        
        if ( class_exists('TBFBKM_World_Ruler') ) {
            TBFBKM_World_Ruler::inline_render($custom_config);
        } else {
            echo '<p>Princess Keilah Studio engine not found.</p>';
        }
        
        return ob_get_clean();
    }

    public static function register_elementor_widget($widgets_manager) {
        // Only load the Elementor class file now that Elementor is active
        require_once dirname(__FILE__) . '/class-tbfbkm-elementor-keilah-widget.php';
        $widgets_manager->register(new TBFBKM_Elementor_Keilah_Widget());
    }
}
