<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Vardi_SMS_Admin_Settings' ) ) {

    class Vardi_SMS_Admin_Settings {

        const OPTION_GATEWAY   = 'vardi_kit_sms_gateway_options';
        const OPTION_ADMIN     = 'vardi_kit_sms_admin_options';
        const OPTION_CUSTOMER  = 'vardi_kit_sms_customer_options';
        const OPTION_PATTERN   = 'vardi_kit_sms_pattern_options'; // **NEW**: گزینه ذخیره‌سازی جدید

        public static function init() {
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
            add_action( 'wp_ajax_vardi_kit_send_manual_sms', array( __CLASS__, 'handle_manual_sms_sending' ) );
            add_action( 'wp_ajax_vardi_kit_send_pattern_test', array( __CLASS__, 'handle_pattern_test' ) );
            add_action( 'wp_ajax_vardi_kit_get_status_config', array( __CLASS__, 'handle_status_fetch' ) );
            add_action( 'admin_notices', array( __CLASS__, 'show_save_notice' ) );
        }

        public static function show_save_notice() {
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'vardi-woocommerce-sms' && isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
                echo '<div id="message" class="updated notice is-dismissible"><p><strong>با موفقیت ثبت شد.</strong></p></div>';
            }
        }

        public static function register_settings() {
            register_setting( 'vardi_kit_sms_gateway_group', self::OPTION_GATEWAY );
            register_setting( 'vardi_kit_sms_admin_group', self::OPTION_ADMIN );
            register_setting( 'vardi_kit_sms_admin_group', self::OPTION_PATTERN, [ 'sanitize_callback' => [ __CLASS__, 'sanitize_pattern_options' ] ] );
            register_setting( 'vardi_kit_sms_customer_group', self::OPTION_CUSTOMER );
            register_setting( 'vardi_kit_sms_customer_group', self::OPTION_PATTERN, [ 'sanitize_callback' => [ __CLASS__, 'sanitize_pattern_options' ] ] );
            register_setting( 'vardi_kit_sms_pattern_group', self::OPTION_PATTERN, [ 'sanitize_callback' => [ __CLASS__, 'sanitize_pattern_options' ] ] );
        }

        /**
         * از بین نرفتن تنظیمات پترن بین تب‌ها و پاکسازی داده‌ها
         */
        public static function sanitize_pattern_options( $input ) {
            $existing = get_option( self::OPTION_PATTERN, [] );

            if ( ! is_array( $input ) ) {
                return $existing;
            }

            $clean = [];

            foreach ( $input as $field => $statuses ) {
                if ( ! is_array( $statuses ) ) {
                    continue;
                }

                foreach ( $statuses as $status_key => $value ) {
                    $safe_key = sanitize_key( $status_key );

                    if ( false !== strpos( (string) $field, 'tokens' ) ) {
                        $clean[ $field ][ $safe_key ] = array_map( 'sanitize_text_field', array_values( (array) $value ) );
                    } else {
                        $clean[ $field ][ $safe_key ] = sanitize_text_field( $value );
                    }
                }
            }

            return array_replace_recursive( $existing, $clean );
        }

        public static function handle_manual_sms_sending() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'vardi_kit_manual_sms_nonce' ) ) {   wp_send_json_error( 'خطای امنیتی.' ); }
            if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'شما دسترسی لازم برای این کار را ندارید.' ); }
            $message = sanitize_textarea_field( $_POST['message'] ?? '' );

            $recipients = [];
            if ( isset( $_POST['recipients'] ) && is_array( $_POST['recipients'] ) ) {
                foreach ( $_POST['recipients'] as $number ) {
                    $clean = trim( sanitize_text_field( $number ) );
                    if ( ! empty( $clean ) ) {
                        $recipients[] = $clean;
                    }
                }
            }

            $extra_numbers_raw = sanitize_textarea_field( $_POST['extra_numbers'] ?? '' );
            if ( ! empty( $extra_numbers_raw ) ) {
                $extra_split = preg_split( '/[\s,]+/', $extra_numbers_raw, -1, PREG_SPLIT_NO_EMPTY );
                foreach ( $extra_split as $num ) {
                    $clean = trim( $num );
                    if ( ! empty( $clean ) ) {
                        $recipients[] = $clean;
                    }
                }
            }

            $recipients = array_values( array_unique( $recipients ) );

            if ( empty( $recipients ) || empty( $message ) ) { wp_send_json_error( 'گیرندگان و متن پیام نمی‌توانند خالی باشند.' ); }
            $api = new Vardi_SMS_API_Client();
            $result = $api->send( $recipients, $message );
            if ( $result['success'] ) { wp_send_json_success( 'پیامک با موفقیت برای ارسال در صف قرار گرفت. پیام سرور: ' . esc_html($result['message']) );
            } else { wp_send_json_error( 'خطا در ارسال: ' . esc_html($result['message'] ?? 'پاسخ نامشخص از سرور.') ); }
        }

        public static function handle_pattern_test() {
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'vardi_kit_pattern_test_nonce' ) ) { wp_send_json_error( 'خطای امنیتی.' ); }
            if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'شما دسترسی لازم برای این کار را ندارید.' ); }

            $context     = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
            $status      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
            $recipient   = isset( $_POST['recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient'] ) ) : '';
            $pattern_id  = isset( $_POST['pattern_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern_id'] ) ) : '';
            $tokens_raw  = isset( $_POST['tokens'] ) && is_array( $_POST['tokens'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tokens'] ) ) : [];

            if ( empty( $context ) || empty( $status ) ) { wp_send_json_error( 'لطفا نوع پیام و وضعیت را انتخاب کنید.' ); }
            if ( empty( $recipient ) ) { wp_send_json_error( 'شماره گیرنده را وارد کنید.' ); }

            $pattern_options = get_option( self::OPTION_PATTERN, [] );
            $pattern_field   = ( 'admin' === $context ) ? 'admin_pattern_id' : 'customer_pattern_id';
            $tokens_field    = ( 'admin' === $context ) ? 'admin_pattern_tokens' : 'customer_pattern_tokens';

            if ( empty( $pattern_id ) ) {
                $pattern_id = $pattern_options[ $pattern_field ][ $status ] ?? '';
            }
            if ( empty( $tokens_raw ) ) {
                $tokens_raw = (array) ( $pattern_options[ $tokens_field ][ $status ] ?? [] );
            }

            if ( empty( $pattern_id ) ) { wp_send_json_error( 'کد پترن برای این وضعیت ثبت نشده است.' ); }

            $api      = new Vardi_SMS_API_Client();
            $response = $api->send_pattern( $recipient, $pattern_id, $tokens_raw );

            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->info(
                    sprintf( 'Pattern test (%s/%s) to %s: %s', $context, $status, $recipient, $response['message'] ?? '—' ),
                    [ 'source' => 'vardi-kit-sms' ]
                );
            }

            if ( ! empty( $response['success'] ) ) {
                wp_send_json_success( 'پیامک تست پترن با موفقیت ارسال شد. پاسخ: ' . esc_html( $response['message'] ?? '' ) );
            }

            wp_send_json_error( 'خطا در ارسال پترن: ' . esc_html( $response['message'] ?? 'پاسخ نامشخص از سرور.' ) );
        }

        public static function handle_status_fetch() {
            $nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            $context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
            $status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

            if ( ! wp_verify_nonce( $nonce, 'vardi_kit_status_nonce' ) ) { wp_send_json_error( 'خطای امنیتی.' ); }
            if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'شما دسترسی لازم برای این کار را ندارید.' ); }
            if ( empty( $context ) || empty( $status ) ) { wp_send_json_error( 'اطلاعات کافی برای دریافت تنظیمات وجود ندارد.' ); }

            $option_key = ( 'admin' === $context ) ? self::OPTION_ADMIN : ( ( 'customer' === $context ) ? self::OPTION_CUSTOMER : '' );
            if ( empty( $option_key ) ) { wp_send_json_error( 'زمینه نامعتبر است.' ); }

            $gateway_options = get_option( self::OPTION_GATEWAY, array() );
            $options         = get_option( $option_key, array() );
            $pattern_options = get_option( self::OPTION_PATTERN, array() );

            $template_field   = ( 'admin' === $context ) ? 'admin_sms_template' : 'customer_sms_template';
            $pattern_id_field = ( 'admin' === $context ) ? 'admin_pattern_id' : 'customer_pattern_id';
            $token_field      = ( 'admin' === $context ) ? 'admin_pattern_tokens' : 'customer_pattern_tokens';
            $sender_field     = ( 'admin' === $context ) ? 'admin_sender_numbers' : 'customer_sender_numbers';

            $modes           = $options['status_modes'] ?? [];
            $text_value      = $options[ $template_field ][ $status ] ?? '';
            $sender_value    = $options[ $sender_field ][ $status ] ?? ( $gateway_options['sender_number'] ?? '' );
            $pattern_value   = $pattern_options[ $pattern_id_field ][ $status ] ?? '';
            $token_values    = array_map( 'sanitize_text_field', (array) ( $pattern_options[ $token_field ][ $status ] ?? [] ) );
            $mode            = $modes[ $status ] ?? ( ! empty( $pattern_value ) ? 'pattern' : 'text' );

            wp_send_json_success(
                [
                    'mode'       => $mode,
                    'sender'     => $sender_value,
                    'template'   => $text_value,
                    'pattern_id' => $pattern_value,
                    'tokens'     => $token_values,
                ]
            );
        }


        public static function render_sms_page_wrapper() {
            if ( ! current_user_can( 'manage_options' ) ) return;
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gateway';
            ?>
            <div class="wrap vardi-kit-admin-wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <p>از این بخش می‌توانید سیستم اطلاع‌رسانی پیامکی فروشگاه خود را مدیریت و پیکربندی کنید.</p>
                <h2 class="nav-tab-wrapper">
                    <a href="?page=vardi-woocommerce-sms&tab=gateway" class="nav-tab <?php echo 'gateway' === $active_tab ? 'nav-tab-active' : ''; ?>">وب‌سرویس</a>
                    <a href="?page=vardi-woocommerce-sms&tab=admin_notif" class="nav-tab <?php echo 'admin_notif' === $active_tab ? 'nav-tab-active' : ''; ?>">پیامک مدیر</a>
                    <a href="?page=vardi-woocommerce-sms&tab=customer_notif" class="nav-tab <?php echo 'customer_notif' === $active_tab ? 'nav-tab-active' : ''; ?>">پیامک کاربران</a>
                    <a href="?page=vardi-woocommerce-sms&tab=manual_send" class="nav-tab <?php echo 'manual_send' === $active_tab ? 'nav-tab-active' : ''; ?>">ارسال دستی</a>
                    <a href="?page=vardi-woocommerce-sms&tab=archive" class="nav-tab <?php echo 'archive' === $active_tab ? 'nav-tab-active' : ''; ?>">آرشیو پیامک‌ها</a>
                </h2>

                <?php
                // فرم و دکمه ذخیره در هر تابع render مربوط به تب قرار دارد
                switch ( $active_tab ) {
                    case 'admin_notif': self::render_admin_notif_tab(); break;
                    case 'customer_notif': self::render_customer_notif_tab(); break;
                    case 'manual_send': self::render_manual_send_tab(); break;
                    case 'archive': self::render_archive_tab(); break;
                    default: self::render_gateway_tab(); break;
                }
                ?>
            </div>
            <?php
        }

        private static function render_gateway_tab() {
            $options = get_option( self::OPTION_GATEWAY, array() );
            $api_key_is_set = ! empty( trim($options['api_key'] ?? '') );
            $credit_info = ['success' => false, 'message' => 'API Key وارد نشده است.'];
            if ( $api_key_is_set ) { $api = new Vardi_SMS_API_Client( $options ); $credit_info = $api->get_credit(); }
            ?>
            <form action="options.php" method="post">
                <?php settings_fields( 'vardi_kit_sms_gateway_group' ); ?>
                <h3>تنظیمات اصلی وب‌سرویس پیامک</h3>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row"><label for="vardi_sms_enable">فعال‌سازی سیستم پیامک</label></th><td><input type="checkbox" id="vardi_sms_enable" name="<?php echo esc_attr( self::OPTION_GATEWAY ); ?>[enable_sms]" value="1" <?php checked( ! empty( $options['enable_sms'] ) ); ?>></td></tr>
                    <tr><th scope="row"><label for="vardi_sms_api_key">کد دسترسی (ApiKey)</label></th><td><input type="text" id="vardi_sms_api_key" name="<?php echo esc_attr( self::OPTION_GATEWAY ); ?>[api_key]" value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" class="regular-text ltr"></td></tr>
                    <tr><th scope="row"><label for="vardi_sms_sender_number">شماره خط ارسال کننده</label></th><td><input type="text" id="vardi_sms_sender_number" name="<?php echo esc_attr( self::OPTION_GATEWAY ); ?>[sender_number]" value="<?php echo esc_attr( $options['sender_number'] ?? '' ); ?>" class="regular-text ltr"><p class="description">شماره خطی که در پنل پیامک خود تعریف کرده‌اید را وارد کنید.</p></td></tr>
                    <tr><th scope="row">اعتبار پنل</th><td><?php if ( $credit_info['success'] && isset( $credit_info['data']['result']['credit'] ) ) : ?><strong style="font-size: 1.2em; color: green;"><?php echo esc_html( number_format_i18n( $credit_info['data']['result']['credit'] ) ); ?> ریال</strong><?php else : ?><span style="color: red;">خطا در دریافت اعتبار.</span><p class="description"><?php echo esc_html( $credit_info['message'] ?? 'پاسخی از سرور دریافت نشد.' ); ?></p><?php endif; ?></td></tr>
                    </tbody></table>
                <?php submit_button( 'ذخیره تغییرات' ); ?>
            </form>
            <?php
        }


        private static function render_admin_notif_tab() {
            $gateway_options  = get_option( self::OPTION_GATEWAY, array() );
            $options          = get_option( self::OPTION_ADMIN, array() );
            $pattern_options  = get_option( self::OPTION_PATTERN, array() );
            $order_statuses   = self::get_order_status_list();
            $sender_number    = $gateway_options['sender_number'] ?? '';
            self::render_status_styles();
            ?>
            <form action="options.php" method="post">
                <?php settings_fields( 'vardi_kit_sms_admin_group' ); ?>
                <input type="hidden" class="vardi-status-nonce" value="<?php echo esc_attr( wp_create_nonce( 'vardi_kit_status_nonce' ) ); ?>">
                <h3>پیامک مدیر</h3>
                <p class="description">ارسال پیامک عادی یا پترن برای مدیران با انتخاب وضعیت‌های فعال و نوع ارسال.</p>

                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row"><label for="vardi_sms_enable_admin_sms">ارسال پیامک به مدیران کل</label></th><td><input type="checkbox" id="vardi_sms_enable_admin_sms" name="<?php echo esc_attr( self::OPTION_ADMIN ); ?>[enable_admin_sms]" value="1" <?php checked( ! empty( $options['enable_admin_sms'] ) ); ?>><p class="description">با فعال‌سازی این گزینه، در هنگام ثبت یا تغییر سفارش، برای مدیران کل سایت پیامک ارسال می‌گردد.</p></td></tr>
                    <tr><th scope="row"><label for="vardi_sms_admin_mobiles">📞 شماره موبایل مدیران کل</label></th><td><input type="text" id="vardi_sms_admin_mobiles" name="<?php echo esc_attr( self::OPTION_ADMIN ); ?>[admin_mobiles]" value="<?php echo esc_attr( $options['admin_mobiles'] ?? '' ); ?>" class="regular-text ltr" placeholder="مثلاً: 09111111111"></td></tr>
                </tbody></table>

                <hr>
                <h3>وضعیت‌های دریافت پیامک</h3>
                <p class="description">هر وضعیت را فعال کنید تا فیلدهای متن یا پترن برای آن نمایش داده شود.</p>
                <div class="vardi-status-grid">
                    <?php self::render_status_cards( 'admin', $options, $pattern_options, $order_statuses, $sender_number ); ?>
                </div>

                <?php self::render_pattern_shortcodes_help(); ?>

                <?php submit_button( 'ذخیره تغییرات' ); ?>
            </form>
            <?php
        }

        private static function render_customer_notif_tab() {
            $gateway_options  = get_option( self::OPTION_GATEWAY, array() );
            $options          = get_option( self::OPTION_CUSTOMER, array() );
            $pattern_options  = get_option( self::OPTION_PATTERN, array() );
            $order_statuses   = self::get_order_status_list();
            $sender_number    = $gateway_options['sender_number'] ?? '';
            self::render_status_styles();
            ?>
            <form action="options.php" method="post">
                <?php settings_fields( 'vardi_kit_sms_customer_group' ); ?>
                <input type="hidden" class="vardi-status-nonce" value="<?php echo esc_attr( wp_create_nonce( 'vardi_kit_status_nonce' ) ); ?>">
                <h3>پیامک کاربران</h3>
                <p class="description">ظاهر و تجربه کاربری مشابه بخش مدیر، با این تفاوت که پیامک‌ها برای مشتریان ارسال می‌شود.</p>

                <div class="vardi-card-grid">
                    <div class="vardi-card">
                        <h4>تنظیمات عمومی</h4>
                        <table class="form-table" role="presentation"><tbody>
                            <tr><th scope="row">فعال</th><td><input type="checkbox" name="<?php echo esc_attr( self::OPTION_CUSTOMER ); ?>[enable_customer_sms]" value="1" <?php checked( ! empty( $options['enable_customer_sms'] ) ); ?>><p class="description">در هنگام ثبت یا تغییر وضعیت سفارش، پیامک برای مشتریان ارسال می‌شود.</p></td></tr>
                            <tr><th scope="row">اختیار دریافت پیامک توسط مشتری</th><td><input type="checkbox" name="<?php echo esc_attr( self::OPTION_CUSTOMER ); ?>[enable_sms_opt_in_checkout]" value="1" <?php checked( ! empty( $options['enable_sms_opt_in_checkout'] ) ); ?>><p class="description">نمایش گزینه دریافت پیامک در صفحه پرداخت.</p></td></tr>
                            <tr><th scope="row"><label for="sms_opt_in_checkout_text">متن اطلاع مشتری</label></th><td><input type="text" id="sms_opt_in_checkout_text" name="<?php echo esc_attr( self::OPTION_CUSTOMER ); ?>[sms_opt_in_checkout_text]" value="<?php echo esc_attr( $options['sms_opt_in_checkout_text'] ?? 'مایل هستم از وضعیت سفارش از طریق پیامک آگاه شوم.' ); ?>" class="regular-text"><p class="description">متن کنار چک‌باکس رضایت دریافت پیامک.</p></td></tr>
                            <tr>
                                <th scope="row"><label for="vardi_sms_customer_phone_meta_key">کلید فیلد شماره موبایل</label></th>
                                <td>
                                    <input type="text" id="vardi_sms_customer_phone_meta_key" name="<?php echo esc_attr( self::OPTION_CUSTOMER ); ?>[customer_phone_meta_key]" value="<?php echo esc_attr( $options['customer_phone_meta_key'] ?? 'billing_phone' ); ?>" class="regular-text ltr">
                                    <p class="description">در صورتی که فیلد شماره موبایل مشتری با کلید دیگری (مثلاً افزونه دیجیتس یا فیلد سفارشی) ذخیره می‌شود، نام کلید متای سفارش را وارد کنید. مقدار پیش‌فرض <code>billing_phone</code> است.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="vardi_sms_customer_phone_field_key">کلید فیلد در فرم پرداخت</label></th>
                                <td>
                                    <input type="text" id="vardi_sms_customer_phone_field_key" name="<?php echo esc_attr( self::OPTION_CUSTOMER ); ?>[customer_phone_field_key]" value="<?php echo esc_attr( $options['customer_phone_field_key'] ?? '' ); ?>" class="regular-text ltr">
                                    <p class="description">در صورت تفاوت نام فیلد فرم پرداخت با کلید متا، اینجا نام ورودی فرم (مثلاً <code>sellPayPhone</code>) را وارد کنید تا مقدار هنگام ثبت سفارش ذخیره و در پیامک‌ها استفاده شود.</p>
                                </td>
                            </tr>
                        </tbody></table>
                    </div>
                </div>

                <hr>
                <h3>وضعیت‌های دریافت پیامک</h3>
                <p class="description">وضعیت فعال → نمایش فیلدها. برای هر وضعیت نوع ارسال (عادی یا پترن) را انتخاب کنید.</p>
                <div class="vardi-status-grid">
                    <?php self::render_status_cards( 'customer', $options, $pattern_options, $order_statuses, $sender_number ); ?>
                </div>

                <?php self::render_pattern_shortcodes_help(); ?>

                <?php submit_button( 'ذخیره تغییرات' ); ?>
            </form>
            <?php
        }

        private static function render_status_styles() {
            static $printed = false;
            if ( $printed ) { return; }
            $printed = true;
            ?>
            <style>
                .vardi-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
                .vardi-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
                .vardi-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-top: 15px; }
                .vardi-status-card { border: 1px solid #dcdcde; background: #fff; border-radius: 8px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
                .vardi-status-card .vardi-status-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
                .vardi-status-body { border-top: 1px solid #eee; padding-top: 10px; display: none; }
                .vardi-status-body.is-active { display: block; }
                .vardi-mode-switch { display: flex; gap: 10px; margin-bottom: 10px; }
                .vardi-mode-switch label { background: #f6f7f7; border: 1px solid #dcdcde; padding: 6px 10px; border-radius: 6px; cursor: pointer; }
                .vardi-mode-switch input:checked + span { font-weight: 700; color: #1d2327; }
                .vardi-mode-panel { display: none; border: 1px dashed #dcdcde; padding: 10px; border-radius: 6px; background: #fbfbfb; }
                .vardi-mode-panel.is-active { display: block; }
                .vardi-token-row { display: flex; gap: 8px; margin-bottom: 6px; align-items: center; }
                .vardi-token-row label { min-width: 36px; text-align: center; background: #eef1f4; padding: 4px 6px; border-radius: 4px; font-weight: 600; }
                .vardi-inline-note { font-size: 12px; color: #50575e; margin-top: 6px; display: block; }
                .vardi-shortcode-help { margin-top: 18px; }
                .vardi-shortcode-help table code { direction: ltr; font-size: 13px; }
                .vardi-shortcode-help .vardi-copy-shortcode { white-space: nowrap; }
                .vardi-shortcode-help .vardi-copy-feedback { color: #198754; font-weight: 600; margin-right: 8px; display: none; }
            </style>
            <?php
        }

        private static function render_pattern_shortcodes_help() {
            $shortcodes = self::get_pattern_shortcodes_reference();
            if ( empty( $shortcodes ) ) {
                return;
            }
            ?>
            <div class="vardi-shortcode-help">
                <h4>راهنمای شورت‌کدهای پترن</h4>
                <p class="description">شورت‌کدهای زیر را در فیلد «توکن‌ها» قرار دهید تا مقادیر سفارش به‌صورت خودکار در الگو جایگزین شوند.</p>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 26%;">شورت‌کد</th>
                            <th>توضیحات</th>
                            <th style="width: 120px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $shortcodes as $code => $label ) : ?>
                            <tr>
                                <td><code class="vardi-shortcode-code"><?php echo esc_html( $code ); ?></code></td>
                                <td><?php echo esc_html( $label ); ?></td>
                                <td>
                                    <span class="vardi-copy-feedback" aria-live="polite"></span>
                                    <button type="button" class="button button-small vardi-copy-shortcode" data-code="<?php echo esc_attr( $code ); ?>">کپی شورت‌کد</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">نکته: ترتیب توکن‌ها باید مطابق با ترتیب تعریف‌شده در پنل پیامک شما باشد.</p>
            </div>
            <?php
        }

        private static function get_pattern_shortcodes_reference() {
            return [
                '{order_id}'          => 'شناسه سفارش',
                '{name}'              => 'نام مشتری',
                '{last_name}'         => 'نام خانوادگی مشتری',
                '{mobile}'            => 'شماره موبایل خریدار',
                '{email}'             => 'ایمیل خریدار',
                '{status}'            => 'نام وضعیت سفارش (مثلاً در حال انجام)',
                '{all_items_qty}'     => 'لیست محصولات به همراه تعداد',
                '{all_items}'         => 'لیست نام محصولات سفارش',
                '{price}'             => 'مبلغ کل سفارش',
                '{transaction_id}'    => 'شناسه تراکنش پرداخت',
                '{payment_method}'    => 'روش پرداخت',
                '{description}'       => 'یادداشت یا توضیح سفارش',
                '{shipping_method}'   => 'روش ارسال انتخاب‌شده',
                '{b_company}'         => 'نام شرکت در آدرس صورتحساب',
                '{b_first_name}'      => 'نام در آدرس صورتحساب',
                '{b_last_name}'       => 'نام خانوادگی در آدرس صورتحساب',
                '{b_country}'         => 'کشور صورتحساب',
                '{b_state}'           => 'استان صورتحساب',
                '{b_city}'            => 'شهر صورتحساب',
                '{b_address_1}'       => 'آدرس صورتحساب',
                '{b_postcode}'        => 'کدپستی صورتحساب',
                '{s_company}'         => 'نام شرکت در آدرس ارسال',
                '{s_first_name}'      => 'نام در آدرس ارسال',
                '{s_last_name}'       => 'نام خانوادگی در آدرس ارسال',
                '{s_country}'         => 'کشور آدرس ارسال',
                '{s_state}'           => 'استان آدرس ارسال',
                '{s_city}'            => 'شهر آدرس ارسال',
                '{s_address_1}'       => 'آدرس کامل ارسال',
                '{s_postcode}'        => 'کدپستی آدرس ارسال',
                '{post_tracking_url}' => 'لینک رهگیری مرسوله',
                '{post_tracking_code}' => 'کد رهگیری پستی',
            ];
        }

        private static function render_status_cards( $context, $options, $pattern_options, $order_statuses, $sender_number ) {
            $option_key       = ( 'admin' === $context ) ? self::OPTION_ADMIN : self::OPTION_CUSTOMER;
            $status_field     = ( 'admin' === $context ) ? 'admin_notif_statuses' : 'customer_notif_statuses';
            $template_field   = ( 'admin' === $context ) ? 'admin_sms_template' : 'customer_sms_template';
            $pattern_id_field = ( 'admin' === $context ) ? 'admin_pattern_id' : 'customer_pattern_id';
            $token_field      = ( 'admin' === $context ) ? 'admin_pattern_tokens' : 'customer_pattern_tokens';
            $sender_field     = ( 'admin' === $context ) ? 'admin_sender_numbers' : 'customer_sender_numbers';
            $status_nonce     = wp_create_nonce( 'vardi_kit_status_nonce' );

            $selected_statuses = $options[ $status_field ] ?? [];
            $modes             = $options['status_modes'] ?? [];
            $sender_overrides  = $options[ $sender_field ] ?? [];

            foreach ( $order_statuses as $slug => $name ) {
                $template_key    = str_replace( 'wc-', '', $slug );
                $is_enabled      = in_array( $slug, $selected_statuses, true );
                $mode            = $modes[ $template_key ] ?? ( ! empty( $pattern_options[ $pattern_id_field ][ $template_key ] ) ? 'pattern' : 'text' );
                $text_value      = $options[ $template_field ][ $template_key ] ?? '';
                $sender_value    = $sender_overrides[ $template_key ] ?? $sender_number;
                $pattern_value   = $pattern_options[ $pattern_id_field ][ $template_key ] ?? '';
                $token_values    = $pattern_options[ $token_field ][ $template_key ] ?? [];
                $display_tokens  = max( 3, count( $token_values ) );

                $body_id = 'vardi-status-body-' . esc_attr( $context . '-' . $template_key );
                ?>
                <div class="vardi-status-card" data-status="<?php echo esc_attr( $template_key ); ?>" data-status-slug="<?php echo esc_attr( $slug ); ?>" data-context="<?php echo esc_attr( $context ); ?>" data-fetch-nonce="<?php echo esc_attr( $status_nonce ); ?>" data-sender-input="<?php echo esc_attr( $option_key . '[' . $sender_field . '][' . $template_key . ']' ); ?>" data-template-input="<?php echo esc_attr( $option_key . '[' . $template_field . '][' . $template_key . ']' ); ?>" data-pattern-input="<?php echo esc_attr( self::OPTION_PATTERN . '[' . $pattern_id_field . '][' . $template_key . ']' ); ?>" data-token-input-base="<?php echo esc_attr( self::OPTION_PATTERN . '[' . $token_field . '][' . $template_key . '][]' ); ?>">
                    <div class="vardi-status-head">
                        <label><input type="checkbox" class="vardi-status-toggle" data-target="<?php echo esc_attr( $body_id ); ?>" name="<?php echo esc_attr( $option_key ); ?>[<?php echo esc_attr( $status_field ); ?>][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_enabled ); ?>> <?php echo esc_html( $name ); ?></label>
                        <span class="description">فعال → نمایش فیلدها</span>
                    </div>
                    <div class="vardi-status-body <?php echo $is_enabled ? 'is-active' : ''; ?>" id="<?php echo esc_attr( $body_id ); ?>">
                        <div class="vardi-mode-switch">
                            <label><input type="radio" class="vardi-mode-radio" data-target="text" name="<?php echo esc_attr( $option_key ); ?>[status_modes][<?php echo esc_attr( $template_key ); ?>]" value="text" <?php checked( 'pattern' !== $mode ); ?>><span>ارسال عادی</span></label>
                            <label><input type="radio" class="vardi-mode-radio" data-target="pattern" name="<?php echo esc_attr( $option_key ); ?>[status_modes][<?php echo esc_attr( $template_key ); ?>]" value="pattern" <?php checked( 'pattern' === $mode ); ?>><span>ارسال پترن</span></label>
                        </div>

                        <div class="vardi-mode-panel vardi-mode-panel-text <?php echo ( 'pattern' !== $mode ) ? 'is-active' : ''; ?>">
                            <p><strong>ارسال عادی</strong></p>
                            <label>شماره ارسال کننده</label>
                            <input type="text" class="regular-text ltr" name="<?php echo esc_attr( $option_key ); ?>[<?php echo esc_attr( $sender_field ); ?>][<?php echo esc_attr( $template_key ); ?>]" value="<?php echo esc_attr( $sender_value ); ?>">
                            <span class="vardi-inline-note">در صورت خالی بودن از شماره پیش‌فرض تب «وب‌سرویس» استفاده می‌شود.</span>
                            <label style="display:block; margin-top:8px;">متن پیامک</label>
                            <?php $textarea_id = 'text-' . $context . '-' . $template_key; ?>
                            <textarea id="<?php echo esc_attr( $textarea_id ); ?>" name="<?php echo esc_attr( $option_key ); ?>[<?php echo esc_attr( $template_field ); ?>][<?php echo esc_attr( $template_key ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $text_value ); ?></textarea>
                        </div>

                        <div class="vardi-mode-panel vardi-mode-panel-pattern <?php echo ( 'pattern' === $mode ) ? 'is-active' : ''; ?>">
                            <p><strong>ارسال پترن</strong> (نیازی به شماره ارسال کننده نیست)</p>
                            <label for="pattern-<?php echo esc_attr( $context . '-' . $template_key ); ?>">کد پترن</label>
                            <input type="text" id="pattern-<?php echo esc_attr( $context . '-' . $template_key ); ?>" name="<?php echo esc_attr( self::OPTION_PATTERN ); ?>[<?php echo esc_attr( $pattern_id_field ); ?>][<?php echo esc_attr( $template_key ); ?>]" class="regular-text ltr" value="<?php echo esc_attr( $pattern_value ); ?>" placeholder="مثلاً 12345">
                            <div class="vardi-inline-note">توکن‌ها را به ترتیب الگوی پنل پیامک وارد کنید.</div>
                            <div class="vardi-token-wrapper" data-name-base="<?php echo esc_attr( self::OPTION_PATTERN . '[' . $token_field . '][' . $template_key . '][]' ); ?>">
                                <?php for ( $i = 0; $i < $display_tokens; $i++ ) : $token_val = $token_values[ $i ] ?? ''; ?>
                                    <div class="vardi-token-row">
                                        <label>{<?php echo esc_html( $i ); ?>}</label>
                                        <input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_PATTERN . '[' . $token_field . '][' . $template_key . '][]' ); ?>" value="<?php echo esc_attr( $token_val ); ?>" placeholder="شورت‌کد یا مقدار برای {<?php echo esc_attr( $i ); ?>}">
                                    </div>
                                <?php endfor; ?>
                                <button type="button" class="button add-pattern-token-button" data-index="<?php echo esc_attr( $display_tokens ); ?>">
                                    <span class="dashicons dashicons-plus-alt"></span> افزودن توکن
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

        private static function get_recent_order_phones( $limit = 50 ) {
            if ( ! class_exists( 'WC_Order_Query' ) || ! function_exists( 'wc_get_orders' ) ) {
                return [];
            }

            $order_ids = wc_get_orders( [
                'limit'   => $limit,
                'orderby' => 'date',
                'order'   => 'DESC',
                'return'  => 'ids',
            ] );

            if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
                return [];
            }

            $result = [];
            $seen   = [];

            foreach ( $order_ids as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) { continue; }
                $phone = trim( (string) $order->get_billing_phone() );
                if ( empty( $phone ) || isset( $seen[ $phone ] ) ) { continue; }
                $seen[ $phone ] = true;

                $name = trim( $order->get_formatted_billing_full_name() );
                if ( empty( $name ) ) {
                    $name = 'سفارش #' . $order_id;
                }

                $result[] = [
                    'name'  => $name,
                    'phone' => $phone,
                ];
            }

            return $result;
        }


        private static function get_recipients_by_roles( $roles ) {
            $users = get_users( [
                'role__in' => $roles,
                'fields'   => [ 'display_name', 'user_email', 'ID', 'user_login' ],
                'number'   => 400,
            ] );

            $result = [];
            foreach ( $users as $user ) {
                $mobile = get_user_meta( $user->ID, 'billing_phone', true );
                if ( empty( $mobile ) ) { $mobile = get_user_meta( $user->ID, 'phone', true ); }
                if ( empty( $mobile ) ) { $mobile = get_user_meta( $user->ID, 'mobile', true ); }
                if ( empty( $mobile ) ) { continue; }
                $result[] = [
                    'name'  => $user->display_name ?: $user->user_login,
                    'phone' => $mobile,
                ];
            }

            return $result;
        }



        private static function render_manual_send_tab() {
            $customers = self::get_recipients_by_roles( [ 'customer', 'subscriber' ] );
            $staff     = self::get_recipients_by_roles( [ 'administrator', 'shop_manager', 'editor' ] );
            $recent_orders = self::get_recent_order_phones();
            $order_statuses = self::get_order_status_list();
            ?>
            <style>
                .vardi-manual-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; align-items: start; }
                .vardi-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
                .vardi-recipient-list { max-height: 210px; overflow-y: auto; border: 1px solid #ececec; padding: 8px; border-radius: 6px; background: #fafafa; }
                .vardi-recipient-list label { display: block; margin-bottom: 6px; }
                .vardi-recipient-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
                .vardi-manual-grid h4 { margin-top: 0; }
                .vardi-token-chip { background: #f6f7f7; border: 1px solid #dcdcde; padding: 3px 6px; border-radius: 4px; display: inline-block; margin-left: 4px; }
                .vardi-manual-actions { display: flex; gap: 10px; align-items: center; margin-top: 10px; }
                .vardi-pattern-test { margin-top: 16px; }
                .vardi-pattern-test .vardi-token-wrapper { border: 1px dashed #dcdcde; padding: 10px; border-radius: 6px; background: #fbfbfb; }
                .vardi-pattern-test .vardi-token-row { display: flex; gap: 8px; margin-bottom: 6px; align-items: center; }
                .vardi-pattern-test .vardi-token-row label { min-width: 36px; text-align: center; background: #eef1f4; padding: 4px 6px; border-radius: 4px; font-weight: 600; }
            </style>
            <h3>ارسال پیامک دستی</h3>
            <p>گیرندگان را انتخاب کنید، در صورت نیاز شماره جدید اضافه کنید و سپس پیامک عادی ارسال نمایید.</p>
            <div id="vardi-manual-sms-response" style="margin-bottom: 15px;"></div>

            <div class="vardi-manual-grid">
                <div class="vardi-card">
                    <h4>گیرندگان</h4>
                    <p class="description">لیست مشتریان و مدیران را انتخاب کنید یا شماره جدید وارد نمایید.</p>
                    <div class="vardi-recipient-group">
                        <div class="vardi-recipient-header">
                            <strong>مشتریان</strong>
                            <label><input type="checkbox" class="vardi-select-all" data-group="customers"> انتخاب همه</label>
                        </div>
                        <div class="vardi-recipient-list" data-group="customers">
                            <?php if ( empty( $customers ) ) : ?>
                                <p class="description">شماره موبایل معتبری برای مشتریان پیدا نشد.</p>
                            <?php else : foreach ( $customers as $recipient ) : ?>
                                <label><input type="checkbox" class="vardi-recipient-checkbox" data-number="<?php echo esc_attr( $recipient['phone'] ); ?>"> <?php echo esc_html( $recipient['name'] . ' (' . $recipient['phone'] . ')' ); ?></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <div class="vardi-recipient-group" style="margin-top: 12px;">
                        <div class="vardi-recipient-header">
                            <strong>مدیران و پشتیبان‌ها</strong>
                            <label><input type="checkbox" class="vardi-select-all" data-group="staff"> انتخاب همه</label>
                        </div>
                        <div class="vardi-recipient-list" data-group="staff">
                            <?php if ( empty( $staff ) ) : ?>
                                <p class="description">شماره موبایل معتبری برای مدیران یافت نشد.</p>
                            <?php else : foreach ( $staff as $recipient ) : ?>
                                <label><input type="checkbox" class="vardi-recipient-checkbox" data-number="<?php echo esc_attr( $recipient['phone'] ); ?>"> <?php echo esc_html( $recipient['name'] . ' (' . $recipient['phone'] . ')' ); ?></label>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <?php if ( ! empty( $recent_orders ) ) : ?>
                        <div class="vardi-recipient-group" style="margin-top: 12px;">
                            <div class="vardi-recipient-header">
                                <strong>خریداران اخیر</strong>
                                <label><input type="checkbox" class="vardi-select-all" data-group="orders"> انتخاب همه</label>
                            </div>
                            <div class="vardi-recipient-list" data-group="orders">
                                <?php foreach ( $recent_orders as $recipient ) : ?>
                                    <label><input type="checkbox" class="vardi-recipient-checkbox" data-number="<?php echo esc_attr( $recipient['phone'] ); ?>"> <?php echo esc_html( $recipient['name'] . ' (' . $recipient['phone'] . ')' ); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top:6px;">این بخش بر اساس آخرین سفارش‌ها تکمیل شده است.</p>
                        </div>
                    <?php endif; ?>

                    <div class="vardi-recipient-group" style="margin-top: 12px;">
                        <label for="manual_extra_numbers"><strong>افزودن شماره دلخواه</strong></label>
                        <textarea id="manual_extra_numbers" rows="3" class="large-text ltr" placeholder="شماره‌ها را با ویرگول یا خط جدید وارد کنید"></textarea>
                        <span class="description">برای مثال: 09120000000, 09130000000</span>
                    </div>
                </div>

                <div class="vardi-card" id="vardi-manual-sms-form">
                    <div style="margin-bottom: 18px;">
                        <h4>پیامک عادی</h4>
                        <textarea id="manual_sms_message" name="manual_sms_message" rows="5" class="large-text"></textarea>
                        <div class="vardi-manual-actions">
                            <?php wp_nonce_field( 'vardi_kit_manual_sms_nonce', 'vardi_sms_nonce' ); ?>
                            <button type="button" id="vardi_send_manual_sms_button" class="button button-primary">ارسال پیامک</button>
                            <span class="spinner" style="float: none; vertical-align: middle;"></span>
                        </div>
                    </div>

                    <p class="description" style="margin-top: 10px;">گزارش کامل ارسال‌ها از تب «آرشیو پیامک‌ها» قابل مشاهده است.</p>
                </div>
                <div class="vardi-card vardi-pattern-test" id="vardi-pattern-test-form">
                    <h4>تست دستی ارسال پترن</h4>
                    <p class="description">برای بررسی صحت تنظیمات، پترن مدیر یا کاربر را روی یک شماره تستی ارسال کنید و پاسخ وب‌سرویس را دریافت نمایید.</p>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <label><input type="radio" name="vardi_pattern_test_context" value="admin" checked> پترن مدیر</label>
                        <label><input type="radio" name="vardi_pattern_test_context" value="customer"> پترن کاربر</label>
                    </div>
                    <div style="margin-top:10px;">
                        <label for="vardi_pattern_test_status"><strong>وضعیت سفارش</strong></label>
                        <select id="vardi_pattern_test_status" class="regular-text" style="min-width:220px;">
                            <?php foreach ( $order_statuses as $slug => $label ) : $key = str_replace( 'wc-', '', $slug ); ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-top:10px;">
                        <label for="vardi_pattern_test_recipient"><strong>شماره گیرنده</strong></label>
                        <input type="text" id="vardi_pattern_test_recipient" class="regular-text ltr" placeholder="0912...">
                    </div>
                    <div style="margin-top:10px;">
                        <label for="vardi_pattern_test_pattern_id"><strong>کد پترن</strong></label>
                        <input type="text" id="vardi_pattern_test_pattern_id" class="regular-text ltr" placeholder="OtpId">
                    </div>
                    <div style="margin-top:10px;">
                        <label><strong>توکن‌ها به ترتیب</strong></label>
                        <div class="vardi-token-wrapper" data-name-base="vardi_pattern_test_tokens[]">
                            <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                <div class="vardi-token-row"><label>{<?php echo esc_html( $i ); ?>}</label><input type="text" class="regular-text" name="vardi_pattern_test_tokens[]" placeholder="مقدار یا شورت‌کد"></div>
                            <?php endfor; ?>
                            <button type="button" class="button add-pattern-token-button" data-index="3"><span class="dashicons dashicons-plus-alt"></span> افزودن توکن</button>
                        </div>
                    </div>
                    <div class="vardi-manual-actions">
                        <?php wp_nonce_field( 'vardi_kit_pattern_test_nonce', 'vardi_pattern_test_nonce' ); ?>
                        <input type="hidden" id="vardi_pattern_test_fetch_nonce" value="<?php echo esc_attr( wp_create_nonce( 'vardi_kit_status_nonce' ) ); ?>">
                        <button type="button" id="vardi_pattern_test_button" class="button button-primary">ارسال پیامک تست</button>
                        <span class="spinner" style="float:none; vertical-align: middle;"></span>
                    </div>
                    <div id="vardi-pattern-test-response" style="margin-top:10px;"></div>
                </div>
            </div>
            <?php
        }

        private static function render_archive_tab() {
            echo '<h3>آرشیو پیامک‌های ارسالی</h3>';
            if ( class_exists( 'Vardi_SMS_Log_Table' ) ) {
                $log_table = new Vardi_SMS_Log_Table();
                $log_table->prepare_items();
                $log_table->display();
            } else {
                echo '<p>خطا: کلاس جدول لاگ یافت نشد.</p>';
            }
        }

        private static function get_order_status_list() {
            return [
                    'wc-new-order' => 'در انتظار پرداخت (بلافاصله بعد از ثبت سفارش)',
                    'wc-pending' => 'در انتظار پرداخت (بعد از تغییر وضعیت سفارش)',
                    'wc-processing' => 'در حال انجام',
                    'wc-on-hold' => 'در انتظار بررسی',
                    'wc-completed' => 'تکمیل شده',
                    'wc-cancelled' => 'لغو شده',
                    'wc-failed' => 'ناموفق',
                    'wc-refunded' => 'مسترد شده',
                    'wc-draft' => 'پیش‌نویس',
                    'wc-post-returned' => 'هنگام بازگشت پستی',
            ];
        }
    }
    Vardi_SMS_Admin_Settings::init();
}
