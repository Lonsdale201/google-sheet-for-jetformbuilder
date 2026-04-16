<?php

namespace GoogleSheet\JetFormBuilder\Action;

use Jet_Form_Builder\Actions\Action_Handler;
use Jet_Form_Builder\Actions\Types\Base;
use Jet_Form_Builder\Exceptions\Action_Exception;
use GoogleSheet\JetFormBuilder\GoogleSheets\Client;
use GoogleSheet\JetFormBuilder\Settings\SettingsRepository;

class GoogleSheetAction extends Base {

	public function get_name() {
		return __( 'Google Sheet', 'google-sheet-for-jetformbuilder' );
	}

	public function get_id() {
		return 'google_sheet';
	}

	public function self_script_name() {
		return 'JetFormGoogleSheet';
	}

	public function action_attributes() {
		return array(
			'spreadsheet_id' => array(
				'default' => '',
			),
			'sheet_name' => array(
				'default' => 'Sheet1',
			),
			'field_map' => array(
				'default' => array(),
			),
			'success_message' => array(
				'default' => '',
			),
			'duplicate_check_field' => array(
				'default' => '',
			),
			'duplicate_skip_message' => array(
				'default' => '',
			),
		);
	}

	public function editor_labels() {
		return array(
			'spreadsheet_id' => __( 'Spreadsheet ID or URL', 'google-sheet-for-jetformbuilder' ),
			'sheet_name'     => __( 'Sheet name', 'google-sheet-for-jetformbuilder' ),
			'field_map'      => __( 'Field mapping', 'google-sheet-for-jetformbuilder' ),
		);
	}

	public function editor_labels_help() {
		return array(
			'spreadsheet_id' => __( 'Paste the full Google Sheets URL or just the spreadsheet ID. The sheet must be shared with the Service Account email.', 'google-sheet-for-jetformbuilder' ),
			'sheet_name'     => __( 'The tab/sheet name where data will be appended. Default: Sheet1', 'google-sheet-for-jetformbuilder' ),
			'field_map'      => __( 'Map form fields to sheet column headers. The first row of the sheet must contain column names.', 'google-sheet-for-jetformbuilder' ),
		);
	}

	public function action_data() {
		return array(
			'isConfigured' => SettingsRepository::is_configured(),
		);
	}

	/**
	 * @throws Action_Exception
	 */
	public function do_action( array $request, Action_Handler $handler ) {
		if ( ! SettingsRepository::is_configured() ) {
			throw new Action_Exception(
				'failed',
				esc_html__( 'Google Sheet action: Service Account credentials are not configured.', 'google-sheet-for-jetformbuilder' )
			);
		}

		$spreadsheet_id = $this->extract_spreadsheet_id( $this->settings['spreadsheet_id'] ?? '' );

		if ( empty( $spreadsheet_id ) ) {
			throw new Action_Exception(
				'failed',
				esc_html__( 'Google Sheet action: Spreadsheet ID is missing.', 'google-sheet-for-jetformbuilder' )
			);
		}

		$sheet_name = ! empty( $this->settings['sheet_name'] )
			? sanitize_text_field( $this->settings['sheet_name'] )
			: 'Sheet1';

		$field_map = $this->settings['field_map'] ?? array();

		if ( empty( $field_map ) || ! is_array( $field_map ) ) {
			throw new Action_Exception(
				'failed',
				esc_html__( 'Google Sheet action: No field mapping configured.', 'google-sheet-for-jetformbuilder' )
			);
		}

		$client = new Client();

		$headers = $client->get_headers( $spreadsheet_id, $sheet_name );

		if ( null === $headers ) {
			throw new Action_Exception(
				'failed',
				esc_html__( 'Google Sheet action: Could not read sheet headers. Verify the spreadsheet is shared with the Service Account.', 'google-sheet-for-jetformbuilder' )
			);
		}

		// Duplicate check.
		$duplicate_field = $this->settings['duplicate_check_field'] ?? '';

		if ( ! empty( $duplicate_field ) ) {
			$duplicate_column = $this->resolve_column_header( $duplicate_field, $field_map );

			if ( $duplicate_column ) {
				$column_index = array_search( $duplicate_column, $headers, true );

				if ( false !== $column_index ) {
					$existing_values = $client->get_column_values( $spreadsheet_id, $sheet_name, $column_index );
					$submitted_value = (string) ( $request[ $duplicate_field ] ?? '' );

					if ( null !== $existing_values && '' !== $submitted_value && in_array( $submitted_value, $existing_values, true ) ) {
						// Duplicate found — skip silently, store optional message.
						$skip_message = trim( (string) ( $this->settings['duplicate_skip_message'] ?? '' ) );

						if ( '' !== $skip_message ) {
							$handler->add_context_once(
								$this->get_id(),
								array( 'gsjfb_success_message' => $skip_message )
							);
						}

						return;
					}
				}
			}
		}

		$row = $this->build_row( $request, $field_map, $headers );

		$success = $client->append_row( $spreadsheet_id, $sheet_name, $row );

		if ( ! $success ) {
			throw new Action_Exception(
				'failed',
				esc_html__( 'Google Sheet action: Failed to append row to spreadsheet.', 'google-sheet-for-jetformbuilder' )
			);
		}

		// Custom success message.
		$success_message = trim( (string) ( $this->settings['success_message'] ?? '' ) );

		if ( '' !== $success_message ) {
			$handler->add_context_once(
				$this->get_id(),
				array( 'gsjfb_success_message' => $success_message )
			);
		}
	}

	/**
	 * Resolve a form field name to its mapped column header.
	 */
	private function resolve_column_header( string $form_field, array $field_map ): ?string {
		foreach ( $field_map as $mapping ) {
			if ( ( $mapping['form_field'] ?? '' ) === $form_field && ! empty( $mapping['column_header'] ) ) {
				return $mapping['column_header'];
			}
		}

		return null;
	}

	/**
	 * Extract the spreadsheet ID from a URL or return as-is if already an ID.
	 */
	private function extract_spreadsheet_id( string $input ): string {
		$input = trim( $input );

		if ( empty( $input ) ) {
			return '';
		}

		if ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $input, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $input ) ) {
			return $input;
		}

		return '';
	}

	/**
	 * Build a row array ordered by sheet headers, using the field_map.
	 *
	 * @param array $request   Form submission data.
	 * @param array $field_map Array of [ form_field => column_header ] mappings.
	 * @param array $headers   The first row of the sheet (column headers).
	 *
	 * @return array Ordered cell values matching the header positions.
	 */
	private function build_row( array $request, array $field_map, array $headers ): array {
		$header_to_value = array();

		foreach ( $field_map as $mapping ) {
			$form_field    = $mapping['form_field'] ?? '';
			$column_header = $mapping['column_header'] ?? '';

			if ( empty( $form_field ) || empty( $column_header ) ) {
				continue;
			}

			$value = $request[ $form_field ] ?? '';

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$value = (string) $value;

			// Strip HTML from WYSIWYG / rich text fields, keep plain text.
			if ( $value !== strip_tags( $value ) ) {
				$value = wp_strip_all_tags( $value );
				$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
				$value = trim( $value );
			}

			$header_to_value[ $column_header ] = $value;
		}

		$row = array();

		foreach ( $headers as $header ) {
			$row[] = $header_to_value[ $header ] ?? '';
		}

		return $row;
	}
}
