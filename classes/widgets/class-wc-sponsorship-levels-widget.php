<?php
class WC_Sponsorship_Levels_Widget extends WP_Widget {
    function WC_Sponsorship_Levels_Widget() {
        parent::WP_Widget('wc-sponsorship-levels-widget', 'WooCommerce Sponsorship - Levels');
    }
    
    function widget($args, $instance) {
        extract($args);
        
        echo $before_widget;
		
		echo '<div style="display: none;">';
        echo $before_title;
        echo $after_title;
		echo '</div>';
		
        include('class-wc-sponsorship-levels-template.php');        
		
        echo $after_widget;
    }
    
    function update($new_instance, $old_instance) { }
    
    function form($instance) { }
    
    function refresh() { }
}
?>
