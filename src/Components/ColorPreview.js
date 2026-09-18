import { __ } from '@wordpress/i18n';

const MENU_ITEMS = 6;
const HEADER_DOTS = [ 1, 2, 3 ];
const STAT_CARDS = [ 1, 2, 3, 4 ];
const CHART_LINES = [ 1, 2, 3 ];
const TEXT_LINES = [ 1, 2 ];

const ColorPreview = ( { colors, theme = 'light' } ) => (
	<div className="storesuite-color-preview">
		<p className="storesuite-preview-label">
			{ __( 'Preview', 'storesuite' ) }
		</p>
		<div
			className={ `storesuite-preview-wrap${
				theme === 'dark' ? ' is-dark' : ''
			}` }
			style={ {
				'--storesuite-preview-page-bg': colors.pageBg,
				'--storesuite-preview-surface-bg': colors.surfaceBg,
			} }
		>
			<div className="storesuite-preview-header">
				<div className="storesuite-preview-dots">
					{ HEADER_DOTS.map( ( dotIndex ) => (
						<span
							key={ dotIndex }
							className={ `storesuite-preview-dot storesuite-preview-dot--${ dotIndex }` }
						/>
					) ) }
				</div>
			</div>

			<div className="storesuite-preview-body">
				<div
					className="storesuite-preview-sidebar"
					style={ { backgroundColor: colors.sidebarBackground } }
				>
					<div className="storesuite-preview-site-logo" />
					<div className="storesuite-preview-menu">
						{ Array.from(
							{ length: MENU_ITEMS },
							( _, menuIndex ) => {
								const isActive = menuIndex === 0;
								const itemBackground = isActive
									? colors.sidebarActiveBackground
									: 'transparent';
								const itemForeground = isActive
									? colors.sidebarActiveText
									: colors.sidebarMenuText;
								return (
									<div
										key={ menuIndex }
										className="storesuite-preview-menu-item"
										style={ {
											backgroundColor: itemBackground,
										} }
									>
										<span
											className="storesuite-preview-menu-icon"
											style={ {
												backgroundColor: itemForeground,
											} }
										/>
										<span
											className="storesuite-preview-menu-text"
											style={ {
												backgroundColor: itemForeground,
											} }
										/>
									</div>
								);
							}
						) }
					</div>
				</div>

				<div className="storesuite-preview-content">
					<div className="storesuite-preview-row">
						<div className="storesuite-preview-stat-cards">
							{ STAT_CARDS.map( ( cardIndex ) => (
								<div
									key={ cardIndex }
									className="storesuite-preview-stat-card"
								/>
							) ) }
						</div>
						<div
							className="storesuite-preview-btn"
							style={ {
								backgroundColor: colors.buttonBackground,
								color: colors.buttonText,
							} }
						>
							{ __( 'Button', 'storesuite' ) }
						</div>
					</div>

					<div className="storesuite-preview-chart-card">
						<div className="storesuite-preview-chart-lines">
							{ CHART_LINES.map( ( lineIndex ) => (
								<span
									key={ lineIndex }
									className="storesuite-preview-chart-line"
								/>
							) ) }
						</div>
						<div
							className="storesuite-preview-btn"
							style={ {
								backgroundColor: colors.buttonHoverBackground,
								color: colors.buttonHoverText,
							} }
						>
							{ __( 'Button Hover', 'storesuite' ) }
						</div>
					</div>

					<div className="storesuite-preview-row">
						<div className="storesuite-preview-text-lines">
							{ TEXT_LINES.map( ( lineIndex ) => (
								<span
									key={ lineIndex }
									className="storesuite-preview-text-line"
								/>
							) ) }
						</div>
						<div
							className="storesuite-preview-border-btn"
							style={ { borderColor: colors.borderColor } }
						>
							{ __( 'Button Border', 'storesuite' ) }
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
);

export default ColorPreview;
