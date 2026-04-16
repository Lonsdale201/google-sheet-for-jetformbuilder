<?php

namespace GoogleSheet\JetFormBuilder\Rest;

use GoogleSheet\JetFormBuilder\GoogleSheets\Client;
use GoogleSheet\JetFormBuilder\Settings\SettingsRepository;

class SheetsRoutes {

	private const NAMESPACE = 'gsjfb/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/sheets/headers',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_headers' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'spreadsheet_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sheet_name' => array(
						'required' => false,
						'type'     => 'string',
						'default'  => 'Sheet1',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sheets/names',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_sheet_names' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'spreadsheet_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function check_permissions(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function get_headers( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! SettingsRepository::is_configured() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Google Service Account is not configured.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		$spreadsheet_id = $this->extract_spreadsheet_id( $request->get_param( 'spreadsheet_id' ) );
		$sheet_name     = $request->get_param( 'sheet_name' ) ?: 'Sheet1';

		if ( empty( $spreadsheet_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid spreadsheet ID or URL.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		$client  = new Client();
		$headers = $client->get_headers( $spreadsheet_id, $sheet_name );

		if ( null === $headers ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not read headers. Ensure the spreadsheet is shared with the Service Account email.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'headers' => $headers,
			),
			200
		);
	}

	public function get_sheet_names( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! SettingsRepository::is_configured() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Google Service Account is not configured.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		$spreadsheet_id = $this->extract_spreadsheet_id( $request->get_param( 'spreadsheet_id' ) );

		if ( empty( $spreadsheet_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid spreadsheet ID or URL.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		$client = new Client();
		$names  = $client->get_sheet_names( $spreadsheet_id );

		if ( null === $names ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Could not read spreadsheet. Ensure it is shared with the Service Account email.', 'google-sheet-for-jetformbuilder' ),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'sheets'  => $names,
			),
			200
		);
	}

	private function extract_spreadsheet_id( string $input ): string {
		$input = trim( $input );

		if ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $input, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $input ) ) {
			return $input;
		}

		return '';
	}
}
