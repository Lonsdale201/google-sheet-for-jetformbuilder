<?php

namespace GoogleSheet\JetFormBuilder\Settings;

use GoogleSheet\JetFormBuilder\Plugin;
use Jet_Form_Builder\Admin\Tabs_Handlers\Base_Handler;
use Jet_Form_Builder\Admin\Pages\Pages_Manager;

class SettingsTab extends Base_Handler {

	public function slug() {
		return 'google-sheet-settings-tab';
	}

	public function before_assets() {
		$handle = Plugin::instance()->slug() . '-' . $this->slug();

		wp_enqueue_style( Pages_Manager::STYLE_ADMIN );
		wp_enqueue_script( Pages_Manager::SCRIPT_VUEX_PACKAGE );
		wp_enqueue_script( Pages_Manager::SCRIPT_PACKAGE );

		$script_path = Plugin::instance()->path( 'assets/js/settings-tab.js' );
		$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : Plugin::instance()->version();
		$style_path  = Plugin::instance()->path( 'assets/css/settings-tab.css' );
		$style_ver   = file_exists( $style_path ) ? (string) filemtime( $style_path ) : Plugin::instance()->version();

		wp_deregister_script( $handle );

		wp_register_script(
			$handle,
			Plugin::instance()->url( 'assets/js/settings-tab.js' ),
			array(
				Pages_Manager::SCRIPT_VUEX_PACKAGE,
				'wp-hooks',
				'wp-i18n',
			),
			$version,
			true
		);

		wp_enqueue_script( $handle );
		wp_enqueue_style(
			$handle . '-styles',
			Plugin::instance()->url( 'assets/css/settings-tab.css' ),
			array( Pages_Manager::STYLE_ADMIN ),
			$style_ver
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				$handle,
				'google-sheet-for-jetformbuilder'
			);
		}

		$sa = SettingsRepository::get_service_account();

		wp_localize_script(
			$handle,
			'GSJFBSettingsMeta',
			array(
				'isConfigured'      => SettingsRepository::is_configured(),
				'serviceAccountEmail' => $sa['client_email'] ?? '',
			)
		);
	}

	public function on_get_request() {
		$payload = array(
			'credentials_json' => $this->sanitize_credentials_json( $_POST['credentials_json'] ?? '' ),
			'debug_enabled'    => ! empty( $_POST['debug_enabled'] ) && rest_sanitize_boolean( wp_unslash( $_POST['debug_enabled'] ) ),
		);

		$result = $this->update_options( $payload );

		$this->send_response( $result );
	}

	public function on_load() {
		$options = $this->get_options( SettingsRepository::defaults() );

		$options['credentials_json'] = isset( $options['credentials_json'] ) && is_string( $options['credentials_json'] )
			? $options['credentials_json']
			: '';

		$options['debug_enabled'] = isset( $options['debug_enabled'] )
			? (bool) $options['debug_enabled']
			: false;

		return $options;
	}

	private function sanitize_credentials_json( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( wp_unslash( $value ) );

		if ( '' === $value ) {
			return '';
		}

		$decoded = json_decode( $value, true );

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		return wp_json_encode( $decoded );
	}
}
