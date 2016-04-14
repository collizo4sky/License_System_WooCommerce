<?php

namespace OmnipayWP\WooCommerce;

class License_System {

	/** @var string db option name */
	protected $option_name;

	/** @var string plugin license key */
	protected $license_key;

	/** @var string status of the license */
	protected $license_status;

	/** @var string plugin version number */
	protected $version_number;

	/** @var string plugin name */
	protected $item_name;

	/** @var string plugin developer */
	protected $plugin_developer = 'Agbonghama Collins';

	/** @var string URL of plugin store */
	protected $store_url;

	/** @var string file system path to plugin */
	protected $plugin_path;

	/** @var string payment gateway method ID */
	protected $method_id;

	/** @var string url of plugin settings page */
	protected $settings_page_url;

	public function init() {

		$this->license_status = get_option( "{$this->option_name}_license_status", false );

		$plugin_setting    = get_option( 'woocommerce_' . $this->method_id . '_settings', null );
		$this->license_key = $plugin_setting['license_key'];

		add_action( 'admin_init', array( $this, 'plugin_updater' ), 0 );
		add_action( 'admin_init', array( $this, 'activate_license' ), 0 );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->method_id,
			array( $this, 'settings_data_update' )
		);


		add_action( 'admin_notices', array( $this, 'license_admin_notice' ) );
		
	}

	public function set_option_name( $value ) {
		$this->option_name = $value;
	}

	public function set_version_number( $value ) {
		$this->version_number = $value;
	}

	public function set_item_name( $value ) {
		$this->item_name = $value;
	}

	public function set_plugin_developer( $value ) {
		$this->plugin_developer = $value;
	}

	public function set_store_url( $value ) {
		$this->store_url = $value;
	}

	public function set_license_key( $value ) {
		$this->license_key = $value;
	}

	public function set_plugin_path( $value ) {
		$this->plugin_path = $value;
	}

	public function set_method_id( $value ) {
		$this->method_id = $value;
	}

	public function set_settings_page_url( $value ) {
		$this->settings_page_url = $value;
	}


	/**
	 * Admin notice to activate license when license status isn't valid.
	 */
	public function license_admin_notice() {

		// this flag ensure this admin notice is displayed only one.
		static $flag = 0;

		if ( $flag > 0 ) {
			return;
		}

		// retrieve the license from the database
		$license_key = $this->license_key;

		// if license key isn't saved or license status is not valid, display notice
		if ( empty( $license_key ) || 'valid' != $this->license_status ) : ?>
			<div id="message" class="error notice"><p>
					<?php printf(
						__(
							'Enter and save your license key to receive %s plugin updates. <strong><a href="%s">Do it now</a></strong>.'
						),
						"<strong>{$this->item_name}</strong>",
						$this->settings_page_url
					); ?>
				</p></div>
		<?php endif;
		// increment by one
		++ $flag;
	}


	/**
	 * Deactivate license and license status when license key is changed.
	 *
	 * @return mixed
	 */
	public function settings_data_update() {
		$new = trim( sanitize_text_field( @$_POST[ $this->method_id . '-woocommerce_license_key' ] ) );
		$old = $this->license_key;
		if ( $new != $old ) {
			$this->deactivate_license();
			delete_option( "{$this->option_name}_license_status" );
		}
	}


	/**
	 * EDD Plugin update method
	 */
	public function plugin_updater() {

		// retrieve our license key from the DB
		$license_key = $this->license_key;

		if ( class_exists( 'EDD_SL_Plugin_Updater' ) && is_admin() ) {

			// setup the updater
			$edd_updater = new \EDD_SL_Plugin_Updater(
				$this->store_url,
				$this->plugin_path,
				array(
					'version'   => $this->version_number,            // current version number
					'license'   => $license_key,        // license key (used get_option above to retrieve from DB)
					'item_name' => $this->item_name,    // name of this plugin
					'author'    => $this->plugin_developer  // author of this plugin
				)
			);
		}

	}


	/** Activate license */
	public function activate_license() {

		// retrieve the license from the database
		$license = $this->license_key;

		// only run update if license status isn't valid
		if ( empty( $license ) || 'valid' == $this->license_status ) {
			return;
		}

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( $this->item_name ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, $this->store_url ),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			) );

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "valid" or "invalid"
		update_option( "{$this->option_name}_license_status", $license_data->license );
	}


	/**
	 * Deactivate license
	 */
	public function deactivate_license() {

		// retrieve the license from the database
		$license = $this->license_key;

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( $this->item_name ),
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, $this->store_url ),
			array(
				'timeout'   => 15,
				'sslverify' => false,
			) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return;
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data->license == 'deactivated' ) {
			delete_option( "{$this->option_name}_license_status" );
		}
	}

}
