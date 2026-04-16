<?php

namespace GoogleSheet\JetFormBuilder\GoogleSheets;

use GoogleSheet\JetFormBuilder\Settings\SettingsRepository;

class Client {

	private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
	private const SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets';
	private const SCOPE      = 'https://www.googleapis.com/auth/spreadsheets';

	private ?string $access_token = null;

	/**
	 * Create a JWT and exchange it for a Google access token.
	 */
	public function authenticate(): bool {
		$sa = SettingsRepository::get_service_account();

		if ( ! $sa ) {
			$this->log( 'No valid service account credentials configured.' );
			return false;
		}

		$jwt = $this->create_jwt( $sa['client_email'], $sa['private_key'] );

		if ( ! $jwt ) {
			$this->log( 'Failed to create JWT token.' );
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Token exchange failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
			$this->log( 'Token exchange returned no access_token: ' . $error );
			return false;
		}

		$this->access_token = $body['access_token'];

		return true;
	}

	/**
	 * Append a row to a Google Sheet.
	 *
	 * @param string $spreadsheet_id The spreadsheet ID.
	 * @param string $sheet_name     The sheet/tab name (e.g. "Sheet1").
	 * @param array  $values         Flat array of cell values for the new row.
	 *
	 * @return bool True on success.
	 */
	public function append_row( string $spreadsheet_id, string $sheet_name, array $values ): bool {
		if ( ! $this->access_token && ! $this->authenticate() ) {
			return false;
		}

		$range = $sheet_name . '!A:ZZ';

		$url = sprintf(
			'%s/%s/values/%s:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
			self::SHEETS_API,
			urlencode( $spreadsheet_id ),
			urlencode( $range )
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'values' => array( array_values( $values ) ),
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Append row failed: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->log( sprintf( 'Append row HTTP %d: %s', $code, $body ) );
			return false;
		}

		return true;
	}

	/**
	 * Fetch the first row (headers) from a sheet.
	 *
	 * @param string $spreadsheet_id
	 * @param string $sheet_name
	 *
	 * @return array|null Array of header strings, or null on failure.
	 */
	public function get_headers( string $spreadsheet_id, string $sheet_name = 'Sheet1' ): ?array {
		if ( ! $this->access_token && ! $this->authenticate() ) {
			return null;
		}

		$range = $sheet_name . '!1:1';

		$url = sprintf(
			'%s/%s/values/%s',
			self::SHEETS_API,
			urlencode( $spreadsheet_id ),
			urlencode( $range )
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->access_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Get headers failed: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->log( sprintf( 'Get headers HTTP %d: %s', $code, $body ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['values'][0] ) && is_array( $body['values'][0] ) ) {
			return $body['values'][0];
		}

		return array();
	}

	/**
	 * Fetch sheet tab names from a spreadsheet.
	 *
	 * @param string $spreadsheet_id
	 *
	 * @return array|null Array of sheet names, or null on failure.
	 */
	public function get_sheet_names( string $spreadsheet_id ): ?array {
		if ( ! $this->access_token && ! $this->authenticate() ) {
			return null;
		}

		$url = sprintf(
			'%s/%s?fields=sheets.properties.title',
			self::SHEETS_API,
			urlencode( $spreadsheet_id )
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->access_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Get sheet names failed: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->log( sprintf( 'Get sheet names HTTP %d: %s', $code, $body ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$names = array();

		if ( ! empty( $body['sheets'] ) && is_array( $body['sheets'] ) ) {
			foreach ( $body['sheets'] as $sheet ) {
				if ( ! empty( $sheet['properties']['title'] ) ) {
					$names[] = $sheet['properties']['title'];
				}
			}
		}

		return $names;
	}

	/**
	 * Fetch all values from a specific column (by header name).
	 *
	 * @param string $spreadsheet_id
	 * @param string $sheet_name
	 * @param int    $column_index Zero-based column index.
	 *
	 * @return array|null Array of cell values (strings), or null on failure.
	 */
	public function get_column_values( string $spreadsheet_id, string $sheet_name, int $column_index ): ?array {
		if ( ! $this->access_token && ! $this->authenticate() ) {
			return null;
		}

		$col_letter = $this->column_index_to_letter( $column_index );
		$range      = $sheet_name . '!' . $col_letter . '2:' . $col_letter;

		$url = sprintf(
			'%s/%s/values/%s',
			self::SHEETS_API,
			urlencode( $spreadsheet_id ),
			urlencode( $range )
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->access_token,
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Get column values failed: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			$this->log( sprintf( 'Get column values HTTP %d: %s', $code, $body ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$values = array();

		if ( ! empty( $body['values'] ) && is_array( $body['values'] ) ) {
			foreach ( $body['values'] as $row ) {
				$values[] = $row[0] ?? '';
			}
		}

		return $values;
	}

	/**
	 * Convert a zero-based column index to a letter (0=A, 1=B, ..., 25=Z, 26=AA).
	 */
	private function column_index_to_letter( int $index ): string {
		$letter = '';

		while ( $index >= 0 ) {
			$letter = chr( 65 + ( $index % 26 ) ) . $letter;
			$index  = intdiv( $index, 26 ) - 1;
		}

		return $letter;
	}

	/**
	 * Create a signed JWT for Google Service Account authentication.
	 */
	private function create_jwt( string $client_email, string $private_key ): ?string {
		$now = time();

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$payload = array(
			'iss'   => $client_email,
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$segments = array(
			$this->base64url_encode( wp_json_encode( $header ) ),
			$this->base64url_encode( wp_json_encode( $payload ) ),
		);

		$signing_input = implode( '.', $segments );

		$key = openssl_pkey_get_private( $private_key );

		if ( ! $key ) {
			$this->log( 'Failed to parse private key from service account JSON.' );
			return null;
		}

		$signature = '';
		$signed    = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );

		if ( ! $signed ) {
			$this->log( 'openssl_sign() failed.' );
			return null;
		}

		$segments[] = $this->base64url_encode( $signature );

		return implode( '.', $segments );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private function log( string $message ): void {
		if ( SettingsRepository::debug_enabled() ) {
			error_log( '[GoogleSheet JFB] ' . $message );
		}
	}
}
