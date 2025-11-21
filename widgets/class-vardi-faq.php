<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Repeater;
use Elementor\Icons_Manager;

class Vardi_FAQ_Widget extends Widget_Base {

    public function get_name() { return 'vardi-faq'; }
    public function get_title() { return esc_html__( 'سوالات متداول / راهنما', 'vardi-kit' ); }
    public function get_icon() { return 'eicon-accordion'; }
    public function get_categories() { return [ 'vardi-collection' ]; }

    public function get_style_depends() {
        return [ 'vardi-kit-faq' ];
    }

    // بازگرداندن فایل JS، چون انیمیشن را با jQuery انجام می‌دهیم
    public function get_script_depends() {
        return [ 'vardi-kit-faq' ];
    }

    protected function register_controls() {

        $this->start_controls_section( 'section_content_options', [ 'label' => esc_html__( 'محتوا و ساختار', 'vardi-kit' ) ] );

        $this->add_control(
                'schema_type',
                [
                        'label' => esc_html__( 'نوع محتوا (مهم برای سئو)', 'vardi-kit' ),
                        'type' => Controls_Manager::SELECT,
                        'default' => 'faq',
                        'options' => [
                                'faq' => esc_html__( 'سوالات متداول (FAQ)', 'vardi-kit' ),
                                'howto' => esc_html__( 'راهنمای چگونه... (How-to)', 'vardi-kit' ),
                        ],
                        'style_transfer' => true,
                ]
        );

        $this->add_control(
                'title_html_tag',
                [
                        'label' => esc_html__( 'تگ HTML عنوان', 'vardi-kit' ),
                        'type' => Controls_Manager::SELECT,
                        'options' => [
                                'h2' => 'H2',
                                'h3' => 'H3',
                                'h4' => 'H4',
                                'h5' => 'H5',
                                'h6' => 'H6',
                                'div' => 'div',
                        ],
                        'default' => 'h3',
                        'separator' => 'before',
                ]
        );

        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        $this->start_controls_section( 'section_faq_items', [ 'label' => esc_html__( 'آیتم های پرسش و پاسخ', 'vardi-kit' ), 'condition' => [ 'schema_type' => 'faq' ] ] );
        $repeater_faq = new Repeater();
        $repeater_faq->add_control( 'question', [ 'label' => esc_html__( 'سوال', 'vardi-kit' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'سوال متداول چیست؟', 'vardi-kit' ), 'dynamic' => [ 'active' => true ], 'label_block' => true ] );
        $repeater_faq->add_control( 'answer', [ 'label' => esc_html__( 'پاسخ', 'vardi-kit' ), 'type' => Controls_Manager::WYSIWYG, 'default' => esc_html__( 'اینجا پاسخ سوال شما قرار می‌گیرد.', 'vardi-kit' ), 'dynamic' => [ 'active' => true ], 'show_label' => false ] );
        $repeater_faq->add_control( 'is_initially_open', [ 'label' => esc_html__( 'این آیتم در ابتدا باز باشد؟', 'vardi-kit' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ] );
        $this->add_control( 'faq_items', [ 'label' => esc_html__( 'لیست پرسش و پاسخ', 'vardi-kit' ), 'type' => Controls_Manager::REPEATER, 'fields' => $repeater_faq->get_controls(), 'default' => [ [ 'question' => esc_html__( 'آیتم شماره ۱', 'vardi-kit' ), 'answer' => esc_html__( 'پاسخ آیتم شماره یک.', 'vardi-kit' ) ] ], 'title_field' => '{{{ question }}}' ] );
        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        $this->start_controls_section( 'section_howto_items', [ 'label' => esc_html__( 'مراحل راهنما', 'vardi-kit' ), 'condition' => [ 'schema_type' => 'howto' ] ] );
        $this->add_control( 'howto_description', [ 'label' => esc_html__( 'توضیح کلی راهنما (برای سئو)', 'vardi-kit' ), 'type' => Controls_Manager::TEXTAREA, 'default' => esc_html__( 'در این راهنما یاد می‌گیرید که چگونه...', 'vardi-kit' ) ] );
        $repeater_howto = new Repeater();
        $repeater_howto->add_control( 'step_name', [ 'label' => esc_html__( 'عنوان مرحله', 'vardi-kit' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'عنوان مرحله ۱', 'vardi-kit' ), 'dynamic' => [ 'active' => true ], 'label_block' => true ] );
        $repeater_howto->add_control( 'step_detail', [ 'label' => esc_html__( 'جزئیات مرحله', 'vardi-kit' ), 'type' => Controls_Manager::WYSIWYG, 'default' => esc_html__( 'جزئیات و توضیحات این مرحله را اینجا بنویسید.', 'vardi-kit' ), 'dynamic' => [ 'active' => true ], 'show_label' => false ] );
        $repeater_howto->add_control( 'is_initially_open_howto', [ 'label' => esc_html__( 'این آیتم در ابتدا باز باشد؟', 'vardi-kit' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ] );
        $this->add_control( 'howto_items', [ 'label' => esc_html__( 'لیست مراحل', 'vardi-kit' ), 'type' => Controls_Manager::REPEATER, 'fields' => $repeater_howto->get_controls(), 'default' => [ [ 'step_name' => esc_html__( 'مرحله اول: آماده سازی', 'vardi-kit' ) ] ], 'title_field' => '{{{ step_name }}}' ] );
        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        $this->start_controls_section( 'section_general_settings', [ 'label' => esc_html__( 'تنظیمات کلی', 'vardi-kit' ) ] );
        $this->add_control( 'accordion_mode', [ 'label' => esc_html__( 'حالت آکاردئون', 'vardi-kit' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => esc_html__( 'فعال', 'vardi-kit' ), 'label_off' => esc_html__( 'غیرفعال', 'vardi-kit' ), 'return_value' => 'yes', 'default' => 'yes', 'description' => esc_html__( 'در حالت فعال، با باز شدن یک آیتم، آیتم‌های دیگر بسته می‌شوند.', 'vardi-kit' ) ] );
        $this->add_control( 'icon', [ 'label' => esc_html__( 'آیکون بسته', 'vardi-kit' ), 'type' => Controls_Manager::ICONS, 'separator' => 'before', 'default' => [ 'value' => 'eicon-plus', 'library' => 'eicons' ], 'skin' => 'inline', 'label_block' => false ] );
        $this->add_control( 'icon_active', [ 'label' => esc_html__( 'آیکون باز', 'vardi-kit' ), 'type' => Controls_Manager::ICONS, 'default' => [ 'value' => 'eicon-minus', 'library' => 'eicons' ], 'skin' => 'inline', 'label_block' => false, 'condition' => [ 'icon[value]!' => '' ] ] );
        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        $this->start_controls_section( 'section_animation_settings', [ 'label' => esc_html__( 'تنظیمات انیمیشن', 'vardi-kit' ) ] );
        $this->add_control( 'animation_duration', [ 'label' => esc_html__( 'سرعت انیمیشن (ثانیه)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 's' => [ 'min' => 0.1, 'max' => 2, 'step' => 0.1 ] ], 'default' => [ 'unit' => 's', 'size' => 0.4 ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-wrapper' => '--faq-animation-duration: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_control(
                'animation_easing',
                [
                        'label' => esc_html__( 'نوع شتاب انیمیشن', 'vardi-kit' ),
                        'type' => Controls_Manager::SELECT,
                        'default' => 'cubic-bezier(0.65, 0, 0.35, 1)',
                        'options' => [
                                'ease' => 'Ease (نرم)',
                                'ease-in-out' => 'Ease In Out (شروع و پایان نرم)',
                                'cubic-bezier(0.65, 0, 0.35, 1)' => 'Modern & Fluid (پیشنهادی)',
                                'linear' => 'Linear (خطی و یکنواخت)',
                        ],
                        'selectors' => [ '{{WRAPPER}} .vardi-faq-wrapper' => '--faq-animation-easing: {{VALUE}};' ],
                ]
        );
        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        // --- بخش‌های استایل بدون تغییر ---
        $this->start_controls_section( 'section_style_item', [ 'label' => esc_html__( 'آیتم', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE ] );
        $this->add_responsive_control( 'items_spacing', [ 'label' => esc_html__( 'فاصله بین آیتم‌ها', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-item' => 'margin-bottom: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'item_border', 'selector' => '{{WRAPPER}} .vardi-faq-item' ] );
        $this->add_responsive_control( 'item_border_radius', [ 'label' => esc_html__( 'انحنای کادر آیتم', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->end_controls_section(); // اصلاح شد: this.-> -> $this->

        $this->start_controls_section( 'section_style_title', [ 'label' => esc_html__( 'عنوان (سوال/مرحله)', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .vardi-faq-title' ] );
        $this->add_responsive_control( 'title_padding', [ 'label' => esc_html__( 'پدینگ', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->add_group_control(
                Group_Control_Border::get_type(),
                [
                        'name' => 'title_border',
                        'selector' => '{{WRAPPER}} .vardi-faq-question',
                        'fields_options' => [ 'border' => [ 'default' => 'none', ], ],
                ]
        );
        $this->add_responsive_control( 'title_border_radius', [ 'label' => esc_html__( 'انحنای کادر عنوان', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-question' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->start_controls_tabs( 'tabs_title_style' );
        $this->start_controls_tab( 'tab_title_normal', [ 'label' => esc_html__( 'عادی', 'vardi-kit' ) ] );
        $this->add_control( 'title_color', [ 'label' => esc_html__( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-faq-title' => 'color: {{VALUE}};' ] ] );
        $this->add_control( 'title_icon_color', [ 'label' => esc_html__( 'رنگ آیکون', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-faq-icon-closed i' => 'color: {{VALUE}};', '{{WRAPPER}} .vardi-faq-icon-closed svg' => 'fill: {{VALUE}};' ] ] );
        $this->add_control( 'title_background_color', [ 'label' => esc_html__( 'رنگ پس‌زمینه', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-faq-question' => 'background-color: {{VALUE}};' ] ] );
        $this->end_controls_tab();
        $this->start_controls_tab( 'tab_title_active', [ 'label' => esc_html__( 'فعال و هاور', 'vardi-kit' ) ] );
        $this->add_control( 'title_color_active', [ 'label' => esc_html__( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-item-active .vardi-faq-title, {{WRAPPER}} .vardi-faq-question:hover .vardi-faq-title' => 'color: {{VALUE}};' ] ] );
        $this->add_control( 'title_icon_color_active', [ 'label' => esc_html__( 'رنگ آیکون', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-item-active .vardi-faq-icon i, {{WRAPPER}} .vardi-faq-question:hover .vardi-faq-icon i' => 'color: {{VALUE}};', '{{WRAPPER}} .vardi-item-active .vardi-faq-icon svg, {{WRAPPER}} .vardi-faq-question:hover .vardi-faq-icon svg' => 'fill: {{VALUE}};' ] ] );
        $this->add_control( 'title_background_color_active', [ 'label' => esc_html__( 'رنگ پس‌زمینه', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-item-active .vardi-faq-question, {{WRAPPER}} .vardi-faq-question:hover' => 'background-color: {{VALUE}};' ] ] );
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section( 'section_style_content', [ 'label' => esc_html__( 'محتوا (پاسخ/جزئیات)', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE ] );
        $this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'content_typography', 'selector' => '{{WRAPPER}} .vardi-faq-answer' ] );
        $this->add_control( 'content_color', [ 'label' => esc_html__( 'رنگ متن', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-faq-answer' => 'color: {{VALUE}};' ] ] );
        $this->add_control( 'content_background_color', [ 'label' => esc_html__( 'رنگ پس‌زمینه', 'vardi-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .vardi-faq-answer-wrapper' => 'background-color: {{VALUE}};' ] ] );
        $this->add_responsive_control( 'content_spacing_top', [ 'label' => esc_html__( 'فاصله از عنوان (باکس محتوا)', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-answer-wrapper' => 'margin-top: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'content_padding', [ 'label' => esc_html__( 'پدینگ', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-answer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'content_border', 'selector' => '{{WRAPPER}} .vardi-faq-answer-wrapper' ] );
        $this->add_responsive_control( 'content_border_radius', [ 'label' => esc_html__( 'انحنای کادر محتوا', 'vardi-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-answer-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
        $this->end_controls_section();

        $this->start_controls_section( 'section_style_icon', [ 'label' => esc_html__( 'آیکون', 'vardi-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => [ 'icon[value]!' => '' ] ] );
        $this->add_control( 'icon_align', [ 'label' => esc_html__( 'چینش', 'vardi-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'left' => [ 'title' => esc_html__( 'چپ', 'vardi-kit' ), 'icon' => 'eicon-h-align-left' ], 'right' => [ 'title' => esc_html__( 'راست', 'vardi-kit' ), 'icon' => 'eicon-h-align-right' ] ], 'default' => is_rtl() ? 'left' : 'right', 'toggle' => false ] );
        $this->add_responsive_control( 'icon_size', [ 'label' => esc_html__( 'اندازه', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'default' => [ 'size' => 16, 'unit' => 'px' ], 'range' => [ 'px' => [ 'min' => 6, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .vardi-faq-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};' ] ] );
        $this->add_responsive_control( 'icon_spacing', [ 'label' => esc_html__( 'فاصله از متن', 'vardi-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ 'body:not(.rtl) {{WRAPPER}} .vardi-faq-icon.vardi-faq-icon-left' => 'margin-right: {{SIZE}}{{UNIT}};', 'body:not(.rtl) {{WRAPPER}} .vardi-faq-icon.vardi-faq-icon-right' => 'margin-left: {{SIZE}}{{UNIT}};', 'body.rtl {{WRAPPER}} .vardi-faq-icon.vardi-faq-icon-left' => 'margin-left: {{SIZE}}{{UNIT}};', 'body.rtl {{WRAPPER}} .vardi-faq-icon.vardi-faq-icon-right' => 'margin-right: {{SIZE}}{{UNIT}};' ] ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $schema_type = $settings['schema_type'];

        // محاسبه سرعت انیمیشن برای data attribute (ثانیه به میلی‌ثانیه)
        $animation_speed = $settings['animation_duration']['size'] * 1000;

        $this->add_render_attribute( 'wrapper', [
                'class' => 'vardi-faq-wrapper',
                'itemscope' => '',
                'data-accordion-mode' => $settings['accordion_mode'],
                'data-animation-speed' => $animation_speed, // ارسال سرعت به JS
        ]);

        if ( 'faq' === $schema_type ) {
            $this->add_render_attribute( 'wrapper', 'itemtype', 'https://schema.org/FAQPage' );
            $this->render_faq();
        } else {
            $this->add_render_attribute( 'wrapper', 'itemtype', 'https://schema.org/HowTo' );
            $this->render_howto();
        }
    }

    private function render_faq() {
        $settings = $this->get_settings_for_display();
        if ( empty( $settings['faq_items'] ) ) { return; }

        $title_tag = $settings['title_html_tag'];
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <?php foreach ( $settings['faq_items'] as $index => $item ) :
                $item_key = 'item-' . $index;
                $this->add_render_attribute($item_key, [
                        'class' => 'vardi-faq-item',
                        'itemprop' => 'mainEntity', 'itemscope' => '', 'itemtype' => 'https://schema.org/Question'
                ], null, true);

                if ( 'yes' === $item['is_initially_open'] ) {
                    $this->add_render_attribute($item_key, 'class', 'vardi-item-active');
                }
                ?>
                <div <?php echo $this->get_render_attribute_string($item_key); ?>>
                    <?php $this->render_summary_content( $item['question'], $title_tag, 'name', $index ); ?>
                    <div class="vardi-faq-answer-wrapper" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                        <div class="vardi-faq-answer" itemprop="text">
                            <?php echo $this->parse_text_editor( $item['answer'] ); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_howto() {
        $settings = $this->get_settings_for_display();
        if ( empty( $settings['howto_items'] ) ) { return; }

        $title_tag = $settings['title_html_tag'];
        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); ?>>
            <meta itemprop="description" content="<?php echo esc_attr( $settings['howto_description'] ); ?>">
            <?php foreach ( $settings['howto_items'] as $index => $item ) :
                $item_key = 'item-' . $index;
                $this->add_render_attribute($item_key, [
                        'class' => 'vardi-faq-item',
                        'itemprop' => 'step', 'itemscope' => '', 'itemtype' => 'https://schema.org/HowToStep'
                ], null, true);

                if ( 'yes' === $item['is_initially_open_howto'] ) {
                    $this->add_render_attribute($item_key, 'class', 'vardi-item-active');
                }
                ?>
                <div <?php echo $this->get_render_attribute_string($item_key); ?>>
                    <?php $this->render_summary_content( $item['step_name'], $title_tag, 'name', $index ); ?>
                    <div class="vardi-faq-answer-wrapper" itemprop="text">
                        <div class="vardi-faq-answer">
                            <?php echo $this->parse_text_editor( $item['step_detail'] ); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    // بازگشت به <summary> به <div>
    private function render_summary_content( $title, $title_tag, $itemprop, $index ) {
        $settings = $this->get_settings_for_display();

        $has_active_icon = ! empty( $settings['icon_active']['value'] );

        $icon_tag_key = 'icon-wrapper-' . $index;
        $this->add_render_attribute( $icon_tag_key, 'class', 'vardi-faq-icon' );

        if ( $has_active_icon ) {
            $this->add_render_attribute( $icon_tag_key, 'class', 'vardi-faq-icon-swap' );
        }

        if ( 'left' === $settings['icon_align'] ) {
            $this->add_render_attribute( $icon_tag_key, 'class', 'vardi-faq-icon-left' );
        } else {
            $this->add_render_attribute( $icon_tag_key, 'class', 'vardi-faq-icon-right' );
        }
        ?>
        <div class="vardi-faq-question">
        <?php if ( !empty($settings['icon']['value']) && 'left' === $settings['icon_align'] ): ?>
            <span <?php echo $this->get_render_attribute_string( $icon_tag_key ); ?>>
                    <span class="vardi-faq-icon-closed"><?php Icons_Manager::render_icon( $settings['icon'] ); ?></span>
                    <?php if ( $has_active_icon ): ?>
                        <span class="vardi-faq-icon-opened"><?php Icons_Manager::render_icon( $settings['icon_active'] ); ?></span>
                    <?php endif; ?>
                </span>
        <?php endif; ?>

        <<?php echo $title_tag; ?> class="vardi-faq-title" itemprop="<?php echo esc_attr($itemprop); ?>"><?php echo $title; ?></<?php echo $title_tag; ?>>

        <?php if ( !empty($settings['icon']['value']) && 'right' === $settings['icon_align'] ): ?>
            <span <?php echo $this->get_render_attribute_string( $icon_tag_key ); ?>>
                    <span class="vardi-faq-icon-closed"><?php Icons_Manager::render_icon( $settings['icon'] ); ?></span>
                    <?php if ( $has_active_icon ): ?>
                        <span class="vardi-faq-icon-opened"><?php Icons_Manager::render_icon( $settings['icon_active'] ); ?></span>
                    <?php endif; ?>
                </span>
        <?php endif; ?>
        </div>
        <?php
        $this->remove_render_attribute( $icon_tag_key );
    }
}