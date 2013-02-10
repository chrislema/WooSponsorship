<?php
global $post, $product, $wpdb;

if ( !is_object( $product ) ) {
	$product = new WC_Product_Variable( $product );
}

if ( !WC_Sponsorship::is_sponsorship( $product ) ) {
	return;
}

$data = get_post_meta( $post->ID, '_sponsorship', true );

$days_left = 0;
if ( $data[ 'end' ][ 'date' ] ) {
	$now = strtotime( date( "Y-m-d" ) ); // or your date as well
	$then = strtotime( $data[ 'end' ][ 'date' ] );
	$end_date = date( "l M j", $then );
	$datediff = $then - $now;

	// add 1 additional day so if current day, it is not the last day
	$days_left = floor( $datediff / ( 60 * 60 * 24 ) ) + 1;

	if ( $days_left < 0 ) {
		$days_left = 0;
	}
}

$goal = $progress = $percent = 0;
if ( array_key_exists( 'goal', $data ) ) {
	$goal = $data[ 'goal' ];
}

if ( array_key_exists( 'progress', $data ) ) {
	$progress = $data[ 'progress' ];
	if ( $goal ) {
		$percent = round( $data[ 'progress' ] / $data[ 'goal' ] * 100 );
	}
}

if ( 100 < $percent ) {
	$percent = 100;
}

$backers = $wpdb->get_var( $wpdb->prepare("
	select count(pm.post_id)
	from $wpdb->postmeta pm
	join $wpdb->postmeta pmCID on pm.post_id = pmCID.post_id and pmCID.meta_key = '_stripe_customer_id'
	join $wpdb->postmeta pmTotal on pm.post_id = pmTotal.post_id and pmTotal.meta_key = '_order_total'
	where pm.meta_key = '_sponsorship_project' and pm.meta_value = %d
	", $product->id ) );

$min_level_id = $wpdb->get_var( $wpdb->prepare("
	select pm.post_id
	from $wpdb->posts p
	join $wpdb->postmeta pm on pm.post_id = p.id and pm.meta_key = '_price'
	where p.post_parent = 272
	order by pm.meta_value
	limit 1;
	", $product->id ) );
if ( $min_level_id ) {
	$min_level = new WC_Product_Variation( $min_level_id );
	$min_level_data = get_post_custom( $min_level_id );
}
?>
<div class="sp-widget-stats">
	<div class="sp-project-stats">
		<h5>
			<div class="sp-project-backers num">
				<span class="sp-project-backers-value">
					<?php echo $backers; ?>
				</span>
			</div>
			<span>
				<span class="sp-project-backers-label">
					Backers
				</span>
			</span>
		</h5>
		<h5>
			<div class="sp-project-pledged num">
				<span class="sp-project-pledged-value">
					$<?php echo $progress; ?>
				</span>
			</div>
			pledged of $<?php echo $goal; ?> goal
		</h5>
		<div class="sp-project-duration">
			<h5>
				<div class="sp-project-duration-value num"><?php echo $days_left; ?></div>
				<span class="sp-project-duration-label text">days to go</span>
			</h5>
		</div>
	</div>
	<?php if ( isset( $min_level ) ) : ?>
	<div class="sp-project-contribute">
		<div>
			<form id="level-<?php echo $min_level_id; ?>-form" enctype="multipart/form-data" method="post" class="cart" action="<?php echo $min_level->add_to_cart_url(); ?>">
				<a id="sp-project-min-pledge-button" rel="<?php echo $min_level_id; ?>">
					Back This Project
					<small>$<?php echo (isset( $min_level_data[ '_price' ][ 0 ] ) ? $min_level_data[ '_price' ][ 0 ] : 0); ?> minimum pledge</small>
				</a>
			</form>
		</div>
	</div>
	<?php endif; ?>
	<div class="sp-project-goal">
		<div>
			<p>
				This project will only be funded if at least $<?php echo $goal; ?> is pledged by <?php echo $end_date; ?>.
			</p>
		</div>
	</div>
</div>
<script>
	jQuery('#sp-project-min-pledge-button').click(function() {
		var levelId = jQuery(this).attr('rel');
		jQuery('#level-' + levelId + '-form').submit();
		return false;
	})
</script>