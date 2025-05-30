<?php
/**
 * File: Generic_AdminActions_Config.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_AdminActions_Config
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Generic_AdminActions_Config {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Generic_AdminActions_Config constructor method.
	 *
	 * Initializes the configuration object.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Imports the configuration settings from an uploaded file.
	 *
	 * @return void
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 */
	public function w3tc_config_import() {
		$error = '';

		$config = new Config();

		if ( ! isset( $_FILES['config_file']['error'] ) || UPLOAD_ERR_NO_FILE === $_FILES['config_file']['error'] ) {
			$error = 'config_import_no_file';
		} elseif ( UPLOAD_ERR_OK !== $_FILES['config_file']['error'] ) {
			$error = 'config_import_upload';
		} else {
			$imported = $config->import(
				isset( $_FILES['config_file']['tmp_name'] ) ?
					esc_url_raw( wp_unslash( $_FILES['config_file']['tmp_name'] ) ) : ''
			);

			if ( ! $imported ) {
				$error = 'config_import_import';
			}
		}

		if ( $error ) {
			Util_Admin::redirect( array( 'w3tc_error' => $error ), true );
			return;
		}

		Util_Admin::config_save( $this->_config, $config );
		Util_Admin::redirect( array( 'w3tc_note' => 'config_import' ), true );
	}

	/**
	 * Exports the current configuration settings to a file.
	 *
	 * Outputs the exported JSON and terminates script execution.
	 *
	 * @return void
	 */
	public function w3tc_config_export() {
		$filename = substr( get_home_url(), strpos( get_home_url(), '//' ) + 2 );
		@header(
			sprintf(
				// Translators: 1 filename.
				__(
					'Content-Disposition: attachment; filename=%1$s.json',
					'w3-total-cache'
				),
				$filename
			)
		);
		echo $this->_config->export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		die();
	}

	/**
	 * Resets the configuration settings to their default values.
	 *
	 * @return void
	 */
	public function w3tc_config_reset() {
		$config = new Config();
		$config->set_defaults();
		Util_Admin::config_save( $this->_config, $config );

		$config_state = Dispatcher::config_state();
		$config_state->reset();
		$config_state->save();

		$config_state = Dispatcher::config_state_master();
		$config_state->reset();
		$config_state->save();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'config_reset',
			),
			true
		);
	}

	/**
	 * Enables the preview mode by copying production settings.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_enable() {
		ConfigUtil::preview_production_copy( Util_Environment::blog_id(), -1 );
		Util_Environment::set_preview( true );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_enable',
			)
		);
	}

	/**
	 * Disables the preview mode.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_disable() {
		$blog_id = Util_Environment::blog_id();
		ConfigUtil::remove_item( $blog_id, true );
		Util_Environment::set_preview( false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_disable',
			)
		);
	}

	/**
	 * Deploys preview settings to production.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_deploy() {
		ConfigUtil::preview_production_copy( Util_Environment::blog_id(), 1 );
		Util_Environment::set_preview( false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_deploy',
			)
		);
	}

	/**
	 * Saves the database cluster configuration file.
	 *
	 * @throws \Exception If the file write operation fails.
	 *
	 * @return void
	 */
	public function w3tc_config_dbcluster_config_save() {
		$params = array( 'page' => 'w3tc_general' );

		if ( ! file_put_contents( W3TC_FILE_DB_CLUSTER_CONFIG, Util_Request::get_string( 'newcontent' ) ) ) {
			try {
				Util_Activation::throw_on_write_error( W3TC_FILE_DB_CLUSTER_CONFIG );
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
				Util_Admin::redirect_with_custom_messages(
					$params,
					array(
						'dbcluster_save_failed' => $error,
					)
				);
			}
		}

		Util_Admin::redirect_with_custom_messages(
			$params,
			null,
			array(
				'dbcluster_save' => __( 'Database Cluster configuration file has been successfully saved', 'w3-total-cache' ),
			)
		);
	}

	/**
	 * Saves the "Support Us" configuration settings.
	 *
	 * Updates settings based on user actions like tweeting or signing up.
	 *
	 * @return void
	 */
	public function w3tc_config_save_support_us() {
		$tweeted      = Util_Request::get_boolean( 'tweeted' );
		$signmeup     = Util_Request::get_boolean( 'signmeup' );
		$accept_terms = Util_Request::get_boolean( 'accept_terms' );
		$this->_config->set( 'common.tweeted', $tweeted );

		$state_master = Dispatcher::config_state_master();
		if ( $accept_terms ) {
			$this->_config->set( 'common.track_usage', true );
			$state_master->set( 'license.community_terms', 'accept' );
		}
		$state_master->save();

		if ( $signmeup ) {
			if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
				$license = 'pro';
			} else {
				$license = 'community';
			}

			$email = filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL );

			wp_remote_post(
				W3TC_MAILLINGLIST_SIGNUP_URL,
				array(
					'body' => array(
						'email'   => $email,
						'license' => $license,
					),
				)
			);
		}
		$this->_config->save();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'config_save',
			)
		);
	}

	/**
	 * Updates the upload path option in the WordPress settings.
	 *
	 * @return void
	 */
	public function w3tc_config_update_upload_path() {
		update_option( 'upload_path', '' );

		Util_Admin::redirect();
	}

	/**
	 * Disables an overloaded configuration setting by its HTTP key.
	 *
	 * @param string $http_key The HTTP key of the setting to disable.
	 *
	 * @return void
	 */
	public function w3tc_config_overloaded_disable( $http_key ) {
		$c   = Dispatcher::config();
		$key = Util_Ui::config_key_from_http_name( $http_key );
		$c->set( $key, false );
		$c->save();

		Util_Admin::redirect( array() );
	}

	/**
	 * Enables an overloaded configuration setting by its HTTP key.
	 *
	 * @param string $http_key The HTTP key of the setting to enable.
	 *
	 * @return void
	 */
	public function w3tc_config_overloaded_enable( $http_key ) {
		$c   = Dispatcher::config();
		$key = Util_Ui::config_key_from_http_name( $http_key );
		$c->set( $key, true );
		$c->save();

		Util_Admin::redirect( array() );
	}
}
