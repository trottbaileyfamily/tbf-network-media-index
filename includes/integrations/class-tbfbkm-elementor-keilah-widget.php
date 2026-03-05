<?php
/**
 * File: includes/integrations/class-tbfbkm-elementor-keilah-widget.php
 * Version: 6.9.27
 * Description: Elementor specific class for the Princess Keilah Studio.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Elementor_Keilah_Widget extends \Elementor\Widget_Base {
    
    public function get_name() { return 'princess_keilah_studio'; }
    public function get_title() { return 'Princess Keilah Studio'; }
    public function get_icon() { return 'eicon-headphones'; }
    public function get_categories() { return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Studio Settings',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('visual_mode', [
            'label' => 'Visual Mode',
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'random',
            'options' => [
                'random' => 'Random Network Images',
                'specific' => 'Specific Images',
            ],
        ]);

        $this->add_control('specific_images', [
            'label' => 'Image IDs (Comma separated)',
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => 'Enter the IDs of the images you want to show, separated by commas.',
            'condition' => [
                'visual_mode' => 'specific',
            ],
        ]);

        $this->add_control('duration', [
            'label' => 'Slide Duration (seconds)',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 5,
        ]);

        $this->add_control('autoplay', [
            'label' => 'Auto Start Audio',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => 'Yes',
            'label_off' => 'No',
            'return_value' => 'true',
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $custom_config = [
            'mode' => $settings['visual_mode'],
            'specific_ids' => $settings['specific_images'],
            'duration' => $settings['duration'],
            'auto_start' => $settings['autoplay'] === 'true'
        ];

        if ( class_exists('TBFBKM_World_Ruler') ) {
            TBFBKM_World_Ruler::inline_render($custom_config);
        }
    }
}
