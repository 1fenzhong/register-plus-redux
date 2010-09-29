<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/blog/category/register-plus-redux/
Description: Fork of Register Plus
Version: 3.6.9
*/

$ops = get_option("register_plus_redux_options");
if ( $ops["enable_invitation_tracking_widget"] )
	include_once("dash_widget.php");

if ( !class_exists("RegisterPlusReduxPlugin") ) {
	class RegisterPlusReduxPlugin {
		function RegisterPlusReduxPlugin() {
			global $wp_version;
			if ( is_admin() ) {
				add_action("init", array($this, "InitializeSettings")); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("init", array($this, "DeleteExpiredUsers")); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("admin_menu", array($this, "AddPages") ); //Runs after the basic admin panel menu structure is in place.
				if ( $_GET["page"] == "register-plus-redux" && $_POST["action"] == "update_settings")
					add_action("init", array($this, "UpdateSettings") ); //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_GET["page"] == "unverified-users" && $_POST["verify_users"] )
					add_action("init", array($this, "AdminValidate")); //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_GET["page"] == "unverified-users" && $_POST["send_verification_email"] )
					add_action("init", array($this, "AdminEmailValidate")); //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_GET["page"] == "unverified-users" && $_POST["delete_users"] )
					add_action("init", array($this, "AdminDeleteUnvalidated")); //Runs after WordPress has finished loading but before any headers are sent.
			}
			add_action("login_head", array($this, "LoginHead")); //Runs just before the end of the HTML head section of the login page. 
			add_action("register_form", array($this, "AlterRegistrationForm")); //Runs just before the end of the new user registration form.
			add_action("register_post", array($this, "CheckRegistration"), 10, 3); //Runs before a new user registration request is processed. 
			add_filter("registration_errors", array($this, "OverrideRegistrationErrors"));
			add_action("login_form", array($this, "VerifyUser")); //Runs just before the end of the HTML head section of the login page. 
			
			//add_action("wpmu_activate_user", array($this, "UpdateSignup"), 10, 3);
			//add_action("signup_extra_fields", array($this, "AlterSignupForm"));
			//add_filter("wpmu_validate_user_signup", array($this, "CheckSignup"));

			add_action("show_user_profile", array($this, "ShowCustomFields")); //Runs near the end of the user profile editing screen.
			add_action("edit_user_profile", array($this, "ShowCustomFields")); //Runs near the end of the user profile editing screen in the admin menus. 
			add_action("profile_update", array($this, "SaveCustomFields"));	//Runs when a user's profile is updated. Action function argument: user ID. 

			//LOCALIZATION
			//Place your language file in the plugin folder and name it "regplus-{language}.mo"
			//replace {language} with your language value from wp-config.php
			//load_plugin_textdomain("regplus", "/wp-content/plugins/register-plus-redux");
			
			//VERSION CONTROL
			if ( $wp_version < 3.0 )
				add_action("admin_notices", array($this, "VersionWarning"));
		}

		function InitializeSettings() {
			$default = array(
				"user_set_password" => "0",
				"show_password_meter" => "0",
				"message_empty_password" => "Strength Indicator",
				"message_short_password" => "Too Short",
				"message_bad_password" => "Bad Password",
				"message_good_password" => "Good Password",
				"message_strong_password" => "Strong Password",
				"message_mismatch_password" => "Password Mismatch",
				"custom_logo_url" => "",
				"verify_user_email" => "0",
				"delete_unverified_users_after" => "7",
				"verify_user_admin" => "0",
				"enable_invitation_code" => "0",
				"enable_invitation_tracking_widget" => "0",
				"require_invitation_code" => "0",
				"invitation_code_bank" => array(),
				"allow_duplicate_emails" => "0",

				"show_fields" => array(),
				"required_fields" => array(),
				"required_fields_style" => "border:solid 1px #E6DB55; background-color:#FFFFE0;",
				"show_disclaimer" => "0",
				"message_disclaimer_title" => "Disclaimer",
				"message_disclaimer" => "",
				"message_disclaimer_agree" => "Accept the Disclaimer",
				"show_license" => "0",
				"message_license_title" => "License Agreement",
				"message_license" => "",
				"message_license_agree" => "Accept the License Agreement",
				"show_privacy_policy" => "0",
				"message_privacy_policy_title" => "Privacy Policy",
				"message_privacy_policy" => "",
				"message_privacy_policy_agree" => "Accept the Privacy Policy",

				"datepicker_firstdayofweek" => "6",
				"datepicker_dateformat" => "mm/dd/yyyy",
				"datepicker_startdate" => "",
				"datepicker_calyear" => "",
				"datepicker_calmonth" => "cur",

				"custom_user_message" => "0",
				"user_message_from_email" => get_option("admin_email"),
				"user_message_from_name" => get_option("blogname"),
				"user_message_subject" => sprintf(__("[%s] Your username and password", "regplus"), get_option("blogname")),
				"user_message_body" => "Username: %user_login%\r\nPassword: %user_password%\r\n\r\n%site_url%\r\n",
				"send_user_message_in_html" => "0",
				"user_message_newline_as_br" => "0",
				"user_message_login_link" => wp_login_url(),

				"disable_admin_message" => "0",
				"custom_admin_message" => "0",
				"admin_message_from_email" => get_option("admin_email"),
				"admin_message_from_name" => get_option("blogname"),
				"admin_message_subject" => sprintf(__("[%s] New User Register", "regplus"), get_option("blogname")),
				"admin_message_body" => "New user registered on your site %blogname%\r\n\r\nUsername: %user_login%\r\n\r\nE-mail: %user_email%\r\n",
				"send_admin_message_in_html" => "0",
				"admin_message_newline_as_br" => "0",

				"custom_registration_page_css" => "",
				"custom_login_page_css" => ""
			);
			if ( !get_option("register_plus_redux_options") ) {
				//Check if settings exist, add defaults in necessary
				add_option("register_plus_redux_options", $default);
			} else {
				//Check settings for new variables, add as necessary
				$options = get_option("register_plus_redux_options");
				foreach ( $default as $k => $v ) {
					if ( !$options[$k] ) {
						$options[$k] = $v;
						$new = true;
					}
				}
				if ( $new ) update_option("register_plus_redux_options", $options);
			}
		}

		function DeleteExpiredUsers() {
			global $wpdb;
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='email_verification_sent'");
			if ( $unverified_users ) {
				$options = get_option("register_plus_redux_options");
				$expirationdate = date("Ymd", strtotime("-".$options["delete_unverified_users_after"]." days"));
				require_once(ABSPATH.'/wp-admin/includes/user.php');
				foreach ( $unverified_users as $unverified_user ) {
					if ( $unverified_user->meta_value < $expirationdate ) {
						wp_delete_user($unverified_user->user_id);
					}
					
				}
			}
		}

		function AddPages() {
			$options_page = add_submenu_page("options-general.php", "Register Plus Redux Settings", "Register Plus Redux", "manage_options", "register-plus-redux", array($this, "OptionsPage"));
			add_action("admin_head-$options_page", array($this, "OptionsHead"));
			add_filter("plugin_action_links", array($this, "filter_plugin_actions"), 10, 2);
			$options = get_option("register_plus_redux_options");
			if ( $options["verify_user_email"] || $options["verify_user_admin"] )
				add_submenu_page("users.php", "Unverified Users", "Unverified Users", "promote_users", "unverified-users", array($this, "UnverifiedUsersPage"));
		}

		function filter_plugin_actions( $links, $file ) {
			static $this_plugin;
			if ( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
			if ( $file == $this_plugin ) {
				$settings_link = "<a href='options-general.php?page=register-plus-redux'>Settings</a>";
				array_unshift($links, $settings_link);	// before other links
				//$links[] = $settings_link;		// ... or after other links
			}
			return $links;
		}

		function OptionsHead() {
			wp_enqueue_script("jquery");
			$options = get_option("register_plus_redux_options");
			?>
			<script type="text/javascript">
			function addInvitationCode() {
				jQuery('<div class="invitation_code"><input type="text" name="invitation_code_bank[]" value="" />&nbsp;<img src="<?php echo plugins_url("removeBtn.gif", __FILE__); ?>" alt="<?php echo __("Remove Code", "regplus"); ?>" title="<?php echo __("Remove Code", "regplus"); ?>" onClick="return removeInvitationCode(this);" style="cursor: pointer;" /></div>').appendTo('#invitation_code_bank');
			}

			function removeInvitationCode(clickety) {
				jQuery(clickety).parent().remove();
			}

			function addCustomField() {
				jQuery("#custom_fields").find('tbody')
					.append(jQuery('<tr>')
						.attr('valign', 'center')
						.attr('class', 'custom_field')
						.append(jQuery('<td>')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'text')
								.attr('name', 'custom_field_name[]')
							)
						)
						.append(jQuery('<td>')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<select>')
								.attr('name', 'custom_field_type[]')
								.append('<option value="text">Text Field</option>')
								.append('<option value="select">Select Field</option>')
								.append('<option value="check">Checkbox Fields</option>')
								.append('<option value="radio">Radio Fields</option>')
								.append('<option value="textarea">Text Area</option>')
								.append('<option value="date">Date Field</option>')
								.append('<option value="hidden">Hidden Field</option>')
							)
						)
						.append(jQuery('<td>')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'text')
								.attr('name', 'custom_field_options[]')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'checkbox')
								.attr('name', 'show_on_profile[]')
								.attr('value', '1')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'checkbox')
								.attr('name', 'show_on_registration[]')
								.attr('value', '1')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'checkbox')
								.attr('name', 'required_on_registration[]')
								.attr('value', '1')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<img>')
								.attr('src', '<?php echo plugins_url("removeBtn.gif", __FILE__); ?>')
								.attr('alt', '<?php echo __("Remove Field", "regplus"); ?>')
								.attr('title', '<?php echo __("Remove Field", "regplus"); ?>')
								.attr('onClick', 'return removeCustomField(this);')
								.attr('style', 'cursor: pointer;')
							)
						)
					);
			}

			function removeCustomField(clickety) {
				jQuery(clickety).parent().parent().remove();
			}

			jQuery(document).ready(function() {
				jQuery('#user_set_password').change(function() {
					if (jQuery('#user_set_password').attr('checked') )
						jQuery('#password_settings').show();
					else
						jQuery('#password_settings').hide();
					return true;
				});

				jQuery('#show_password_meter').change(function() {
					if (jQuery('#show_password_meter').attr('checked') )
						jQuery('#meter_settings').show();
					else
						jQuery('#meter_settings').hide();
					return true;
				});

				jQuery('#verify_user_email').change(function() {
					if ( jQuery('#verify_user_email').attr('checked') )
						jQuery('#verify_user_email_settings').show();
					else
						jQuery('#verify_user_email_settings').hide();
					return true;
				});

				jQuery('#enable_invitation_code').change(function() {
					if (jQuery('#enable_invitation_code').attr('checked') )
						jQuery('#invitation_code_settings').show();
					else
						jQuery('#invitation_code_settings').hide();
					return true;
				});

				jQuery('#show_disclaimer').change(function() {
					if (jQuery('#show_disclaimer').attr('checked') )
						jQuery('#disclaim_settings').show();
					else
						jQuery('#disclaim_settings').hide();
					return true;
				});

				jQuery('#show_license').change(function() {
					if (jQuery('#show_license').attr('checked') )
						jQuery('#license_agreement_settings').show();
					else
						jQuery('#license_agreement_settings').hide();
					return true;
				});

				jQuery('#show_privacy_policy').change(function() {
					if (jQuery('#show_privacy_policy').attr('checked') )
						jQuery('#privacy_policy_settings').show();
					else
						jQuery('#privacy_policy_settings').hide();
					return true;
				});

				jQuery('#custom_user_message').change(function() {
					if ( jQuery('#custom_user_message').attr('checked') )
						jQuery('#custom_user_message_settings').show();
					else
						jQuery('#custom_user_message_settings').hide();
					return true;
				});

				jQuery('#custom_admin_message').change(function() {
					if ( jQuery('#custom_admin_message').attr('checked') )
						jQuery('#custom_admin_message_settings').show();
					else
						jQuery('#custom_admin_message_settings').hide();
					return true;
				});
				<?php if ( !$options["user_set_password"] ) echo "\njQuery('#password_settings').hide();"; ?>
				<?php if ( !$options["show_password_meter"] ) echo "\njQuery('#meter_settings').hide();"; ?>
				<?php if ( !$options["verify_user_email"] ) echo "\njQuery('#verify_user_email_settings').hide();"; ?>
				<?php if ( !$options["enable_invitation_code"] ) echo "\njQuery('#invitation_code_settings').hide();"; ?>
				<?php if ( !$options["show_disclaimer"] ) echo "\njQuery('#disclaim_settings').hide();"; ?>
				<?php if ( !$options["show_license"] ) echo "\njQuery('#license_agreement_settings').hide();"; ?>
				<?php if ( !$options["show_privacy_policy"] ) echo "\njQuery('#privacy_policy_settings').hide();"; ?>
				<?php if ( !$options["custom_user_message"] ) echo "\njQuery('#custom_user_message_settings').hide();"; ?>
				<?php if ( !$options["custom_admin_message"] ) echo "\njQuery('#custom_admin_message_settings').hide();"; ?>

				jQuery('.disabled').hide();
			});
			</script>
		<?php
		}

		function OptionsPage() {
			?>
			<div class="wrap">
			<h2><?php _e("Register Plus Redux Settings", "regplus") ?></h2>
			<p><input type="button" class="button" value="<?php _e("Preview Registraton Page", "regplus"); ?>" name="preview" onclick="window.open('<?php echo wp_login_url(), "?action=register"; ?>');" /></p>
			<?php if ( $_POST["notice"] ) echo "\n<div id='message' class='updated fade'><p><strong>", $_POST["notice"], "</strong></p></div>"; ?>
			<form enctype="multipart/form-data" method="post">
				<?php wp_nonce_field("register-plus-redux-update-settings"); ?>
				<input type="hidden" name="action" value="update_settings" />
				<?php $options = get_option("register_plus_redux_options"); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Password", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="user_set_password" id="user_set_password" value="1" <?php if ( $options["user_set_password"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Allow New Registrations to set their own Password", "regplus"); ?></label><br />
							<div id="password_settings" style="margin-left:0px;">
								<label><input type="checkbox" name="show_password_meter" id="show_password_meter" value="1" <?php if ( $options["show_password_meter"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable Password Strength Meter","regplus"); ?></label>
								<div id="meter_settings" style="margin-left:10px;">
									<table>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_empty_password"><?php _e("Empty", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_empty_password" value="<?php echo $options["message_empty_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_short_password"><?php _e("Short", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_short_password" value="<?php echo $options["message_short_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_bad_password"><?php _e("Bad", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_bad_password" value="<?php echo $options["message_bad_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_good_password"><?php _e("Good", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_good_password" value="<?php echo $options["message_good_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_strong_password"><?php _e("Strong", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_strong_password" value="<?php echo $options["message_strong_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_mismatch_password"><?php _e("Mismath", "regplus"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_mismatch_password" value="<?php echo $options["message_mismatch_password"]; ?>" /></td>
										</tr>
									</table>
								</div>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Custom Logo URL", "regplus"); ?></th>
						<td>
							<input type="text" name="custom_logo_url" id="custom_logo_url" value="<?php echo $options["custom_logo_url"]; ?>" style="width: 50%;" /><br />
							<?php _e("Upload a new logo:", "regplus"); ?>&nbsp;<input type="file" name="upload_custom_logo" id="upload_custom_logo" value="1" /><br />
							<?php _e("You must Save Changes to upload logo.", "regplus"); ?><br />
							<?php _e("Recommended logo should not exceed 358px width.", "regplus"); ?>
							<?php if ( $options["custom_logo_url"] ) { ?>
								<br /><img src="<?php echo $options["custom_logo_url"]; ?>" /><br />
								<?php list($custom_logo_width, $custom_logo_height) = getimagesize($options["custom_logo_url"]); ?>
								<?php echo $custom_logo_width; ?>x<?php echo $custom_logo_height; ?><br />
								<label><input type="checkbox" name="remove_logo" value="1" />&nbsp;<?php _e("Remove Logo", "regplus"); ?></label><br />
								<?php _e("You must Save Changes to remove logo.", "regplus"); ?>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Email Verification", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_email" id="verify_user_email" value="1" <?php if ( $options["verify_user_email"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Prevent fake email address registrations.", "regplus"); ?></label><br />
							<?php _e("New users will be sent an activation code via email.", "regplus"); ?>
							<div id="verify_user_email_settings">
								<label><strong><?php _e("Grace Period (days):", "regplus"); ?></strong>&nbsp;<input type="text" name="delete_unverified_users_after" id="delete_unverified_users_after" style="width:50px;" value="<?php echo $options["delete_unverified_users_after"]; ?>" /></label><br />
								<?php _e("Unverified users will be automatically deleted after grace period expires.", "regplus"); ?>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Admin Verification", "regplus"); ?></th>
						<td><label><input type="checkbox" name="verify_user_admin" id="verify_user_admin" value="1" <?php if ( $options["verify_user_admin"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Moderate all user registrations to require admin approval. NOTE: Email Verification must be DISABLED to use this feature.", "regplus"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Invitation Code", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?php if ( $options["enable_invitation_code"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable Invitation Code(s)", "regplus"); ?></label>
							<div id="invitation_code_settings">
								<label><input type="checkbox" name="enable_invitation_tracking_widget" value="1" <?php if ( $options["enable_invitation_tracking_widget"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable Invitation Tracking Dashboard Widget", "regplus"); ?></label><br />
								<label><input type="checkbox" name="require_invitation_code" value="1" <?php if ( $options["require_invitation_code"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Require Invitation Code to Register", "regplus"); ?></label>
								<div id="invitation_code_bank">
								<?php
									$invitation_codes = $options["invitation_code_bank"];
									if ( !is_array($options["invitation_code_bank"]) ) $options["invitation_code_bank"] = array();
									foreach ($options["invitation_code_bank"] as $invitation_code )
										echo "\n<div class='invitation_code'><input type='text' name='invitation_code_bank[]' value='$invitation_code' />&nbsp;<img src='", plugins_url("removeBtn.gif", __FILE__), "' alt='", __("Remove Code", "regplus"), "' title='", __("Remove Code", "regplus"), "' onClick='return removeInvitationCode(this);' style='cursor: pointer;' /></div>";
								?>
								</div>
								<img src="<?php echo plugins_url("addBtn.gif", __FILE__); ?>" alt="<?php _e("Add Code", "regplus") ?>" title="<?php _e("Add Code", "regplus") ?>" onClick="return addInvitationCode();" style="cursor: pointer;" />&nbsp;<?php _e("Add a new invitation code", "regplus") ?><br />
							</div>
						</td>
					</tr>
					<tr valign="top" class="disabled">
						<th scope="row"><?php _e("Allow Duplicate Email Addresses", "regplus"); ?></th>
						<td><label><input type="checkbox" name="allow_duplicate_emails" value="1" <?php if ( $options["allow_duplicate_emails"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Allow new registrations to use an email address that has been previously registered", "regplus"); ?></label></td>
					</tr>
				</table>
				<h3><?php _e("Registration Page", "regplus"); ?></h3>
				<p><?php _e("Check the fields you would like to appear on the Registration Page.", "regplus"); ?></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Fields", "regplus"); ?></th>
						<td style="padding: 0px;">
							<table>
								<thead valign="top">
									<td style="padding-top: 0px; padding-bottom: 0px;"></td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Show</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Require</td>
								</thead>
								<tbody>
									<?php if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array(); ?>
									<?php if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array(); ?>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("First Name", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="first_name" <?php if ( in_array("first_name", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="first_name" <?php if ( in_array("first_name", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Last Name", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="last_name" <?php if ( in_array("last_name", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="last_name" <?php if ( in_array("last_name", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Website", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="user_url" <?php if ( in_array("user_url", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="user_url" <?php if ( in_array("user_url", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("AIM", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="aim" <?php if ( in_array("aim", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="aim" <?php if ( in_array("aim", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Yahoo IM", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Jabber / Google Talk", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="jabber" <?php if ( in_array("jabber", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="jabber" <?php if ( in_array("jabber", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("About Yourself", "regplus"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_about_field" value="about" <?php if ( in_array("about", $options["show_fields"]) ) echo "checked='checked'"; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="about" <?php if ( in_array("about", $options["required_fields"]) ) echo "checked='checked'"; ?> /></td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Required Field Style Rules", "regplus"); ?></th>
						<td><input type="text" name="required_fields_style" value="<?php echo $options["required_fields_style"]; ?>" style="width: 50%;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Disclaimer", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="show_disclaimer" id="show_disclaimer" value="1" <?php if ( $options["show_disclaimer"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable Disclaimer", "regplus"); ?></label>
							<div id="disclaim_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_disclaimer_title"><?php _e("Disclaimer Title", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_title" value="<?php echo $options["message_disclaimer_title"]; ?>" style="width: 30%;" />							
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_disclaimer"><?php _e("Disclaimer Content", "regplus"); ?></label><br />
											<textarea name="message_disclaimer" cols="25" rows="10" style="width:80%; height:300px; display:block;"><?php echo $options["message_disclaimer"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_disclaimer_agree"><?php _e("Agreement Text", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_agree" value="<?php echo $options["message_disclaimer_agree"]; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("License Agreement", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="show_license" id="show_license" value="1" <?php if ( $options["show_license"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable License Agreement", "regplus"); ?></label>
							<div id="license_agreement_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_license_title"><?php _e("License Agreement Title", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_title" value="<?php echo $options["message_license_title"]; ?>" style="width: 30%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_license"><?php _e("License Agreement Content", "regplus"); ?></label><br />
											<textarea name="message_license" cols="25" rows="10" style="width:80%; height:300px; display:block;"><?php echo $options["message_license"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_license_agree"><?php _e("Agreement Text", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_agree" value="<?php echo $options["message_license_agree"]; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Privacy Policy", "regplus"); ?></th>
						<td>
							<label><input type="checkbox" name="show_privacy_policy" id="show_privacy_policy" value="1" <?php if ( $options["show_privacy_policy"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable Privacy Policy", "regplus"); ?></label>
							<div id="privacy_policy_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_privacy_policy_title"><?php _e("Privacy Policy Title", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_title" value="<?php echo $options["message_privacy_policy_title"]; ?>" style="width: 30%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_privacy_policy"><?php _e("Privacy Policy Content", "regplus"); ?></label><br />
											<textarea name="message_privacy_policy" cols="25" rows="10" style="width:80%; height:300px; display:block;"><?php echo $options["message_privacy_policy"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_privacy_policy_agree"><?php _e("Agreement Text", "regplus"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_agree" value="<?php echo $options["message_privacy_policy_agree"]; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
				<h3><?php _e("User Defined Fields", "regplus"); ?></h3>
				<p><?php _e("Enter custom fields you would like to appear on the Profile and/or Registration Page.", "regplus"); ?></p>
				<p><small><?php _e("Enter Extra Options for Select, Checkboxes and Radio Fields as comma seperated values. For example, if you chose a select box for a custom field of \"Gender\", your extra options would be \"Male,Female\".", "regplus"); ?></small></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Custom Fields", "regplus"); ?></th>
						<td style="padding: 0px;">
							<table id="custom_fields">
								<thead valign="top">
									<td style="padding-top: 0px; padding-bottom: 0px;">Name</td>
									<td style="padding-top: 0px; padding-bottom: 0px;">Type</td>
									<td style="padding-top: 0px; padding-bottom: 0px;">Options</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Profile</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Registration</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Require</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Action</td>
								</thead>
								<tbody>
									<?php
									$custom_fields = get_option("register_plus_redux_custom_fields");
									if ( !is_array($custom_fields) ) $custom_fields = array();
									foreach ( $custom_fields as $k => $v ) {
										echo "\n<tr valign='center' class='custom_field'>";
										echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'><input type='text' name='custom_field_name[$k]' value='", $v["custom_field_name"], "' /></td>";
										echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'>";
										echo "\n		<select name='custom_field_type[$k]'>";
										echo "\n			<option value='text'"; if ( $v["custom_field_type"] == "text" ) echo " selected='selected'"; echo ">Text Field</option>";
										echo "\n			<option value='select'"; if ( $v["custom_field_type"] == "select" ) echo " selected='selected'"; echo ">Select Field</option>";
										echo "\n			<option value='checkbox'"; if ( $v["custom_field_type"] == "checkbox" ) echo " selected='selected'"; echo ">Checkbox Fields</option>";
										echo "\n			<option value='radio'"; if ( $v["custom_field_type"] == "radio" ) echo " selected='selected'"; echo ">Radio Fields</option>";
										echo "\n			<option value='textarea'"; if ( $v["custom_field_type"] == "textarea" ) echo " selected='selected'"; echo ">Text Area</option>";
										echo "\n			<option value='date'"; if ( $v["custom_field_type"] == "date" ) echo " selected='selected'"; echo ">Date Field</option>";
										echo "\n			<option value='hidden'"; if ( $v["custom_field_type"] == "hidden" ) echo " selected='selected'"; echo ">Hidden Field</option>";
										echo "\n		</select>";
										echo "\n	</td>";
										echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'><input type='text' name='custom_field_options[$k]' value='", $v["custom_field_options"], "' /></td>";
										echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_profile[$k]' value='1'"; if ( $v["show_on_profile"] ) echo " checked='checked'"; echo " /></td>";
										echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_registration[$k]' value='1'"; if ( $v["show_on_registration"] ) echo " checked='checked'"; echo " /></td>";
										echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='required_on_registration[$k]' value='1'"; if ( $v["required_on_registration"] ) echo " checked='checked'"; echo " /></td>";
										echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><img src='", plugins_url("removeBtn.gif", __FILE__), "' alt='", __("Remove Field", "regplus"), "' title='", __("Remove Field", "regplus"), "' onClick='return removeCustomField(this);' style='cursor: pointer;' /></td>";
										echo "\n</tr>";
									}
									?>
								</tbody>
							</table>
							<img src="<?php echo plugins_url("addBtn.gif", __FILE__); ?>" alt="<?php _e("Add Field", "regplus") ?>" title="<?php _e("Add Field", "regplus") ?>" onClick="return addCustomField();" style="cursor: pointer;" />&nbsp;<?php _e("Add a new custom field.", "regplus") ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Date Field Settings", "regplus"); ?></th>
						<td>
							<label for="datepicker_firstdayofweek"><?php _e("First Day of the Week", "regplus"); ?>:</label>
							<select type="select" name="datepicker_firstdayofweek">
								<option value="7" <?php if ( $options["datepicker_firstdayofweek"] == "7" ) echo "selected='selected'"; ?>><?php _e("Monday", "regplus"); ?></option>
								<option value="1" <?php if ( $options["datepicker_firstdayofweek"] == "1" ) echo "selected='selected'"; ?>><?php _e("Tuesday", "regplus"); ?></option>
								<option value="2" <?php if ( $options["datepicker_firstdayofweek"] == "2" ) echo "selected='selected'"; ?>><?php _e("Wednesday", "regplus"); ?></option>
								<option value="3" <?php if ( $options["datepicker_firstdayofweek"] == "3" ) echo "selected='selected'"; ?>><?php _e("Thursday", "regplus"); ?></option>
								<option value="4" <?php if ( $options["datepicker_firstdayofweek"] == "4" ) echo "selected='selected'"; ?>><?php _e("Friday", "regplus"); ?></option>
								<option value="5" <?php if ( $options["datepicker_firstdayofweek"] == "5" ) echo "selected='selected'"; ?>><?php _e("Saturday", "regplus"); ?></option>
								<option value="6" <?php if ( $options["datepicker_firstdayofweek"] == "6" ) echo "selected='selected'"; ?>><?php _e("Sunday", "regplus"); ?></option>
							</select><br />
							<label for="datepicker_dateformat"><?php _e("Date Format", "regplus"); ?>:</label><input type="text" name="datepicker_dateformat" value="<?php echo $options["datepicker_dateformat"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_startdate"><?php _e("First Selectable Date", "regplus"); ?>:</label><input type="text" name="datepicker_startdate" id="datepicker_startdate" value="<?php echo $options["datepicker_startdate"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_calyear"><?php _e("Default Year", "regplus"); ?>:</label><input type="text" name="datepicker_calyear" id="datepicker_calyear" value="<?php echo $options["datepicker_calyear"]; ?>" style="width:40px;" /><br />
							<label for="datepicker_calmonth"><?php _e("Default Month", "regplus"); ?>:</label>
							<select name="datepicker_calmonth" id="datepicker_calmonth">
								<option value="cur" <?php if ( $options["datepicker_calmonth"] == "cur" ) echo "selected='selected'"; ?>><?php _e("Current Month", "regplus"); ?></option>
								<option value="0" <?php if ( $options["datepicker_calmonth"] == "0" ) echo "selected='selected'"; ?>><?php _e("Jan", "regplus"); ?></option>
								<option value="1" <?php if ( $options["datepicker_calmonth"] == "1" ) echo "selected='selected'"; ?>><?php _e("Feb", "regplus"); ?></option>
								<option value="2" <?php if ( $options["datepicker_calmonth"] == "2" ) echo "selected='selected'"; ?>><?php _e("Mar", "regplus"); ?></option>
								<option value="3" <?php if ( $options["datepicker_calmonth"] == "3" ) echo "selected='selected'"; ?>><?php _e("Apr", "regplus"); ?></option>
								<option value="4" <?php if ( $options["datepicker_calmonth"] == "4" ) echo "selected='selected'"; ?>><?php _e("May", "regplus"); ?></option>
								<option value="5" <?php if ( $options["datepicker_calmonth"] == "5" ) echo "selected='selected'"; ?>><?php _e("Jun", "regplus"); ?></option>
								<option value="6" <?php if ( $options["datepicker_calmonth"] == "6" ) echo "selected='selected'"; ?>><?php _e("Jul", "regplus"); ?></option>
								<option value="7" <?php if ( $options["datepicker_calmonth"] == "7" ) echo "selected='selected'"; ?>><?php _e("Aug", "regplus"); ?></option>
								<option value="8" <?php if ( $options["datepicker_calmonth"] == "8" ) echo "selected='selected'"; ?>><?php _e("Sep", "regplus"); ?></option>
								<option value="9" <?php if ( $options["datepicker_calmonth"] == "9" ) echo "selected='selected'"; ?>><?php _e("Oct", "regplus"); ?></option>
								<option value="10" <?php if ( $options["datepicker_calmonth"] == "10" ) echo "selected='selected'"; ?>><?php _e("Nov", "regplus"); ?></option>
								<option value="11" <?php if ( $options["datepicker_calmonth"] == "11" ) echo "selected='selected'"; ?>><?php _e("Dec", "regplus"); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<h3><?php _e("Auto-Complete Queries", "regplus"); ?></h3>
				<p><?php _e("You can now link to the registration page with queries to autocomplete specific fields for the user. I have included the query keys below and an example of a query URL.", "regplus"); ?></p>
				<?php
				$registration_fields = "%first_name% %last_name% %user_url% %aim% %yahoo% %jabber% %about% %invitation_code%";
				foreach ( $custom_fields as $k => $v ) {
					if ( $v["show_on_registration"] )
					$registration_fields .= " %".$this->fnSanitizeFieldName($v["custom_field_name"])."%";
				}
				?>
				<code><?php echo $registration_fields; ?></code>
				<p><?php _e("For any custom fields, use your custom field label with the text all lowercase, using underscores instead of spaces. For example if your custom field was \"Middle Name\" your query key would be %middle_name%", "regplus"); ?></p>
				<p><strong><?php _e("Example Query URL", "regplus"); ?></strong></p>
				<code>http://www.radiok.info/wp-login.php?action=register&user_login=radiok&user_email=radiok@radiok.info&first_name=Radio&last_name=K&user_url=www.radiok.info&aim=radioko&invitation_code=1979&middle_name=Billy</code>
				<h3><?php _e("Customize User Notification Email", "regplus"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom User Email Notification", "regplus"); ?></label></th>
						<td><label><input type="checkbox" name="custom_user_message" id="custom_user_message" value="1" <?php if ( $options["custom_user_message"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable", "regplus"); ?></label></td>
					</tr>
				</table>
				<div id="custom_user_message_settings">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="user_message_from_email"><?php _e("From Email", "regplus"); ?></label></th>
							<td><input type="text" name="user_message_from_email" id="user_message_from_email" style="width:250px;" value="<?php echo $options["user_message_from_email"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_from_name"><?php _e("From Name", "regplus"); ?></label></th>
							<td><input type="text" name="user_message_from_name" id="user_message_from_name" style="width:250px;" value="<?php echo $options["user_message_from_name"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_subject"><?php _e("Subject", "regplus"); ?></label></th>
							<td><input type="text" name="user_message_subject" id="user_message_subject" style="width:350px;" value="<?php echo $options["user_message_subject"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_body"><?php _e("User Message", "regplus"); ?></label></th>
							<td>
								<p><strong><?php _e("Replacement Keys", "regplus"); ?>:</strong> %user_login %user_password% %user_email% %blogname% %site_url%<?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent%</p>
								<textarea name="user_message_body" id="user_message_body" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options["user_message_body"]; ?></textarea><br />
								<label><input type="checkbox" name="send_user_message_in_html" value="1" <?php if ( $options["send_user_message_in_html"]) echo "checked='checked'"; ?> /><?php _e("Send as HTML", "regplus"); ?></label>
								&nbsp;<label><input type="checkbox" name="user_message_newline_as_br" value="1" <?php if ( $options["user_message_newline_as_br"]) echo "checked='checked'"; ?> /><?php _e("Convert new lines to &lt;br/> tags (HTML only)", "regplus"); ?></label>
							</td>
						</tr>
						<tr valign="top" class="disabled">
							<th scope="row"><label for="user_message_login_link"><?php _e("Login URL", "regplus"); ?></label></th>
							<td><input type="text" name="user_message_login_link" id="user_message_login_link" style="width:250px;" value="<?php echo $options["user_message_login_link"]; ?>" /><small><?php _e("This will redirect the users login after registration.", "regplus"); ?></small></td>
						</tr>
					</table>
				</div>
				<h3><?php _e("Customize Admin Notification Email", "regplus"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label for="disable_admin_message"><?php _e("Admin Email Notification", "regplus"); ?></label></th>
						<td><label><input type="checkbox" name="disable_admin_message" id="disable_admin_message" value="1" <?php if ( $options["disable_admin_message"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Disable", "regplus"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom Admin Email Notification", "regplus"); ?></label></th>
						<td><label><input type="checkbox" name="custom_admin_message" id="custom_admin_message" value="1" <?php if ( $options["custom_admin_message"]) echo "checked='checked'"; ?> />&nbsp;<?php _e("Enable", "regplus"); ?></label></td>
					</tr>
				</table>
				<div id="custom_admin_message_settings">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="admin_message_from_email"><?php _e("From Email", "regplus"); ?></label></th>
							<td><input type="text" name="admin_message_from_email" id="admin_message_from_email" style="width:250px;" value="<?php echo $options["admin_message_from_email"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_from_name"><?php _e("From Name", "regplus"); ?></label></th>
							<td><input type="text" name="admin_message_from_name" id="admin_message_from_name" style="width:250px;" value="<?php echo $options["admin_message_from_name"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_subject"><?php _e("Subject", "regplus"); ?></label></th>
							<td><input type="text" name="admin_message_subject" id="admin_message_subject" style="width:350px;" value="<?php echo $options["admin_message_subject"]; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_body"><?php _e("Admin Message", "regplus"); ?></label></th>
							<td>
								<p><strong><?php _e("Replacement Keys", "regplus"); ?>:</strong> %user_login% %user_email% %blogname% %site_url%<?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent%</p>
								<textarea name="admin_message_body" id="admin_message_body" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options["admin_message_body"]; ?></textarea><br />
								<label><input type="checkbox" name="send_admin_message_in_html" value="1" <?php if ( $options["send_admin_message_in_html"]) echo "checked='checked'"; ?> /><?php _e("Send as HTML", "regplus"); ?></label>
								&nbsp;<label><input type="checkbox" name="admin_message_newline_as_br" value="1" <?php if ( $options["admin_message_newline_as_br"]) echo "checked='checked'"; ?> /><?php _e("Convert new lines to &lt;br/> tags (HTML only)", "regplus"); ?></label>
							</td>
						</tr>
					</table>
				</div>
				<br />
				<h3><?php _e("Custom CSS for Register & Login Pages", "regplus"); ?></h3>
				<p><?php _e("CSS Rule Example:", "regplus"); ?><code>#user_login{ font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="custom_registration_page_css"><?php _e("Custom Register CSS", "regplus"); ?></label></th>
						<td><textarea name="custom_registration_page_css" id="custom_registration_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options["custom_registration_page_css"]; ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="custom_login_page_css"><?php _e("Custom Login CSS", "regplus"); ?></label></th>
						<td><textarea name="custom_login_page_css" id="custom_login_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options["custom_login_page_css"]; ?></textarea></td>
					</tr>
				</table>
				<p><input type="button" class="button" value="<?php _e("Preview Registraton Page", "regplus"); ?>" name="preview" onclick="window.open('<?php echo wp_login_url(), "?action=register"; ?>');" /></p>
				<p class="submit"><input type="submit" class="button-primary" value="<?php _e("Save Changes", "regplus"); ?>" name="submit" /></p>
				
			</form>
			</div>
			<?php
		}

		function UpdateSettings() {
			/*
			UpdateSettings is much harsher than it used to be, does not load old settings and
			update them, just pulls whatever is on the current settings page
			*/
			check_admin_referer("register-plus-redux-update-settings");
			//$current_options = get_option("register_plus_redux_options");
			$options = array();
			$options["user_set_password"] = $_POST["user_set_password"];
			$options["show_password_meter"] = $_POST["show_password_meter"];
			$options["message_empty_password"] = $_POST["message_empty_password"];
			$options["message_short_password"] = $_POST["message_short_password"];
			$options["message_bad_password"] = $_POST["message_bad_password"];
			$options["message_good_password"] = $_POST["message_good_password"];
			$options["message_strong_password"] = $_POST["message_strong_password"];
			$options["message_mismatch_password"] = $_POST["message_mismatch_password"];
			$options["custom_logo_url"] = $_POST["custom_logo_url"];
			if ( $_FILES["upload_custom_logo"]["name"] ) {
				$upload = wp_upload_bits($_FILES["upload_custom_logo"]["name"], null, file_get_contents($_FILES["upload_custom_logo"]["tmp_name"]));
				if ( !$upload["error"] ) $options["custom_logo_url"] = $upload["url"];
			}
			if ( $_POST["remove_logo"] ) $options["custom_logo_url"] = "";
			$options["verify_user_email"] = $_POST["verify_user_email"];
			$options["delete_unverified_users_after"] = $_POST["delete_unverified_users_after"];
			$options["verify_user_admin"] = $_POST["verify_user_admin"];
			$options["enable_invitation_code"] = $_POST["enable_invitation_code"];
			$options["enable_invitation_tracking_widget"] = $_POST["enable_invitation_tracking_widget"];
			$options["require_invitation_code"] = $_POST["require_invitation_code"];
			$options["invitation_code_bank"] = $_POST["invitation_code_bank"];
			$options["allow_duplicate_emails"] = $_POST["allow_duplicate_emails"];

			$options["show_fields"] = $_POST["show_fields"];
			$options["required_fields"] = $_POST["required_fields"];
			$options["required_fields_style"] = $_POST["required_fields_style"];
			$options["show_disclaimer"] = $_POST["show_disclaimer"];
			$options["message_disclaimer_title"] = $_POST["message_disclaimer_title"];
			$options["message_disclaimer"] = $_POST["message_disclaimer"];
			$options["message_disclaimer_agree"] = $_POST["message_disclaimer_agree"];
			$options["show_license"] = $_POST["show_license"];
			$options["message_license_title"] = $_POST["message_license_title"];
			$options["message_license"] = $_POST["message_license"];
			$options["message_license_agree"] = $_POST["message_license_agree"];
			$options["show_privacy_policy"] = $_POST["show_privacy_policy"];
			$options["message_privacy_policy_title"] = $_POST["message_privacy_policy_title"];
			$options["message_privacy_policy"] = $_POST["message_privacy_policy"];
			$options["message_privacy_policy_agree"] = $_POST["message_privacy_policy_agree"];

			if ( $_POST["custom_field_name"] ) {
				foreach ( $_POST["custom_field_name"] as $k => $v ) {
					$custom_fields[$k] = array("custom_field_name" => $v,
						"custom_field_type" => $_POST["custom_field_type"][$k],
						"custom_field_options" => $_POST["custom_field_options"][$k],
						"show_on_profile" => $_POST["show_on_profile"][$k],
						"show_on_registration" => $_POST["show_on_registration"][$k],
						"required_on_registration" => $_POST["required_on_registration"][$k]);
				}
			}
			$options["datepicker_firstdayofweek"] = $_POST["datepicker_firstdayofweek"];
			$options["datepicker_dateformat"] = $_POST["datepicker_dateformat"];
			$options["datepicker_startdate"] = $_POST["datepicker_startdate"];
			$options["datepicker_calyear"] = $_POST["datepicker_calyear"];
			$options["datepicker_calmonth"] = $_POST["datepicker_calmonth"];

			$options["custom_user_message"] = $_POST["custom_user_message"];
			$options["user_message_from_email"] = $_POST["user_message_from_email"];
			$options["user_message_from_name"] = $_POST["user_message_from_name"];
			$options["user_message_subject"] = $_POST["user_message_subject"];
			$options["user_message_body"] = $_POST["user_message_body"];
			$options["send_user_message_in_html"] = $_POST["send_user_message_in_html"];
			$options["user_message_newline_as_br"] = $_POST["user_message_newline_as_br"];
			$options["user_message_login_link"] = $_POST["user_message_login_link"];

			$options["disable_admin_message"] = $_POST["disable_admin_message"];
			$options["custom_admin_message"] = $_POST["custom_admin_message"];
			$options["admin_message_from_email"] = $_POST["admin_message_from_email"];
			$options["admin_message_from_name"] = $_POST["admin_message_from_name"];
			$options["admin_message_subject"] = $_POST["admin_message_subject"];
			$options["admin_message_body"] = $_POST["admin_message_body"];
			$options["send_admin_message_in_html"] = $_POST["send_admin_message_in_html"];
			$options["admin_message_newline_as_br"] = $_POST["admin_message_newline_as_br"];

			$options["custom_registration_page_css"] = $_POST["custom_registration_page_css"];
			$options["custom_login_page_css"] = $_POST["custom_login_page_css"];

			update_option("register_plus_redux_options", $options);
			update_option("register_plus_redux_custom_fields", $custom_fields);
			$_POST["notice"] = __("Settings Saved", "regplus");
		}

		function UnverifiedUsersPage() {
			if ( $_POST["notice"] ) echo "\n<div id='message' class='updated fade'><p><strong>", $_POST["notice"], "</strong></p></div>";
			global $wpdb;
			$unverified = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE user_login LIKE '%unverified_%'");
			$options = get_option("register_plus_redux_options");
			?>
			<div class="wrap">
				<h2><?php _e("Unverified Users", "regplus") ?></h2>
				<form id="verify-filter" method="post" action="">
				<?php wp_nonce_field("register-plus-redux-unverified-users"); ?>
				<div class="tablenav">
					<div class="alignleft">
						<input value="<?php _e("Verify Checked Users", "regplus"); ?>" name="verify_users" class="button-secondary" type="submit">&nbsp;
						<?php if ( $options["verify_user_email"] ) { ?>
						<input value="<?php _e("Resend Verification E-mail", "regplus"); ?>" name="send_verification_email" class="button-secondary" type="submit">
						<?php } ?>
						&nbsp;<input value="<?php _e("Delete", "regplus"); ?>" name="delete_users" class="button-secondary delete" type="submit">
					</div>
					<br class="clear">
				</div>
				<br class="clear">
				<table class="widefat">
					<thead>
						<tr class="thead">
							<th scope="col" class="check-column"><input onclick="checkAll(document.getElementById('verify-filter'));" type="checkbox"></th>
							<th><?php _e("Unverified ID", "regplus"); ?></th>
							<th><?php _e("User Name", "regplus"); ?></th>
							<th><?php _e("E-mail", "regplus"); ?></th>
							<th><?php _e("Role", "regplus"); ?></th>
						</tr>
					</thead>
					<tbody id="users" class="list:user user-list">
						<?php foreach ( $unverified as $un ) {
							if ( $alt ) $alt = ""; else $alt = "alternate";
							$user_object = new WP_User($un->ID);
							$roles = $user_object->roles;
							$role = array_shift($roles);
							if ( $options["verify_user_email"] ) $user_login = get_user_meta($un->ID, "email_verification_user_login", true);
							elseif ( $options["verify_user_admin"] ) $user_login = get_user_meta($un->ID, "admin_verification_user_login", true);
						?>
						<tr id="user-1" class="<?php echo $alt; ?>">
							<th scope="row" class="check-column"><input name="selected_users[]" id="user_<?php echo $un->ID; ?>" class="administrator" value="<?php echo $un->ID; ?>" type="checkbox"></th>
							<td><strong><?php echo $un->user_login; ?></strong></td>
							<td><strong><?php echo $user_login; ?></strong></td>
							<td><a href="mailto:<?php echo $un->user_email; ?>" title="<?php _e("e-mail: ", "regplus"); echo $un->user_email; ?>"><?php echo $un->user_email; ?></a></td>
							<td><?php echo ucwords($role); ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
				</form>
			</div>
			<?php
		}

		function AdminValidate() {
			check_admin_referer("register-plus-redux-unverified-users");
			if ( is_array($_POST["selected_users"]) ) {
				foreach ( $_POST["selected_users"] as $user_id ) {
					global $wpdb;
					$options = get_option("register_plus_redux_options");
					if ( $options["verify_user_email"] ) {
						$stored_user_login = get_user_meta($user_id, "email_verification_user_login", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = '$stored_user_login' WHERE ID = '$user_id'") );
						delete_user_meta($user_id, "email_verification_code");
						delete_user_meta($user_id, "email_verification_sent");
						delete_user_meta($user_id, "email_verification_user_login");
					} elseif ( $options["verify_user_admin"] ) {
						$stored_user_login = get_user_meta($user_id, "admin_verification_user_login", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = '$stored_user_login' WHERE ID = '$user_id'") );
						delete_user_meta($user_id, "admin_verification_user_login");
					}
					if ( !$options["user_set_password"] ) {
						$plaintext_pass = wp_generate_password();
						update_user_option( $user_id, "default_password_nag", true, true );
						wp_set_password($plaintext_pass, $user_id);
					}
					$this->sendUserMessage( $user_id, $plaintext_pass );
				}
				$_POST["notice"] = __("Users Verified", "regplus");
			} else {
				$_POST["notice"] = __("<strong>Error:</strong> No users selected.", "regplus");
			}
		}

		function AdminEmailValidate() {
			check_admin_referer("register-plus-redux-unverified-users");
			if ( is_array($_POST["selected_users"]) ) {
				foreach ( $_POST["selected_users"] as $user_id )
					$this->sendEmailVerificationMessage($user_id);
				$_POST["notice"] = __("Verification Emails have been re-sent", "regplus");
			} else {
				$_POST["notice"] = __("<strong>Error:</strong> No users selected.", "regplus");
			}
		}

		function AdminDeleteUnvalidated() {
			check_admin_referer("register-plus-redux-unverified-users");
			if ( is_array($_POST["selected_users"]) ) {
				require_once(ABSPATH.'/wp-admin/includes/user.php');
				foreach ( $_POST["selected_users"] as $user_id ) {
					if ( $user_id ) wp_delete_user($user_id);
				}
				$_POST["notice"] = __("Users Deleted", "regplus");
			} else {
				$_POST["notice"] = __("<strong>Error:</strong> No users selected.", "regplus");
			}
		}

		function LoginHead() {
			$options = get_option("register_plus_redux_options");
			if ( $options["custom_logo_url"] ) {
				list($width, $height, $type, $attr) = getimagesize($options["custom_logo_url"]);
				wp_print_scripts("jquery");
				echo "\n<script type=\"text/javascript\">";
				echo "\njQuery(document).ready(function() {";
				echo "\njQuery('#login h1 a').attr('href', '", get_option("home"), "');";
				echo "\njQuery('#login h1 a').attr('title', '", get_option("blogname"), " - ", get_option("blogdescription"). "');";
				echo "\n});";
				echo "\n</script>";
				echo "\n<style type=\"text/css\">";
				echo "\n#login h1 a {";
				echo "\nbackground-image: url(", $options["custom_logo_url"], ");";
				echo "\nbackground-position:center top;";
				echo "\nwidth: $width", "px;";
				echo "\nmin-width: 292px;";
				echo "\nheight: $height", "px;";
				echo "\n</style>";
			}
			if ( $_GET["checkemail"] == "registered" && ($options["verify_user_admin"] || $options["verify_user_email"]) ) {
				//label, #user_login, #user_pass, .forgetmenot, #wp-submit, .message { display: none; }
				echo "\n<style type=\"text/css\">";
				echo "\np { display: none; }";
				echo "\n#message, #backtoblog { display: block; }";
				echo "\n</style>";
			}
			if ( $_GET["action"] == "register" ) {
				$custom_fields = get_option("register_plus_redux_custom_fields");
				if ( !is_array($custom_fields) ) $custom_fields = array();
				foreach ( $custom_fields as $k => $v ) {
					if ( $v["show_on_registration"] ) {
						if ( $v["custom_field_type"] == "text" ) {
							if ( !$show_custom_text_fields )
								$show_custom_text_fields = '#'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_text_fields = ', #'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "select" ) {
							if ( !$show_custom_select_fields )
								$show_custom_select_fields = '#'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_select_fields = ', #'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "checkbox" ) {
							if ( !$show_custom_checkbox_fields )
								$show_custom_checkbox_fields = '.'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_checkbox_fields = ', .'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "radio" ) {
							if ( !$show_custom_radio_fields )
								$show_custom_radio_fields = '.'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_radio_fields = ', .'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "textarea" ) {
							if ( !$show_custom_textarea_fields )
								$show_custom_textarea_fields = '#'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_textarea_fields = ', #'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "date" ) {
							if ( !$show_custom_date_fields )
								$show_custom_date_fields = '#'.$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_date_fields = ', #'.$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["required_on_registration"] ) {
							$required_custom_fields .= '#'.$this->fnSanitizeFieldName($v["custom_field_name"]).', ';
						}
					}
				}

				if ( $options["show_fields"][0] ) $show_fields = "#".implode(", #", $options["show_fields"]);
				if ( $options["required_fields"][0] ) $required_fields = "#".implode(", #", $options["required_fields"]);

				echo "\n<style type=\"text/css\">";
				echo "\nsmall { display:block; margin-bottom:8px; }";
				if ( $show_fields ) echo "\n$show_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( in_array("about", $options["show_fields"]) ) echo "\n#about { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( $show_custom_text_fields ) echo "\n$show_custom_text_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( $show_custom_select_fields ) echo "\n$show_custom_select_fields { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( $show_custom_checkbox_fields ) echo "\n$show_custom_checkbox_fields { font-size:18px; }";
				if ( $show_custom_radio_fields ) echo "\n$show_custom_radio_fields { font-size:18px; }";
				if ( $show_custom_textarea_fields ) echo "\n$show_custom_textarea_fields { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( $show_custom_date_fields ) echo "\n$show_custom_date_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( $options["show_disclaimer"] ) { echo "\n#disclaimer { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_disclaimer"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( $options["show_license"] ) { echo "\n#license { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_license"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( $options["show_privacy_policy"] ) { echo "\n#privacy_policy { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_privacy_policy"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( $options["show_disclaimer"] || $options["show_license"] || $options["show_privacy_policy"] ) echo "\n.accept_check { display:block; margin-bottom:8px; }";
				if ( $show_custom_date_fields ) {
					echo "\na.dp-choose-date { float: left; width: 16px; height: 16px; padding: 0; margin: 5px 3px 0; display: block; text-indent: -2000px; overflow: hidden; background: url('"; echo plugins_url("datepicker/calendar.png", __FILE__); echo "') no-repeat; }";
					echo "\na.dp-choose-date.dp-disabled { background-position: 0 -20px; cursor: default; } /* makes the input field shorter once the date picker code * has run (to allow space for the calendar icon */";
					echo "\ninput.dp-applied { width: 140px; float: left; }";
				}
				if ( $options["user_set_password"] ) {
					echo "\n#reg_passmail { display: none; }";
					echo "\n#pass1, #pass2 { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( $options["show_password_meter"] ) echo "\n#pass-strength-result { width: 97%; padding: 3px; margin-top:0px; margin-right:6px; margin-bottom:4px; border: 1px solid; text-align: center; }";
				}
				if ( $options["enable_invitation_code"] ) echo "\n#invitation_code { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:4px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				echo "\n#user_login, #user_email { ", $options["required_fields_style"], "} ";
				if ( $required_fields ) echo "\n$required_fields { ", $options["required_fields_style"], " }";
				if ( $required_custom_fields ) echo "\n$required_custom_fields { ", $options["required_fields_style"], " }";
				if ( $options["user_set_password"] ) echo "\n#pass1, #pass2 { ", $options["required_fields_style"], " }";
				if ( $options["require_invitation_code"] ) echo "\n#invitation_code { ", $options["required_fields_style"], " }";
				if ( $options["custom_registration_page_css"] ) echo "\n", $options["custom_registration_page_css"];
				echo "\n</style>";

				if ( $show_custom_date_fields ) {
					//wp_enqueue_script("jquery");
					//wp_enqueue_script("jquery-ui-core");
					//wp_enqueue_script(plugins_url("js/jquery.ui.datepicker.js", __FILE__));
					?>
					<!-- required plugins -->
					<script type="text/javascript" src="<?php echo plugins_url("datepicker/date.js", __FILE__); ?>"></script>
					<!--[if IE]><script type="text/javascript" src="<?php echo plugins_url("datepicker/jquery.bgiframe.js", __FILE__); ?>"></script><![endif]-->
					<!-- jquery.datePicker.js -->
					<script type="text/javascript" src="<?php echo plugins_url("datepicker/jquery.datePicker.js", __FILE__); ?>"></script>
					<link href="<?php echo plugins_url("datepicker/datePicker.css", __FILE__); ?>" rel="stylesheet" type="text/css" />
					<script type="text/javascript">
					jQuery.dpText = {
						TEXT_PREV_YEAR	:	'<?php _e("Previous year", "regplus"); ?>',
						TEXT_PREV_MONTH	:	'<?php _e("Previous month", "regplus"); ?>',
						TEXT_NEXT_YEAR	:	'<?php _e("Next year", "regplus"); ?>',
						TEXT_NEXT_MONTH	:	'<?php _e("Next Month", "regplus"); ?>',
						TEXT_CLOSE	:	'<?php _e("Close", "regplus"); ?>',
						TEXT_CHOOSE_DATE:	'<?php _e("Choose Date", "regplus"); ?>'
					}
					Date.dayNames = ['<?php _e("Monday", "regplus"); ?>', '<?php _e("Tuesday", "regplus"); ?>', '<?php _e("Wednesday", "regplus"); ?>', '<?php _e("Thursday", "regplus"); ?>', '<?php _e("Friday", "regplus"); ?>', '<?php _e("Saturday", "regplus"); ?>', '<?php _e("Sunday", "regplus"); ?>'];
					Date.abbrDayNames = ['<?php _e("Mon", "regplus"); ?>', '<?php _e("Tue", "regplus"); ?>', '<?php _e("Wed", "regplus"); ?>", '<?php _e("Thu", "regplus"); ?>', '<?php _e("Fri", "regplus"); ?>', '<?php _e("Sat", "regplus"); ?>', '<?php _e("Sun", "regplus"); ?>'];
					Date.monthNames = ['<?php _e("January", "regplus"); ?>', '<?php _e("February", "regplus"); ?>', '<?php _e("March", "regplus"); ?>', '<?php _e("April", "regplus"); ?>', '<?php _e("May", "regplus"); ?>', '<?php _e("June", "regplus"); ?>', '<?php _e("July", "regplus"); ?>', '<?php _e("August", "regplus"); ?>', '<?php _e("September", "regplus"); ?>', '<?php _e("October", "regplus"); ?>', '<?php _e("November", "regplus"); ?>', '<?php _e("December", "regplus"); ?>'];
					Date.abbrMonthNames = ['<?php _e("Jan", "regplus"); ?>', '<?php _e("Feb", "regplus"); ?>', '<?php _e("Mar", "regplus"); ?>', '<?php _e("Apr", "regplus"); ?>', '<?php _e("May", "regplus"); ?>', '<?php _e("Jun", "regplus"); ?>', '<?php _e("Jul", "regplus"); ?>', '<?php _e("Aug", "regplus"); ?>', '<?php _e("Sep", "regplus"); ?>', '<?php _e("Oct", "regplus"); ?>', '<?php _e("Nov", "regplus"); ?>', '<?php _e("Dec", "regplus"); ?>'];
					Date.firstDayOfWeek = '<?php echo $options["datepicker_firstdayofweek"]; ?>';
					Date.format = '<?php echo $options["datepicker_dateformat"]; ?>';
					jQuery(function() {
						jQuery('.date-pick').datePicker({
							clickInput: true,
							startDate: '<?php echo $options["datepicker_startdate"]; ?>',
							year: '<?php echo $options["datepicker_calyear"]; ?>',
							month: '<?php if ( $options["datepicker_calmonth"] != "cur") echo $options["datepicker_calmonth"]; else echo date("n")-1; ?>'
						})
					});
					</script>
					<?php
				}
				if ( $options["user_set_password"] && $options["show_password_meter"] ) {
					wp_print_scripts("jquery");
					?>
					<script type="text/javascript">
						/* <![CDATA[ */
							pwsL10n={
								empty: "<?php echo $options["message_empty_password"]; ?>",
								short: "<?php echo $options["message_short_password"]; ?>",
								bad: "<?php echo $options["message_bad_password"]; ?>",
								good: "<?php echo $options["message_good_password"]; ?>",
								strong: "<?php echo $options["message_strong_password"]; ?>",
								mismatch: "<?php echo $options["message_mismatch_password"]; ?>"
							}
						/* ]]> */
						function check_pass_strength() {
							var pass1 = jQuery('#pass1').val(), user = jQuery('#user_login').val(), pass2 = jQuery('#pass2').val(), strength;
							jQuery('#pass-strength-result').removeClass('short bad good strong mismatch');
							if ( ! pass1 ) {
								jQuery('#pass-strength-result').html( pwsL10n.empty );
								return;
							}
							strength = passwordStrength(pass1, user, pass2);
							switch ( strength ) {
								case 2:
									jQuery('#pass-strength-result').addClass('bad').html( pwsL10n['bad'] );
									break;
								case 3:
									jQuery('#pass-strength-result').addClass('good').html( pwsL10n['good'] );
									break;
								case 4:
									jQuery('#pass-strength-result').addClass('strong').html( pwsL10n['strong'] );
									break;
								case 5:
									jQuery('#pass-strength-result').addClass('mismatch').html( pwsL10n['mismatch'] );
									break;
								default:
									jQuery('#pass-strength-result').addClass('short').html( pwsL10n['short'] );
							}
						}
						function passwordStrength(password1, username, password2) {
							var shortPass = 1, badPass = 2, goodPass = 3, strongPass = 4, mismatch = 5, symbolSize = 0, natLog, score;
							// password 1 != password 2
							if ( (password1 != password2) && password2.length > 0)
								return mismatch
							//password < 4
							if ( password1.length < 4 )
								return shortPass
							//password1 == username
							if ( password1.toLowerCase() == username.toLowerCase() )
								return badPass;
							if ( password1.match(/[0-9]/) )
								symbolSize +=10;
							if ( password1.match(/[a-z]/) )
								symbolSize +=26;
							if ( password1.match(/[A-Z]/) )
								symbolSize +=26;
							if ( password1.match(/[^a-zA-Z0-9]/) )
								symbolSize +=31;
							natLog = Math.log( Math.pow(symbolSize, password1.length) );
								score = natLog / Math.LN2;
							if (score < 40 )
								return badPass
							if (score < 56 )
								return goodPass
							return strongPass;
						}
						jQuery(document).ready( function() {
							jQuery('#pass1').val('').keyup( check_pass_strength );
							jQuery('#pass2').val('').keyup( check_pass_strength );
						});
					</script>
					<?php
				}
			} else {
				if ( $options["custom_login_page_css"] ) {
					echo "\n<style type=\"text/css\">";
					echo "\n", $options["custom_login_page_css"];
					echo "\n</style>";
				}
			}
		}

		function AlterRegistrationForm() {
			$options = get_option("register_plus_redux_options");
			$tabindex = 21;
			if ( in_array("first_name", $options["show_fields"]) ) {
				if ( isset($_GET["first_name"]) ) $_POST["first_name"] = $_GET["first_name"];
				echo "\n<p><label>", _e("First Name", "regplus"), "<br /><input type='text' name='first_name' id='first_name' class='input' value='", $_POST["first_name"],"' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("last_name", $options["show_fields"]) ) {
				if ( isset($_GET["last_name"]) ) $_POST["last_name"] = $_GET["last_name"];
				echo "\n<p><label>", _e("Last Name", "regplus"), "<br /><input type='text' name='last_name' id='last_name' class='input' value='", $_POST["last_name"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("user_url", $options["show_fields"]) ) {
				if ( isset($_GET["user_url"]) ) $_POST["user_url"] = $_GET["user_url"];
				echo "\n<p><label>", _e("Website", "regplus"), "<br /><input type='text' name='user_url' id='user_url' class='input' value='", $_POST["user_url"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("aim", $options["show_fields"]) ) {
				if ( isset($_GET["aim"]) ) $_POST["aim"] = $_GET["aim"];
				echo "\n<p><label>", _e("AIM", "regplus"), "<br /><input type='text' name='aim' id='aim' class='input' value='", $_POST["aim"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("yahoo", $options["show_fields"]) ) {
				if ( isset($_GET["yahoo"]) ) $_POST["yahoo"] = $_GET["yahoo"];
				echo "\n<p><label>", _e("Yahoo IM", "regplus"), "<br /><input type='text' name='yahoo' id='yahoo' class='input' value='", $_POST["yahoo"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("jabber", $options["show_fields"]) ) {
				if ( isset($_GET["jabber"]) ) $_POST["jabber"] = $_GET["jabber"];
				echo "\n<p><label>", _e("Jabber / Google Talk", "regplus"), "<br /><input type='text' name='jabber' id='jabber' class='input' value='", $_POST["jabber"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("about", $options["show_fields"]) ) {
				if ( isset($_GET["about"]) ) $_POST["about"] = $_GET["about"];
				echo "\n<p><label for='about'>", _e("About Yourself", "regplus"), "</label><br />";
				echo "\n<small>", _e("Share a little biographical information to fill out your profile. This may be shown publicly.", "regplus"), "</small><br />";
				echo "\n<textarea name='about' id='about' cols='25' rows='5' tabindex='$tabindex'>", stripslashes($_POST["about"]), "</textarea></p>";
				$tabindex++;
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v["show_on_registration"] ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( isset($_GET[$key]) ) $_POST[$key] = $_GET[$key];
					switch ( $v["custom_field_type"] ) {
						case "text":
							echo "\n<p><label>", $v["custom_field_name"], "<br /><input type='text' name='$key' id='$key' value='", $_POST[$key], "' size='25' tabindex='$tabindex' /></label></p>";
							$tabindex++;
							break;
						case "select":
							echo "\n<p><label>", $v["custom_field_name"], "<br />";
							echo "\n	<select name='$key' id='$key' tabindex='$tabindex'>";
							$tabindex++;
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								echo "<option value='$custom_field_option'";
								if ( $_POST[$key] == $custom_field_option ) echo " selected='selected'";
								echo ">$custom_field_option</option>";
							}
							echo "</select>";
							echo "\n</label></p>";
							break;
						case "checkbox":
							echo "\n<p style='margin-bottom:16px;'><label>", $v["custom_field_name"], "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								echo "\n<input type='checkbox' name='$key"."[]' id='", $this->fnSanitizeFieldName($custom_field_option), "'";
								//if ( in_array($custom_field_option, $_POST[$key])) echo " checked='checked' ";
								echo " value='$custom_field_option' tabindex='$tabindex' /><label for='", $this->fnSanitizeFieldName($custom_field_option), "' class='", $this->fnSanitizeFieldName($v["custom_field_name"]), "'>&nbsp;$custom_field_option</label><br />";
								$tabindex++;
							}
							echo "\n</p>";
							break;
						case "radio":
							echo "\n<p style='margin-bottom:16px;'><label>", $v["custom_field_name"], "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								echo "\n<input type='radio' name='$key' id='", $this->fnSanitizeFieldName($custom_field_option), "'";
								//if ( in_array($custom_field_option, $_POST[$key])) echo " checked='checked' ";
								echo " value='$custom_field_option' tabindex='$tabindex' /><label for='", $this->fnSanitizeFieldName($custom_field_option), "' class='", $this->fnSanitizeFieldName($v["custom_field_name"]), "' >&nbsp;$custom_field_option</label><br />";
								$tabindex++;
							}
							echo "\n</p>";
							break;
						case "textarea":
							echo "\n<p><label>", $v["custom_field_name"], "<br /><textarea name='$key' id='$key' cols='25' rows='5' tabindex='$tabindex'>", $_POST[$key], "</textarea></label></p>";
							$tabindex++;
							break;
						case "date":
							echo "\n<p><label>", $v['custom_field_name'], "<br /><input type='text' name='$key' id='$key' value='", $_POST[$key], "' size='25' tabindex='$tabindex' /></label></p>";
							$tabindex++;
							break;
						case "hidden":
							echo "\n<input type='hidden' name='$key' id='$key' value='", $_POST[$key], "' tabindex='$tabindex' />";
							$tabindex++;
							break;
					}
				}
			}
			if ( $options["user_set_password"] ) {
				echo "\n<p><label>", _e("Password", "regplus"), "<br /><input type='password' autocomplete='off' name='pass1' id='pass1' value='", $_POST["password"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				echo "\n<p><label>", _e("Confirm Password", "regplus"), "<br /><input type='password' autocomplete='off' name='pass2' id='pass2' value='", $_POST["password"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				if ( $options["show_password_meter"]) {
					echo "\n<div id='pass-strength-result'>", $options["message_empty_password"], "</div>";
					echo "\n<small>", _e("Your password must be at least seven characters long. To make your password stronger, use upper and lower case letters, numbers, and the following symbols !@#$%^&amp;*()", "regplus"), "</small>";
				}
			}
			if ( $options["enable_invitation_code"] ) {
				if ( isset($_GET["invitation_code"]) ) $_POST["invitation_code"] = $_GET["invitation_code"];
				echo "<p><label>", _e("Invitation Code", "regplus"), "<br /><input type='text' name='invitation_code' id='invitation_code' class='input' value='", $_POST["invitation_code"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				if ($options["require_invitation_code"])
					echo "\n<small>", _e("This website is currently closed to public registrations. You will need an invitation code to register.", "regplus"), "</small>";
				else
					echo "\n<small>", _e("Have an invitation code? Enter it here. (This is not required)", "regplus"), "</small>";
			}
			if ( $options["show_disclaimer"] ) {
				echo "\n<p>";
				echo "\n	<label>", $options["message_disclaimer_title"], "</label><br />";
				echo "\n	<span name='disclaimer' id='disclaimer'>", $options["message_disclaimer"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_disclaimer' id='accept_disclaimer' value='1'"; if ( $_POST["accept_disclaimer"] ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_disclaimer_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
			if ( $options["show_license"] ) {
				echo "\n<p>";
				echo "\n	<label>", $options["message_license_title"], "</label><br />";
				echo "\n	<span name='license' id='license'>", $options["message_license"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_license' id='accept_license' value='1'"; if ( $_POST["accept_license_agreement"] ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_license_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
			if ( $options["show_privacy_policy"] ) {
				echo "\n<p>";
				echo "\n	<label>", $options["message_privacy_policy_title"], "</label><br />";
				echo "\n	<span name='privacy_policy' id='privacy_policy'>", $options["message_privacy_policy"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_privacy_policy' id='accept_privacy_policy' value='1'"; if ( $_POST["accept_privacy_policy"] ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_privacy_policy_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
		}

		function CheckRegistration( $sanitized_user_login, $user_email, $errors ) {
			$options = get_option("register_plus_redux_options");
			if ( in_array("first_name", $options["show_fields"]) && in_array("first_name", $options["required_fields"]) ) {
				if ( empty($_POST["first_name"]) || $_POST["first_name"] == "" ) {
					$errors->add("empty_first_name", __("<strong>ERROR</strong>: Please enter your first name.", "regplus"));
				}
			}
			if ( in_array("last_name", $options["show_fields"]) && in_array("last_name", $options["required_fields"]) ) {
				if ( empty($_POST["last_name"]) || $_POST["last_name"] == "" ) {
					$errors->add("empty_last_name", __("<strong>ERROR</strong>: Please enter your last name.", "regplus"));
				}
			}
			if ( in_array("user_url", $options["show_fields"]) && in_array("user_url", $options["required_fields"]) ) {
				if ( empty($_POST["user_url"]) || $_POST["user_url"] == "" ) {
					$errors->add("empty_user_url", __("<strong>ERROR</strong>: Please enter your website URL.", "regplus"));
				}
			}
			if ( in_array("aim", $options["show_fields"]) && in_array("aim", $options["required_fields"]) ) {
				if ( empty($_POST["aim"]) || $_POST["aim"] == "" ) {
					$errors->add("empty_aim", __("<strong>ERROR</strong>: Please enter your AIM username.", "regplus"));
				}
			}
			if ( in_array("yahoo", $options["show_fields"]) && in_array("yahoo", $options["required_fields"]) ) {
				if ( empty($_POST["yahoo"]) || $_POST["yahoo"] == "" ) {
					$errors->add("empty_yahoo", __("<strong>ERROR</strong>: Please enter your Yahoo IM username.", "regplus"));
				}
			}
			if ( in_array("jabber", $options["show_fields"]) && in_array("jabber", $options["required_fields"]) ) {
				if ( empty($_POST["jabber"]) || $_POST["jabber"] == "" ) {
					$errors->add("empty_jabber", __("<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.", "regplus"));
				}
			}
			if ( in_array("about", $options["show_fields"]) && in_array("about", $options["required_fields"]) ) {
				if ( empty($_POST["about"]) || $_POST["about"] == "" ) {
					$errors->add("empty_about", __("<strong>ERROR</strong>: Please enter some information about yourself.", "regplus"));
				}
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v["show_on_registration"] && $v["required_on_registration"] ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( empty($_POST[$key]) || $_POST[$key] == "" ) {
						$errors->add("empty_$key", __("<strong>ERROR</strong>: Please complete ".$v["custom_field_name"].".", "regplus"));
					}
				}
			}
			if ( $options["user_set_password"] ) {
				if ( empty($_POST["pass1"]) || $_POST["pass1"] == "" || empty($_POST["pass2"]) || $_POST["pass2"] == "" ) {
					$errors->add("empty_password", __("<strong>ERROR</strong>: Please enter a password.", "regplus"));
				} elseif ( $_POST["pass1"] != $_POST["pass2"] ) {
					$errors->add("password_mismatch", __("<strong>ERROR</strong>: Your password does not match.", "regplus"));
				} elseif ( strlen($_POST["pass1"]) < 6 ) {
					$errors->add("password_length", __("<strong>ERROR</strong>: Your password must be at least 6 characters in length.", "regplus"));
				} else {
					$_POST["password"] = $_POST["pass1"];
				}
			}
			if ( $options["enable_invitation_code"] && $options["require_invitation_code"] ) {
				if ( empty($_POST["invitation_code"]) || $_POST["invitation_code"] == "" ) {
					$errors->add("empty_invitation_code", __("<strong>ERROR</strong>: Please enter an invitation code.", "regplus"));
				} elseif ( !in_array(strtolower($_POST["invitation_code"]), $options["invitation_code_bank"]) ) {
					$errors->add("invitation_code_mismatch", __("<strong>ERROR</strong>: Your invitation code is incorrect.", "regplus"));
				}
			}
			if ( $options["show_disclaimer"] ) {
				if ( !$_POST["accept_disclaimer"] ) {
					$errors->add("show_disclaimer", __("<strong>ERROR</strong>: Please accept the ", "regplus") . $options["message_disclaimer_title"] . ".");
				}
			}
			if ( $options["show_license"] ) {
				if ( !$_POST["accept_license_agreement"] ) {
					$errors->add("show_license", __("<strong>ERROR</strong>: Please accept the ", "regplus") . $options["message_license_title"] . ".");
				}
			}
			if ( $options["show_privacy_policy"] ) {
				if ( !$_POST["accept_privacy_policy"] ) {
					$errors->add("show_privacy_policy", __("<strong>ERROR</strong>: Please accept the ", "regplus") . $options["message_privacy_policy_title"] . ".");
				}
			}
		}

		function OverrideRegistrationErrors( $errors ) {
			$options = get_option("register_plus_redux_options");
			if ( $options["allow_duplicate_emails"] ) {
				//TODO: Verify this works
				//if ( $errors->errors["email_exists"] ) unset($errors->errors["email_exists"]);
			}
			return $errors;
		}

		function sendUserMessage ( $user_id, $plaintext_pass )
		{
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			if ( $options["custom_user_message"] ) {
				$headers = "";
				if ( $options["send_user_message_in_html"] ) {
					$headers .= "MIME-Version: 1.0" . "\r\n";
					$headers .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
				}
				//$headers .= "From: " . $options["user_message_from_email"] . "\r\n";
				//$headers .= "Reply-To: " . $options["user_message_from_email"] . "\r\n";
				$message = $this->replaceKeywords($options["user_message_body"], $user_info, $plaintext_pass);
				if ( $options["send_user_message_in_html"] && $options["user_message_newline_as_br"] )
					$message = nl2br($message);
				if ( $options["user_message_from_email"] )
					add_filter("wp_mail_from", array($this, "filter_user_message_from_email"));
				if ( $options["user_message_from_name"] )
					add_filter("wp_mail_from_name", array($this, "filter_user_message_from_name"));
				wp_mail($user_info->user_email, $options["user_message_subject"], $message, $headers);
			}
			if ( !$options["custom_user_message"] ) {
				$message = sprintf(__("Username: %s", "regplus"), $user_info->user_login)."\r\n";
				if ( !$options["user_set_password"] )
					$message .= sprintf(__("Password: %s", "regplus"), $plaintext_pass)."\r\n";
				$message .= "\r\n".wp_login_url()."\r\n";
				wp_mail($user_info->user_email, sprintf(__("[%s] Your login information", "regplus"), $blogname), $message);
			}
		}
		
		function sendEmailVerificationMessage ( $user_id )
		{
			global $wpdb;
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$email_verification_code = wp_generate_password(20, false);
			update_user_meta($user_id, "email_verification_code", $wpdb->prepare($email_verification_code));
			update_user_meta($user_id, "email_verification_sent", $wpdb->prepare(date("Ymd")));
			$message = __("Verification URL: ", "regplus").wp_login_url()."?verification_code=".$email_verification_code."\r\n";
			$message .= __("Please use the above link to verify your email address and activate your account", "regplus")."\r\n";
			if ( $options["user_message_from_email"] )
				add_filter("wp_mail_from", array($this, "filter_user_message_from_email"));
			if ( $options["user_message_from_name"] )
				add_filter("wp_mail_from_name", array($this, "filter_user_message_from_name"));
			wp_mail($user_info->user_email, sprintf(__("[%s] Verify your account", "regplus"), $blogname), $message);
		}
		
		function replaceKeywords ( $message, $user_info, $plaintext_pass = "" ) {
			$blogname = wp_specialchars_decode(get_option("blogname"), ENT_QUOTES);
			$message = str_replace("%blogname%", $blogname, $message);
			$message = str_replace("%site_url%", site_url(), $message);
			if ( $plaintext_pass ) {
				$message = str_replace("%user_pass%", $plaintext_pass, $message);
			}
			if ( $_SERVER ) {
				$message = str_replace("%registered_from_ip%", $_SERVER["REMOTE_ADDR"], $message);
				$message = str_replace("%registered_from_host%", gethostbyaddr($_SERVER["REMOTE_ADDR"]), $message);
				$message = str_replace("%http_referer%", $_SERVER["HTTP_REFERER"], $message);
				$message = str_replace("%http_user_agent%", $_SERVER["HTTP_USER_AGENT"], $message);
			}
			$message = str_replace("%user_login%", $user_info->user_login, $message);
			$message = str_replace("%user_email%", $user_info->user_email, $message);
			$message = str_replace("%first_name%", get_user_meta($user_info->ID, "first_name", true), $message);
			$message = str_replace("%last_name%", get_user_meta($user_info->ID, "last_name", true), $message);
			$message = str_replace("%user_url%", get_user_meta($user_info->ID, "user_url", true), $message);
			$message = str_replace("%aim%", get_user_meta($user_info->ID, "aim", true), $message);
			$message = str_replace("%yahoo%", get_user_meta($user_info->ID, "yahoo", true), $message);
			$message = str_replace("%jabber%", get_user_meta($user_info->ID, "jabber", true), $message);
			$message = str_replace("%about%", get_user_meta($user_info->ID, "about", true), $message);
			$message = str_replace("%invitation_code%", get_user_meta($user_info->ID, "invitation_code", true), $message);
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$key = $registerPlusRedux->fnSanitizeFieldName($v["custom_field_name"]);
				if ( $v["show_on_registration"] )
					$message = str_replace("%$key%", get_user_meta($user_info->ID, $key, true), $message);
			}
		}
		
		function VerifyUser() {
			$options = get_option("register_plus_redux_options");
			if ( $options["verify_user_admin"] && isset($_GET["checkemail"]) ) {
				echo "<p id='message' style='text-align:center;'>", __("Your account will be reviewed by an administrator and you will be notified when it is activated.", "regplus"), "</p>";
			} elseif ( $options["verify_user_email"] && isset($_GET["checkemail"]) ) {
				echo "<p id='message' style='text-align:center;'>", __("Please activate your account using the verification link sent to your email address.", "regplus"), "</p>";
			}
			if ( $options["verify_user_email"] && isset($_GET["verification_code"]) ) {
				global $wpdb;
				$email_verification_code = $_GET["verification_code"];
				$user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='email_verification_code' AND meta_value='$email_verification_code'");
				if ( $user_id ) {
					$stored_user_login = get_user_meta($user_id, "email_verification_user_login", true);
					$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = '$stored_user_login' WHERE ID = '$user_id'") );
					delete_user_meta($user_id, "email_verification_code");
					delete_user_meta($user_id, "email_verification_sent");
					delete_user_meta($user_id, "email_verification_user_login");
					echo "<p>", sprintf(__("Thank you %s, your account has been verified, please login.", "regplus"), $stored_user_login), "</p>";
					if ( !$options["user_set_password"] ) {
						$plaintext_pass = wp_generate_password();
						update_user_option( $user_id, "default_password_nag", true, true );
						wp_set_password($plaintext_pass, $user_id);
					}
					$this->sendUserMessage( $user_id, $plaintext_pass );
				}
			}
		}

		function ShowCustomFields( $profileuser ) {
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( is_array($custom_fields) ) {
				echo "<h3>", __("Additional Information", "regplus"), "</h3>";
				echo "<table class='form-table'>";
				foreach ( $custom_fields as $k => $v ) {
					if ( $v["show_on_profile"] ) {
						$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
						$value = get_user_meta($profileuser->ID, $key, true);
						echo "\n	<tr>";
						echo "\n		<th><label for='$key'>", $v["custom_field_name"], "</label></th>";
						switch ( $v["custom_field_type"] ) {
							case "text":
								echo "\n		<td><input type='text' name='$key' id='$key' value='$value' class='regular-text' /></td>";
								break;
							case "date":
								echo "\n		<td><input type='text' name='$key' id='$key' value='$value' /></td>";
								break;
							case "select":
								echo "\n		<td>";
								echo "\n			<select name='$key' id='$key' style='width: 15em;'>";
								$custom_field_options = explode(",", $v["custom_field_options"]);
								foreach ( $custom_field_options as $custom_field_option ) {
									echo "<option value='$custom_field_option'";
									if ( $value == $custom_field_option ) echo " selected='selected'";
									echo " >$custom_field_option</option>";
								}
								echo "</select>";
								echo "\n		</td>";
								break;
							case "checkbox":
								echo "\n		<td>";
								$custom_field_options = explode(",", $v["custom_field_options"]);
								$values = explode(", ", $value);
								foreach ( $custom_field_options as $custom_field_option ) {
									echo "\n			<label><input type='checkbox' name='$key", "[]' value='$custom_field_option'";
									if ( in_array($custom_field_option, $values) ) echo " checked='checked'";
									echo " />&nbsp;$custom_field_option</label><br />";
								}
								echo "\n		</td>";
								break;
							case "radio":
								echo "\n		<td>";
								$custom_field_options = explode(",", $v["custom_field_options"]);
								foreach ( $custom_field_options as $custom_field_option ) {
									echo "\n			<label><input type='radio' name='$key' value='$custom_field_option'";
									if ( $value == $custom_field_option ) echo " checked='checked'";
									echo " class='tog' >&nbsp;$custom_field_option</label><br />";
								}
								echo "\n		</td>";
								break;
							case "textarea":
								echo "\n		<td><textarea name='$key' id='$key' rows='5' cols='30'>", stripslashes($value), "</textarea></td>";
								break;
							case "hidden":
								echo "\n		<td><input type='text' disabled='disabled' name='$key' id='$key' value='$value' /></td>";
								break;
						}
						echo "\n	</tr>";
					}
				}
				echo "</table>";
			}
		}

		function SaveCustomFields( $user_id ) {
			global $wpdb;
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v["show_on_profile"] ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( is_array($_POST[$key]) ) $_POST[$key] = implode(", ", $_POST[$key]);
					update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
				}
			}
		}

		function filter_admin_message_from_email() {
			$options = get_option("register_plus_redux_options");
			return $options["admin_message_from_email"];
		}

		function filter_admin_message_from_name() {
			$options = get_option("register_plus_redux_options");
			return $options["admin_message_from_name"];
		}

		function filter_user_message_from_email() {
			$options = get_option("register_plus_redux_options");
			return $options["user_message_from_email"];
		}

		function filter_user_message_from_name() {
			$options = get_option("register_plus_redux_options");
			return $options["user_message_from_name"];
		}

		function fnSanitizeFieldName( $key ) {
			$key = str_replace(" ", "_", $key);
			$key = strtolower($key);
			$key = sanitize_key($key);
			return $key;
		}

		function VersionWarning() {
			global $wp_version;
			echo "\n<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __("Register Plus Redux is only compatible with WordPress 3.0 and up. You are currently using WordPress ", "regplus"), "$wp_version</strong></p></div>";
		}

		function override_warning() {
			if ( current_user_can(10) && $_GET["page"] == "register-plus-redux" )
			echo "\n<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __("You have another plugin installed that is conflicting with Register Plus Redux. This other plugin is overriding the user notification emails. Please see <a href='http://skullbit.com/news/register-plus-conflicts/'>Register Plus Conflicts</a> for more information.", "regplus"), "</strong></p></div>";
		}

		function RegMsg( $errors ) {
			$options = get_option("register_plus_redux_options");
			session_start();
			if ( $errors->errors["registered"] ) {
				//unset($errors->errors["registered"]);
			}
			if ( isset($_GET["checkemail"]) && "registered" == $_GET["checkemail"] ) $errors->add("registeredit", __("Please check your e-mail and click the verification link to activate your account and complete your registration."), "message");
			return $errors;
		}

	}
}

if ( class_exists("RegisterPlusReduxPlugin") ) $registerPlusRedux = new RegisterPlusReduxPlugin();

if ( function_exists("wp_new_user_notification") )
	add_action("admin_notices", array($registerPlusRedux, "override_warning"));

# Override set user password and send email to User #
if ( !function_exists("wp_new_user_notification") ) {
	function wp_new_user_notification($user_id, $plaintext_pass = "") {
		global $wpdb, $registerPlusRedux;
		$options = get_option("register_plus_redux_options");
		if ( in_array("first_name", $options["show_fields"]) && $_POST["first_name"] ) update_user_meta($user_id, "first_name", $wpdb->prepare($_POST["first_name"]));
		if ( in_array("last_name", $options["show_fields"]) && $_POST["last_name"] ) update_user_meta($user_id, "last_name", $wpdb->prepare($_POST["last_name"]));
		if ( in_array("user_url", $options["show_fields"]) && $_POST["user_url"] ) {
			$user_url = esc_url_raw( $_POST["user_url"] );
			$user_url = preg_match("/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is", $user_url) ? $user_url : "http://".$user_url;
			wp_update_user(array("ID" => $user_id, "user_url" => $wpdb->prepare($user_url)));
		}
		if ( in_array("aim", $options["show_fields"]) && $_POST["aim"] ) update_user_meta($user_id, "aim", $wpdb->prepare($_POST["aim"]));
		if ( in_array("yahoo", $options["show_fields"]) && $_POST["yahoo"] ) update_user_meta($user_id, "yim", $wpdb->prepare($_POST["yahoo"]));
		if ( in_array("jabber", $options["show_fields"]) && $_POST["jabber"] ) update_user_meta($user_id, "jabber", $wpdb->prepare($_POST["jabber"]));
		if ( in_array("about", $options["show_fields"]) && $_POST["about"] ) update_user_meta($user_id, "description", $wpdb->prepare($_POST["about"]));
		$custom_fields = get_option("register_plus_redux_custom_fields");
		if ( !is_array($custom_fields) ) $custom_fields = array();
		foreach ( $custom_fields as $k => $v ) {
			$key = $registerPlusRedux->fnSanitizeFieldName($v["custom_field_name"]);
			if ( $v["show_on_registration"] && $_POST[$key] ) {
				if ( is_array($_POST[$key]) ) $_POST[$key] = implode(", ", $_POST[$key]);
				update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
			}
		}
		if ( $options["user_set_password"] && $_POST["password"] ) {
			$plaintext_pass = $wpdb->prepare($_POST["password"]);
			update_user_option( $user_id, "default_password_nag", false, true );
			wp_set_password($plaintext_pass, $user_id);
		}
		if ( !$plaintext_pass ) {
			$plaintext_pass = wp_generate_password();
			update_user_option( $user_id, "default_password_nag", true, true );
			wp_set_password($plaintext_pass, $user_id);
		}
		if ( $options["enable_invitation_code"] && $_POST["invitation_code"] ) update_user_meta($user_id, "invitation_code", $wpdb->prepare($_POST["invitation_code"]));
		$user_info = get_userdata($user_id);
		$ref = explode("?", $_SERVER["HTTP_REFERER"]);
		if ( $ref[0] != site_url("wp-admin/unverified-users.php") && $options["verify_user_admin"] ) {
			update_user_meta($user_id, "admin_verification_user_login", $wpdb->prepare($user_info->user_login));
			$stored_user_login = $user_info->user_login;
			$temp_user_login = $wpdb->prepare("unverified_".wp_generate_password(7, false));
			$wpdb->query("UPDATE $wpdb->users SET user_login = '$temp_user_login' WHERE ID = '$user_id'");
		}

		if ( $ref[0] != site_url("wp-admin/unverified-users.php") && $options["verify_user_email"] ) {
			update_user_meta($user_id, "email_verification_user_login", $wpdb->prepare($user_info->user_login));
			$stored_user_login = $user_info->user_login;
			$temp_user_login = $wpdb->prepare("unverified_".wp_generate_password(7, false));
			$wpdb->query("UPDATE $wpdb->users SET user_login = '$temp_user_login' WHERE ID = '$user_id'");
			$registerPlusRedux->sendEmailVerificationMessage($user_id);
		}
		if ( $options["custom_admin_message"] && !$options["disable_admin_message"] ) {
			if ( $options["send_admin_message_in_html"] ) {
				$headers = "MIME-Version: 1.0\r\n";
				$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
			}
			//$headers .= "From: " . $options["admin_message_from_email"] . "\r\n"
			//$headers .= "Reply-To: " . $options["admin_message_from_email"] . "\r\n";
			add_filter("wp_mail_from", array($registerPlusRedux, "filter_admin_message_from_email"));
			add_filter("wp_mail_from_name", array($registerPlusRedux, "filter_admin_message_from_name"));
			$message = $registerPlusRedux->replaceKeywords($options["admin_message_body"], $user_info);
			if ( $options["send_admin_message_in_html"] && $options["admin_message_newline_as_br"] )
				$message = nl2br($message);
			@wp_mail(get_option("admin_message"), $options["admin_message_subject"], $message, $headers);
		}
		if ( !$options["custom_admin_message"] && !$options["disable_admin_message"]) {
			//Wordpress 3.0.1 default admin message
			$blogname = wp_specialchars_decode(get_option("blogname"), ENT_QUOTES);
			$message = sprintf(__("New user registered on your site %s:", "regplus"), $blogname) . "\r\n\r\n";
			$message .= sprintf(__("Username: %s", "regplus"), $user_info->user_login) . "\r\n\r\n";
			$message .= sprintf(__("E-mail: %s", "regplus"), $user_info->user_email) . "\r\n";
			@wp_mail(get_option("admin_email"), sprintf(__("[%s] New User Registration"), $blogname), $message);
		}
		if ( !$options["verify_user_email"] && !$options["verify_user_admin"] ) {
			$registerPlusRedux->sendUserMessage ($user_id, $plaintext_pass);
		}
	}
}
?>