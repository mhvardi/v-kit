<?php
/**
 * Plugin Name:       Vardi Kit
 * Description:       A collection of professional and SEO-friendly widgets for Elementor by Vardi Collection.
 * Version:           10.0.2 (Stable Path & Load Architecture)
 * Author:            محمد وردی
 * Author URI:        https://vardi.ir/
 * Text Domain:       vardi-kit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'VARDI_KIT_VERSION', '10.0.2' );
define( 'VARDI_KIT_DB_VERSION', '1.1' );
define( 'VARDI_KIT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'VARDI_KIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VARDI_KIT_MINIMUM_ELEMENTOR_VERSION', '3.0.0' );

final class Vardi_Kit {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		register_activation_hook( __FILE__, [ $this, 'on_activation' ] );
	}

	public function init() {
		// **FIXED**: Corrected syntax from $this. to $this->
		$this->load_dependencies();

		if ( get_option( 'vardi_kit_db_version' ) != VARDI_KIT_DB_VERSION ) {
			// **FIXED**: Corrected syntax from $this. to $this->
			$this->create_or_update_tables();
			update_option( 'vardi_kit_db_version', VARDI_KIT_DB_VERSION );
		}

		Vardi_Admin_Settings::get_instance();
		Vardi_Features::get_instance();

		if ( is_admin() ) {
			new Vardi_Kit_Updater( __FILE__, 'https://vardi.ir/updates/vardi-kit.json' );
		}

		if ( class_exists( 'WooCommerce' ) ) {
			Vardi_Woocommerce_SMS::get_instance();
		}

		// **FIXED**: Corrected syntax from $this. to $this->
		if ( $this->is_elementor_compatible() ) {
			add_action( 'elementor/init', [ $this, 'init_elementor' ] );
		}
	}

	private function load_dependencies() {
		// Main plugin classes
		require_once VARDI_KIT_PLUGIN_PATH . 'includes/admin/class-vardi-admin-settings.php';
		require_once VARDI_KIT_PLUGIN_PATH . 'includes/class-vardi-features.php';
		require_once VARDI_KIT_PLUGIN_PATH . 'includes/class-vardi-updater.php';

		// **FIX**: The lines below were commented out because they loaded jdf.php unconditionally,
		// ignoring the setting. The loading logic is now correctly handled
		// by Vardi_Features::load_shamsi_date() based on the user's setting.
		/*
		if ( file_exists( VARDI_KIT_PLUGIN_PATH . 'jdf.php' ) ) {
			require_once VARDI_KIT_PLUGIN_PATH . 'jdf.php';
		}
		*/

		// Load all SMS module files if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			require_once VARDI_KIT_PLUGIN_PATH . 'includes/sms/admin/class-vardi-sms-admin-settings.php';
			require_once VARDI_KIT_PLUGIN_PATH . 'includes/sms/class-vardi-sms-api-client.php';
			require_once VARDI_KIT_PLUGIN_PATH . 'includes/sms/admin/class-vardi-sms-log-table.php';
			require_once VARDI_KIT_PLUGIN_PATH . 'includes/sms/class-vardi-woocommerce-sms.php';
		}
	}

	public function is_elementor_compatible() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
			return false;
		}
		return version_compare( ELEMENTOR_VERSION, VARDI_KIT_MINIMUM_ELEMENTOR_VERSION, '>=' );
	}

	public function init_elementor() {
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	public function on_activation() {
		// **FIXED**: Corrected syntax from $this. to $this->
		$this->load_dependencies();
		// **FIXED**: Corrected syntax from $this. to $this->
		$this->create_or_update_tables();
		update_option( 'vardi_kit_db_version', VARDI_KIT_DB_VERSION, 'no' );
	}

	public function register_assets() {
		wp_register_style( 'vardi-kit-seo-box', VARDI_KIT_PLUGIN_URL . 'assets/css/widgets/seo-content-box.css', [], VARDI_KIT_VERSION );
		wp_register_script( 'vardi-kit-seo-box', VARDI_KIT_PLUGIN_URL . 'assets/js/widgets/seo-content-box.js', [ 'jquery' ], VARDI_KIT_VERSION, true );
		wp_register_style( 'vardi-kit-faq', VARDI_KIT_PLUGIN_URL . 'assets/css/widgets/vardi-faq.css', [], VARDI_KIT_VERSION );
		wp_register_script( 'vardi-kit-faq', VARDI_KIT_PLUGIN_URL . 'assets/js/widgets/vardi-faq.js', [ 'jquery' ], VARDI_KIT_VERSION, true );
	}
	public function register_widget_categories( $elements_manager ) {
		// **FIXED**: Corrected syntax from $elements_manager. to $elements_manager->
		$elements_manager->add_category( 'vardi-collection', [ 'title' => esc_html__( 'مجموعه وردی', 'vardi-kit' ) ] );
	}
	public function register_widgets( $widgets_manager ) {
		if ( file_exists(VARDI_KIT_PLUGIN_PATH . 'widgets/class-seo-content-box.php') ) {
			require_once( VARDI_KIT_PLUGIN_PATH . 'widgets/class-seo-content-box.php' );
			// **FIXED**: Corrected syntax from $widgets_manager. to $widgets_manager->
			$widgets_manager->register( new \SEO_Content_Box_Widget() );
		}
		if ( file_exists(VARDI_KIT_PLUGIN_PATH . 'widgets/class-vardi-faq.php') ) {
			require_once( VARDI_KIT_PLUGIN_PATH . 'widgets/class-vardi-faq.php' );
			// **FIXED**: Corrected syntax from $widgets_manager. to $widgets_manager->
			$widgets_manager->register( new \Vardi_FAQ_Widget() );
		}
	}
	public function create_or_update_tables() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	}
	public function admin_notice_missing_main_plugin() { /* Notice logic */ }
}

function vardi_kit_run() {
	return Vardi_Kit::instance();
}
vardi_kit_run();