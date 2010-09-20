<?php
if( !class_exists('RegisterPlusReduxInvitationTrackingWidget') ) {
	class RegisterPlusReduxInvitationTrackingWidget {
		function RegisterPlusWidget() {
			//Add widget to the dashboard
			add_action('wp_dashboard_setup', array($this, 'register_widget'));
			add_filter('wp_dashboard_widgets', array($this, 'add_widget'));
		}

		function register_widget() {
			wp_register_sidebar_widget('redux_invitation_tacking_widget', __( 'Invitation Code Tracking', 'regplus' ), array($this, 'widget'), array( 'settings' => 'options-general.php?page=register-plus-redux' ));
		}

		// Modifies the array of dashboard widgets and adds this plugin's
		function add_widget( $widgets ) {
			global $wp_registered_widgets;
			if ( !isset($wp_registered_widgets['redux_invitation_tacking_widget']) ) return $widgets;
			array_splice($widgets, 2, 0, 'redux_invitation_tacking_widget');
			return $widgets;
		}

		// Output the widget contents
		function widget( $args ) {
			//v.3.5.1 code
			//extract($args, EXTR_SKIP);
			//code recommended by robert.lang
			extract(array($this, 'EXTR_SKIP'));
			echo $before_widget;
			echo $before_title;
			echo $widget_name;
			echo $after_title;
			global $wpdb;
			$options = get_option('register_plus');
			$invitation_code_bank = $options['invitation_code_bank'];
			$usercodes = array();
			foreach ( $invitation_code_bank as $invitation_code ) {
				$users = $wpdb->get_results( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key='invitation_code' AND meta_value='$invitation_code'" );
				echo '<h3>' . $invitation_code . ': <small style="font-weight:normal">' . count($users) . ' Users Registered.</small></h3>';
			}		
			echo $after_widget;
		}
	}
}

// Start this plugin once all other plugins are fully loaded
add_action('plugins_loaded', create_function('', 'global $registerPlusReduxInvitationTrackingWidget; $registerPlusReduxInvitationTrackingWidget = new RegisterPlusReduxInvitationTrackingWidget();'));
?>