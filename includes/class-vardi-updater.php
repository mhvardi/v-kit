<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Vardi_Kit_Updater {

    public $plugin_slug;
    public $version;
    public $remote_url;
    public $plugin_file;

    public function __construct( $plugin_file, $remote_url ) {
        $this->plugin_file = $plugin_file;
        $this->remote_url = $remote_url;
        $this->plugin_slug = plugin_basename( $this->plugin_file );
        
        $plugin_data = get_plugin_data( $this->plugin_file );
        $this->version = $plugin_data['Version'];

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
    }

    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote_info = $this->get_remote_info();

        if ( $remote_info && version_compare( $this->version, $remote_info->new_version, '<' ) ) {
            $plugin_obj = new stdClass();
            $plugin_obj->slug = basename($this->plugin_slug, '.php');
            $plugin_obj->plugin = $this->plugin_slug;
            $plugin_obj->new_version = $remote_info->new_version;
            $plugin_obj->url = $remote_info->url;
            $plugin_obj->package = $remote_info->package;
            
            $transient->response[ $this->plugin_slug ] = $plugin_obj;
        }

        return $transient;
    }

    private function get_remote_info() {
        $response = wp_remote_get( $this->remote_url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/json' ]
        ]);

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ) );
    }
}