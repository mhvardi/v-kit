<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Elementor\Widget_base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

class SEO_Content_Box_Widget extends Widget_Base {

    public function get_name() { return 'seo_content_box'; }
    public function get_title() { return __( 'باکس محتوای سئو', 'vardi-kit' ); }
    public function get_icon() { return 'eicon-editor-list-ul'; }
    public function get_categories() { return [ 'vardi-collection' ]; }

    public function get_style_depends() {
        return [ 'vardi-kit-seo-box' ];
    }

    public function get_script_depends() {
        return [ 'vardi-kit-seo-box' ];
    }

    private function _get_rgb_color_string( $color_string ) {
        if ( empty( $color_string ) ) return '255, 255, 255';

        if ( strpos( strtolower( trim( $color_string ) ), 'rgba' ) === 0 ) {
            preg_match( '/rgba\((\d+,\s*\d+,\s*\d+),?\s*[\d\.]*\)/', $color_string, $matches );
            return $matches[1] ?? '255, 255, 255';
        }

        if ( strpos( trim( $color_string ), '#' ) === 0 ) {
            $hex = ltrim( $color_string, '#' );
            if ( strlen( $hex ) === 3 ) {
                $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
                $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
                $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
            } elseif ( strlen( $hex ) === 6 ) {
                $r = hexdec( substr( $hex, 0, 2 ) );
                $g = hexdec( substr( $hex, 2, 2 ) );
                $b = hexdec( substr( $hex, 4, 2 ) );
            } else {
                return '255, 255, 255';
            }
            return "$r, $g, $b";
        }
        
        return '255, 255, 255';
    }

    protected function register_controls() {
        
        $this->start_controls_section( 'section_content', [ 'label' => __( 'محتوا', 'vardi-kit' ) ] );
        $this->add_control( 'content_text', [ 'label' => __( 'متن محتوا', 'vardi-kit' ), 'type' => Controls_Manager::WYSIWYG, 'dynamic' => [ 'active' => true ], 'default' => __( 'اینجا متن سئو قرار می‌گیرد...', 'vardi-kit' ) ] );
        $this->add_control( 'toggle_heading', [ 'label' => __( 'تنظیمات فعال‌ساز (Toggle)', 'vardi-kit' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
        $this->add_control( 'button_text_more', [ 'label' => __( 'متن (نمایش بیشتر)', 'vardi-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'ادامه مطلب', 'vardi-kit' ) ] );
        $this->add_control( 'icon_more', [ 'label' => __( 'آیکون (نمایش بیشتر)', 'vardi-kit' ), 'type' => Controls_Manager::ICONS, 'default' => [ 'value' => 'fas fa-chevron-down', 'library' => 'fa-solid' ] ] );
        $this->add_control( 'button_text_less', [ 'label' => __( 'متن (نمایش کمتر)', 'vardi-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'بستن', 'vardi-kit' ) ] );
        $this->add_control( 'icon_less', [ 'label' => __( 'آیکون (نمایش کمتر)', 'vardi-kit' ), 'type' => Controls_Manager::ICONS, 'default' => [ 'value' => 'fas fa-chevron-up', 'library' => 'fa-solid' ] ] );
        $this->end_controls_section();

        $this->start_controls_section( 'section_animation_settings', [ 'label' => __( 'تنظیمات انیمیشن', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE, ] );
        $this->add_control( 'animation_duration', [ 'label' => __( 'سرعت انیمیشن (ثانیه)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 's' => [ 'min' => 0.1, 'max' => 3, 'step' => 0.1 ] ], 'default' => [ 'unit' => 's', 'size' => 0.5 ], 'selectors' => [ '{{WRAPPER}} .seo-content-box' => '--sc-animation-duration: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_control( 'animation_easing', [ 'label' => __( 'نوع شتاب انیمیشن', 'vardi-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'ease-in-out', 'options' => [ 'ease' => 'Ease', 'ease-in-out' => 'Ease In Out', 'ease-in' => 'Ease In', 'ease-out' => 'Ease Out', 'linear' => 'Linear', ], 'selectors' => [ '{{WRAPPER}} .seo-content-box' => '--sc-animation-easing: {{VALUE}};' ] ] );
        $this->end_controls_section();

        $this->start_controls_section( 'section_style_content', [ 'label' => __( 'استایل محتوا', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE ] );
        $this->add_responsive_control( 'content_text_align', [ 'label' => __( 'چینش متن', 'vardi-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'right' => [ 'title' => __( 'راست' ), 'icon' => 'eicon-text-align-right' ], 'center' => [ 'title' => __( 'وسط' ), 'icon' => 'eicon-text-align-center' ], 'left' => [ 'title' => __( 'چپ' ), 'icon' => 'eicon-text-align-left' ], 'justify' => [ 'title' => __( 'منظم' ), 'icon' => 'eicon-text-align-justify' ] ], 'default' => 'right', 'selectors' => [ '{{WRAPPER}} .seo-content-box-text' => 'text-align: {{VALUE}};' ] ] );
        $this->add_control( 'text_color', [ 'label' => __( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-content-box-text' => 'color: {{VALUE}};' ] ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'typography', 'selector' => '{{WRAPPER}} .seo-content-box-text' ] );
        $this->add_control( 'hr_collapsed', [ 'type' => Controls_Manager::DIVIDER ] );
        $this->add_control( 'collapsed_heading', [ 'label' => __( 'تنظیمات حالت بسته', 'vardi-kit' ), 'type' => Controls_Manager::HEADING ] );
        $this->add_responsive_control( 'collapsed_height', [ 'label' => __( 'ارتفاع باکس (px)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => [ 'px' => [ 'min' => 20, 'max' => 500 ] ], 'default' => [ 'unit' => 'px', 'size' => 150 ] ] );
        $this->add_control( 'fade_effect_type', [ 'label' => esc_html__( 'افکت محو شونده', 'vardi-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'gradient_and_blur', 'options' => [ 'none' => esc_html__( 'هیچکدام', 'vardi-kit' ), 'gradient' => esc_html__( 'گرادینت (کلاسیک)', 'vardi-kit' ), 'blur' => esc_html__( 'تاری (مدرن)', 'vardi-kit' ), 'gradient_and_blur' => esc_html__( 'تاری و گرادینت (پیشنهادی)', 'vardi-kit' ), ], 'separator' => 'before', ] );
        
        // **FIXED**: Removed the problematic `selectors` from here. The color will be handled in the `render` method.
        $this->add_control( 'gradient_color', [ 'label' => __( 'رنگ گرادینت', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'default' => '#FFFFFF', 'condition' => [ 'fade_effect_type' => ['gradient', 'gradient_and_blur'] ], ] );
        
        $this->add_control( 'collapsed_blur_strength', [ 'label' => __( 'میزان تاری (px)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 30 ] ], 'default' => [ 'unit' => 'px', 'size' => 8 ], 'condition' => [ 'fade_effect_type' => ['blur', 'gradient_and_blur'] ], 'selectors' => [ '{{WRAPPER}} .seo-content-box' => '--sc-blur-px: {{SIZE}}{{UNIT}};' ], ] );
        $this->add_control( 'fade_height', [ 'label' => __( 'ارتفاع ناحیه افکت (px)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'default' => [ 'size' => 80 ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 200 ] ], 'selectors' => [ '{{WRAPPER}} .seo-content-box-text.collapsed::after' => 'height: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'fade_effect_type!' => 'none' ], ] );
        $this->end_controls_section();

        $this->start_controls_section( 'section_style_toggle', [ 'label' => __( 'استایل فعال‌ساز', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE ] );
        $this->add_control( 'toggle_type', [ 'label' => __( 'نوع فعال‌ساز', 'vardi-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'button', 'options' => [ 'button' => __( 'دکمه' ), 'text' => __( 'متن' ) ] ] );
        $this->add_responsive_control( 'toggle_align', [ 'label' => __( 'چینش افقی', 'vardi-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'flex-start' => [ 'title' => __( 'چپ' ), 'icon' => 'eicon-h-align-left' ], 'center' => [ 'title' => __( 'وسط' ), 'icon' => 'eicon-h-align-center' ], 'flex-end' => [ 'title' => __( 'راست' ), 'icon' => 'eicon-h-align-right' ], 'stretch' => [ 'title' => __( 'تمام عرض' ), 'icon' => 'eicon-h-align-stretch' ] ], 'default' => 'center', 'selectors' => [ '{{WRAPPER}} .seo-content-toggle-wrapper' => 'align-items: {{VALUE}};' ] ] );
        $this->add_control( 'icon_position', [ 'label' => __( 'موقعیت آیکون', 'vardi-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'row' => [ 'title' => __( 'قبل از متن' ), 'icon' => 'eicon-h-align-left' ], 'row-reverse' => [ 'title' => __( 'بعد از متن' ), 'icon' => 'eicon-h-align-right' ] ], 'default' => 'row-reverse', 'selectors' => [ '{{WRAPPER}} .seo-content-toggle' => 'flex-direction: {{VALUE}};' ] ] );
        $this->add_responsive_control( 'toggle_icon_size', [ 'label' => __( 'اندازه آیکون', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 6, 'max' => 100 ], 'em' => [ 'min' => 0.5, 'max' => 5, 'step' => 0.1 ] ], 'selectors' => [ '{{WRAPPER}} .seo-toggle-icon i' => 'font-size: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .seo-toggle-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'toggle_typography', 'selector' => '{{WRAPPER}} .seo-content-toggle' ] );
        $this->add_responsive_control( 'toggle_padding', [ 'label' => __( 'پدینگ دکمه', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'selectors' => [ '{{WRAPPER}} .seo-content-toggle.seo-toggle-type-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ], 'condition' => [ 'toggle_type' => 'button' ] ] );
        $this->start_controls_tabs( 'toggle_style_tabs' );
        $this->start_controls_tab( 'tab_toggle_normal', [ 'label' => __( 'عادی', 'vardi-kit' ) ] );
        $this->add_control( 'toggle_text_color', [ 'label' => __( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-toggle-text' => 'color: {{VALUE}};' ] ] );
        $this->add_control( 'toggle_icon_color', [ 'label' => __( 'رنگ آیکون', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-toggle-icon i' => 'color: {{VALUE}};', '{{WRAPPER}} .seo-toggle-icon svg' => 'fill: {{VALUE}};' ] ] );
        $this->add_control( 'button_background', [ 'label' => __( 'رنگ پس‌زمینه', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-content-toggle.seo-toggle-type-button' => 'background-color: {{VALUE}};' ], 'condition' => [ 'toggle_type' => 'button' ] ] );
        $this->end_controls_tab();
        $this->start_controls_tab( 'tab_toggle_hover', [ 'label' => __( 'هاور', 'vardi-kit' ) ] );
        $this->add_control( 'toggle_text_hover_color', [ 'label' => __( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-content-toggle:hover .seo-toggle-text' => 'color: {{VALUE}};' ] ] );
        $this->add_control( 'toggle_icon_hover_color', [ 'label' => __( 'رنگ آیکون', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-content-toggle:hover .seo-toggle-icon i' => 'color: {{VALUE}};', '{{WRAPPER}} .seo-content-toggle:hover .seo-toggle-icon svg' => 'fill: {{VALUE}};' ] ] );
        $this->add_control( 'button_hover_background', [ 'label' => __( 'رنگ پس‌زمینه', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .seo-content-toggle.seo-toggle-type-button:hover' => 'background-color: {{VALUE}};' ], 'condition' => [ 'toggle_type' => 'button' ] ] );
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        ob_start(); Icons_Manager::render_icon($settings['icon_more'], ['aria-hidden' => 'true']); $icon_more_html = ob_get_clean();
        ob_start(); Icons_Manager::render_icon($settings['icon_less'], ['aria-hidden' => 'true']); $icon_less_html = ob_get_clean();

        // **FIXED**: The misplaced `add_control` is removed.
        // We now generate the inline style here, which is the correct and stable method.
        $inline_style = '';
        if ( ! empty( $settings['gradient_color'] ) ) {
            $gradient_rgb_string = $this->_get_rgb_color_string( $settings['gradient_color'] );
            $inline_style = sprintf( '--sc-gradient-rgb: %s;', esc_attr( $gradient_rgb_string ) );
        }

        $this->add_render_attribute('wrapper', [
            'class' => 'seo-content-box',
            'data-effect-type' => $settings['fade_effect_type'],
            'data-desktop-height' => $settings['collapsed_height']['size'],
            'data-tablet-height' => $settings['collapsed_height_tablet']['size'] ?? '',
            'data-mobile-height' => $settings['collapsed_height_mobile']['size'] ?? '',
            'style' => $inline_style, // Apply the generated style
        ]);

        $toggle_class = ['seo-content-toggle', 'seo-toggle-type-' . $settings['toggle_type']];
        $this->add_render_attribute('toggle', [ 
            'class' => $toggle_class, 
            'role' => 'button', 'tabindex' => '0', 'aria-expanded' => 'false', 
            'data-more-text' => esc_attr($settings['button_text_more']), 
            'data-less-text' => esc_attr($settings['button_text_less']), 
            'data-icon-more-html' => base64_encode($icon_more_html), 
            'data-icon-less-html' => base64_encode($icon_less_html), 
        ]);
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <div class="seo-content-box-text collapsed">
                <?php echo $this->parse_text_editor($settings['content_text']); ?>
            </div>
            <div class="seo-content-toggle-wrapper">
                <div <?php echo $this->get_render_attribute_string('toggle'); ?>>
                    <span class="seo-toggle-icon"><?php echo $icon_more_html; ?></span>
                    <span class="seo-toggle-text"><?php echo esc_html($settings['button_text_more']); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
}