import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const MODES = [
	{ value: 'light', label: __( 'Light mode', 'storesuite' ) },
	{ value: 'dark', label: __( 'Dark mode', 'storesuite' ) },
];

function ImagePicker( { attachmentId, onChange, isDark } ) {
	const [ previewUrl, setPreviewUrl ] = useState( '' );
	const id = parseInt( attachmentId, 10 ) || 0;

	useEffect( () => {
		if ( ! id ) {
			setPreviewUrl( '' );
			return;
		}
		let cancelled = false;
		apiFetch( { path: `/wp/v2/media/${ id }` } )
			.then( ( media ) => {
				if ( ! cancelled && media?.source_url ) {
					setPreviewUrl( media.source_url );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setPreviewUrl( '' );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ id ] );

	const openMediaLibrary = () => {
		if ( ! window.wp?.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: __( 'Select image', 'storesuite' ),
			button: { text: __( 'Use this image', 'storesuite' ) },
			multiple: false,
			library: { type: 'image' },
		} );
		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			if ( attachment?.id ) {
				onChange( attachment.id );
			}
		} );
		frame.open();
	};

	return (
		<>
			{ previewUrl && (
				<div
					className={ `storesuite-sidebar-image-control__preview${
						isDark ? ' is-dark' : ''
					}` }
				>
					<img src={ previewUrl } alt="" />
				</div>
			) }
			<div className="storesuite-sidebar-image-control__actions">
				<Button variant="secondary" onClick={ openMediaLibrary }>
					{ id
						? __( 'Replace image', 'storesuite' )
						: __( 'Select image', 'storesuite' ) }
				</Button>
				{ id > 0 && (
					<Button
						isDestructive
						variant="tertiary"
						onClick={ () => onChange( 0 ) }
					>
						{ __( 'Remove', 'storesuite' ) }
					</Button>
				) }
			</div>
		</>
	);
}

export default function DashboardSidebarImageControl( {
	label,
	help,
	darkHelp,
	attachmentId,
	darkAttachmentId,
	onChange,
	onDarkChange,
} ) {
	const [ mode, setMode ] = useState( 'light' );
	const isDark = mode === 'dark';

	return (
		<div className="storesuite-settings-group storesuite-sidebar-image-control">
			<p className="storesuite-sidebar-image-control__label">{ label }</p>
			<div className="storesuite-theme-tabs storesuite-theme-tabs--compact">
				{ MODES.map( ( { value, label: modeLabel } ) => (
					<button
						key={ value }
						type="button"
						className={ `storesuite-theme-tab${
							mode === value ? ' is-active' : ''
						}` }
						aria-pressed={ mode === value }
						onClick={ () => setMode( value ) }
					>
						{ modeLabel }
					</button>
				) ) }
			</div>
			{ isDark
				? !! darkHelp && (
						<p className="storesuite-sidebar-image-control__help">
							{ darkHelp }
						</p>
				  )
				: !! help && (
						<p className="storesuite-sidebar-image-control__help">
							{ help }
						</p>
				  ) }
			{ /* Each mode keeps its own picker instance so switching tabs
			     re-reads the preview for that attachment. */ }
			{ isDark ? (
				<ImagePicker
					key="dark"
					attachmentId={ darkAttachmentId }
					onChange={ onDarkChange }
					isDark
				/>
			) : (
				<ImagePicker
					key="light"
					attachmentId={ attachmentId }
					onChange={ onChange }
				/>
			) }
		</div>
	);
}
