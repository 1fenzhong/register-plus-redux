<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/blog/category/register-plus-redux/
Description: Enhances the user registration process with complete customization and additional administration options.
Version: 3.6.13
Text Domain: register-plus-redux
*/

$ops = get_option("register_plus_redux_options");
if ( isset($_POST["enable_invitation_tracking_widget"]) && $ops["enable_invitation_tracking_widget"] )
	include_once("dash_widget.php");

if ( !class_exists("RegisterPlusReduxPlugin") ) {
	class RegisterPlusReduxPlugin {
		function RegisterPlusReduxPlugin() {
			global $wp_version;
			if ( is_admin() ) {
				add_action("init", array($this, "InitializeSettings")); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("init", array($this, "DeleteExpiredUsers")); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("admin_menu", array($this, "AddPages") ); //Runs after the basic admin panel menu structure is in place.
				if ( isset($_GET["page"]) && $_GET["page"] == "register-plus-redux" && isset($_POST["action"]) && $_POST["action"] == "update_settings")
					add_action("init", array($this, "UpdateSettings") ); //Runs after WordPress has finished loading but before any headers are sent.
			}
			add_action("login_head", array($this, "LoginHead")); //Runs just before the end of the HTML head section of the login page. 
			add_action("register_form", array($this, "AlterRegistrationForm")); //Runs just before the end of the new user registration form.
			add_action("register_post", array($this, "CheckRegistration"), 10, 3); //Runs before a new user registration request is processed. 
			add_filter("registration_errors", array($this, "OverrideRegistrationErrors"));
			add_action("login_form", array($this, "AlterLoginForm")); //Runs just before the end of the HTML head section of the login page. 
			
			//add_action("wpmu_activate_user", array($this, "UpdateSignup"), 10, 3);
			//add_action("signup_extra_fields", array($this, "AlterSignupForm"));
			//add_filter("wpmu_validate_user_signup", array($this, "CheckSignup"));

			add_action("show_user_profile", array($this, "ShowCustomFields")); //Runs near the end of the user profile editing screen.
			add_action("edit_user_profile", array($this, "ShowCustomFields")); //Runs near the end of the user profile editing screen in the admin menus. 
			add_action("profile_update", array($this, "SaveCustomFields"));	//Runs when a user's profile is updated. Action function argument: user ID. 

			//LOCALIZATION
			//Place your language file in the plugin folder and name it "regplus-{language}.mo"
			//replace {language} with your language value from wp-config.php
			//load_plugin_textdomain("register-plus-redux", false, dirname(plugin_basename(__FILE__)));
			
			//VERSION CONTROL
			if ( $wp_version < 3.0 )
				add_action("admin_notices", array($this, "VersionWarning"));
		}

		function defaultOptions( $key = "" )
		{
			$blogname = wp_specialchars_decode(get_option("blogname"), ENT_QUOTES);
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
				"registration_banner_url" => "",
				"login_banner_url" => "",
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
				"required_fields_asterisk" => "0",
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

				"disable_user_message_registered" => "0",
				"disable_user_message_created" => "0",
				"custom_user_message" => "0",
				"user_message_from_email" => get_option("admin_email"),
				"user_message_from_name" => $blogname,
				"user_message_subject" => "[".$blogname."] ".__("Your Login Information", "register-plus-redux"),
				"user_message_body" => "Username: %user_login%\nPassword: %user_password%\n\n%site_url%\n",
				"send_user_message_in_html" => "0",
				"user_message_newline_as_br" => "0",
				"user_message_login_link" => wp_login_url(),
				"custom_verification_message" => "0",
				"verification_message_from_email" => get_option("admin_email"),
				"verification_message_from_name" => $blogname,
				"verification_message_subject" => "[".$blogname."] ".__("Verify Your Account", "register-plus-redux"),
				"verification_message_body" => "Verification URL: %verification_link%\nPlease use the above link to verify your email address and activate your account\n",
				"send_verification_message_in_html" => "0",
				"verification_message_newline_as_br" => "0",

				"disable_admin_message_registered" => "0",
				"disable_admin_message_created" => "0",
				"custom_admin_message" => "0",
				"admin_message_from_email" => get_option("admin_email"),
				"admin_message_from_name" => $blogname,
				"admin_message_subject" => "[".$blogname."] ".__("New User Registered", "register-plus-redux"),
				"admin_message_body" => "New user registered on your site %blogname%\n\nUsername: %user_login%\nE-mail: %user_email%\n",
				"send_admin_message_in_html" => "0",
				"admin_message_newline_as_br" => "0",

				"custom_registration_page_css" => "",
				"custom_login_page_css" => ""
			);
			if ( !empty($key) )
				return $default[$key];
			else
				return $default;
		}

		function InitializeSettings() {
			global $wpdb;
			// Added 10/01/10 no longer seperating unverified users by type
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='admin_verification_user_login'");
			if ( $unverified_users ) {
				foreach ( $unverified_users as $unverified_user ) {
					update_user_meta($unverified_user->user_id, "stored_user_login", $unverified_user->meta_value);
					delete_user_meta($unverified_user->user_id, "admin_verification_user_login");
				}
			}
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='email_verification_user_login'");
			if ( $unverified_users ) {
				foreach ( $unverified_users as $unverified_user ) {
					update_user_meta($unverified_user->user_id, "stored_user_login", $unverified_user->meta_value);
					delete_user_meta($unverified_user->user_id, "email_verification_user_login");
				}
			}
			$default = $this->defaultOptions();
			if ( !get_option("register_plus_redux_options") ) {
				//Check if settings exist, add defaults if necessary
				add_option("register_plus_redux_options", $default);
			} else {
				//Check settings for new variables, add as necessary
				$options = get_option("register_plus_redux_options");
				foreach ( $default as $k => $v ) {
					if ( !isset($options[$k]) ) {
						$options[$k] = $v;
						$update = true;
					}
				}
				if ( !empty($update) ) update_option("register_plus_redux_options", $options);
			}
		}

		function DeleteExpiredUsers() {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["delete_unverified_users_after"]) ) {
				global $wpdb;
				$unverified_users = $wpdb->get_results("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='stored_user_login'");
				if ( $unverified_users ) {
					$options = get_option("register_plus_redux_options");
					$expirationdate = date("Ymd", strtotime("-".$options["delete_unverified_users_after"]." days"));
					require_once(ABSPATH."/wp-admin/includes/user.php");
					foreach ( $unverified_users as $unverified_user ) {
						$user_info = get_userdata($unverified_user->user_id);
						if ( date("Ymd", strtotime($user_info->user_registered)) < $expirationdate ) {
							if ( $user_info->email_verification_sent ) {
								if ( date("Ymd", strtotime($user_info->email_verification_sent)) < $expirationdate ) {
									if ( $user_info->email_verified ) {
										if ( date("Ymd", strtotime($user_info->email_verified)) < $expirationdate ) {
											wp_delete_user($unverified_user->user_id);
										}
									} else {
										wp_delete_user($unverified_user->user_id);
									}
								}
							} else {
								wp_delete_user($unverified_user->user_id);
							}
						}
					}
				}
			}
		}

		function AddPages() {
			global $wpdb;
			$options = get_option("register_plus_redux_options");
			$options_page = add_submenu_page("options-general.php", "Register Plus Redux Settings", "Register Plus Redux", "manage_options", "register-plus-redux", array($this, "OptionsPage"));
			add_action("admin_head-$options_page", array($this, "OptionsHead"));
			add_filter("plugin_action_links", array($this, "filter_plugin_actions"), 10, 2);
			if ( !empty($options["verify_user_email"]) || !empty($options["verify_user_admin"]) || $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key='stored_user_login'") )
				add_submenu_page("users.php", "Unverified Users", "Unverified Users", "promote_users", "unverified-users", array($this, "UnverifiedUsersPage"));
		}

		function filter_plugin_actions( $links, $file ) {
			static $this_plugin;
			if ( empty($this_plugin) ) $this_plugin = plugin_basename(__FILE__);
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
			function showHideSettings(clickety) {
				if ( jQuery(clickety).attr('checked') )
					jQuery(clickety).parent().nextAll('div').first().show();
				else
					jQuery(clickety).parent().nextAll('div').first().hide();
			}

			function modifyNextCellInput(clickety) {
				if ( jQuery(clickety).attr('checked') )
					jQuery(clickety).parent().next().find('input').removeAttr('disabled');
				else {
					jQuery(clickety).parent().next().find('input').removeAttr('checked');
					jQuery(clickety).parent().next().find('input').attr('disabled', 'disabled');
				}
			}

			function maybeModifyNextCellInput(clickety) {
				if ( jQuery(clickety).val() == 'select' || jQuery(clickety).val() == 'checkbox' || jQuery(clickety).val() == 'radio' )
					jQuery(clickety).parent().next().find('input').removeAttr('readonly');
				else
					jQuery(clickety).parent().next().find('input').attr('readonly', 'readonly');
			}

			function addInvitationCode() {
				jQuery('<div class="invitation_code"><input type="text" name="invitation_code_bank[]" value="" />&nbsp;<img src="<?php echo plugins_url("images\minus.gif", __FILE__); ?>" alt="<?php esc_attr_e("Remove Code", "register-plus-redux"); ?>" title="<?php esc_attr_e("Remove Code", "register-plus-redux"); ?>" onclick="removeInvitationCode(this);" style="cursor: pointer;" /></div>').appendTo('#invitation_code_bank');
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
							.attr('style', 'padding-top: 0px; padding-bottom: 0px; padding-left: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'text')
								.attr('name', 'custom_field_name[]')
								.attr('style', 'width: 100%;')
							)
						)
						.append(jQuery('<td>')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<select>')
								.attr('name', 'custom_field_type[]')
								.attr('onclick', 'maybeModifyNextCellInput(this)')
								.attr('style', 'width: 100%;')
								.append('<option value="text">Text Field</option>')
								.append('<option value="select">Select Field</option>')
								.append('<option value="checkbox">Checkbox Fields</option>')
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
								.attr('readonly', 'readonly')
								.attr('style', 'width: 100%;')
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
								.attr('onclick', 'modifyNextCellInput(this)')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<input>')
								.attr('type', 'checkbox')
								.attr('name', 'required_on_registration[]')
								.attr('value', '1')
								.attr('disabled', 'disabled')
							)
						)
						.append(jQuery('<td>')
							.attr('align', 'center')
							.attr('style', 'padding-top: 0px; padding-bottom: 0px;')
							.append(jQuery('<img>')
								.attr('src', '<?php echo plugins_url("images\minus.gif", __FILE__); ?>')
								.attr('alt', '<?php esc_attr_e("Remove Field", "register-plus-redux"); ?>')
								.attr('title', '<?php esc_attr_e("Remove Field", "register-plus-redux"); ?>')
								.attr('onclick', 'removeCustomField(this);')
								.attr('style', 'cursor: pointer;')
							)
						)
					);
			}

			function removeCustomField(clickety) {
				jQuery(clickety).parent().parent().remove();
			}

			jQuery(document).ready(function() {
				<?php if ( empty($options["user_set_password"]) ) echo "\njQuery('#password_settings').hide();"; ?>
				<?php if ( empty($options["show_password_meter"]) ) echo "\njQuery('#meter_settings').hide();"; ?>
				<?php if ( empty($options["enable_invitation_code"]) ) echo "\njQuery('#invitation_code_settings').hide();"; ?>
				<?php if ( empty($options["show_disclaimer"]) ) echo "\njQuery('#disclaim_settings').hide();"; ?>
				<?php if ( empty($options["show_license"]) ) echo "\njQuery('#license_agreement_settings').hide();"; ?>
				<?php if ( empty($options["show_privacy_policy"]) ) echo "\njQuery('#privacy_policy_settings').hide();"; ?>
				<?php if ( empty($options["custom_user_message"]) ) echo "\njQuery('#custom_user_message_settings').hide();"; ?>
				<?php if ( empty($options["custom_verification_message"]) ) echo "\njQuery('#custom_verification_message_settings').hide();"; ?>
				<?php if ( empty($options["custom_admin_message"]) ) echo "\njQuery('#custom_admin_message_settings').hide();"; ?>

				jQuery('.disabled').hide();
			});
			</script>
		<?php
		}

		function OptionsPage() {
			?>
			<div class="wrap">
			<h2><?php _e("Register Plus Redux Settings", "register-plus-redux") ?></h2>
			<?php if ( !empty($_POST["notice"]) ) echo "\n<div id='message' class='updated fade'><p><strong>", $_POST["notice"], "</strong></p></div>"; ?>
			<form enctype="multipart/form-data" method="post">
				<?php wp_nonce_field("register-plus-redux-update-settings"); ?>
				<input type="hidden" name="action" value="update_settings" />
				<?php $options = get_option("register_plus_redux_options"); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("User Set Password", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="user_set_password" id="user_set_password" value="1" <?php if ( !empty($options["user_set_password"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Require new users enter a password during registration.", "register-plus-redux"); ?></label><br />
							<div id="password_settings">
								<label><input type="checkbox" name="show_password_meter" id="show_password_meter" value="1" <?php if ( !empty($options["show_password_meter"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Show password stregth meter.","register-plus-redux"); ?></label>
								<div id="meter_settings">
									<table>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_empty_password"><?php _e("Empty", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_empty_password" value="<?php echo $options["message_empty_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_short_password"><?php _e("Short", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_short_password" value="<?php echo $options["message_short_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_bad_password"><?php _e("Bad", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_bad_password" value="<?php echo $options["message_bad_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_good_password"><?php _e("Good", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_good_password" value="<?php echo $options["message_good_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_strong_password"><?php _e("Strong", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_strong_password" value="<?php echo $options["message_strong_password"]; ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_mismatch_password"><?php _e("Mismatch", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_mismatch_password" value="<?php echo $options["message_mismatch_password"]; ?>" /></td>
										</tr>
									</table>
								</div>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Custom Logo URL", "register-plus-redux"); ?></th>
						<td>
							<input type="text" name="custom_logo_url" id="custom_logo_url" value="<?php echo $options["custom_logo_url"]; ?>" style="width: 60%;" /><br />
							<?php _e("Upload a new logo:", "register-plus-redux"); ?>&nbsp;<input type="file" name="upload_custom_logo" id="upload_custom_logo" value="1" /><br />
							<?php _e("You must Save Changes to upload logo.", "register-plus-redux"); ?><br />
							<?php _e("Recommended logo should not exceed 358px width.", "register-plus-redux"); ?>
							<?php if ( !empty($options["custom_logo_url"]) ) { ?>
								<br /><img src="<?php echo $options["custom_logo_url"]; ?>" /><br />
								<?php list($custom_logo_width, $custom_logo_height) = getimagesize($options["custom_logo_url"]); ?>
								<?php echo $custom_logo_width; ?>x<?php echo $custom_logo_height; ?><br />
								<label><input type="checkbox" name="remove_logo" value="1" />&nbsp;<?php _e("Remove Logo", "register-plus-redux"); ?></label><br />
								<?php _e("You must Save Changes to remove logo.", "register-plus-redux"); ?>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Email Verification", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_email" id="verify_user_email" value="1" <?php if ( !empty($options["verify_user_email"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Verify all new users email address.", "register-plus-redux"); ?></label><br />
							<?php _e("New users will not be able to login until they click on the verification link sent to them via email, or an administrator authorizes them through the Unverified Users page.", "register-plus-redux"); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Admin Verification", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_admin" id="verify_user_admin" value="1" <?php if ( !empty($options["verify_user_admin"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Moderate all new user registrations.", "register-plus-redux"); ?></label><br />
							<?php _e("New users will not be able to login until an administrator has authorized them through the Unverified Users page.", "register-plus-redux"); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Grace Period", "register-plus-redux"); ?></th>
						<td>
							<label><input type="text" name="delete_unverified_users_after" id="delete_unverified_users_after" style="width:50px;" value="<?php echo $options["delete_unverified_users_after"]; ?>" />&nbsp;<?php _e("days", "register-plus-redux"); ?></label><br />
							<?php _e("Unverified users will be automatically deleted after grace period expires.  Set to 0 to never delete unverified users.", "register-plus-redux"); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Invitation Code", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?php if ( !empty($options["enable_invitation_code"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Use invitation codes to track or authorize new user registration.", "register-plus-redux"); ?></label>
							<div id="invitation_code_settings">
								<label><input type="checkbox" name="require_invitation_code" value="1" <?php if ( !empty($options["require_invitation_code"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Require new user enter one of the following invitation codes to register.", "register-plus-redux"); ?></label><br />
								<label><input type="checkbox" name="enable_invitation_tracking_widget" value="1" <?php if ( !empty($options["enable_invitation_tracking_widget"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Show Invitation Code Tracking widget on Dashboard.", "register-plus-redux"); ?></label>
								<div id="invitation_code_bank">
								<?php
									$invitation_codes = $options["invitation_code_bank"];
									if ( !is_array($options["invitation_code_bank"]) ) $options["invitation_code_bank"] = array();
									foreach ( $options["invitation_code_bank"] as $invitation_code )
										echo "\n<div class='invitation_code'><input type='text' name='invitation_code_bank[]' value='$invitation_code' />&nbsp;<img src='", plugins_url("images\minus.gif", __FILE__), "' alt='", __("Remove Code", "register-plus-redux"), "' title='", __("Remove Code", "register-plus-redux"), "' onclick='removeInvitationCode(this);' style='cursor: pointer;' /></div>";
								?>
								</div>
								<img src="<?php echo plugins_url("images\plus.gif", __FILE__); ?>" alt="<?php esc_attr_e("Add Code", "register-plus-redux") ?>" title="<?php esc_attr_e("Add Code", "register-plus-redux") ?>" onclick="addInvitationCode();" style="cursor: pointer;" />&nbsp;<?php _e("Add a new invitation code", "register-plus-redux") ?><br />
							</div>
						</td>
					</tr>
					<tr valign="top" class="disabled">
						<th scope="row"><?php _e("Allow Duplicate Email Addresses", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="allow_duplicate_emails" value="1" <?php if ( !empty($options["allow_duplicate_emails"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Allow new registrations to use an email address that has been previously registered", "register-plus-redux"); ?></label></td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Registration Page", "register-plus-redux"); ?></h3>
				<p><?php _e("Select which fields to add to the Registration Page.", "register-plus-redux"); ?></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Fields", "register-plus-redux"); ?></th>
						<td>
							<table>
								<thead valign="top">
									<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"></td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Show</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Require</td>
								</thead>
								<tbody>
									<?php if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array(); ?>
									<?php if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array(); ?>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("First Name", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="first_name" <?php if ( in_array("first_name", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="first_name" <?php if ( in_array("first_name", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("first_name", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Last Name", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="last_name" <?php if ( in_array("last_name", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="last_name" <?php if ( in_array("last_name", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("last_name", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Website", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="user_url" <?php if ( in_array("user_url", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="user_url" <?php if ( in_array("user_url", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("user_url", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("AIM", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="aim" <?php if ( in_array("aim", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="aim" <?php if ( in_array("aim", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("aim", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Yahoo IM", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("yahoo", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Jabber / Google Talk", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="jabber" <?php if ( in_array("jabber", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="jabber" <?php if ( in_array("jabber", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("jabber", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("About Yourself", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="about" <?php if ( in_array("about", $options["show_fields"]) ) echo "checked='checked'"; ?> onclick='modifyNextCellInput(this);' /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="about" <?php if ( in_array("about", $options["required_fields"]) ) echo "checked='checked'"; ?> <?php if ( !in_array("about", $options["show_fields"]) ) echo "disabled='disabled'"; ?> /></td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Required Fields Style Rules", "register-plus-redux"); ?></th>
						<td><input type="text" name="required_fields_style" value="<?php echo $options["required_fields_style"]; ?>" style="width: 60%;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Required Fields Asterisk", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="required_fields_asterisk" value="1" <?php if ( !empty($options["required_fields_asterisk"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Add asterisk to left of all required field's name", "register-plus-redux"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Disclaimer", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_disclaimer" id="show_disclaimer" value="1" <?php if ( !empty($options["show_disclaimer"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Show Disclaimer during registration.", "register-plus-redux"); ?></label>
							<div id="disclaim_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_disclaimer_title"><?php _e("Disclaimer Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_title" value="<?php echo $options["message_disclaimer_title"]; ?>" style="width: 100%;" />							
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_disclaimer"><?php _e("Disclaimer Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_disclaimer" cols="25" rows="10" style="width: 100%; height: 300px; display: block;"><?php echo $options["message_disclaimer"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_disclaimer_agree"><?php _e("Agreement Text", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_agree" value="<?php echo $options["message_disclaimer_agree"]; ?>" style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("License Agreement", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_license" id="show_license" value="1" <?php if ( !empty($options["show_license"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Show License Agreement during registration.", "register-plus-redux"); ?></label>
							<div id="license_agreement_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_license_title"><?php _e("License Agreement Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_title" value="<?php echo $options["message_license_title"]; ?>" style="width: 100%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_license"><?php _e("License Agreement Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_license" cols="25" rows="10" style="width: 100%; height: 300px; display: block;"><?php echo $options["message_license"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_license_agree"><?php _e("Agreement Text", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_agree" value="<?php echo $options["message_license_agree"]; ?>" style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Privacy Policy", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_privacy_policy" id="show_privacy_policy" value="1" <?php if ( !empty($options["show_privacy_policy"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Show Privacy Policy during registration.", "register-plus-redux"); ?></label>
							<div id="privacy_policy_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_privacy_policy_title"><?php _e("Privacy Policy Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_title" value="<?php echo $options["message_privacy_policy_title"]; ?>" style="width: 100%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_privacy_policy"><?php _e("Privacy Policy Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_privacy_policy" cols="25" rows="10" style="width: 100%; height: 300px; display: block;"><?php echo $options["message_privacy_policy"]; ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_privacy_policy_agree"><?php _e("Agreement Text", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_agree" value="<?php echo $options["message_privacy_policy_agree"]; ?>" style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Additional Fields", "register-plus-redux"); ?></h3>
				<p><?php _e("Enter additional fields to add to the User Profile and/or Registration Page. Options are required for Select, Checkbox, and Radio fields. Options should be enter with commas seperating each possible value. For example, a Radio field named \"Gender\" might have the following options, \"Male,Female\".", "register-plus-redux"); ?></p>
				<table id="custom_fields" style="width: 80%;">
					<thead valign="top">
						<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">Name</td>
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
							echo "\n		<select name='custom_field_type[$k]' onchange='maybeModifyNextCellInput(this);'>";
							echo "\n			<option value='text'"; if ( $v["custom_field_type"] == "text" ) echo " selected='selected'"; echo ">Text Field</option>";
							echo "\n			<option value='select'"; if ( $v["custom_field_type"] == "select" ) echo " selected='selected'"; echo ">Select Field</option>";
							echo "\n			<option value='checkbox'"; if ( $v["custom_field_type"] == "checkbox" ) echo " selected='selected'"; echo ">Checkbox Fields</option>";
							echo "\n			<option value='radio'"; if ( $v["custom_field_type"] == "radio" ) echo " selected='selected'"; echo ">Radio Fields</option>";
							echo "\n			<option value='textarea'"; if ( $v["custom_field_type"] == "textarea" ) echo " selected='selected'"; echo ">Text Area</option>";
							echo "\n			<option value='date'"; if ( $v["custom_field_type"] == "date" ) echo " selected='selected'"; echo ">Date Field</option>";
							echo "\n			<option value='hidden'"; if ( $v["custom_field_type"] == "hidden" ) echo " selected='selected'"; echo ">Hidden Field</option>";
							echo "\n		</select>";
							echo "\n	</td>";
							echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'><input type='text' name='custom_field_options[$k]' value='", $v["custom_field_options"], "'"; if ( $v["custom_field_type"] != "select" && $v["custom_field_type"] != "checkbox" && $v["custom_field_type"] != "radio" ) echo " readonly='readonly'"; echo " /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_profile[$k]' value='1'"; if ( !empty($v["show_on_profile"]) ) echo " checked='checked'"; echo " /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_registration[$k]' value='1'"; if ( !empty($v["show_on_registration"]) ) echo " checked='checked'"; echo " onclick='modifyNextCellInput(this);' /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='required_on_registration[$k]' value='1'"; if ( !empty($v["required_on_registration"]) ) echo " checked='checked'"; if ( empty($v["show_on_registration"]) ) echo " disabled='disabled'"; echo " /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><img src='", plugins_url("images\minus.gif", __FILE__), "' alt='", __("Remove Field", "register-plus-redux"), "' title='", __("Remove Field", "register-plus-redux"), "' onclick='removeCustomField(this);' style='cursor: pointer;' /></td>";
							echo "\n</tr>";
						}
						if ( empty($custom_fields) ) {
							echo "\n<tr valign='center' class='custom_field'>";
							echo "\n	<td style='padding-top: 0px; padding-bottom: 0px; padding-left: 0px;'><input type='text' name='custom_field_name[]' value='' style='width: 100%;'/></td>";
							echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'>";
							echo "\n		<select name='custom_field_type[]' onchange='maybeModifyNextCellInput(this);' style='width: 100%;'>";
							echo "\n			<option value='text'>Text Field</option>";
							echo "\n			<option value='select'>Select Field</option>";
							echo "\n			<option value='checkbox'>Checkbox Fields</option>";
							echo "\n			<option value='radio'>Radio Fields</option>";
							echo "\n			<option value='textarea'>Text Area</option>";
							echo "\n			<option value='date'>Date Field</option>";
							echo "\n			<option value='hidden'>Hidden Field</option>";
							echo "\n		</select>";
							echo "\n	</td>";
							echo "\n	<td style='padding-top: 0px; padding-bottom: 0px;'><input type='text' name='custom_field_options[]' value='' readonly='readonly' style='width: 100%;'/></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_profile[]' value='1' /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='show_on_registration[]' value='1' onclick='modifyNextCellInput(this);' /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><input type='checkbox' name='required_on_registration[]' value='1' disabled='disabled' /></td>";
							echo "\n	<td align='center' style='padding-top: 0px; padding-bottom: 0px;'><img src='", plugins_url("images\minus.gif", __FILE__), "' alt='", __("Remove Field", "register-plus-redux"), "' title='", __("Remove Field", "register-plus-redux"), "' onclick='removeCustomField(this);' style='cursor: pointer;' /></td>";
							echo "\n</tr>";
						}
						?>
					</tbody>
				</table>
				<img src="<?php echo plugins_url("images\plus.gif", __FILE__); ?>" alt="<?php esc_attr_e("Add Field", "register-plus-redux") ?>" title="<?php esc_attr_e("Add Field", "register-plus-redux") ?>" onclick="addCustomField();" style="cursor: pointer;" />&nbsp;<?php _e("Add a new custom field.", "register-plus-redux") ?>
				<table class="form-table">
					<tr valign="top" class="disabled">
						<th scope="row"><?php _e("Date Field Settings", "register-plus-redux"); ?></th>
						<td>
							<label for="datepicker_firstdayofweek"><?php _e("First Day of the Week", "register-plus-redux"); ?>:</label>
							<select type="select" name="datepicker_firstdayofweek">
								<option value="7" <?php if ( $options["datepicker_firstdayofweek"] == "7" ) echo "selected='selected'"; ?>><?php _e("Monday", "register-plus-redux"); ?></option>
								<option value="1" <?php if ( $options["datepicker_firstdayofweek"] == "1" ) echo "selected='selected'"; ?>><?php _e("Tuesday", "register-plus-redux"); ?></option>
								<option value="2" <?php if ( $options["datepicker_firstdayofweek"] == "2" ) echo "selected='selected'"; ?>><?php _e("Wednesday", "register-plus-redux"); ?></option>
								<option value="3" <?php if ( $options["datepicker_firstdayofweek"] == "3" ) echo "selected='selected'"; ?>><?php _e("Thursday", "register-plus-redux"); ?></option>
								<option value="4" <?php if ( $options["datepicker_firstdayofweek"] == "4" ) echo "selected='selected'"; ?>><?php _e("Friday", "register-plus-redux"); ?></option>
								<option value="5" <?php if ( $options["datepicker_firstdayofweek"] == "5" ) echo "selected='selected'"; ?>><?php _e("Saturday", "register-plus-redux"); ?></option>
								<option value="6" <?php if ( $options["datepicker_firstdayofweek"] == "6" ) echo "selected='selected'"; ?>><?php _e("Sunday", "register-plus-redux"); ?></option>
							</select><br />
							<label for="datepicker_dateformat"><?php _e("Date Format", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_dateformat" value="<?php echo $options["datepicker_dateformat"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_startdate"><?php _e("First Selectable Date", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_startdate" id="datepicker_startdate" value="<?php echo $options["datepicker_startdate"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_calyear"><?php _e("Default Year", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_calyear" id="datepicker_calyear" value="<?php echo $options["datepicker_calyear"]; ?>" style="width:40px;" /><br />
							<label for="datepicker_calmonth"><?php _e("Default Month", "register-plus-redux"); ?>:</label>
							<select name="datepicker_calmonth" id="datepicker_calmonth">
								<option value="cur" <?php if ( $options["datepicker_calmonth"] == "cur" ) echo "selected='selected'"; ?>><?php _e("Current Month", "register-plus-redux"); ?></option>
								<option value="0" <?php if ( $options["datepicker_calmonth"] == "0" ) echo "selected='selected'"; ?>><?php _e("Jan", "register-plus-redux"); ?></option>
								<option value="1" <?php if ( $options["datepicker_calmonth"] == "1" ) echo "selected='selected'"; ?>><?php _e("Feb", "register-plus-redux"); ?></option>
								<option value="2" <?php if ( $options["datepicker_calmonth"] == "2" ) echo "selected='selected'"; ?>><?php _e("Mar", "register-plus-redux"); ?></option>
								<option value="3" <?php if ( $options["datepicker_calmonth"] == "3" ) echo "selected='selected'"; ?>><?php _e("Apr", "register-plus-redux"); ?></option>
								<option value="4" <?php if ( $options["datepicker_calmonth"] == "4" ) echo "selected='selected'"; ?>><?php _e("May", "register-plus-redux"); ?></option>
								<option value="5" <?php if ( $options["datepicker_calmonth"] == "5" ) echo "selected='selected'"; ?>><?php _e("Jun", "register-plus-redux"); ?></option>
								<option value="6" <?php if ( $options["datepicker_calmonth"] == "6" ) echo "selected='selected'"; ?>><?php _e("Jul", "register-plus-redux"); ?></option>
								<option value="7" <?php if ( $options["datepicker_calmonth"] == "7" ) echo "selected='selected'"; ?>><?php _e("Aug", "register-plus-redux"); ?></option>
								<option value="8" <?php if ( $options["datepicker_calmonth"] == "8" ) echo "selected='selected'"; ?>><?php _e("Sep", "register-plus-redux"); ?></option>
								<option value="9" <?php if ( $options["datepicker_calmonth"] == "9" ) echo "selected='selected'"; ?>><?php _e("Oct", "register-plus-redux"); ?></option>
								<option value="10" <?php if ( $options["datepicker_calmonth"] == "10" ) echo "selected='selected'"; ?>><?php _e("Nov", "register-plus-redux"); ?></option>
								<option value="11" <?php if ( $options["datepicker_calmonth"] == "11" ) echo "selected='selected'"; ?>><?php _e("Dec", "register-plus-redux"); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Auto-Complete Queries", "register-plus-redux"); ?></h3>
				<p><?php _e("You can now link to the registration page with queries to autocomplete specific fields for the user. I have included the query keys below and an example of a query URL.", "register-plus-redux"); ?></p>
				<?php
				$registration_fields = "%first_name% %last_name% %user_url% %aim% %yahoo% %jabber% %about% %invitation_code%";
				foreach ( $custom_fields as $k => $v ) {
					if ( !empty($v["show_on_registration"]) ) $registration_fields .= " %".$this->fnSanitizeFieldName($v["custom_field_name"])."%";
				}
				?>
				<code><?php echo $registration_fields; ?></code>
				<p><?php _e("For any custom fields, use your custom field label with the text all lowercase, using underscores instead of spaces. For example if your custom field was \"Middle Name\" your query key would be %middle_name%", "register-plus-redux"); ?></p>
				<p><strong><?php _e("Example Query URL", "register-plus-redux"); ?></strong></p>
				<code>http://www.radiok.info/wp-login.php?action=register&user_login=radiok&user_email=radiok@radiok.info&first_name=Radio&last_name=K&user_url=www.radiok.info&aim=radioko&invitation_code=1979&middle_name=Billy</code>
				<h3 class="title"><?php _e("New User Message Settings", "register-plus-redux"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e("New User Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="disable_user_message_registered" id="disable_user_message_registered" value="1" <?php if ( !empty($options["disable_user_message_registered"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Do NOT send user an email after they are registered", "register-plus-redux"); ?></label><br />
							<label><input type="checkbox" name="disable_user_message_created" id="disable_user_message_created" value="1" <?php if ( !empty($options["disable_user_message_created"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Do NOT send user an email when created by an administrator", "register-plus-redux"); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom New User Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_user_message" id="custom_user_message" value="1" <?php if ( !empty($options["custom_user_message"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Enable", "register-plus-redux"); ?></label>
							<div id="custom_user_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="user_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_from_email" id="user_message_from_email" style="width: 100%;" value="<?php echo $options["user_message_from_email"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_from_name" id="user_message_from_name" style="width: 100%;" value="<?php echo $options["user_message_from_name"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_subject" id="user_message_subject" style="width: 100%;" value="<?php echo $options["user_message_subject"]; ?>" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="user_message_body"><?php _e("User Message", "register-plus-redux"); ?></label><br />
											<textarea name="user_message_body" id="user_message_body" rows="10" cols="25" style="width: 100%; height: 300px;"><?php echo $options["user_message_body"]; ?></textarea><br />
											<strong><?php _e("Replacement Keys", "register-plus-redux"); ?>:</strong> %user_login% %user_password% %user_email% %blogname% %site_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent%<br />
											<label><input type="checkbox" name="send_user_message_in_html" value="1" <?php if ( !empty($options["send_user_message_in_html"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="user_message_newline_as_br" value="1" <?php if ( !empty($options["user_message_newline_as_br"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
									<tr class="disabled">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_login_link"><?php _e("Login URL", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="user_message_login_link" id="user_message_login_link" style="width:250px;" value="<?php echo $options["user_message_login_link"]; ?>" /><br />
											<?php _e("This will redirect the users login after registration.", "register-plus-redux"); ?>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom Verification Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_verification_message" id="custom_verification_message" value="1" <?php if ( !empty($options["custom_verification_message"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Enable", "register-plus-redux"); ?></label>
							<div id="custom_verification_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="verification_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_from_email" id="verification_message_from_email" style="width: 100%;" value="<?php echo $options["verification_message_from_email"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="verification_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_from_name" id="verification_message_from_name" style="width: 100%;" value="<?php echo $options["verification_message_from_name"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="verification_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_subject" id="verification_message_subject" style="width: 100%;" value="<?php echo $options["verification_message_subject"]; ?>" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="verification_message_body"><?php _e("User Message", "register-plus-redux"); ?></label><br />
											<textarea name="verification_message_body" id="verification_message_body" rows="10" cols="25" style="width: 100%; height: 300px;"><?php echo $options["verification_message_body"]; ?></textarea><br />
											<strong><?php _e("Replacement Keys", "register-plus-redux"); ?>:</strong> %user_login% %user_email% %blogname% %site_url% %verification_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent%<br />
											<label><input type="checkbox" name="send_verification_message_in_html" value="1" <?php if ( !empty($options["send_verification_message_in_html"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="verification_message_newline_as_br" value="1" <?php if ( !empty($options["verification_message_newline_as_br"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>				</table>
				<h3 class="title"><?php _e("Admin Notification Settings", "register-plus-redux"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e("Admin Notification", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="disable_admin_message_registered" id="disable_admin_message_registered" value="1" <?php if ( !empty($options["disable_admin_message_registered"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Do NOT send administrator an email whenever a new user registers", "register-plus-redux"); ?></label><br />
							<label><input type="checkbox" name="disable_admin_message_created" id="disable_admin_message_created" value="1" <?php if ( !empty($options["disable_admin_message_created"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Do NOT send administrator an email whenever a new user is created by an administrator", "register-plus-redux"); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom Admin Notification", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_admin_message" id="custom_admin_message" value="1" <?php if ( !empty($options["custom_admin_message"]) ) echo "checked='checked'"; ?> onclick="showHideSettings(this);" />&nbsp;<?php _e("Enable", "register-plus-redux"); ?></label>
							<div id="custom_admin_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="admin_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_from_email" id="admin_message_from_email" style="width: 100%;" value="<?php echo $options["admin_message_from_email"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="admin_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_from_name" id="admin_message_from_name" style="width: 100%;" value="<?php echo $options["admin_message_from_name"]; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="admin_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_subject" id="admin_message_subject" style="width: 100%;" value="<?php echo $options["admin_message_subject"]; ?>" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="admin_message_body"><?php _e("Admin Message", "register-plus-redux"); ?></label><br />
											<textarea name="admin_message_body" id="admin_message_body" rows="10" cols="25" style="width: 100%; height: 300px;"><?php echo $options["admin_message_body"]; ?></textarea><br />
											<strong><?php _e("Replacement Keys", "register-plus-redux"); ?>:</strong> %user_login% %user_email% %blogname% %site_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent%<br />
											<label><input type="checkbox" name="send_admin_message_in_html" value="1" <?php if ( !empty($options["send_admin_message_in_html"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="admin_message_newline_as_br" value="1" <?php if ( !empty($options["admin_message_newline_as_br"]) ) echo "checked='checked'"; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
				<br />
				<h3 class="title"><?php _e("Custom CSS for Register & Login Pages", "register-plus-redux"); ?></h3>
				<p><?php _e("CSS Rule Example:", "register-plus-redux"); ?>&nbsp;<code>#user_login { font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="custom_registration_page_css"><?php _e("Custom Register CSS", "register-plus-redux"); ?></label></th>
						<td><textarea name="custom_registration_page_css" id="custom_registration_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options["custom_registration_page_css"]; ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="custom_login_page_css"><?php _e("Custom Login CSS", "register-plus-redux"); ?></label></th>
						<td><textarea name="custom_login_page_css" id="custom_login_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options["custom_login_page_css"]; ?></textarea></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_attr_e("Save Changes", "register-plus-redux"); ?>" name="submit" />
					<input type="button" class="button" value="<?php esc_attr_e("Preview Registraton Page", "register-plus-redux"); ?>" name="preview" onclick="window.open('<?php echo wp_login_url(), "?action=register"; ?>');" />
				</p>
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
			if ( isset($_POST["user_set_password"]) ) $options["user_set_password"] = $_POST["user_set_password"];
			if ( isset($_POST["show_password_meter"]) ) $options["show_password_meter"] = $_POST["show_password_meter"];
			if ( isset($_POST["message_empty_password"]) ) $options["message_empty_password"] = $_POST["message_empty_password"];
			if ( isset($_POST["message_short_password"]) ) $options["message_short_password"] = $_POST["message_short_password"];
			if ( isset($_POST["message_bad_password"]) ) $options["message_bad_password"] = $_POST["message_bad_password"];
			if ( isset($_POST["message_good_password"]) ) $options["message_good_password"] = $_POST["message_good_password"];
			if ( isset($_POST["message_strong_password"]) ) $options["message_strong_password"] = $_POST["message_strong_password"];
			if ( isset($_POST["message_mismatch_password"]) ) $options["message_mismatch_password"] = $_POST["message_mismatch_password"];
			if ( isset($_POST["custom_logo_url"]) ) $options["custom_logo_url"] = $_POST["custom_logo_url"];
			if ( isset($_POST["registration_banner_url"]) ) $options["registration_banner_url"] = $_POST["registration_banner_url"];
			if ( isset($_POST["login_banner_url"]) ) $options["login_banner_url"] = $_POST["login_banner_url"];
			if ( !empty($_FILES["upload_custom_logo"]["name"]) ) {
				$upload = wp_upload_bits($_FILES["upload_custom_logo"]["name"], null, file_get_contents($_FILES["upload_custom_logo"]["tmp_name"]));
				if ( !$upload["error"] ) $options["custom_logo_url"] = $upload["url"];
			}
			if ( isset($_POST["remove_logo"]) ) $options["custom_logo_url"] = "";
			if ( isset($_POST["verify_user_email"]) ) $options["verify_user_email"] = $_POST["verify_user_email"];
			if ( isset($_POST["delete_unverified_users_after"]) ) $options["delete_unverified_users_after"] = $_POST["delete_unverified_users_after"];
			if ( isset($_POST["verify_user_admin"]) ) $options["verify_user_admin"] = $_POST["verify_user_admin"];
			if ( isset($_POST["enable_invitation_code"]) ) $options["enable_invitation_code"] = $_POST["enable_invitation_code"];
			if ( isset($_POST["enable_invitation_tracking_widget"]) ) $options["enable_invitation_tracking_widget"] = $_POST["enable_invitation_tracking_widget"];
			if ( isset($_POST["require_invitation_code"]) ) $options["require_invitation_code"] = $_POST["require_invitation_code"];
			if ( isset($_POST["invitation_code_bank"]) ) $options["invitation_code_bank"] = $_POST["invitation_code_bank"];
			if ( isset($_POST["allow_duplicate_emails"]) ) $options["allow_duplicate_emails"] = $_POST["allow_duplicate_emails"];

			if ( isset($_POST["show_fields"]) ) $options["show_fields"] = $_POST["show_fields"];
			if ( isset($_POST["required_fields"]) ) $options["required_fields"] = $_POST["required_fields"];
			if ( isset($_POST["required_fields_style"]) ) $options["required_fields_style"] = $_POST["required_fields_style"];
			if ( isset($_POST["required_fields_asterisk"]) ) $options["required_fields_asterisk"] = $_POST["required_fields_asterisk"];
			if ( isset($_POST["show_disclaimer"]) ) $options["show_disclaimer"] = $_POST["show_disclaimer"];
			if ( isset($_POST["message_disclaimer_title"]) ) $options["message_disclaimer_title"] = $_POST["message_disclaimer_title"];
			if ( isset($_POST["message_disclaimer"]) ) $options["message_disclaimer"] = $_POST["message_disclaimer"];
			if ( isset($_POST["message_disclaimer_agree"]) ) $options["message_disclaimer_agree"] = $_POST["message_disclaimer_agree"];
			if ( isset($_POST["show_license"]) ) $options["show_license"] = $_POST["show_license"];
			if ( isset($_POST["message_license_title"]) ) $options["message_license_title"] = $_POST["message_license_title"];
			if ( isset($_POST["message_license"]) ) $options["message_license"] = $_POST["message_license"];
			if ( isset($_POST["message_license_agree"]) ) $options["message_license_agree"] = $_POST["message_license_agree"];
			if ( isset($_POST["show_privacy_policy"]) ) $options["show_privacy_policy"] = $_POST["show_privacy_policy"];
			if ( isset($_POST["message_privacy_policy_title"]) ) $options["message_privacy_policy_title"] = $_POST["message_privacy_policy_title"];
			if ( isset($_POST["message_privacy_policy"]) ) $options["message_privacy_policy"] = $_POST["message_privacy_policy"];
			if ( isset($_POST["message_privacy_policy_agree"]) ) $options["message_privacy_policy_agree"] = $_POST["message_privacy_policy_agree"];

			if ( isset($_POST["custom_field_name"]) ) {
				foreach ( $_POST["custom_field_name"] as $k => $v ) {
					if (!empty($v) ) { 
						$custom_fields[$k] = array("custom_field_name" => $v,
							"custom_field_type" => isset($_POST["custom_field_name"][$k]) ? $_POST["custom_field_type"][$k] : "",
							"custom_field_options" => isset($_POST["custom_field_options"][$k]) ? $_POST["custom_field_options"][$k] : "",
							"show_on_profile" => isset($_POST["show_on_profile"][$k]) ? $_POST["show_on_profile"][$k] : "",
							"show_on_registration" => isset($_POST["show_on_registration"][$k]) ? $_POST["show_on_registration"][$k] : "",
							"required_on_registration" => isset($_POST["required_on_registration"][$k]) ? $_POST["required_on_registration"][$k] : "");
					}
				}
			}
			if ( isset($_POST["datepicker_firstdayofweek"]) ) $options["datepicker_firstdayofweek"] = $_POST["datepicker_firstdayofweek"];
			if ( isset($_POST["datepicker_dateformat"]) ) $options["datepicker_dateformat"] = $_POST["datepicker_dateformat"];
			if ( isset($_POST["datepicker_startdate"]) ) $options["datepicker_startdate"] = $_POST["datepicker_startdate"];
			if ( isset($_POST["datepicker_calyear"]) ) $options["datepicker_calyear"] = $_POST["datepicker_calyear"];
			if ( isset($_POST["datepicker_calmonth"]) ) $options["datepicker_calmonth"] = $_POST["datepicker_calmonth"];

			if ( isset($_POST["disable_user_message_registered"]) ) $options["disable_user_message_registered"] = $_POST["disable_user_message_registered"];
			if ( isset($_POST["disable_user_message_created"]) ) $options["disable_user_message_created"] = $_POST["disable_user_message_created"];
			if ( isset($_POST["custom_user_message"]) ) $options["custom_user_message"] = $_POST["custom_user_message"];
			if ( isset($_POST["user_message_from_email"]) ) $options["user_message_from_email"] = $_POST["user_message_from_email"];
			if ( isset($_POST["user_message_from_name"]) ) $options["user_message_from_name"] = $_POST["user_message_from_name"];
			if ( isset($_POST["user_message_subject"]) ) $options["user_message_subject"] = $_POST["user_message_subject"];
			if ( isset($_POST["user_message_body"]) ) $options["user_message_body"] = $_POST["user_message_body"];
			if ( isset($_POST["send_user_message_in_html"]) ) $options["send_user_message_in_html"] = $_POST["send_user_message_in_html"];
			if ( isset($_POST["user_message_newline_as_br"]) ) $options["user_message_newline_as_br"] = $_POST["user_message_newline_as_br"];
			if ( isset($_POST["user_message_login_link"]) ) $options["user_message_login_link"] = $_POST["user_message_login_link"];
			if ( isset($_POST["custom_verification_message"]) ) $options["custom_verification_message"] = $_POST["custom_verification_message"];
			if ( isset($_POST["verification_message_from_email"]) ) $options["verification_message_from_email"] = $_POST["verification_message_from_email"];
			if ( isset($_POST["verification_message_from_name"]) ) $options["verification_message_from_name"] = $_POST["verification_message_from_name"];
			if ( isset($_POST["verification_message_subject"]) ) $options["verification_message_subject"] = $_POST["verification_message_subject"];
			if ( isset($_POST["verification_message_body"]) ) $options["verification_message_body"] = $_POST["verification_message_body"];
			if ( isset($_POST["send_verification_message_in_html"]) ) $options["send_verification_message_in_html"] = $_POST["send_verification_message_in_html"];
			if ( isset($_POST["verification_message_newline_as_br"]) ) $options["verification_message_newline_as_br"] = $_POST["verification_message_newline_as_br"];

			if ( isset($_POST["disable_admin_message_registered"]) ) $options["disable_admin_message_registered"] = $_POST["disable_admin_message_registered"];
			if ( isset($_POST["disable_admin_message_created"]) ) $options["disable_admin_message_created"] = $_POST["disable_admin_message_created"];
			if ( isset($_POST["custom_admin_message"]) ) $options["custom_admin_message"] = $_POST["custom_admin_message"];
			if ( isset($_POST["admin_message_from_email"]) ) $options["admin_message_from_email"] = $_POST["admin_message_from_email"];
			if ( isset($_POST["admin_message_from_name"]) ) $options["admin_message_from_name"] = $_POST["admin_message_from_name"];
			if ( isset($_POST["admin_message_subject"]) ) $options["admin_message_subject"] = $_POST["admin_message_subject"];
			if ( isset($_POST["admin_message_body"]) ) $options["admin_message_body"] = $_POST["admin_message_body"];
			if ( isset($_POST["send_admin_message_in_html"]) ) $options["send_admin_message_in_html"] = $_POST["send_admin_message_in_html"];
			if ( isset($_POST["admin_message_newline_as_br"]) ) $options["admin_message_newline_as_br"] = $_POST["admin_message_newline_as_br"];

			if ( isset($_POST["custom_registration_page_css"]) ) $options["custom_registration_page_css"] = $_POST["custom_registration_page_css"];
			if ( isset($_POST["custom_login_page_css"]) ) $options["custom_login_page_css"] = $_POST["custom_login_page_css"];

			update_option("register_plus_redux_options", $options);
			//update_option("register_plus_redux_custom_fields", $custom_fields);
			$_POST["notice"] = __("Settings Saved", "register-plus-redux");
		}

		function UnverifiedUsersPage() {
			if ( isset($_REQUEST["action"]) && $_REQUEST["action"] == "verify_users" ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "verify_users";
					global $wpdb;
					$options = get_option("register_plus_redux_options");
					foreach ( $_REQUEST["users"] as $user_id ) {
						$stored_user_login = get_user_meta($user_id, "stored_user_login", true);
						$plaintext_pass = get_user_meta($user_id, "stored_user_password", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = '$stored_user_login' WHERE ID = '$user_id'") );
						delete_user_meta($user_id, "email_verification_code");
						delete_user_meta($user_id, "email_verification_sent");
						delete_user_meta($user_id, "email_verified");
						delete_user_meta($user_id, "stored_user_login");
						delete_user_meta($user_id, "stored_user_password");
						if ( empty($plaintext_pass) ) {
							$plaintext_pass = wp_generate_password();
							update_user_option($user_id, "default_password_nag", true, true);
							wp_set_password($plaintext_pass, $user_id);
						}
						if ( empty($options["disable_user_message_registered"]) )
							$this->sendUserMessage($user_id, $plaintext_pass);
					}
				}
			}
			if ( isset($_REQUEST["action"]) && $_REQUEST["action"] == "send_verification_email" ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "send_verification_email";
					foreach ( $_REQUEST["users"] as $user_id ) {
						$id = (int) $user_id;
						if ( !current_user_can('promote_user', $id) )
							wp_die(__("You cannot edit that user.", "register-plus-redux"));
						$this->sendVerificationMessage($user_id);
					}
				}
			}
			if ( isset($_REQUEST["action"]) && $_REQUEST["action"] == "delete_users" ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "delete_users";
					require_once(ABSPATH.'/wp-admin/includes/user.php');
					foreach ( $_REQUEST["users"] as $user_id )
						wp_delete_user($user_id);
				}
			}
			if ( !empty($update) ) {
				switch( $update ) {
					case "verify_users":
						echo "<div id='message' class='updated'><p>", __("Users approved.", "register-plus-redux"), "</p></div>";
						break;
					case "send_verification_email":
						echo "<div id='message' class='updated'><p>", __("Verification emails sent.", "register-plus-redux"), "</p></div>";
						break;
					case "delete_users":
						echo "<div id='message' class='updated'><p>", __("Users deleted.", "register-plus-redux"), "</p></div>";
						break;
				}
			}
			global $wpdb;
			?>
			<div class="wrap">
				<h2><?php _e("Unverified Users", "register-plus-redux") ?></h2>
				<form id="verify-filter" method="post" action="">
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option value="" selected="selected"><?php _e("Bulk Actions", "register-plus-redux"); ?></option>
							<option value="verify_users"><?php _e("Approve", "register-plus-redux"); ?></option>
							<option value="send_verification_email"><?php _e("Send E-mail Verification", "register-plus-redux"); ?></option>
							<option value="delete_users"><?php _e("Delete", "register-plus-redux"); ?></option>
						</select>
						<input type="submit" value="<?php esc_attr_e("Apply", "register-plus-redux"); ?>" name="doaction" id="doaction" class="button-secondary action" />
						<?php wp_nonce_field("register-plus-redux-unverified-users"); ?>
					</div>
					<br class="clear">
				</div>
				<table class="widefat fixed" cellspacing="0">
					<thead>
						<tr class="thead">
							<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
							<th><?php _e("Unverified Username", "register-plus-redux"); ?></th>
							<th><?php _e("Requested Username", "register-plus-redux"); ?></th>
							<th scope="col" id="email" class="manage-column column-email" style=""><?php _e("E-mail", "register-plus-redux"); ?></th>
							<th><?php _e("Registered", "register-plus-redux"); ?></th>
							<th><?php _e("Verification Sent", "register-plus-redux"); ?></th>
							<th><?php _e("Verified", "register-plus-redux"); ?></th>
						</tr>
					</thead>
					<tbody id="users" class="list:user user-list">
						<?php 
						$unverified_users = $wpdb->get_results("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='stored_user_login'");
						$style = "";
						foreach ( $unverified_users as $unverified_user ) {
							$user_info = get_userdata($unverified_user->user_id);
							$style = ( $style == "class=\"alternate\"" ) ? "" : "class=\"alternate\"";
							?>

							<tr id="user-<?php echo $user_info->ID; ?>" <?php echo $style; ?>>
								<th scope="row" class="check-column"><input name="users[]" id="user_<?php echo $user_info->ID; ?>" name="user_<?php echo $user_info->ID; ?>" value="<?php echo $user_info->ID; ?>" type="checkbox"></th>
								<td><strong><?php echo $user_info->user_login; ?></strong></td>
								<td><strong><?php echo $user_info->stored_user_login; ?></strong></td>
								<td><a href="mailto:<?php echo $user_info->user_email; ?>" title="<?php esc_attr_e("E-mail: ", "register-plus-redux"); echo $user_info->user_email; ?>"><?php echo $user_info->user_email; ?></a></td>
								<td><strong><?php echo $user_info->user_registered; ?></strong></td>
								<td><strong><?php echo $user_info->email_verification_sent; ?></strong></td>
								<td><strong><?php echo $user_info->email_verified; ?></strong></td>
							</tr>
						<?php } ?>

					</tbody>
				</table>
				</form>
			</div>
			<br class="clear" />
			<?php
		}

		function LoginHead() {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["custom_logo_url"]) ) {
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
			if ( isset($_GET["checkemail"]) && $_GET["checkemail"] == "registered" && ($options["verify_user_admin"] || $options["verify_user_email"]) ) {
				//label, #user_login, #user_pass, .forgetmenot, #wp-submit, .message { display: none; }
				echo "\n<style type=\"text/css\">";
				echo "\np { display: none; }";
				echo "\n#message, #backtoblog { display: block; }";
				echo "\n</style>";
			}
			if ( isset($_GET["action"]) && $_GET["action"] == "register" ) {
				$custom_fields = get_option("register_plus_redux_custom_fields");
				if ( !is_array($custom_fields) ) $custom_fields = array();
				foreach ( $custom_fields as $k => $v ) {
					if ( !empty($v["show_on_registration"]) ) {
						if ( $v["custom_field_type"] == "text" ) {
							if ( empty($show_custom_text_fields) )
								$show_custom_text_fields = "#".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_text_fields .= ", #".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "select" ) {
							if ( empty($show_custom_select_fields) )
								$show_custom_select_fields = "#".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_select_fields .= ", #".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "checkbox" ) {
							if ( empty($show_custom_checkbox_fields) )
								$show_custom_checkbox_fields = ".".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_checkbox_fields .= ", .".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "radio" ) {
							if ( empty($show_custom_radio_fields) )
								$show_custom_radio_fields = ".".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_radio_fields .= ", .".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "textarea" ) {
							if ( empty($show_custom_textarea_fields) )
								$show_custom_textarea_fields = "#".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_textarea_fields .= ", #".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "date" ) {
							if ( empty($show_custom_date_fields) )
								$show_custom_date_fields = "#".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$show_custom_date_fields .= ", #".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
						if ( !empty($v["required_on_registration"]) ) {
							if ( empty($required_custom_fields) )
								$required_custom_fields = "#".$this->fnSanitizeFieldName($v["custom_field_name"]);
							else
								$required_custom_fields .= ", #".$this->fnSanitizeFieldName($v["custom_field_name"]);
						}
					}
				}

				if ( !empty($options["show_fields"][0]) ) $show_fields = "#".implode(", #", $options["show_fields"]);
				if ( !empty($options["required_fields"][0]) ) $required_fields = "#".implode(", #", $options["required_fields"]);

				echo "\n<style type=\"text/css\">";
				echo "\nsmall { display:block; margin-bottom:8px; }";
				if ( !empty($show_fields) ) echo "\n$show_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( in_array("about", $options["show_fields"]) ) echo "\n#about { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($show_custom_text_fields) ) echo "\n$show_custom_text_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($show_custom_select_fields) ) echo "\n$show_custom_select_fields { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($show_custom_checkbox_fields) ) echo "\n$show_custom_checkbox_fields { font-size:18px; }";
				if ( !empty($show_custom_radio_fields) ) echo "\n$show_custom_radio_fields { font-size:18px; }";
				if ( !empty($show_custom_textarea_fields) ) echo "\n$show_custom_textarea_fields { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($show_custom_date_fields) ) echo "\n$show_custom_date_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($options["show_disclaimer"]) ) { echo "\n#disclaimer { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_disclaimer"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_license"]) ) { echo "\n#license { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_license"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_privacy_policy"]) ) { echo "\n#privacy_policy { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_privacy_policy"]) > 525) echo "height: 200px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_disclaimer"]) || !empty($options["show_license"]) || !empty($options["show_privacy_policy"]) ) echo "\n.accept_check { display:block; margin-bottom:8px; }";
				if ( !empty($show_custom_date_fields) ) {
					echo "\na.dp-choose-date { float: left; width: 16px; height: 16px; padding: 0; margin: 5px 3px 0; display: block; text-indent: -2000px; overflow: hidden; background: url('"; echo plugins_url("datepicker/calendar.png", __FILE__); echo "') no-repeat; }";
					echo "\na.dp-choose-date.dp-disabled { background-position: 0 -20px; cursor: default; } /* makes the input field shorter once the date picker code * has run (to allow space for the calendar icon */";
					echo "\ninput.dp-applied { width: 140px; float: left; }";
				}
				if ( !empty($options["user_set_password"]) ) {
					echo "\n#reg_passmail { display: none; }";
					echo "\n#pass1, #pass2 { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($options["show_password_meter"]) ) echo "\n#pass-strength-result { width: 97%; padding: 3px; margin-top:0px; margin-right:6px; margin-bottom:4px; border: 1px solid; text-align: center; }";
				}
				if ( !empty($options["enable_invitation_code"]) ) echo "\n#invitation_code { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:4px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				if ( !empty($options["required_fields_style"]) ) {
					echo "\n#user_login, #user_email { ", $options["required_fields_style"], "} ";
					if ( !empty($required_fields) ) echo "\n$required_fields { ", $options["required_fields_style"], " }";
					if ( !empty($required_custom_fields) ) echo "\n$required_custom_fields { ", $options["required_fields_style"], " }";
					if ( !empty($options["user_set_password"]) ) echo "\n#pass1, #pass2 { ", $options["required_fields_style"], " }";
					if ( !empty($options["require_invitation_code"]) ) echo "\n#invitation_code { ", $options["required_fields_style"], " }";
				}
				if ( !empty($options["custom_registration_page_css"]) ) echo "\n", $options["custom_registration_page_css"];
				echo "\n</style>";

				if ( !empty($show_custom_date_fields) ) {
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
						TEXT_PREV_YEAR	: '<?php _e("Previous year", "register-plus-redux"); ?>',
						TEXT_PREV_MONTH	: '<?php _e("Previous month", "register-plus-redux"); ?>',
						TEXT_NEXT_YEAR	: '<?php _e("Next year", "register-plus-redux"); ?>',
						TEXT_NEXT_MONTH	: '<?php _e("Next Month", "register-plus-redux"); ?>',
						TEXT_CLOSE	: '<?php _e("Close", "register-plus-redux"); ?>',
						TEXT_CHOOSE_DATE: '<?php _e("Choose Date", "register-plus-redux"); ?>'
					}
					Date.dayNames = ['<?php _e("Monday", "register-plus-redux"); ?>', '<?php _e("Tuesday", "register-plus-redux"); ?>', '<?php _e("Wednesday", "register-plus-redux"); ?>', '<?php _e("Thursday", "register-plus-redux"); ?>', '<?php _e("Friday", "register-plus-redux"); ?>', '<?php _e("Saturday", "register-plus-redux"); ?>', '<?php _e("Sunday", "register-plus-redux"); ?>'];
					Date.abbrDayNames = ['<?php _e("Mon", "register-plus-redux"); ?>', '<?php _e("Tue", "register-plus-redux"); ?>', '<?php _e("Wed", "register-plus-redux"); ?>", '<?php _e("Thu", "register-plus-redux"); ?>', '<?php _e("Fri", "register-plus-redux"); ?>', '<?php _e("Sat", "register-plus-redux"); ?>', '<?php _e("Sun", "register-plus-redux"); ?>'];
					Date.monthNames = ['<?php _e("January", "register-plus-redux"); ?>', '<?php _e("February", "register-plus-redux"); ?>', '<?php _e("March", "register-plus-redux"); ?>', '<?php _e("April", "register-plus-redux"); ?>', '<?php _e("May", "register-plus-redux"); ?>', '<?php _e("June", "register-plus-redux"); ?>', '<?php _e("July", "register-plus-redux"); ?>', '<?php _e("August", "register-plus-redux"); ?>', '<?php _e("September", "register-plus-redux"); ?>', '<?php _e("October", "register-plus-redux"); ?>', '<?php _e("November", "register-plus-redux"); ?>', '<?php _e("December", "register-plus-redux"); ?>'];
					Date.abbrMonthNames = ['<?php _e("Jan", "register-plus-redux"); ?>', '<?php _e("Feb", "register-plus-redux"); ?>', '<?php _e("Mar", "register-plus-redux"); ?>', '<?php _e("Apr", "register-plus-redux"); ?>', '<?php _e("May", "register-plus-redux"); ?>', '<?php _e("Jun", "register-plus-redux"); ?>', '<?php _e("Jul", "register-plus-redux"); ?>', '<?php _e("Aug", "register-plus-redux"); ?>', '<?php _e("Sep", "register-plus-redux"); ?>', '<?php _e("Oct", "register-plus-redux"); ?>', '<?php _e("Nov", "register-plus-redux"); ?>', '<?php _e("Dec", "register-plus-redux"); ?>'];
					Date.firstDayOfWeek = '<?php echo $options["datepicker_firstdayofweek"]; ?>';
					Date.format = '<?php echo $options["datepicker_dateformat"]; ?>';
					jQuery(function() {
						jQuery('.date-pick').datePicker({
							clickInput: true,
							startDate: '<?php echo $options["datepicker_startdate"]; ?>',
							year: '<?php echo $options["datepicker_calyear"]; ?>',
							month: '<?php if ( $options["datepicker_calmonth"] != "cur" ) echo $options["datepicker_calmonth"]; else echo date("n")-1; ?>'
						})
					});
					</script>
					<?php
				}
				if ( !empty($options["user_set_password"]) && !empty($options["show_password_meter"]) ) {
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
							if ( !pass1 ) {
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
							if ( (password1 != password2) && password2.length > 0 )
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
							if ( score < 40 )
								return badPass
							if ( score < 56 )
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
				if ( !empty($options["custom_login_page_css"]) ) {
					echo "\n<style type=\"text/css\">";
					echo "\n", $options["custom_login_page_css"];
					echo "\n</style>";
				}
			}
		}

		function AlterRegistrationForm() {
			$options = get_option("register_plus_redux_options");
			$tabindex = 21;
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) ) {
				if ( isset($_GET["first_name"]) ) $_POST["first_name"] = $_GET["first_name"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("First Name", "register-plus-redux"), "<br /><input type='text' name='first_name' id='first_name' class='input' value='", $_POST["first_name"],"' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("last_name", $options["show_fields"]) ) {
				if ( isset($_GET["last_name"]) ) $_POST["last_name"] = $_GET["last_name"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Last Name", "register-plus-redux"), "<br /><input type='text' name='last_name' id='last_name' class='input' value='", $_POST["last_name"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("user_url", $options["show_fields"]) ) {
				if ( isset($_GET["url"]) ) $_POST["url"] = $_GET["url"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Website", "register-plus-redux"), "<br /><input type='text' name='url' id='user_url' class='input' value='", $_POST["url"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("aim", $options["show_fields"]) ) {
				if ( isset($_GET["aim"]) ) $_POST["aim"] = $_GET["aim"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("AIM", "register-plus-redux"), "<br /><input type='text' name='aim' id='aim' class='input' value='", $_POST["aim"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("yahoo", $options["show_fields"]) ) {
				if ( isset($_GET["yahoo"]) ) $_POST["yahoo"] = $_GET["yahoo"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Yahoo IM", "register-plus-redux"), "<br /><input type='text' name='yahoo' id='yahoo' class='input' value='", $_POST["yahoo"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("jabber", $options["show_fields"]) ) {
				if ( isset($_GET["jabber"]) ) $_POST["jabber"] = $_GET["jabber"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Jabber / Google Talk", "register-plus-redux"), "<br /><input type='text' name='jabber' id='jabber' class='input' value='", $_POST["jabber"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
			}
			if ( in_array("about", $options["show_fields"]) ) {
				if ( isset($_GET["about"]) ) $_POST["about"] = $_GET["about"];
				echo "\n<p><label for='about'>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("About Yourself", "register-plus-redux"), "</label><br />";
				echo "\n<small>", __("Share a little biographical information to fill out your profile. This may be shown publicly.", "register-plus-redux"), "</small><br />";
				echo "\n<textarea name='about' id='about' cols='25' rows='5' tabindex='$tabindex'>", stripslashes($_POST["about"]), "</textarea></p>";
				$tabindex++;
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( !empty($v["show_on_registration"]) ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( isset($_GET[$key]) ) $_POST[$key] = $_GET[$key];
					switch ( $v["custom_field_type"] ) {
						case "text":
							echo "\n<p><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v["custom_field_name"], "<br /><input type='text' name='$key' id='$key' value='", $_POST[$key], "' size='25' tabindex='$tabindex' /></label></p>";
							$tabindex++;
							break;
						case "select":
							echo "\n<p><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v["custom_field_name"], "<br />";
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
							echo "\n<p style='margin-bottom:16px;'><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v["custom_field_name"], "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								echo "\n<input type='checkbox' name='$key"."[]' id='", $this->fnSanitizeFieldName($custom_field_option), "'";
								//if ( in_array($custom_field_option, $_POST[$key])) echo " checked='checked'";
								echo " value='$custom_field_option' tabindex='$tabindex' /><label for='", $this->fnSanitizeFieldName($custom_field_option), "' class='", $this->fnSanitizeFieldName($v["custom_field_name"]), "'>&nbsp;$custom_field_option</label><br />";
								$tabindex++;
							}
							echo "\n</p>";
							break;
						case "radio":
							echo "\n<p style='margin-bottom:16px;'><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v["custom_field_name"], "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								echo "\n<input type='radio' name='$key' id='", $this->fnSanitizeFieldName($custom_field_option), "'";
								if ( $_POST[$key] == $custom_field_option ) echo " checked='checked'";
								echo " value='$custom_field_option' tabindex='$tabindex' /><label for='", $this->fnSanitizeFieldName($custom_field_option), "' class='", $this->fnSanitizeFieldName($v["custom_field_name"]), "'>&nbsp;$custom_field_option</label><br />";
								$tabindex++;
							}
							echo "\n</p>";
							break;
						case "textarea":
							echo "\n<p><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v["custom_field_name"], "<br /><textarea name='$key' id='$key' cols='25' rows='5' tabindex='$tabindex'>", $_POST[$key], "</textarea></label></p>";
							$tabindex++;
							break;
						case "date":
							echo "\n<p><label>";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo $v['custom_field_name'], "<br /><input type='text' name='$key' id='$key' value='", $_POST[$key], "' size='25' tabindex='$tabindex' /></label></p>";
							$tabindex++;
							break;
						case "hidden":
							echo "\n<input type='hidden' name='$key' id='$key' value='", $_POST[$key], "' tabindex='$tabindex' />";
							$tabindex++;
							break;
					}
				}
			}
			if ( !empty($options["user_set_password"]) ) {
				if ( isset($_GET["password"]) ) $_POST["password"] = $_GET["password"];
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Password", "register-plus-redux"), "<br /><input type='password' autocomplete='off' name='pass1' id='pass1' value='", $_POST["password"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				echo "\n<p><label>";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Confirm Password", "register-plus-redux"), "<br /><input type='password' autocomplete='off' name='pass2' id='pass2' value='", $_POST["password"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				if ( !empty($options["show_password_meter"]) ) {
					echo "\n<div id='pass-strength-result'>", $options["message_empty_password"], "</div>";
					echo "\n<small>", __("Your password must be at least seven characters long. To make your password stronger, use upper and lower case letters, numbers, and the following symbols !@#$%^&amp;*()", "register-plus-redux"), "</small>";
				}
			}
			if ( !empty($options["enable_invitation_code"]) ) {
				if ( isset($_GET["invitation_code"]) ) $_POST["invitation_code"] = $_GET["invitation_code"];
				echo "<p><label>";
				if ( !empty($options["required_fields_asterisk"]) && !empty($options["require_invitation_code"]) ) echo "*";
				echo __("Invitation Code", "register-plus-redux"), "<br /><input type='text' name='invitation_code' id='invitation_code' class='input' value='", $_POST["invitation_code"], "' size='25' tabindex='$tabindex' /></label></p>";
				$tabindex++;
				if ( !empty($options["require_invitation_code"]) )
					echo "\n<small>", __("This website is currently closed to public registrations. You will need an invitation code to register.", "register-plus-redux"), "</small>";
				else
					echo "\n<small>", __("Have an invitation code? Enter it here. (This is not required)", "register-plus-redux"), "</small>";
			}
			if ( !empty($options["show_disclaimer"]) ) {
				if ( isset($_GET["accept_disclaimer"]) ) $_POST["accept_disclaimer"] = $_GET["accept_disclaimer"];
				echo "\n<p>";
				echo "\n	<label>", $options["message_disclaimer_title"], "</label><br />";
				echo "\n	<span name='disclaimer' id='disclaimer'>", $options["message_disclaimer"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_disclaimer' id='accept_disclaimer' value='1'"; if ( !empty($_POST["accept_disclaimer"]) ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_disclaimer_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
			if ( !empty($options["show_license"]) ) {
				if ( isset($_GET["accept_license"]) ) $_POST["accept_license"] = $_GET["accept_license"];
				echo "\n<p>";
				echo "\n	<label>", $options["message_license_title"], "</label><br />";
				echo "\n	<span name='license' id='license'>", $options["message_license"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_license' id='accept_license' value='1'"; if ( !empty($_POST["accept_license_agreement"]) ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_license_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
			if ( !empty($options["show_privacy_policy"]) ) {
				if ( isset($_GET["accept_privacy_policy"]) ) $_POST["accept_privacy_policy"] = $_GET["accept_privacy_policy"];
				echo "\n<p>";
				echo "\n	<label>", $options["message_privacy_policy_title"], "</label><br />";
				echo "\n	<span name='privacy_policy' id='privacy_policy'>", $options["message_privacy_policy"], "</span>";
				echo "\n	<label class='accept_check'><input type='checkbox' name='accept_privacy_policy' id='accept_privacy_policy' value='1'"; if ( !empty($_POST["accept_privacy_policy"]) ) echo " checked='checked'"; echo " tabindex='$tabindex'/>&nbsp;", $options["message_privacy_policy_agree"], "</label>";
				$tabindex++;
				echo "\n</p>";
			}
		}

		function CheckRegistration( $sanitized_user_login, $user_email, $errors ) {
			global $wpdb;
			if ( $wpdb->get_var($wpdb->prepare("SELECT * FROM $wpdb->usermeta WHERE meta_value=%s", $sanitized_user_login)) ) {
				$errors->add("username_exists", __("<strong>ERROR</strong>: This username is already registered, please choose another one.", "register-plus-redux"));
			}
			$options = get_option("register_plus_redux_options");
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) && in_array("first_name", $options["required_fields"]) ) {
				if ( empty($_POST["first_name"]) ) {
					$errors->add("empty_first_name", __("<strong>ERROR</strong>: Please enter your first name.", "register-plus-redux"));
				}
			}
			if ( in_array("last_name", $options["show_fields"]) && in_array("last_name", $options["required_fields"]) ) {
				if ( empty($_POST["last_name"]) ) {
					$errors->add("empty_last_name", __("<strong>ERROR</strong>: Please enter your last name.", "register-plus-redux"));
				}
			}
			if ( in_array("user_url", $options["show_fields"]) && in_array("user_url", $options["required_fields"]) ) {
				if ( empty($_POST["url"]) ) {
					$errors->add("empty_user_url", __("<strong>ERROR</strong>: Please enter your website URL.", "register-plus-redux"));
				}
			}
			if ( in_array("aim", $options["show_fields"]) && in_array("aim", $options["required_fields"]) ) {
				if ( empty($_POST["aim"]) ) {
					$errors->add("empty_aim", __("<strong>ERROR</strong>: Please enter your AIM username.", "register-plus-redux"));
				}
			}
			if ( in_array("yahoo", $options["show_fields"]) && in_array("yahoo", $options["required_fields"]) ) {
				if ( empty($_POST["yahoo"]) ) {
					$errors->add("empty_yahoo", __("<strong>ERROR</strong>: Please enter your Yahoo IM username.", "register-plus-redux"));
				}
			}
			if ( in_array("jabber", $options["show_fields"]) && in_array("jabber", $options["required_fields"]) ) {
				if ( empty($_POST["jabber"]) ) {
					$errors->add("empty_jabber", __("<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.", "register-plus-redux"));
				}
			}
			if ( in_array("about", $options["show_fields"]) && in_array("about", $options["required_fields"]) ) {
				if ( empty($_POST["about"]) ) {
					$errors->add("empty_about", __("<strong>ERROR</strong>: Please enter some information about yourself.", "register-plus-redux"));
				}
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( !empty($v["show_on_registration"]) && !empty($v["required_on_registration"]) ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( empty($_POST[$key]) ) {
						$errors->add("empty_$key", sprintf(__("<strong>ERROR</strong>: Please complete %s.", "register-plus-redux"), $v["custom_field_name"]));
					}
				}
			}
			if ( !empty($options["user_set_password"]) ) {
				if ( empty($_POST["pass1"]) || empty($_POST["pass2"]) ) {
					$errors->add("empty_password", __("<strong>ERROR</strong>: Please enter a password.", "register-plus-redux"));
				} elseif ( $_POST["pass1"] != $_POST["pass2"] ) {
					$errors->add("password_mismatch", __("<strong>ERROR</strong>: Your password does not match.", "register-plus-redux"));
				} elseif ( strlen($_POST["pass1"]) < 6 ) {
					$errors->add("password_length", __("<strong>ERROR</strong>: Your password must be at least 6 characters in length.", "register-plus-redux"));
				} else {
					$_POST["password"] = $_POST["pass1"];
				}
			}
			if ( !empty($options["enable_invitation_code"]) && !empty($options["require_invitation_code"]) ) {
				if ( empty($_POST["invitation_code"]) ) {
					$errors->add("empty_invitation_code", __("<strong>ERROR</strong>: Please enter an invitation code.", "register-plus-redux"));
				} elseif ( !in_array(strtolower($_POST["invitation_code"]), $options["invitation_code_bank"]) ) {
					$errors->add("invitation_code_mismatch", __("<strong>ERROR</strong>: Your invitation code is incorrect.", "register-plus-redux"));
				}
			}
			if ( !empty($options["show_disclaimer"]) ) {
				if ( empty($_POST["accept_disclaimer"]) || !$_POST["accept_disclaimer"] ) {
					$errors->add("show_disclaimer", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), $options["message_disclaimer_title"]) . ".");
				}
			}
			if ( !empty($options["show_license"]) ) {
				if ( empty($_POST["accept_license_agreement"]) || !$_POST["accept_license_agreement"] ) {
					$errors->add("show_license", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), $options["message_license_title"]) . ".");
				}
			}
			if ( !empty($options["show_privacy_policy"]) ) {
				if ( empty($_POST["accept_privacy_policy"]) || !$_POST["accept_privacy_policy"] ) {
					$errors->add("show_privacy_policy", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), $options["message_privacy_policy_title"]) . ".");
				}
			}
		}

		function OverrideRegistrationErrors( $errors ) {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["allow_duplicate_emails"]) ) {
				//TODO: Verify this works
				//if ( $errors->errors["email_exists"] ) unset($errors->errors["email_exists"]);
			}
			return $errors;
		}

		function sendUserMessage ( $user_id, $plaintext_pass )
		{
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$message = $this->defaultOptions("user_message_body");
			$subject = $this->defaultOptions("user_message_subject");
			if ( !empty($options["custom_user_message"]) ) {
				$message = $options["user_message_body"];
				$subject = $options["user_message_subject"];
				if ( !empty($options["send_user_message_in_html"]) && !empty($options["user_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["user_message_from_email"]) )
					add_filter("wp_mail_from", array($this, "filter_user_message_from"));
				if ( !empty($options["user_message_from_name"]) )
					add_filter("wp_mail_from_name", array($this, "filter_user_message_from_name"));
				if ( !empty($options["send_user_message_in_html"]) )
					add_filter("wp_mail_content_type", array($this, "filter_message_content_type_html"));
			}
			$message = $this->replaceKeywords($message, $user_info, $plaintext_pass);
			wp_mail($user_info->user_email, $subject, $message);
		}

		function sendVerificationMessage ( $user_id )
		{
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$verification_code = wp_generate_password(20, false);
			update_user_meta($user_id, "email_verification_code", $verification_code);
			update_user_meta($user_id, "email_verification_sent", gmdate("Y-m-d H:i:s"));
			$message = $this->defaultOptions("verification_message_body");
			$subject = $this->defaultOptions("verification_message_subject");
			if ( !empty($options["custom_verification_message"]) ) {
				$message = $options["verification_message_body"];
				$subject = $options["verification_message_subject"];
				if ( !empty($options["send_verification_message_in_html"]) && !empty($options["verification_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["verification_message_from_email"]) )
					add_filter("wp_mail_from", array($this, "filter_verification_message_from"));
				if ( !empty($options["verification_message_from_name"]) )
					add_filter("wp_mail_from_name", array($this, "filter_verification_message_from_name"));
				if ( !empty($options["send_verification_message_in_html"]) )
					add_filter("wp_mail_content_type", array($this, "filter_message_content_type_html"));
			}
			$message = $this->replaceKeywords($message, $user_info, $plaintext_pass, $verification_code);
			wp_mail($user_info->user_email, $subject, $message);
		}

		function sendAdminMessage ( $user_id )
		{
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$message = $this->defaultOptions("admin_message_body");
			$subject = $this->defaultOptions("admin_message_subject");
			if ( !empty($options["custom_admin_message"]) ) {
				$message = $options["admin_message_body"];
				$subject = $options["admin_message_subject"];
				if ( !empty($options["send_admin_message_in_html"]) && !empty($options["admin_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["admin_message_from_email"]) )
					add_filter("wp_mail_from", array($registerPlusRedux, "filter_admin_message_from"));
				if ( !empty($options["admin_message_from_name"]) )
					add_filter("wp_mail_from_name", array($registerPlusRedux, "filter_admin_message_from_name"));
				if ( !empty($options["send_admin_message_in_html"]) )
					add_filter("wp_mail_content_type", array($registerPlusRedux, "filter_message_content_type_html"));
			}
			$message = $this->replaceKeywords($message, $user_info);
			wp_mail(get_option("admin_email"), $subject, $message);
		}

		function replaceKeywords ( $message, $user_info, $plaintext_pass = "", $verification_code = "" ) {
			$blogname = wp_specialchars_decode(get_option("blogname"), ENT_QUOTES);
			$message = str_replace("%blogname%", $blogname, $message);
			$message = str_replace("%site_url%", site_url(), $message);
			$message = str_replace("%user_password%", $plaintext_pass, $message);
			$message = str_replace("%verification_code%", $verification_code, $message);
			$message = str_replace("%verification_link%", wp_login_url()."?verification_code=".$verification_code, $message);
			if ( !empty($_SERVER) ) {
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
				$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
				if ( !empty($v["show_on_registration"]) )
					$message = str_replace("%$key%", get_user_meta($user_info->ID, $key, true), $message);
			}
			return $message;
		}

		function AlterLoginForm() {
			$options = get_option("register_plus_redux_options");
			if ( isset($_GET["checkemail"]) && $options["verify_user_email"] ) {
				echo "<p id='message' style='text-align:center;'>", __("Please verify your account using the verification link sent to your email address.", "register-plus-redux"), "</p>";
			} elseif ( isset($_GET["checkemail"]) && $options["verify_user_admin"] ) {
				echo "<p id='message' style='text-align:center;'>", __("Your account will be reviewed by an administrator and you will be notified when it is activated.", "register-plus-redux"), "</p>";
			}
			if ( isset($_GET["verification_code"]) ) {
				global $wpdb;
				$verification_code = $_GET["verification_code"];
				$user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='email_verification_code' AND meta_value='$verification_code'");
				if ( !empty($user_id) ) {
					if ( empty($options["verify_user_admin"]) ) {
						$stored_user_login = get_user_meta($user_id, "stored_user_login", true);
						$plaintext_pass = get_user_meta($user_id, "stored_user_password", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = '$stored_user_login' WHERE ID = '$user_id'") );
						delete_user_meta($user_id, "email_verification_code");
						delete_user_meta($user_id, "email_verification_sent");
						delete_user_meta($user_id, "stored_user_login");
						delete_user_meta($user_id, "stored_user_password");
						if ( empty($plaintext_pass) ) {
							$plaintext_pass = wp_generate_password();
							update_user_option( $user_id, "default_password_nag", true, true );
							wp_set_password($plaintext_pass, $user_id);
						}
						if ( empty($options["disable_user_message_registered"]) )
							$this->sendUserMessage($user_id, $plaintext_pass);
						echo "<p>", sprintf(__("Thank you %s, your account has been verified, please login.", "register-plus-redux"), $stored_user_login), "</p>";
					} elseif ( !empty($options["verify_user_admin"]) ) {
						update_user_meta($user_id, "email_verified", gmdate("Y-m-d H:i:s"));
						echo "<p id='message' style='text-align:center;'>", __("Your account will be reviewed by an administrator and you will be notified when it is activated.", "register-plus-redux"), "</p>";
					}
				}
			}
		}

		function ShowCustomFields( $profileuser ) {
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( is_array($custom_fields) ) {
				echo "<h3>", __("Additional Information", "register-plus-redux"), "</h3>";
				echo "<table class='form-table'>";
				foreach ( $custom_fields as $k => $v ) {
					if ( !empty($v["show_on_profile"]) ) {
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
									echo ">$custom_field_option</option>";
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
									echo " class='tog'>&nbsp;$custom_field_option</label><br />";
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
				if ( !empty($v["show_on_profile"]) ) {
					$key = $this->fnSanitizeFieldName($v["custom_field_name"]);
					if ( is_array($_POST[$key]) ) $_POST[$key] = implode(", ", $_POST[$key]);
					update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
				}
			}
		}

		function filter_admin_message_from() {
			$options = get_option("register_plus_redux_options");
			return $options["admin_message_from_email"];
		}

		function filter_admin_message_from_name() {
			$options = get_option("register_plus_redux_options");
			return $options["admin_message_from_name"];
		}

		function filter_user_message_from() {
			$options = get_option("register_plus_redux_options");
			return $options["user_message_from_email"];
		}

		function filter_user_message_from_name() {
			$options = get_option("register_plus_redux_options");
			return $options["user_message_from_name"];
		}

		function filter_verification_message_from() {
			$options = get_option("register_plus_redux_options");
			return $options["verification_message_from_email"];
		}

		function filter_verification_message_from_name() {
			$options = get_option("register_plus_redux_options");
			return $options["verification_message_from_name"];
		}

		function filter_message_content_type_html() {
			return "text/html";
		}
		
		function fnSanitizeFieldName( $key ) {
			$key = str_replace(" ", "_", $key);
			$key = strtolower($key);
			$key = sanitize_key($key);
			return $key;
		}

		function VersionWarning() {
			global $wp_version;
			echo "\n<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __("Register Plus Redux is only compatible with WordPress 3.0 and up. You are currently using WordPress ", "register-plus-redux"), "$wp_version</strong></p></div>";
		}

		function override_warning() {
			if ( current_user_can(10) && isset($_GET["page"]) && $_GET["page"] == "register-plus-redux" )
			echo "\n<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __("You have another plugin installed that is conflicting with Register Plus Redux. This other plugin is overriding the user notification emails. Please see <a href='http://skullbit.com/news/register-plus-conflicts/'>Register Plus Conflicts</a> for more information.", "register-plus-redux"), "</strong></p></div>";
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
		$ref = explode("?", $_SERVER["HTTP_REFERER"]);
		$created_by = "user";
		if ( $ref[0] == site_url("wp-admin/user-new.php") )
			$created_by = "admin";
		$options = get_option("register_plus_redux_options");
		if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
		if ( !empty($_POST["first_name"]) ) update_user_meta($user_id, "first_name", $wpdb->prepare($_POST["first_name"]));
		if ( !empty($_POST["last_name"]) ) update_user_meta($user_id, "last_name", $wpdb->prepare($_POST["last_name"]));
		if ( !empty($_POST["url"]) ) {
			$user_url = esc_url_raw( $_POST["url"] );
			$user_url = preg_match("/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is", $user_url) ? $user_url : "http://".$user_url;
			wp_update_user(array("ID" => $user_id, "user_url" => $wpdb->prepare($user_url)));
		}
		if ( in_array("aim", $options["show_fields"]) && !empty($_POST["aim"]) ) update_user_meta($user_id, "aim", $wpdb->prepare($_POST["aim"]));
		if ( in_array("yahoo", $options["show_fields"]) && !empty($_POST["yahoo"]) ) update_user_meta($user_id, "yim", $wpdb->prepare($_POST["yahoo"]));
		if ( in_array("jabber", $options["show_fields"]) && !empty($_POST["jabber"]) ) update_user_meta($user_id, "jabber", $wpdb->prepare($_POST["jabber"]));
		if ( in_array("about", $options["show_fields"]) && !empty($_POST["about"]) ) update_user_meta($user_id, "description", $wpdb->prepare($_POST["about"]));
		$custom_fields = get_option("register_plus_redux_custom_fields");
		if ( !is_array($custom_fields) ) $custom_fields = array();
		foreach ( $custom_fields as $k => $v ) {
			$key = $registerPlusRedux->fnSanitizeFieldName($v["custom_field_name"]);
			if ( !empty($v["show_on_registration"]) && !empty($_POST[$key]) ) {
				if ( is_array($_POST[$key]) ) $_POST[$key] = implode(", ", $_POST[$key]);
				update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
			}
		}
		if ( !empty($options["user_set_password"]) && !empty($_POST["password"]) ) {
			$plaintext_pass = $wpdb->prepare($_POST["password"]);
			update_user_option( $user_id, "default_password_nag", false, true );
			wp_set_password($plaintext_pass, $user_id);
		}
		if ( $created_by == "admin" && !empty($_POST["pass1"]) ) {
			$plaintext_pass = $wpdb->prepare($_POST["pass1"]);
			update_user_option( $user_id, "default_password_nag", false, true );
			wp_set_password($plaintext_pass, $user_id);
		}
		if ( empty($plaintext_pass) ) {
			$plaintext_pass = wp_generate_password();
			update_user_option( $user_id, "default_password_nag", true, true );
			wp_set_password($plaintext_pass, $user_id);
		}
		if ( !empty($options["enable_invitation_code"]) && !empty($_POST["invitation_code"]) )
			update_user_meta($user_id, "invitation_code", $wpdb->prepare($_POST["invitation_code"]));
		$user_info = get_userdata($user_id);
		if ( $created_by == "user" && (!empty($options["verify_user_email"]) || !empty($options["verify_user_admin"])) ) {
			update_user_meta($user_id, "stored_user_login", $wpdb->prepare($user_info->user_login));
			update_user_meta($user_id, "stored_user_password", $wpdb->prepare($plaintext_pass));
			$temp_user_login = $wpdb->prepare("unverified_".wp_generate_password(7, false));
			$wpdb->query("UPDATE $wpdb->users SET user_login = '$temp_user_login' WHERE ID = '$user_id'");
		}
		if ( $created_by == "user" && !empty($options["verify_user_email"]) ) {
			$registerPlusRedux->sendVerificationMessage($user_id);
		}
		if ( $created_by == "user" && empty($options["disable_user_message_registered"]) || 
			$created_by == "admin" && empty($options["disable_user_message_created"]) ) {
			if ( empty($options["verify_user_email"]) && empty($options["verify_user_admin"]) ) {
				$registerPlusRedux->sendUserMessage($user_id, $plaintext_pass);
			}
		}
		if ( $created_by == "user" && empty($options["disable_admin_message_registered"]) || 
			$created_by == "admin" && empty($options["disable_admin_message_created"]) ) {
			$registerPlusRedux->sendAdminMessage($user_id);
		}
	}
}
?>