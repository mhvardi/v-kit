<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vardi_Woocommerce_SMS {

	private static $_instance = null;
	private $gateway_options;
        private $admin_options;
        private $customer_options;
        private $pattern_options; // **NEW**: Holds pattern settings
        private $gateway_sender = '';
        public $api;
        private $shortcode_mappings = [];
        private $customer_phone_meta_key = 'billing_phone';
        private $customer_phone_field_key = '';

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

        private function __construct() {
                $this->gateway_options  = get_option( 'vardi_kit_sms_gateway_options', [] );
                $this->admin_options    = get_option( 'vardi_kit_sms_admin_options', [] );
                $this->customer_options = get_option( 'vardi_kit_sms_customer_options', [] );
                $this->pattern_options  = get_option( 'vardi_kit_sms_pattern_options', [] ); // **NEW**: Load pattern options
                $this->gateway_sender   = $this->gateway_options['sender_number'] ?? '';
                $this->customer_phone_meta_key  = trim( $this->customer_options['customer_phone_meta_key'] ?? 'billing_phone' );
                $this->customer_phone_field_key = trim( $this->customer_options['customer_phone_field_key'] ?? '' );

                if ( empty( $this->gateway_options['enable_sms'] ) || empty( $this->gateway_options['api_key'] ) ) {
                        return;
                }

                $this->api = new Vardi_SMS_API_Client( $this->gateway_options );

                add_action( 'woocommerce_order_status_changed', [ $this, 'trigger_sms_on_status_change' ], 10, 4 );
                add_action( 'woocommerce_checkout_order_processed', [ $this, 'trigger_sms_on_new_order_processed' ], 10, 2 );
                add_filter( 'woocommerce_order_actions', [ $this, 'register_manual_order_actions' ] );
                add_action( 'woocommerce_order_action_vardi_resend_admin_sms', [ $this, 'handle_resend_admin_sms' ] );
                add_action( 'woocommerce_order_action_vardi_resend_customer_sms', [ $this, 'handle_resend_customer_sms' ] );

                add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'capture_custom_phone_field' ], 5, 2 );

		add_action( 'woocommerce_low_stock', [ $this, 'trigger_low_stock_sms' ] );
		add_action( 'woocommerce_product_set_stock_status_outofstock', [ $this, 'trigger_out_of_stock_sms' ], 10, 3 );

		if ( ! empty( $this->customer_options['enable_sms_opt_in_checkout'] ) ) {
			add_action( 'woocommerce_after_order_notes', [ $this, 'add_sms_checkout_field' ] );
			add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_sms_checkout_field' ] );
		}
	}

	public function trigger_sms_on_new_order_processed( $order_id, $posted_data ) {
		$order = wc_get_order( $order_id );
		if ($order) {
			$this->send_order_sms_notifications( $order, 'new-order', 'wc-new-order' );
		}
	}

        public function trigger_sms_on_status_change( $order_id, $old_status, $new_status, $order ) {
                $this->send_order_sms_notifications( $order, $new_status, 'wc-' . $new_status );
        }

        private function send_order_sms_notifications( $order, $status_key, $full_status_key, $record_callback = null, $allowed_audiences = [ 'admin', 'customer' ] ) {
                if ( ! $order instanceof WC_Order ) {
                        return [];
                }

                $logs = [];
                $append_log = function( $entry ) use ( $record_callback, &$logs ) {
                        $logs[] = $entry;

                        if ( is_callable( $record_callback ) ) {
                                call_user_func( $record_callback, $entry );
                        }
                };

                $admin_modes                = $this->admin_options['status_modes'] ?? [];
                $customer_modes             = $this->customer_options['status_modes'] ?? [];
                $selected_admin_statuses    = $this->admin_options['admin_notif_statuses'] ?? [];
                $selected_customer_statuses = $this->customer_options['customer_notif_statuses'] ?? [];
                $admin_sender_numbers       = $this->admin_options['admin_sender_numbers'] ?? [];
                $customer_sender_numbers    = $this->customer_options['customer_sender_numbers'] ?? [];

                // --- Send to Admin ---
                if ( in_array( 'admin', $allowed_audiences, true ) ) {
                        $admin_pattern_id        = $this->pattern_options['admin_pattern_id'][ $status_key ] ?? '';
                        $admin_tokens_shortcodes = $this->pattern_options['admin_pattern_tokens'][ $status_key ] ?? [];
                        $admin_mode              = $admin_modes[ $status_key ] ?? ( ! empty( $admin_pattern_id ) ? 'pattern' : 'text' );

                        if ( ! empty( $this->admin_options['enable_admin_sms'] ) && in_array( $full_status_key, $selected_admin_statuses, true ) ) {
                                $admin_phones_raw = $this->admin_options['admin_mobiles'] ?? '';

                                if ( ! empty( $admin_phones_raw ) ) {
                                        $admin_phones = array_map( 'trim', explode( ',', $admin_phones_raw ) );

                                        if ( 'pattern' === $admin_mode && ! empty( $admin_pattern_id ) ) {
                                                $final_tokens = [];
                                                foreach ( $admin_tokens_shortcodes as $shortcode ) {
                                                        $final_tokens[] = $this->process_token_content( $shortcode, $order );
                                                }

                                                foreach ( $admin_phones as $phone ) {
                                                        if ( ! empty( $phone ) ) {
                                                                $response = $this->api->send_pattern( $phone, $admin_pattern_id, $final_tokens );
                                                                $append_log([
                                                                        'audience'  => 'admin',
                                                                        'mode'      => 'pattern',
                                                                        'recipient' => $phone,
                                                                        'success'   => (bool) ( $response['success'] ?? false ),
                                                                        'message'   => $response['message'] ?? '',
                                                                ]);
                                                        }
                                                }
                                        } else {
                                                if ( 'pattern' === $admin_mode && empty( $admin_pattern_id ) ) {
                                                        $append_log([
                                                                'audience'  => 'admin',
                                                                'mode'      => 'pattern',
                                                                'recipient' => implode( ', ', $admin_phones ),
                                                                'success'   => false,
                                                                'message'   => __( 'کد پترن برای مدیر تنظیم نشده است.', 'vardi-kit' ),
                                                        ]);
                                                } else {
                                                        $message_template = $this->admin_options['admin_sms_template'][ $status_key ] ?? '';

                                                        if ( ! empty( $admin_phones ) && ! empty( $message_template ) ) {
                                                                $message  = $this->process_token_content( $message_template, $order );
                                                                $sender   = $admin_sender_numbers[ $status_key ] ?? $this->gateway_sender;
                                                                $response = $this->api->send( $admin_phones, $message, $sender );

                                                                $append_log([
                                                                        'audience'  => 'admin',
                                                                        'mode'      => 'text',
                                                                        'recipient' => implode( ', ', $admin_phones ),
                                                                        'success'   => (bool) ( $response['success'] ?? false ),
                                                                        'message'   => $response['message'] ?? '',
                                                                ]);
                                                        } elseif ( empty( $message_template ) ) {
                                                                $append_log([
                                                                        'audience'  => 'admin',
                                                                        'mode'      => 'text',
                                                                        'recipient' => implode( ', ', $admin_phones ),
                                                                        'success'   => false,
                                                                        'message'   => __( 'متن پیامک مدیر برای این وضعیت خالی است.', 'vardi-kit' ),
                                                                ]);
                                                        }
                                                }
                                        }
                                } else {
                                        $append_log([
                                                'audience'  => 'admin',
                                                'mode'      => $admin_mode,
                                                'recipient' => '',
                                                'success'   => false,
                                                'message'   => __( 'شماره‌ای برای مدیران ثبت نشده است.', 'vardi-kit' ),
                                        ]);
                                }
                        }
                }

                // --- Send to Customer ---
                if ( in_array( 'customer', $allowed_audiences, true ) ) {
                        $opt_in_enabled    = ! empty( $this->customer_options['enable_sms_opt_in_checkout'] );
                        $customer_opted_in = $order->get_meta( '_sms_opt_in' ) === 'yes';

                        if ( ! empty( $this->customer_options['enable_customer_sms'] ) && ( ! $opt_in_enabled || $customer_opted_in ) && in_array( $full_status_key, $selected_customer_statuses, true ) ) {
                                $customer_phone = $this->get_order_customer_phone( $order );

                                if ( ! empty( $customer_phone ) ) {
                                        $pattern_id        = $this->pattern_options['customer_pattern_id'][ $status_key ] ?? '';
                                        $tokens_shortcodes = $this->pattern_options['customer_pattern_tokens'][ $status_key ] ?? [];
                                        $customer_mode     = $customer_modes[ $status_key ] ?? ( ! empty( $pattern_id ) ? 'pattern' : 'text' );

                                        if ( 'pattern' === $customer_mode && ! empty( $pattern_id ) ) {
                                                $final_tokens = [];
                                                foreach ( $tokens_shortcodes as $shortcode ) {
                                                        $final_tokens[] = $this->process_token_content( $shortcode, $order );
                                                }

                                                $response = $this->api->send_pattern( $customer_phone, $pattern_id, $final_tokens );
                                                $append_log([
                                                        'audience'  => 'customer',
                                                        'mode'      => 'pattern',
                                                        'recipient' => $customer_phone,
                                                        'success'   => (bool) ( $response['success'] ?? false ),
                                                        'message'   => $response['message'] ?? '',
                                                ]);
                                        } else {
                                                $message_template = $this->customer_options['customer_sms_template'][ $status_key ] ?? '';

                                                if ( ! empty( $message_template ) ) {
                                                        $message  = $this->process_token_content( $message_template, $order );
                                                        $sender   = $customer_sender_numbers[ $status_key ] ?? $this->gateway_sender;
                                                        $response = $this->api->send( [ $customer_phone ], $message, $sender );

                                                        $append_log([
                                                                'audience'  => 'customer',
                                                                'mode'      => 'text',
                                                                'recipient' => $customer_phone,
                                                                'success'   => (bool) ( $response['success'] ?? false ),
                                                                'message'   => $response['message'] ?? '',
                                                        ]);
                                                } else {
                                                        $append_log([
                                                                'audience'  => 'customer',
                                                                'mode'      => 'text',
                                                                'recipient' => $customer_phone,
                                                                'success'   => false,
                                                                'message'   => __( 'متن پیامک کاربر برای این وضعیت خالی است.', 'vardi-kit' ),
                                                        ]);
                                                }
                                        }
                                } elseif ( 'pattern' === ( $customer_modes[ $status_key ] ?? 'text' ) ) {
                                        $append_log([
                                                'audience'  => 'customer',
                                                'mode'      => 'pattern',
                                                'recipient' => '',
                                                'success'   => false,
                                                'message'   => __( 'کد پترن کاربر برای این وضعیت تنظیم نشده است.', 'vardi-kit' ),
                                        ]);
                                } else {
                                        $append_log([
                                                'audience'  => 'customer',
                                                'mode'      => $customer_modes[ $status_key ] ?? 'text',
                                                'recipient' => '',
                                                'success'   => false,
                                                'message'   => __( 'شماره موبایل مشتری یافت نشد.', 'vardi-kit' ),
                                        ]);
                                }
                        }
                }

                return $logs;
        }
	
	public function register_manual_order_actions( $actions ) {
	$actions['vardi_resend_admin_sms']    = __( 'ارسال مجدد پیامک مدیر', 'vardi-kit' );
	$actions['vardi_resend_customer_sms'] = __( 'ارسال مجدد پیامک کاربر', 'vardi-kit' );
	return $actions;
	}
	
	public function handle_resend_admin_sms( $order ) {
	$this->dispatch_manual_order_sms( $order, [ 'admin' ] );
	}
	
	public function handle_resend_customer_sms( $order ) {
	$this->dispatch_manual_order_sms( $order, [ 'customer' ] );
	}
	
	private function dispatch_manual_order_sms( $order, $audiences ) {
	if ( ! $order instanceof WC_Order ) {
	return;
	}
	
	$status_key      = $order->get_status();
	$full_status_key = 'wc-' . $status_key;
	$audiences       = is_array( $audiences ) ? $audiences : [ 'admin', 'customer' ];
	$status_label    = wc_get_order_status_name( $status_key );
	
	$logs = $this->send_order_sms_notifications(
	$order,
	$status_key,
	$full_status_key,
	function( $entry ) use ( $order, $status_label ) {
	$this->add_order_note_from_log( $order, $entry, $status_label );
	},
	$audiences
	);
	
	if ( empty( $logs ) ) {
	$order->add_order_note( __( 'هیچ پیامکی ارسال نشد (تنظیمات غیر فعال است یا شماره‌ای موجود نیست).', 'vardi-kit' ) );
	}
	}
	
        private function add_order_note_from_log( $order, $entry, $status_label ) {
        $audience_label = $entry['audience'] === 'admin' ? __( 'مدیر', 'vardi-kit' ) : __( 'کاربر', 'vardi-kit' );
        $recipient      = $entry['recipient'] ?? '';
        $message        = $entry['message'] ?? '';
        $mode_label     = $entry['mode'] === 'pattern' ? __( 'پترن', 'vardi-kit' ) : __( 'متن عادی', 'vardi-kit' );

	if ( ! empty( $entry['success'] ) ) {
	$note = sprintf(
	__( 'پیامک %1$s (%2$s) برای وضعیت "%3$s" با موفقیت به %4$s ارسال شد. پاسخ: %5$s', 'vardi-kit' ),
	$audience_label,
	$mode_label,
	$status_label,
	$recipient,
	$message
	);
	} else {
	$note = sprintf(
	__( 'خطا در ارسال پیامک %1$s (%2$s) برای وضعیت "%3$s" به %4$s: %5$s', 'vardi-kit' ),
	$audience_label,
	$mode_label,
	$status_label,
	$recipient,
	$message
	);
	}

        $order->add_order_note( $note );
        }

        /**
         * Retrieve customer phone from configurable order meta or fallbacks.
         */
        private function get_order_customer_phone( $order ) {
                if ( ! $order instanceof WC_Order ) {
                        return '';
                }

                $meta_key  = $this->customer_phone_meta_key ?: 'billing_phone';
                $field_key = $this->customer_phone_field_key;
                $order_id  = $order->get_id();

                $candidates = [];
                if ( ! empty( $field_key ) ) {
                        $candidates[] = $field_key;
                }
                if ( ! empty( $meta_key ) && $meta_key !== $field_key ) {
                        $candidates[] = $meta_key;
                }

                // WooCommerce هدرهای آدرس صورتحساب را معمولا با پیشوند _ ذخیره می‌کند؛ در صورت نیاز آنها را هم بررسی می‌کنیم.
                $underscored_meta = ltrim( $meta_key, '_' );
                if ( '_billing_phone' !== $meta_key ) {
                        $candidates[] = '_billing_phone';
                }
                if ( '_billing_phone' !== $underscored_meta && $underscored_meta !== $meta_key ) {
                        $candidates[] = '_' . $underscored_meta;
                }

                foreach ( array_unique( array_filter( $candidates ) ) as $key ) {
                        $value = (string) $order->get_meta( $key );
                        if ( empty( $value ) ) {
                                $value = (string) get_post_meta( $order_id, $key, true );
                        }
                        if ( ! empty( $value ) ) {
                                return trim( $value );
                        }
                }

                $billing_phone = $order->get_billing_phone();
                if ( ! empty( $billing_phone ) ) {
                        return trim( $billing_phone );
                }

                $user_id = $order->get_user_id();
                if ( $user_id ) {
                        $user_meta_keys = array_unique( array_filter( [ $meta_key, $field_key, '_billing_phone', 'billing_phone', 'phone', 'mobile' ] ) );
                        foreach ( $user_meta_keys as $user_meta_key ) {
                                $user_phone = get_user_meta( $user_id, $user_meta_key, true );
                                if ( ! empty( $user_phone ) ) {
                                        return trim( (string) $user_phone );
                                }
                        }
                }

                return '';
        }

        /**
         * Save custom phone field into order meta on checkout.
         */
        public function capture_custom_phone_field( $order_id, $data ) {
                $order = wc_get_order( $order_id );
                if ( ! $order instanceof WC_Order ) {
                        return;
                }

                $meta_key  = $this->customer_phone_meta_key ?: 'billing_phone';
                $field_key = $this->customer_phone_field_key ?: $meta_key;
                $phone_val = '';

                if ( ! empty( $field_key ) && isset( $_POST[ $field_key ] ) ) {
                        $phone_val = wc_clean( wp_unslash( $_POST[ $field_key ] ) );
                } elseif ( ! empty( $meta_key ) && isset( $_POST[ $meta_key ] ) ) {
                        $phone_val = wc_clean( wp_unslash( $_POST[ $meta_key ] ) );
                } elseif ( is_array( $data ) && isset( $data[ $field_key ] ) ) {
                        $phone_val = wc_clean( $data[ $field_key ] );
                }

                if ( empty( $phone_val ) ) {
                        return;
                }

                $order->update_meta_data( $field_key, $phone_val );
                if ( $meta_key !== $field_key ) {
                        $order->update_meta_data( $meta_key, $phone_val );
                }
                // اطمینان از ذخیره شدن در متای اصلی ووکامرس
                $order->update_meta_data( '_billing_phone', $phone_val );

                if ( empty( $order->get_billing_phone() ) ) {
                        $order->set_billing_phone( $phone_val );
                }

                $order->save();
        }

	/**
	 * **FINAL FIX**: This function is rewritten to correctly process shortcodes.
	 * It handles both single shortcodes (like '{name}') and templates containing multiple shortcodes.
	 * Returns an empty string if the input is empty or only whitespace.
	 * Returns an empty string if a single shortcode is not found (to prevent sending "{invalid_code}").
	 */
        private function process_token_content( $content, $order ) {
                if ( ! $order instanceof WC_Order ) {
                        return ''; // Return empty string if order is invalid
                }

		$trimmed_content = trim($content ?? ''); // Ensure content is a string and trim whitespace

		if ( empty($trimmed_content) ) {
			return ''; // Return empty string if content is empty after trimming
		}

		// Generate or retrieve mappings for the current order
		$order_id = $order->get_id();
                if (!isset($this->shortcode_mappings[$order_id])) {
                        $this->shortcode_mappings[$order_id] = $this->get_shortcode_mappings($order);
                }
		$mappings = $this->shortcode_mappings[$order_id];

		// Check if the content IS EXACTLY a single shortcode like {name}
		if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $trimmed_content, $matches)) {
			$shortcode_key_full = $matches[0]; // e.g., {name}
			// Return the direct value if the key exists, otherwise return an empty string.
			return (string) ($mappings[$shortcode_key_full] ?? '');
		} else {
			// Otherwise, treat it as a template string and replace all occurrences
			return str_replace(
				array_keys($mappings),
				array_values($mappings),
				$trimmed_content
			);
		}
	}


	/**
	 * Generates an array mapping shortcodes to their corresponding order values.
	 */
	private function get_shortcode_mappings($order) {
		if (!$order instanceof WC_Order) return [];

		$all_items = [];
		$all_items_qty = [];
		foreach ( $order->get_items() as $item ) {
			$all_items[] = $item->get_name();
			$all_items_qty[] = $item->get_name() . ' (تعداد: ' . $item->get_quantity() . ')';
		}

		$billing_country = $order->get_billing_country();
		$billing_state = $order->get_billing_state();
		$shipping_country = $order->get_shipping_country();
		$shipping_state = $order->get_shipping_state();

		return [
                        '{order_id}'         => $order->get_id(),
                        '{name}'             => $order->get_billing_first_name(),
                        '{last_name}'        => $order->get_billing_last_name(),
                        '{mobile}'           => $this->get_order_customer_phone( $order ),
                        '{email}'            => $order->get_billing_email(),
                        '{status}'           => wc_get_order_status_name( $order->get_status() ),
			'{all_items_qty}'    => implode( ' | ', $all_items_qty ),
			'{all_items}'        => implode( '، ', $all_items ),
			'{price}'            => wp_strip_all_tags( wc_price( $order->get_total() ) ),
			'{transaction_id}'   => $order->get_transaction_id() ?? '',
			'{payment_method}'   => $order->get_payment_method_title() ?? '',
			'{description}'      => $order->get_customer_note() ?? '',
			'{shipping_method}'  => $order->get_shipping_method() ?? '',
			'{b_company}'        => $order->get_billing_company() ?? '',
			'{b_first_name}'     => $order->get_billing_first_name() ?? '',
			'{b_last_name}'      => $order->get_billing_last_name() ?? '',
			'{b_country}'        => ($billing_country && isset(WC()->countries->get_countries()[$billing_country])) ? WC()->countries->get_countries()[$billing_country] : $billing_country,
			'{b_state}'          => ($billing_country && $billing_state && isset(WC()->countries->get_states($billing_country)[$billing_state])) ? WC()->countries->get_states($billing_country)[$billing_state] : $billing_state,
			'{b_city}'           => $order->get_billing_city() ?? '',
			'{b_address_1}'      => $order->get_billing_address_1() ?? '',
			'{b_postcode}'       => $order->get_billing_postcode() ?? '',
			'{s_company}'        => $order->get_shipping_company() ?? '',
			'{s_first_name}'     => $order->get_shipping_first_name() ?? '',
			'{s_last_name}'      => $order->get_shipping_last_name() ?? '',
			'{s_country}'        => ($shipping_country && isset(WC()->countries->get_countries()[$shipping_country])) ? WC()->countries->get_countries()[$shipping_country] : $shipping_country,
			'{s_state}'          => ($shipping_country && $shipping_state && isset(WC()->countries->get_states($shipping_country)[$shipping_state])) ? WC()->countries->get_states($shipping_country)[$shipping_state] : $shipping_state,
			'{s_city}'           => $order->get_shipping_city() ?? '',
			'{s_address_1}'      => $order->get_shipping_address_1() ?? '',
			'{s_postcode}'       => $order->get_shipping_postcode() ?? '',
			'{post_tracking_url}' => get_post_meta( $order->get_id(), '_post_tracking_url', true ),
			'{post_tracking_code}' => get_post_meta( $order->get_id(), '_post_tracking_code', true ),
		];
	}

	// --- Stock SMS Functions (Complete and unchanged) ---
	public function trigger_low_stock_sms( $product ) {
		$template = $this->admin_options['low_stock_template'] ?? '';
		$this->send_stock_sms($product, $template);
	}

	public function trigger_out_of_stock_sms( $product_id, $stock_status, $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) return;
		}
		if ($stock_status === 'outofstock') {
			$template = $this->admin_options['out_of_stock_template'] ?? '';
			$this->send_stock_sms($product, $template);
		}
	}

	private function send_stock_sms( $product, $template ) {
		if ( ! is_a( $product, 'WC_Product' ) || empty($template) ) return;
		$mobiles_raw = $this->admin_options['low_stock_mobiles'] ?? ($this->admin_options['admin_mobiles'] ?? '');
		if ( ! empty( $mobiles_raw ) ) {
			$mobiles = array_map('trim', explode(',', $mobiles_raw));
			$replacements = [ '{product_title}' => $product->get_name(), '{sku}' => $product->get_sku(), '{stock}' => $product->get_stock_quantity() ?? '0', ];
			$message = str_replace( array_keys($replacements), array_values($replacements), $template );
			$this->api->send( $mobiles, $message );
		}
	}

	// --- Checkout Opt-in Functions (Complete and unchanged) ---
	public function add_sms_checkout_field( $checkout ) {
		echo '<div id="sms_opt_in_checkout_field">';
		woocommerce_form_field( 'sms_opt_in', array( 'type' => 'checkbox', 'class' => array('input-checkbox'), 'label' => esc_html($this->customer_options['sms_opt_in_checkout_text'] ?? 'مایل هستم از وضعیت سفارش از طریق پیامک آگاه شوم.'), ), $checkout->get_value( 'sms_opt_in' ));
		echo '</div>';
	}

	public function save_sms_checkout_field( $order_id ) {
		if ( ! empty( $_POST['sms_opt_in'] ) ) {
			update_post_meta( $order_id, '_sms_opt_in', 'yes' );
		} else {
			delete_post_meta( $order_id, '_sms_opt_in' );
		}
	}
	} 