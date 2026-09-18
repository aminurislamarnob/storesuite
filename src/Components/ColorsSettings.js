import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	Card,
	CardBody,
	ColorIndicator,
	ColorPicker,
	Dropdown,
	Spinner,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';
import ColorPreview from './ColorPreview';

const PREDEFINED_PALETTES = [
	{
		value: 'default',
		label: __( 'StoreSuite Default', 'storesuite' ),
		colorOptions: [ '#ffffff', '#2d5bdb', '#213fd4', '#e0e7ff' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#2d5bdb',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#213fd4',
			textColor: '#475569',
			titleTextColor: '#334155',
			liteTextColor: '#828282',
			iconColor: '#94a3b8',
			sidebarMenuText: '#334155',
			sidebarBackground: '#ffffff',
			sidebarActiveText: '#213fd4',
			sidebarActiveBackground: '#213fd4',
			sidebarBorderColor: '#e2e8f0',
			borderColor: '#e2e8f0',
			liteBgColor: '#f7f7f7',
		},
	},
	{
		value: 'purple',
		label: __( 'Purple', 'storesuite' ),
		colorOptions: [ '#1e1b4b', '#5539FD', '#635BFF', '#ede9fe' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#5539FD',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#635BFF',
			textColor: '#475569',
			titleTextColor: '#1e1b4b',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#ffffff',
			sidebarBackground: '#1e1b4b',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#5539FD',
			sidebarBorderColor: '#2d2a6e',
			borderColor: '#e2e8f0',
			liteBgColor: '#f5f3ff',
		},
	},
	{
		value: 'ocean',
		label: __( 'Ocean', 'storesuite' ),
		colorOptions: [ '#0c4a6e', '#0ea5e9', '#38bdf8', '#e0f2fe' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#0ea5e9',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#0284c7',
			textColor: '#475569',
			titleTextColor: '#0c4a6e',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#ffffff',
			sidebarBackground: '#0c4a6e',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#0ea5e9',
			sidebarBorderColor: '#174e70',
			borderColor: '#e0f2fe',
			liteBgColor: '#f0f9ff',
		},
	},
	{
		value: 'crimson',
		label: __( 'Crimson', 'storesuite' ),
		colorOptions: [ '#1c0a0a', '#dc2626', '#ef4444', '#fee2e2' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#dc2626',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#b91c1c',
			textColor: '#475569',
			titleTextColor: '#1c0a0a',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#ffffff',
			sidebarBackground: '#1c0a0a',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#dc2626',
			sidebarBorderColor: '#3d1515',
			borderColor: '#fee2e2',
			liteBgColor: '#fff5f5',
		},
	},
	{
		value: 'forest',
		label: __( 'Forest', 'storesuite' ),
		colorOptions: [ '#14532d', '#16a34a', '#22c55e', '#dcfce7' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#16a34a',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#15803d',
			textColor: '#475569',
			titleTextColor: '#14532d',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#ffffff',
			sidebarBackground: '#14532d',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#16a34a',
			sidebarBorderColor: '#1d6b3a',
			borderColor: '#dcfce7',
			liteBgColor: '#f0fdf4',
		},
	},
	{
		value: 'midnight',
		label: __( 'Midnight', 'storesuite' ),
		colorOptions: [ '#0f172a', '#6366f1', '#818cf8', '#e0e7ff' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#6366f1',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#4f46e5',
			textColor: '#475569',
			titleTextColor: '#0f172a',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#ffffff',
			sidebarBackground: '#0f172a',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#6366f1',
			sidebarBorderColor: '#1a2540',
			borderColor: '#e0e7ff',
			liteBgColor: '#eef2ff',
		},
	},
	{
		value: 'graphite',
		label: __( 'Graphite', 'storesuite' ),
		colorOptions: [ '#10131a', '#2f6bff', '#1f5fe6', '#e6efff' ],
		colors: {
			buttonText: '#ffffff',
			buttonBackground: '#2f6bff',
			buttonHoverText: '#ffffff',
			buttonHoverBackground: '#1f5fe6',
			textColor: '#475569',
			titleTextColor: '#10131a',
			liteTextColor: '#828282',
			iconColor: '#ffffff',
			sidebarMenuText: '#cbd5e1',
			sidebarBackground: '#10131a',
			sidebarActiveText: '#ffffff',
			sidebarActiveBackground: '#2f6bff',
			sidebarBorderColor: '#20242e',
			borderColor: '#e2e8f0',
			liteBgColor: '#f6f8fb',
		},
	},
];

// Defaults match :root CSS variables in Main::add_storesuite_css_variables()
const COLOR_FIELDS = [
	{
		key: 'buttonText',
		apiKey: 'storesuite_color_button_text',
		label: __( 'Button Text', 'storesuite' ),
		defaultValue: '#ffffff',
	},
	{
		key: 'buttonBackground',
		apiKey: 'storesuite_color_button_background',
		label: __( 'Button Background', 'storesuite' ),
		defaultValue: '#2d5bdb',
	},
	{
		key: 'buttonHoverText',
		apiKey: 'storesuite_color_button_hover_text',
		label: __( 'Button Hover Text', 'storesuite' ),
		defaultValue: '#ffffff',
	},
	{
		key: 'buttonHoverBackground',
		apiKey: 'storesuite_color_button_hover_background',
		label: __( 'Button Hover Background', 'storesuite' ),
		defaultValue: '#213fd4',
	},
	{
		key: 'textColor',
		apiKey: 'storesuite_text_color',
		label: __( 'Normal Text Color', 'storesuite' ),
		defaultValue: '#475569',
	},
	{
		key: 'titleTextColor',
		apiKey: 'storesuite_title_text_color',
		label: __( 'Title Text Color', 'storesuite' ),
		defaultValue: '#334155',
	},
	{
		key: 'liteTextColor',
		apiKey: 'storesuite_lite_text_color',
		label: __( 'Lite Text Color', 'storesuite' ),
		defaultValue: '#828282',
	},
	{
		key: 'iconColor',
		apiKey: 'storesuite_icon_color',
		label: __( 'Icon Color', 'storesuite' ),
		defaultValue: '#94a3b8',
	},
	{
		key: 'sidebarMenuText',
		apiKey: 'storesuite_color_sidebar_menu_text',
		label: __( 'Dashboard Sidebar Menu Text', 'storesuite' ),
		defaultValue: '#334155',
	},
	{
		key: 'sidebarBackground',
		apiKey: 'storesuite_color_sidebar_background',
		label: __( 'Dashboard Sidebar Background', 'storesuite' ),
		defaultValue: '#ffffff',
	},
	{
		key: 'sidebarActiveText',
		apiKey: 'storesuite_color_sidebar_active_text',
		label: __( 'Dashboard Sidebar Active/Hover Menu Text', 'storesuite' ),
		defaultValue: '#213fd4',
	},
	{
		key: 'sidebarActiveBackground',
		apiKey: 'storesuite_color_sidebar_active_background',
		label: __( 'Dashboard Sidebar Active Menu Background', 'storesuite' ),
		defaultValue: '#213fd4',
	},
	{
		key: 'sidebarBorderColor',
		apiKey: 'storesuite_color_sidebar_border',
		label: __( 'Sidebar Border Color', 'storesuite' ),
		defaultValue: '#e2e8f0',
	},
	{
		key: 'borderColor',
		apiKey: 'storesuite_color_border',
		label: __( 'Border Color', 'storesuite' ),
		defaultValue: '#e2e8f0',
	},
	{
		key: 'liteBgColor',
		apiKey: 'storesuite_color_lite_bg',
		label: __( 'Lite Background Color', 'storesuite' ),
		defaultValue: '#f7f7f7',
	},
];

// Dark mode is a choice of neutrals only — surfaces, text and borders. The accent
// (buttons, active menu) always comes from the selected light palette, so a store
// keeps its brand color in both modes. "default" matches the
// :root[data-theme="dark"] block in assets/frontend/style.css.
const DARK_THEMES = [
	{
		value: 'default',
		colorOptions: [ '#0f172a', '#1e293b', '#334155', '#cbd5e1' ],
		label: __( 'Dark default', 'storesuite' ),
		description: __(
			'The standard dark theme, with full contrast on a slate background.',
			'storesuite'
		),
		colors: {
			textColor: '#cbd5e1',
			titleTextColor: '#f1f5f9',
			liteTextColor: '#94a3b8',
			iconColor: '#94a3b8',
			sidebarMenuText: '#cbd5e1',
			sidebarBackground: '#1e293b',
			sidebarActiveText: '#f8fafc',
			sidebarBorderColor: '#334155',
			borderColor: '#334155',
			liteBgColor: '#1e293b',
			pageBg: '#0f172a',
			surfaceBg: '#1e293b',
		},
	},
	{
		value: 'soft',
		colorOptions: [ '#1c2128', '#22272e', '#373e47', '#adbac7' ],
		label: __( 'Soft dark', 'storesuite' ),
		description: __(
			'A dark theme with reduced contrast for comfortable viewing in low-light environments.',
			'storesuite'
		),
		colors: {
			textColor: '#adbac7',
			titleTextColor: '#cdd9e5',
			liteTextColor: '#768390',
			iconColor: '#768390',
			sidebarMenuText: '#adbac7',
			sidebarBackground: '#22272e',
			sidebarActiveText: '#cdd9e5',
			sidebarBorderColor: '#373e47',
			borderColor: '#373e47',
			liteBgColor: '#2d333b',
			pageBg: '#1c2128',
			surfaceBg: '#22272e',
		},
	},
	{
		value: 'midnight',
		colorOptions: [ '#010409', '#0d1117', '#21262d', '#c9d1d9' ],
		label: __( 'Midnight black', 'storesuite' ),
		description: __(
			'A near-black theme that saves power on OLED screens.',
			'storesuite'
		),
		colors: {
			textColor: '#c9d1d9',
			titleTextColor: '#f0f6fc',
			liteTextColor: '#8b949e',
			iconColor: '#8b949e',
			sidebarMenuText: '#c9d1d9',
			sidebarBackground: '#0d1117',
			sidebarActiveText: '#ffffff',
			sidebarBorderColor: '#21262d',
			borderColor: '#21262d',
			liteBgColor: '#161b22',
			pageBg: '#010409',
			surfaceBg: '#0d1117',
		},
	},
	{
		value: 'carbon',
		colorOptions: [ '#000000', '#0a0a0a', '#333333', '#a1a1a1' ],
		label: __( 'Carbon', 'storesuite' ),
		description: __(
			'A true-black canvas with charcoal cards, grey text and subtle borders.',
			'storesuite'
		),
		colors: {
			textColor: '#a1a1a1',
			titleTextColor: '#ededed',
			liteTextColor: '#8f8f8f',
			iconColor: '#8f8f8f',
			sidebarMenuText: '#a1a1a1',
			sidebarBackground: '#000000',
			sidebarActiveText: '#ededed',
			sidebarBorderColor: '#333333',
			borderColor: '#333333',
			liteBgColor: '#1f1f1f',
			pageBg: '#000000',
			surfaceBg: '#0a0a0a',
		},
	},
];

// Where each dark neutral is stored. Deliberately no accent keys — see DARK_THEMES.
const DARK_COLOR_KEYS = {
	textColor: 'storesuite_dark_text_color',
	titleTextColor: 'storesuite_dark_title_text_color',
	liteTextColor: 'storesuite_dark_lite_text_color',
	iconColor: 'storesuite_dark_icon_color',
	sidebarMenuText: 'storesuite_dark_color_sidebar_menu_text',
	sidebarBackground: 'storesuite_dark_color_sidebar_background',
	sidebarActiveText: 'storesuite_dark_color_sidebar_active_text',
	sidebarBorderColor: 'storesuite_dark_color_sidebar_border',
	borderColor: 'storesuite_dark_color_border',
	liteBgColor: 'storesuite_dark_color_lite_bg',
	pageBg: 'storesuite_dark_color_page_bg',
	surfaceBg: 'storesuite_dark_color_surface_bg',
};

// Accent keys the dark preview borrows from the light palette.
const ACCENT_KEYS = [
	'buttonText',
	'buttonBackground',
	'buttonHoverText',
	'buttonHoverBackground',
	'sidebarActiveBackground',
];

const THEME_TABS = [
	{ value: 'light', label: __( 'Light Mode', 'storesuite' ) },
	{ value: 'dark', label: __( 'Dark Mode', 'storesuite' ) },
];

const LOGO_VARIANTS = [
	{
		value: 'dark',
		label: __( 'Dark logo', 'storesuite' ),
		description: __( 'Best for light sidebar backgrounds.', 'storesuite' ),
	},
	{
		value: 'light',
		label: __( 'Light logo', 'storesuite' ),
		description: __( 'Best for dark sidebar backgrounds.', 'storesuite' ),
	},
];

const findPalette = ( slug ) =>
	PREDEFINED_PALETTES.find( ( palette ) => palette.value === slug );

const findDarkTheme = ( slug ) =>
	DARK_THEMES.find( ( darkTheme ) => darkTheme.value === slug );

const getInitialColors = ( settings ) => {
	const mode = settings.storesuite_color_palette_mode ?? 'predefined';
	if ( mode === 'predefined' ) {
		const palette =
			findPalette( settings.storesuite_color_palette_name ) ||
			PREDEFINED_PALETTES[ 0 ];
		return { ...palette.colors };
	}
	return Object.fromEntries(
		COLOR_FIELDS.map( ( { key, apiKey, defaultValue } ) => {
			const storedValue = settings[ apiKey ];
			return [
				key,
				storedValue !== undefined && storedValue !== ''
					? String( storedValue )
					: defaultValue ?? '',
			];
		} )
	);
};

// What dark mode actually renders: the chosen dark neutrals, with the light
// palette's accent colors carried over unchanged.
const mergeDarkColors = ( darkTheme, lightColors ) => ( {
	...darkTheme.colors,
	...Object.fromEntries(
		ACCENT_KEYS.map( ( key ) => [ key, lightColors[ key ] ] )
	),
} );

const ColorControl = ( {
	label,
	value,
	colorKey,
	defaultValue = '#ffffff',
	onChange,
} ) => {
	const displayColor = value || defaultValue;
	return (
		<BaseControl
			id={ `storesuite-color-${ colorKey }` }
			label={ label }
			className="storesuite-color-control"
		>
			<Dropdown
				contentClassName="storesuite-color-picker-dropdown"
				renderContent={ () => (
					<div className="storesuite-color-picker-popover">
						<ColorPicker
							color={ displayColor }
							onChange={ onChange }
							enableAlpha={ false }
						/>
					</div>
				) }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="storesuite-color-toggle"
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						<ColorIndicator colorValue={ displayColor } />
						<svg
							width="24"
							height="24"
							viewBox="0 0 24 24"
							fill="currentColor"
							aria-hidden
						>
							<path d="M7 10l5 5 5-5z" />
						</svg>
					</Button>
				) }
			/>
		</BaseControl>
	);
};

const ModeCard = ( { isActive, onClick, title, description } ) => (
	<div
		className={ `storesuite-color-mode-card${
			isActive ? ' is-active' : ''
		}` }
		onClick={ onClick }
		role="button"
		tabIndex={ 0 }
		onKeyDown={ ( event ) => {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				onClick();
			}
		} }
	>
		<div className="storesuite-color-mode-icon">
			{ isActive && (
				<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path
						fillRule="evenodd"
						d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
						clipRule="evenodd"
					/>
				</svg>
			) }
		</div>
		<div className="storesuite-color-mode-content">
			<h4 className="storesuite-color-mode-title">{ title }</h4>
			<p className="storesuite-color-mode-desc">{ description }</p>
		</div>
	</div>
);
// Same row as a light palette: radio, name, and a strip of the theme's neutrals.
const DarkThemeItem = ( { darkTheme, isActive, onSelect } ) => {
	const { label, value, colorOptions } = darkTheme;

	return (
		<div
			className={ `storesuite-palette-item${
				isActive ? ' is-active' : ''
			}` }
			onClick={ () => onSelect( value ) }
			role="button"
			tabIndex={ 0 }
			onKeyDown={ ( event ) => {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					onSelect( value );
				}
			} }
		>
			<div className="storesuite-palette-item__radio">
				<input
					id={ `storesuite-dark-theme-${ value }` }
					type="radio"
					name="storesuite_dark_theme"
					value={ value }
					checked={ isActive }
					onChange={ () => onSelect( value ) }
				/>
				<span
					className="storesuite-palette-item__indicator"
					aria-hidden="true"
				>
					<svg viewBox="0 0 20 20" fill="currentColor">
						<path
							fillRule="evenodd"
							d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
							clipRule="evenodd"
						/>
					</svg>
				</span>
				<label htmlFor={ `storesuite-dark-theme-${ value }` }>
					{ label }
				</label>
			</div>
			<div className="storesuite-color-swatches">
				{ colorOptions.map( ( swatchColor, swatchIndex ) => (
					<div
						key={ swatchIndex }
						className="storesuite-color-swatch"
						style={ { backgroundColor: swatchColor } }
					/>
				) ) }
			</div>
		</div>
	);
};

const ColorsSettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ activeTab, setActiveTab ] = useState( 'light' );
	const [ paletteMode, setPaletteMode ] = useState(
		settings.storesuite_color_palette_mode ?? 'predefined'
	);
	const [ selectedPalette, setSelectedPalette ] = useState(
		findPalette( settings.storesuite_color_palette_name )
			? settings.storesuite_color_palette_name
			: 'default'
	);
	const [ colors, setColors ] = useState( () =>
		getInitialColors( settings )
	);
	const [ selectedDarkTheme, setSelectedDarkTheme ] = useState(
		findDarkTheme( settings.storesuite_dark_theme )
			? settings.storesuite_dark_theme
			: 'default'
	);
	const [ logoVariant, setLogoVariant ] = useState(
		settings.storesuite_attribution_logo_variant === 'light'
			? 'light'
			: 'dark'
	);

	const darkTheme = findDarkTheme( selectedDarkTheme ) || DARK_THEMES[ 0 ];
	const darkColors = mergeDarkColors( darkTheme, colors );

	const applyPalette = ( slug ) => {
		const palette = findPalette( slug );
		if ( palette ) {
			setColors( { ...palette.colors } );
		}
	};

	const handleModeChange = ( mode ) => {
		setPaletteMode( mode );
		if ( mode === 'predefined' ) {
			applyPalette( selectedPalette );
		}
	};

	const handlePaletteSelect = ( slug ) => {
		setSelectedPalette( slug );
		applyPalette( slug );
	};

	const handleResetColors = () => {
		setColors(
			Object.fromEntries(
				COLOR_FIELDS.map( ( { key, defaultValue } ) => [
					key,
					defaultValue ?? '',
				] )
			)
		);
	};

	const handleSubmit = ( event ) => {
		event.preventDefault();

		const data = {
			storesuite_color_palette_mode: paletteMode,
			storesuite_color_palette_name:
				paletteMode === 'predefined' ? selectedPalette : '',
			storesuite_attribution_logo_variant: logoVariant,
			storesuite_dark_theme: selectedDarkTheme,
		};

		COLOR_FIELDS.forEach( ( { key, apiKey } ) => {
			data[ apiKey ] = colors[ key ] ?? '';
		} );

		// Only the dark neutrals are stored; accents stay with the light palette.
		Object.entries( DARK_COLOR_KEYS ).forEach( ( [ key, apiKey ] ) => {
			data[ apiKey ] = darkTheme.colors[ key ] ?? '';
		} );

		saveSettings( data );
	};

	return (
		<div className="storesuite-section" id="storesuite-colors-settings">
			<form onSubmit={ handleSubmit } className="storesuite-colors-form">
				<Card className="storesuite-form-header-card">
					<CardBody className="storesuite-form-section-header">
						<h3 className="storesuite-section-title">
							{ __( 'Appearance Settings', 'storesuite' ) }
						</h3>
						<p className="storesuite-section-description">
							{ __(
								'Customise the colors used throughout the frontend dashboard.',
								'storesuite'
							) }
						</p>
					</CardBody>
				</Card>
				<Card>
					<CardBody className="storesuite-form-section-body">
						<div
							className="storesuite-theme-tabs"
							role="tablist"
							aria-label={ __( 'Color scheme', 'storesuite' ) }
						>
							{ THEME_TABS.map( ( { value, label } ) => (
								<button
									key={ value }
									type="button"
									role="tab"
									aria-selected={ activeTab === value }
									className={ `storesuite-theme-tab${
										activeTab === value ? ' is-active' : ''
									}` }
									onClick={ () => setActiveTab( value ) }
								>
									{ label }
								</button>
							) ) }
						</div>

						{ activeTab === 'light' ? (
							<>
								<div className="storesuite-color-mode-selector">
									<ModeCard
										isActive={
											paletteMode === 'predefined'
										}
										onClick={ () =>
											handleModeChange( 'predefined' )
										}
										title={ __(
											'Pre-defined Color Palette',
											'storesuite'
										) }
										description={ __(
											'Choose from ready-made color palettes to quickly style your dashboard.',
											'storesuite'
										) }
									/>
									<ModeCard
										isActive={ paletteMode === 'custom' }
										onClick={ () =>
											handleModeChange( 'custom' )
										}
										title={ __(
											'Custom Color Palette',
											'storesuite'
										) }
										description={ __(
											'Pick individual colors to match your brand identity.',
											'storesuite'
										) }
									/>
								</div>

								<div className="storesuite-colors-layout">
									<div className="storesuite-colors-left">
										{ paletteMode === 'predefined' ? (
											<div className="storesuite-palette-list">
												{ PREDEFINED_PALETTES.map(
													( palette ) => (
														<div
															key={
																palette.value
															}
															className={ `storesuite-palette-item${
																selectedPalette ===
																palette.value
																	? ' is-active'
																	: ''
															}` }
															onClick={ () =>
																handlePaletteSelect(
																	palette.value
																)
															}
														>
															<div className="storesuite-palette-item__radio">
																<input
																	id={ `storesuite-palette-${ palette.value }` }
																	type="radio"
																	name="storesuite_color_palette"
																	value={
																		palette.value
																	}
																	checked={
																		selectedPalette ===
																		palette.value
																	}
																	onChange={ () =>
																		handlePaletteSelect(
																			palette.value
																		)
																	}
																/>
																<span
																	className="storesuite-palette-item__indicator"
																	aria-hidden="true"
																>
																	<svg
																		viewBox="0 0 20 20"
																		fill="currentColor"
																	>
																		<path
																			fillRule="evenodd"
																			d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
																			clipRule="evenodd"
																		/>
																	</svg>
																</span>
																<label
																	htmlFor={ `storesuite-palette-${ palette.value }` }
																>
																	{
																		palette.label
																	}
																</label>
															</div>
															<div className="storesuite-color-swatches">
																{ palette.colorOptions.map(
																	(
																		swatchColor,
																		swatchIndex
																	) => (
																		<div
																			key={
																				swatchIndex
																			}
																			className="storesuite-color-swatch"
																			style={ {
																				backgroundColor:
																					swatchColor,
																			} }
																		/>
																	)
																) }
															</div>
														</div>
													)
												) }
											</div>
										) : (
											<>
												<div className="storesuite-custom-color-header">
													<h4>
														{ __(
															'Choose the color:',
															'storesuite'
														) }
													</h4>
													<button
														type="button"
														className="storesuite-reset-btn"
														onClick={
															handleResetColors
														}
													>
														{ __(
															'Reset all',
															'storesuite'
														) }
													</button>
												</div>
												<div className="storesuite-custom-color-list storesuite-settings-group">
													{ COLOR_FIELDS.map(
														( {
															key,
															label,
															defaultValue,
														} ) => (
															<ColorControl
																key={ key }
																colorKey={ key }
																label={ label }
																value={
																	colors[
																		key
																	]
																}
																defaultValue={
																	defaultValue
																}
																onChange={ (
																	value
																) =>
																	setColors(
																		(
																			prev
																		) => ( {
																			...prev,
																			[ key ]:
																				value,
																		} )
																	)
																}
															/>
														)
													) }
												</div>
												<div className="storesuite-sidebar-footer-logo">
													<div className="storesuite-custom-color-header storesuite-logo-variant-header">
														<h4>
															{ __(
																'Sidebar Footer Attribution Logo:',
																'storesuite'
															) }
														</h4>
													</div>
													<div className="storesuite-palette-list storesuite-logo-variant-list">
														{ LOGO_VARIANTS.map(
															( variant ) => (
																<div
																	key={
																		variant.value
																	}
																	className={ `storesuite-palette-item${
																		logoVariant ===
																		variant.value
																			? ' is-active'
																			: ''
																	}` }
																	onClick={ () =>
																		setLogoVariant(
																			variant.value
																		)
																	}
																>
																	<div className="storesuite-palette-item__radio">
																		<input
																			id={ `storesuite-logo-variant-${ variant.value }` }
																			type="radio"
																			name="storesuite_attribution_logo_variant"
																			value={
																				variant.value
																			}
																			checked={
																				logoVariant ===
																				variant.value
																			}
																			onChange={ () =>
																				setLogoVariant(
																					variant.value
																				)
																			}
																		/>
																		<span
																			className="storesuite-palette-item__indicator"
																			aria-hidden="true"
																		>
																			<svg
																				viewBox="0 0 20 20"
																				fill="currentColor"
																			>
																				<path
																					fillRule="evenodd"
																					d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
																					clipRule="evenodd"
																				/>
																			</svg>
																		</span>
																		<label
																			htmlFor={ `storesuite-logo-variant-${ variant.value }` }
																		>
																			{
																				variant.label
																			}
																			<span className="storesuite-logo-variant-hint">
																				{
																					variant.description
																				}
																			</span>
																		</label>
																	</div>
																</div>
															)
														) }
													</div>
												</div>
											</>
										) }
									</div>

									<div className="storesuite-colors-right">
										<ColorPreview colors={ colors } />
									</div>
								</div>
							</>
						) : (
							<>
								<p className="storesuite-dark-theme-intro">
									{ __(
										'Pick how dark mode looks. Your light palette keeps supplying the accent colors — only the backgrounds, text and borders change.',
										'storesuite'
									) }
								</p>
								<div className="storesuite-colors-layout">
									<div className="storesuite-colors-left">
										<div className="storesuite-palette-list">
											{ DARK_THEMES.map( ( theme ) => (
												<DarkThemeItem
													key={ theme.value }
													darkTheme={ theme }
													isActive={
														selectedDarkTheme ===
														theme.value
													}
													onSelect={
														setSelectedDarkTheme
													}
												/>
											) ) }
										</div>
									</div>

									<div className="storesuite-colors-right">
										<ColorPreview
											colors={ darkColors }
											theme="dark"
										/>
									</div>
								</div>
							</>
						) }

						<Button
							variant="primary"
							type="submit"
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ isSaving && <Spinner /> }
							{ __( 'Save Changes', 'storesuite' ) }
						</Button>
					</CardBody>
				</Card>
			</form>
		</div>
	);
};

export default ColorsSettings;
