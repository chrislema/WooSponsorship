<?php
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
<div class="sp-widget-levels">
	<div class="sp-widget-levels">
		<?php
		foreach ( $levels as $level ) :
			$level_product = new WC_Product_Simple( $level->ID );
			$level_data = get_post_custom( $level->ID );
			?>
			<form id="level-<?php echo $level->ID; ?>-form" enctype="multipart/form-data" method="post" class="cart" action="<?php echo $level_product->add_to_cart_url(); ?>">
				<a class="sp-widget-level" rel="<?php echo $level->ID; ?>">
					<div class="sp-widget-level-title">
						<?php echo get_the_title( $level->ID ); ?>
						<div class="sp-level-amount">
							<strong>$<?php echo (isset( $level_data[ '_price' ][ 0 ] ) ? $level_data[ '_price' ][ 0 ] : 0); ?></strong>
						</div>
					</div>		
					<div class="sp-widget-level-description">
						<p><?php echo $level->post_content; ?></p>
					</div>
				</a>
			</form>
		<?php endforeach; ?>
	</div>
</div>
<script>
	jQuery('.sp-widget-level').click(function() {
		var levelId = jQuery(this).attr('rel');
		jQuery('#level-' + levelId + '-form').submit();
		return false;
	})
</script>
