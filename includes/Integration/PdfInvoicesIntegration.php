<?php

namespace PluginizeLab\StoreSuite\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;

/**
 * Surfaces third-party PDF-invoice / packing-slip document actions inside the
 * StoreSuite frontend order list and order details pages.
 *
 * Supported plugins (detected dynamically, whichever are active):
 * - WooCommerce PDF Invoices & Packing Slips (WPO).
 * - WebToffee Print Invoices, Packing Slips, Delivery Notes & Shipping Labels.
 *
 * The class is fully self-contained: the order templates only expose generic
 * StoreSuite extension hooks, and this integration injects the document links.
 */
class PdfInvoicesIntegration {

	/**
	 * Constructor. Bails unless at least one supported plugin is active.
	 */
	public function __construct() {
		if ( ! $this->is_wpo_active() && ! $this->is_webtoffee_active() ) {
			return;
		}

		add_action( 'storesuite_order_list_row_actions', array( $this, 'render_list_row_actions' ) );
		// Priority 5 so the Documents card appears above notes / customer-history (default 10).
		add_action( 'storesuite_after_order_details_action', array( $this, 'render_order_details_documents' ), 5 );
	}

	/**
	 * Whether WooCommerce PDF Invoices & Packing Slips (WPO) is active.
	 *
	 * @return bool
	 */
	protected function is_wpo_active(): bool {
		return function_exists( 'WPO_WCPDF' );
	}

	/**
	 * Whether WebToffee Print Invoices, Packing Slips etc. is active.
	 *
	 * @return bool
	 */
	protected function is_webtoffee_active(): bool {
		return class_exists( 'Wf_Woocommerce_Packing_List_Admin' );
	}

	/**
	 * Build the SVG icon markup for a document action.
	 *
	 * References a symbol from the shared dashboard sprite (templates/dashboard-icons.php),
	 * which is emitted once per dashboard page.
	 *
	 * @param string $icon Icon name: 'print' or 'download'.
	 *
	 * @return string SVG markup, or an empty string for an unknown icon.
	 */
	protected function get_document_icon( string $icon ): string {
		$symbols = array(
			'print'    => 'storesuite-icon-printer',
			'download' => 'storesuite-icon-cloud-download',
		);

		if ( ! isset( $symbols[ $icon ] ) ) {
			return '';
		}

		return sprintf(
			'<svg width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><use href="#%s"></use></svg>',
			esc_attr( $symbols[ $icon ] )
		);
	}

	/**
	 * Render document links inside the order list row-actions dropdown.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_list_row_actions( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		// Trashed orders have no printable documents.
		if ( 'trash' === $order->get_status() ) {
			return;
		}

		$documents = $this->get_documents_for_order( $order, 'list_page' );

		foreach ( $documents as $document ) {
			if ( empty( $document['url'] ) ) {
				continue;
			}
			?>
			<li>
				<?php // The icon and label must not be separated by whitespace: `.dropdown-link` is a block, so a text node between them renders as a leading space and offsets the label. ?>
				<a href="<?php echo esc_url( $document['url'] ); ?>" class="dropdown-link<?php echo empty( $document['print'] ) ? '' : ' storesuite-print-document'; ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->get_document_icon( $document['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_document_icon(). ?><?php echo esc_html( $document['label'] ); ?></a>
			</li>
			<?php
		}
	}

	/**
	 * Render a "Documents" card in the order details sidebar.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_order_details_documents( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$documents = array_filter(
			$this->get_documents_for_order( $order, 'detail_page' ),
			static function ( $document ) {
				return ! empty( $document['url'] );
			}
		);

		if ( empty( $documents ) ) {
			return;
		}
		?>
		<div class="storesuite-card storesuite-order-documents">
			<div class="card-title-with-link">
				<h3 class="storesuite-card-title"><?php esc_html_e( 'Documents', 'storesuite' ); ?></h3>
			</div>
			<div class="storesuite-card-content">
				<div class="storesuite-order-documents-list">
					<?php
					foreach ( $documents as $document ) :
						$classes = 'my-storesuite-button my-storesuite-button-light';

						if ( ! empty( $document['half'] ) ) {
							$classes .= ' storesuite-document-half';
						}

						if ( ! empty( $document['print'] ) ) {
							$classes .= ' storesuite-print-document';
						}
						?>
						<a href="<?php echo esc_url( $document['url'] ); ?>" class="<?php echo esc_attr( $classes ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo $this->get_document_icon( $document['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_document_icon(). ?>
							<?php echo esc_html( $document['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Build a normalized list of documents for an order, merged from every
	 * active supported plugin.
	 *
	 * @param WC_Order $order   The order.
	 * @param string   $context Rendering context: 'list_page' or 'detail_page'.
	 *
	 * @return array<int, array{label:string, url:string, exists:bool, print:bool, icon:string, half:bool}>
	 */
	public function get_documents_for_order( WC_Order $order, string $context ): array {
		$documents = array();

		if ( $this->is_wpo_active() ) {
			$documents = array_merge( $documents, $this->get_wpo_documents( $order ) );
		}

		if ( $this->is_webtoffee_active() ) {
			$documents = array_merge( $documents, $this->get_webtoffee_documents( $order, $context ) );
		}

		return $documents;
	}

	/**
	 * Collect WooCommerce PDF Invoices & Packing Slips (WPO) documents.
	 *
	 * Mirrors the admin order-actions logic in the plugin's includes/Admin.php.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return array<int, array{label:string, url:string, exists:bool, print:bool, icon:string, half:bool}>
	 */
	protected function get_wpo_documents( WC_Order $order ): array {
		$items = array();

		if ( ! function_exists( 'wcpdf_get_document' ) ) {
			return $items;
		}

		$documents = WPO_WCPDF()->documents->get_documents( 'enabled', 'any' );

		foreach ( $documents as $document ) {
			$type = $document->get_type();
			$doc  = wcpdf_get_document( $type, $order );

			if ( ! $doc || ! $doc->is_enabled( 'pdf' ) ) {
				continue;
			}

			$items[] = array(
				'label'  => $doc->get_title(),
				'url'    => WPO_WCPDF()->endpoint->get_document_link( $order, $type ),
				'exists' => is_callable( array( $doc, 'exists' ) ) ? $doc->exists() : false,
				// WPO streams a PDF, which the browser renders in the new tab.
				'print'  => false,
				'icon'   => 'download',
				// WPO exposes few documents with short labels, so they pair up two per row.
				'half'   => true,
			);
		}

		return $items;
	}

	/**
	 * Collect WebToffee Print Invoices / Packing Slips documents.
	 *
	 * Reuses the plugin's own `wt_print_actions` button filter so every document
	 * the plugin exposes (invoice, packing slip, delivery note, shipping label,
	 * dispatch label, UBL invoice, …) is included. Each filtered entry describes
	 * an action rather than a link, so URLs are built with the plugin's own
	 * `get_print_url()` helper.
	 *
	 * @param WC_Order $order   The order.
	 * @param string   $context Rendering context: 'list_page' or 'detail_page'.
	 *
	 * @return array<int, array{label:string, url:string, exists:bool, print:bool, icon:string, half:bool}>
	 */
	protected function get_webtoffee_documents( WC_Order $order, string $context ): array {
		$items = array();

		if ( ! is_callable( array( 'Wf_Woocommerce_Packing_List_Admin', 'get_print_url' ) ) ) {
			return $items;
		}

		$order_id = $order->get_id();
		$buttons  = apply_filters( 'wt_print_actions', array(), $order, $order_id, $context );

		if ( empty( $buttons ) || ! is_array( $buttons ) ) {
			return $items;
		}

		foreach ( $buttons as $button ) {
			if ( ! is_array( $button ) ) {
				continue;
			}

			// Aggregate / dropdown buttons carry their real actions in `items`,
			// with short child labels ("Print", "Download") that only make sense
			// prefixed by the parent label ("Invoice").
			if ( ! empty( $button['items'] ) && is_array( $button['items'] ) ) {
				$parent_label = isset( $button['label'] ) ? (string) $button['label'] : '';
				$exists       = ! empty( $button['exist'] );

				foreach ( $button['items'] as $child ) {
					$item = $this->build_webtoffee_document( $child, $order_id, $parent_label, $exists );

					if ( $item ) {
						$items[] = $item;
					}
				}

				continue;
			}

			$item = $this->build_webtoffee_document( $button, $order_id, '', ! empty( $button['exist'] ) );

			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Build a normalized document entry from a single WebToffee button.
	 *
	 * @param mixed  $button       Button args, keyed by `action` and `label`.
	 * @param int    $order_id     Order ID the document belongs to.
	 * @param string $parent_label Aggregate button label, prefixed onto the child label.
	 * @param bool   $exists       Whether the document has already been generated.
	 *
	 * @return array{label:string, url:string, exists:bool, print:bool, icon:string, half:bool}|null Null when the button is not a usable link.
	 */
	protected function build_webtoffee_document( $button, int $order_id, string $parent_label, bool $exists ) {
		if ( ! is_array( $button ) || empty( $button['action'] ) ) {
			return null;
		}

		$action = (string) $button['action'];
		$label  = isset( $button['label'] ) ? (string) $button['label'] : '';

		if ( '' === $label ) {
			$label = isset( $button['tooltip'] ) ? (string) $button['tooltip'] : __( 'Document', 'storesuite' );
		}

		if ( '' !== $parent_label ) {
			/* translators: 1: document name, e.g. "Invoice". 2: action, e.g. "Download". */
			$label = sprintf( __( '%1$s: %2$s', 'storesuite' ), $parent_label, $label );
		}

		$url = \Wf_Woocommerce_Packing_List_Admin::get_print_url( $order_id, $action );

		if ( empty( $url ) ) {
			return null;
		}

		// `print_*` actions return printable HTML rather than a PDF stream; the
		// frontend script prints them instead of navigating to them.
		$is_print = 0 === strpos( $action, 'print_' );

		return array(
			'label'  => wp_strip_all_tags( $label ),
			'url'    => $url,
			'exists' => $exists,
			'print'  => $is_print,
			'icon'   => $is_print ? 'print' : 'download',
			// WebToffee labels ("Download Packing slip") are too long to pair up.
			'half'   => false,
		);
	}
}
