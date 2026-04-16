<?php

namespace GoogleSheet\JetFormBuilder\Settings;

class SettingsRepository {

	public const OPTION_NAME = 'jet_form_builder_settings__google-sheet-settings-tab';

	public static function defaults(): array {
		return array(
			'credentials_json' => '',
			'debug_enabled'    => false,
		);
	}

	public static function get(): array {
		$stored = get_option( self::OPTION_NAME, '' );

		if ( is_string( $stored ) && $stored ) {
			$data = json_decode( $stored, true );
		} else {
			$data = is_array( $stored ) ? $stored : array();
		}

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return array(
			'credentials_json' => isset( $data['credentials_json'] ) && is_string( $data['credentials_json'] )
				? $data['credentials_json']
				: '',
			'debug_enabled'    => ! empty( $data['debug_enabled'] ),
		);
	}

	public static function credentials_json(): string {
		return self::get()['credentials_json'] ?? '';
	}

	public static function debug_enabled(): bool {
		return ! empty( self::get()['debug_enabled'] );
	}

	public static function get_service_account(): ?array {
		$json = self::credentials_json();

		if ( empty( $json ) ) {
			return null;
		}

		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		if ( empty( $decoded['client_email'] ) || empty( $decoded['private_key'] ) ) {
			return null;
		}

		return $decoded;
	}

	public static function is_configured(): bool {
		return null !== self::get_service_account();
	}
}
