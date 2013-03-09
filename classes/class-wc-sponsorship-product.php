<?php

/**
 * Sponsorship Product Class
 * 
 * The WooCommerce Sponsorship project product class handles individual project 
 * product data.
 *
 * @class 		WC_Sponsorship_Product
 * @package		WooCommerce Sponsorship
 * @category	Class
 * @author		Justin Kussow
 */
class WC_Sponsorship_Product {

	function __construct() {
		if ( is_admin() ) {
			add_action( 'woocommerce_product_write_panel_tabs', array( &$this, 'product_write_panel_tab' ) );
			add_action( 'woocommerce_product_write_panels', array( &$this, 'product_write_panel' ) );

			add_filter( 'woocommerce_process_product_meta_sponsorship-project', array( &$this, 'product_save_data' ) );
			add_filter( 'product_type_selector', array( &$this, 'add_product_type' ), 10, 3 );
		} else {
			add_action( 'wp_head', array( &$this, 'product_frontend_styling' ) );
			// add_action( 'woocommerce_sponsorship-project_add_to_cart', array( &$this, 'add_sponsorship_project_to_cart' ) );
			add_action( 'woocommerce_before_add_to_cart_form', array( &$this, 'before_add_to_cart' ) );
			add_action( 'woocommerce_after_add_to_cart_form', array( &$this, 'after_add_to_cart' ) );
			
			add_filter( 'woocommerce_product_is_visible', array( &$this, 'filter_product_visibility' ), 10, 3 );
			add_filter( 'woocommerce_add_to_cart_handler', array( &$this, 'filter_add_to_cart_handler' ), 10, 2);
		}

		add_filter( 'woocommerce_get_price_html', array( &$this, 'product_price_html' ), 10, 2 );
		add_filter( 'woocommerce_price_html', array( &$this, 'product_price_html' ), 10, 2 );
		add_filter( 'woocommerce_product_class', array( &$this, 'filter_product_class' ), 10, 4 );
	}

	function add_product_type( $var, $attr, $content = null ) {
		$var[ 'sponsorship-project' ] = 'Sponsorship Project';
		return $var;
	}
		
	function filter_add_to_cart_handler( $type, $product ) {
		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $product );
		if ( !WC_Sponsorship::is_sponsorship( $product ) ) return $type;
		return 'variable';
	}

	function filter_product_class( $classname, $product_type, $post_type, $product_id ) {
		if ( WC_Sponsorship::is_sponsorship( $product_id ) ) {
			return 'variable';
		}
		return $classname;
		//return (WC_Sponsorship::is_sponsorship( $product_id ) ? 'variable' : $classname );
	}
	
	function before_add_to_cart() {
		echo '<div style="display: none;">';
	}
	
	function after_add_to_cart() {
		echo '</div>';
	}
	
	/**
	 * adds a new tab to the product interface
	 */
	function product_write_panel_tab() {
		?>
		<li class="sponsorship_tab show_if_sponsorship"><a href="#sponsorship_data">Sponsorship Project</a></li>
		<?php
	}

	/**
	 * adds the panel to the product interface
	 */
	function product_write_panel() {
		global $post, $woocommerce;

		$data = get_post_meta( $post->ID, '_sponsorship', true );
		$is_new = false;

		if ( !$data ) {
			$is_new = true;
			
			if ( !isset( $data[ 'end' ] ) ) $data[ 'end' ] = array();
			if ( !isset( $data[ 'end' ][ 'type' ] ) ) $data[ 'end' ][ 'type' ] = 'duration';
			if ( !isset( $data[ 'end' ][ 'days' ] ) ) $data[ 'end' ][ 'days' ] = 0;
			if ( !isset( $data[ 'end' ][ 'date' ] ) ) $data[ 'end' ][ 'date' ] = '';
			if ( !isset( $data[ 'goal' ] ) ) $data[ 'goal' ] = 0;
			if ( !isset( $data[ 'end' ][ 'time' ] ) ) $data[ 'end' ][ 'time' ] = '';
		}

		$progress = $progress_percent = 0;
		if ( $data && isset( $data[ 'progress' ] ) ) {
			$progress = $data[ 'progress' ];

			if ( $data[ 'progress' ] > $data[ 'goal' ] ) {
				$progress_percent = 100;
			} else if ( $data[ 'goal' ] > 0 ) {
				$progress_percent = round( $data[ 'progress' ] / $data[ 'goal' ] * 100 );
			}
		}

		$cancel = get_post_meta( $post->ID, '_sponsorship_cancel', true );
		$complete = get_post_meta( $post->ID, '_sponsorship_complete', true );
		?>
		<div id="sponsorship_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field sponsorship_goal_field <?php echo (!$is_new ? 'nomarginbottom' : ''); ?>">
					<label for="_sponsorship_goal">Goal ($)</label>
					<?php if ( $is_new ) : ?>
						<input type="text" placeholder="0.00" value="<?php echo $data[ 'goal' ]; ?>" id="_sponsorship_goal" name="_sponsorship[goal]" class="short" />
						<span class="description">The amount needed to complete your project.</span>
					<?php elseif ( $complete ) : ?>
						<em>The project goal has been met!</em>
						<input type="hidden" value="<?php echo $data[ 'goal' ]; ?>" id="_sponsorship_goal" name="_sponsorship[goal]" />
					<?php elseif ( $cancel ) : ?>
						<em>The project goal was not met by the deadline of <?php echo $data[ 'end' ][ 'date' ]; ?>.</em>
						<input type="hidden" value="<?php echo $data[ 'goal' ]; ?>" id="_sponsorship_goal" name="_sponsorship[goal]" />
					<?php else : ?>
						<input type="text" value="<?php echo ( $data[ 'goal' ] ? $data[ 'goal' ] : '0' ); ?>" id="_sponsorship_goal_disp" class="short" <?php echo (!$is_new ? 'disabled="disabled"' : ''); ?> />
						<input type="hidden" value="<?php echo $data[ 'goal' ]; ?>" id="_sponsorship_goal" name="_sponsorship[goal]" />
						<span class="description">The amount needed to complete your project.</span>
					<?php endif; ?>				
				</p>
				<div class="form-field sponsorship_goal_progress_field" style="<?php echo $is_new ? 'display:none;' : ''; ?>">
					<div id="sponsorship-progress">
						<div id="sponsorship-progress-percent" style="width: <?php echo $progress_percent; ?>%;"></div>
					</div>
					<span class="description sponsorship-progress-desc">
						$<?php echo $progress; ?> contributed
					</span>
				</div>
			</div>
			<div class="options_group">
				<?php if ( $is_new ) : ?>
					<p class="form-field sponsorship_duration_field nomarginbottom">
						<label for="_sponsorship_duration">Duration</label>
						<input type="radio" id="_sponsorship_end1" name="_sponsorship[end][type]" class="checkbox wc-sponsorship-radio" value="duration" <?php echo ($data[ 'end' ][ 'type' ] == 'duration' ? 'checked="checked"' : ''); ?>> <span class="description">Number of days</span>
					</p>
					<p style="<?php echo ($data[ 'end' ][ 'type' ] == 'datetime' ? 'display: none;' : ''); ?>" class="form-field duration_day_field nomargintop nomarginbottom">
						<input type="text" class="nolabel short" id="_sponsorship_end_duration" name="_sponsorship[end][days]" value="<?php echo $data[ 'end' ][ 'days' ]; ?>" />
						<span class="description">1-60 days</span>
					</p>
					<p class="form-field sponsorhip_enddate_field nomargintop">
						<input type="radio" id="_sponsorship_end2" name="_sponsorship[end][type]" class="checkbox nolabel wc-sponsorship-radio" value="datetime" <?php echo ($data[ 'end' ][ 'type' ] == 'datetime' ? 'checked="checked"' : ''); ?>> <span class="description">End on date & time</span>
					</p>
					<div style="<?php echo ($data[ 'end' ][ 'type' ] == 'duration' ? 'display: none;' : ''); ?>" class="sp_end_datetime_field">
						<p class="form-field nomargintop nomarginbottom">
						<div id="_sponsorship_end_date" class="nolabel"></div>
						<input type="hidden" id="_sponsorship_end_date_value" name="_sponsorship[end][date]" value="<?php echo $data[ 'end' ][ 'date' ]; ?>" />
						</p>
						<p class="form-field nomargintop nomarginbottom" style="display:none;">
							<label for="_sponsorship_time" class="nolabel autowidth">Time</label>
							<input type="text" id="_sponsorship_end_time" name="_sponsorship[end][time]" class="short" value="<?php echo $data[ 'end' ][ 'time' ]; ?>" />
						</p>
					</div>
				<?php elseif ( !$cancel && !$complete ) : ?>
					<p class="form-field sponsorship_enddate_field">
						<label for="_sponsorship_end_datetime">End Date</label>
						<span id="_sponsorship_end_date" style="float: left;"></span>
						<input type="hidden" id="_sponsorship_end_date_value" name="_sponsorship[end][date]" value="<?php echo $data[ 'end' ][ 'date' ]; ?>" />
						<input type="hidden" id="_sponsorship_end_type" name="_sponsorship[end][type]" value="datetime" />
					</p>
				<?php endif; ?>
			</div>
			<div class="options_group sponsorship_levels_options_group">
				<div class="form-field sponsorship_levels_field wc-sponsorship-fix">
					<?php
					$args = array(
						'post_type' => 'product_variation',
						'post_status' => array( 'private', 'publish' ),
						'numberposts' => -1,
						'orderby' => 'id',
						'order' => 'asc',
						'post_parent' => $post->ID
					);
					$levels = get_posts( $args );
					?>
					<label>Contribution Levels</label>
					<input type="hidden" id="_sponsorship_levels_count" value="<?php echo count( $levels ); ?>" />
					<ul id="sponsorship-levels" class="levels">
						<?php
						$loop = 0;
						if ( $levels ) foreach ( $levels as $level ) :
								$level_data = get_post_custom( $level->ID );
								?>
								<li id="level-<?php echo $loop; ?>" class="level">
									<input type="hidden" id="_level_<?php echo $loop; ?>_deleted" name="_sponsorship[levels][<?php echo $loop; ?>][_deleted]" value="0" />
									<input type="hidden" id="_level_<?php echo $loop; ?>_id" name="_sponsorship[levels][<?php echo $loop; ?>][id]" value="<?php echo $level->ID; ?>" />
									<table>
										<thead>
											<tr>
												<th class="level-title" colspan="2">
													<input class="level-text" type="text" value="<?php echo get_the_title( $level->ID ); ?>" placeholder="Title" id="_level_<?php echo $loop; ?>_title" name="_sponsorship[levels][<?php echo $loop; ?>][title]" />
													<span class="level-id">ID: <?php echo $level->ID; ?></span>
												</th>
											</tr>
											<tr>
												<th class="left">
													<label for="_level_<?php echo $loop; ?>_amount">Amount ($)</label>
												</th>
												<th>
													<label for="_level_<?php echo $loop; ?>_desc">Description</label>
												</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="left">
													<input class="level-text" type="text" value="<?php if ( isset( $level_data[ '_price' ][ 0 ] ) ) echo $level_data[ '_price' ][ 0 ]; ?>" id="_level_<?php echo $loop; ?>_amount" name="_sponsorship[levels][<?php echo $loop; ?>][amount]" />
													<div class="level-submit">
														<input id="level_<?php echo $loop; ?>_delete" class="level-delete-button button" type="submit" value="Delete" name="_sponsorship[levels][<?php echo $loop; ?>][delete]" rel="<?php echo $loop; ?>" />
													</div>
												</td>
												<td>
													<textarea class="level-textarea" cols="25" id="_level_<?php echo $loop; ?>_desc" name="_sponsorship[levels][<?php echo $loop; ?>][desc]"><?php echo $level->post_content; ?></textarea>
												</td>
											</tr>									
										</tbody>
									</table>
								</li>
								<?php
								++$loop;
							endforeach;
						?>						
						<li id="new-level" class="level" style="display:none;">
							<input type="hidden" id="_level_new_deleted" name="_sponsorship[levels][new][_deleted]" value="0" />
							<input type="hidden" id="_level_new_id" name="_sponsorship[levels][new][id]" value="" />
							<table>
								<thead>
									<tr>
										<th class="level-title" colspan="2">
											<input class="level-text" type="text" value="" placeholder="Title" id="_level_new_title" name="_sponsorship[levels][new][title]" />
											</td>
									</tr>
									<tr>
										<th class="left">
											<label for="_level_new_amount">Amount ($)</label>
										</th>
										<th>
											<label for="_level_new_desc">Description</label>
										</th>
									</tr>									
								</thead>
								<tbody>
									<tr>
										<td class="left">
											<input class="level-text" type="text" value="" placeholder="0.00" id="_level_new_amount" name="_sponsorship[levels][new][amount]" />
											<div class="level-submit">
												<input id="level_new_delete" class="level-delete-button button" type="submit" value="Delete" name="_sponsorship[levels][new][delete]" rel="new" />
											</div>
										</td>
										<td>

											<textarea class="level-textarea" cols="25" id="_level_new_desc" name="_sponsorship[levels][new][desc]"></textarea>
										</td>
									</tr>									
								</tbody>
							</table>
						</li>
						<li class="level add-level">
							<a id="sponsorship_level_add" class="add-level-link">
								<div class="add-level-button">
									<strong>
										<span class="icon-add icon"></span>
										Add another contribution level
									</strong>
								</div>
							</a>
						</li>
					</ul>					
				</div>
			</div>
		</div>
		<?php
		ob_start();
		?>
		jQuery(function(){
			jQuery('select#product-type').change(function() {
				jQuery('.show_if_sponsorship').hide();

				if ( jQuery('select#product-type').val() == 'sponsorship-project' ) {
					jQuery('.show_if_sponsorship').show();
				} else {
					if ( jQuery('.sponsorshp_project_tab').is('.active') ) jQuery('ul.tabs li:visible').eq(0).find('a').click();
				}
			}).change();

			jQuery('input:radio.wc-sponsorship-radio').change(function() {
				jQuery('._duration_day_field').hide();
				jQuery('._end_datetime_field').hide();

				if ( jQuery(this).attr('id') == '_sponsorship_end1' ) {
					jQuery('._duration_day_field').show();
				} else if ( jQuery(this).attr('id') == '_sponsorship_end2' ) {
					jQuery('.sp_end_datetime_field').show();
				}
			});

			if ( jQuery('#_sponsorship_end_date').length > 0 ) {
				jQuery('#_sponsorship_end_date').datepicker({
					showButtonPanel: true,
					onSelect: function(dateText, inst) {
						jQuery('#_sponsorship_end_date_value').val(jQuery(this).val());
					}
				});

				jQuery('#_sponsorship_end_date').datepicker('setDate', jQuery('#_sponsorship_end_date_value').val());				
			}

			function wireUpLevelDeleteButtons() {
				jQuery('.level-delete-button').click(deleteLevel);
			}

			function deleteLevel() {
				var level = jQuery(this).attr('rel');
				jQuery('#level-' + level).hide();
				jQuery('#_level_' + level + '_deleted').val(1);
				return false;
			}

			jQuery('#sponsorship_level_add').click(function() {
				var newLevel = jQuery('#new-level').clone();
				var next = parseInt(jQuery('#_sponsorship_levels_count').val());
				jQuery('#_sponsorship_levels_count').val(next + 1);
				newLevel.attr('id', 'level-' + next);
				var cleanHtml = newLevel.html().replace(/new/g, next);
				console.log('cleaned html = ' + cleanHtml);
				newLevel.html(cleanHtml);
				jQuery('#new-level').before(newLevel);
				newLevel.show();
				wireUpLevelDeleteButtons();
			});			

			// want project end to be set to duration to start
			wireUpLevelDeleteButtons();
		});
		<?php
		$javascript = ob_get_clean();
		$woocommerce->add_inline_js( $javascript );
	}

	/**
	 * saves the data inputed into the product boxes into a serialized array
	 */
	function product_save_data() {
		global $post;

		// get data from POST
		$sponsorship = $_POST[ '_sponsorship' ];

		// don't want to save the levels to the meta data, so pull out of
		// _sponsorship collect and clean up
		$levels = $sponsorship[ 'levels' ];
		if ( $levels[ 'new' ] ) unset( $levels[ 'new' ] );

		// update parent (Project) posts meta data		
		if ( $sponsorship && is_array( $sponsorship ) ) {
			unset( $sponsorship[ 'levels' ] );

			if ( $sponsorship[ 'end' ][ 'type' ] == 'duration' ) {
				$days = $sponsorship[ 'end' ][ 'days' ];
				if ( $days == 1 ) {
					$days = " +1 day";
				} else {
					$days = " +" . $days . " days";
				}

				$calcDate = new DateTime( $days );
				$date = $calcDate->format( 'm/d/Y' );
			} else {
				$date = $sponsorship[ 'end' ][ 'date' ];
			}
			unset( $sponsorship[ 'end' ] );
			$sponsorship[ 'end' ] = array( 'date' => $date );

			$existing = get_post_meta( $post->ID, '_sponsorship', true );
			if ( $existing ) {
				$sponsorship = array_merge( $existing, $sponsorship );
			}
			update_post_meta( $post->ID, '_sponsorship', $sponsorship );
		}

		// process levels (insert children posts, etc)
		if ( $levels ) {
			foreach ( $levels as $key => $level ) {
				if ( isset( $level[ '_deleted' ] ) && "1" == $level[ '_deleted' ] ) {
					if ( $level[ 'id' ] ) {
						// delete the post for this level
						$level_id = ( int ) $level[ 'id' ];
						wp_delete_post( $level_id );
					}
				} else {
					if ( $level[ 'id' ] ) {
						$level_id = ( int ) $level[ 'id' ];

						$level_post = array(
							'ID' => $level_id,
							'post_content' => esc_attr( $level[ 'desc' ] ),
							'post_title' => esc_attr( $level[ 'title' ] ),
							'post_status' => $post->post_status
						);

						// update the post for this level
						wp_update_post( $level_post );
					} else {
						// insert a post for this level

						$level_post = array(
							'post_title' => esc_attr( $level[ 'title' ] ),
							'post_content' => esc_attr( $level[ 'desc' ] ),
							'post_status' => 'publish',
							'post_author' => get_current_user_id(),
							'post_parent' => $post->ID,
							'post_type' => 'product_variation',
						);
						$level_id = wp_insert_post( $level_post );
					}

					// Update post meta
					update_post_meta( $level_id, '_price', esc_attr( $level[ 'amount' ] ) );
					update_post_meta( $level_id, '_stock', 1 );
					update_post_meta( $level_id, '_virtual', 'yes' );
					update_post_meta( $level_id, '_downloadable', 'no' );
				}
			}

			// update parent so price sorting works and stays in sycn with lowest level
			// based on logic in WooCommerce writepanel-product-type-variable.php
			$post_parent = $post->ID;

			$children = get_posts( array(
				'post_parent' => $post_parent,
				'posts_per_page' => -1,
				'post_type' => 'product_variation',
				'fields' => 'ids',
				'post_status' => 'publish'
					) );

			$lowest_price = '';
			$highest_price = '';

			if ( $children ) {
				foreach ( $children as $child ) {
					$child_price = get_post_meta( $child, '_price', true );

					// Low price
					if ( !$lowest_price || $child_price < $lowest_price ) $lowest_price = $child_price;

					// High price
					if ( !$highest_price || $child_price > $highest_price ) $highest_price = $child_price;
				}
			}
		}

		update_post_meta( $post_parent, '_price', $lowest_price );
		update_post_meta( $post_parent, '_min_variation_price', $lowest_price );
		update_post_meta( $post_parent, '_max_variation_price', $highest_price );
	}

	function product_price_html( $price, $product ) {
		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $product );

		if ( WC_Sponsorship::is_sponsorship( $product ) ) {
			if ( is_admin() ) {
				$price = $this->product_admin_price_string( $product );
			} else {
				$price = $this->product_frontend_price_string( $product );
			}
		}


		return $price;
	}

	function product_admin_price_string( $product ) {
		global $post;

		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $product );

		if ( !WC_Sponsorship::is_sponsorship( $product ) ) return null;

		$cancel = get_post_meta( $product->id, '_sponsorship_cancel', true );
		$complete = get_post_meta( $product->id, '_sponsorship_complete', true );

		$data = get_post_meta( $post->ID, '_sponsorship', true );

		$goal = '0';
		if ( $data && isset( $data[ 'goal' ] ) ) {
			$goal = $data[ 'goal' ];
		}

		$progress = '0';
		if ( $data && isset( $data[ 'progress' ] ) ) {
			$progress = $data[ 'progress' ];
		}

		$sponsorship_string = '';
		if ( !$cancel ) {
			if ( $complete ) {
				$sponsorship_string = 'Completed! ';
			}
			$sponsorship_string .= '$' . $progress . ' / $' . $goal;
		} else {
			$sponsorship_string = 'Cancelled';
		}
		return apply_filters( 'woocommerce_sponsorship_price_string', $sponsorship_string, $product );
	}

	function filter_product_visibility( $visible, $product ) {
		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $product );

		if ( !WC_Sponsorship::is_sponsorship( $product ) ) return $visible;

		$cancel = get_post_meta( $product->id, '_sponsorship_cancel', true );
		$complete = get_post_meta( $product->id, '_sponsorship_complete', true );

		if ( $cancel || $complete ) return false;

		return WC_Sponsorship_Product::check_project_duedate( $product->id );
	}

	function product_frontend_price_string( $product ) {
		global $post;

		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $product );

		if ( !WC_Sponsorship::is_sponsorship( $product ) ) return;

		$data = get_post_meta( $post->ID, '_sponsorship', true );

		$days_left = 0;
		if ( $data[ 'end' ][ 'date' ] ) {
			$now = strtotime( date( "Y-m-d" ) ); // or your date as well
			$then = strtotime( $data[ 'end' ][ 'date' ] );
			$datediff = $then - $now;

			// add 1 additional day so if current day, it is not the last day
			$days_left = floor( $datediff / ( 60 * 60 * 24 ) ) + 1;

			if ( $days_left < 0 ) {
				$days_left = 0;
			}
		}

		$goal = $progress = $percent = 0;
		if ( $data[ 'goal' ] ) {
			$goal = $data[ 'goal' ];
		}

		if ( isset( $data[ 'progress' ] ) ) {
			$progress = $data[ 'progress' ];
			if ( $goal ) {
				$percent = round( $data[ 'progress' ] / $data[ 'goal' ] * 100 );
			}
		}

		if ( 100 < $percent ) {
			$percent = 100;
		}

		$args = array(
			'post_type' => 'product_variation',
			'post_status' => array( 'private', 'publish' ),
			'numberposts' => -1,
			'orderby' => 'id',
			'order' => 'asc',
			'post_parent' => $post->ID
		);
		$levels = get_posts( $args );

		$price_html = '<div class="sponsorship-product">';
		if ( $levels && count( $levels ) > 0 ) {
			ob_start();
			?>
			<div class="sp-progress">
				<div class="sp-progress-bar">
					<div class="sp-progress-bar-percent" style="width: <?php echo $percent; ?>%;"></div>
				</div>
				<div class="sp-price">
					<div class="sp-price-col">
						<strong><?php echo $percent; ?>%</strong>
						funded
					</div>
					<div class="sp-price-col">
						<strong>$<?php echo $progress; ?></strong>
						pledged
					</div>
					<div class="sp-price-col">
						<strong><?php echo $days_left; ?></strong>
						days left
					</div>
				</div>
			</div>
			<?php
			$price_html .= ob_get_clean();
		} else {
			$price_html .= '<em>Project not set up...</em>';
		}
		$price_html .= '</div>';

		return $price_html;
	}

	function product_frontend_styling() {
		global $post, $product;

		if ( !is_object( $product ) ) $product = new WC_Product_Variable( $post->ID );

		if ( !WC_Sponsorship::is_sponsorship( $product ) ) return;

		$output = '';

		$output .= '.woocommerce .summary p.price { display: none; }' . "\n";
		$output .= '.woocommerce .product_meta { display: none; }' . "\n";

		if ( $output ) {
			$output = "\n<!-- WooCommerce Sponsorship Custom Styling -->\n<style type=\"text/css\">\n" . $output . "</style>\n<!-- /WooCommerce Sponsorship Custom Styling -->\n\n";
			echo $output;
		}
	}

	function add_sponsorship_project_to_cart() {
		global $post;

		$args = array(
			'post_type' => 'product_variation',
			'post_status' => array( 'private', 'publish' ),
			'numberposts' => -1,
			'orderby' => 'id',
			'order' => 'asc',
			'post_parent' => $post->ID
		);
		$levels = get_posts( $args );

		do_action( 'woocommerce_before_add_to_cart_button' );
		?>
		<div class="sp-levels">
			<?php
			foreach ( $levels as $level ) :
				$level_product = new WC_Product_Variation( $level->ID );
				$level_data = get_post_custom( $level->ID );
				?>
				<form id="level-<?php echo $level->ID; ?>-form" enctype="multipart/form-data" method="post" class="cart" action="<?php echo $level_product->add_to_cart_url(); ?>">
					<a class="sp-level" rel="<?php echo $level->ID; ?>">
						<div class="sp-level-title">
							<?php echo get_the_title( $level->ID ); ?>
							<div class="sp-level-amount">
								<strong>$<?php echo (isset( $level_data[ '_price' ][ 0 ] ) ? $level_data[ '_price' ][ 0 ] : 0); ?></strong>
							</div>
						</div>		
						<div class="sp-level-description">
							<?php echo $level->post_content; ?>
						</div>
					</a>
				</form>
			<?php endforeach; ?>
		</div>
		<script>
			jQuery('.sp-level').click(function() {
				var levelId = jQuery(this).attr('rel');
				jQuery('#level-' + levelId + '-form').submit();
				return false;
			})
		</script>
		<?php
		do_action( 'woocommerce_after_add_to_cart_button' );
	}

	public static function get_contribution_amount( $project_id ) {
		// do some magic to see how much has been contributed so far
	}

	public static function get_contribution_level_title( $product ) {
		if ( !is_object( $product ) ) {
			$product = new WC_Product_Variable( $product );
		}

		$title = $product->post->post_title;
		if ( WC_Sponsorship_Product::is_sponsorship_contribution_level( $product ) ) {
			$title = get_the_title( $product->post->post_parent ) . ' - ' . $title;
		}

		return $title;
	}

	public static function is_sponsorship( $product ) {
		return WC_Sponsorship::is_sponsorship( $product );
	}

	public static function is_sponsorship_contribution_level( $product ) {
		return WC_Sponsorship::is_sponsorship_contribution_level( $product );
	}

	public static function check_project_progress( $project_id ) {
		global $wpdb;

		$is_complete = false;
		if ( $project_id ) {
			// get the project's meta data, which has the goal in it
			$pdata = get_post_meta( $project_id, '_sponsorship', true );
			$pdata[ 'progress' ] = $wpdb->get_var( $wpdb->prepare( "
select sum(pmV.meta_value) as Progress
from $wpdb->postmeta pm
join $wpdb->postmeta pmV on pm.post_id = pmV.post_id and pmV.meta_key = '_order_total'
where pm.meta_key = '_sponsorship_project' and pm.meta_value = %d;
				", $project_id ) );

			if ( $pdata[ 'goal' ] <= $pdata[ 'progress' ] ) {
				// complete all the charges
				update_post_meta( $project_id, '_sponsorship_complete', true );
				$is_complete = true;

				$orders = $wpdb->get_results( $wpdb->prepare( "
select pm.post_id as OrderId, pmTotal.meta_value as OrderTotal
from $wpdb->postmeta pm
join $wpdb->postmeta pmCID on pm.post_id = pmCID.post_id and pmCID.meta_key = '_stripe_customer_id'
join $wpdb->postmeta pmTotal on pm.post_id = pmTotal.post_id and pmTotal.meta_key = '_order_total'
where pm.meta_key = '_sponsorship_project' and pm.meta_value = %d
					", $project_id ) );

				foreach ( $orders as $order ) {
					WC_Sponsorship_Order::complete_order( $order->OrderId, $project_id, $order->OrderTotal );
				}
			}
			update_post_meta( $project_id, '_sponsorship', $pdata );
		}

		return $is_complete;
	}

	public static function check_project_duedate( $project_id ) {
		$data = get_post_meta( $project_id, '_sponsorship', true );

		$days_left = 0;
		if ( $data[ 'end' ][ 'date' ] ) {
			$now = strtotime( date( "Y-m-d" ) ); // or your date as well
			$then = strtotime( $data[ 'end' ][ 'date' ] );
			$datediff = $then - $now;

			// add 1 additional day so if current day, it is not the last day
			$days_left = floor( $datediff / ( 60 * 60 * 24 ) ) + 1;

			if ( $days_left < 0 ) {
				$days_left = 0;
			}
		}


		if ( !$days_left ) {
			$days_left = false;

			// cancel all orders
			WC_Sponsorship_Product::cancel_project_orders( $project_id );
		} else {
			$days_left = true;
		}

		return $days_left;
	}

	public static function cancel_project_orders( $project_id ) {
		global $wpdb;

		if ( !$project_id ) {
			return;
		}

		update_post_meta( $project_id, '_sponsorship_cancel', true );

		$orders = $wpdb->get_results( $wpdb->prepare( "
select pm.post_id as OrderId, pmCID.meta_value as StripeCustomerID
from $wpdb->postmeta pm
join $wpdb->postmeta pmCID on pm.post_id = pmCID.post_id and pmCID.meta_key = '_stripe_customer_id'
where pm.meta_key = '_sponsorship_project' and pm.meta_value = %d
									", $project_id ) );

		foreach ( $orders as $order ) {
			WC_Sponsorship_Order::cancel_order( $order->OrderId, 'Sponsorship project goal not met, charge was cancelled.' );
		}
	}

}

$GLOBALS[ 'WC_Sponsorship_Product' ] = new WC_Sponsorship_Product();
?>
