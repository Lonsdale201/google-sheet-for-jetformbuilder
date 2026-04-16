( function registerGoogleSheetAction( wp, JetFBActions, actionData, jfb ) {
	if ( ! wp || ! JetFBActions || ! JetFBActions.addAction ) {
		return;
	}

	var addAction = JetFBActions.addAction;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useCallback = wp.element.useCallback;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Tooltip = wp.components.Tooltip;
	var Icon = wp.components.Icon;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	var jetFormBuilder = jfb || {};
	var jfbComponents = jetFormBuilder.components || {};
	var jfbBlocks = jetFormBuilder.blocksToActions || {};

	var StyledSelect = jfbComponents.StyledSelectControl || SelectControl;
	var StyledText = jfbComponents.StyledTextControl || TextControl;
	var useFields = typeof jfbBlocks.useFields === 'function' ? jfbBlocks.useFields : null;

	var selectControlProps = {
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true,
	};

	var textControlProps = {
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true,
	};

	/* ── Extract spreadsheet ID from URL or raw ID ── */
	var extractSpreadsheetId = function ( input ) {
		if ( ! input ) {
			return '';
		}
		var match = input.match( /\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/ );
		if ( match ) {
			return match[ 1 ];
		}
		return input.trim();
	};

	/* ── Sheet name selector with fetch ── */
	var SheetNameControl = function ( props ) {
		var settings = props.settings;
		var onChangeSetting = props.onChangeSetting;
		var _s = useState( [] );
		var sheets = _s[ 0 ];
		var setSheets = _s[ 1 ];
		var _l = useState( false );
		var loading = _l[ 0 ];
		var setLoading = _l[ 1 ];

		var fetchSheets = function () {
			var sid = extractSpreadsheetId( settings.spreadsheet_id );
			if ( ! sid ) {
				return;
			}
			setLoading( true );
			apiFetch( {
				path: '/gsjfb/v1/sheets/names',
				method: 'POST',
				data: { spreadsheet_id: sid },
			} )
				.then( function ( res ) {
					setLoading( false );
					if ( res && res.success && Array.isArray( res.sheets ) ) {
						setSheets( res.sheets );
					}
				} )
				.catch( function () {
					setLoading( false );
				} );
		};

		var hasSelection = !! settings.sheet_name;

		return createElement(
			'div',
			{ className: 'gsjfb-field-row' },
			createElement(
				'div',
				{ className: 'gsjfb-sheet-name-row' },
				createElement(
					'div',
					{ className: 'gsjfb-sheet-name-input' },
					createElement( StyledText, {
						...textControlProps,
						label: __( 'Sheet name (tab)', 'google-sheet-for-jetformbuilder' ),
						value: settings.sheet_name || '',
						onChange: function ( val ) {
							onChangeSetting( val, 'sheet_name' );
						},
					} )
				),
				createElement(
					Button,
					{
						variant: 'secondary',
						isBusy: loading,
						disabled: ! settings.spreadsheet_id || loading,
						onClick: fetchSheets,
						className: 'gsjfb-fetch-btn',
					},
					loading
						? createElement( Spinner, null )
						: __( 'Fetch sheets', 'google-sheet-for-jetformbuilder' )
				)
			),
			sheets.length > 0
				? createElement(
						'div',
						{ className: 'gsjfb-sheet-chips-wrap' },
						createElement(
							'p',
							{ className: 'description gsjfb-chips-hint' },
							__( 'Click a tab name below to select it as the target sheet:', 'google-sheet-for-jetformbuilder' )
						),
						createElement(
							'div',
							{ className: 'gsjfb-sheet-chips' },
							sheets.map( function ( name ) {
								return createElement(
									'button',
									{
										key: name,
										type: 'button',
										className:
											'gsjfb-chip' +
											( settings.sheet_name === name
												? ' gsjfb-chip--active'
												: '' ),
										onClick: function () {
											onChangeSetting( name, 'sheet_name' );
										},
									},
									name
								);
							} )
						)
				  )
				: null,
			! hasSelection && ! loading
				? createElement(
						'p',
						{ className: 'gsjfb-hint-required' },
						__( 'Please type or fetch and select a sheet name. Data will not be sent until a target sheet is set.', 'google-sheet-for-jetformbuilder' )
				  )
				: null
		);
	};

	/* ── Field mapping table ── */
	var FieldMappingControl = function ( props ) {
		var settings = props.settings;
		var onChangeSetting = props.onChangeSetting;

		var fieldOptions = useFields
			? useFields( { withInner: false, placeholder: '--' } )
			: [ { value: '', label: '--' } ];

		var fieldMap = Array.isArray( settings.field_map )
			? settings.field_map
			: [];

		var _h = useState( [] );
		var sheetHeaders = _h[ 0 ];
		var setSheetHeaders = _h[ 1 ];
		var _l = useState( false );
		var loading = _l[ 0 ];
		var setLoading = _l[ 1 ];
		var _e = useState( '' );
		var error = _e[ 0 ];
		var setError = _e[ 1 ];

		var fetchHeaders = function () {
			var sid = extractSpreadsheetId( settings.spreadsheet_id );
			if ( ! sid ) {
				return;
			}
			setLoading( true );
			setError( '' );
			apiFetch( {
				path: '/gsjfb/v1/sheets/headers',
				method: 'POST',
				data: {
					spreadsheet_id: sid,
					sheet_name: settings.sheet_name || 'Sheet1',
				},
			} )
				.then( function ( res ) {
					setLoading( false );
					if ( res && res.success && Array.isArray( res.headers ) ) {
						setSheetHeaders( res.headers );
						// Auto-create mapping entries for fetched headers
						if ( res.headers.length > 0 ) {
							var autoMap = res.headers.map( function ( header ) {
								// Try matching existing mapping
								var existing = fieldMap.find( function ( m ) {
									return m.column_header === header;
								} );
								if ( existing ) {
									return existing;
								}
								// Try auto-matching by field name
								var matched = fieldOptions.find( function ( f ) {
									return (
										f.value &&
										f.value.toLowerCase().replace( /[_-]/g, '' ) ===
											header.toLowerCase().replace( /[_\-\s]/g, '' )
									);
								} );
								return {
									column_header: header,
									form_field: matched ? matched.value : '',
								};
							} );
							onChangeSetting( autoMap, 'field_map' );
						}
					} else {
						setError(
							( res && res.message ) ||
								__(
									'Could not fetch headers.',
									'google-sheet-for-jetformbuilder'
								)
						);
					}
				} )
				.catch( function ( err ) {
					setLoading( false );
					setError(
						( err && err.message ) ||
							__(
								'Request failed.',
								'google-sheet-for-jetformbuilder'
							)
					);
				} );
		};

		var updateMapping = function ( index, key, value ) {
			var updated = fieldMap.map( function ( item, i ) {
				if ( i !== index ) {
					return item;
				}
				var copy = {};
				copy.column_header = item.column_header;
				copy.form_field = item.form_field;
				copy[ key ] = value;
				return copy;
			} );
			onChangeSetting( updated, 'field_map' );
		};

		var addRow = function () {
			onChangeSetting(
				fieldMap.concat( [ { column_header: '', form_field: '' } ] ),
				'field_map'
			);
		};

		var removeRow = function ( index ) {
			onChangeSetting(
				fieldMap.filter( function ( _, i ) {
					return i !== index;
				} ),
				'field_map'
			);
		};

		return createElement(
			'div',
			{ className: 'gsjfb-field-mapping' },
			createElement(
				'div',
				{ className: 'gsjfb-mapping-header' },
				createElement(
					'div',
					{ className: 'gsjfb-label-with-tooltip' },
					createElement(
						'strong',
						{ className: 'gsjfb-label' },
						__( 'Field mapping', 'google-sheet-for-jetformbuilder' )
					),
					createElement(
						Tooltip,
						{
							text: __( 'Map form fields to spreadsheet column headers. Click "Fetch column headers" to auto-populate from the first row.', 'google-sheet-for-jetformbuilder' ),
							position: 'top center',
						},
						createElement( 'span', { className: 'gsjfb-tooltip-icon dashicons dashicons-info-outline' } )
					)
				),
				createElement(
					Button,
					{
						variant: 'secondary',
						isBusy: loading,
						disabled: ! settings.spreadsheet_id || loading,
						onClick: fetchHeaders,
						className: 'gsjfb-fetch-btn',
					},
					loading
						? createElement( Spinner, null )
						: __(
								'Fetch column headers',
								'google-sheet-for-jetformbuilder'
						  )
				)
			),
			error
				? createElement( 'p', { className: 'gsjfb-error' }, error )
				: null,
			fieldMap.length > 0
				? createElement(
						'div',
						{ className: 'gsjfb-mapping-card' },
						createElement(
							'div',
							{ className: 'gsjfb-mapping-th' },
							createElement(
								'div',
								{ className: 'gsjfb-mapping-th-item' },
								__(
									'Sheet column',
									'google-sheet-for-jetformbuilder'
								)
							),
							createElement(
								'div',
								{ className: 'gsjfb-mapping-th-item' },
								__(
									'Form field',
									'google-sheet-for-jetformbuilder'
								)
							),
							createElement( 'div', {
								className: 'gsjfb-mapping-col-actions',
							} )
						),
						fieldMap.map( function ( mapping, index ) {
							return createElement(
								'div',
								{
									key: index,
									className: 'gsjfb-mapping-td',
								},
								createElement(
									'div',
									{ className: 'gsjfb-mapping-td-item' },
									createElement( 'input', {
										type: 'text',
										className:
											'components-text-control__input gsjfb-mapping-input',
										value: mapping.column_header || '',
										placeholder: __(
											'Column header',
											'google-sheet-for-jetformbuilder'
										),
										onChange: function ( e ) {
											updateMapping(
												index,
												'column_header',
												e.target.value
											);
										},
									} )
								),
								createElement(
									'div',
									{ className: 'gsjfb-mapping-td-item' },
									createElement(
										'select',
										{
											className:
												'components-select-control__input gsjfb-mapping-input',
											value: mapping.form_field || '',
											onChange: function ( e ) {
												updateMapping(
													index,
													'form_field',
													e.target.value
												);
											},
										},
										createElement(
											'option',
											{ value: '' },
											'— ' +
												__(
													'Select field',
													'google-sheet-for-jetformbuilder'
												) +
												' —'
										),
										fieldOptions.map( function ( field ) {
											return createElement(
												'option',
												{
													key: field.value,
													value: field.value,
												},
												field.label
											);
										} )
									)
								),
								createElement(
									'div',
									{ className: 'gsjfb-mapping-col-actions' },
									createElement(
										Button,
										{
											isDestructive: true,
											variant: 'tertiary',
											onClick: function () {
												removeRow( index );
											},
											className: 'gsjfb-remove-btn',
										},
										'\u2715'
									)
								)
							);
						} )
				  )
				: null,
			createElement(
				'div',
				{ className: 'gsjfb-mapping-actions' },
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: addRow,
					},
					__( '+ Add row', 'google-sheet-for-jetformbuilder' )
				)
			)
		);
	};

	/* ── Duplicate check control ── */
	var DuplicateCheckControl = function ( props ) {
		var settings = props.settings;
		var onChangeSetting = props.onChangeSetting;

		var fieldOptions = useFields
			? useFields( { withInner: false, placeholder: '--' } )
			: [ { value: '', label: '--' } ];

		var selectOptions = [
			{ value: '', label: '— ' + __( 'Disabled', 'google-sheet-for-jetformbuilder' ) + ' —' },
		].concat(
			fieldOptions
				.filter( function ( f ) { return f.value; } )
				.map( function ( f ) {
					return { value: f.value, label: f.label };
				} )
		);

		return createElement(
			'div',
			{ className: 'gsjfb-duplicate-section' },
			createElement(
				'div',
				{ className: 'gsjfb-label-with-tooltip' },
				createElement(
					'strong',
					{ className: 'gsjfb-label' },
					__( 'Duplicate check', 'google-sheet-for-jetformbuilder' )
				),
				createElement(
					Tooltip,
					{
						text: __( 'Select a form field to check for duplicates before appending. If the submitted value already exists in the mapped sheet column, the row will be skipped (no error).', 'google-sheet-for-jetformbuilder' ),
						position: 'top center',
					},
					createElement( 'span', { className: 'gsjfb-tooltip-icon dashicons dashicons-info-outline' } )
				)
			),
			createElement( StyledSelect, {
				...selectControlProps,
				label: __( 'Check field for duplicates', 'google-sheet-for-jetformbuilder' ),
				value: settings.duplicate_check_field || '',
				options: selectOptions,
				onChange: function ( val ) {
					onChangeSetting( val, 'duplicate_check_field' );
				},
			} ),
			settings.duplicate_check_field
				? createElement( StyledText, {
						...textControlProps,
						label: __( 'Skip message (optional)', 'google-sheet-for-jetformbuilder' ),
						value: settings.duplicate_skip_message || '',
						onChange: function ( val ) {
							onChangeSetting( val, 'duplicate_skip_message' );
						},
						placeholder: __( 'e.g. This entry has already been submitted.', 'google-sheet-for-jetformbuilder' ),
				  } )
				: null
		);
	};

	/* ── Main action editor component ── */
	addAction(
		'google_sheet',
		function GoogleSheetEdit( props ) {
			var settings = props.settings || {};
			var label = props.label;
			var onChangeSetting = props.onChangeSetting;

			var isConfigured = actionData && actionData.isConfigured;

			return createElement(
				'div',
				{ className: 'gsjfb-action-editor' },
				! isConfigured
					? createElement(
							'div',
							{ className: 'gsjfb-notice gsjfb-notice--warning' },
							__(
								'Google Service Account is not configured. Go to JetFormBuilder \u2192 Settings \u2192 Google Sheet tab to set up credentials.',
								'google-sheet-for-jetformbuilder'
							)
					  )
					: null,
				createElement(
					'div',
					{ className: 'gsjfb-spreadsheet-row' },
					createElement( StyledText, {
						...textControlProps,
						label: createElement(
							Fragment,
							null,
							__( 'Spreadsheet ID or URL', 'google-sheet-for-jetformbuilder' ),
							createElement(
								Tooltip,
								{
									text: __( 'Paste the full Google Sheets URL or just the spreadsheet ID. The sheet must be shared with the Service Account email (Editor role).', 'google-sheet-for-jetformbuilder' ),
									position: 'top center',
								},
								createElement( 'span', { className: 'gsjfb-tooltip-icon dashicons dashicons-info-outline' } )
							)
						),
						value: settings.spreadsheet_id || '',
						onChange: function ( val ) {
							onChangeSetting( val, 'spreadsheet_id' );
						},
					} )
				),
				createElement( SheetNameControl, {
					settings: settings,
					onChangeSetting: onChangeSetting,
				} ),
				createElement( 'hr', {
					className: 'jet-form-builder-separator',
					'aria-hidden': true,
				} ),
				createElement( FieldMappingControl, {
					settings: settings,
					onChangeSetting: onChangeSetting,
				} ),
				createElement( 'hr', {
					className: 'jet-form-builder-separator',
					'aria-hidden': true,
				} ),
				createElement( DuplicateCheckControl, {
					settings: settings,
					onChangeSetting: onChangeSetting,
				} ),
				createElement( 'hr', {
					className: 'jet-form-builder-separator',
					'aria-hidden': true,
				} ),
				createElement( StyledText, {
					...textControlProps,
					label: createElement(
						Fragment,
						null,
						__( 'Custom success message', 'google-sheet-for-jetformbuilder' ),
						createElement(
							Tooltip,
							{
								text: __( 'Optional. If set, this message will be shown to the user after a successful submission instead of the default JetFormBuilder message.', 'google-sheet-for-jetformbuilder' ),
								position: 'top center',
							},
							createElement( 'span', { className: 'gsjfb-tooltip-icon dashicons dashicons-info-outline' } )
						)
					),
					value: settings.success_message || '',
					onChange: function ( val ) {
						onChangeSetting( val, 'success_message' );
					},
					placeholder: __( 'Leave empty to use the default form message', 'google-sheet-for-jetformbuilder' ),
				} )
			);
		},
		{
			category: 'advanced',
		}
	);

	/* Register in Redux store so action appears in the list */
	if ( wp.data && wp.data.dispatch ) {
		try {
			wp.data.dispatch( 'jet-forms/actions' ).registerAction( {
				type: 'google_sheet',
				label: __( 'Google Sheet', 'google-sheet-for-jetformbuilder' ),
				category: 'advanced',
			} );
		} catch ( err ) {
			// Store may not be ready yet.
		}
	}
}(
	window.wp || false,
	window.JetFBActions || false,
	window.JetFormGoogleSheet || {},
	window.jfb || {}
) );
