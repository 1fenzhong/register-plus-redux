<?php
if( !class_exists("RegisterPlusReduxInvitationTrackingWidget") ) {
	class RegisterPlusReduxInvitationTrackingWidget {
		function RegisterPlusReduxInvitationTrackingWidget() {
			global $rpr_options;
			if ( !empty($rpr_options["enable_invitation_tracking_widget"]) )
				add_action("wp_dashboard_setup", array($this, "AddDashboardWidget"));
		}

		function AddDashboardWidget() {
			wp_add_dashboard_widget("redux_invitation_tracking_widget", __("Invitation Code Tracking", "register-plus-redux"), array($this, "ShowWidget"));
		}

		function ShowWidget() {
			global $wpdb;
			$invitation_code_bank = get_option("register_plus_redux_invitation_code_bank-rv1");
			//$options = get_option("register_plus_redux_options");
			//$invitation_code_bank = $options["invitation_code_bank"];
			foreach ( $invitation_code_bank as $invitation_code ) {
				$users = $wpdb->get_results( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key='invitation_code' AND meta_value='$invitation_code'" );
				echo "<h3>$invitation_code: <small style=\"font-weight:normal\">", count($users), " Users Registered.</small></h3>";
			}		
		}
	}
}

if ( class_exists("RegisterPlusReduxInvitationTrackingWidget") )
	$registerPlusReduxInvitationTrackingWidget = new RegisterPlusReduxInvitationTrackingWidget();
?>