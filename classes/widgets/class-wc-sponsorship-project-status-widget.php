<?php
class WC_Sponsorship_Project_Status_Widget extends WP_Widget {
    function WC_Sponsorship_Project_Status_Widget() {
        parent::WP_Widget('wc-sponsorship-project-status-widget', 'WooCommerce Sponsorship - Status');
    }
    
    function widget($args, $instance) {
        extract($args);
        
        echo $before_widget;
		
		echo '<div style="display: none;">';
        echo $before_title;
        echo $after_title;
		echo '</div>';
		
        include('class-wc-sponsorship-project-status-template.php');        
		
        echo $after_widget;
    }
    
    function update($new_instance, $old_instance) { }
    
    function form($instance) { }
    
    function refresh() { }
}
?>
