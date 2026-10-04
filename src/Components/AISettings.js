import {
	useState,
	createInterpolateElement,
	Fragment,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Notice,
	Spinner,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

import { useSettings } from '../context/SettingsContext';

// Each AI-assisted product field that can be toggled on or off. The buttons are
// shown on the product form only when the matching field is enabled. Defaults to
// enabled when the setting has never been saved.
const AI_FIELDS = [
	{
		key: 'title',
		apiKey: 'storesuite_ai_field_title',
		label: __( 'Product Title', 'storesuite' ),
		help: __(
			'Show the "Generate with AI" button on the product title field.',
			'storesuite'
		),
	},
	{
		key: 'description',
		apiKey: 'storesuite_ai_field_description',
		label: __( 'Product Long description', 'storesuite' ),
		help: __(
			'Show the "Generate with AI" button on the long description field.',
			'storesuite'
		),
	},
	{
		key: 'short_description',
		apiKey: 'storesuite_ai_field_short_description',
		label: __( 'Product Short description', 'storesuite' ),
		help: __(
			'Show the "Generate with AI" button on the short description field.',
			'storesuite'
		),
	},
	{
		key: 'featured_image',
		apiKey: 'storesuite_ai_field_featured_image',
		label: __( 'Product featured image', 'storesuite' ),
		help: __(
			'Show the "AI" button on the product featured image.',
			'storesuite'
		),
	},
	{
		key: 'gallery_image',
		apiKey: 'storesuite_ai_field_gallery_image',
		label: __( 'Product Gallery images', 'storesuite' ),
		help: __(
			'Show the "AI" button on the product gallery images.',
			'storesuite'
		),
	},
	{
		key: 'bundle',
		apiKey: 'storesuite_ai_field_bundle',
		label: __( 'Product Global Generate with AI button', 'storesuite' ),
		help: __(
			'Show the header button that drafts the title, long and short descriptions together.',
			'storesuite'
		),
	},
];

// Built-in defaults exposed by PHP. The text instructions are keyed by field
// (title, description, short_description); the image instruction is a single
// string. Used to prefill the textareas.
const DEFAULT_INSTRUCTIONS =
	window.storeSuiteAdmin?.aiDefaultInstructions ?? {};
const DEFAULT_IMAGE_INSTRUCTION =
	window.storeSuiteAdmin?.aiDefaultImageInstruction ?? '';

// Whether at least one AI provider is connected, and the WordPress Connectors
// page where one can be set up. Default to connected so a missing global never
// shows a false warning.
const AI_CONNECTED = window.storeSuiteAdmin?.aiConnected ?? true;

// Extra blocks contributed by integrations (e.g. Yoast SEO): one toggle for the
// group and one instruction textarea per distinct system instruction. Only
// present while the integration is active.
const AI_GROUPS = window.storeSuiteAdmin?.aiSettingsGroups ?? [];
const CONNECTORS_URL = window.storeSuiteAdmin?.connectorsUrl ?? '';

// Custom system instructions. Each textarea is prefilled with the saved custom
// instruction, falling back to the built-in default so the merchant always
// starts from the instruction that is actually in effect. An empty saved value
// means "use the default" on the server side.
const AI_INSTRUCTIONS = [
	{
		key: 'title',
		apiKey: 'storesuite_ai_instruction_title',
		label: __( 'Product Title system instruction', 'storesuite' ),
		help: __(
			'Guides how the product title is generated. Leave empty to use the built-in default.',
			'storesuite'
		),
		defaultValue: DEFAULT_INSTRUCTIONS.title ?? '',
	},
	{
		key: 'description',
		apiKey: 'storesuite_ai_instruction_description',
		label: __(
			'Product Long description system instruction',
			'storesuite'
		),
		help: __(
			'Guides how the long description is generated. Leave empty to use the built-in default.',
			'storesuite'
		),
		defaultValue: DEFAULT_INSTRUCTIONS.description ?? '',
	},
	{
		key: 'short_description',
		apiKey: 'storesuite_ai_instruction_short_description',
		label: __(
			'Product Short description system instruction',
			'storesuite'
		),
		help: __(
			'Guides how the short description is generated. Leave empty to use the built-in default.',
			'storesuite'
		),
		defaultValue: DEFAULT_INSTRUCTIONS.short_description ?? '',
	},
	{
		key: 'image',
		apiKey: 'storesuite_ai_image_instruction',
		label: __( 'Product image system instruction', 'storesuite' ),
		help: __(
			'Styling guidance appended to every image prompt. Leave empty to use the built-in default.',
			'storesuite'
		),
		defaultValue: DEFAULT_IMAGE_INSTRUCTION,
	},
];

// A field is on unless it was explicitly saved as "no".
const isEnabled = ( value ) => value !== 'no' && value !== false;

const AISettings = () => {
	const { settings, isSaving, saveSettings } = useSettings();

	const [ fields, setFields ] = useState( () =>
		Object.fromEntries(
			AI_FIELDS.map( ( { key, apiKey } ) => [
				key,
				isEnabled( settings[ apiKey ] ),
			] )
		)
	);

	const [ instructions, setInstructions ] = useState( () =>
		Object.fromEntries(
			AI_INSTRUCTIONS.map( ( { key, apiKey, defaultValue } ) => {
				const saved = settings[ apiKey ];
				return [
					key,
					saved !== undefined && saved !== '' ? saved : defaultValue,
				];
			} )
		)
	);

	// Integration groups: one toggle and one textarea per instruction, keyed by
	// their settings key so they need no hardcoded knowledge of the integration.
	const [ groupFields, setGroupFields ] = useState( () =>
		Object.fromEntries(
			AI_GROUPS.filter( ( group ) => group.enabled_setting ).map(
				( group ) => [
					group.enabled_setting,
					isEnabled( settings[ group.enabled_setting ] ),
				]
			)
		)
	);

	const [ groupInstructions, setGroupInstructions ] = useState( () =>
		Object.fromEntries(
			AI_GROUPS.flatMap( ( group ) => group.instructions ).map(
				( instruction ) => {
					const saved = settings[ instruction.setting ];
					return [
						instruction.setting,
						saved !== undefined && saved !== ''
							? saved
							: instruction.default,
					];
				}
			)
		)
	);

	const handleSubmit = ( event ) => {
		event.preventDefault();
		const data = {};
		AI_FIELDS.forEach( ( { key, apiKey } ) => {
			data[ apiKey ] = fields[ key ] ? 'yes' : 'no';
		} );
		AI_INSTRUCTIONS.forEach( ( { key, apiKey } ) => {
			data[ apiKey ] = instructions[ key ];
		} );
		Object.entries( groupFields ).forEach( ( [ apiKey, value ] ) => {
			data[ apiKey ] = value ? 'yes' : 'no';
		} );
		Object.entries( groupInstructions ).forEach( ( [ apiKey, value ] ) => {
			data[ apiKey ] = value;
		} );
		saveSettings( data );
	};

	return (
		<div
			className="storesuite-section storesuite-section--medium"
			id="storesuite-ai-settings"
		>
			{ ! AI_CONNECTED ? (
				<Notice
					status="warning"
					isDismissible={ false }
					className="storesuite-ai-connector-notice"
				>
					{ createInterpolateElement(
						__(
							'No AI provider is connected yet. <a>Connect a provider on the Connectors page</a> to start generating product content.',
							'storesuite'
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									href={ CONNECTORS_URL }
									target="_blank"
									rel="noreferrer"
								/>
							),
						}
					) }
				</Notice>
			) : (
				<form onSubmit={ handleSubmit }>
					<Card className="storesuite-form-header-card">
						<CardBody className="storesuite-form-section-header">
							<h3 className="storesuite-section-title">
								{ __( 'AI Settings', 'storesuite' ) }
							</h3>
							<p className="storesuite-section-description">
								{ __(
									'Choose which product fields offer AI-assisted generation on the storesuite frontend product form.',
									'storesuite'
								) }
							</p>
						</CardBody>
					</Card>
					<Card>
						<CardBody className="storesuite-form-section-body">
							{ AI_FIELDS.map( ( { key, label, help } ) => (
								<div
									key={ key }
									className="storesuite-settings-group"
								>
									<ToggleControl
										label={ label }
										help={ help }
										checked={ fields[ key ] }
										onChange={ ( value ) =>
											setFields( ( prev ) => ( {
												...prev,
												[ key ]: value,
											} ) )
										}
									/>
								</div>
							) ) }
						</CardBody>
					</Card>

					<Card className="storesuite-form-header-card storesuite-section-gap-top">
						<CardBody className="storesuite-form-section-header">
							<h3 className="storesuite-section-title">
								{ __( 'System Instructions', 'storesuite' ) }
							</h3>
							<p className="storesuite-section-description">
								{ __(
									'Customize how AI generates each product fields response.',
									'storesuite'
								) }
							</p>
						</CardBody>
					</Card>
					<Card>
						<CardBody className="storesuite-form-section-body">
							{ AI_INSTRUCTIONS.map( ( { key, label, help } ) => (
								<div
									key={ key }
									className="storesuite-settings-group"
								>
									<TextareaControl
										label={ label }
										help={ help }
										rows={ 4 }
										value={ instructions[ key ] }
										onChange={ ( value ) =>
											setInstructions( ( prev ) => ( {
												...prev,
												[ key ]: value,
											} ) )
										}
									/>
								</div>
							) ) }
						</CardBody>
					</Card>

					{ AI_GROUPS.map( ( group ) => (
						<Fragment key={ group.key }>
							<Card className="storesuite-form-header-card storesuite-section-gap-top">
								<CardBody className="storesuite-form-section-header">
									<h3 className="storesuite-section-title">
										{ group.label }
									</h3>
								</CardBody>
							</Card>
							<Card>
								<CardBody className="storesuite-form-section-body">
									{ group.enabled_setting && (
										<div className="storesuite-settings-group">
											<ToggleControl
												label={ __(
													'Offer AI generation for these fields',
													'storesuite'
												) }
												checked={
													groupFields[
														group.enabled_setting
													]
												}
												onChange={ ( value ) =>
													setGroupFields(
														( prev ) => ( {
															...prev,
															[ group.enabled_setting ]:
																value,
														} )
													)
												}
											/>
										</div>
									) }
									{ group.instructions.map(
										( instruction ) => (
											<div
												key={ instruction.setting }
												className="storesuite-settings-group"
											>
												<TextareaControl
													label={ instruction.label }
													rows={ 4 }
													value={
														groupInstructions[
															instruction.setting
														]
													}
													onChange={ ( value ) =>
														setGroupInstructions(
															( prev ) => ( {
																...prev,
																[ instruction.setting ]:
																	value,
															} )
														)
													}
												/>
											</div>
										)
									) }
								</CardBody>
							</Card>
						</Fragment>
					) ) }

					<Card className="storesuite-section-gap-top">
						<CardBody className="storesuite-form-section-body">
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
			) }
		</div>
	);
};

export default AISettings;
