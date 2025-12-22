<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffeebrk_User_Greeting_Widget extends Widget_Base {

    public function get_name() {
        return 'coffeebrk_user_greeting';
    }

    public function get_title() {
        return __( 'User Greeting', 'coffeebrk-core' );
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return [ 'coffeebrk' ];
    }

    public function get_keywords() {
        return [ 'user', 'greeting', 'name', 'welcome', 'logged in', 'first name', 'coffeebrk' ];
    }

    protected function register_controls() {
        
        // Content Section: Settings
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'prefix_text',
            [
                'label' => __( 'Prefix Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'Welcome back, ',
                'placeholder' => 'Welcome back, ',
                'description' => __( 'Text to show before the user name', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'suffix_text',
            [
                'label' => __( 'Suffix Text', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => ' 👋',
                'placeholder' => ' 👋',
                'description' => __( 'Text to show after the user name (supports emojis)', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'fallback_text',
            [
                'label' => __( 'Fallback Text (Not Logged In)', 'coffeebrk-core' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'Welcome 👋',
                'placeholder' => 'Welcome 👋',
                'description' => __( 'Text to show when user is not logged in', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'name_source',
            [
                'label' => __( 'Name Source', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'first_name' => __( 'First Name', 'coffeebrk-core' ),
                    'display_name' => __( 'Display Name', 'coffeebrk-core' ),
                    'username' => __( 'Username', 'coffeebrk-core' ),
                ],
                'default' => 'first_name',
                'description' => __( 'Which user field to display', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'fallback_to_display_name',
            [
                'label' => __( 'Fallback to Display Name', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __( 'If first name is empty, use display name instead', 'coffeebrk-core' ),
                'condition' => [
                    'name_source' => 'first_name',
                ],
            ]
        );

        $this->add_control(
            'html_tag',
            [
                'label' => __( 'HTML Tag', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'div',
                    'span' => 'span',
                    'p' => 'p',
                ],
                'default' => 'h2',
            ]
        );

        $this->end_controls_section();

        // Style Section: Text
        $this->start_controls_section(
            'section_style_text',
            [
                'label' => __( 'Text', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __( 'Text Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'selector' => '{{WRAPPER}} .cbk-user-greeting',
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .cbk-user-greeting',
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label' => __( 'Alignment', 'coffeebrk-core' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __( 'Left', 'coffeebrk-core' ),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __( 'Center', 'coffeebrk-core' ),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __( 'Right', 'coffeebrk-core' ),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => __( 'Justified', 'coffeebrk-core' ),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'text_padding',
            [
                'label' => __( 'Padding', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'text_margin',
            [
                'label' => __( 'Margin', 'coffeebrk-core' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section: Name Highlight (optional)
        $this->start_controls_section(
            'section_style_name',
            [
                'label' => __( 'Name Styling', 'coffeebrk-core' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'enable_name_styling',
            [
                'label' => __( 'Enable Custom Name Styling', 'coffeebrk-core' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'coffeebrk-core' ),
                'label_off' => __( 'No', 'coffeebrk-core' ),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __( 'Apply different styling to the user name only', 'coffeebrk-core' ),
            ]
        );

        $this->add_control(
            'name_color',
            [
                'label' => __( 'Name Color', 'coffeebrk-core' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting__name' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'enable_name_styling' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'name_typography',
                'selector' => '{{WRAPPER}} .cbk-user-greeting__name',
                'condition' => [
                    'enable_name_styling' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'name_font_weight',
            [
                'label' => __( 'Font Weight', 'coffeebrk-core' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __( 'Default', 'coffeebrk-core' ),
                    '300' => __( 'Light', 'coffeebrk-core' ),
                    '400' => __( 'Normal', 'coffeebrk-core' ),
                    '500' => __( 'Medium', 'coffeebrk-core' ),
                    '600' => __( 'Semi Bold', 'coffeebrk-core' ),
                    '700' => __( 'Bold', 'coffeebrk-core' ),
                    '800' => __( 'Extra Bold', 'coffeebrk-core' ),
                ],
                'default' => '600',
                'selectors' => [
                    '{{WRAPPER}} .cbk-user-greeting__name' => 'font-weight: {{VALUE}};',
                ],
                'condition' => [
                    'enable_name_styling' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $greeting_text = $this->get_greeting_text( $settings );
        $html_tag = $settings['html_tag'] ?? 'h2';

        if ( ! $greeting_text ) {
            return;
        }

        printf(
            '<%1$s class="cbk-user-greeting">%2$s</%1$s>',
            esc_attr( $html_tag ),
            $greeting_text // Already escaped in get_greeting_text()
        );
    }

    private function get_greeting_text( $settings ) {
        $user = wp_get_current_user();

        // User not logged in - show fallback
        if ( ! $user || $user->ID === 0 ) {
            $fallback = $settings['fallback_text'] ?? 'Welcome 👋';
            return esc_html( $fallback );
        }

        // Get user name based on source
        $name_source = $settings['name_source'] ?? 'first_name';
        $user_name = '';

        if ( $name_source === 'first_name' ) {
            $user_name = get_user_meta( $user->ID, 'first_name', true );
            
            // Fallback to display name if first name is empty
            if ( empty( $user_name ) && $settings['fallback_to_display_name'] === 'yes' ) {
                $user_name = $user->display_name;
            }
        } elseif ( $name_source === 'display_name' ) {
            $user_name = $user->display_name;
        } elseif ( $name_source === 'username' ) {
            $user_name = $user->user_login;
        }

        // If still empty, use display name as final fallback
        if ( empty( $user_name ) ) {
            $user_name = $user->display_name;
        }

        // Build greeting text
        $prefix = $settings['prefix_text'] ?? '';
        $suffix = $settings['suffix_text'] ?? '';
        $enable_name_styling = $settings['enable_name_styling'] === 'yes';

        // Escape components
        $prefix = esc_html( $prefix );
        $suffix = esc_html( $suffix );
        $user_name = esc_html( $user_name );

        // Wrap name in span if custom styling is enabled
        if ( $enable_name_styling ) {
            $user_name = '<span class="cbk-user-greeting__name">' . $user_name . '</span>';
        }

        return $prefix . $user_name . $suffix;
    }

    protected function content_template() {
        // JS template for Elementor editor preview
        ?>
        <#
        var htmlTag = settings.html_tag || 'h2';
        var prefix = settings.prefix_text || '';
        var suffix = settings.suffix_text || '';
        var enableNameStyling = settings.enable_name_styling === 'yes';
        
        // Preview with sample name
        var userName = 'Hasan';
        
        if ( enableNameStyling ) {
            userName = '<span class="cbk-user-greeting__name">' + userName + '</span>';
        }
        
        var greetingText = prefix + userName + suffix;
        #>
        <{{{ htmlTag }}} class="cbk-user-greeting">
            {{{ greetingText }}}
        </{{{ htmlTag }}}>
        <?php
    }
}
