<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vardi_Admin_Settings {

	private static $_instance = null;
	private $options;
	private $admin_pages = [];

	const PAGE_SLUG = 'vardi-site-management';
	const OPTION_GROUP = 'vardi_kit_settings_group';
        const OPTION_NAME = 'vardi_kit_options';
        const PASSWORD_SESSION_KEY = 'vardi_kit_access_granted';
        const DEFAULT_PASSWORD = '123456789';
        const AI_PAGE_SLUG = 'vardi-ai-inspector';

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

        private function __construct() {
                add_action( 'admin_init', [ $this, 'start_session' ], 1 );
                add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
                add_action( 'admin_init', [ $this, 'settings_init' ] );
                add_action( 'admin_init', [ $this, 'handle_password_submission' ] );
                add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
                add_action( 'admin_head', [ $this, 'hide_other_admin_notices' ] );
                add_action( 'admin_notices', [ $this, 'show_save_notice' ] );
                add_action( 'admin_notices', [ $this, 'show_shamsi_conflict_notice' ] );
        }

	public function start_session() {
		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}
	}

        public function show_save_notice() {
                if ( isset( $_GET['page'] ) && ($_GET['page'] === self::PAGE_SLUG || $_GET['page'] === 'vardi-db-cleaner' || $_GET['page'] === 'vardi-login-lockouts') && isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
                        echo '<div id="message" class="updated notice is-dismissible"><p><strong>با موفقیت ثبت شد.</strong></p></div>';
                }
        }

        public function show_shamsi_conflict_notice() {
                if ( ! current_user_can( 'manage_options' ) ) return;
                if ( ! isset( $_GET['page'] ) || strpos( sanitize_key( $_GET['page'] ), 'vardi-' ) !== 0 ) return;
                $notice = get_transient( 'vardi_kit_shamsi_conflict_notice' );
                if ( ! $notice ) { return; }

                $default_message = __( 'وضعیت تاریخ شمسی نیاز به بررسی دارد.', 'vardi-kit' );
                $message = is_array( $notice ) && ! empty( $notice['message'] ) ? $notice['message'] : $default_message;
                $type    = is_array( $notice ) && ! empty( $notice['type'] ) ? $notice['type'] : 'warning';

                printf(
                        '<div class="notice notice-%1$s vardi-kit-notice"><p><strong>Vardi Kit:</strong> %2$s</p></div>',
                        esc_attr( $type ),
                        esc_html( $message )
                );

                delete_transient( 'vardi_kit_shamsi_conflict_notice' );
        }

	public function add_admin_menu() {
                $main_page_hook = add_menu_page( __( 'مدیریت سایت', 'vardi-kit' ), __( 'مدیریت سایت', 'vardi-kit' ), 'manage_options', self::PAGE_SLUG, [ $this, 'render_page_wrapper' ], 'dashicons-shield-alt', 2 );
                $settings_hook = add_submenu_page( self::PAGE_SLUG, __( 'تنظیمات', 'vardi-kit' ), __( 'تنظیمات', 'vardi-kit' ), 'manage_options', self::PAGE_SLUG, [ $this, 'render_page_wrapper' ] );

                if ( class_exists( 'WooCommerce' ) ) {
                        $sms_hook = add_submenu_page( self::PAGE_SLUG, __( 'مدیریت پیامک', 'vardi-kit' ), __( 'مدیریت پیامک', 'vardi-kit' ), 'manage_options', 'vardi-woocommerce-sms', [ 'Vardi_SMS_Admin_Settings', 'render_sms_page_wrapper' ] );
                        $this->admin_pages['sms'] = $sms_hook;
                }

                $db_cleaner_hook = add_submenu_page( self::PAGE_SLUG, __( 'پاکسازی دیتابیس', 'vardi-kit' ), __( 'پاکسازی دیتابیس', 'vardi-kit' ), 'manage_options', 'vardi-db-cleaner', [ $this, 'render_page_wrapper' ] );
                $lockouts_hook = add_submenu_page( self::PAGE_SLUG, __( 'لیست سیاه ورود', 'vardi-kit' ), __( 'لیست سیاه ورود', 'vardi-kit' ), 'manage_options', 'vardi-login-lockouts', [ $this, 'render_page_wrapper' ] );
                $ai_hook = add_submenu_page( self::PAGE_SLUG, __( 'هوش مصنوعی', 'vardi-kit' ), __( 'هوش مصنوعی', 'vardi-kit' ), 'manage_options', self::AI_PAGE_SLUG, [ $this, 'render_page_wrapper' ] );

                $this->admin_pages['main'] = $main_page_hook;
                $this->admin_pages['settings'] = $settings_hook;
                $this->admin_pages['db_cleaner'] = $db_cleaner_hook;
                $this->admin_pages['lockouts'] = $lockouts_hook;
                $this->admin_pages['ai'] = $ai_hook;
        }

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( in_array( $hook_suffix, $this->admin_pages, true ) ) {
			wp_enqueue_style( 'vardi-kit-admin-css', VARDI_KIT_PLUGIN_URL . 'assets/css/admin-settings.css', [], VARDI_KIT_VERSION );
			wp_enqueue_script( 'vardi-kit-admin-js', VARDI_KIT_PLUGIN_URL . 'assets/js/admin-settings.js', [ 'jquery' ], VARDI_KIT_VERSION, true );
		}
	}

	public function handle_password_submission() {
		if ( isset($_GET['page']) && strpos($_GET['page'], 'vardi-') === 0 && isset( $_POST['vardi_kit_password'] ) ) {
			$this->options = get_option(self::OPTION_NAME, []);
			$password = !empty($this->options['panel_password']) ? $this->options['panel_password'] : self::DEFAULT_PASSWORD;
			if ( hash_equals($password, $_POST['vardi_kit_password']) ) {
				$_SESSION[self::PASSWORD_SESSION_KEY] = true;
				wp_redirect( admin_url( 'admin.php?page=' . sanitize_key($_GET['page']) ) );
				exit;
			}
		}
	}

	public function render_page_wrapper() {
		if ( ! $this->is_access_granted() ) {
			if (isset($_POST['vardi_kit_password'])) {
				echo '<div class="notice notice-error is-dismissible vardi-kit-notice"><p>' . __('رمز عبور اشتباه است.', 'vardi-kit') . '</p></div>';
			}
			// *** نکته مهم ***
			// اطمینان حاصل کنید که فایل 'password-form-template.php' شما
			// محتوای فرم رمز عبور را دارد و نه قالب تنظیمات
			include_once VARDI_KIT_PLUGIN_PATH . 'includes/admin/templates/password-form-template.php';
			return;
		}
		$current_page_slug = $_GET['page'] ?? self::PAGE_SLUG;
                switch ($current_page_slug) {
                        case 'vardi-db-cleaner': $this->render_db_cleaner_page(); break;
                        case 'vardi-login-lockouts': $this->render_lockouts_page(); break;
                        case self::AI_PAGE_SLUG: $this->render_ai_page(); break;
                        case self::PAGE_SLUG: default: $this->render_settings_page(); break;
                }
        }

	private function is_access_granted() {
		$this->options = get_option(self::OPTION_NAME, []);
		if (empty($this->options['panel_password'])) return true;
		return isset($_SESSION[self::PASSWORD_SESSION_KEY]) && $_SESSION[self::PASSWORD_SESSION_KEY];
	}

	private function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		include_once VARDI_KIT_PLUGIN_PATH . 'includes/admin/templates/settings-page-template.php';
	}

	public function settings_init() {
                register_setting( self::OPTION_GROUP, self::OPTION_NAME, [ $this, 'sanitize_options' ] ); // این تابع مهم است
                add_settings_section( 'vardi_general_section', null, null, 'vardi_general' );
                add_settings_field( 'enable_shamsi_date', __( 'فعال‌سازی تاریخ شمسی', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_general', 'vardi_general_section', ['name' => 'enable_shamsi_date', 'label' => 'تمام تاریخ‌های وردپرس (نوشته‌ها، دیدگاه‌ها، ووکامرس و ...) به شمسی تبدیل شوند.'] );
                add_settings_field( 'shamsi_date_conflict_mode', __( 'حالت مدیریت تداخل تاریخ شمسی', 'vardi-kit' ), [ $this, 'render_select_field' ], 'vardi_general', 'vardi_general_section', [
                        'name'    => 'shamsi_date_conflict_mode',
                        'options' => [
                                'auto'    => __( 'تشخیص خودکار و توقف در صورت تداخل', 'vardi-kit' ),
                                'force'   => __( 'اجبار به تبدیل حتی در صورت تداخل', 'vardi-kit' ),
                                'disable' => __( 'غیرفعال (عدم تبدیل به شمسی)', 'vardi-kit' ),
                        ],
                        'desc'    => __( 'در صورت فعال بودن گزینه بالا، می‌توانید مشخص کنید در صورت وجود افزونه یا کتابخانه تاریخ شمسی دیگر چه اتفاقی بیفتد.', 'vardi-kit' ),
                ] );
                add_settings_field( 'admin_footer_text', __( 'متن فوتر پیشخوان', 'vardi-kit' ), [ $this, 'render_text_field' ], 'vardi_general', 'vardi_general_section', ['name' => 'admin_footer_text', 'placeholder' => 'آژانس خلاقیت وردی'] );

		add_settings_section( 'vardi_appearance_section', null, null, 'vardi_appearance' );
		add_settings_field( 'enable_custom_login', __( 'صفحه ورود سفارشی', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_appearance', 'vardi_appearance_section', ['name' => 'enable_custom_login', 'label' => 'فعال کردن استایل مدرن و لوکس برای صفحه ورود'] );
		add_settings_field( 'login_logo_url', __( 'آدرس لوگو صفحه ورود', 'vardi-kit' ), [ $this, 'render_text_field' ], 'vardi_appearance', 'vardi_appearance_section', ['name' => 'login_logo_url', 'placeholder' => 'در صورت خالی بودن، از لوگوی سایت استفاده می‌شود'] );
		add_settings_field( 'admin_font', __( 'فونت پیشخوان', 'vardi-kit' ), [ $this, 'render_font_select_field' ], 'vardi_appearance', 'vardi_appearance_section' );
		add_settings_field( 'enable_dashboard_banner', __( 'ویجت‌های دستیار داشبورد', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_appearance', 'vardi_appearance_section', ['name' => 'enable_dashboard_banner', 'label' => 'نمایش ویجت‌های خوشامدگویی و دستیار فروشگاه در پیشخوان'] );
		add_settings_field( 'dashboard_banner_phone', __( 'شماره تماس پشتیبانی', 'vardi-kit' ), [ $this, 'render_text_field' ], 'vardi_appearance', 'vardi_appearance_section', ['name' => 'dashboard_banner_phone', 'placeholder' => '09119035272'] );

		add_settings_section( 'vardi_security_section', null, null, 'vardi_security' );
		add_settings_field( 'change_login_url', __( 'تغییر آدرس ورود', 'vardi-kit' ), [ $this, 'render_login_url_field' ], 'vardi_security', 'vardi_security_section' );
		add_settings_field( 'enable_login_protection', __( 'محافظت از صفحه ورود', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_security', 'vardi_security_section', ['name' => 'enable_login_protection', 'label' => 'فعال کردن سیستم ضد بروت-فورس (مسدودسازی IP)']);
		add_settings_field( 'login_attempts_settings', __( 'تنظیمات محافظت', 'vardi-kit' ), [ $this, 'render_login_attempts_fields' ], 'vardi_security', 'vardi_security_section' );
		add_settings_field( 'block_wordpress_api', __( 'مسدودسازی API وردپرس', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_security', 'vardi_security_section', [ 'name' => 'block_wordpress_api', 'label' => 'مسدود کردن ارتباط با سرورهای وردپرس (api.wordpress.org)', 'desc' => 'این کار از بررسی خودکار برای به‌روزرسانی‌ها جلوگیری کرده و سرعت پیشخوان را افزایش می‌دهد. برای به‌روزرسانی، آن را موقتاً غیرفعال کنید.' ] );
		add_settings_field( 'block_external_requests', __( 'مسدودسازی دامنه‌های دیگر', 'vardi-kit' ), [ $this, 'render_textarea_field' ], 'vardi_security', 'vardi_security_section', [ 'name' => 'block_external_requests', 'desc' => 'هر دامنه را در یک خط جدید وارد کنید. این کار سرعت پیشخوان را بهبود می‌بخشد.' ] );
		add_settings_field( 'disable_xmlrpc', __( 'غیرفعال کردن XML-RPC', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_security', 'vardi_security_section', ['name' => 'disable_xmlrpc', 'label' => 'جلوگیری از حملات Brute Force (بسیار توصیه می‌شود)', 'desc' => '<strong>این چیست؟</strong> XML-RPC مانند یک در پشتی برای اپلیکیشن‌های قدیمی است. اگر از اپلیکیشن موبایل وردپرس استفاده نمی‌کنید، بستن این در، امنیت سایت شما را به شدت افزایش می‌دهد.'] );
		add_settings_field( 'disable_rest_api', __( 'محدود کردن REST API', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_security', 'vardi_security_section', ['name' => 'disable_rest_api', 'label' => 'فقط به کاربران وارد شده اجازه دسترسی داده شود', 'desc' => '<strong>این چیست؟</strong> این گزینه از دسترسی عمومی و ناشناس به اطلاعات سایت شما (مانند لیست نام‌های کاربری) از طریق API جلوگیری می‌کند و برای سئو ضرری ندارد.'] );

		add_settings_section( 'vardi_performance_section', null, null, 'vardi_performance' );
		add_settings_field( 'disable_emojis', __( 'غیرفعال‌سازی Emojis', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_performance', 'vardi_performance_section', ['name' => 'disable_emojis', 'label' => 'حذف اسکریپت‌های اضافی مربوط به ایموجی‌های وردپرس برای افزایش سرعت.'] );
		add_settings_field( 'disable_embeds', __( 'غیرفعال‌سازی Embeds', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_performance', 'vardi_performance_section', ['name' => 'disable_embeds', 'label' => 'جلوگیری از قابلیت تبدیل خودکار لینک‌ها (مثل یوتیوب) به محتوای جاسازی شده.'] );
                add_settings_field( 'control_heartbeat', __( 'کنترل Heartbeat API', 'vardi-kit' ), [ $this, 'render_select_field' ], 'vardi_performance', 'vardi_performance_section', ['name' => 'control_heartbeat', 'options' => ['default' => 'پیش‌فرض وردپرس', 'slow_down' => 'کاهش فعالیت (پیشنهادی)', 'disable' => 'غیرفعال‌سازی کامل'], 'desc' => 'کاهش فعالیت Heartbeat، بار روی سرور را به خصوص در پیشخوان کاهش می‌دهد.'] );

                add_settings_section( 'vardi_access_section', null, null, 'vardi_access' );
                add_settings_field( 'panel_password', __( 'رمز عبور پنل', 'vardi-kit' ), [ $this, 'render_password_field' ], 'vardi_access', 'vardi_access_section', ['name' => 'panel_password'] );

                add_settings_section( 'vardi_ai_section', null, null, 'vardi_ai' );
                add_settings_field( 'enable_ai_inspector', __( 'فعال‌سازی دستیار هوش مصنوعی', 'vardi-kit' ), [ $this, 'render_checkbox_field' ], 'vardi_ai', 'vardi_ai_section', ['name' => 'enable_ai_inspector', 'label' => 'نمایش گزینه «تحلیل صفحه توسط AI» در نوار مدیریت برای مدیران'] );
                add_settings_field( 'ai_api_key', __( 'کلید API گپ جی‌پی‌تی', 'vardi-kit' ), [ $this, 'render_text_field' ], 'vardi_ai', 'vardi_ai_section', ['name' => 'ai_api_key', 'placeholder' => 'کلید دریافت‌شده از gapgpt'] );
                add_settings_field( 'ai_base_url', __( 'آدرس پایه API', 'vardi-kit' ), [ $this, 'render_select_field' ], 'vardi_ai', 'vardi_ai_section', ['name' => 'ai_base_url', 'options' => [ 'https://api.gapgpt.app/v1' => 'https://api.gapgpt.app/v1', 'https://api.gapapi.com/v1' => 'https://api.gapapi.com/v1' ], 'desc' => 'مطابق راهنمای گپ جی‌پی‌تی باید یکی از این دو آدرس استفاده شود.'] );
                add_settings_field( 'ai_model', __( 'مدل پیش‌فرض', 'vardi-kit' ), [ $this, 'render_text_field' ], 'vardi_ai', 'vardi_ai_section', ['name' => 'ai_model', 'placeholder' => 'مثلاً gpt-4o یا gemini-2.5-pro'] );
        }

        private function render_db_cleaner_page() {
                $features = Vardi_Features::get_instance();
                $message = '';
		if ( isset( $_POST['vardi_cleanup_action'] ) && check_admin_referer( 'vardi_db_cleanup_nonce' ) ) {
			$action = sanitize_key( $_POST['vardi_cleanup_action'] );
			$result = 0; $action_text = '';
			switch ( $action ) {
				case 'revisions': $result = $features->cleanup_post_revisions(); $action_text = 'رونوشت نوشته'; break;
				case 'drafts': $result = $features->cleanup_auto_drafts(); $action_text = 'پیش‌نویس خودکار'; break;
				case 'spam_comments': $result = $features->cleanup_spam_comments(); $action_text = 'دیدگاه اسپم'; break;
				case 'trashed_comments': $result = $features->cleanup_trashed_comments(); $action_text = 'دیدگاه زباله‌دان'; break;
				case 'transients': $result = $features->cleanup_expired_transients(); $action_text = 'اطلاعات موقت منقضی شده'; break;
			}
			if ( $result > 0 ) {
				$message = sprintf( '<div class="notice notice-success is-dismissible vardi-kit-notice"><p>%d %s با موفقیت حذف شد.</p></div>', $result, $action_text );
			} else {
				$message = '<div class="notice notice-info is-dismissible vardi-kit-notice"><p>موردی برای حذف یافت نشد.</p></div>';
			}
		}
		$stats = [
			'revisions' => $features->get_cleanup_count('revisions'),
			'drafts' => $features->get_cleanup_count('drafts'),
			'spam_comments' => $features->get_cleanup_count('spam_comments'),
			'trashed_comments' => $features->get_cleanup_count('trashed_comments'),
			'transients' => $features->get_cleanup_count('transients'),
		];
		include_once VARDI_KIT_PLUGIN_PATH . 'includes/admin/templates/db-cleaner-page-template.php';
	}

	public function hide_other_admin_notices() {
		$screen = get_current_screen();
		if ($screen && in_array($screen->id, $this->admin_pages, true)) {
			echo '<style> .notice:not(.vardi-kit-notice), .update-nag, #wp-admin-bar-updates { display: none !important; } </style>';
		}
	}

	private function render_lockouts_page() {
		global $wpdb; $table_name = $wpdb->prefix . 'vardi_kit_login_lockouts';
		if (isset($_GET['action']) && $_GET['action'] == 'unblock' && isset($_GET['lockout_id']) && check_admin_referer('vardi_unblock_' . $_GET['lockout_id'])) {
			$lockout_id = intval($_GET['lockout_id']); $wpdb->delete($table_name, ['lockout_id' => $lockout_id]);
			echo '<div class="notice notice-success is-dismissible vardi-kit-notice"><p>کاربر با موفقیت از لیست سیاه خارج شد.</p></div>';
		}
		$lockouts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY lockout_date DESC");
		echo '<div class="wrap"><h1>لیست سیاه ورود</h1><p>کاربرانی که به دلیل تلاش برای ورود ناموفق مسدود شده‌اند در این لیست نمایش داده می‌شوند.</p>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>IP کاربر</th><th>تاریخ مسدودیت</th><th>تاریخ آزادسازی</th><th>عملیات</th></tr></thead><tbody>';
		if ($lockouts) {
			foreach ($lockouts as $lockout) {
				$lockout_date_formatted = date_i18n('Y/m/d H:i:s', strtotime($lockout->lockout_date));
				$release_date_formatted = date_i18n('Y/m/d H:i:s', strtotime($lockout->release_date));

				$unblock_url = wp_nonce_url( admin_url('admin.php?page=vardi-login-lockouts&action=unblock&lockout_id=' . $lockout->lockout_id), 'vardi_unblock_' . $lockout->lockout_id);
				echo '<tr><td>' . esc_html($lockout->user_ip) . '</td><td>' . esc_html($lockout_date_formatted) . '</td><td>' . esc_html($release_date_formatted) . '</td><td><a href="' . esc_url($unblock_url) . '" class="button button-secondary">آزادسازی</a></td></tr>';
			}
		} else { echo '<tr><td colspan="4">هیچ کاربری در لیست سیاه وجود ندارد.</td></tr>'; }
		echo '</tbody></table></div>';
	}

        public function render_checkbox_field( $args ) {
                $this->options = get_option( self::OPTION_NAME, [] );
                $name = $args['name'];

                // **FIX**: اینجا را اصلاح کردیم
                // به جای isset (که '0' را true برمی‌گرداند)، مقدار را می‌خوانیم
                $checked_val = $this->options[$name] ?? '0';
                // تابع checked وردپرس به درستی '1' را با مقدار فعلی مقایسه می‌کند
                $checked = checked( '1', $checked_val, false );

                echo "<label><input type='checkbox' name='" . self::OPTION_NAME . "[$name]' value='1' $checked> " . ($args['label'] ?? '') . "</label>";
                if(isset($args['desc'])) echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
        }

        private function render_ai_page() {
                echo '<div class="wrap vardi-kit-admin-wrap">';
                echo '<h1>هوش مصنوعی</h1>';
                echo '<p>تنظیمات اتصال به GapGPT برای دکمه «تحلیل صفحه توسط AI» در نوار ابزار مدیریت.</p>';
                echo '<p class="description">برای استفاده، base_url باید یکی از <code>https://api.gapgpt.app/v1</code> یا <code>https://api.gapapi.com/v1</code> باشد و کلید API از پنل GapGPT دریافت شود.</p>';
                echo '<form action="options.php" method="post">';
                settings_fields(self::OPTION_GROUP);
                echo '<input type="hidden" name="vardi_kit_active_tab_marker" value="ai" />';
                echo '<div class="card" style="max-width:880px;">';
                do_settings_sections('vardi_ai');
                echo '</div>';
                submit_button('ذخیره تنظیمات هوش مصنوعی');
                echo '</form>';
                echo '</div>';
        }

	public function render_text_field( $args ) {
		$this->options = get_option( self::OPTION_NAME, [] );
		$name = $args['name'];
		$value = $this->options[$name] ?? '';
		echo "<input type='text' class='regular-text' name='" . self::OPTION_NAME . "[$name]' value='" . esc_attr($value) . "' placeholder='" . ($args['placeholder'] ?? '') . "'>";
		if(isset($args['desc'])) echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
	}

	public function render_password_field( $args ) {
		$this->options = get_option( self::OPTION_NAME, [] );
		$name = $args['name'];
		$value = $this->options[$name] ?? '';
		echo "<input type='password' class='regular-text' name='" . self::OPTION_NAME . "[$name]' value='" . esc_attr($value) . "' placeholder='برای امنیت بیشتر رمز را تغییر دهید'>";
		echo "<p class='description'>" . __('اگر این فیلد را خالی بگذارید، رمز پیش‌فرض (123456789) استفاده خواهد شد.', 'vardi-kit') . "</p>";
	}

	public function render_textarea_field( $args ) {
		$default_domains = "templates.elementor.com\nrankmath.com\nwoocommerce.com\njetpack.com\nyoast.com\nakismet.com\nsiteground.com\ngravatar.com";
		$name = $args['name'];
		$this->options = get_option( self::OPTION_NAME, [] );
		$value = $this->options[$name] ?? $default_domains;
		echo "<textarea rows='8' class='large-text' name='" . self::OPTION_NAME . "[{$name}]'>" . esc_textarea($value) . "</textarea>";
		if (isset($args['desc'])) echo "<p class='description'>" . wp_kses_post($args['desc']) . "</p>";
	}

	public function render_font_select_field() {
		$this->options = get_option( self::OPTION_NAME, [] );
		$current_font = $this->options['admin_font'] ?? '';
		$font_dir = VARDI_KIT_PLUGIN_PATH . 'fonts/';
		$fonts = glob($font_dir . '*.{ttf,woff,woff2}', GLOB_BRACE);
		echo "<select name='" . self::OPTION_NAME . "[admin_font]'>";
		echo "<option value=''>" . __('پیش‌فرض وردپرس', 'vardi-kit') . "</option>";
		foreach ($fonts as $font) {
			$font_file = basename($font);
			$selected = ($font_file === $current_font) ? 'selected' : '';
			echo "<option value='" . esc_attr($font_file) . "' $selected>" . esc_html($font_file) . "</option>";
		}
		echo "</select>";
		echo "<p class='description'>" . __('فونت‌های خود را با فرمت ttf, woff یا woff2 در پوشه /fonts افزونه قرار دهید.', 'vardi-kit') . "</p>";
	}

	public function render_login_attempts_fields() {
		$this->options = get_option( self::OPTION_NAME, [] );
		$attempts_count = $this->options['login_attempts_count'] ?? '5';
		$lockout_time = $this->options['login_lockout_time'] ?? '15';
		echo "<input type='number' min='1' max='10' name='" . self::OPTION_NAME . "[login_attempts_count]' value='" . esc_attr($attempts_count) . "'> تعداد تلاش ناموفق قبل از مسدودسازی<br>";
		echo "<input type='number' min='1' max='60' name='" . self::OPTION_NAME . "[login_lockout_time]' value='" . esc_attr($lockout_time) . "'> دقیقه زمان مسدودیت IP";
	}

	public function render_select_field($args) {
		$this->options = get_option(self::OPTION_NAME, []);
		$name = $args['name'];
		$value = $this->options[$name] ?? 'default';
		echo "<select name='" . self::OPTION_NAME . "[{$name}]'>";
		foreach($args['options'] as $opt_val => $opt_label) {
			echo "<option value='" . esc_attr($opt_val) . "' " . selected($value, $opt_val, false) . ">" . esc_html($opt_label) . "</option>";
		}
		echo "</select>";
		if (isset($args['desc'])) echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
	}

	public function render_login_url_field() {
		$this->options = get_option( self::OPTION_NAME, [] );
		$value = $this->options['change_login_url'] ?? '';
		$home_url = home_url( '/' );

		// آدرس نهایی بر اساس فیلتر login_url ساخته می‌شود (امن‌ترین راه)
		$login_url = wp_login_url();

                echo "<div>";
                echo "<input type='text' class='regular-text' id='vardi_kit_change_login_url' name='" . self::OPTION_NAME . "[change_login_url]' value='" . esc_attr($value) . "' placeholder='مثلا: my-login'>";
                echo "<button type='button' class='button button-secondary' id='vardi_kit_generate_random_url' style='margin-right: 5px;'>ساخت آدرس امن تصادفی</button>";
                echo "<p class='description'>آدرس ورود نهایی شما: <strong id='vardi_kit_final_login_url' style='direction: ltr; user-select: all;'>" . esc_url($login_url) . "</strong></p>";
                echo "<p class='description'>مهم: پس از ذخیره، این آدرس را بوکمارک کنید. پس از به‌روزرسانی قوانین بازنویسی (به‌صورت خودکار در پیشخوان)، آدرس جدید فعال می‌شود. اگر خالی باشد، از آدرس پیش‌فرض وردپرس (/wp-login.php) استفاده می‌شود.</p>";
                echo "</div>";
        }


	/**
	 * ===================================================================
	 * ** *** شروع بخش اصلاح شده (قلب تپنده راه‌حل) *** **
	 * ===================================================================
	 * * این تابع به طور کامل بازنویسی شده تا مشکل ذخیره‌سازی تب‌ها را حل کند.
	 *
	 * @param array $input داده‌های ناقص ارسال شده فقط از تب فعال
	 * @return array داده‌های کامل و ادغام شده برای ذخیره در دیتابیس
	 */
	public function sanitize_options($input) {

		// $input یک آرایه ناقص است و فقط شامل فیلدهای تبی است که ذخیره شده.
		// مثلا: ['enable_custom_login' => '1', 'login_logo_url' => '...']

		// 1. تمام تنظیمات قدیمی و کامل را از دیتابیس می‌خوانیم
		$old_options = get_option(self::OPTION_NAME, []);

		// 2. تبی که در حال ذخیره آن هستیم را از فیلد مخفی که اضافه کردیم می‌خوانیم
		// (این کار برای مدیریت صحیح "تیک نخوردن" چک‌باکس‌ها حیاتی است)
		$active_tab = 'general'; // پیش‌فرض
		if ( isset( $_POST['vardi_kit_active_tab_marker'] ) ) {
			$active_tab = sanitize_key( $_POST['vardi_kit_active_tab_marker'] );
		}

		// 3. تمام فیلدهای "چک‌باکس" افزونه را به تفکیک تب تعریف می‌کنیم
		// این لیست باید با فیلدهایی که در settings_init() ثبت کرده‌اید یکسان باشد
                $tab_checkboxes = [
                        'general'     => ['enable_shamsi_date'],
                        'appearance'  => ['enable_custom_login', 'enable_dashboard_banner'],
                        'security'    => [
                                'enable_login_protection',
                                'block_wordpress_api',
                                'disable_xmlrpc',
                                'disable_rest_api'
                        ],
                        'performance' => ['disable_emojis', 'disable_embeds'],
                        'access'      => [], // تب دسترسی چک‌باکس ندارد
                        'ai'          => ['enable_ai_inspector'],
                ];

		// 4. چک‌باکس‌های مربوط به *تب فعال* را پیدا می‌کنیم
		$current_tab_checkboxes = $tab_checkboxes[$active_tab] ?? [];

		// 5. تمام چک‌باکس‌های تب فعال را در آرایه تنظیمات قدیمی "صفر" می‌کنیم
		// این کار تضمین می‌کند که اگر کاربر تیک چک‌باکسی را برداشته (که باعث می‌شود در $input نباشد)،
		// مقدار آن به '0' (خاموش) آپدیت شود و مقدار قدیمی '1' باقی نماند.
		if ( ! empty( $current_tab_checkboxes ) ) {
			foreach ($current_tab_checkboxes as $checkbox_key) {
				$old_options[$checkbox_key] = '0';
			}
		}

		// 6. حالا، داده‌های جدید ($input) را با تنظیمات قدیمی (که چک‌باکس‌هایش صفر شده) ادغام می‌کنیم.
		// wp_parse_args عالی عمل می‌کند:
		// - مقادیر متنی و چک‌باکس‌های تیک‌خورده جدید ($input) را جایگزین مقادیر قدیمی می‌کند.
		// - تنظیمات تب‌های دیگر (که در $input نیستند) از $old_options دست نخورده باقی می‌مانند.
		$new_options = wp_parse_args($input, $old_options);

		// --- بخش مربوط به Flush Rewrite Rules (منطق قبلی خودتان) ---

		// اسلاگ قدیمی را از تنظیمات کامل *قبل* از ادغام می‌خوانیم
		$db_options = get_option(self::OPTION_NAME, []); // خواندن مجدد دیتابیس برای اطمینان
		$old_slug = $db_options['change_login_url'] ?? '';

		// اسلاگ جدید را از داده‌های *ادغام‌شده* می‌خوانیم و پاکسازی می‌کنیم
                $new_slug = sanitize_title($new_options['change_login_url'] ?? '');
                $new_options['change_login_url'] = $new_slug; // مقدار پاکسازی شده را در آرایه نهایی قرار می‌دهیم

                // اگر اسلاگ تغییر کرده بود، قوانین را فلاش کن
                if ($old_slug !== $new_slug) {
                        delete_option( 'vardi_kit_login_rules_flushed' );
                        add_action('shutdown', function() { flush_rewrite_rules(true); });
                }

                if ( isset( $new_options['ai_base_url'] ) ) {
                        $allowed_endpoints = [ 'https://api.gapgpt.app/v1', 'https://api.gapapi.com/v1' ];
                        $new_options['ai_base_url'] = in_array( $new_options['ai_base_url'], $allowed_endpoints, true ) ? $new_options['ai_base_url'] : 'https://api.gapgpt.app/v1';
                }
                if ( isset( $new_options['ai_model'] ) ) {
                        $new_options['ai_model'] = sanitize_text_field( $new_options['ai_model'] );
                }
                if ( isset( $new_options['ai_api_key'] ) ) {
                        $new_options['ai_api_key'] = trim( sanitize_text_field( $new_options['ai_api_key'] ) );
                }

                $allowed_conflict_modes = [ 'auto', 'force', 'disable' ];
                $mode = $new_options['shamsi_date_conflict_mode'] ?? 'auto';
                $new_options['shamsi_date_conflict_mode'] = in_array( $mode, $allowed_conflict_modes, true ) ? $mode : 'auto';

                // --- پایان بخش Flush ---

                // 7. آرایه کامل و ادغام شده را برای ذخیره‌سازی نهایی برمی‌گردانیم
                return $new_options;
        }
	/**
	 * ===================================================================
	 * ** *** پایان بخش اصلاح شده *** **
	 * ===================================================================
	 */

}