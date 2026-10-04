/**
 * Customer CRM — manual tags editor.
 *
 * A lightweight tag input: comma/enter-separated tags saved to the module REST.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchCustomerTags, setCustomerTags } from '../api';

const TagsEditor = ( { customerId } ) => {
	const [ tags, setTags ] = useState( [] );
	const [ input, setInput ] = useState( '' );

	useEffect( () => {
		fetchCustomerTags( customerId )
			.then( ( data ) =>
				setTags( Array.isArray( data ) ? data.map( ( t ) => t.name ) : [] )
			)
			.catch( () => setTags( [] ) );
	}, [ customerId ] );

	const persist = ( next ) => {
		setTags( next );
		setCustomerTags( customerId, next ).catch( () => {} );
	};

	const addTag = ( value ) => {
		const name = value.trim().replace( /,$/, '' );
		if ( name && ! tags.includes( name ) ) {
			persist( [ ...tags, name ] );
		}
		setInput( '' );
	};

	const removeTag = ( name ) => {
		persist( tags.filter( ( t ) => t !== name ) );
	};

	return (
		<div className="storesuite-tags-editor">
			<div className="storesuite-tags-list">
				{ tags.map( ( name ) => (
					<span key={ name } className="storesuite-tag-pill">
						{ name }
						<button
							type="button"
							aria-label={ __( 'Remove tag', 'storesuite' ) }
							onClick={ () => removeTag( name ) }
						>
							×
						</button>
					</span>
				) ) }
			</div>
			<input
				type="text"
				className="storesuite-tag-input"
				value={ input }
				placeholder={ __( 'Add tag and press Enter', 'storesuite' ) }
				onChange={ ( e ) => setInput( e.target.value ) }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === ',' ) {
						e.preventDefault();
						addTag( input );
					}
				} }
			/>
		</div>
	);
};

export default TagsEditor;
