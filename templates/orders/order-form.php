<?php
/**
 * StoreSuite order add/edit form
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="storesuite-dashboard-order-details">
	<form action="" method="post">
		<div class="row">
			<!-- Left Column -->
			<div class="col-md-8">
				<!-- Products Section: visibility toggles reactively with #order_status via order.js. -->
				<div class="storesuite-card product-serach-for-order-box<?php echo $order->is_editable() ? '' : ' storesuite-hide'; ?>">
					<h3 class="storesuite-card-title"><?php esc_html_e( 'Products', 'storesuite' ); ?></h3>
					<div class="storesuite-card-content">
						<div class="storesuite-form-group search-group">
							<select class="wc-product-search" id="storesuite_product_search" name="item_id" data-allow_clear="true" data-display_stock="true" data-exclude_type="variable" data-placeholder="<?php echo esc_attr__( 'Search for a product&hellip;', 'storesuite' ); ?>"></select>
						</div>
						<div id="search-order-items" class="products-table">
							<table class="storesuite-table storesuite-mb-20">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Product', 'storesuite' ); ?></th>
										<th><?php esc_html_e( 'Quantity', 'storesuite' ); ?></th>
										<th><?php esc_html_e( 'Action', 'storesuite' ); ?></th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
							<button id="add-to-order-items" type="button" class="my-storesuite-button"><?php esc_html_e( 'Add To Order', 'storesuite' ); ?></button>
						</div>
					</div>
				</div>

				<div id="woocommerce-order-items" class="storesuite-card storesuite-order-items-box">
					<div class="inside">
						<?php
						if ( $order->get_item_count() > 0 ) {
							require WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
						}
						?>
					</div>
				</div>

				<!-- Discounts & Fees and Order Summary Section -->
				<div class="order-fee-and-shipping-box<?php echo $order->get_item_count() > 0 ? ' active' : ''; ?>">
					<div class="row">
						<div class="col-md-6">
							<div class="storesuite-card">
								<h3 class="storesuite-card-title"><?php esc_html_e( 'Discounts & Fees', 'storesuite' ); ?></h3>
								<div class="storesuite-card-content">
									<div class="storesuite-form-group">
										<div class="coupon-group">
											<input type="text" class="storesuite-form-control" id="coupon_code" placeholder="<?php echo esc_attr__( 'e.g. SUMMER20', 'storesuite' ); ?>">
											<button type="button" class="apply-btn storesuite-apply-coupon"><?php esc_html_e( 'Apply Coupon', 'storesuite' ); ?></button>
										</div>
									</div>
									<div class="storesuite-form-group">
										<div class="coupon-group">
											<input type="text" class="storesuite-form-control" id="add_fee" placeholder="<?php echo esc_attr__( 'Enter a fixed amount or percentage', 'storesuite' ); ?>">
											<button type="button" class="apply-btn storesuite-add-fee"><?php esc_html_e( 'Add Fee', 'storesuite' ); ?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="storesuite-card">
								<h3 class="storesuite-card-title"><?php esc_html_e( 'Shipping', 'storesuite' ); ?></h3>
								<div class="storesuite-card-content">
									<div class="storesuite-form-group">
										<div class="coupon-group">
											<input type="text" class="shipping_method_title storesuite-form-control" placeholder="<?php esc_attr_e( 'Shipping name', 'storesuite' ); ?>" name="storesuite_shipping_method_title" value="<?php echo esc_attr__( 'Shipping', 'storesuite' ); ?>" />
											<input type="text" name="storesuite_shipping_cost" placeholder="0" class="storesuite-form-control" />
											<select class="shipping_method storesuite-form-control" name="storesuite_shipping_method">
												<optgroup label="<?php esc_attr_e( 'Shipping method', 'storesuite' ); ?>">
													<option value=""><?php esc_html_e( 'N/A', 'storesuite' ); ?></option>
													<?php
													$found_method     = false;
													$shipping_methods = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : array();

													foreach ( $shipping_methods as $method ) {
														echo '<option value="' . esc_attr( $method->id ) . '">' . esc_html( $method->get_method_title() ) . '</option>';
													}

													echo '<option value="other">' . esc_html__( 'Other', 'storesuite' ) . '</option>';
													?>
												</optgroup>
											</select>
											<button type="button" class="apply-btn storesuite-add-shipping"><?php esc_html_e( 'Add Shipping', 'storesuite' ); ?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Billing & Shipping Address Section -->
				<div class="row">
					<div class="col-md-6">
						<div class="storesuite-card customer-address-box <?php echo 'edit' === $context ? 'show-address' : 'hide-address'; ?>">
							<h3 class="storesuite-card-title">
								<?php esc_html_e( 'Billing Address', 'storesuite' ); ?>
								<button class="edit-storesuite-order-address edit-storesuite-order-billing-address"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="m19,0H5C2.243,0,0,2.243,0,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5V5c0-2.757-2.243-5-5-5Zm3,19c0,1.654-1.346,3-3,3H5c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h14c1.654,0,3,1.346,3,3v14ZM13.879,6.379l-6.707,6.707c-.755.755-1.172,1.76-1.172,2.828v1.586c0,.553.448,1,1,1h1.586c1.068,0,2.073-.416,2.828-1.172l6.707-6.707c1.17-1.17,1.17-3.072,0-4.242-1.134-1.133-3.11-1.133-4.243,0Zm-3.879,9.535c-.373.372-.888.586-1.414.586h-.586v-.586c0-.534.208-1.036.586-1.414l4.25-4.25,1.414,1.414-4.25,4.25Zm6.707-6.707l-1.043,1.043-1.414-1.414,1.043-1.043c.377-.379,1.036-.379,1.414,0,.39.39.39,1.024,0,1.414Z"/></svg></button>
							</h3>
							<div class="customer-billing-address">
								<ul>
									<li class="_billing_first_name">
										<strong><?php esc_html_e( 'Full Name', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></span>
									</li>
									<li class="_billing_company">
										<strong><?php esc_html_e( 'Company', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_company() ); ?></span>
									</li>
									<li class="_billing_address_1">
										<strong><?php esc_html_e( 'Address Line 1', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_address_1() ); ?></span>
									</li>
									<li class="_billing_address_2">
										<strong><?php esc_html_e( 'Address Line 2', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_address_2() ); ?></span>
									</li>
									<li class="_billing_city">
										<strong><?php esc_html_e( 'City', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_city() ); ?></span>
									</li>
									<li class="_billing_postcode">
										<strong><?php esc_html_e( 'Postcode / ZIP', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_postcode() ); ?></span>
									</li>
									<li class="_billing_country">
										<strong><?php esc_html_e( 'Country / Region', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_country() ); ?></span>
									</li>
									<li class="_billing_state">
										<strong><?php esc_html_e( 'State / County', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_state() ); ?></span>
									</li>
									<li class="_billing_email">
										<strong><?php esc_html_e( 'Email Address', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_email() ); ?></span>
									</li>
									<li class="_billing_phone">
										<strong><?php esc_html_e( 'Phone', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_billing_phone() ); ?></span>
									</li>
								</ul>
							</div>
							<div class="storesuite-card-content storesuite-billing-address-fields">
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_first_name"><?php esc_html_e( 'First Name', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_first_name" name="_billing_first_name" value="<?php echo esc_attr( $order->get_billing_first_name() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_last_name"><?php esc_html_e( 'Last Name', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_last_name" name="_billing_last_name" value="<?php echo esc_attr( $order->get_billing_last_name() ); ?>">
										</div>
									</div>
								</div>
								<div class="storesuite-form-group">
									<label for="_billing_company"><?php esc_html_e( 'Company', 'storesuite' ); ?></label>
									<input type="text" class="storesuite-form-control" id="_billing_company" name="_billing_company" value="<?php echo esc_attr( $order->get_billing_company() ); ?>">
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_address_1"><?php esc_html_e( 'Address Line 1', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_address_1" name="_billing_address_1" value="<?php echo esc_attr( $order->get_billing_address_1() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_address_2"><?php esc_html_e( 'Address Line 2', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_address_2" name="_billing_address_2" value="<?php echo esc_attr( $order->get_billing_address_2() ); ?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_city"><?php esc_html_e( 'City', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_city" name="_billing_city" value="<?php echo esc_attr( $order->get_billing_city() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_postcode"><?php esc_html_e( 'Postcode / ZIP', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_postcode" name="_billing_postcode" value="<?php echo esc_attr( $order->get_billing_postcode() ); ?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_country"><?php esc_html_e( 'Country / Region', 'storesuite' ); ?></label>
											<select class="storesuite-form-control js_field-country" id="_billing_country" name="_billing_country">
												<option value=""><?php esc_html_e( 'Select a country...', 'storesuite' ); ?></option>
												<?php
													$countries                = WC()->countries->get_countries();
													$selected_billing_country = $order ? $order->get_billing_country() : '';

												foreach ( $countries as $code => $name ) {
													printf(
														'<option value="%s" %s>%s</option>',
														esc_attr( $code ),
														selected( $selected_billing_country, $code, false ),
														esc_html( $name )
													);
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_state"><?php esc_html_e( 'State / County', 'storesuite' ); ?></label>
											<?php
											$billing_state = $order ? $order->get_billing_state() : '';
											$states        = WC()->countries->get_states( $selected_billing_country );

											if ( ! empty( $states ) ) {
												?>
												<select class="storesuite-form-control js_field-state" id="_billing_state" name="_billing_state">
													<option value=""><?php esc_html_e( 'Select a state...', 'storesuite' ); ?></option>
													<?php
													foreach ( $states as $code => $name ) {
														printf(
															'<option value="%s" %s>%s</option>',
															esc_attr( $code ),
															selected( $billing_state, $code, false ),
															esc_html( $name )
														);
													}
													?>
												</select>
												<?php
											} else {
												?>
												<input 
													type="text" 
													class="storesuite-form-control js_field-state" 
													id="_billing_state" 
													name="_billing_state" 
													value="<?php echo esc_attr( $billing_state ); ?>"
													placeholder="<?php esc_attr_e( 'State / County', 'storesuite' ); ?>"
												/>
												<?php
											}
											?>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_email"><?php esc_html_e( 'Email Address', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_email" name="_billing_email" value="<?php echo esc_attr( $order->get_billing_email() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_billing_phone"><?php esc_html_e( 'Phone', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_billing_phone" name="_billing_phone" value="<?php echo esc_attr( $order->get_billing_phone() ); ?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_payment_method"><?php esc_html_e( 'Payment Method', 'storesuite' ); ?></label>
											<select name="_payment_method" id="_payment_method" class=" storesuite-form-control">
												<option value=""><?php esc_html_e( 'N/A', 'storesuite' ); ?></option>
												<?php
												if ( WC()->payment_gateways() ) {
													$payment_gateways = WC()->payment_gateways->payment_gateways();
												} else {
													$payment_gateways = array();
												}
												$payment_method = $order->get_payment_method();
												$found_method   = false;

												foreach ( $payment_gateways as $gateway ) {
													if ( 'yes' === $gateway->enabled ) {
														echo '<option value="' . esc_attr( $gateway->id ) . '" ' . selected( $payment_method, $gateway->id, false ) . '>' . esc_html( $gateway->get_title() ) . '</option>';
														if ( $payment_method === $gateway->id ) {
															$found_method = true;
														}
													}
												}

												if ( ! $found_method && ! empty( $payment_method ) ) {
													echo '<option value="' . esc_attr( $payment_method ) . '" selected="selected">' . esc_html__( 'Other', 'storesuite' ) . '</option>';
												} else {
													echo '<option value="other">' . esc_html__( 'Other', 'storesuite' ) . '</option>';
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_transaction_id"><?php esc_html_e( 'Transaction ID', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_transaction_id" name="_transaction_id" value="<?php echo esc_attr( $order->get_transaction_id() ); ?>">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="storesuite-card customer-address-box <?php echo 'edit' === $context ? 'show-address' : 'hide-address'; ?>">
							<h3 class="storesuite-card-title">
								<?php esc_html_e( 'Shipping Address', 'storesuite' ); ?>
								<button class="edit-storesuite-order-address edit-storesuite-order-shipping-address"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="m19,0H5C2.243,0,0,2.243,0,5v14c0,2.757,2.243,5,5,5h14c2.757,0,5-2.243,5-5V5c0-2.757-2.243-5-5-5Zm3,19c0,1.654-1.346,3-3,3H5c-1.654,0-3-1.346-3-3V5c0-1.654,1.346-3,3-3h14c1.654,0,3,1.346,3,3v14ZM13.879,6.379l-6.707,6.707c-.755.755-1.172,1.76-1.172,2.828v1.586c0,.553.448,1,1,1h1.586c1.068,0,2.073-.416,2.828-1.172l6.707-6.707c1.17-1.17,1.17-3.072,0-4.242-1.134-1.133-3.11-1.133-4.243,0Zm-3.879,9.535c-.373.372-.888.586-1.414.586h-.586v-.586c0-.534.208-1.036.586-1.414l4.25-4.25,1.414,1.414-4.25,4.25Zm6.707-6.707l-1.043,1.043-1.414-1.414,1.043-1.043c.377-.379,1.036-.379,1.414,0,.39.39.39,1.024,0,1.414Z"/></svg></button>
							</h3>
							<div class="customer-shipping-address">
								<ul>
									<li class="_shipping_first_name">
										<strong><?php esc_html_e( 'Full Name', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ); ?></span>
									</li>
									<li class="_shipping_company">
										<strong><?php esc_html_e( 'Company', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_company() ); ?></span>
									</li>
									<li class="_shipping_address_1">
										<strong><?php esc_html_e( 'Address Line 1', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_address_1() ); ?></span>
									</li>
									<li class="_shipping_address_2">
										<strong><?php esc_html_e( 'Address Line 2', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_address_2() ); ?></span>
									</li>
									<li class="_shipping_city">
										<strong><?php esc_html_e( 'City', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_city() ); ?></span>
									</li>
									<li class="_shipping_postcode">
										<strong><?php esc_html_e( 'Postcode / ZIP', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_postcode() ); ?></span>
									</li>
									<li class="_shipping_country">
										<strong><?php esc_html_e( 'Country / Region', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_country() ); ?></span>
									</li>
									<li class="_shipping_state">
										<strong><?php esc_html_e( 'State / County', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_state() ); ?></span>
									</li>
									<li class="_shipping_phone">
										<strong><?php esc_html_e( 'Phone', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_shipping_phone() ); ?></span>
									</li>
									<li class="customer_note">
										<strong><?php esc_html_e( 'Note', 'storesuite' ); ?>:</strong>
										<span><?php echo esc_html( $order->get_customer_note() ); ?></span>
									</li>
								</ul>
							</div>
							<div class="storesuite-card-content storesuite-shipping-address-fields">
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_first_name"><?php esc_html_e( 'First Name', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_first_name" name="_shipping_first_name" value="<?php echo esc_attr( $order->get_shipping_first_name() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_last_name"><?php esc_html_e( 'Last Name', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_last_name" name="_shipping_last_name" value="<?php echo esc_attr( $order->get_shipping_last_name() ); ?>">
										</div>
									</div>
								</div>
								<div class="storesuite-form-group">
									<label for="_shipping_company"><?php esc_html_e( 'Company', 'storesuite' ); ?></label>
									<input type="text" class="storesuite-form-control" id="_shipping_company" name="_shipping_company" value="<?php echo esc_attr( $order->get_shipping_company() ); ?>">
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_address_1"><?php esc_html_e( 'Address Line 1', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_address_1" name="_shipping_address_1" value="<?php echo esc_attr( $order->get_shipping_address_1() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_address_2"><?php esc_html_e( 'Address Line 2', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_address_2" name="_shipping_address_2" value="<?php echo esc_attr( $order->get_shipping_address_2() ); ?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_city"><?php esc_html_e( 'City', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_city" name="_shipping_city" value="<?php echo esc_attr( $order->get_shipping_city() ); ?>">
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_postcode"><?php esc_html_e( 'Postcode / ZIP', 'storesuite' ); ?></label>
											<input type="text" class="storesuite-form-control" id="_shipping_postcode" name="_shipping_postcode" value="<?php echo esc_attr( $order->get_shipping_postcode() ); ?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_country"><?php esc_html_e( 'Country / Region', 'storesuite' ); ?></label>
											<select class="storesuite-form-control js_field-country" id="_shipping_country" name="_shipping_country">
											<option value=""><?php esc_html_e( 'Select a country...', 'storesuite' ); ?></option>
												<?php
													$selected_shipping_country = $order ? $order->get_shipping_country() : '';

												foreach ( $countries as $code => $name ) {
													printf(
														'<option value="%s" %s>%s</option>',
														esc_attr( $code ),
														selected( $selected_shipping_country, $code, false ),
														esc_html( $name )
													);
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="storesuite-form-group">
											<label for="_shipping_state"><?php esc_html_e( 'State / County', 'storesuite' ); ?></label>
											<?php
											$shipping_state  = $order ? $order->get_shipping_state() : '';
											$shipping_states = WC()->countries->get_states( $selected_shipping_country );

											if ( ! empty( $shipping_states ) ) {
												?>
												<select class="storesuite-form-control js_field-state" id="_shipping_state" name="_shipping_state">
													<option value=""><?php esc_html_e( 'Select a state...', 'storesuite' ); ?></option>
													<?php
													foreach ( $shipping_states as $code => $name ) {
														printf(
															'<option value="%s" %s>%s</option>',
															esc_attr( $code ),
															selected( $shipping_state, $code, false ),
															esc_html( $name )
														);
													}
													?>
												</select>
												<?php
											} else {
												?>
												<input 
													type="text" 
													class="storesuite-form-control js_field-state" 
													id="_shipping_state" 
													name="_shipping_state" 
													value="<?php echo esc_attr( $shipping_state ); ?>"
													placeholder="<?php esc_attr_e( 'State / County', 'storesuite' ); ?>"
												/>
												<?php
											}
											?>
										</div>
									</div>
								</div>
								<div class="storesuite-form-group">
									<label for="_shipping_phone"><?php esc_html_e( 'Phone', 'storesuite' ); ?></label>
									<input type="text" class="storesuite-form-control" id="_shipping_phone" name="_shipping_phone" value="<?php echo esc_attr( $order->get_shipping_phone() ); ?>">
								</div>
								<?php
								if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) {
									?>
								<div class="storesuite-form-group">
									<label for="customer_note"><?php esc_html_e( 'Customer Provided Note', 'storesuite' ); ?></label>
									<textarea rows="3" cols="40" class="storesuite-form-control" name="customer_note" tabindex="6" id="customer_note" placeholder="<?php esc_attr_e( 'Customer notes about the order', 'storesuite' ); ?>"><?php echo wp_kses( $order->get_customer_note(), array( 'br' => array() ) ); ?></textarea>
								</div>
								<?php } ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Right Column -->
			<div class="col-md-4">
				<!-- General Section -->
				<div class="storesuite-card">
					<h3 class="storesuite-card-title"><?php esc_html_e( 'General', 'storesuite' ); ?></h3>
					<div class="storesuite-card-content">
						<div class="storesuite-form-group search-group">
							<?php
							$user_id     = $order->get_customer_id();
							$user_string = '';
							if ( $user_id ) {
								$user = get_user_by( 'id', $user_id );
								if ( $user ) {
									$user_string = sprintf(
										/* translators: 1: user display name 2: user ID 3: user email */
										esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'storesuite' ),
										$user->display_name,
										$user->ID,
										$user->user_email
									);
								}
							}
							?>
							<label for="date_created"><?php esc_html_e( 'Customer', 'storesuite' ); ?></label>
							<select class="wc-customer-search" id="customer_user" name="customer_user" data-placeholder="<?php esc_attr_e( 'Guest', 'storesuite' ); ?>" data-allow_clear="true">
								<option value="<?php echo $user_id ? esc_attr( $user_id ) : ''; ?>"><?php echo $user_id ? esc_html( htmlspecialchars( wp_kses_post( $user_string ) ) ) : esc_html__( 'Guest', 'storesuite' ); ?></option>
							</select>
						</div>
						<div class="storesuite-form-group">
							<label for="date_created"><?php esc_html_e( 'Date Created', 'storesuite' ); ?></label>
							<div class="date-time-group">
								<?php
								$order_date_created_localised = ! is_null( $order->get_date_created() ) ? $order->get_date_created()->getOffsetTimestamp() : '';
								?>
								<input type="text" class="date-picker storesuite-form-control date-input" name="order_date" maxlength="10" autocomplete="off" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'storesuite' ); ?>" value="<?php echo esc_attr( date_i18n( 'Y-m-d', $order_date_created_localised ) ); ?>" pattern="<?php echo esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment ?>" />@
								&lrm;
								<input type="number" class="hour storesuite-form-control time-input" placeholder="<?php esc_attr_e( 'h', 'storesuite' ); ?>" name="order_date_hour" min="0" max="23" step="1" value="<?php echo esc_attr( date_i18n( 'H', $order_date_created_localised ) ); ?>" pattern="([01]?[0-9]{1}|2[0-3]{1})" />:
								<input type="number" class="minute storesuite-form-control time-input" placeholder="<?php esc_attr_e( 'm', 'storesuite' ); ?>" name="order_date_minute" min="0" max="59" step="1" value="<?php echo esc_attr( date_i18n( 'i', $order_date_created_localised ) ); ?>" pattern="[0-5]{1}[0-9]{1}" />
								<input type="hidden" name="order_date_second" value="<?php echo esc_attr( date_i18n( 's', $order_date_created_localised ) ); ?>" />
							</div>
						</div>
						
						<div class="storesuite-form-group">
							<label for="order_status"><?php esc_html_e( 'Status', 'storesuite' ); ?></label>
							<select class="storesuite-form-control" id="order_status" name="order_status">
								<?php
									$statuses = wc_get_order_statuses();
								foreach ( $statuses as $status => $status_name ) {
									echo '<option value="' . esc_attr( $status ) . '" ' . selected( $status, 'wc-' . $order->get_status(), false ) . '>' . esc_html( $status_name ) . '</option>';
								}
								?>
							</select>
						</div>
						<div class="storesuite-form-group">
							<?php
							$order_id      = $order->get_id();
							$order_actions = PluginizeLab\StoreSuite\Order\OrderManager::get_available_order_actions_for_order( $order );
							?>
							<label for="order_action"><?php esc_html_e( 'Order Actions', 'storesuite' ); ?></label>
							<select class="storesuite-form-control" id="order_action" name="order_action">
								<option value=""><?php esc_html_e( 'Choose an action...', 'storesuite' ); ?></option>
								<?php foreach ( $order_actions as $order_action => $order_action_title ) { ?>
									<option value="<?php echo esc_attr( $order_action ); ?>"><?php echo esc_html( $order_action_title ); ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
				</div>
				<?php
					/**
					 * Action hook fired after the order details action.
					 *
					 * @param WC_Order $order Order data.
					 */
					do_action( 'storesuite_after_new_order_actions', $order );
				?>

				<!-- Action Buttons -->
				<div class="action-buttons">
					<input type="hidden" name="context" id="context" value="<?php echo esc_attr( $context ); ?>">
					<button type="submit" class="create-order-btn"><?php echo ( 'auto-draft' === $order->get_status() ) ? esc_html__( 'Create Order', 'storesuite' ) : esc_html__( 'Update Order', 'storesuite' ); ?></button>
				</div>

				<!-- Order Notes Section -->
				<div id="new_order_notes" class="storesuite-card">
					<h3 class="storesuite-card-title"><?php esc_html_e( 'Order notes', 'storesuite' ); ?></h3>
					<div class="storesuite-card-content">
						<!-- Existing Notes -->
						<ul class="existing-notes order_notes">
							<?php
							$existing_notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
							if ( $existing_notes ) {
								foreach ( $existing_notes as $note ) {
									$is_customer_note = get_comment_meta( $note->id, 'is_customer_note', true );
									$note_classes     = array( 'note' );
									$note_classes[]   = $is_customer_note ? 'customer-note' : '';
									$note_classes     = apply_filters( 'woocommerce_order_note_class', array_filter( $note_classes ), $note );
									?>
									<li rel="<?php echo absint( $note->id ); ?>" data-id="<?php echo absint( $note->id ); ?>" class="<?php echo esc_attr( implode( ' ', $note_classes ) ); ?>">
										<div class="note_content">
										<?php echo wp_kses_post( wpautop( wptexturize( make_clickable( $note->content ) ) ) ); ?>
										</div>
										<p class="meta">
											<abbr class="exact-date" title="<?php echo esc_attr( $note->date_created->date( 'Y-m-d H:i:s' ) ); ?>">
											<?php
											printf(
												/* translators: $1: Date created, $2 Time created */
												esc_html__( '%1$s at %2$s', 'storesuite' ),
												esc_html( $note->date_created->date_i18n( wc_date_format() ) ),
												esc_html( $note->date_created->date_i18n( wc_time_format() ) )
											);
											?>
											</abbr>
												<?php
												if ( 'system' !== $note->added_by ) :
													/* translators: %s: note author */
													printf( ' ' . esc_html__( 'by %s', 'storesuite' ), esc_html( $note->added_by ) );
												endif;
												?>
											<a href="#" class="delete_note" x-on:click.prevent="handleDeleteNote" role="button"><?php esc_html_e( 'Delete note', 'storesuite' ); ?></a>
										</p>
									</li>
									<?php
								}
							} else {
								?>
								<li class="note no-items">
									<div class="note_content">
										<p><?php esc_html_e( 'There are no notes yet.', 'storesuite' ); ?></p>
									</div>
								</li>
								<?php
							}
							?>
						</ul>
						
						<!-- Add Note Section -->
						<div class="add-note-section">
							<div class="add-note-header">
								<h4 class="add-note-title"><?php esc_html_e( 'Add Note', 'storesuite' ); ?></h4>
							</div>
							<div class="storesuite-form-group">
								<textarea id="add_order_note" class="storesuite-form-control note-textarea" placeholder="<?php echo esc_attr__( 'Enter your note here...', 'storesuite' ); ?>" rows="4"></textarea>
								<small><?php esc_html_e( 'Add a note for your reference, or add a customer note (the user will be notified).', 'storesuite' ); ?></small>
							</div>
							<div class="note-options">
								<div class="storesuite-form-group">
									<select class="storesuite-form-control" id="order_note_type">
										<option><?php esc_html_e( 'Internal note', 'storesuite' ); ?></option>
										<option value="customer"><?php esc_html_e( 'Note to customer', 'storesuite' ); ?></option>
									</select>
								</div>
								<button type="button" class="add-note add-note-btn"><?php esc_html_e( 'Add', 'storesuite' ); ?></button>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>
		<input type="hidden" id="post_ID" name="post_ID" value="<?php echo esc_attr( $order->get_id() ); ?>">
	</form>
</div>