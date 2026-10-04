/**
 * Customer CRM — internal notes panel.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, Button, TextareaControl } from '@wordpress/components';
import { fetchNotes, addNote, deleteNote } from '../api';

const NotesPanel = ( { customerId } ) => {
	const [ notes, setNotes ] = useState( [] );
	const [ draft, setDraft ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const load = () => {
		fetchNotes( customerId )
			.then( ( data ) => setNotes( Array.isArray( data ) ? data : [] ) )
			.catch( () => setNotes( [] ) );
	};

	useEffect( load, [ customerId ] );

	const submit = () => {
		if ( ! draft.trim() ) {
			return;
		}
		setSaving( true );
		addNote( customerId, draft )
			.then( () => {
				setDraft( '' );
				load();
			} )
			.finally( () => setSaving( false ) );
	};

	const remove = ( id ) => {
		deleteNote( id ).then( load );
	};

	return (
		<Card className="storesuite-notes-panel">
			<CardBody>
				<h3>{ __( 'Internal notes', 'storesuite' ) }</h3>
				<TextareaControl
					value={ draft }
					onChange={ setDraft }
					placeholder={ __( 'Add a private note about this customer…', 'storesuite' ) }
					__nextHasNoMarginBottom
				/>
				<Button variant="primary" onClick={ submit } isBusy={ saving } disabled={ saving }>
					{ __( 'Add note', 'storesuite' ) }
				</Button>

				<ul className="storesuite-notes-list">
					{ notes.map( ( note ) => (
						<li key={ note.id }>
							<div className="storesuite-note-body">{ note.note }</div>
							<div className="storesuite-note-meta">
								{ note.author } · { note.created_h }
								<button
									type="button"
									className="storesuite-linklike"
									onClick={ () => remove( note.id ) }
								>
									{ __( 'Delete', 'storesuite' ) }
								</button>
							</div>
						</li>
					) ) }
					{ notes.length === 0 && (
						<li className="storesuite-notes-empty">
							{ __( 'No notes yet.', 'storesuite' ) }
						</li>
					) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default NotesPanel;
