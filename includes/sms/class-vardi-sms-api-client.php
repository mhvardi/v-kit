<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vardi_SMS_API_Client {

	private $api_key;
	private $sender_number;
	private $base_url = 'https://api.limosms.com/api/';

        public function __construct( $gateway_options = [] ) {
                // اگر تنظیمات مستقیم پاس داده نشد، از دیتابیس بخوان
                if ( empty( $gateway_options ) ) {
                        $gateway_options = get_option( 'vardi_kit_sms_gateway_options', [] );
                }
                $this->api_key = trim( $gateway_options['api_key'] ?? '' );
                $this->sender_number = trim( $gateway_options['sender_number'] ?? '' );
        }

	private function post_request($endpoint, $data) {
		if (empty($this->api_key)) {
			return ['success' => false, 'message' => 'خطا: کلید API در تنظیمات وارد نشده است.'];
		}

		$url = $this->base_url . $endpoint;
		$args = [
			'method'    => 'POST',
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'ApiKey'       => $this->api_key, // کلید API در هدر ارسال می‌شود
			],
			'body'      => json_encode($data), // داده‌ها به صورت JSON ارسال می‌شوند
		];

		// تابع استاندارد وردپرس برای ارسال درخواست POST
		$response = wp_remote_post($url, $args);

		// بررسی خطاهای اتصال اولیه (مثل خطای cURL یا فایروال هاست)
		if (is_wp_error($response)) {
			$error_message = 'خطای اتصال وردپرس: ' . $response->get_error_message();
			// در اینجا می‌توان لاگ خطا را ذخیره کرد (اختیاری)
			return ['success' => false, 'message' => $error_message];
		}

		// بررسی کد وضعیت HTTP پاسخ (باید 200 باشد)
		$http_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($http_code !== 200) {
			// خطاهایی مثل 401 (Unauthorized) یا 403 (Forbidden) معمولاً به دلیل کلید API اشتباه یا محدودیت IP است
			$error_message = sprintf(
				'خطا از سرور پیامک (کد وضعیت: %d). این خطا معمولاً به دلیل محدودیت IP یا اشتباه بودن کلید API رخ می‌دهد. پاسخ سرور: %s',
				$http_code,
				esc_html($response_body) // نمایش پاسخ خام سرور برای عیب‌یابی
			);
			return ['success' => false, 'message' => $error_message];
		}

		// بررسی اینکه آیا پاسخ دریافتی یک JSON معتبر است یا خیر
		$body = json_decode($response_body, true);

		if ($body === null) {
			$error_message = 'خطا: پاسخ دریافت شده از سرور پیامک معتبر نبود (فرمت JSON اشتباه است). پاسخ خام: ' . esc_html($response_body);
			return ['success' => false, 'message' => $error_message];
		}

		// استخراج وضعیت موفقیت و پیام از پاسخ JSON
		$is_success = $body['success'] ?? false; // کلید success با حروف کوچک
		$api_message = $body['message'] ?? 'پاسخ دریافتی از API نامشخص بود.'; // کلید message با حروف کوچک

		return ['success' => $is_success, 'message' => $api_message, 'data' => $body];
	}

	/**
	 * ارسال پیامک عادی (متن دلخواه)
	 */
        public function send($recipients, $message, $sender_override = null) {
                $sender = $sender_override ? trim($sender_override) : $this->sender_number;
                $data = [
                        'SenderNumber' => $sender,
                        'Message'      => $message,
                        'MobileNumber' => (array) $recipients, // همیشه به صورت آرایه ارسال شود
                ];
		return $this->post_request('sendsms', $data);
	}

	/**
	 * ارسال پیامک با استفاده از پترن (الگو)
	 *
	 * @param string $recipient شماره موبایل گیرنده (فقط یک شماره)
	 * @param int|string $pattern_id کد پترن (OtpId)
	 * @param array $tokens آرایه‌ای از مقادیر نهایی برای جایگزینی در پترن (مثلا ["علی", "12345"])
	 * @return array پاسخ API
	 */
        public function send_pattern($recipient, $pattern_id, $tokens) {
                // اطمینان از اینکه توکن‌ها همیشه یک آرایه ساده و عددی هستند
                $token_values = array_values((array) ($tokens ?? []));

                $data = [
                        'OtpId'         => trim((string) $pattern_id), // کد پترن باید بدون دستکاری ارسال شود تا صفرهای ابتدایی از بین نرود
                        'MobileNumber'  => $recipient,                 // شماره گیرنده
                        'ReplaceToken'  => $token_values,              // آرایه مقادیر جایگزین
                ];

                // برخی وب‌سرویس‌ها نیاز دارند خط ارسال‌کننده نیز همراه درخواست پترن ارسال شود
                if ( ! empty( $this->sender_number ) ) {
                        $data['SenderNumber'] = $this->sender_number;
                }

                return $this->post_request('sendpatternmessage', $data);
        }

	/**
	 * دریافت اعتبار باقیمانده حساب
	 */
	public function get_credit() {
		// این متد نیاز به ارسال بدنه خالی {} دارد
		return $this->post_request('getcurrentcredit', new stdClass());
	}
}