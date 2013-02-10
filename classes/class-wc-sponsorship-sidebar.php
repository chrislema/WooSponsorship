<div id="sidebar" class="wc-sponsorship-sidebar sidebar widget-area">
<?php
	if ( ! dynamic_sidebar( 'Sponsorship Sidebar' ) ) {
		echo '<div class="widget widget_text"><div class="widget-wrap">';
			echo '<h4 class="widgettitle">';
				__( 'Sponsorship Sidebar Widget Area' );
			echo '</h4>';
			echo '<div class="textwidget"><p>';
				printf( __( 'This is the Sponsorship Sidebar Widget Area. You can add content to this area by visiting your <a href="%s">Widgets Panel</a> and adding new widgets to this area.' ), admin_url( 'widgets.php' ) );
			echo '</p></div>';
		echo '</div></div>';
	}
?>
</div>