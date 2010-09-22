<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/register-plus-redux/
Description: Fork of Register Plus
Version: 3.6.4
*/

$ops = get_option('register_plus_redux_options');
if ( $ops['enable_invitation_tracking_widget'] )
	include_once('dash_widget.php');

if ( !class_exists('RegisterPlusReduxPlugin') ) {
	class RegisterPlusReduxPlugin {
		function RegisterPlusReduxPlugin() {
			global $wp_version;
			register_activation_hook(__FILE__, array($this, 'InitializeSettings'));
			if ( is_admin() ) {
				add_action('init', array($this, 'DeleteInvalidUsers'));  //Runs after WordPress has finished loading but before any headers are sent.
				add_action('admin_menu', array($this, 'AddPages') );  //Runs after the basic admin panel menu structure is in place.
				if ( $_GET['page'] == 'register-plus-redux' && $_POST['action'] == 'update_settings')
					add_action('init', array($this, 'UpdateSettings') );  //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_POST['verifyit'] )
					add_action('init', array($this, 'AdminValidate'));  //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_POST['emailverifyit'] )
					add_action('init', array($this, 'AdminEmailValidate'));  //Runs after WordPress has finished loading but before any headers are sent.
				if ( $_POST['vdeleteit'] )
					add_action('init', array($this, 'AdminDeleteUnvalidated'));  //Runs after WordPress has finished loading but before any headers are sent.
			}
			if ( $_GET['action'] == 'register' )
				add_action('register_form', array($this, 'RegisterForm'));  //Runs just before the end of the new user registration form. 
			add_filter('registration_errors', array($this, 'RegistrationErrors'));

			add_action('login_head', array($this, 'PassHead'));  //Runs just before the end of the HTML head section of the login page. 
			add_action('login_head', array($this, 'LoginHead'));  //Runs just before the end of the HTML head section of the login page. 
			add_action('login_form', array($this, 'ValidateUser'));  //Runs just before the end of the HTML head section of the login page. 
			
			add_action('show_user_profile', array($this, 'ShowCustomFields')); //Runs near the end of the user profile editing screen.
			add_action('edit_user_profile', array($this, 'ShowCustomFields')); //Runs near the end of the user profile editing screen in the admin menus. 
			add_action('profile_update', array($this, 'SaveCustomFields'));	//Runs when a user's profile is updated. Action function argument: user ID. 

			//LOCALIZATION
			#Place your language file in the plugin folder and name it "regplus-{language}.mo"
			#replace {language} with your language value from wp-config.php
			//load_plugin_textdomain('regplus', '/wp-content/plugins/register-plus-redux');
			
			//VERSION CONTROL
			if ( $wp_version < 3.0 )
				add_action('admin_notices', array($this, 'VersionWarning'));
		}

		function AddPages() {
			$options_page = add_submenu_page('options-general.php', 'Register Plus Redux Settings', 'Register Plus Redux', 'manage_options', 'register-plus-redux', array($this, 'OptionsPage'));
			add_action("admin_head-$options_page", array($this, 'OptionsHead'));
			add_filter('plugin_action_links', array($this, 'filter_plugin_actions'), 10, 2);
			$options = get_option('register_plus_redux_options');
			if ( $options['verify_user_email'] || $options['verify_user_admin'] )
				add_submenu_page('users.php', 'Unverified Users', 'Unverified Users', 'promote_users', 'unverified-users', array($this, 'UnverifiedUsersPage'));
		}

		function filter_plugin_actions( $links, $file ) {
			static $this_plugin;
			if ( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
			if ( $file == $this_plugin ) {
				$settings_link = '<a href="options-general.php?page=register-plus-redux">Settings</a>';
				array_unshift($links, $settings_link); // before other links
				//$links[] = $settings_link;           // ... or after other links
			}
			return $links;
		}

		function InitializeSettings() {
			$default = array(
				'user_set_password' => '0',
				'show_password_meter' => '0',
				'message_short_password' => 'Too Short',
				'message_bad_password' => 'Bad Password',
				'message_good_password' => 'Good Password',
				'message_strong_password' => 'Strong Password',
				'custom_logo' => '',
				'verify_user_email' => '0',
				'delete_unverified_users_after' => '7',
				'verify_user_admin' => '0',
				'enable_invitation_code' => '0',
				'enable_invitation_tracking_widget' => '0',
				'require_invitation_code' => '0',
				'invitation_code_bank' => array(),
				'allow_duplicate_emails' => '0',

				'show_firstname_field' => '0',
				'show_lastname_field' => '0',
				'show_website_field' => '0',
				'show_aim_field' => '0',
				'show_yahoo_field' => '0',
				'show_jabber_field' => '0',
				'show_about_field' => '0',
				'required_fields' => array(),
				'required_fields_style' => 'border:solid 1px #E6DB55; background-color:#FFFFE0;',
				'show_disclaimer' => '0',
				'message_disclaimer_title' => 'Disclaimer',
				'message_disclaimer' => '',
				'message_disclaimer_agree' => 'Accept the Disclaimer',
				'show_license_agreement' => '0',
				'message_license_title' => 'License Agreement',
				'message_license' => '',
				'message_license_agree' => 'Accept the License Agreement',
				'show_privacy_policy' => '0',
				'message_privacy_policy_title' => 'Privacy Policy',
				'message_privacy_policy' => '',
				'message_privacy_policy_agree' => 'Accept the Privacy Policy',

				'datepicker_firstdayofweek' => '6',
				'datepicker_dateformat' => 'mm/dd/yyyy',
				'datepicker_startdate' => '',
				'datepicker_calyear' => '',
				'datepicker_calmonth' => 'cur',

				'custom_user_message' => '0',
				'user_message_from_email' => get_option('admin_email'),
				'user_message_from_name' => get_option('blogname'),
				'user_message_subject' => sprintf(__('[%s] Your username and password', 'regplus'), get_option('blogname')),
				'user_message_body' => " %blogname% Registration \r\n --------------------------- \r\n\r\n Here are your credentials: \r\n Username: %user_login% \r\n Password: %user_pass% \r\n Confirm Registration: %siteurl% \r\n\r\n Thank you for registering with %blogname%!\r\n",
				'send_user_message_in_html' => '0',
				'user_message_newline_as_br' => '0',
				'user_message_login_link' => site_url(),

				'disable_admin_message' => '0',
				'custom_admin_message' => '0',
				'admin_message_from_email' => get_option('admin_email'),
				'admin_message_from_name' => get_option('blogname'),
				'admin_message_subject' => sprintf(__('[%s] New User Register', 'regplus'), get_option('blogname')),
				'admin_message_body' => " New %blogname% Registration \r\n --------------------------- \r\n\r\n Username: %user_login% \r\n E-Mail: %user_email% \r\n",
				'send_admin_message_in_html' => '0',
				'admin_message_newline_as_br' => '0',

				'custom_registration_page_css' => '',
				'custom_login_page_css' => ''
			);
			if ( !get_option('register_plus_redux_options') ) {
				#Check if settings exist, add defaults in necessary
				add_option('register_plus_redux_options', $default);
			} else {
				#Check settings for new variables, add as necessary
				$options = get_option('register_plus_redux_options');
				foreach ( $default as $k => $v ) {
					if ( !$options[$k] ) {
						$options[$k] = $v;
						$new = true;
					}
				}
				if ( $new ) update_option('register_plus_redux_options', $options);
			}
		}
		
		function OptionsHead() {
			wp_enqueue_script('jquery');
			$options = get_option('register_plus_redux_options');
			?>
			<script type="text/javascript">
			function addInvitationCode() {
				jQuery('<div class="invitation_code"><input type="text" name="invitation_code_bank[]" value="" />&nbsp;<a href="#" onClick="return removeInvitationCode(this);"><img src="<?php echo plugins_url('removeBtn.gif', __FILE__); ?>" alt="<?php echo __('Remove Code', 'regplus'); ?>" title="<?php echo __('Remove Code', 'regplus'); ?>" /></a></div>').appendTo('#invitation_code_bank');
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
								.append('<option value="radio">Radio Field</option>')
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
							.append(jQuery('<a>')
								.attr('href', '#')
								.attr('onClick', 'return removeCustomField(this);')
								.append(jQuery('<img>')
									.attr('src', '<?php echo plugins_url('removeBtn.gif', __FILE__); ?>')
									.attr('alt', '<?php echo __('Remove Field', 'regplus'); ?>')
									.attr('title', '<?php echo __('Remove Field', 'regplus'); ?>')
								)
							)
						)
					);
			}

			function removeCustomField(clickety) {
				jQuery(clickety).parent().parent().remove();
			}

			jQuery(document).ready(function() {
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

				jQuery('#show_license_agreement').change(function() {
					if (jQuery('#show_license_agreement').attr('checked') )
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
				<?php if ( !$options['show_password_meter'] ) echo "jQuery('#meter_settings').hide();"; ?>
				<?php if ( !$options['verify_user_email'] ) echo "jQuery('#verify_user_email_settings').hide();"; ?>
				<?php if ( !$options['enable_invitation_code'] ) echo "jQuery('#invitation_code_settings').hide();"; ?>
				<?php if ( !$options['show_disclaimer'] ) echo "jQuery('#disclaim_settings').hide();"; ?>
				<?php if ( !$options['show_license_agreement'] ) echo "jQuery('#license_agreement_settings').hide();"; ?>
				<?php if ( !$options['show_privacy_policy'] ) echo "jQuery('#privacy_policy_settings').hide();"; ?>
				<?php if ( !$options['custom_user_message'] ) echo "jQuery('#custom_user_message_settings').hide();"; ?>
				<?php if ( !$options['custom_admin_message'] ) echo "jQuery('#custom_admin_message_settings').hide();"; ?>
			});
			</script>
		<?php
		}

		function OptionsPage() {
			?>
			<div class="wrap">
			<h2><?php _e('Register Plus Redux Settings', 'regplus') ?></h2>
			<p><input type="button" class="button" value="<?php _e('Preview Registraton Page', 'regplus'); ?>" name="preview" onclick="window.open('<?php echo site_url('/wp-login.php?action=register'); ?>');" /></p>
			<?php if ( $_POST['notice'] ) echo '<div id="message" class="updated fade"><p><strong>', $_POST['notice'], '.</strong></p></div>'; ?>
			<form method="post" action="">
				<?php wp_nonce_field('register-plus-redux-update-settings'); ?>
				<input type="hidden" name="action" value="update_settings" />
				<?php $options = get_option('register_plus_redux_options'); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Password', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="user_set_password" value="1" <?php if ( $options['user_set_password']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Allow New Registrations to set their own Password', 'regplus'); ?></label><br />
							<label><input type="checkbox" name="show_password_meter" id="show_password_meter" value="1" <?php if ( $options['show_password_meter']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable Password Strength Meter','regplus'); ?></label>
							<div id="meter_settings" style="margin-left:10px;">
								<table>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_short_password"><?php _e('Short', 'regplus'); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_short_password" value="<?php echo $options['message_short_password']; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_bad_password"><?php _e('Bad', 'regplus'); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_bad_password" value="<?php echo $options['message_bad_password']; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_good_password"><?php _e('Good', 'regplus'); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_good_password" value="<?php echo $options['message_good_password']; ?>" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px;"><label for="message_strong_password"><?php _e('Strong', 'regplus'); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_strong_password" value="<?php echo $options['message_strong_password']; ?>" /></td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Custom Logo', 'regplus'); ?></th>
						<td>
							<input type="file" name="custom_logo" id="custom_logo" value="1" />&nbsp;<small><?php _e('Recommended Logo width is 292px, but any height should work.', 'regplus'); ?></small><br /><img src="<?php echo $options['custom_logo']; ?>" alt="" />
							<?php if ($options['custom_logo']) { ?>
							<br />
							<label><input type="checkbox" name="remove_logo" value="1" /><?php _e('Delete Logo', 'regplus'); ?></label>
							<?php } else { ?>
							<p><small><strong><?php _e('Having troubles uploading?','regplus'); ?></strong>&nbsp;<?php _e('Uncheck "Organize my uploads into month- and year-based folders" in ','regplus'); ?><a href="<?php echo site_url('/wp-admin/options-misc.php'); ?>"><?php _e('Miscellaneous Settings', 'regplus'); ?></a>&nbsp;<?php _e('(You can recheck this option after your logo has uploaded.)','regplus'); ?></small></p>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Email Verification', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_email" id="verify_user_email" value="1" <?php if ( $options['verify_user_email']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Prevent fake email address registrations.', 'regplus'); ?></label><br />
							<?php _e('Requires new registrations to click a link in the notification email to enable their account.', 'regplus'); ?>
							<div id="verify_user_email_settings">
								<label><strong><?php _e('Grace Period (days):', 'regplus'); ?></strong>&nbsp;<input type="text" name="delete_unverified_users_after" id="delete_unverified_users_after" style="width:50px;" value="<?php echo $options['delete_unverified_users_after']; ?>" /></label><br />
								<?php _e('Unverified Users will be automatically deleted after grace period expires', 'regplus'); ?>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Admin Verification', 'regplus'); ?></th>
						<td><label><input type="checkbox" name="verify_user_admin" id="verify_user_admin" value="1" <?php if ( $options['verify_user_admin']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Moderate all user registrations to require admin approval. NOTE: Email Verification must be DISABLED to use this feature.', 'regplus'); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Invitation Code', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?php if ( $options['enable_invitation_code']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable Invitation Code(s)', 'regplus'); ?></label>
							<div id="invitation_code_settings">
								<label><input type="checkbox" name="enable_invitation_tracking_widget" value="1" <?php if ( $options['enable_invitation_tracking_widget']) echo 'checked="checked"';  ?>  />&nbsp;<?php _e('Enable Invitation Tracking Dashboard Widget', 'regplus'); ?></label><br />
								<label><input type="checkbox" name="require_invitation_code" value="1" <?php if ( $options['require_invitation_code']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Require Invitation Code to Register', 'regplus'); ?></label>
								<div id="invitation_code_bank">
								<?php
									$invitation_codes = $options['invitation_code_bank'];
									if ( !is_array($options['invitation_code_bank']) ) $options['invitation_code_bank'] = array();
									foreach ($options['invitation_code_bank'] as $invitation_code )
										echo '<div class="invitation_code"><input type="text" name="invitation_code_bank[]" value="', $invitation_code, '" />&nbsp;<a href="#" onClick="return removeInvitationCode(this);"><img src="', plugins_url('removeBtn.gif', __FILE__), '" alt="', __('Remove Code', 'regplus'), '" title="', __('Remove Code', 'regplus'), '" /></a></div>';
								?>
								</div>
								<a href="#" onClick="return addInvitationCode();"><img src="<?php echo plugins_url('addBtn.gif', __FILE__); ?>" alt="<?php _e('Add Code', 'regplus') ?>" title="<?php _e('Add Code', 'regplus') ?>" /></a>&nbsp;<?php _e('Add a new invitation code', 'regplus') ?><br />
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Allow Duplicate Email Addresses', 'regplus'); ?></th>
						<td><label><input type="checkbox" name="allow_duplicate_emails" value="1" <?php if ( $options['allow_duplicate_emails']) echo 'checked="checked""'; ?> />&nbsp;<?php _e('Allow new registrations to use an email address that has been previously registered', 'regplus'); ?></label></td>
					</tr>
				</table>
				<h3><?php _e('Registration Page', 'regplus'); ?></h3>
				<p><?php _e('Check the fields you would like to appear on the Registration Page.', 'regplus'); ?></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Fields', 'regplus'); ?></th>
						<td style="padding: 0px;">
							<table>
								<thead valign="top">
									<td style="padding-top: 0px; padding-bottom: 0px;"></td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Show</td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;">Require</td>
								</thead>
								<tbody>
									<?php if ( !is_array($options['required_fields']) ) $options['required_fields'] = array(); ?>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('First Name', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_firstname_field" value="1" <?php if ( $options['show_firstname_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="firstname" <?php if ( in_array('firstname', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('Last Name', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_lastname_field" value="1" <?php if ( $options['show_lastname_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="lastname" <?php if ( in_array('lastname', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('Website', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_website_field" value="1" <?php if ( $options['show_website_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="website" <?php if ( in_array('website', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('AIM', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_aim_field" value="1" <?php if ( $options['show_aim_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="aim" <?php if ( in_array('aim', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('Yahoo IM', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_yahoo_field" value="1" <?php if ( $options['show_yahoo_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="yahoo" <?php if ( in_array('yahoo', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('Jabber / Google Talk', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_jabber_field" value="1" <?php if ( $options['show_jabber_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="jabber" <?php if ( in_array('jabber', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e('About Yourself', 'regplus'); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_about_field" value="1" <?php if ( $options['show_about_field']) echo 'checked="checked"'; ?> /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="about" <?php if ( in_array('about', $options['required_fields'])) echo 'checked="checked"'; ?> /></td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Required Field Style Rules', 'regplus'); ?></th>
						<td><input type="text" name="required_fields_style" value="<?php echo $options['required_fields_style']; ?>" style="width: 50%;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Disclaimer', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="show_disclaimer" id="show_disclaimer" value="1" <?php if ( $options['show_disclaimer']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable Disclaimer','regplus'); ?></label>
							<div id="disclaim_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_disclaimer_title"><?php _e('Disclaimer Title','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_title" value="<?php echo $options['message_disclaimer_title']; ?>" style="width: 30%;" />							
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_disclaimer"><?php _e('Disclaimer Content','regplus'); ?></label><br />
											<textarea name="message_disclaimer" style="width:100%; height:300px; display:block;"><?php echo stripslashes($options['message_disclaimer']); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_disclaimer_agree><?php _e('Agreement Text','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_agree" value="<?php echo $options['message_disclaimer_agree']; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('License Agreement', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="show_license_agreement" id="show_license_agreement" value="1" <?php if ( $options['show_license_agreement']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable License Agreement','regplus'); ?></label>
							<div id="license_agreement_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_license_title"><?php _e('License Agreement Title','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_title" value="<?php echo $options['message_license_title']; ?>" style="width: 30%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_license"><?php _e('License Agreement Content','regplus'); ?></label><br />
											<textarea name="message_license" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($options['message_license']); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_license_agree"><?php _e('Agreement Text','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_agree" value="<?php echo $options['message_license_agree']; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Privacy Policy', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="show_privacy_policy" id="show_privacy_policy" value="1" <?php if ( $options['show_privacy_policy']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable Privacy Policy','regplus'); ?></label>
							<div id="privacy_policy_settings" style="margin-left:10px;">
								<table width="80%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_privacy_policy_title"><?php _e('Privacy Policy Title','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_title" value="<?php echo $options['message_privacy_policy_title']; ?>" style="width: 30%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px;" >
											<label for"message_privacy_policy"><?php _e('Privacy Policy Content','regplus'); ?></label><br />
											<textarea name="message_privacy_policy" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($options['message_privacy_policy']); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_privacy_policy_agree"><?php _e('Agreement Text','regplus'); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_agree" value="<?php echo $options['message_privacy_policy_agree']; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
				<h3><?php _e('User Defined Fields', 'regplus'); ?></h3>
				<p><?php _e('Enter custom fields you would like to appear on the Profile and/or Registration Page.', 'regplus'); ?></p>
				<p><small><?php _e('Enter Extra Options for Select, Checkboxes and Radio Fields as comma seperated values. For example, if you chose a select box for a custom field of "Gender", your extra options would be "Male,Female".','regplus'); ?></small></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Custom Fields', 'regplus'); ?></th>
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
									$custom_fields = get_option('register_plus_redux_custom_fields');
									if ( !is_array($custom_fields) ) $custom_fields = array();
									foreach ( $custom_fields as $k => $v ) {
										echo '<tr valign="center" class="custom_field">';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="custom_field_name[', $k, ']" value="', $v['custom_field_name'], '" /></td>';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;">';
										echo '		<select name="custom_field_type[', $k, ']">';
										echo '			<option value="text"'; if ( $v['custom_field_type'] == 'text' ) echo ' selected="selected"'; echo '>Text Field</option>';
										echo '			<option value="select"'; if ( $v['custom_field_type'] == 'select' ) echo ' selected="selected"'; echo '>Select Field</option>';
										echo '			<option value="radio"'; if ( $v['custom_field_type'] == 'radio' ) echo ' selected="selected"'; echo '>Radio Field</option>';
										echo '			<option value="textarea"'; if ( $v['custom_field_type'] == 'textarea' ) echo ' selected="selected"'; echo '>Text Area</option>';
										echo '			<option value="date"'; if ( $v['custom_field_type'] == 'date' ) echo ' selected="selected"'; echo '>Date Field</option>';
										echo '			<option value="hidden"'; if ( $v['custom_field_type'] == 'hidden' ) echo ' selected="selected"'; echo '>Hidden Field</option>';
										echo '		</select>';
										echo '	</td>';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="custom_field_options[', $k, ']" value="', $v['custom_field_options'], '" /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_on_profile[', $k, ']" value="1"'; if ( $v['show_on_profile'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_on_registration[', $k, ']" value="1"'; if ( $v['show_on_registration'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_on_registration[', $k, ']" value="1"'; if ( $v['required_on_registration'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><a href="#" onClick="return removeCustomField(this);"><img src="', plugins_url('removeBtn.gif', __FILE__), '" alt="', __('Remove Field', 'regplus'), '" title="', __('Remove Field', 'regplus'), '" /></a></td>';
										echo '</tr>';
									}
									?>
								</tbody>
							</table>
							<a href="#" onClick="return addCustomField();"><img src="<?php echo plugins_url('addBtn.gif', __FILE__); ?>" alt="<?php _e('Add Field', 'regplus') ?>" title="<?php _e('Add Field', 'regplus') ?>" /></a>&nbsp;<?php _e('Add a new custom field.', 'regplus') ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Date Field Settings', 'regplus'); ?></th>
						<td>
							<label for="datepicker_firstdayofweek"><?php _e('First Day of the Week', 'regplus'); ?>:</label>
							<select type="select" name="datepicker_firstdayofweek">
								<option value="7" <?php if ( $options['datepicker_firstdayofweek'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Monday', 'regplus'); ?></option>
								<option value="1" <?php if ( $options['datepicker_firstdayofweek'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Tuesday', 'regplus'); ?></option>
								<option value="2" <?php if ( $options['datepicker_firstdayofweek'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Wednesday', 'regplus'); ?></option>
								<option value="3" <?php if ( $options['datepicker_firstdayofweek'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Thursday', 'regplus'); ?></option>
								<option value="4" <?php if ( $options['datepicker_firstdayofweek'] == '4' ) echo 'selected="selected"'; ?>><?php _e('Friday', 'regplus'); ?></option>
								<option value="5" <?php if ( $options['datepicker_firstdayofweek'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Saturday', 'regplus'); ?></option>
								<option value="6" <?php if ( $options['datepicker_firstdayofweek'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Sunday', 'regplus'); ?></option>
							</select><br />
							<label for="datepicker_dateformat"><?php _e('Date Format', 'regplus'); ?>:</label><input type="text" name="datepicker_dateformat" value="<?php echo $options['datepicker_dateformat']; ?>" style="width:100px;" /><br />
							<label for="datepicker_startdate"><?php _e('First Selectable Date', 'regplus'); ?>:</label><input type="text" name="datepicker_startdate" id="datepicker_startdate" value="<?php echo $options['datepicker_startdate']; ?>"  style="width:100px;" /><br />
							<label for="datepicker_calyear"><?php _e('Default Year', 'regplus'); ?>:</label><input type="text" name="datepicker_calyear" id="datepicker_calyear" value="<?php echo $options['datepicker_calyear']; ?>" style="width:40px;" /><br />
							<label for="datepicker_calmonth"><?php _e('Default Month', 'regplus'); ?>:</label>
							<select name="datepicker_calmonth" id="datepicker_calmonth">
								<option value="cur" <?php if ( $options['datepicker_calmonth'] == 'cur' ) echo 'selected="selected"'; ?>><?php _e('Current Month', 'regplus'); ?></option>
								<option value="0" <?php if ( $options['datepicker_calmonth'] == '0' ) echo 'selected="selected"'; ?>><?php _e('Jan', 'regplus'); ?></option>
								<option value="1" <?php if ( $options['datepicker_calmonth'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Feb', 'regplus'); ?></option>
								<option value="2" <?php if ( $options['datepicker_calmonth'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Mar', 'regplus'); ?></option>
								<option value="3" <?php if ( $options['datepicker_calmonth'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Apr', 'regplus'); ?></option>
								<option value="4" <?php if ( $options['datepicker_calmonth'] == '4' ) echo 'selected="selected"'; ?>><?php _e('May', 'regplus'); ?></option>
								<option value="5" <?php if ( $options['datepicker_calmonth'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Jun', 'regplus'); ?></option>
								<option value="6" <?php if ( $options['datepicker_calmonth'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Jul', 'regplus'); ?></option>
								<option value="7" <?php if ( $options['datepicker_calmonth'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Aug', 'regplus'); ?></option>
								<option value="8" <?php if ( $options['datepicker_calmonth'] == '8' ) echo 'selected="selected"'; ?>><?php _e('Sep', 'regplus'); ?></option>
								<option value="9" <?php if ( $options['datepicker_calmonth'] == '9' ) echo 'selected="selected"'; ?>><?php _e('Oct', 'regplus'); ?></option>
								<option value="10" <?php if ( $options['datepicker_calmonth'] == '10' ) echo 'selected="selected"'; ?>><?php _e('Nov', 'regplus'); ?></option>
								<option value="11" <?php if ( $options['datepicker_calmonth'] == '11' ) echo 'selected="selected"'; ?>><?php _e('Dec', 'regplus'); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<h3><?php _e('Auto-Complete Queries', 'regplus'); ?></h3>
				<p><?php _e('You can now link to the registration page with queries to autocomplete specific fields for the user.  I have included the query keys below and an example of a query URL.', 'regplus'); ?></p>
				<code>user_login&nbsp;user_email&nbsp;firstname&nbsp;lastname&nbsp;user_url&nbsp;aim&nbsp;yahoo&nbsp;jabber&nbsp;about&nbsp;code</code>
				<p><?php _e('For any custom fields, use your custom field label with the text all lowercase, using underscores instead of spaces. For example if your custom field was "Middle Name" your query key would be <code>middle_name</code>', 'regplus'); ?></p>
				<p><strong><?php _e('Example Query URL', 'regplus'); ?></strong></p>
				<code>http://www.skullbit.com/wp-login.php?action=register&user_login=skullbit&user_email=info@skullbit.com&firstname=Skull&lastname=Bit&user_url=www.skullbit.com&aim=skullaim&yahoo=skullhoo&jabber=skulltalk&about=I+am+a+WordPress+Plugin+developer.&code=invitation&middle_name=Danger</code>
				<h3><?php _e('Customize User Notification Email', 'regplus'); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e('Custom User Email Notification', 'regplus'); ?></label></th>
						<td><label><input type="checkbox" name="custom_user_message" id="custom_user_message" value="1" <?php if ( $options['custom_user_message']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
					</tr>
				</table>
				<div id="custom_user_message_settings">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="user_message_from_email"><?php _e('From Email', 'regplus'); ?></label></th>
							<td><input type="text" name="user_message_from_email" id="user_message_from_email" style="width:250px;" value="<?php echo $options['user_message_from_email']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_from_name"><?php _e('From Name', 'regplus'); ?></label></th>
							<td><input type="text" name="user_message_from_name" id="user_message_from_name" style="width:250px;" value="<?php echo $options['user_message_from_name']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_subject"><?php _e('Subject', 'regplus'); ?></label></th>
							<td><input type="text" name="user_message_subject" id="user_message_subject" style="width:350px;" value="<?php echo $options['user_message_subject']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_body"><?php _e('User Message', 'regplus'); ?></label></th>
							<td>
							<?php
							$registration_fields = '';
							if ( $options['show_firstname_field'] ) $registration_fields .= ' %firstname%';
							if ( $options['show_lastname_field'] ) $registration_fields .= ' %lastname%';
							if ( $options['show_website_field'] ) $registration_fields .= ' %user_url%';
							if ( $options['show_aim_field'] ) $registration_fields .= ' %aim%';
							if ( $options['show_yahoo_field'] ) $registration_fields .= ' %yahoo%';
							if ( $options['show_jabber_field'] ) $registration_fields .= ' %jabber%';
							if ( $options['show_about_field'] ) $registration_fields .= ' %about%';
							if ( $options['enable_invitation_code'] ) $registration_fields .= ' %invitation_code%';
							foreach ( $custom_fields as $k => $v ) 
							{
								if ( $v['show_on_registration'] )
								$registration_fields .= ' %'.$this->fnSanitizeFieldName($v['custom_field_name']).'%';
							}
							?>
								<p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong> %user_login %user_pass% %user_message% %blogname% %siteurl%<?php echo $registration_fields; ?> %user_ip% %user_ref% %user_host% %user_agent%</p>
								<textarea name="user_message_body" id="user_message_body" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options['user_message_body']; ?></textarea><br />
								<label><input type="checkbox" name="send_user_message_in_html" value="1" <?php if ( $options['send_user_message_in_html']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML', 'regplus'); ?></label>
								&nbsp;<label><input type="checkbox" name="user_message_newline_as_br" value="1" <?php if ( $options['user_message_newline_as_br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)', 'regplus'); ?></label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="user_message_login_link"><?php _e('Login Redirect URL', 'regplus'); ?></label></th>
							<td><input type="text" name="user_message_login_link" id="user_message_login_link" style="width:250px;" value="<?php echo $options['user_message_login_link']; ?>" /><small><?php _e('This will redirect the users login after registration.', 'regplus'); ?></small></td>
						</tr>
					</table>
				</div>
				<h3><?php _e('Customize Admin Notification Email', 'regplus'); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label for="disable_admin_message"><?php _e('Admin Email Notification', 'regplus'); ?></label></th>
						<td><label><input type="checkbox" name="disable_admin_message" id="disable_admin_message" value="1" <?php if ( $options['disable_admin_message']) echo 'checked="checked"'; ?> /><?php _e('Disable', 'regplus'); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Custom Admin Email Notification', 'regplus'); ?></label></th>
						<td><label><input type="checkbox" name="custom_admin_message" id="custom_admin_message" value="1" <?php if ( $options['custom_admin_message']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
					</tr>
				</table>
				<div id="custom_admin_message_settings">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="admin_message_from_email"><?php _e('From Email', 'regplus'); ?></label></th>
							<td><input type="text" name="admin_message_from_email" id="admin_message_from_email" style="width:250px;" value="<?php echo $options['admin_message_from_email']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_from_name"><?php _e('From Name', 'regplus'); ?></label></th>
							<td><input type="text" name="admin_message_from_name" id="admin_message_from_name" style="width:250px;" value="<?php echo $options['admin_message_from_name']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_subject"><?php _e('Subject', 'regplus'); ?></label></th>
							<td><input type="text" name="admin_message_subject" id="admin_message_subject" style="width:350px;" value="<?php echo $options['admin_message_subject']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="admin_message_body"><?php _e('Admin Message', 'regplus'); ?></label></th>
							<td>
								<p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong> %user_login %user_pass% %user_email% %blogname% %siteurl%<?php echo $registration_fields; ?> %user_ip% %user_ref% %user_host% %user_agent%</p>
								<textarea name="admin_message_body" id="admin_message_body" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options['admin_message_body']; ?></textarea><br />
								<label><input type="checkbox" name="send_admin_message_in_html" value="1" <?php if ( $options['send_admin_message_in_html']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML', 'regplus'); ?></label>
								&nbsp;<label><input type="checkbox" name="admin_message_newline_as_br" value="1" <?php if ( $options['admin_message_newline_as_br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)', 'regplus'); ?></label>
							</td>
						</tr>
					</table>
				</div>
				<br />
				<h3><?php _e('Custom CSS for Register & Login Pages', 'regplus'); ?></h3>
				<p><?php _e('CSS Rule Example:', 'regplus'); ?><code>#user_login{ font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="custom_registration_page_css"><?php _e('Custom Register CSS', 'regplus'); ?></label></th>
						<td><textarea name="custom_registration_page_css" id="custom_registration_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options['custom_registration_page_css']; ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="custom_login_page_css"><?php _e('Custom Login CSS', 'regplus'); ?></label></th>
						<td><textarea name="custom_login_page_css" id="custom_login_page_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options['custom_login_page_css']; ?></textarea></td>
					</tr>
				</table>
				<p><input type="button" class="button" value="<?php _e('Preview Registraton Page', 'regplus'); ?>" name="preview" onclick="window.open('<?php echo site_url('/wp-login.php?action=register'); ?>');" /></p>
				<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'regplus'); ?>" name="submit" /></p>
				
			</form>
			</div>
			<?php
		}

		function UpdateSettings() {
			check_admin_referer('register-plus-redux-update-settings');
			//$options = get_option('register_plus_redux_options');
			$options = array();
			$options["user_set_password"] = $_POST['user_set_password'];
			$options["show_password_meter"] = $_POST['show_password_meter'];
			$options["message_short_password"] = $_POST['message_short_password'];
			$options["message_bad_password"] = $_POST['message_bad_password'];
			$options["message_good_password"] = $_POST['message_good_password'];
			$options["message_strong_password"] = $_POST['message_strong_password'];
			if ( $_FILES['custom_logo']['name'] ) $options['custom_logo'] = $this->UploadLogo();
			elseif ( $_POST['remove_logo'] ) $options['custom_logo'] = '';
			$options["verify_user_email"] = $_POST['verify_user_email'];
			$options["delete_unverified_users_after"] = $_POST['delete_unverified_users_after'];
			$options["verify_user_admin"] = $_POST['verify_user_admin'];
			$options["enable_invitation_code"] = $_POST['enable_invitation_code'];
			$options["enable_invitation_tracking_widget"] = $_POST['enable_invitation_tracking_widget'];
			$options["require_invitation_code"] = $_POST['require_invitation_code'];
			$options["invitation_code_bank"] = $_POST['invitation_code_bank'];
			foreach ( $options["invitation_code_bank"] as $k => $v )
				$options["invitation_code_bank"][$k] = strtolower($v);
			$options["allow_duplicate_emails"] = $_POST['allow_duplicate_emails'];

			$options["show_firstname_field"] = $_POST['show_firstname_field'];
			$options["show_lastname_field"] = $_POST['show_lastname_field'];
			$options["show_website_field"] = $_POST['show_website_field'];
			$options["show_aim_field"] = $_POST['show_aim_field'];
			$options["show_yahoo_field"] = $_POST['show_yahoo_field'];
			$options["show_jabber_field"] = $_POST['show_jabber_field'];
			$options["show_about_field"] = $_POST['show_about_field'];
			$options["required_fields"] = $_POST['required_fields'];
			$options["required_fields_style"] = $_POST['required_fields_style'];
			$options["show_disclaimer"] = $_POST['show_disclaimer'];
			$options["message_disclaimer_title"] = $_POST['message_disclaimer_title'];
			$options["message_disclaimer"] = $_POST['message_disclaimer'];
			$options["message_disclaimer_agree"] = $_POST['message_disclaimer_agree'];
			$options["show_license_agreement"] = $_POST['show_license_agreement'];
			$options["message_license_title"] = $_POST['message_license_title'];
			$options["message_license"] = $_POST['message_license'];
			$options["message_license_agree"] = $_POST['message_license_agree'];
			$options["show_privacy_policy"] = $_POST['show_privacy_policy'];
			$options["message_privacy_policy_title"] = $_POST['message_privacy_policy_title'];
			$options["message_privacy_policy"] = $_POST['message_privacy_policy'];
			$options["message_privacy_policy_agree"] = $_POST['message_privacy_policy_agree'];

			if ( $_POST['custom_field_name'] ) {
				foreach ( $_POST['custom_field_name'] as $k => $field ) {
					if ( $field )
					{
						$custom_fields[$k] = array('custom_field_name' => $field,
							'custom_field_type' => $_POST['custom_field_type'][$k],
							'custom_field_options' => $_POST['custom_field_options'][$k],
							'show_on_profile' => $_POST['show_on_profile'][$k],
							'show_on_registration' => $_POST['show_on_registration'][$k],
							'required_on_registration' => $_POST['required_on_registration'][$k]);
					}
				}
			}
			$options['datepicker_firstdayofweek'] = $_POST['datepicker_firstdayofweek'];
			$options['datepicker_dateformat'] = $_POST['datepicker_dateformat'];
			$options['datepicker_startdate'] = $_POST['datepicker_startdate'];
			$options['datepicker_calyear'] = $_POST['datepicker_calyear'];
			$options['datepicker_calmonth'] = $_POST['datepicker_calmonth'];

			$options['custom_user_message'] = $_POST['custom_user_message'];
			$options['user_message_from_email'] = $_POST['user_message_from_email'];
			$options['user_message_from_name'] = $_POST['user_message_from_name'];
			$options['user_message_subject'] = $_POST['user_message_subject'];
			$options['user_message_body'] = $_POST['user_message_body'];
			$options['send_user_message_in_html'] = $_POST['send_user_message_in_html'];
			$options['user_message_newline_as_br'] = $_POST['user_message_newline_as_br'];
			$options['user_message_login_link'] = $_POST['user_message_login_link'];

			$options['disable_admin_message'] = $_POST['disable_admin_message'];
			$options['custom_admin_message'] = $_POST['custom_admin_message'];
			$options['admin_message_from_email'] = $_POST['admin_message_from_email'];
			$options['admin_message_from_name'] = $_POST['admin_message_from_name'];
			$options['admin_message_subject'] = $_POST['admin_message_subject'];
			$options['admin_message_body'] = $_POST['admin_message_body'];
			$options['send_admin_message_in_html'] = $_POST['send_admin_message_in_html'];
			$options['admin_message_newline_as_br'] = $_POST['admin_message_newline_as_br'];

			$options['custom_registration_page_css'] = $_POST['custom_registration_page_css'];
			$options['custom_login_page_css'] = $_POST['custom_login_page_css'];

			update_option('register_plus_redux_options', $options);
			update_option('register_plus_redux_custom_fields', $custom_fields);
			$_POST['notice'] = __('Settings Saved', 'regplus');
		}

		function UnverifiedUsersPage() {
			global $wpdb;
			if ( $_POST['notice'] )
				echo '<div id="message" class="updated fade"><p><strong>', $_POST['notice'], '.</strong></p></div>';
			$unverified = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE user_login LIKE '%unverified__%'");
			$options = get_option('register_plus_redux_options');
			?>
			<div class="wrap">
				<h2><?php _e('Unverified Users', 'regplus') ?></h2>
				<form id="verify-filter" method="post" action="">
				<?php wp_nonce_field('register-plus-redux-unverified-users'); ?>
				<div class="tablenav">
					<div class="alignleft">
						<input value="<?php _e('Verify Checked Users', 'regplus'); ?>" name="verifyit" class="button-secondary" type="submit">&nbsp;
						<?php if ( $options['verify_user_email'] ) { ?>
						<input value="<?php _e('Resend Verification E-mail', 'regplus'); ?>" name="emailverifyit" class="button-secondary" type="submit">
						<?php } ?>
						&nbsp;<input value="<?php _e('Delete', 'regplus'); ?>" name="vdeleteit" class="button-secondary delete" type="submit">
					</div>
					<br class="clear">
				</div>
				<br class="clear">
				<table class="widefat">
					<thead>
						<tr class="thead">
							<th scope="col" class="check-column"><input onclick="checkAll(document.getElementById('verify-filter'));" type="checkbox"></th>
							<th><?php _e('Unverified ID', 'regplus'); ?></th>
							<th><?php _e('User Name', 'regplus'); ?></th>
							<th><?php _e('E-mail', 'regplus'); ?></th>
							<th><?php _e('Role', 'regplus'); ?></th>
						</tr>
					</thead>
					<tbody id="users" class="list:user user-list">
						<?php foreach ( $unverified as $un ) {
							if ( $alt ) $alt = ''; else $alt = "alternate";
							$user_object = new WP_User($un->ID);
							$roles = $user_object->roles;
							$role = array_shift($roles);
							if ( $options['verify_user_email'] ) $user_login = get_user_meta($un->ID, 'email_verify_user', true);
							elseif ( $options['verify_user_admin'] ) $user_login = get_user_meta($un->ID, 'admin_verify_user', true);
						?>
						<tr id="user-1" class="<?php echo $alt; ?>">
							<th scope="row" class="check-column"><input name="vusers[]" id="user_<?php echo $un->ID; ?>" class="administrator" value="<?php echo $un->ID; ?>" type="checkbox"></th>
							<td><strong><?php echo $un->user_login; ?></strong></td>
							<td><strong><?php echo $user_login; ?></strong></td>
							<td><a href="mailto:<?php echo $un->user_email; ?>" title="<?php _e('e-mail: ', 'regplus'); echo $un->user_email; ?>"><?php echo $un->user_email; ?></a></td>
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
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			check_admin_referer('register-plus-redux-unverified-users');
			$valid = $_POST['vusers'];
			foreach ( $valid as $user_id ) {
				if ( $user_id ) {
					if ( $options['verify_user_email'] ) {
						$stored_user_login = get_user_meta($user_id, 'email_verify_user', true);
						wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
						delete_user_meta($user_id, 'email_verification_code');
						delete_user_meta($user_id, 'email_verify_date');
						delete_user_meta($user_id, 'email_verify_user');
					} elseif ( $options['verify_user_admin'] ) {
						$stored_user_login = get_user_meta($user_id, 'admin_verify_user', true);
						wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
						delete_user_meta($user_id, 'admin_verify_user');
					}
					$user_info = get_userdata($user_id);
					$options = get_option('register_plus_redux_options');
					$message = __('Your account has now been activated by an administrator.')."\r\n";
					$message .= sprintf(__('Username: %s', 'regplus'), $user_info->user_login)."\r\n";
					$message .= site_url('/wp-login.php')."\r\n"; 
					add_filter('wp_mail_from', array($this, 'filter_user_message_from_email'));
					add_filter('wp_mail_from_name', array($this, 'filter_user_message_from_name'));
					wp_mail($user->user_email, sprintf(__('[%s] User Account Activated', 'regplus'), get_option('blogname')), $message);
				}
			}
			$_POST['notice'] = __('Users Verified', 'regplus');
		}

		function VersionWarning() {
			global $wp_version;
			echo "<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __('Register Plus Redux is only compatible with WordPress 3.0 and up. You are currently using WordPress ', 'regplus'), $wp_version, "</strong></p></div>";
		}

		function override_warning() {
			if ( current_user_can(10) && $_GET['page'] == 'register-plus-redux' )
			echo "<div id='register-plus-redux-warning' class='updated fade-ff0000'><p><strong>", __('You have another plugin installed that is conflicting with Register Plus Redux. This other plugin is overriding the user notification emails. Please see <a href="http://skullbit.com/news/register-plus-conflicts/">Register Plus Conflicts</a> for more information.', 'regplus'), "</strong></p></div>";
		}

		function UploadLogo() {
			//v3.5.1 code
			//$upload_dir = ABSPATH . get_option('upload_path');
			//$upload_file = trailingslashit($upload_dir) . basename($_FILES['custom_logo']['name']);
			//if ( !is_dir($upload_dir) )
			//	wp_upload_dir();
			//if ( move_uploaded_file($_FILES['custom_logo']['tmp_name'], $upload_file) ) {
			//	chmod($upload_file, 0777);
			//	$custom_logo = $_FILES['custom_logo']['name'];
			//	return site_url('wp-content/uploads/').$custom_logo;
			//} else { return false; }
			//code recommended by nschmede
			$uploads = wp_upload_dir();
			$upload_dir = $uploads['basedir'];
			$upload_url = $uploads['baseurl'];
			$upload_file = trailingslashit($upload_dir) . basename($_FILES['custom_logo']['name']);
			//echo $upload_file;
			if ( !is_dir($upload_dir) )
				wp_upload_dir();
			if ( move_uploaded_file($_FILES['custom_logo']['tmp_name'], $upload_file) ) {
				chmod($upload_file, 0777);
				$custom_logo = $_FILES['custom_logo']['name'];
				return trailingslashit($upload_url) . $custom_logo;
			} else { return false; }
		}

		function AdminDeleteUnvalidated() {
			$options = get_option('register_plus_redux_options');
			check_admin_referer('register-plus-redux-unverified-users');
			$delete = $_POST['vusers'];
			foreach ( $delete as $user_id ) {
				if ( $user_id ) wp_delete_user($user_id);
			}
			$_POST['notice'] = __('Users Deleted', 'regplus');
		}

		function AdminEmailValidate() {
			global $wpdb;
			check_admin_referer('register-plus-redux-unverified-users');
			$valid = $_POST['vusers'];
			if ( is_array($valid) ):
				foreach ( $valid as $user_id ) {
					$email_verification_code = get_user_meta($user_id, 'email_verification_code', true);
					$user_login = get_user_meta($user_id, 'email_verify_user', true);
					$user_info = get_userdata($user_id);
					//$user_email = $user_info->user_email;
					$prelink = __('Verification URL: ', 'regplus');
					$message = sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n";
					//$message .= sprintf(__('Password: %s', 'regplus'), $plaintext_pass) . "\r\n";
					$message .= $prelink . site_url('/wp-login.php') . '?email_verification_code=' . $email_verification_code . "\r\n";
					$message .= $notice;
					add_filter('wp_mail_from', array($this, 'filter_user_message_from_email'));
					add_filter('wp_mail_from_name', array($this, 'filter_user_message_from_name'));
					wp_mail($user_info->user_email, sprintf(__('[%s] Verify Account Link', 'regplus'), get_option('blogname')), $message);
				}
			$_POST['notice'] = __('Verification Emails have been re-sent', 'regplus');
			else:
			$_POST['notice'] = __('<strong>Error:</strong> Please select a user to send emails to.', 'regplus');
			endif;
		}

		function RegMsg( $errors ) {
			$options = get_option('register_plus_redux_options');
			session_start();
			if ( $errors->errors['registered'] ) {
				//unset($errors->errors['registered']);
			}
			if ( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] ) $errors->add('registeredit', __('Please check your e-mail and click the verification link to activate your account and complete your registration.'), 'message');
			return $errors;
		}

		#Add Javascript & CSS needed
		function PassHead() {
			$options = get_option('register_plus_redux_options');
			if ( isset($_GET['user_login']) ) $user_login = $_GET['user_login'];
			if ( isset($_GET['user_email']) ) $user_email = $_GET['user_email'];
			if ( isset($_GET['user_url']) ) $user_url = $_GET['user_url'];
			if ( $options['user_set_password'] ) {
				wp_enqueue_script('jquery');
				wp_enqueue_script('jquery-color');
				wp_enqueue_script('common');
				wp_enqueue_script('password-strength-meter');
				?>
				<script type='text/javascript'>
				/* <![CDATA[ */
					pwsL10n={
						short: "<?php echo $options['message_short_password']; ?>",
						bad: "<?php echo $options['message_bad_password']; ?>",
						good: "<?php echo $options['message_good_password']; ?>",
						strong: "<?php echo $options['message_strong_password']; ?>"
					}
				/* ]]> */
				</script>
				<script type="text/javascript">
					function check_pass_strength () {
						var pass = jQuery('#pass1').val();
						var user = jQuery('#user_login').val();
				
						//get the result as an object, i'm tired of typing it
						var res = jQuery('#pass-strength-result');
				
						var strength = passwordStrength(pass, user);
				
						jQuery(res).removeClass('short bad good strong');
				
						if ( strength == pwsL10n.bad ) {
							jQuery(res).addClass('message_bad_password');
							jQuery(res).html(pwsL10n.bad);
						}
						else if ( strength == pwsL10n.good ) {
							jQuery(res).addClass('message_good_password');
							jQuery(res).html(pwsL10n.good);
						}
						else if ( strength == pwsL10n.strong ) {
							jQuery(res).addClass('message_strong_password');
							jQuery(res).html(pwsL10n.strong);
						}
						else {
							//this catches 'Too short' and the off chance anything else comes along
							jQuery(res).addClass('message_short_password');
							jQuery(res).html(pwsL10n.short);
						}
					}
				
					jQuery(function($) { 
						$('#pass1').keyup(check_pass_strength) 
						$('.color-palette').click(function() {$(this).siblings('input[name=admin_color]').attr('checked', 'checked')});
					});
					
					jQuery(document).ready(function() {
						jQuery('#pass1,#pass2').attr('autocomplete','off');
						jQuery('#user_login').val('<?php echo $user_login; ?>');
						jQuery('#user_email').val('<?php echo $user_email; ?>');
						jQuery('#user_url').val('<?php echo $user_url; ?>');
					});
				</script>
				<?php
			}

			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script(plugins_url('js/jquery.ui.datepicker.js', __FILE__));
			?>
			<!-- required plugins -->
			<script type="text/javascript" src="<?php echo plugins_url('datepicker/date.js', __FILE__); ?>"></script>
			<!--[if IE]><script type="text/javascript" src="<?php echo plugins_url('datepicker/jquery.bgiframe.js', __FILE__); ?>"></script><![endif]-->
			
			<!-- jquery.datePicker.js -->
			<script type="text/javascript" src="<?php echo plugins_url('datepicker/jquery.datePicker.js', __FILE__); ?>"></script>
			<link href="<?php echo plugins_url('datepicker/datePicker.css', __FILE__); ?>" rel="stylesheet" type="text/css" />

			<script type="text/javascript">
			jQuery.dpText = {
				TEXT_PREV_YEAR	:	'<?php _e('Previous year', 'regplus'); ?>',
				TEXT_PREV_MONTH	:	'<?php _e('Previous month', 'regplus'); ?>',
				TEXT_NEXT_YEAR	:	'<?php _e('Next year', 'regplus'); ?>',
				TEXT_NEXT_MONTH	:	'<?php _e('Next Month', 'regplus'); ?>',
				TEXT_CLOSE	:	'<?php _e('Close', 'regplus'); ?>',
				TEXT_CHOOSE_DATE:	'<?php _e('Choose Date', 'regplus'); ?>'
			}
			Date.dayNames = ['<?php _e('Monday', 'regplus'); ?>', '<?php _e('Tuesday', 'regplus'); ?>', '<?php _e('Wednesday', 'regplus'); ?>', '<?php _e('Thursday', 'regplus'); ?>', '<?php _e('Friday', 'regplus'); ?>', '<?php _e('Saturday', 'regplus'); ?>', '<?php _e('Sunday', 'regplus'); ?>'];
			Date.abbrDayNames = ['<?php _e('Mon', 'regplus'); ?>', '<?php _e('Tue', 'regplus'); ?>', '<?php _e('Wed', 'regplus'); ?>', '<?php _e('Thu', 'regplus'); ?>', '<?php _e('Fri', 'regplus'); ?>', '<?php _e('Sat', 'regplus'); ?>', '<?php _e('Sun', 'regplus'); ?>'];
			Date.monthNames = ['<?php _e('January', 'regplus'); ?>', '<?php _e('February', 'regplus'); ?>', '<?php _e('March', 'regplus'); ?>', '<?php _e('April', 'regplus'); ?>', '<?php _e('May', 'regplus'); ?>', '<?php _e('June', 'regplus'); ?>', '<?php _e('July', 'regplus'); ?>', '<?php _e('August', 'regplus'); ?>', '<?php _e('September', 'regplus'); ?>', '<?php _e('October', 'regplus'); ?>', '<?php _e('November', 'regplus'); ?>', '<?php _e('December', 'regplus'); ?>'];
			Date.abbrMonthNames = ['<?php _e('Jan', 'regplus'); ?>', '<?php _e('Feb', 'regplus'); ?>', '<?php _e('Mar', 'regplus'); ?>', '<?php _e('Apr', 'regplus'); ?>', '<?php _e('May', 'regplus'); ?>', '<?php _e('Jun', 'regplus'); ?>', '<?php _e('Jul', 'regplus'); ?>', '<?php _e('Aug', 'regplus'); ?>', '<?php _e('Sep', 'regplus'); ?>', '<?php _e('Oct', 'regplus'); ?>', '<?php _e('Nov', 'regplus'); ?>', '<?php _e('Dec', 'regplus'); ?>'];
			Date.firstDayOfWeek = <?php echo $options['datepicker_firstdayofweek']; ?>;
			Date.format = '<?php echo $options['datepicker_dateformat']; ?>';
			jQuery(function() {
				jQuery('.date-pick').datePicker({
					clickInput:true,
					startDate:'<?php echo $options['datepicker_startdate']; ?>',
					year:'<?php echo $options['datepicker_calyear']; ?>',
					month:'<?php if ( $options['datepicker_calmonth'] != 'cur') echo $options['datepicker_calmonth']; else echo date('n')-1; ?>'
				})
			});
			</script>

			<style type="text/css">
			a.dp-choose-date { float: left; width: 16px; height: 16px; padding: 0; margin: 5px 3px 0; display: block; text-indent: -2000px; overflow: hidden; background: url('<?php echo plugins_url('datepicker/calendar.png', __FILE__); ?>') no-repeat; }
			a.dp-choose-date.dp-disabled { background-position: 0 -20px; cursor: default; } /* makes the input field shorter once the date picker code * has run (to allow space for the calendar icon */
			input.dp-applied { width: 140px; float: left; }
			#pass1, #pass2, #invitation_code, #firstname, #lastname, #user_url, #aim, #yahoo, #jabber, #about, .custom_field {
			        font-size:24px;
			        width:97%;
			        padding:3px;
			        margin-top:2px;
			        margin-right:6px;
			        margin-bottom:16px;
			        border:1px solid #e5e5e5;
			        background:#fbfbfb;
			}
			.custom_select, .custom_textarea {
				width: 97%;
				padding: 3px;
				margin-right: 6px;
			}
			#about, .custom_textarea {
				height: 60px;
			}
			#disclaimer, #license, #privacy {
				display:block;
				width: 97%;
				padding: 3px;
				background-color:#fff;
				border:solid 1px #A7A6AA;
				font-weight:normal;
			}
			<?php
			$custom = array();
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v['required_on_registration'] && $v['show_on_registration'] ) {
					$custom[] = ', #' . $this->fnSanitizeFieldName($v['custom_field_name']);
				}
			}
			if ( $options['required_fields'][0] ) $required_fields = ', #' . implode(', #', $options['required_fields']);
			if ( $custom[0] ) $required_fields .= implode('', $custom);
			?>
			#user_login, #user_email, #pass1, #pass2 <?php echo $required_fields; ?> {
				<?php echo $options['required_fields_style']; ?>
			}
			<?php if ( strlen($options['message_disclaimer'] ) > 525) { ?>
			#disclaimer {
				height: 200px;
				overflow:scroll;
			}
			<?php } ?>
			<?php if ( strlen($options['message_license'] ) > 525) { ?>
			#license {
				height: 200px;
				overflow:scroll;
			}
			<?php } ?>
			<?php if ( strlen($options['message_privacy_policy']) > 525 ) { ?>
			#privacy {
				height: 200px;
				overflow:scroll;
			}
			<?php } ?>
			#reg_passmail {
				display:none;
			}
			small {
				font-weight:normal;
			}
			#pass-strength-result {
				padding-top: 3px;
				padding-right: 5px;
				padding-bottom: 3px;
				padding-left: 5px;
				margin-top: 3px;
				text-align: center;
				border-top-width: 1px;
				border-right-width: 1px;
				border-bottom-width: 1px;
				border-left-width: 1px;
				border-top-style: solid;
				border-right-style: solid;
				border-bottom-style: solid;
				border-left-style: solid;
				display:block;
			}
			</style>
			<?php
		}

		function LoginHead() {
			$options = get_option('register_plus_redux_options');
			if ( ($options['verify_user_admin'] || $options['verify_user_email']) && $_GET['checkemail'] == 'registered' ) {
				echo '<style type="text/css">';
				echo 'label, #user_login, #user_pass, .forgetmenot, #wp-submit, .message { display:none; }';
				echo '</style>';
			}
			if ( $options['custom_logo'] ) { 
				$custom_logo = str_replace(trailingslashit(site_url()), ABSPATH, $options['custom_logo']);
				list($width, $height, $type, $attr) = getimagesize($custom_logo);
				if ( $_GET['action'] != 'register' )
					wp_enqueue_script('jquery');
					echo '<script type="text/javascript">';
					echo 'jQuery(document).ready(function() {';
					echo 'jQuery("#login h1 a").attr("href", "', get_option('home'), '");';
					echo 'jQuery("#login h1 a").attr("title", "', get_option('blogname'), ' - ', get_option('blogdescription'). ');';
					echo '});';
					echo '</script>';
					echo '<style type="text/css">';
					echo '#login h1 a {';
					echo 'background-image: url(', $options['custom_logo'], ');';
					echo 'background-position:center top;';
					echo 'width: ', $width, 'px;';
					echo 'min-width:292px;';
					echo 'height: ', $height, 'px;';
					echo '</style>';
			}
			if ( $options['custom_registration_page_css'] && $_GET['action'] == 'register' )
			{
				echo '<style type="text/css">';
				echo $options['custom_registration_page_css'];
				echo '</style>';
			}
			elseif ( $options['custom_login_page_css'] )
			{
				echo '<style type="text/css">';
				echo $options['custom_login_page_css'];
				echo '</style>';
			}
		}

		function ValidateUser() {
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			if ( $options['verify_user_admin'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">', __('Your account will be reviewed by an administrator and you will be notified when it is activated.', 'regplus'), '</p>';
			} elseif ( $options['verify_user_email'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">', __('Please activate your account using the verification link sent to your email address.', 'regplus'), '</p>';
			}
			if ( $options['verify_user_email'] && isset($_GET['email_verification_code']) ) {
				$verify_key = $_GET['email_verification_code'];
				$user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='email_verification_code' AND meta_value='$verify_key'");
				if ( $user_id ) {
					$stored_user_login = get_user_meta($user_id, 'email_verify_user', true);
					wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
					delete_user_meta($user_id, 'email_verification_code');
					delete_user_meta($user_id, 'email_verify_date');
					delete_user_meta($user_id, 'email_verify_user');
					$msg = '<p>' . sprintf(__('Thank you %s, your account has been verified, please login.', 'regplus'), $stored_user_login) . '</p>';
					echo $msg;
				}
			}
		}

		function DeleteInvalidUsers() {
			//TODO: delete_unverified_users_after period is being ignored
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			$grace = $options['delete_unverified_users_after'];
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='email_verify_date'");
			$grace_date = date('Ymd', strtotime("-7 days"));
			if ( count($results) > 0 )
			{
				foreach ( $unverified_users as $unverified_user ) {
					if ( $grace_date > $unverified_user->meta_value ) {
						wp_delete_user($unverified_user->user_id);
					}
				}
			}
		}

		function RegisterForm() {
			$options = get_option('register_plus_redux_options');
			if ( $options['show_firstname_field'] ) {
				if ( isset($_GET['firstname']) ) $_POST['firstname'] = $_GET['firstname'];
				echo '<p><label>', _e('First Name', 'regplus'), '<br /><input autocomplete="off" name="firstname" id="firstname" size="25" value="', $_POST['firstname'], '" type="text" tabindex="30" /></label></p>';
			}
			if ( $options['show_lastname_field'] ) {
				if ( isset($_GET['lastname']) ) $_POST['lastname'] = $_GET['lastname'];
				echo '<p><label>', _e('Last Name', 'regplus'), '<br /><input autocomplete="off" name="lastname" id="lastname" size="25" value="', $_POST['lastname'], '" type="text" tabindex="31" /></label></p>';
			}
			if ( $options['show_website_field'] ) {
				if ( isset($_GET['user_url']) ) $_POST['user_url'] = $_GET['user_url'];
				echo '<p><label>', _e('Website', 'regplus'), '<br /><input autocomplete="off" name="user_url" id="user_url" size="25" value="', $_POST['user_url'], '" type="text" tabindex="32" /></label></p>';
			}
			if ( $options['show_aim_field'] ) {
				if ( isset($_GET['aim']) ) $_POST['aim'] = $_GET['aim'];
				echo '<p><label>', _e('AIM', 'regplus'), '<br /><input autocomplete="off" name="aim" id="aim" size="25" value="', $_POST['aim'], '" type="text" tabindex="32" /></label></p>';
			}
			if ( $options['show_yahoo_field'] ) {
				if ( isset($_GET['yahoo']) ) $_POST['yahoo'] = $_GET['yahoo'];
				echo '<p><label>', _e('Yahoo IM', 'regplus'), '<br /><input autocomplete="off" name="yahoo" id="yahoo" size="25" value="', $_POST['yahoo'], '" type="text" tabindex="33" /></label></p>';
			}
			if ( $options['show_jabber_field'] ) {
				if ( isset($_GET['jabber']) ) $_POST['jabber'] = $_GET['jabber'];
				echo '<p><label>', _e('Jabber / Google Talk', 'regplus'), '<br /><input autocomplete="off" name="jabber" id="jabber" size="25" value="', $_POST['jabber'], '" type="text" tabindex="34" /></label></p>';
			}
			if ( $options['show_about_field'] ) {
				if ( isset($_GET['about']) ) $_POST['about'] = $_GET['about'];
				echo '<p><label for="about">', _e('About Yourself', 'regplus'), '</label><br />';
				echo '<small>', _e('Share a little biographical information to fill out your profile. This may be shown publicly.', 'regplus'), '</small><br />';
				echo '<textarea autocomplete="off" name="about" id="about" cols="25" rows="5" tabindex="35">', stripslashes($_POST['about']), '</textarea></p>';
			}
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v['show_on_registration'] ) {
					$id = $this->fnSanitizeFieldName($v['custom_field_name']);
					if ( isset($_GET[$id]) ) $_POST[$id] = $_GET[$id];
					switch ( $v['custom_field_type'] ) {
						case "text":
							echo '<p><label>', $v['custom_field_name'], '<br /><input autocomplete="off" class="custom_field" tabindex="36" name="', $id, '" id="', $id, '" size="25" value="', $_POST[$id], '" type="text" /></label></p>';
							break;
						case "date":
							echo '<p><label>', $v['custom_field_name'], '<br /><input autocomplete="off" class="custom_field date-pick" tabindex="36" name="', $id, '" id="', $id, '" size="25" value="', $_POST[$id], '" type="text" /></label><br /></p>';
							break;
						case "select":
							$custom_field_options = explode(',',$v['custom_field_options']);
							$custom_field_options_text = '';
							foreach ( $custom_field_options as $custom_field_option ) {
								$custom_field_options_text .= '<option value="'.$custom_field_option.'" ';
								if ( $_POST[$id] == $custom_field_option ) $custom_field_options_text .= 'selected="selected"';
								$custom_field_options_text .= '>' . $custom_field_option . '</option>';
							}
							echo '<p><label>', $v['custom_field_name'], '<br /><select class="custom_select" tabindex="36" name="', $id, '" id="', $id, '">', $custom_field_options_text, '</select></label><br /></p>';
							break;
						case "checkbox":
							$custom_field_options = explode(',',$v['custom_field_options']);
							$check = '';
							foreach ( $custom_field_options as $custom_field_option ) {
								$check .= '<label><input type="checkbox" class="custom_checkbox" tabindex="36" name="'.$id.'[]" id="'.$id.'" ';
								//if ( in_array($custom_field_option, $_POST[$id])) $check .= 'checked="checked" ';
								$check .= 'value="'.$custom_field_option.'" /> '.$custom_field_option.'</label> ';
							}
							echo '<p><label>', $v['custom_field_name'], '</label><br />';
							echo $check.'<br /></p>';
							break;
						case "radio":
							$custom_field_options = explode(',',$v['custom_field_options']);
							$radio = '';
							foreach ( $custom_field_options as $custom_field_option ) {
								$radio .= '<label><input type="radio" class="custom_radio" tabindex="36" name="'.$id.'" id="'.$id.'" ';
								//if ( in_array($custom_field_option, $_POST[$id])) $radio .= 'checked="checked" ';
								$radio .= 'value="'.$custom_field_option.'" /> '.$custom_field_option.'</label> ';
							}
							echo '<p><label>', $v['custom_field_name'], '</label><br />';
							echo $radio.'<br /></p>';
							break;
						case "textarea":
							echo '<p><label>', $v['custom_field_name'], ': <br /><textarea tabindex="36" name="', $id, '" cols="25" rows="5" id="', $id, '" class="custom_textarea">', $_POST[$id], '</textarea></label><br /></p>';
							break;
						case "hidden":
							echo '<input class="custom_field" tabindex="36" name="', $id, '" value="', $_POST[$id], '" type="hidden" />';
							break;
					}
				}
			}
			if ( $options['user_set_password'] ) {
				echo '<p><label>', _e('Password', 'regplus'), '<br /><input autocomplete="off" name="pass1" id="pass1" size="25" value="', $_POST['password'], '" type="password" tabindex="40" /></label></p>';
				echo '<p><label>', _e('Confirm Password', 'regplus'), '<br /><input autocomplete="off" name="pass2" id="pass2" size="25" value="', $_POST['password'], '" type="password" tabindex="41" /></label></p>';
				if ( $options['show_password_meter']) { 
					echo '<div id="pass-strength-result">', $options['message_short_password'], '</div>';
					echo '<small>', _e('Hint: The password should be at least seven characters long. To make it stronger, use upper and lower case letters, numbers and symbols like ! " ? $ % ^ &amp; ).', 'regplus'), '</small>';
				}
			}
			if ( $options['enable_invitation_code'] ) {
				if ( isset($_GET['invitation_code']) ) $_POST['invitation_code'] = $_GET['invitation_code'];
				echo '<p><label>', _e('Invitation Code', 'regplus'), '<br /><input name="invitation_code" id="invitation_code" size="25" value="', $_POST['invitation_code'], '" type="text" tabindex="45" /></label></p>';
				if ($options['require_invitation_code'])
					echo '<small>', _e('This website is currently closed to public registrations.  You will need an invitation code to register.', 'regplus'), '</small>';
				else
					echo '<small>', _e('Have an invitation code? Enter it here. (This is not required)', 'regplus'), '</small>';
			}
			if ($options['show_disclaimer'] ) {
				echo '<p>';
				echo '	<label>', stripslashes($options['message_disclaimer_title']), '<br />';
				echo '	<span id="license">', stripslashes($options['message_disclaimer']), '</span>';
				echo '	<input name="accept_disclaimer" value="1" type="checkbox" tabindex="50"'; if ( $_POST['accept_disclaimer']) echo ' checked="checked"'; echo '/>', $options['message_disclaimer_agree'], '</label>';
				echo '</p>';
			}
			if ( $options['show_license_agreement'] ) {
				echo '<p>';
				echo '	<label>', stripslashes($options['message_license_title']), '<br />';
				echo '	<span id="license">', stripslashes($options['message_license']), '</span>';
				echo '	<input name="accept_license_agreement" value="1" type="checkbox" tabindex="50"'; if ( $_POST['accept_license_agreement']) echo ' checked="checked"'; echo '/>', $options['message_license_agree'], '</label>';
				echo '</p>';
			}
			if ( $options['show_privacy_policy'] ) {
				echo '<p>';
				echo '	<label>', stripslashes($options['message_privacy_policy_title']), '<br />';
				echo '	<span id="license">', stripslashes($options['message_privacy_policy']), '</span>';
				echo '	<input name="accept_privacy_policy" value="1" type="checkbox" tabindex="50"'; if ( $_POST['accept_privacy_policy']) echo ' checked="checked"'; echo '/>', $options['message_privacy_policy_agree'], '</label>';
				echo '</p>';
			}
		}

		function RegistrationErrors( $errors ) {
			$options = get_option('register_plus_redux_options');
			if ( $options['allow_duplicate_emails'] ) {
				//TODO: Verify this error
				if ($errors->errors['allow_duplicate_emails'] ) unset($errors->errors['allow_duplicate_emails']);
			}
			if ( $options['show_firstname_field'] && in_array('firstname', $options['required_fields']) ) {
				if ( empty($_POST['firstname']) || $_POST['firstname'] == '' ) {
					$errors->add('empty_firstname', __('<strong>ERROR</strong>: Please enter your First Name.', 'regplus'));
				}
			}
			if ( $options['show_lastname_field'] && in_array('lastname', $options['required_fields']) ) {
				if ( empty($_POST['lastname']) || $_POST['lastname'] == '' ) {
					$errors->add('empty_lastname', __('<strong>ERROR</strong>: Please enter your Last Name.', 'regplus'));
				}
			}
			if ( $options['show_website_field'] && in_array('website', $options['required_fields']) ) {
				if ( empty($_POST['user_url']) || $_POST['user_url'] == '' ) {
					$errors->add('empty_user_url', __('<strong>ERROR</strong>: Please enter your Website URL.', 'regplus'));
				}
			}
			if ( $options['show_aim_field'] && in_array('aim', $options['required_fields']) ) {
				if ( empty($_POST['aim']) || $_POST['aim'] == '' ) {
					$errors->add('empty_aim', __('<strong>ERROR</strong>: Please enter your AIM username.', 'regplus'));
				}
			}
			if ( $options['show_yahoo_field'] && in_array('yahoo', $options['required_fields']) ) {
				if ( empty($_POST['yahoo']) || $_POST['yahoo'] == '' ) {
					$errors->add('empty_yahoo', __('<strong>ERROR</strong>: Please enter your Yahoo IM username.', 'regplus'));
				}
			}
			if ( $options['show_jabber_field'] && in_array('jabber', $options['required_fields']) ) {
				if ( empty($_POST['jabber']) || $_POST['jabber'] == '' ) {
					$errors->add('empty_jabber', __('<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.', 'regplus'));
				}
			}
			if ( $options['show_about_field'] && in_array('about', $options['required_fields']) ) {
				if ( empty($_POST['about']) || $_POST['about'] == '' ) {
					$errors->add('empty_about', __('<strong>ERROR</strong>: Please enter some information About Yourself.', 'regplus'));
				}
			}
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( $v['required_on_registration'] && $v['show_on_registration'] ) {
					$id = $this->fnSanitizeFieldName($v['custom_field_name']);
					if ( empty($_POST[$id]) || $_POST[$id] == '' ) {
						$errors->add('empty_' . $id, __('<strong>ERROR</strong>: Please complete '.$v['custom_field_name'].'.', 'regplus'));
					}
				}
			}
			if ( $options['user_set_password'] ) {
				if ( empty($_POST['pass1']) || $_POST['pass1'] == '' || empty($_POST['pass2']) || $_POST['pass2'] == '' ) {
					$errors->add('empty_password', __('<strong>ERROR</strong>: Please enter a Password.', 'regplus'));
				} elseif ( $_POST['pass1'] !=  $_POST['pass2'] ) {
					$errors->add('password_mismatch', __('<strong>ERROR</strong>: Your Password does not match.', 'regplus'));
				} elseif ( strlen($_POST['pass1']) < 6 ) {
					$errors->add('password_length', __('<strong>ERROR</strong>: Your Password must be at least 6 characters in length.', 'regplus'));
				} else {
					$_POST['password'] = $_POST['pass1'];
				}
			}
			if ( $options['enable_invitation_code'] && $options['require_invitation_code'] ) {
				if ( empty($_POST['invitation_code']) || $_POST['invitation_code'] == '' ) {
					$errors->add('empty_invitation_code', __('<strong>ERROR</strong>: Please enter an Invitation Code.', 'regplus'));
				} elseif ( !in_array(strtolower($_POST['invitation_code']), $options['invitation_code_bank']) ) {
					$errors->add('invitation_code_mismatch', __('<strong>ERROR</strong>: Your Invitation Code is incorrect.', 'regplus'));
				}
			}
			if ( $options['show_disclaimer'] ) {
				if ( !$_POST['accept_disclaimer'] ) {
					$errors->add('show_disclaimer', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_disclaimer_title']) . '.');
				}
			}
			if ( $options['show_license_agreement'] ) {
				if ( !$_POST['accept_license_agreement'] ) {
					$errors->add('show_license_agreement', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_license_title']) . '.');
				}
			}
			if ( $options['show_privacy_policy'] ) {
				if ( !$_POST['accept_privacy_policy'] ) {
					$errors->add('show_privacy_policy', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_privacy_policy_title']) . '.');
				}
			}
			return $errors;
		}

		function ShowCustomFields() {
			global $user_ID;
			get_currentuserinfo();					//fills $user_ID
			if ( $_GET['user_id'] ) $user_ID = $_GET['user_id'];	//when editing a user use their user_ID
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( is_array($custom_fields) ) {
				echo '<h3>', __('Additional Information', 'regplus'), '</h3>';
				echo '<table class="form-table">';
				foreach ( $custom_fields as $k => $v ) {
					if ( $v['show_on_profile'] ) {
						$custom_field_name = $this->fnSanitizeFieldName($v['custom_field_name']);
						$value = get_user_meta($user_ID, $custom_field_name, true);
						echo '	<tr>';
						echo '		<th><label for="', $custom_field_name, '">', $v['custom_field_name'], '</label></th>';
						switch ( $v['custom_field_type'] ) {
							case "text":
								echo '		<td><input type="text" name="', $custom_field_name, '" id="', $custom_field_name, '" value="', $value, '" class="regular-text" /></td>';
								break;
							case "hidden":
								echo '		<td><input type="text" disabled="disabled" name="', $custom_field_name, '" id="', $custom_field_name, '" value="', $value, '" /></td>';
								break;
							case "select":
								echo '		<td>';
								echo '			<select name="', $custom_field_name, '" id="', $custom_field_name, '">';
								$custom_field_options = explode(',', $v['custom_field_options']);
								foreach ( $custom_field_options as $custom_field_option )
									echo '				<option value="',  $custom_field_option,  '"'; if ( $value == $custom_field_option ) echo ' selected="selected"'; echo '>', $custom_field_option, '</option>';
								echo '			</select>';
								echo '		</td>';
								break;
							case "textarea":
								echo '		<td><textarea name="', $custom_field_name, '" id="', $custom_field_name, '" rows="5" cols="30">', stripslashes($value), '</textarea></td>';
								break;
							case "checkbox":
								echo '		<td>';
								$custom_field_options = explode(',', $v['custom_field_options']);
								$values = explode(', ', $value);
								foreach ( $custom_field_options as $custom_field_option )
									echo '			<label><input type="checkbox" name="', $custom_field_name, '[]" value="', $custom_field_option, '"'; if ( in_array($custom_field_option, $values) ) echo ' checked="checked"'; echo ' />&nbsp;', $custom_field_option, '</label>';
								echo '		</td>';
								break;
							case "radio":
								echo '		<td>';
								$custom_field_options = explode(',', $v['custom_field_options']);
								foreach ( $custom_field_options as $custom_field_option )
									echo '			<label><input type="radio" name="', $custom_field_name, '" value="', $custom_field_option, '"'; if ( $value == $custom_field_option ) echo ' checked="checked"'; echo '>&nbsp;', $custom_field_option, '</label>';
								echo '		</td>';
								break;
						}
						echo '	</tr>';
					}
				}
				echo '</table>';
			}
		}

		function SaveCustomFields( $user_id ) {
			global $wpdb;
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			//if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					//echo 'in foreach<br/>';
					if ( $v['show_on_profile'] ) {
						$key = $this->fnSanitizeFieldName($v['custom_field_name']);
						if ( is_array($_POST[$key]) ) $_POST[$key] = implode(', ', $_POST[$key]);
						update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
					}
				}
			//}
		}

		function filter_admin_message_from_email() {
			$options = get_option('register_plus_redux_options');
			return $options['admin_message_from_email'];
		}

		function filter_admin_message_from_name() {
			$options = get_option('register_plus_redux_options');
			return $options['admin_message_from_name'];
		}

		function filter_user_message_from_email() {
			$options = get_option('register_plus_redux_options');
			return $options['user_message_from_email'];
		}

		function filter_user_message_from_name() {
			$options = get_option('register_plus_redux_options');
			return $options['user_message_from_name'];
		}

		function fnRandomString( $len ) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
			$rand_string = '';
			for ($i = 0; $i < $len; $i++) {
				$rand_string .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
			}
			return $rand_string;
		}

		function fnSanitizeFieldName( $key ) {
			$key = str_replace(' ', '_', $custom_field_name);
			$key = strtolower($key);
			$key = sanitize_key($key);
			return $key;
		}

	}
}

if ( class_exists('RegisterPlusReduxPlugin') ) $registerPlusRedux = new RegisterPlusReduxPlugin();

if ( function_exists('wp_new_user_notification') )
	add_action('admin_notices', array($registerPlusRedux, 'override_warning'));

# Override set user password and send email to User #
if ( !function_exists('wp_new_user_notification') ) :
	function wp_new_user_notification($user_id, $plaintext_pass = '') {
		$user = new WP_User($user_id);
		global $wpdb, $registerPlusRedux;
		$options = get_option('register_plus_redux_options');
		$ref = explode('?', $_SERVER['HTTP_REFERER']);
		$ref = $ref[0];
		$admin = site_url('wp-admin/unverified-users.php');
		if ( $options['show_firstname_field'] && $_POST['firstname'] ) update_user_meta($user_id, 'first_name', $wpdb->prepare($_POST['firstname']));
		if ( $options['show_lastname_field'] && $_POST['lastname'] ) update_user_meta($user_id, 'last_name', $wpdb->prepare($_POST['lastname']));
		if ( $options['show_website_field'] && $_POST['user_url'] ) {
			$url = esc_url_raw( $_POST['user_url'] );
			$user->user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is', $url) ? $url : 'http://'.$url;
			wp_update_user(array('ID' => $user_id, 'user_url' => $wpdb->prepare($url)));
		}
		if ( $options['show_aim_field'] && $_POST['aim'] ) update_user_meta($user_id, 'aim', $wpdb->prepare($_POST['aim']));
		if ( $options['show_yahoo_field'] && $_POST['yahoo'] ) update_user_meta($user_id, 'yim', $wpdb->prepare($_POST['yahoo']));
		if ( $options['show_jabber_field'] && $_POST['jabber'] ) update_user_meta($user_id, 'jabber', $wpdb->prepare($_POST['jabber']));
		if ( $options['show_about_field'] && $_POST['about'] ) update_user_meta($user_id, 'description', $wpdb->prepare($_POST['about']));
		$custom_fields = get_option('register_plus_redux_custom_fields');
		if ( !is_array($custom_fields) ) $custom_fields = array();
		foreach ( $custom_fields as $k => $v ) {
			$custom_Field_name = $registerPlusRedux->fnSanitizeFieldName($v['custom_field_name']);
			if ( $v['show_on_registration'] && $_POST[$custom_Field_name] ) {
				if ( is_array($_POST[$custom_Field_name]) ) $_POST[$custom_Field_name] = implode(', ', $_POST[$custom_Field_name]);
				update_user_meta($user_id, $custom_Field_name, $wpdb->prepare($_POST[$custom_Field_name]));
			}
		}
		if ( $options['user_set_password'] && $_POST['password'] ) $plaintext_pass = $wpdb->prepare($_POST['password']);
		elseif ( $ref == $admin && $_POST['pass1'] == $_POST['pass2'] ) $plaintext_pass = $wpdb->prepare($_POST['pass1']);
		elseif ( $plaintext_pass = '') $plaintext_pass = $registerPlusRedux->fnRandomString(6);
		if ( $options['enable_invitation_code'] && $_POST['invitation_code'] ) update_user_meta($user_id, 'invitation_code', $wpdb->prepare($_POST['invitation_code']));
		if ( $ref != $admin && $options['verify_user_admin'] ) {
			update_user_meta($user_id, 'admin_verify_user', $user->user_login);
			$temp_login = 'unverified__' . $registerPlusRedux->fnRandomString(7);
			$notice = __('Your account requires activation by an administrator before you will be able to login.', 'regplus') . "\r\n";
		} elseif ( $ref != $admin && $options['verify_user_email'] ) {
			$email_verification_code = $registerPlusRedux->fnRandomString(25);
			update_user_meta($user_id, 'email_verification_code', $email_verification_code);
			update_user_meta($user_id, 'email_verify_date', date('Ymd'));
			update_user_meta($user_id, 'email_verify_user', $user->user_login);
			$prelink = __('Verification URL: ', 'regplus');
			$notice = __('Please use the link above to verify and activate your account', 'regplus') . "\r\n";
			$temp_login = 'unverified__' . $registerPlusRedux->fnRandomString(7);
		}
		wp_set_password($plaintext_pass, $user_id);
		$user_login = stripslashes($user->user_login);
		$user_email = stripslashes($user->user_email);
		$user_url = stripslashes($user->user_url);
		if ( $options['custom_admin_message'] && !$options['disable_admin_message'] ) {
			if ( $options['send_admin_message_in_html'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $options['admin_message_from_email'] . "\r\n" . 'Reply-To: ' . $options['admin_message_from_email'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'filter_admin_message_from_email'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'filter_admin_message_from_name'));
			$message = str_replace('%user_login%', $user_login, $options['admin_message_body']);
			$message = str_replace('%user_email%', $user_email, $message);
			$message = str_replace('%blogname%', get_option('blogname'), $message);
			$message = str_replace('%user_ip%', $_SERVER['REMOTE_ADDR'], $message);
			$message = str_replace('%user_host%', gethostbyaddr($_SERVER['REMOTE_ADDR']), $message);
			$message = str_replace('%user_ref%', $_SERVER['HTTP_REFERER'], $message);
			$message = str_replace('%user_agent%', $_SERVER['HTTP_USER_AGENT'], $message);
			if ( $options['show_firstname_field'] ) $message = str_replace('%firstname%', $_POST['firstname'], $message);
			if ( $options['show_lastname_field'] ) $message = str_replace('%lastname%', $_POST['lastname'], $message);
			if ( $options['show_website_field'] ) $message = str_replace('%user_url%', $_POST['user_url'], $message);
			if ( $options['show_aim_field'] ) $message = str_replace('%aim%', $_POST['aim'], $message);
			if ( $options['show_yahoo_field'] ) $message = str_replace('%yahoo%', $_POST['yahoo'], $message);
			if ( $options['show_jabber_field'] ) $message = str_replace('%jabber%', $_POST['jabber'], $message);
			if ( $options['show_about_field'] ) $message = str_replace('%about%', $_POST['about'], $message);
			if ( $options['enable_invitation_code'] ) $message = str_replace('%invitation_code%', $_POST['invitation_code'], $message);
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$custom_Field_name = $registerPlusRedux->fnSanitizeFieldName($v['custom_field_name']);
				$message = str_replace('%'.$custom_Field_name.'%', get_user_meta($user_id, $custom_Field_name, true), $message);
			}
			$message = str_replace('%siteurl%', site_url(), $message);
			if ( $options['send_admin_message_in_html'] && $options['admin_message_newline_as_br'] )
				$message = nl2br($message);
			wp_mail(get_option('admin_message'), $options['admin_message_subject'], $message, $headers); 
		} elseif ( !$options['custom_admin_message'] && !$options['disable_admin_message']) {
			$message = sprintf(__('New user Register on your blog %s:', 'regplus'), get_option('blogname')) . "\r\n\r\n";
			$message .= sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n\r\n";
			$message .= sprintf(__('E-mail: %s', 'regplus'), $user_email) . "\r\n";
			wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Register', 'regplus'), get_option('blogname')), $message);
		}
		if ( empty($plaintext_pass) )
			return;
		if ( $options['custom_user_message'] ) {
			if ( $options['send_user_message_in_html'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $options['user_message_from_email'] . "\r\n" . 'Reply-To: ' . $options['user_message_from_email'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'filter_user_message_from_email'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'filter_user_message_from_name'));
			$message = str_replace('%user_pass%', $plaintext_pass, $options['user_message_body']);
			$message = str_replace('%user_login%', $user_login, $message);
			$message = str_replace('%user_email%', $user_email, $message);
			$message = str_replace('%blogname%', get_option('blogname'), $message);
			$message = str_replace('%user_ip%', $_SERVER['REMOTE_ADDR'], $message);
			$message = str_replace('%user_host%', gethostbyaddr($_SERVER['REMOTE_ADDR']), $message);
			$message = str_replace('%user_ref%', $_SERVER['HTTP_REFERER'], $message);
			$message = str_replace('%user_agent%', $_SERVER['HTTP_USER_AGENT'], $message);
			if ( $options['show_firstname_field'] ) $message = str_replace('%firstname%', $_POST['firstname'], $message);
			if ( $options['show_lastname_field'] ) $message = str_replace('%lastname%', $_POST['lastname'], $message);
			if ( $options['show_website_field'] ) $message = str_replace('%user_url%', $_POST['user_url'], $message);
			if ( $options['show_aim_field'] ) $message = str_replace('%aim%', $_POST['aim'], $message);
			if ( $options['show_yahoo_field'] ) $message = str_replace('%yahoo%', $_POST['yahoo'], $message);
			if ( $options['show_jabber_field'] ) $message = str_replace('%jabber%', $_POST['jabber'], $message);
			if ( $options['show_about_field'] ) $message = str_replace('%about%', $_POST['about'], $message);
			if ( $options['enable_invitation_code'] ) $message = str_replace('%invitation_code%', $_POST['invitation_code'], $message);
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$custom_Field_name = $registerPlusRedux->fnSanitizeFieldName($v['custom_field_name']);
				$message = str_replace('%'.$custom_Field_name.'%', get_user_meta($user_id, $custom_Field_name, true), $message);
			}
			$redirect = 'redirect_to=' . $options['user_message_login_link'];
			if ( $options['verify_user_email'] )
				$siteurl = site_url('/wp-login.php') . '?email_verification_code=' . $email_verification_code . '&' . $redirect;
			else
				$siteurl = site_url('/wp-login.php') . '?' . admin_message_subject . $redirect;
			$message = str_replace('%admin_message_subject%', $siteurl, $message);
			if ( $options['send_user_message_in_html'] && $options['user_message_newline_as_br'] )
				$message = nl2br($message);
			wp_mail($user_email, $options['user_message_subject'], $message, $headers); 
		} elseif ( !$options['custom_user_message'] ) {
			$message = sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n";
			$message .= sprintf(__('Password: %s', 'regplus'), $plaintext_pass) . "\r\n";
			//$message .= site_url('/wp-login.php');
			$message .= $prelink . site_url('/wp-login.php') . '?email_verification_code=' . $email_verification_code . "\r\n"; 
			$message .= $notice; 
			wp_mail($user_email, sprintf(__('[%s] Your username and password', 'regplus'), get_option('blogname')), $message);
		}
		if ( $ref != $admin && ($options['verify_user_email'] || $options['verify_user_admin']) ) 
			wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($temp_login)));
	}
endif;
?>