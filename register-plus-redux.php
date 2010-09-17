<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/register-plus-redux/
Description: Fork of Register Plus
Version: 3.6.2
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
				add_action('admin_menu', array($this, 'AddPages') );
				if ( $_GET['page'] == 'register-plus-redux' ) {
					wp_enqueue_script('jquery');
					add_action('admin_head', array($this, 'OptionsHead'));
					if ( $_POST['action'] == 'update_settings' )
						add_action('init', array($this, 'UpdateSettings') );
				}
			}

			#Add Register Form Fields
			add_action('register_form', array($this, 'RegForm'));
			#Add Register Page Javascript & CSS
			if ( $_GET['action'] == 'register' )
				add_action('login_head', array($this, 'PassHead'));
				#Add Custom Logo CSS to Login Page
				add_action('login_head', array($this, 'LogoHead'));
				#Hide initial login fields when email verification is enabled
				add_action('login_head', array($this, 'HideLogin'));
				
				add_action('show_user_profile', array($this, 'ShowCustomFields')); //whenever profile is shown, show custom fields
				add_action('edit_user_profile', array($this, 'ShowCustomFields')); //whenever profile is edit, add custom fields
				add_action('profile_update', array($this, 'SaveCustomFields'));	//whenever profile is updated, also update custom fields
				#Validate User
				add_action('login_form', array($this, 'ValidateUser'));
				#Delete Invalid Users
				add_action('init', array($this, 'DeleteInvalidUsers'));
				#Admin Validate Users
				if ( $_POST['verifyit'] )
					add_action('init', array($this, 'AdminValidate'));
				#Admin Resend VerificatioN Email
				if ( $_POST['emailverifyit'] )
					add_action('init', array($this, 'AdminEmailValidate'));
				#Admin Delete Unverified User
				if ( $_POST['vdeleteit'] )
					add_action('init', array($this, 'AdminDeleteUnvalidated'));
			//FILTERS
			#Check Register Form for Errors
			add_filter('registration_errors', array($this, 'RegErrors'));
			//LOCALIZATION
			#Place your language file in the plugin folder and name it "regplus-{language}.mo"
			#replace {language} with your language value from wp-config.php
			load_plugin_textdomain('regplus', '/wp-content/plugins/register-plus-redux');
			
			//VERSION CONTROL
			if ( $wp_version < 3.0 )
				add_action('admin_notices', array($this, 'VersionWarning'));
		}

		function AddPages() {
			add_submenu_page('options-general.php', 'Register Plus Redux Settings', 'Register Plus Redux', 'manage_options', 'register-plus-redux', array($this, 'OptionsPage'));
			add_filter('plugin_action_links', array($this, 'filter_plugin_actions'), 10, 2);
			$options = get_option('register_plus_redux_options');
			if ( $options['email_verify'] || $options['admin_verify'] )
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
				'required_fields_style' => 'border:solid 1px #E6DB55;background-color:#FFFFE0;',
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



				'email_verify'		=> '0',
				'admin_verify'		=> '0',
				'email_delete_grace'	=> '7',
				'html'			=> '0',
				'adminhtml'		=> '0',
				'from'			=> get_option('admin_email'),
				'fromname'		=> get_option('blogname'),
				'subject'		=> sprintf(__('[%s] Your username and password', 'regplus'), get_option('blogname')),
				'custom_msg'		=> '0',
				'user_nl2br'		=> '0',
				'msg'			=> " %blogname% Registration \r\n --------------------------- \r\n\r\n Here are your credentials: \r\n Username: %user_login% \r\n Password: %user_pass% \r\n Confirm Registration: %siteurl% \r\n\r\n Thank you for registering with %blogname%!\r\n",
				'disable_admin'		=> '0',
				'adminfrom'		=> get_option('admin_email'),
				'adminfromname'		=> get_option('blogname'),
				'adminsubject'		=> sprintf(__('[%s] New User Register', 'regplus'), get_option('blogname')),
				'custom_adminmsg'	=> '0',
				'admin_nl2br'		=> '0',
				'adminmsg'		=> " New %blogname% Registration \r\n --------------------------- \r\n\r\n Username: %user_login% \r\n E-Mail: %user_email% \r\n",
				'logo'			=> '',
				'login_redirect'	=> get_option('siteurl'),
				'register_css'		=> '',
				'login_css'		=> '',
				'datepicker_firstdayofweek'		=> 6,
				'datepicker_dateformat'		=> 'mm/dd/yyyy',
				'startdate'		=> '',
				'calyear'		=> '',
				'calmonth'		=> 'cur'
			);
			if ( !get_option('register_plus_redux_options') ) {
				#Check if settings exist, add defaults in necessary
				add_option('register_plus_redux_options', $default);
			} else {
				#Check settings for new variables, add as necessary
				$options = get_option('register_plus_redux_options');
				foreach ( $default as $key => $val ) {
					if ( !$options[$key] ) {
						$options[$key] = $val;
						$new = true;
					}
				}
				if ( $new ) update_option('register_plus_redux_options', $options);
			}
		}
		
		function OptionsHead() {
			$options = get_option('register_plus_redux_options');
?>
<script type="text/javascript">
	function set_add_del_code() {
		jQuery('.remove_code').show();
		jQuery('.add_code').hide();
		jQuery('.add_code:last').show();
		jQuery(".invitation_code:only-child > .remove_code").hide();
	}

	function selremcode(clickety) {
		jQuery(clickety).parent().remove();
		//set_add_del_code();
		//return false;
	}

	function seladdcode(clickety) {
		jQuery('.invitation_code:last').after(jQuery('.invitation_code:last').clone());
		jQuery('.invitation_code:last input').attr('value', '');
		//set_add_del_code(); 
		//return false;
	}

	function set_add_del() {
		jQuery('.remove_row').show();
		jQuery('.add_row').hide();
		jQuery('.add_row:last').show();
		jQuery(".row_block:only-child > .remove_row").hide();
	}

	function selrem(clickety) {
		jQuery(clickety).parent().parent().remove();
		set_add_del();
		return false;
	}

	function seladd(clickety) {
		jQuery('.row_block:last').after(
			jQuery('.row_block:last').clone());
		jQuery('.row_block:last input.custom').attr('value', '');
		jQuery('.row_block:last input.extraops').attr('value', '');
		var custom = jQuery('.row_block:last input.custom').attr('name');
		var reg = jQuery('.row_block:last input.reg').attr('name');
		var profile = jQuery('.row_block:last input.profile').attr('name');
		var req = jQuery('.row_block:last input.required').attr('name');
		var fieldtype = jQuery('.row_block:last select.fieldtype').attr('name');
		var extraops = jQuery('.row_block:last input.extraops').attr('name');
		var c_split = custom.split("[");
		var r_split = reg.split("[");
		var p_split = profile.split("[");
		var q_split = req.split("[");
		var f_split = fieldtype.split("[");
		var e_split = extraops.split("[");
		var split2 = c_split[1].split("]");
		var index = parseInt(split2[0]) + 1;
		var c_name = c_split[0] + '[' + index + ']';
		var r_name = r_split[0] + '[' + index + ']';
		var p_name = p_split[0] + '[' + index + ']';
		var q_name = q_split[0] + '[' + index + ']';
		var f_name = f_split[0] + '[' + index + ']';
		var e_name = e_split[0] + '[' + index + ']';
		jQuery('.row_block:last input.custom').attr('name', c_name);
		jQuery('.row_block:last input.reg').attr('name', r_name);
		jQuery('.row_block:last input.profile').attr('name', p_name);
		jQuery('.row_block:last input.required').attr('name', q_name);
		jQuery('.row_block:last select.fieldtype').attr('name', f_name);
		jQuery('.row_block:last input.extraops').attr('name', e_name);
		set_add_del();
		return false;
	}

	jQuery(document).ready(function() {
	<?php if ( !$options['enable_invitation_code']) { ?>
		jQuery('#invitation_code_settings').hide(); <?php } ?>
	<?php if ( !$options['show_password_meter']) { ?>
		jQuery('#meter_settings').hide(); <?php } ?>
	<?php if ( !$options['show_disclaimer']) { ?>
		jQuery('#disclaim_settings').hide(); <?php } ?>
	<?php if ( !$options['show_license_agreement']) { ?>
		jQuery('#license_agreement_settings').hide(); <?php } ?>
	<?php if ( !$options['show_privacy_policy']) { ?>
		jQuery('#privacy_policy_settings').hide(); <?php } ?>
	<?php if ( !$options['email_verify']) { ?>
		jQuery('#grace').hide(); <?php } ?>
	<?php if ( !$options['custom_msg']) { ?>
		jQuery('#enabled_msg').hide(); <?php } ?>
	<?php if ( !$options['custom_adminmsg']) { ?>
		jQuery('#enabled_adminmsg').hide(); <?php } ?>
		jQuery('#email_verify').change(function() {
			if ( jQuery('#email_verify').attr('checked') )
				jQuery('#grace').show();
			else
				jQuery('#grace').hide();
			return true;
		});
		jQuery('#enable_invitation_code').change(function() {
			if (jQuery('#enable_invitation_code').attr('checked') )
				jQuery('#invitation_code_settings').show();
			else
				jQuery('#invitation_code_settings').hide();
			return true;
		});
		jQuery('#show_password_meter').change(function() {
			if (jQuery('#show_password_meter').attr('checked') )
				jQuery('#meter_settings').show();
			else
				jQuery('#meter_settings').hide();
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
		jQuery('#custom_msg').change(function() {
			if ( jQuery('#custom_msg').attr('checked') )
				jQuery('#enabled_msg').show();
			else
				jQuery('#enabled_msg').hide();
			return true;
		});
		jQuery('#custom_adminmsg').change(function() {
			if ( jQuery('#custom_adminmsg').attr('checked') )
				jQuery('#enabled_adminmsg').show();
			else
				jQuery('#enabled_adminmsg').hide();
			return true;
		});
		//set_add_del_code();
		//set_add_del();
	});
</script>
<?php
		}

		function OptionsPage() {
			?>
			<div class="wrap">
			<h2><?php _e('Register Plus Settings', 'regplus') ?></h2>
			<?php if ( $_POST['notice'] ) echo '<div id="message" class="updated fade"><p><strong>'.$_POST['notice'].'.</strong></p></div>'; ?>
			<form method="post" action="">
				<?php wp_nonce_field('register-plus-redux-update-settings'); ?>
				<input type="hidden" name="action" value="update_settings" />
				<?php $options = get_option('register_plus_redux_options'); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Password', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="user_set_password" <?php if ( $options['user_set_password']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Allow New Registrations to set their own Password', 'regplus'); ?></label><br />
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
							<input type="file" name="regplus_logo" id="logo" value="1" />&nbsp;<small><?php _e("Recommended Logo width is 292px, but any height should work.", "regplus"); ?></small><br /><img src="<?php echo $options['logo']; ?>" alt="" />
							<?php if ($options['logo']) { ?>
							<br />
							<label><input type="checkbox" name="remove_logo" value="1" /><?php _e('Delete Logo', 'regplus'); ?></label>
							<?php } else { ?>
							<p><small><strong><?php _e('Having troubles uploading?','regplus'); ?></strong>&nbsp;<?php _e('Uncheck "Organize my uploads into month- and year-based folders" in ','regplus'); ?><a href="<?php echo get_option('siteurl'); ?>/wp-admin/options-misc.php"><?php _e('Miscellaneous Settings', 'regplus'); ?></a>&nbsp;<?php _e('(You can recheck this option after your logo has uploaded.)','regplus'); ?></small></p>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Email Verification', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="regplus_email_verify" id="email_verify" value="1" <?php if ( $options['email_verify']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Prevent fake email address registrations.', 'regplus'); ?></label><br />
							<?php _e('Requires new registrations to click a link in the notification email to enable their account.', 'regplus'); ?>
							<div id="grace">
								<label><strong><?php _e('Grace Period (days):', 'regplus'); ?></strong>&nbsp;<input type="text" name="regplus_email_delete_grace" id="email_delete_grace" style="width:50px;" value="<?php echo $options['email_delete_grace']; ?>" /></label><br />
								<?php _e('Unverified Users will be automatically deleted after grace period expires', 'regplus'); ?>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Admin Verification', 'regplus'); ?></th>
						<td><label><input type="checkbox" name="regplus_admin_verify" id="admin_verify" value="1" <?php if ( $options['admin_verify']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Moderate all user registrations to require admin approval. NOTE: Email Verification must be DISABLED to use this feature.', 'regplus'); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Invitation Code', 'regplus'); ?></th>
						<td>
							<label><input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?php if ( $options['enable_invitation_code']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Enable Invitation Code(s)', 'regplus'); ?></label>
							<div id="invitation_code_settings">
								<label><input type="checkbox" name="regplus_dash_widget" value="1" <?php if ( $options['enable_invitation_tracking_widget']) echo 'checked="checked"';  ?>  />&nbsp;<?php _e('Enable Invitation Tracking Dashboard Widget', 'regplus'); ?></label><br />
								<label><input type="checkbox" name="require_invitation_code" value="1" <?php if ( $options['require_invitation_code']) echo 'checked="checked"'; ?> />&nbsp;<?php _e('Require Invitation Code to Register', 'regplus'); ?></label>
								<?php
									$invitation_codes = $options['invitation_code_bank'];
									if ( !is_array($options['invitation_code_bank']) ) $options['invitation_code_bank'] = array();
									foreach ($options['invitation_code_bank'] as $invitation_code )
										echo '<div class="invitation_code"><input type="text" name="invitation_code_bank[]" value="'.$invitation_code.'" />&nbsp;<a href="#" onClick="return selremcode(this);" class="remove_code"><img src="'.plugins_url('removeBtn.gif', __FILE__).'" alt="'.__("Remove Code", "regplus").'" title="'.__("Remove Code", "regplus") . '" /></a></div>';
								?>
								<div class="code_block"><input type="text" name="invitation_code_bank[0]"  value="" />&nbsp;<a href="#" onClick="return seladdcode(this);" class="add_code"><img src="<?php echo plugins_url('addBtn.gif', __FILE__); ?>" alt="<?php _e("Add Code", "regplus") ?>" title="<?php _e("Add Code", "regplus") ?>" /></a></div>
								<small><?php _e('One of these codes will be required for users to register.', 'regplus'); ?></small>
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
											<label><?php _e('Disclaimer Content','regplus'); ?><br />
											<textarea name="message_disclaimer" style="width:100%; height:300px; display:block;"><?php echo stripslashes($options['message_disclaimer']); ?></textarea></label>
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
											<label><?php _e('License Agreement Content','regplus'); ?><br />
											<textarea name="message_license" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($options['message_license']); ?></textarea></label>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_license_agree"><?php _e('Agreement Text','regplus'); ?></label>
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
											<label><?php _e('Privacy Policy Content','regplus'); ?><br />
											<textarea name="message_privacy_policy" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($options['message_privacy_policy']); ?></textarea></label>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; width: 20%;" >
											<label for"message_privacy_policy_agree"><?php _e('Agreement Text','regplus'); ?></label>
											<input type="text" name="message_privacy_policy_agree" value="<?php echo $options['message_privacy_policy_agree']; ?>" style="width: 30%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
				<h3><?php _e('User Defined Fields', 'regplus'); ?></h3>
				<p><?php _e('Enter the custom fields you would like to appear on the Registration Page.', 'regplus'); ?></p>
				<p><small><?php _e('Enter Extra Options for Select, Checkboxes and Radio Fields as comma seperated values. For example, if you chose a select box for a custom field of "Gender", your extra options would be "Male,Female".','regplus'); ?></small></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Custom Fields', 'regplus'); ?></th>
						<td style="padding: 0px;">
							<table>
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
										echo '<tr valign="center" class="row_block">';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="label['.$k.']" class="custom" value="'.$v['label'].'" /></td>';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;">';
										echo '		<select class="fieldtype" name="fieldtype['.$k.']">';
										echo '			<option value="text"'; if ( $v['fieldtype'] == 'text' ) echo ' selected="selected"'; echo '>Text Field</option>';
										echo '			<option value="select"'; if ( $v['fieldtype'] == 'select' ) echo ' selected="selected"'; echo '>Select Field</option>';
										echo '			<option value="radio"'; if ( $v['fieldtype'] == 'radio' ) echo ' selected="selected"'; echo '>Radio Field</option>';
										echo '			<option value="textarea"'; if ( $v['fieldtype'] == 'textarea' ) echo ' selected="selected"'; echo '>Text Area</option>';
										echo '			<option value="date"'; if ( $v['fieldtype'] == 'date' ) echo ' selected="selected"'; echo '>Date Field</option>';
										echo '			<option value="hidden"'; if ( $v['fieldtype'] == 'hidden' ) echo ' selected="selected"'; echo '>Hidden Field</option>';
										echo '		</select>';
										echo '	</td>';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="extraoptions['.$k.']" class="extraops" value="'.$v['extraoptions'].'" /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="profile['.$k.']" class="profile" value="1"'; if ( $v['profile'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="reg['.$k.']" class="profile" value="1"'; if ( $v['reg'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required['.$k.']" class="required" value="1"'; if ( $v['required'] ) echo ' checked="checked"'; echo ' /></td>';
										echo '	<td style="padding-top: 0px; padding-bottom: 0px;"><a href="#" onClick="return selrem(this);" class="remove_row"><img src="'.plugins_url('removeBtn.gif', __FILE__).'" alt="'.__("Remove Field", "regplus").'" title="'.__("Remove Field", "regplus").'" /></a></td>';
										echo '</tr>';
									}
									?>
									<tr valign="center" class="row_block">
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="label[0]" class="custom" value="" /></td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<select class="fieldtype" name="name="fieldtype[0]">
												<option value="text">Text Field</option>
												<option value="select">Select Field</option>
												<option value="radio">Radio Field</option>
												<option value="textarea">Text Area</option>
												<option value="date">Date Field</option>
												<option value="hidden">Hidden Field</option>
											</select>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="extraoptions[0]" class="extraops" value="" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="profile[0]" class="profile" value="1" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="reg[0]" class="profile" value="1" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required[0]" class="required" value="1" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><a href="#" onClick="return seladd(this);" class="add_row"><img src="<?php echo plugins_url('addBtn.gif', __FILE__); ?>" alt="<?php _e("Add Field","regplus") ?>" title="<?php _e("Add Field","regplus") ?>" /></a></td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Date Field Settings', 'regplus'); ?></th>
						<td>
							<label for="datepicker_firstdayofweek"><?php _e('First Day of the Week','regplus'); ?>:</label>
							<select type="select" name="datepicker_firstdayofweek">
								<option value="7" <?php if ( $options['datepicker_firstdayofweek'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Monday','regplus'); ?></option>
								<option value="1" <?php if ( $options['datepicker_firstdayofweek'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Tuesday','regplus'); ?></option>
								<option value="2" <?php if ( $options['datepicker_firstdayofweek'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Wednesday','regplus'); ?></option>
								<option value="3" <?php if ( $options['datepicker_firstdayofweek'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Thursday','regplus'); ?></option>
								<option value="4" <?php if ( $options['datepicker_firstdayofweek'] == '4' ) echo 'selected="selected"'; ?>><?php _e('Friday','regplus'); ?></option>
								<option value="5" <?php if ( $options['datepicker_firstdayofweek'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Saturday','regplus'); ?></option>
								<option value="6" <?php if ( $options['datepicker_firstdayofweek'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Sunday','regplus'); ?></option>
							</select><br />
							<label for="datepicker_dateformat"><?php _e('Date Format', 'regplus'); ?>:</label><input type="text" name="datepicker_dateformat" value="<?php echo $options['datepicker_dateformat']; ?>" style="width:100px;" /><br />
							<label for="startdate"><?php _e('First Selectable Date', 'regplus'); ?>:</label><input type="text" name="regplus_startdate" id="startdate" value="<?php echo $options['startdate']; ?>"  style="width:100px;" /><br />
							<label for="calyear"><?php _e('Default Year', 'regplus'); ?>:</label><input type="text" name="regplus_calyear" id="calyear" value="<?php echo $options['calyear']; ?>" style="width:40px;" /><br />
							<label for="calmonth"><?php _e('Default Month', 'regplus'); ?>:</label>
							<select name="regplus_calmonth" id="calmonth">
								<option value="cur" <?php if ( $options['calmonth'] == 'cur' ) echo 'selected="selected"'; ?>><?php _e('Current Month','regplus'); ?></option>
								<option value="0" <?php if ( $options['calmonth'] == '0' ) echo 'selected="selected"'; ?>><?php _e('Jan','regplus'); ?></option>
								<option value="1" <?php if ( $options['calmonth'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Feb','regplus'); ?></option>
								<option value="2" <?php if ( $options['calmonth'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Mar','regplus'); ?></option>
								<option value="3" <?php if ( $options['calmonth'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Apr','regplus'); ?></option>
								<option value="4" <?php if ( $options['calmonth'] == '4' ) echo 'selected="selected"'; ?>><?php _e('May','regplus'); ?></option>
								<option value="5" <?php if ( $options['calmonth'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Jun','regplus'); ?></option>
								<option value="6" <?php if ( $options['calmonth'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Jul','regplus'); ?></option>
								<option value="7" <?php if ( $options['calmonth'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Aug','regplus'); ?></option>
								<option value="8" <?php if ( $options['calmonth'] == '8' ) echo 'selected="selected"'; ?>><?php _e('Sep','regplus'); ?></option>
								<option value="9" <?php if ( $options['calmonth'] == '9' ) echo 'selected="selected"'; ?>><?php _e('Oct','regplus'); ?></option>
								<option value="10" <?php if ( $options['calmonth'] == '10' ) echo 'selected="selected"'; ?>><?php _e('Nov','regplus'); ?></option>
								<option value="11" <?php if ( $options['calmonth'] == '11' ) echo 'selected="selected"'; ?>><?php _e('Dec','regplus'); ?></option>
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
						<td><label><input type="checkbox" name="regplus_custom_msg" id="custom_msg" value="1" <?php if ( $options['custom_msg']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
					</tr>
				</table>
				<div id="enabled_msg">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="from"><?php _e('From Email', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_from" id="from" style="width:250px;" value="<?php echo $options['from']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="fromname"><?php _e('From Name', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_fromname" id="fromname" style="width:250px;" value="<?php echo $options['fromname']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="subject"><?php _e('Subject', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_subject" id="subject" style="width:350px;" value="<?php echo $options['subject']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="msg"><?php _e('User Message', 'regplus'); ?></label></th>
							<td>
							<?php
							if ( $options['show_firstname_field'] ) $custom_keys .= '&nbsp;%firstname%';
							if ( $options['show_lastname_field'] ) $custom_keys .= '&nbsp;%lastname%';
							if ( $options['show_website_field'] ) $custom_keys .= '&nbsp;%user_url%';
							if ( $options['show_aim_field'] ) $custom_keys .= '&nbsp;%aim%';
							if ( $options['show_yahoo_field'] ) $custom_keys .= '&nbsp;%yahoo%';
							if ( $options['show_jabber_field'] ) $custom_keys .= '&nbsp;%jabber%';
							if ( $options['show_about_field'] ) $custom_keys .= '&nbsp;%about%';
							if ( $options['enable_invitation_code'] ) $custom_keys .= '&nbsp;%invitecode%';
							if ( is_array($custom_fields) ) {
								foreach ( $custom_fields as $k => $v ) {
									$meta = $this->LabelId($v['label']);
									$value = get_user_meta($user_id, $meta, false);
									$custom_keys .= '&nbsp;%'.$meta.'%';
								}
							}
							?>
								<p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong>&nbsp;%user_login% &nbsp;%user_pass%&nbsp;%user_email%&nbsp;%blogname%&nbsp;%siteurl% <?php echo $custom_keys; ?>&nbsp; %user_ip%&nbsp;%user_ref%&nbsp;%user_host%&nbsp;%user_agent% </p>
								<textarea name="regplus_msg" id="msg" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options['msg']; ?></textarea><br /><label><input type="checkbox" name="regplus_html" id="html" value="1" <?php if ( $options['html']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_user_nl2br" id="html" value="1" <?php if ( $options['user_nl2br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)' , 'regplus'); ?></label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="login_redirect"><?php _e('Login Redirect URL', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_login_redirect" id="login_redirect" style="width:250px;" value="<?php echo $options['login_redirect']; ?>" /><small><?php _e('This will redirect the users login after registration.', 'regplus'); ?></small></td>
						</tr>
					</table>
				</div>
				<h3><?php _e('Customize Admin Notification Email', 'regplus'); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label for="disable_admin"><?php _e('Admin Email Notification', 'regplus'); ?></label></th>
						<td><label><input type="checkbox" name="regplus_disable_admin" id="disable_admin" value="1" <?php if ( $options['disable_admin']) echo 'checked="checked"'; ?> /><?php _e('Disable', 'regplus'); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Custom Admin Email Notification', 'regplus'); ?></label></th>
						<td><label><input type="checkbox" name="regplus_custom_adminmsg" id="custom_adminmsg" value="1" <?php if ( $options['custom_adminmsg']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
					</tr>
				</table>
				<div id="enabled_adminmsg">
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><label for="adminfrom"><?php _e('From Email', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_adminfrom" id="adminfrom" style="width:250px;" value="<?php echo $options['adminfrom']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="adminfromname"><?php _e('From Name', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_adminfromname" id="adminfromname" style="width:250px;" value="<?php echo $options['adminfromname']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="adminsubject"><?php _e('Subject', 'regplus'); ?></label></th>
							<td><input type="text" name="regplus_adminsubject" id="adminsubject" style="width:350px;" value="<?php echo $options['adminsubject']; ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="adminmsg"><?php _e('Admin Message', 'regplus'); ?></label></th>
							<td><p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong>&nbsp;%user_login% &nbsp;%user_email%&nbsp;%blogname%&nbsp;%siteurl%  <?php echo $custom_keys; ?>&nbsp; %user_ip%&nbsp;%user_ref%&nbsp;%user_host%&nbsp;%user_agent%</p><textarea name="regplus_adminmsg" id="adminmsg" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $options['adminmsg']; ?></textarea><br /><label><input type="checkbox" name="regplus_adminhtml" id="adminhtml" value="1" <?php if ( $options['adminhtml']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML' , 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_admin_nl2br" id="html" value="1" <?php if ( $options['admin_nl2br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)' , 'regplus'); ?></label></td>
						</tr>
					</table>
				</div>
				<br />
				<h3><?php _e('Custom CSS for Register & Login Pages', 'regplus'); ?></h3>
				<p><?php _e('CSS Rule Example:', 'regplus'); ?><code>#user_login{ font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="register_css"><?php _e('Custom Register CSS', 'regplus'); ?></label></th>
						<td><textarea name="regplus_register_css" id="register_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options['register_css']; ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="login_css"><?php _e('Custom Login CSS', 'regplus'); ?></label></th>
						<td><textarea name="regplus_login_css" id="login_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $options['login_css']; ?></textarea></td>
					</tr>
				</table>
				<p class="submit"><input class="button-primary" type="submit" value="<?php _e('Save Changes','regplus'); ?>" name="Submit" /></p>
			</form>
			</div>
			<?php
		}

		function UpdateSettings() {
			check_admin_referer('register-plus-redux-update-settings');
			$update = get_option('register_plus_redux_options');
			$update["user_set_password"] = $_POST['user_set_password'];
			$update["show_password_meter"] = $_POST['show_password_meter'];
			$update["message_short_password"] = $_POST['message_short_password'];
			$update["message_bad_password"] = $_POST['message_bad_password'];
			$update["message_good_password"] = $_POST['message_good_password'];
			$update["message_strong_password"] = $_POST['message_strong_password'];

			$update["code"] = $_POST['regplus_code'];
			if ( $_POST['enable_invitation_code'] ) {
				$update["enable_invitation_code"] = $_POST['enable_invitation_code'];
				$update["enable_invitation_tracking_widget"] = $_POST['enable_invitation_tracking_widget'];
				$update["require_invitation_code"] = $_POST['require_invitation_code'];
				$update["invitation_code_bank"] = $_POST['invitation_code_bank'];
				foreach ( $update["invitation_code_bank"] as $k => $v )
					$update["invitation_code_bank"][$k] = strtolower($v);
			}
			$update["allow_duplicate_emails"] = $_POST['allow_duplicate_emails'];

			$update["show_firstname_field"] = $_POST['show_firstname_field'];
			$update["show_lastname_field"] = $_POST['show_lastname_field'];
			$update["show_website_field"] = $_POST['show_website_field'];
			$update["show_aim_field"] = $_POST['show_aim_field'];
			$update["show_yahoo_field"] = $_POST['show_yahoo_field'];
			$update["show_jabber_field"] = $_POST['show_jabber_field'];
			$update["show_about_field"] = $_POST['show_about_field'];
			$update["required_fields"] = $_POST['required_fields'];
			$update["required_fields_style"] = $_POST['required_fields_style'];
			$update["show_disclaimer"] = $_POST['show_disclaimer'];
			$update["message_disclaimer_title"] = $_POST['message_disclaimer_title'];
			$update["message_disclaimer"] = $_POST['message_disclaimer'];
			$update["message_disclaimer_agree"] = $_POST['message_disclaimer_agree'];
			$update["show_license_agreement"] = $_POST['show_license_agreement'];
			$update["message_license_title"] = $_POST['message_license_title'];
			$update["message_license"] = $_POST['message_license'];
			$update["message_license_agree"] = $_POST['message_license_agree'];
			$update["show_privacy_policy"] = $_POST['show_privacy_policy'];
			$update["message_privacy_policy_title"] = $_POST['message_privacy_policy_title'];
			$update["message_privacy_policy"] = $_POST['message_privacy_policy'];
			$update["message_privacy_policy_agree"] = $_POST['message_privacy_policy_agree'];

			$update["admin_verify"] = $_POST['regplus_admin_verify'];
			$update["email_verify"] = $_POST['regplus_email_verify'];
			$update["email_verify_date"] = $_POST['regplus_email_verify_date'];
			$update["email_delete_grace"] = $_POST['regplus_email_delete_grace'];
			$update['html'] = $_POST['regplus_html'];
			$update['from'] = $_POST['regplus_from'];
			$update['fromname'] = $_POST['regplus_fromname'];
			$update['subject'] = $_POST['regplus_subject'];
			$update['custom_msg'] = $_POST['regplus_custom_msg'];
			$update['user_nl2br'] = $_POST['regplus_user_nl2br'];
			$update['msg'] = $_POST['regplus_msg'];
			$update['disable_admin'] = $_POST['regplus_disable_admin'];
			$update['adminhtml'] = $_POST['regplus_adminhtml'];
			$update['adminfrom'] = $_POST['regplus_adminfrom'];
			$update['adminfromname'] = $_POST['regplus_adminfromname'];
			$update['adminsubject'] = $_POST['regplus_adminsubject'];
			$update['custom_adminmsg'] = $_POST['regplus_custom_adminmsg'];
			$update['admin_nl2br'] = $_POST['regplus_admin_nl2br'];
			$update['adminmsg'] = $_POST['regplus_adminmsg'];
			$update['login_redirect'] = $_POST['regplus_login_redirect'];

			$update['register_css'] = $_POST['regplus_register_css'];
			$update['login_css'] = $_POST['regplus_login_css'];

			$update['datepicker_firstdayofweek'] = $_POST['datepicker_firstdayofweek'];
			$update['datepicker_dateformat'] = $_POST['datepicker_dateformat'];
			$update['startdate'] = $_POST['regplus_startdate'];
			$update['calyear'] = $_POST['regplus_calyear'];
			$update['calmonth'] = $_POST['regplus_calmonth'];

			if ( $_FILES['regplus_logo']['name'] ) $update['logo'] = $this->UploadLogo();
			elseif ( $_POST['remove_logo'] ) $update['logo'] = '';

			if ( $_POST['label'] ) {
				foreach ( $_POST['label'] as $k => $field ) {
					if ( $field ) $custom[$k] = array('label' => $field, 'profile' => $_POST['profile'][$k], 'reg' => $_POST['reg'][$k], 'required' => $_POST['required'][$k], 'fieldtype' => $_POST['fieldtype'][$k], 'extraoptions' => $_POST['extraoptions'][$k]);
				}
			}

			update_option('register_plus_redux_options', $update);
			update_option('register_plus_redux_custom_fields', $custom);
			$_POST['notice'] = __('Settings Saved', 'regplus');
		}

		function UnverifiedUsersPage() {
			global $wpdb;
			if ( $_POST['notice'] )
				echo '<div id="message" class="updated fade"><p><strong>' . $_POST['notice'] . '.</strong></p></div>';
			$unverified = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE user_login LIKE '%unverified__%'");
			$options = get_option('register_plus_redux_options');
			?>
<div class="wrap">
	<h2><?php _e('Unverified Users', 'regplus') ?></h2>
	<form id="verify-filter" method="post" action="">
	<?php wp_nonce_field('regplus-unverified'); ?>
	<div class="tablenav">
		<div class="alignleft">
			<input value="<?php _e('Verify Checked Users','regplus'); ?>" name="verifyit" class="button-secondary" type="submit">&nbsp;
			<?php if ( $options['email_verify'] ) { ?>
			<input value="<?php _e('Resend Verification E-mail','regplus'); ?>" name="emailverifyit" class="button-secondary" type="submit">
			<?php } ?>
			&nbsp;<input value="<?php _e('Delete','regplus'); ?>" name="vdeleteit" class="button-secondary delete" type="submit">
		</div>
		<br class="clear">
	</div>
	<br class="clear">
	<table class="widefat">
		<thead>
			<tr class="thead">
				<th scope="col" class="check-column"><input onclick="checkAll(document.getElementById('verify-filter'));" type="checkbox"></th>
				<th><?php _e('Unverified ID','regplus'); ?></th>
				<th><?php _e('User Name','regplus'); ?></th>
				<th><?php _e('E-mail','regplus'); ?></th>
				<th><?php _e('Role','regplus'); ?></th>
			</tr>
		</thead>
		<tbody id="users" class="list:user user-list">
			<?php foreach ( $unverified as $un ) {
				if ( $alt ) $alt = ''; else $alt = "alternate";
				$user_object = new WP_User($un->ID);
				$roles = $user_object->roles;
				$role = array_shift($roles);
				if ( $options['email_verify'] ) $user_login = get_user_meta($un->ID, 'email_verify_user', false);
				elseif ( $options['admin_verify'] ) $user_login = get_user_meta($un->ID, 'admin_verify_user', false);
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

		function VersionWarning() {
			global $wp_version;
			echo "<div id='regplus-warning' class='updated fade-ff0000'><p><strong>".__('Register Plus is only compatible with WordPress 3.0 and up. You are currently using WordPress ', 'regplus').$wp_version."</strong></p></div>";
		}

		function override_warning() {
			if ( current_user_can(10) && $_GET['page'] == 'register-plus-redux' )
			echo "<div id='regplus-warning' class='updated fade-ff0000'><p><strong>".__('You have another plugin installed that is conflicting with Register Plus. This other plugin is overriding the user notification emails. Please see <a href="http://skullbit.com/news/register-plus-conflicts/">Register Plus Conflicts</a> for more information.', 'regplus') . "</strong></p></div>";
		}

		function UploadLogo() {
			//v3.5.1 code
			//$upload_dir = ABSPATH . get_option('upload_path');
			//$upload_file = trailingslashit($upload_dir) . basename($_FILES['regplus_logo']['name']);
			//if ( !is_dir($upload_dir) )
			//	wp_upload_dir();
			//if ( move_uploaded_file($_FILES['regplus_logo']['tmp_name'], $upload_file) ) {
			//	chmod($upload_file, 0777);
			//	$logo = $_FILES['regplus_logo']['name'];
			//	return trailingslashit(get_option('siteurl')) . 'wp-content/uploads/' . $logo;
			//} else { return false; }
			//code recommended by nschmede
			$uploads = wp_upload_dir();
			$upload_dir = $uploads['basedir'];
			$upload_url = $uploads['baseurl'];
			$upload_file = trailingslashit($upload_dir) . basename($_FILES['regplus_logo']['name']);
			//echo $upload_file;
			if ( !is_dir($upload_dir) )
				wp_upload_dir();
			if ( move_uploaded_file($_FILES['regplus_logo']['tmp_name'], $upload_file) ) {
				chmod($upload_file, 0777);
				$logo = $_FILES['regplus_logo']['name'];
				return trailingslashit($upload_url) . $logo;
			} else { return false; }
		}

		function AdminValidate() {
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			check_admin_referer('regplus-unverified');
			$valid = $_POST['vusers'];
			foreach ( $valid as $user_id ) {
				if ( $user_id ) {
					if ( $options['email_verify'] ) {
						$stored_user_login = get_user_meta($user_id, 'email_verify_user', false);
						//v3.5.1
						//$wpdb->query("UPDATE $wpdb->users SET user_login='$stored_user_login' WHERE ID='$user_id'");
						//trying to depreciate use of $wpdb->query
						wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
						delete_user_meta($user_id, 'email_verify_user');
						delete_user_meta($user_id, 'email_verify');
						delete_user_meta($user_id, 'email_verify_date');
					} elseif ( $options['admin_verify'] ) {
						$stored_user_login = get_user_meta($user_id, 'admin_verify_user', false);
						//v3.5.1
						//$wpdb->query("UPDATE $wpdb->users SET user_login='$stored_user_login' WHERE ID='$user_id'");
						//trying to depreciate use of $wpdb->query
						wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
						delete_user_meta($user_id, 'admin_verify_user');
					}
					$this->VerifyNotification($user_id);
				}
			}
			$_POST['notice'] = __("Users Verified","regplus");
		}

		function AdminDeleteUnvalidated() {
			//why is this declared?
			//global $wpdb;
			$options = get_option('register_plus_redux_options');
			check_admin_referer('regplus-unverified');
			$delete = $_POST['vusers'];
			include_once(admin_url('/includes/user.php'));
			foreach ( $delete as $user_id ) {
				if ( $user_id ) wp_delete_user($user_id);
			}
			$_POST['notice'] = __("Users Deleted","regplus");
		}

		function AdminEmailValidate() {
			global $wpdb;
			check_admin_referer('regplus-unverified');
			$valid = $_POST['vusers'];
			if ( is_array($valid) ):
				foreach ( $valid as $user_id ) {
					$code = get_user_meta($user_id, 'email_verify', false);
					$user_login = get_user_meta($user_id, 'email_verify_user', false);
					//v3.5.1 code
					//$user_email = $wpdb->get_var("SELECT user_email FROM $wpdb->users WHERE ID='$user_id'");
					//depreciating $wpdb->get_var
					$user_info = get_userdata($user_id);
					//$user_email = $user_info->user_email;
					$email_code = '?regplus_verification=' . $code;
					$prelink = __('Verification URL: ', 'regplus');
					$message = sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n";
					//$message .= sprintf(__('Password: %s', 'regplus'), $plaintext_pass) . "\r\n";
					$message .= $prelink . get_option('siteurl') . "/wp-login.php" . $email_code . "\r\n";
					$message .= $notice;
					add_filter('wp_mail_from', array($this, 'userfrom'));
					add_filter('wp_mail_from_name', array($this, 'userfromname'));
					wp_mail($user_info->user_email, sprintf(__('[%s] Verify Account Link', 'regplus'), get_option('blogname')), $message);
				}
			$_POST['notice'] = __("Verification Emails have been re-sent", "regplus");
			else:
			$_POST['notice'] = __("<strong>Error:</strong> Please select a user to send emails to.", "regplus");
			endif;
		}

		function VerifyNotification( $user_id ) {
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			$user = $wpdb->get_row("SELECT user_login, user_email FROM $wpdb->users WHERE ID='$user_id'");
			$message = __('Your account has now been activated by an administrator.') . "\r\n";
			$message .= sprintf(__('Username: %s', 'regplus'), $user->user_login) . "\r\n";
			$message .= $prelink . get_option('siteurl') . "/wp-login.php" . $email_code . "\r\n"; 
			add_filter('wp_mail_from', array($this, 'userfrom'));
			add_filter('wp_mail_from_name', array($this, 'userfromname'));
			wp_mail($user->user_email, sprintf(__('[%s] User Account Activated', 'regplus'), get_option('blogname')), $message);
		}

		#Check Required Fields
		function RegErrors( $errors ) {
			$options = get_option('register_plus_redux_options');
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			if ( $options['allow_duplicate_emails'] ) {
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
			if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					if ( $v['required'] && $v['reg'] ) {
						$id = $this->LabelId($v['label']);
						if ( empty($_POST[$id]) || $_POST[$id] == '' ) {
							$errors->add('empty_' . $id, __('<strong>ERROR</strong>: Please enter your ' . $v['label'] . '.', 'regplus'));
						}
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
					$_POST['user_pw'] = $_POST['pass1'];
				}
			}
			if ( $options['enable_invitation_code'] && $options['require_invitation_code'] ) {
				if ( empty($_POST['invitation_code']) || $_POST['invitation_code'] == '' ) {
					$errors->add('empty_invitation_code', __('<strong>ERROR</strong>: Please enter the Invitation Code.', 'regplus'));
				} elseif ( !in_array(strtolower($_POST['invitation_code']), $options['invitation_code_bank']) ) {
					$errors->add('invitation_code_mismatch', __('<strong>ERROR</strong>: Your Invitation Code is incorrect.', 'regplus'));
				}
			}
			if ( $options['show_disclaimer'] ) {
				if ( !$_POST['show_disclaimer'] ) {
					$errors->add('show_disclaimer', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_disclaimer_title']) . '.');
				}
			}
			if ( $options['show_license_agreement'] ) {
				if ( !$_POST['show_license_agreement'] ) {
					$errors->add('show_license_agreement', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_license_title']) . '.');
				}
			}
			if ( $options['show_privacy_policy'] ) {
				if ( !$_POST['show_privacy_policy'] ) {
					$errors->add('show_privacy_policy', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($options['message_privacy_policy_title']) . '.');
				}
			}
			return $errors;
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

		#Add Fields to Register Form
		function RegForm() {
			$options = get_option('register_plus_redux_options');
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			if ( $options['show_firstname_field'] ) {
				if ( isset($_GET['firstname']) ) $_POST['firstname'] = $_GET['firstname']; ?>
<p><label><?php _e('First Name:', 'regplus'); ?><br /><input autocomplete="off" name="firstname" id="firstname" size="25" value="<?php echo $_POST['firstname']; ?>" type="text" tabindex="30" /></label></p>
			<?php }
			if ( $options['show_lastname_field'] ) {
				if ( isset($_GET['lastname']) ) $_POST['lastname'] = $_GET['lastname']; ?>
<p><label><?php _e('Last Name:', 'regplus'); ?><br /><input autocomplete="off" name="lastname" id="lastname" size="25" value="<?php echo $_POST['lastname']; ?>" type="text" tabindex="31" /></label></p>
			<?php }
			if ( $options['show_website_field'] ) {
				if ( isset($_GET['user_url']) ) $_POST['user_url'] = $_GET['user_url']; ?>
<p><label><?php _e('Website:', 'regplus'); ?><br /><input autocomplete="off" name="user_url" id="user_url" size="25" value="<?php echo $_POST['user_url']; ?>" type="text" tabindex="32" /></label></p>
			<?php }
			if ( $options['show_aim_field'] ) {
				if ( isset($_GET['aim']) ) $_POST['aim'] = $_GET['aim']; ?>
<p><label><?php _e('AIM:', 'regplus'); ?><br /><input autocomplete="off" name="aim" id="aim" size="25" value="<?php echo $_POST['aim']; ?>" type="text" tabindex="32" /></label></p>
			<?php }
			if ( $options['show_yahoo_field'] ) {
				if ( isset($_GET['yahoo']) ) $_POST['yahoo'] = $_GET['yahoo']; ?>
<p><label><?php _e('Yahoo IM:', 'regplus'); ?><br /><input autocomplete="off" name="yahoo" id="yahoo" size="25" value="<?php echo $_POST['yahoo']; ?>" type="text" tabindex="33" /></label></p>
			<?php }
			if ( $options['show_jabber_field'] ) {
				if ( isset($_GET['jabber']) ) $_POST['jabber'] = $_GET['jabber']; ?>
<p><label><?php _e('Jabber / Google Talk:', 'regplus'); ?><br /><input autocomplete="off" name="jabber" id="jabber" size="25" value="<?php echo $_POST['jabber']; ?>" type="text" tabindex="34" /></label></p>
			<?php }
			if ( $options['show_about_field'] ) {
				if ( isset($_GET['about']) ) $_POST['about'] = $_GET['about']; ?>
<p><label><?php _e('About Yourself:', 'regplus'); ?><br /><textarea autocomplete="off" name="about" id="about" cols="25" rows="5" tabindex="35"><?php echo stripslashes($_POST['about']); ?></textarea></label><small><?php _e('Share a little biographical information to fill out your profile. This may be shown publicly.', 'regplus'); ?></small></p>
			<?php }
			foreach ( $custom_fields as $k => $v ) {
				if ( $v['reg'] ) {
					$id = $this->LabelId($v['label']);
					if ( isset($_GET[$id]) ) $_POST[$id] = $_GET[$id];
					if ( $v['fieldtype'] == 'text' ) { ?>
<p><label><?php echo $v['label']; ?>: <br /><input autocomplete="off" class="custom_field" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>" size="25" value="<?php echo $_POST[$id]; ?>" type="text" /></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'date' ) { ?>
<p><label><?php echo $v['label']; ?>: <br /><input autocomplete="off" class="custom_field date-pick" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>" size="25" value="<?php echo $_POST[$id]; ?>" type="text" /></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'select' ) {
						$extraoptions = explode(',',$v['extraoptions']);
						$extraoptionshtml = '';
						foreach ( $extraoptions as $extraoption ) {
							$extraoptionshtml .= '<option value="'.$extraoption.'" ';
							if ( $_POST[$id] == $extraoption ) $extraoptionshtml .= 'selected="selected"';
							$extraoptionshtml .= '>' . $extraoption . '</option>';
						}
						?>
<p><label><?php echo $v['label']; ?>: <br /><select class="custom_select" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>"><?php echo $extraoptionshtml; ?></select></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'checkbox' ) {
						$extraoptions = explode(',',$v['extraoptions']);
						$check = '';
						foreach ( $extraoptions as $extraoption ) {
							$check .= '<label><input type="checkbox" class="custom_checkbox" tabindex="36" name="'.$id.'[]" id="'.$id.'" ';
							//if ( in_array($extraoption, $_POST[$id])) $check .= 'checked="checked" ';
							$check .= 'value="'.$extraoption.'" /> '.$extraoption.'</label> ';
						}
						?>
<p><label><?php echo $v['label']; ?>:</label><br />
						<?php echo $check . '<br /></p>';
					} elseif ( $v['fieldtype'] == 'radio' ) {
						$extraoptions = explode(',',$v['extraoptions']);
						$radio = '';
						foreach ( $extraoptions as $extraoption ) {
							$radio .= '<label><input type="radio" class="custom_radio" tabindex="36" name="'.$id.'" id="'.$id.'" ';
							//if ( in_array($extraoption, $_POST[$id])) $radio .= 'checked="checked" ';
							$radio .= 'value="'.$extraoption.'" /> '.$extraoption.'</label> ';
						}
						?>
<p><label><?php echo $v['label']; ?>:</label><br />
						<?php echo $radio . '<br /></p>';
					} elseif ( $v['fieldtype'] == 'textarea' ) {
						?>
<p><label><?php echo $v['label']; ?>: <br /><textarea tabindex="36" name="<?php echo $id; ?>" cols="25" rows="5" id="<?php echo $id; ?>" class="custom_textarea"><?php echo $_POST[$id]; ?></textarea></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'hidden' ) {
						?>
<input class="custom_field" tabindex="36" name="<?php echo $id; ?>" value="<?php echo $_POST[$id]; ?>" type="hidden" />
					<?php }
				}
			}
			if ( $options['user_set_password'] ) {
				?>
<p>
	<label><?php _e('Password:', 'regplus'); ?><br />
	<input autocomplete="off" name="pass1" id="pass1" size="25" value="<?php echo $_POST['pass1']; ?>" type="password" tabindex="40" /></label><br />
	<label><?php _e('Confirm Password:', 'regplus'); ?><br />
	<input autocomplete="off" name="pass2" id="pass2" size="25" value="<?php echo $_POST['pass2']; ?>" type="password" tabindex="41" /></label>
	<?php if ( $options['show_password_meter']) { ?><br />
		<span id="pass-strength-result"><?php echo $options['message_short_password']; ?></span>
		<small><?php _e('Hint: Use upper and lower case characters, numbers and symbols like !"?$%^&amp;(in your password.', 'regplus'); ?></small>
	<?php } ?>
</p>
<?php
			}
			if ( $options['enable_invitation_code'] ) {
				if ( isset($_GET['invitation_code']) ) $_POST['invitation_code'] = $_GET['invitation_code'];
					?>
<p>
	<label><?php _e('Invitation Code:', 'regplus'); ?><br />
	<input name="invitation_code" id="invitation_code" size="25" value="<?php echo $_POST['invitation_code']; ?>" type="text" tabindex="45" /></label><br />
	<?php if ($options['require_invitation_code']) { ?>
		<small><?php _e('This website is currently closed to public registrations.  You will need an invitation code to register.', 'regplus'); ?></small>
	<?php } else { ?>
		<small><?php _e('Have an invitation code? Enter it here. (This is not required)', 'regplus'); ?></small>
	<?php } ?>
</p>
<?php
			}
			if ($options['show_disclaimer'] ) {
				?>
<p>
	<label><?php echo stripslashes($options['message_disclaimer_title']); ?><br />
	<span id="disclaimer"><?php echo stripslashes($options['message_disclaimer']); ?></span>
	<input name="disclaimer" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_disclaimer']) echo ' checked="checked"'; ?> /><?php echo $options['message_disclaimer_agree']; ?></label>
</p>
<?php
			}
			if ( $options['show_license_agreement'] ) {
				?>
<p>
	<label><?php echo stripslashes($options['message_license_title']); ?><br />
	<span id="license"><?php echo stripslashes($options['message_license']); ?></span>
	<input name="license" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_license_agreement']) echo ' checked="checked"'; ?> /><?php echo $options['message_license_agree']; ?></label>
</p>
<?php
			}
			if ( $options['show_privacy_policy'] ) {
				?>
<p>
	<label><?php echo stripslashes($options['message_privacy_policy_title']); ?><br />
	<span id="privacy"><?php echo stripslashes($options['message_privacy_policy']); ?></span>
	<input name="privacy" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_privacy_policy']) echo ' checked="checked"'; ?> /><?php echo $options['message_privacy_policy_agree']; ?></label>
</p>
<?php
			}
		}

		function LabelId( $label ) {
			$id = str_replace(' ', '_', $label);
			$id = strtolower($id);
			$id = sanitize_user($id, true);
			return $id;
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
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script(plugins_url('js/jquery.ui.datepicker.js', __FILE__));
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
			?>
<!-- required plugins -->
<script type="text/javascript" src="<?php echo plugins_url('datepicker/date.js', __FILE__); ?>"></script>
<!--[if IE]><script type="text/javascript" src="<?php echo plugins_url('datepicker/jquery.bgiframe.js', __FILE__); ?>"></script><![endif]-->

<!-- jquery.datePicker.js -->
<script type="text/javascript" src="<?php echo plugins_url('datepicker/jquery.datePicker.js', __FILE__); ?>"></script>
<link href="<?php echo plugins_url('datepicker/datePicker.css', __FILE__); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript">
jQuery.dpText = {
	TEXT_PREV_YEAR	:	'<?php _e('Previous year','regplus'); ?>',
	TEXT_PREV_MONTH	:	'<?php _e('Previous month','regplus'); ?>',
	TEXT_NEXT_YEAR	:	'<?php _e('Next year','regplus'); ?>',
	TEXT_NEXT_MONTH	:	'<?php _e('Next Month','regplus'); ?>',
	TEXT_CLOSE	:	'<?php _e('Close','regplus'); ?>',
	TEXT_CHOOSE_DATE:	'<?php _e('Choose Date','regplus'); ?>'
}
Date.dayNames = ['<?php _e('Monday','regplus'); ?>', '<?php _e('Tuesday','regplus'); ?>', '<?php _e('Wednesday','regplus'); ?>', '<?php _e('Thursday','regplus'); ?>', '<?php _e('Friday','regplus'); ?>', '<?php _e('Saturday','regplus'); ?>', '<?php _e('Sunday','regplus'); ?>'];
Date.abbrDayNames = ['<?php _e('Mon','regplus'); ?>', '<?php _e('Tue','regplus'); ?>', '<?php _e('Wed','regplus'); ?>', '<?php _e('Thu','regplus'); ?>', '<?php _e('Fri','regplus'); ?>', '<?php _e('Sat','regplus'); ?>', '<?php _e('Sun','regplus'); ?>'];
Date.monthNames = ['<?php _e('January','regplus'); ?>', '<?php _e('February','regplus'); ?>', '<?php _e('March','regplus'); ?>', '<?php _e('April','regplus'); ?>', '<?php _e('May','regplus'); ?>', '<?php _e('June','regplus'); ?>', '<?php _e('July','regplus'); ?>', '<?php _e('August','regplus'); ?>', '<?php _e('September','regplus'); ?>', '<?php _e('October','regplus'); ?>', '<?php _e('November','regplus'); ?>', '<?php _e('December','regplus'); ?>'];
Date.abbrMonthNames = ['<?php _e('Jan','regplus'); ?>', '<?php _e('Feb','regplus'); ?>', '<?php _e('Mar','regplus'); ?>', '<?php _e('Apr','regplus'); ?>', '<?php _e('May','regplus'); ?>', '<?php _e('Jun','regplus'); ?>', '<?php _e('Jul','regplus'); ?>', '<?php _e('Aug','regplus'); ?>', '<?php _e('Sep','regplus'); ?>', '<?php _e('Oct','regplus'); ?>', '<?php _e('Nov','regplus'); ?>', '<?php _e('Dec','regplus'); ?>'];
Date.firstDayOfWeek = <?php echo $options['datepicker_firstdayofweek']; ?>;
Date.format = '<?php echo $options['datepicker_dateformat']; ?>';
jQuery(function() {
	jQuery('.date-pick').datePicker({
		clickInput:true,
		startDate:'<?php echo $options['startdate']; ?>',
		year:'<?php echo $options['calyear']; ?>',
		month:'<?php if ( $options['calmonth'] != 'cur') echo $options['calmonth']; else echo date('n')-1; ?>'
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
			$custom_fields = get_option('register_plus_redux_custom_fields');
			$custom = array();
			if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					if ( $v['required'] && $v['reg'] ) {
						$custom[] = ', #' . $this->LabelId($v['label']);
					}
				}
			}
			//WTF does this line accomplish?
			if ( $options['required_fields'][0] ) $profile_req = ', #' . implode(', #', $options['required_fields']);
			if ( $custom[0] ) $profile_req .= implode('', $custom);
?>
#user_login, #user_email, #pass1, #pass2 <?php echo $profile_req; ?> {
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

		function HideLogin() {
			$options = get_option('register_plus_redux_options');
			if ( ($options['admin_verify'] || $options['email_verify']) && $_GET['checkemail'] == 'registered' ) {
				?>
<style type="text/css">
label, #user_login, #user_pass, .forgetmenot, #wp-submit, .message {
	display:none;
}
</style>
<?php
			}
		}

		function LogoHead() {
			$options = get_option('register_plus_redux_options');
			if ( $options['logo'] ) { 
				$logo = str_replace(trailingslashit(get_option('siteurl')), ABSPATH, $options['logo']);
				list($width, $height, $type, $attr) = getimagesize($logo);
				if ( $_GET['action'] != 'register' )
					wp_enqueue_script('jquery');
					?>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#login h1 a').attr('href', '<?php echo get_option('home'); ?>');
		jQuery('#login h1 a').attr('title', '<?php echo get_option('blogname') . ' - ' . get_option('blogdescription'); ?>');
	});
</script>
<style type="text/css">
#login h1 a {
	background-image: url(<?php echo $options['logo']; ?>);
	background-position:center top;
	width: <?php echo $width; ?>px;
	min-width:292px;
	height: <?php echo $height; ?>px;
}
<?php 
			if ( $options['register_css'] &&  $_GET['action'] == 'register' ) echo $options['register_css'];
			elseif ( $options['login_css'] ) echo $options['login_css'];
			?>
</style>
<?php
			}
		}

		function ShowCustomFields() {
			global $user_ID;
			get_currentuserinfo();
			if ( $_GET['user_id'] ) $user_ID = $_GET['user_id'];
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( is_array($custom_fields) && !empty($custom_fields) ) {
				echo '<h3>'.__('Additional Information', 'regplus').'</h3>';
				echo '<table class="form-table">';
				foreach ( $custom_fields as $k => $v ) {
					if ( $v['profile'] ) {
						$id = $this->LabelId($v['label']);
						$value = get_user_meta($user_ID, $id, false);
						$extraoptions = explode(',', $v['extraoptions']);
						switch ( $v['fieldtype'] ) {
							case "text":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td><input type="text" name="'.$id.'" id="'.$id.'" value="'.$value.'" class="regular-text" /></td>';
								echo '	</tr>';
								break;
							case "hidden":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td><input type="text" disabled="disabled" name="'.$id.'" id="'.$id.'" value="'.$value.'" /></td>';
								echo '	</tr>';
								break;
							case "select":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td>';
								echo '			<select name="'.$id.'" id="'.$id.'">';
								foreach ( $extraoptions as $extraoption )
								echo '				<option value="' . $extraoption . '"'; if ( $value == $extraoption ) echo ' selected="selected"'; echo '>'.$extraoption.'</option>';
								echo '			</select>';
								echo '		</td>';
								echo '	</tr>';
								break;
							case "textarea":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td><textarea name="'.$id.'" id="'.$id.'" rows="5" cols="30">'.stripslashes($value).'</textarea></td>';
								echo '	</tr>';
								break;
							case "checkbox":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td>';
								$values = explode(', ', $value);
								foreach ( $extraoptions as $extraoption )
								echo '			<label><input type="checkbox" name="'.$id.'[]" value="'.$extraoption.'"'; if ( in_array($extraoption, $values) ) echo ' checked="checked"'; echo ' />&nbsp;'.$extraoption.'</label>';
								echo '		</td>';
								echo '	</tr>';
								break;
							case "radio":
								echo '	<tr>';
								echo '		<th><label for="'.$id.'">'.$v['label'].'</label></th>';
								echo '		<td>';
								foreach ( $extraoptions as $extraoption )
								echo '			<label><input type="radio" name="'.$id.'" value="'.$extraoption.'"'; if ( $value == $extraoption ) echo ' checked="checked"'; echo '>&nbsp;'.$extraoption.'</label>';
								echo '		</td>';
								echo '	</tr>';
								break;
						}
					}
				}
				echo '</table>';
			}
		}

		function SaveCustomFields() {
			global $wpdb, $user_ID;
			get_currentuserinfo();
			//v.3.5.1 code
			//if ( $_GET['user_id'] ) $user_ID = $_GET['user_id'];
			//code recommended by bitkahuna
			if( !empty($_REQUEST['user_id']) ) $user_ID = $_REQUEST['user_id'];
			$custom_fields = get_option('register_plus_redux_custom_fields');
			if ( !is_array($custom_fields) ) $custom_fields = array();
			if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					if ( $v['profile'] ) {
						$key = $this->LabelId($v['label']);
						if ( is_array($_POST[$key]) ) $_POST[$key] = implode(', ', $_POST[$key]);
						$value = $wpdb->prepare($_POST[$key]);
						update_user_meta($user_ID ,$key ,$value);
					}
				}
			}
		}

		function RandomString( $len ) {
			$chars = "0123456789abcdefghijkl0123456789mnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQ0123456789RSTUVWXYZ0123456789";
			srand((double)microtime()*1000000);
			$i = 0;
			$pass = '';
			while ( $i <= $len ) {
				$num = rand() % 33;
				$tmp = substr($chars, $num, 1);
				$pass = $pass . $tmp;
				$i++;
			}
			return $pass;
		}

		function ValidateUser() {
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			if ( $options['admin_verify'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">' . __('Your account will be reviewed by an administrator and you will be notified when it is activated.', 'regplus') . '</p>';
			} elseif ( $options['email_verify'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">' . __('Please activate your account using the verification link sent to your email address.', 'regplus') . '</p>';
			}
			if ( $options['email_verify'] && isset($_GET['regplus_verification']) ) {
				$verify_key = $_GET['regplus_verification'];
				$user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='email_verify' AND meta_value='$verify_key'");
				if ( $user_id ) {
					$stored_user_login = get_user_meta($user_id, 'email_verify_user', false);
					//v3.5.1 code
					//$wpdb->query("UPDATE $wpdb->users SET user_login='$stored_user_login' WHERE ID='$user_id'");
					//trying to depreciate use of $wpdb->query
					wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
					delete_user_meta($user_id, 'email_verify_user');
					delete_user_meta($user_id, 'email_verify');
					delete_user_meta($user_id, 'email_verify_date');
					$msg = '<p>' . sprintf(__('Thank you %s, your account has been verified, please login.', 'regplus'), $stored_user_login) . '</p>';
					echo $msg;
				}
			}
		}

		function adminfrom() {
			$options = get_option('register_plus_redux_options');
			return $options['adminfrom'];
		}

		function userfrom() {
			$options = get_option('register_plus_redux_options');
			return $options['from'];
		}

		function adminfromname() {
			$options = get_option('register_plus_redux_options');
			return $options['adminfromname'];
		}

		function userfromname() {
			$options = get_option('register_plus_redux_options');
			return $options['fromname'];
		}

		function DeleteInvalidUsers() {
			global $wpdb;
			$options = get_option('register_plus_redux_options');
			$grace = $options['email_delete_grace'];
			$unverified = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='email_verify_date'");
			$grace_date = date('Ymd', strtotime("-7 days"));
			if ( $unverified ) {
				foreach ( $unverified as $bad ) {
					if ( $grace_date > $bad->meta_value ) {
						include_once(admin_url('/includes/user.php'));
						wp_delete_user($bad->user_id);
					}
				}
			}
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
		$custom_fields = get_option('register_plus_redux_custom_fields');
		$ref = explode('?', $_SERVER['HTTP_REFERER']);
		$ref = $ref[0];
		$admin = trailingslashit(get_option('siteurl')) . 'wp-admin/users.php';
		if ( !is_array($custom_fields) ) $custom_fields = array();
		if ( $options['user_set_password'] && $_POST['user_pw'] ) $plaintext_pass = $wpdb->prepare($_POST['user_pw']);
		elseif ( $ref == $admin && $_POST['pass1'] == $_POST['pass2'] ) $plaintext_pass = $wpdb->prepare($_POST['pass1']);
		else $plaintext_pass = $registerPlusRedux->RandomString(6);
		if ( $options['show_firstname_field'] && $_POST['firstname'] ) update_user_meta($user_id, 'first_name', $wpdb->prepare($_POST['firstname']));
		if ( $options['show_lastname_field'] && $_POST['lastname'] ) update_user_meta($user_id, 'last_name', $wpdb->prepare($_POST['lastname']));
		//v.3.5.1 code
		//if ( $options['show_website_field'] && $_POST['user_url'] ) update_user_meta($user_id, 'user_url', $wpdb->prepare($_POST['user_url']));
		if ( $options['show_website_field'] && $_POST['user_url'] ) {
			$url = esc_url_raw( $_POST['user_url'] );
			$user->user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is', $url) ? $url : 'http://'.$url;
			wp_update_user(array('ID' => $user_id, 'user_url' => $wpdb->prepare($url)));
		}
		if ( $options['show_aim_field'] && $_POST['aim'] ) update_user_meta($user_id, 'aim', $wpdb->prepare($_POST['aim']));
		if ( $options['show_yahoo_field'] && $_POST['yahoo'] ) update_user_meta($user_id, 'yim', $wpdb->prepare($_POST['yahoo']));
		if ( $options['show_jabber_field'] && $_POST['jabber'] ) update_user_meta($user_id, 'jabber', $wpdb->prepare($_POST['jabber']));
		if ( $options['show_about_field'] && $_POST['about'] ) update_user_meta($user_id, 'description', $wpdb->prepare($_POST['about']));
		if ( $options['enable_invitation_code'] && $_POST['invitation_code'] ) update_user_meta($user_id, 'invite_code', $wpdb->prepare($_POST['invitation_code']));
		if ( $ref != $admin && $options['admin_verify'] ) {
			update_user_meta($user_id, 'admin_verify_user', $user->user_login);
			$temp_login = 'unverified__' . $registerPlusRedux->RandomString(7);
			$notice = __('Your account requires activation by an administrator before you will be able to login.', 'regplus') . "\r\n";
		} elseif ( $ref != $admin && $options['email_verify'] ) {
			$code = $registerPlusRedux->RandomString(25);
			update_user_meta($user_id, 'email_verify', $code);
			update_user_meta($user_id, 'email_verify_date', date('Ymd'));
			update_user_meta($user_id, 'email_verify_user', $user->user_login);
			$email_code = '?regplus_verification=' . $code;
			$prelink = __('Verification URL: ', 'regplus');
			$notice = __('Please use the link above to verify and activate your account', 'regplus') . "\r\n";
			$temp_login = 'unverified__' . $registerPlusRedux->RandomString(7);
		}
		if ( !empty($custom_fields) ) {
			foreach ( $custom_fields as $k => $v ) {
				$id = $registerPlusRedux->LabelId($v['label']);
				if ( $v['reg'] && $_POST[$id] ) {
					if ( is_array($_POST[$id]) ) $_POST[$id] = implode(', ', $_POST[$id]);
					update_user_meta($user_id, $id, $wpdb->prepare($_POST[$id]));
				}
			}
		}
		wp_set_password($plaintext_pass, $user_id);
		$user_login = stripslashes($user->user_login);
		$user_email = stripslashes($user->user_email);
		$user_url = stripslashes($user->user_url);
		if ( !$options['custom_adminmsg'] && !$options['disable_admin']) {
			$message = sprintf(__('New user Register on your blog %s:', 'regplus'), get_option('blogname')) . "\r\n\r\n";
			$message .= sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n\r\n";
			$message .= sprintf(__('E-mail: %s', 'regplus'), $user_email) . "\r\n";
			@wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Register', 'regplus'), get_option('blogname')), $message);
		} elseif ( !$options['disable_admin'] ) {
			if ( $options['adminhtml'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $options['adminfrom'] . "\r\n" . 'Reply-To: ' . $options['adminfrom'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'adminfrom'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'adminfromname'));
			$subject = $options['adminsubject'];
			$message = str_replace('%user_login%', $user_login, $options['adminmsg']);
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
			if ( $options['enable_invitation_code'] ) $message = str_replace('%invitecode%', $_POST['invitation_code'], $message);
			if ( !is_array($custom_fields) ) $custom_fields = array();
			if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					$meta = $registerPlusRedux->LabelId($v['label']);
					$value = get_user_meta($user_id, $meta, false);
					$message = str_replace('%'.$meta.'%', $value, $message);
				}
			}
			$siteurl = get_option('siteurl');
			$message = str_replace('%siteurl%', $siteurl, $message);
			if ( $options['adminhtml'] && $options['admin_nl2br'] )
				$message = nl2br($message);
			wp_mail(get_option('admin_email'), $subject, $message, $headers); 
		}
		if ( empty($plaintext_pass) )
			return;
		if ( !$options['custom_msg'] ) {
			$message = sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n";
			$message .= sprintf(__('Password: %s', 'regplus'), $plaintext_pass) . "\r\n";
			//$message .= get_option('siteurl') . "/wp-login.php";
			$message .= $prelink . get_option('siteurl') . "/wp-login.php" . $email_code . "\r\n"; 
			$message .= $notice; 
			wp_mail($user_email, sprintf(__('[%s] Your username and password', 'regplus'), get_option('blogname')), $message);
		} else {
			if ( $options['html'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $options['from'] . "\r\n" . 'Reply-To: ' . $options['from'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'userfrom'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'userfromname'));
			$subject = $options['subject'];
			$message = str_replace('%user_pass%', $plaintext_pass, $options['msg']);
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
			if ( $options['enable_invitation_code'] ) $message = str_replace('%invitecode%', $_POST['invitation_code'], $message);
			if ( !is_array($custom_fields) ) $custom_fields = array();
			if ( !empty($custom_fields) ) {
				foreach ( $custom_fields as $k => $v ) {
					$meta = $registerPlusRedux->LabelId($v['label']);
					$value = get_user_meta($user_id, $meta, false);
					$message = str_replace('%'.$meta.'%', $value, $message);
				}
			}
			$redirect = 'redirect_to=' . $options['login_redirect'];
			if ( $options['email_verify'] )
				$siteurl = get_option('siteurl') . "/wp-login.php" . $email_code . '&' . $redirect;
			else
				$siteurl = get_option('siteurl') . "/wp-login.php?" . $redirect;
			$message = str_replace('%siteurl%', $siteurl, $message);
			if ( $options['html'] && $options['user_nl2br'] )
				$message = nl2br($message);
			wp_mail($user_email, $subject, $message, $headers); 
		}
		if ( $ref != $admin && ($options['email_verify'] || $options['admin_verify']) ) 
			//v3.5.1 code
			//$temp_user=$wpdb->query("UPDATE $wpdb->users SET user_login='$temp_login' WHERE ID='$user_id'"); 
			//trying to depreciate use of $wpdb->query
			wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($temp_login)));
	}
endif;
?>