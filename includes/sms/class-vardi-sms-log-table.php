<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// The "if" condition should be here, before the class definition.
if ( ! class_exists( 'Vardi_SMS_Log_Table' ) ) {
	class Vardi_SMS_Log_Table extends WP_List_Table {

		public function __construct() {
			parent::__construct( [
				'singular' => 'پیامک',
				'plural'   => 'پیامک‌ها',
				'ajax'     => false
			] );
		}

		public function get_columns() {
			return [
				'cb'           => '<input type="checkbox" />',
				'sent_at'      => 'زمان ارسال',
				'sent_to'      => 'گیرنده',
				'message_text' => 'متن پیام',
				'status'       => 'وضعیت',
				'response'     => 'پاسخ وب‌سرویس',
				'gateway'      => 'وب‌سرویس'
			];
		}

		public function column_default($item, $column_name) {
			return esc_html($item[$column_name]);
		}

		public function column_cb($item) {
			return sprintf('<input type="checkbox" name="log_id[]" value="%s" />', $item['id']);
		}

		protected function get_sortable_columns() {
			return [
				'sent_at' => ['sent_at', false]
			];
		}

		public function prepare_items() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'vardi_kit_sms_log';

			$per_page = 20;
			$columns = $this->get_columns();
			$hidden = [];
			$sortable = $this->get_sortable_columns();
			$this->_column_headers = [$columns, $hidden, $sortable];

			$current_page = $this->get_pagenum();
			$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

			$this->set_pagination_args([
				'total_items' => $total_items,
				'per_page'    => $per_page
			]);

			$orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'sent_at';
			$order = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'desc';
			$offset = ($current_page - 1) * $per_page;

			$this->items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
					$per_page,
					$offset
				), ARRAY_A
			);
		}
	}
}