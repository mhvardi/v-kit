<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vardi_Features {

	private static $_instance = null;
	private $options;
	private $login_slug = null;

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		$this->options = get_option( 'vardi_kit_options', [] );
		$this->login_slug = $this->options['change_login_url'] ?? null;
		$this->init_features();
	}

	private function is_enabled($feature) {
		return !empty($this->options[$feature]);
	}

        private function init_features() {
                // --- General Features ---
                if ($this->is_enabled('enable_shamsi_date')) $this->load_shamsi_date();
		$default_footer = 'آژانس خلاقیت وردی';
		$footer_text = !empty($this->options['admin_footer_text']) ? $this->options['admin_footer_text'] : $default_footer;
		add_filter('admin_footer_text', function() use ($footer_text) { return sanitize_text_field($footer_text); });

		// --- Appearance Features ---
                if ($this->is_enabled('enable_custom_login')) {
                        add_action('login_enqueue_scripts', [$this, 'custom_login_styles']);
                        add_filter('login_headerurl', [$this, 'custom_login_logo_url']);
                        add_filter('login_headertext', [$this, 'custom_login_logo_url_title']);
                }
                if (!empty($this->options['admin_font'])) {
                        add_action('admin_enqueue_scripts', [$this, 'load_custom_admin_font']);
                        add_action('elementor/editor/after_enqueue_styles', [$this, 'apply_editor_font_styles']);
                }
                if ( class_exists( 'WooCommerce' ) ) {
                        add_action( 'admin_head', [ $this, 'style_shop_order_statuses' ] );
                }
                if ($this->is_enabled('enable_dashboard_banner')) {
                        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets'], 99);
                }

		// --- **MODIFIED (LOGIN FIX)**: منطق تغییر آدرس ورود بازبینی شد ---
		if (!empty($this->login_slug)) {
			// 1. ثبت قانون بازنویسی (در init اجرا می‌شود تا زود ثبت شود)
			add_action('init', [$this, 'register_login_rewrite_rule']);

			// 2. فیلترها برای ساختن URL های جدید
			add_filter('login_url', [$this, 'filter_login_url'], 10, 2);
			add_filter('logout_url', [$this, 'filter_logout_url_fix'], 10, 2);
			add_filter('lostpassword_url', [$this, 'filter_lostpassword_url'], 10, 2);
			add_filter('register_url', [$this, 'filter_register_url']);

			// 3. فیلتر مهم site_url
			add_filter('site_url', [$this, 'filter_site_url_for_login_php'], 10, 4);

			// 4. مسدود کردن دسترسی مستقیم به wp-login.php (در template_redirect اجرا می‌شود)
			add_action('template_redirect', [$this, 'block_old_login_access']);

			// **NEW (LOGIN FIX)**: اطمینان از اینکه wp-admin هم به آدرس جدید هدایت می‌شود
			add_action('init', [$this, 'redirect_wp_admin_to_new_login']);
		}
		// --- پایان بخش بازنویسی شده ---

		if ($this->is_enabled('enable_login_protection')) $this->init_login_protection();
		if ($this->is_block_request_enabled()) {
			add_filter('pre_http_request', [$this, 'block_external_requests'], 10, 3);
		}
		if ($this->is_enabled('disable_xmlrpc')) add_filter('xmlrpc_enabled', '__return_false');
		if ($this->is_enabled('disable_rest_api')) add_filter('rest_authentication_errors', [$this, 'limit_rest_api_access']);

		// --- Performance & SEO Features ---
                if ($this->is_enabled('disable_emojis')) $this->disable_emojis();
                if ($this->is_enabled('disable_embeds')) $this->disable_embeds();
                if (!empty($this->options['control_heartbeat'])) $this->control_heartbeat();

                if ( ! empty( $this->options['enable_ai_inspector'] ) && ! empty( $this->options['ai_api_key'] ) ) {
                        $this->init_ai_inspector();
                }
        }

	private function is_block_request_enabled() {
		if (is_admin() && function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ($screen && $screen->id === 'update-core') {
				return false;
			}
		}
		return $this->is_enabled('block_wordpress_api') || !empty($this->options['block_external_requests']);
	}

        public function load_shamsi_date() {
                $has_conflict = function_exists('wp_is_jalali') || class_exists('parsidate') || function_exists('jdate') || function_exists('tr_num');
                if ( $has_conflict ) {
                        // فقط هشدار بده تا مدیر در جریان باشد، اما قابلیت را غیرفعال نکن.
                        set_transient( 'vardi_kit_shamsi_conflict_notice', 1, DAY_IN_SECONDS );
                }

                if ( ! function_exists( 'jdate' ) && file_exists( VARDI_KIT_PLUGIN_PATH . 'jdf.php' ) ) {
                        require_once VARDI_KIT_PLUGIN_PATH . 'jdf.php';
                }

                if ( function_exists( 'jdate' ) ) {

                        $convert = function( $format, $timestamp, $fallback ) {
                                if ( ! function_exists( 'jdate' ) ) { return $fallback; }
                                if ( $timestamp instanceof DateTimeInterface ) { $timestamp = $timestamp->getTimestamp(); }
                                if ( empty( $timestamp ) && ! is_numeric( $timestamp ) ) { return $fallback; }
                                $ts = is_numeric( $timestamp ) ? (int) $timestamp : strtotime( (string) $timestamp );
                                if ( empty( $ts ) ) { return $fallback; }
                                return jdate( $format, $ts, '', '', 'fa' );
                        };

                        add_filter( 'date_i18n', function( $date_i18n_default, $format, $gmt_timestamp, $is_gmt ) use ( $convert ) {
                                if ( empty( $gmt_timestamp ) || ! is_numeric( $gmt_timestamp ) ) { return $date_i18n_default; }
                                return $convert( $format, $gmt_timestamp, $date_i18n_default );
                        }, 10, 4 );

                        add_filter( 'time_i18n', function( $translated, $format, $timestamp, $gmt ) use ( $convert ) {
                                $ts = $timestamp;
                                if ( $timestamp instanceof DateTimeInterface ) {
                                        $ts = $timestamp->getTimestamp();
                                } elseif ( ! is_numeric( $timestamp ) ) {
                                        $ts = strtotime( (string) $timestamp );
                                }
                                return $convert( $format ?: get_option( 'time_format' ), $ts, $translated );
                        }, 10, 4 );

                        add_filter( 'wp_date', function( $formatted, $format, $timestamp, $timezone ) use ( $convert ) {
                                $ts = $timestamp;
                                if ( $timestamp instanceof DateTimeInterface ) {
                                        $ts = $timestamp->getTimestamp();
                                } elseif ( ! is_numeric( $timestamp ) ) {
                                        $ts = strtotime( (string) $timestamp );
                                }
                                return $convert( $format ?: get_option( 'date_format' ), $ts, $formatted );
                        }, 10, 4 );

                        add_filter( 'mysql2date', function( $date, $format, $mysql, $translate ) use ( $convert ) {
                                return $convert( $format, strtotime( $mysql ), $date );
                        }, 10, 4 );

                        add_filter('get_the_date', function($date, $format, $post) use ( $convert ) {
                                if ( !is_a($post, 'WP_Post') ) { return $date; }
                                $timestamp = get_post_timestamp($post, 'gmt');
                                if ( !$timestamp ) return $date;
                                return $convert(empty($format) ? get_option('date_format') : $format, $timestamp, $date);
                        }, 10, 3);
                        add_filter('get_the_time', function($time, $format, $post) use ( $convert ) {
                                if ( !is_a($post, 'WP_Post') ) { return $time; }
                                $timestamp = get_post_timestamp($post, 'gmt');
                                if ( !$timestamp ) return $time;
                                return $convert(empty($format) ? get_option('time_format') : $format, $timestamp, $time);
                        }, 10, 3);
                        add_filter('get_the_modified_date', function($date, $format, $post) use ( $convert ) {
                                if ( !is_a($post, 'WP_Post') ) { return $date; }
                                $timestamp = get_post_modified_timestamp($post, 'gmt');
                                if ( !$timestamp ) return $date;
                                return $convert(empty($format) ? get_option('date_format') : $format, $timestamp, $date);
                        }, 10, 3);
                        add_filter('get_comment_date', function($date, $format, $comment) use ( $convert ) {
                                if ( !is_a($comment, 'WP_Comment') ) { return $date; }
                                $timestamp = get_comment_timestamp($comment, 'gmt');
                                if ( !$timestamp ) return $date;
                                return $convert(empty($format) ? get_option('date_format') : $format, $timestamp, $date);
                        }, 10, 3);
                        add_filter('get_comment_time', function($time, $format, $comment) use ( $convert ) {
                                if ( !is_a($comment, 'WP_Comment') ) { return $time; }
                                $timestamp = get_comment_timestamp($comment, 'gmt');
                                if ( !$timestamp ) return $time;
                                return $convert(empty($format) ? get_option('time_format') : $format, $timestamp, $time);
                        }, 10, 3);

                        add_filter('woocommerce_format_datetime', function($formatted, $datetime, $format) use ( $convert ) {
                                if ( !class_exists('WC_DateTime') || !($datetime instanceof WC_DateTime) ) { return $formatted; }
                                $timestamp = $datetime->getTimestamp();
                                $format = $format ? $format : wc_date_format();
                                return $convert($format, $timestamp, $formatted);
                        }, 10, 3);
                }
        }

	public function custom_login_styles() {
		$logo_url = $this->options['login_logo_url'] ?? '';
		if (empty($logo_url) && function_exists('get_custom_logo')) {
			$custom_logo_id = get_theme_mod('custom_logo');
			if ($custom_logo_id) $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
		}
		$logo_url = $logo_url ? esc_url($logo_url) : admin_url('images/w-logo-white.png');
		echo "<style>
            body.login { background: #e0e0e0; font-family: 'Peyda', 'IRANSans', sans-serif; }
            #login { width: 420px !important; padding: 40px 20px !important; background: #e0e0e0; border-radius: 15px; box-shadow: 20px 20px 60px #bebebe, -20px -20px 60px #ffffff; }
            #login h1 a, .login h1 a { background-image: url('{$logo_url}'); width: 150px; height: 80px; background-size: contain; margin: 0 auto 30px auto; }
            #loginform { background: transparent; border: none; box-shadow: none; padding: 0; }
            .login #nav, .login #backtoblog { text-align: center; }
            #loginform .input, #loginform input[type=text], #loginform input[type=password] { font-family: inherit; border: none !important; border-radius: 8px !important; background: #e0e0e0 !important; box-shadow: inset 5px 5px 10px #bebebe, inset -5px -5px 10px #ffffff !important; color: #555 !important; }
            #loginform .submit .button-primary { font-family: inherit !important; width: 100% !important; background: #34495e !important; color: #fff !important; border: none !important; border-radius: 8px !important; box-shadow: 5px 5px 10px #bebebe, -5px -5px 10px #ffffff !important; transition: all 0.3s ease !important; }
            #loginform .submit .button-primary:hover { background: #2c3e50 !important; box-shadow: none !important; }
        </style>";
	}

	public function custom_login_logo_url() { return home_url('/'); }
	public function custom_login_logo_url_title() { return get_bloginfo('name'); }

	public function load_custom_admin_font() {
		if (empty($this->options['admin_font'])) return;
		$font_file = $this->options['admin_font'];
		$font_url = VARDI_KIT_PLUGIN_URL . 'fonts/' . $font_file;
		$style = "@font-face { font-family: 'VardiAdminFont'; src: url('{$font_url}'); } body.wp-admin, #wpadminbar, .wp-core-ui .button, h1, h2, h3, h4, h5, h6, #adminmenu a, .wrap, th, td, label, input, textarea, select { font-family: 'VardiAdminFont', sans-serif !important; }";
		wp_add_inline_style('wp-admin', $style);
	}

        public function apply_editor_font_styles() {
                if (empty($this->options['admin_font'])) return;
                $font_file = $this->options['admin_font'];
                $font_url = VARDI_KIT_PLUGIN_URL . 'fonts/' . $font_file;
                echo "<style> @font-face { font-family: 'VardiAdminFont'; src: url('{$font_url}'); } .elementor-editor-active .elementor-panel, .elementor-editor-active .elementor-panel .elementor-control-title, .elementor-editor-active .elementor-panel .elementor-control-input-wrapper, .elementor-editor-active .select2-container .select2-selection__rendered { font-family: 'VardiAdminFont', sans-serif !important; } </style>";
        }

        public function style_shop_order_statuses() {
                if ( ! function_exists( 'get_current_screen' ) ) { return; }
                $screen = get_current_screen();
                if ( ! $screen || $screen->id !== 'edit-shop_order' ) { return; }
                echo "<style>
.post-type-shop_order .order-status {border-radius: 8px;font-weight: 500;font-size: 12px;display: inline-block;text-align: center;max-width: 100%;width: 100%;box-sizing: border-box;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
@media (max-width: 600px) {.post-type-shop_order .order-status {font-size: 11px;padding: 4px 6px;}}
.post-type-shop_order .order-status.status-processing { background:linear-gradient(45deg,#007bff,#409eff);color:#fff; }
.post-type-shop_order .order-status.status-pending    { background:linear-gradient(45deg,#ffce3d,#ffb300);color:#000; }
.post-type-shop_order .order-status.status-completed  { background:linear-gradient(45deg,#28a745,#4cd964);color:#fff; }
.post-type-shop_order .order-status.status-cancelled  { background:linear-gradient(45deg,#dc3545,#ff4b5c);color:#fff; }
.post-type-shop_order .order-status.status-failed     { background:linear-gradient(45deg,#6c757d,#999);color:#fff; }
.post-type-shop_order .order-status.status-on-hold    { background:linear-gradient(45deg,#17a2b8,#00c4cc);color:#fff; }
</style>";
        }

        private function init_ai_inspector() {
                add_action( 'admin_bar_menu', [ $this, 'add_ai_toolbar_button' ], 100 );
                add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_ai_assets' ] );
                add_action( 'wp_ajax_vardi_ai_analyze_page', [ $this, 'handle_ai_analysis' ] );
        }

        public function add_ai_toolbar_button( $admin_bar ) {
                if ( is_admin() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
                        return;
                }
                $admin_bar->add_node( [
                        'id'    => 'vardi-ai-inspect',
                        'title' => 'تحلیل صفحه توسط AI',
                        'href'  => '#',
                        'meta'  => [ 'class' => 'vardi-ai-inspect-toggle' ],
                ] );
        }

        public function enqueue_ai_assets( $hook_suffix ) {
                if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
                        return;
                }

                wp_enqueue_script( 'vardi-ai-inspector', VARDI_KIT_PLUGIN_URL . 'assets/js/ai-inspector.js', [ 'jquery' ], VARDI_KIT_VERSION, true );
                $base_url = ! empty( $this->options['ai_base_url'] ) ? $this->options['ai_base_url'] : 'https://api.gapgpt.app/v1';
                $model    = ! empty( $this->options['ai_model'] ) ? $this->options['ai_model'] : 'gpt-4o-mini';
                wp_localize_script( 'vardi-ai-inspector', 'vardiAiInspector', [
                        'nonce'      => wp_create_nonce( 'vardi_ai_inspect' ),
                        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                        'baseUrl'    => esc_url_raw( $base_url ),
                        'model'      => sanitize_text_field( $model ),
                        'title'      => 'تحلیل صفحه توسط AI',
                ] );

                $inline_css = '.vardi-ai-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:99999;display:flex;align-items:center;justify-content:center;}.vardi-ai-modal{background:#fff;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:880px;max-width:94%;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;font-family:inherit;}.vardi-ai-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #dcdcde;background:linear-gradient(135deg,#f6f7fb,#eef2ff);}.vardi-ai-body{padding:16px;overflow:auto;}.vardi-ai-footer{padding:12px 16px;border-top:1px solid #dcdcde;display:flex;align-items:center;gap:10px;background:#f9f9f9;}.vardi-ai-close{cursor:pointer;font-size:18px;}.vardi-ai-progress{display:flex;align-items:center;gap:8px;}.vardi-ai-loading{width:16px;height:16px;border:2px solid #ccc;border-top-color:#2271b1;border-radius:50%;animation:vardiSpin 1s linear infinite;}@keyframes vardiSpin{to{transform:rotate(360deg);}}.vardi-ai-score-card{display:flex;flex-direction:column;gap:10px;background:linear-gradient(135deg,#eef9ff,#f5f7ff);border:1px solid #d7e5ff;border-radius:10px;padding:12px;}.vardi-ai-score-bar{height:10px;background:#e6e7eb;border-radius:999px;overflow:hidden;}.vardi-ai-score-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#2ecc71,#27ae60);}.vardi-ai-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}.vardi-ai-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e6e7eb;border-radius:8px;overflow:hidden;}.vardi-ai-table th,.vardi-ai-table td{padding:10px;border-bottom:1px solid #eef0f4;text-align:right;font-size:13px;}.vardi-ai-pill{display:inline-block;padding:4px 8px;border-radius:8px;background:#f0f4ff;color:#1d2327;font-size:12px;margin-left:4px;}.vardi-ai-section{background:#fff;border:1px solid #e6e7eb;border-radius:10px;padding:12px;}.vardi-ai-section h4{margin:0 0 6px;font-size:14px;}.vardi-ai-badges{display:flex;flex-wrap:wrap;gap:6px;}.vardi-ai-chart-competitor{display:flex;align-items:center;gap:8px;margin:4px 0;}.vardi-ai-chart-competitor .bar{height:8px;border-radius:6px;background:linear-gradient(90deg,#ff9a3c,#ff4d4d);}';
                wp_register_style( 'vardi-ai-inline', false );
                wp_enqueue_style( 'vardi-ai-inline' );
                wp_add_inline_style( 'vardi-ai-inline', $inline_css );
        }

        public function handle_ai_analysis() {
                if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'vardi_ai_inspect' ) ) {
                        wp_send_json_error( 'درخواست نامعتبر است.' );
                }
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_send_json_error( 'دسترسی کافی ندارید.' );
                }

                $api_key = $this->options['ai_api_key'] ?? '';
                if ( empty( $api_key ) ) {
                        wp_send_json_error( 'کلید API تنظیم نشده است.' );
                }

                $base_url = rtrim( $this->options['ai_base_url'] ?? 'https://api.gapgpt.app/v1', '/' ) . '/';
                $model    = ! empty( $this->options['ai_model'] ) ? $this->options['ai_model'] : 'gpt-4o-mini';
                $page_url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
                $html_raw = wp_unslash( $_POST['html'] ?? '' );
                $html_excerpt = wp_html_excerpt( $html_raw, 60000, '...' );

                $post_id = url_to_postid( $page_url );
                $rank_math_meta = [
                        'is_active'          => class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ),
                        'focus_keyword'      => $post_id ? ( get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ?: get_post_meta( $post_id, '_rank_math_focus_keyword', true ) ) : '',
                        'secondary_keywords' => $post_id ? ( get_post_meta( $post_id, 'rank_math_secondary_focus_keyword', true ) ?: get_post_meta( $post_id, '_rank_math_secondary_focus_keyword', true ) ) : '',
                        'seo_score'          => $post_id ? get_post_meta( $post_id, 'rank_math_seo_score', true ) : '',
                ];

                $structure_hint = 'خروجی را فقط به صورت JSON با کلیدهای زیر بده: {"score": عدد 0 تا 100, "summary": متن کوتاه, "strengths": [], "issues": [], "suggestions": [], "keywords": {"focus": [], "secondary": []}, "rank_math": {"active": bool, "focus": "", "secondary": []}, "competitors": [{"keyword": "", "gap": "", "action": ""}] }. از Markdown استفاده نکن.';

                $messages = [
                        [ 'role' => 'system', 'content' => 'شما یک متخصص سئو و Core Web Vitals هستید. همیشه آخرین استانداردهای گوگل و راهنمای سئو رنگ‌مث را لحاظ کن.' ],
                        [ 'role' => 'user', 'content' => "{$structure_hint}\nآدرس صفحه: {$page_url}\nHTML نمونه:\n{$html_excerpt}\nاطلاعات Rank Math: " . wp_json_encode( $rank_math_meta ) ],
                ];

                $body = [
                        'model'    => $model,
                        'messages' => $messages,
                        'max_tokens' => 400,
                ];

                $response = wp_remote_post( $base_url . 'chat/completions', [
                        'headers' => [
                                'Content-Type'  => 'application/json',
                                'Authorization' => 'Bearer ' . $api_key,
                        ],
                        'body'    => wp_json_encode( $body ),
                        'timeout' => 45,
                ] );

                if ( is_wp_error( $response ) ) {
                        wp_send_json_error( 'خطای ارتباط با GapGPT: ' . $response->get_error_message() );
                }

                $code      = wp_remote_retrieve_response_code( $response );
                $resp_body = wp_remote_retrieve_body( $response );
                $body_json = json_decode( $resp_body, true );

                if ( 200 !== $code ) {
                        $error_msg = $body_json['error']['message'] ?? wp_remote_retrieve_response_message( $response ) ?? 'پاسخ نامشخص از سرور AI دریافت شد.';
                        wp_send_json_error( 'خطای سرور AI: ' . $error_msg );
                }

                $content = $body_json['choices'][0]['message']['content'] ?? $body_json['choices'][0]['text'] ?? '';
                if ( empty( $content ) ) {
                        $fallback = $body_json['error']['message'] ?? 'پاسخ نامعتبر از سرور AI دریافت شد.';
                        wp_send_json_error( $fallback );
                }

                $decoded = json_decode( $content, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                        $normalized = $this->normalize_ai_payload( $decoded, $rank_math_meta );
                        wp_send_json_success( [ 'structured' => $normalized ] );
                }

                wp_send_json_success( [ 'raw' => wp_kses_post( $content ) ] );
        }

        private function normalize_ai_payload( array $payload, array $rank_math_meta ) {
                $sanitize_list = function( $value ) {
                        if ( empty( $value ) ) { return []; }
                        if ( is_string( $value ) ) { $value = preg_split( '/\r\n|\r|\n|,/', $value ); }
                        $value = array_filter( array_map( [ $this, 'sanitize_ai_string' ], (array) $value ) );
                        return array_values( $value );
                };

                $score = isset( $payload['score'] ) ? intval( $payload['score'] ) : 0;
                $score = max( 0, min( 100, $score ) );

                $rank_math_data = [
                        'active'    => ! empty( $payload['rank_math']['active'] ) || ! empty( $rank_math_meta['is_active'] ),
                        'focus'     => $this->sanitize_ai_string( $payload['rank_math']['focus'] ?? $rank_math_meta['focus_keyword'] ?? '' ),
                        'secondary' => $sanitize_list( $payload['rank_math']['secondary'] ?? $rank_math_meta['secondary_keywords'] ?? [] ),
                        'score'     => $this->sanitize_ai_string( $rank_math_meta['seo_score'] ?? '' ),
                ];

                return [
                        'score'       => $score,
                        'summary'     => $this->sanitize_ai_string( $payload['summary'] ?? '' ),
                        'strengths'   => $sanitize_list( $payload['strengths'] ?? [] ),
                        'issues'      => $sanitize_list( $payload['issues'] ?? [] ),
                        'suggestions' => $sanitize_list( $payload['suggestions'] ?? [] ),
                        'keywords'    => [
                                'focus'     => $sanitize_list( $payload['keywords']['focus'] ?? ( $rank_math_meta['focus_keyword'] ? [ $rank_math_meta['focus_keyword'] ] : [] ) ),
                                'secondary' => $sanitize_list( $payload['keywords']['secondary'] ?? ( $rank_math_meta['secondary_keywords'] ? [ $rank_math_meta['secondary_keywords'] ] : [] ) ),
                        ],
                        'rank_math'   => $rank_math_data,
                        'competitors' => array_map( function( $item ) {
                                return [
                                        'keyword' => $this->sanitize_ai_string( $item['keyword'] ?? '' ),
                                        'gap'     => $this->sanitize_ai_string( $item['gap'] ?? '' ),
                                        'action'  => $this->sanitize_ai_string( $item['action'] ?? '' ),
                                ];
                        }, is_array( $payload['competitors'] ?? [] ) ? $payload['competitors'] : [] ),
                ];
        }

        private function sanitize_ai_string( $value ) {
                if ( empty( $value ) ) { return ''; }
                return trim( wp_strip_all_tags( (string) $value ) );
        }

	public function add_dashboard_widgets() {
		wp_add_dashboard_widget('vardi_welcome_assistant', 'آژانس خلاقیت وردی', [$this, 'render_welcome_assistant']);
		if ( class_exists( 'WooCommerce' ) ) {
			wp_add_dashboard_widget('vardi_woocommerce_assistant', 'دستیار فروشگاه', [$this, 'render_woocommerce_assistant']);
		}
	}

	public function render_welcome_assistant() {
		$user = wp_get_current_user();
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
		$date = date_i18n('l, j F Y');
		$phone = $this->options['dashboard_banner_phone'] ?? '09119035272';
		echo "<div class='vardi-welcome-widget'><div class='welcome-header'><h3>سلام <strong>" . esc_html($user->display_name) . "</strong>، روز خوبی داشته باشید!</h3><p>امروز: " . esc_html($date) . " | IP شما: " . esc_html($ip) . "</p></div><div class='welcome-actions'><p>نیاز به راهنمایی یا پشتیبانی دارید؟</p><a href='tel:" . esc_attr($phone) . "' class='button button-primary'>تماس با پشتیبانی</a></div></div><style>.vardi-welcome-widget { border-left: 4px solid #34495e; background: #fff; padding: 0; margin-top: -12px; } .vardi-welcome-widget .welcome-header { padding: 15px; border-bottom: 1px solid #eee; } .vardi-welcome-widget h3 { margin: 0 0 10px; font-size: 1.2em; } .vardi-welcome-widget p { margin: 0; color: #555; } .vardi-welcome-widget .welcome-actions { padding: 15px; text-align: left; }</style>";
	}

	public function render_woocommerce_assistant() {
		if ( ! function_exists( 'wc_get_orders' ) || ! did_action( 'woocommerce_init' ) ) {
			echo '<p>در حال بارگذاری اطلاعات فروشگاه...</p>'; return;
		}
		$site_name = get_bloginfo('name');
		$processing_orders = wc_get_orders(['status' => 'processing', 'limit' => -1]);
		$processing_orders_count = is_array($processing_orders) ? count($processing_orders) : 0;
		$today_orders = wc_get_orders(['date_created' => date('Y-m-d'), 'limit' => -1]);
		$today_orders_count = is_array($today_orders) ? count($today_orders) : 0;
		echo "<div class='vardi-assistant-box'><p>نگاهی به وضعیت امروز فروشگاه <strong>" . esc_html($site_name) . "</strong> بیندازیم:</p><ul><li><span style='font-size: 1.2em; margin-left: 8px;'>📈</span> امروز <strong>" . $today_orders_count . "</strong> سفارش جدید ثبت شده است.</li><li><span style='font-size: 1.2em; margin-left: 8px;'>📦</span> <strong>" . $processing_orders_count . "</strong> سفارش منتظر پردازش و ارسال هستند.</li></ul><div style='text-align: left; margin-top: 15px;'><a href='" . admin_url('edit.php?post_type=shop_order') . "' class='button button-primary'>مدیریت سفارشات</a></div></div>";
	}


	// --- **NEW (LOGIN FIX)**: توابع جدید برای مدیریت آدرس ورود با Rewrite API ---

	/**
	 * 1. قانون بازنویسی را ثبت می‌کند (در init).
	 */
	public function register_login_rewrite_rule() {
		if (!empty($this->login_slug)) {
			// قانون اصلی: /my-slug/ به wp-login.php اشاره کند
			add_rewrite_rule('^' . $this->login_slug . '/?$', 'wp-login.php', 'top');

			// **NEW**: قوانین اضافی برای اکشن‌های لاگین (مانند register, lostpassword)
			// اینها تضمین می‌کنند که site.com/my-slug/?action=register کار کند
			add_rewrite_rule('^' . $this->login_slug . '/?(.*)', 'wp-login.php?$1', 'top');
		}
	}

	/**
	 * 2. دسترسی مستقیم به wp-login.php را مسدود می‌کند (در template_redirect).
	 * این تابع دیرتر اجرا می‌شود و امن‌تر است.
	 */
	public function block_old_login_access() {
		// اگر کاربر لاگین کرده یا در پیشخوان است، کاری نکن
		if (is_user_logged_in() || is_admin()) {
			return;
		}

		// مسیر درخواست فعلی در مرورگر
		$request_uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		// اگر آدرس مرورگر شامل wp-login.php بود (و آدرس جدید ما نیست)،
		// کاربر را به صفحه 404 هدایت کن
		if ( strpos($request_uri_path, 'wp-login.php') !== false ) {
			wp_safe_redirect(home_url('404'));
			exit;
		}
	}

	/**
	 * **NEW (LOGIN FIX)**: اگر کاربر لاگین نکرده و می‌خواهد وارد wp-admin شود، او را به صفحه لاگین *جدید* هدایت می‌کند.
	 */
	public function redirect_wp_admin_to_new_login() {
		global $pagenow;
		// اگر کاربر لاگین کرده، یا درخواست AJAX است، یا در حال حاضر در صفحه لاگین هستیم، کاری نکن
		if (is_user_logged_in() || defined('DOING_AJAX') && DOING_AJAX || $pagenow === 'wp-login.php') {
			return;
		}

		// اگر درخواست برای wp-admin است
		if (is_admin()) {
			// به صفحه لاگین جدید هدایت کن و آدرس فعلی را به عنوان redirect_to بفرست
			$redirect_url = wp_login_url(admin_url());
			wp_safe_redirect($redirect_url);
			exit;
		}
	}


	/**
	 * 3. فیلتر برای ساختن URL لاگین (اشاره به آدرس جدید).
	 */
	public function filter_login_url($login_url, $redirect) {
		$login_url = home_url('/' . $this->login_slug); // آدرس جدید بدون اسلش انتهایی
		if (!empty($redirect)) {
			$login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
		}
		return $login_url;
	}

	/**
	 * 4. فیلتر برای URL خروج (Logout) - باید به آدرس جدید اشاره کند.
	 */
	public function filter_logout_url_fix($logout_url, $redirect) {
		$logout_url = add_query_arg('action', 'logout', home_url('/' . $this->login_slug));
		$logout_url = wp_nonce_url($logout_url, 'log-out'); // Nonce مهم است
		if (!empty($redirect)) {
			$logout_url = add_query_arg('redirect_to', urlencode($redirect), $logout_url);
		}
		return $logout_url;
	}

	/**
	 * 5. فیلتر برای URL فراموشی رمز عبور (اشاره به آدرس جدید).
	 */
	public function filter_lostpassword_url($lostpassword_url, $redirect) {
		return add_query_arg('action', 'lostpassword', home_url('/' . $this->login_slug));
	}

	/**
	 * 6. فیلتر برای URL ثبت نام (اشاره به آدرس جدید).
	 */
	public function filter_register_url($register_url) {
		return add_query_arg('action', 'register', home_url('/' . $this->login_slug));
	}

	/**
	 * 7. فیلتر مهم site_url برای جلوگیری از ریدایرکت‌های ناخواسته.
	 */
	public function filter_site_url_for_login_php( $url, $path, $scheme, $blog_id ) {
		// فقط زمانی اجرا شو که وردپرس می‌خواهد URL مربوط به wp-login.php را بسازد
		// یا وقتی که scheme برابر با 'login_post' (برای فرم لاگین) یا 'login' است.
		if ( ($path === 'wp-login.php' || $path === '') && ($scheme === 'login' || $scheme === 'login_post') ) {
			// URL پایه آدرس جدید را برگردان
			return home_url( $this->login_slug, $scheme );
		}
		// در غیر این صورت، URL اصلی را برگردان
		return $url;
	}

	// --- پایان توابع آدرس ورود ---

	private function init_login_protection() {
		add_filter('authenticate', [$this, 'check_lockout'], 25, 1);
		add_action('wp_login_failed', [$this, 'handle_failed_login'], 10, 1);
	}

	public function check_lockout($user) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'vardi_kit_login_lockouts';
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		if (empty($ip)) return $user;
		$now = current_time('mysql');
		$is_locked = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table_name WHERE user_ip = %s AND release_date > %s", $ip, $now));
		if ($is_locked) {
			return new WP_Error('authentication_failed', '<strong>خطا:</strong> به دلیل تلاش‌های ناموفق متعدد، دسترسی شما به صورت موقت مسدود شده است.');
		}
		return $user;
	}

	public function handle_failed_login($username) {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		if (empty($ip)) return;
		$attempts_limit = intval($this->options['login_attempts_count'] ?? 5);
		$transient_name = 'vardi_login_attempts_' . preg_replace('/[^0-9a-zA-Z_]/', '', $ip);
		$attempts = (int) get_transient($transient_name) + 1;
		if ($attempts >= $attempts_limit) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'vardi_kit_login_lockouts';
			$lockout_time = intval($this->options['login_lockout_time'] ?? 15);
			$lockout_date = current_time('mysql');
			$release_date = date('Y-m-d H:i:s', strtotime("+$lockout_time minutes", strtotime($lockout_date)));
			$wpdb->insert($table_name, ['user_ip' => $ip, 'lockout_date' => $lockout_date, 'release_date' => $release_date]);
			delete_transient($transient_name);
		} else {
			set_transient($transient_name, $attempts, 15 * MINUTE_IN_SECONDS);
		}
	}

	public function block_external_requests($pre, $args, $url) {
		$domains_to_block = [];
		$textarea_domains_raw = $this->options['block_external_requests'] ?? '';
		if (!empty($textarea_domains_raw)) {
			$textarea_domains = preg_split("/\r\n|\n|\r/", $textarea_domains_raw);
			$domains_to_block = array_merge($domains_to_block, array_filter(array_map('trim', $textarea_domains)));
		}
		if ($this->is_enabled('block_wordpress_api')) {
			$domains_to_block = array_merge($domains_to_block, ['api.wordpress.org', 'downloads.wordpress.org', 'profiles.wordpress.org']);
		}
		if (empty($domains_to_block)) return $pre;
		$domains_to_block = array_unique($domains_to_block);
		foreach ($domains_to_block as $domain) {
			if ($domain && strpos($url, $domain) !== false) {
				return new WP_Error('request_blocked', 'Request blocked by Vardi Kit.');
			}
		}
		return $pre;
	}

	public function limit_rest_api_access($result) {
		if (!is_user_logged_in()) { return new WP_Error('rest_forbidden', 'Authentication required.', ['status' => 401]); }
		return $result;
	}

	public function disable_emojis() {
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	}

	public function disable_embeds() {
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');
	}

	public function control_heartbeat() {
		$setting = $this->options['control_heartbeat'] ?? 'default';
		if ($setting === 'disable') {
			add_action('init', function() { wp_deregister_script('heartbeat'); }, 1);
		} elseif ($setting === 'slow_down') {
			add_filter('heartbeat_settings', function($settings) {
				$settings['interval'] = 60;
				return $settings;
			});
		}
	}

	// --- DATABASE CLEANUP FUNCTIONS ---
	// (بدون تغییر)
	public function get_cleanup_count( $type ) { global $wpdb; switch ( $type ) { case 'revisions': return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); case 'drafts': return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ); case 'spam_comments': return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ); case 'trashed_comments': return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ); case 'transients': $sql = "SELECT COUNT(option_name) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < " . time(); return (int) $wpdb->get_var( $sql ); default: return 0; } }
	public function cleanup_post_revisions() { global $wpdb; $result = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" ); return $result === false ? 0 : $result; }
	public function cleanup_auto_drafts() { global $wpdb; $result = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ); return $result === false ? 0 : $result; }
	public function cleanup_spam_comments() { global $wpdb; $result = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ); return $result === false ? 0 : $result; }
	public function cleanup_trashed_comments() { global $wpdb; $result = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ); return $result === false ? 0 : $result; }
	public function cleanup_expired_transients() { global $wpdb; $time = time(); $sql = "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b WHERE a.option_name LIKE '\_transient\_%' AND a.option_name NOT LIKE '\_transient\_timeout\_%' AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, CHAR_LENGTH('_transient_') + 1 ) ) AND b.option_value < {$time}"; $result = $wpdb->query( $sql ); return $result === false ? 0 : $result; }
}