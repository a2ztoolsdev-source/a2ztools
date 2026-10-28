/**
 * A2Z Widget block - editor script.
 *
 * Plain ES5 on the WordPress globals (wp.blocks, wp.element, wp.components,
 * wp.blockEditor, wp.i18n, wp.serverSideRender): no npm, no build step.
 * The widget list arrives in window.a2zWidgetsData (see
 * A2Z_Widgets_Block::editor_data). The front end is rendered by PHP, so
 * save() returns null and the preview is the real server output.
 */
( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var c = wp.components;
	var be = wp.blockEditor || wp.editor;
	var InspectorControls = be.InspectorControls;
	var useBlockProps = be.useBlockProps;
	var ServerSideRender = wp.serverSideRender;

	data = data || {};
	var widgets = Array.isArray( data.widgets ) ? data.widgets : [];
	var categories = data.categories || {};
	var currencies = Array.isArray( data.currencies ) ? data.currencies : [];

	var SLUG_RE = /^[a-z0-9-]{2,80}$/;
	var HEX_RE = /^#?[0-9a-fA-F]{6}$/;
	var WIDTH_RE = /^\d{2,4}(px|%)?$/;

	function findWidget( slug ) {
		for ( var i = 0; i < widgets.length; i++ ) {
			if ( widgets[ i ].slug === slug ) {
				return widgets[ i ];
			}
		}
		return null;
	}

	function widgetOptions() {
		return widgets.map( function ( w ) {
			var cat = categories[ w.category ] || w.category || '';
			return { value: w.slug, label: w.name + ( cat ? ' - ' + cat : '' ) };
		} );
	}

	function picker( attributes, setAttributes ) {
		var onChange = function ( slug ) {
			slug = slug || '';
			if ( slug && ! SLUG_RE.test( slug ) ) {
				return;
			}
			// A new widget has different fields: drop old presets and height.
			setAttributes( { slug: slug, presets: {}, height: undefined } );
		};
		if ( c.ComboboxControl ) {
			return el( c.ComboboxControl, {
				label: __( 'Widget', 'a2z-widgets' ),
				value: attributes.slug,
				options: widgetOptions(),
				onChange: onChange,
				help: 'fallback' === data.source ?
					__( 'The A2Z widget list could not be loaded, so only popular widgets are listed.', 'a2z-widgets' ) :
					/* translators: %d: number of widgets */
					sprintf( __( '%d widgets available. Type to search.', 'a2z-widgets' ), widgets.length )
			} );
		}
		return el( c.SelectControl, {
			label: __( 'Widget', 'a2z-widgets' ),
			value: attributes.slug,
			options: [ { value: '', label: __( 'Choose a widget', 'a2z-widgets' ) } ].concat( widgetOptions() ),
			onChange: onChange
		} );
	}

	function presetFields( widget, attributes, setAttributes ) {
		if ( ! widget || ! widget.inputs || ! widget.inputs.length ) {
			return null;
		}
		var presets = attributes.presets || {};
		return el(
			c.PanelBody,
			{ title: __( 'Starting values', 'a2z-widgets' ), initialOpen: false },
			el( 'p', { className: 'description' }, __( 'Optional. Pre-fill the widget fields; visitors can still change them.', 'a2z-widgets' ) ),
			widget.inputs.map( function ( input ) {
				return el( c.TextControl, {
					key: input.id,
					label: input.label,
					value: presets[ input.id ] || '',
					placeholder: input.example || '',
					onChange: function ( value ) {
						var next = {};
						Object.keys( presets ).forEach( function ( k ) { next[ k ] = presets[ k ]; } );
						if ( value ) {
							next[ input.id ] = String( value ).slice( 0, 4000 );
						} else {
							delete next[ input.id ];
						}
						setAttributes( { presets: next } );
					}
				} );
			} )
		);
	}

	function settingsPanel( widget, attributes, setAttributes ) {
		var height = attributes.height || ( widget ? widget.height : 560 );
		var controls = [
			el( c.SelectControl, {
				key: 'theme',
				label: __( 'Theme', 'a2z-widgets' ),
				value: attributes.theme,
				options: [
					{ value: '', label: __( 'Site default', 'a2z-widgets' ) },
					{ value: 'auto', label: __( 'Auto (follows the visitor)', 'a2z-widgets' ) },
					{ value: 'light', label: __( 'Light', 'a2z-widgets' ) },
					{ value: 'dark', label: __( 'Dark', 'a2z-widgets' ) }
				],
				onChange: function ( v ) { setAttributes( { theme: v } ); }
			} )
		];

		if ( ! widget || widget.supportsCurrency ) {
			controls.push( el( c.SelectControl, {
				key: 'currency',
				label: __( 'Currency', 'a2z-widgets' ),
				value: attributes.currency,
				options: [ { value: '', label: widget && widget.defaultCurrency ?
					/* translators: %s: ISO currency code */
					sprintf( __( 'Widget default (%s)', 'a2z-widgets' ), widget.defaultCurrency ) :
					__( 'Widget default', 'a2z-widgets' ) } ].concat( currencies.map( function ( code ) {
					return { value: code, label: code };
				} ) ),
				onChange: function ( v ) { setAttributes( { currency: v } ); }
			} ) );
		}

		if ( ! widget || widget.supportsUnits ) {
			controls.push( el( c.SelectControl, {
				key: 'units',
				label: __( 'Units', 'a2z-widgets' ),
				value: attributes.units,
				options: [
					{ value: '', label: __( 'Widget default', 'a2z-widgets' ) },
					{ value: 'metric', label: __( 'Metric', 'a2z-widgets' ) },
					{ value: 'imperial', label: __( 'Imperial', 'a2z-widgets' ) }
				],
				onChange: function ( v ) { setAttributes( { units: v } ); }
			} ) );
		}

		controls.push( el( c.RangeControl, {
			key: 'height',
			label: __( 'Height (px)', 'a2z-widgets' ),
			value: height,
			min: 80,
			max: 2000,
			step: 10,
			allowReset: true,
			onChange: function ( v ) { setAttributes( { height: v ? Math.max( 80, Math.min( 5000, parseInt( v, 10 ) ) ) : undefined } ); },
			help: __( 'Turn on "auto height" in Settings > A2Z Widgets to let the widget resize itself.', 'a2z-widgets' )
		} ) );

		controls.push( el( c.TextControl, {
			key: 'width',
			label: __( 'Maximum width', 'a2z-widgets' ),
			value: attributes.width,
			placeholder: '480px',
			help: __( 'Leave empty for full width. Examples: 480px, 80%.', 'a2z-widgets' ),
			onChange: function ( v ) {
				v = ( v || '' ).trim();
				if ( '' === v || WIDTH_RE.test( v ) || /^\d{0,4}(p|px|%)?$/.test( v ) ) {
					setAttributes( { width: v } );
				}
			}
		} ) );

		controls.push( el( c.ToggleControl, {
			key: 'compact',
			label: __( 'Compact layout', 'a2z-widgets' ),
			checked: !! attributes.compact,
			onChange: function ( v ) { setAttributes( { compact: v } ); }
		} ) );
		controls.push( el( c.ToggleControl, {
			key: 'showTitle',
			label: __( 'Show widget title', 'a2z-widgets' ),
			checked: attributes.showTitle !== false,
			onChange: function ( v ) { setAttributes( { showTitle: v } ); }
		} ) );
		controls.push( el( c.ToggleControl, {
			key: 'border',
			label: __( 'Show border', 'a2z-widgets' ),
			checked: attributes.border !== false,
			onChange: function ( v ) { setAttributes( { border: v } ); }
		} ) );

		controls.push( el( c.RangeControl, {
			key: 'radius',
			label: __( 'Corner radius (px)', 'a2z-widgets' ),
			value: typeof attributes.radius === 'number' ? attributes.radius : undefined,
			min: 0,
			max: 24,
			allowReset: true,
			onChange: function ( v ) { setAttributes( { radius: typeof v === 'number' ? v : undefined } ); }
		} ) );

		controls.push( el( c.TextControl, {
			key: 'accent',
			label: __( 'Accent colour (hex)', 'a2z-widgets' ),
			value: attributes.accent,
			placeholder: '#2563eb',
			help: '' === attributes.accent || HEX_RE.test( attributes.accent || '' ) ? '' : __( 'Use six hex digits, e.g. #2563eb. Invalid values are ignored.', 'a2z-widgets' ),
			onChange: function ( v ) { setAttributes( { accent: ( v || '' ).slice( 0, 7 ) } ); }
		} ) );

		return el( c.PanelBody, { title: __( 'Widget settings', 'a2z-widgets' ), initialOpen: true }, controls );
	}

	function preview( attributes ) {
		if ( ! attributes.slug ) {
			return el( c.Placeholder, {
				icon: 'calculator',
				label: __( 'A2Z Widget', 'a2z-widgets' ),
				instructions: __( 'Choose a widget in the block settings sidebar.', 'a2z-widgets' )
			} );
		}
		if ( ! ServerSideRender ) {
			/* translators: %s: widget slug */
			return el( 'p', null, sprintf( __( 'A2Z widget: %s', 'a2z-widgets' ), attributes.slug ) );
		}
		var ssr = el( ServerSideRender, { block: 'a2z-widgets/widget', attributes: attributes } );
		// Disabled keeps clicks selecting the block instead of the iframe.
		return c.Disabled ? el( c.Disabled, null, ssr ) : ssr;
	}

	wp.blocks.registerBlockType( 'a2z-widgets/widget', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var widget = findWidget( attributes.slug );
			var blockProps = useBlockProps ? useBlockProps() : {};

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el( c.PanelBody, { title: __( 'Widget', 'a2z-widgets' ), initialOpen: true }, picker( attributes, setAttributes ) ),
					settingsPanel( widget, attributes, setAttributes ),
					presetFields( widget, attributes, setAttributes )
				),
				el( 'div', blockProps, preview( attributes ) )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp, window.a2zWidgetsData ) );
