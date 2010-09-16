<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/register-plus-redux/
Description: Fork of Register Plus
Version: 3.6.1
*/

$rp = get_option('plugin_register_plus_redux_settings'); //load options
if ( $rp['enable_invitation_tracking_widget'] ) //if dashboard widget is enabled
	include_once('dash_widget.php'); //add the dashboard widget

if ( !class_exists('RegisterPlusReduxPlugin') ) {
	class RegisterPlusReduxPlugin {
		function RegisterPlusReduxPlugin() {
			global $wp_version;
			//ACTIONS
			add_action('admin_menu', array($this, 'rprAddPages') );
			#Update Settings on Save
			if ( $_POST['action'] == 'reg_plus_update' )
				add_action('init', array($this,'SaveSettings') );
			#Enable jQuery on Settings panel
			if ( $_GET['page'] == 'register-plus' ) {
				wp_enqueue_script('jquery');
				add_action('admin_head', array($this, 'SettingsHead'));
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
				add_action('init', array($this, 'rprInitializeSettings'));
				#Profile
				add_action('show_user_profile', array($this, 'Add2Profile'));
				add_action('edit_user_profile', array($this, 'Add2Profile'));
				add_action('profile_update', array($this, 'SaveProfile'));
				#Validate User
				add_action('login_form', array($this, 'ValidateUser'));
				#Delete Invalid Users
				add_action('init', array($this, 'DeleteInvalidUsers'));
				#Unverified Users Head Scripts
				add_action('admin_head', array($this, 'UnverifiedHead'));
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
			load_plugin_textdomain('regplus', '/wp-content/plugins/register-plus');
			
			//VERSION CONTROL
			if ( $wp_version < 3.0 )
				add_action('admin_notices', array($this, 'VersionWarning'));
		}

		//Show warning if plugin is installed on a WordPress lower than 2.5
		function VersionWarning() {
			global $wp_version;
			echo "<div id='regplus-warning' class='updated fade-ff0000'><p><strong>".__('Register Plus is only compatible with WordPress 3.0 and up. You are currently using WordPress ', 'regplus').$wp_version."</strong></p></div>";
		}

		function override_warning() {
			if ( current_user_can(10) && $_GET['page'] == 'register-plus' )
			echo "<div id='regplus-warning' class='updated fade-ff0000'><p><strong>".__('You have another plugin installed that is conflicting with Register Plus. This other plugin is overriding the user notification emails. Please see <a href="http://skullbit.com/news/register-plus-conflicts/">Register Plus Conflicts</a> for more information.', 'regplus') . "</strong></p></div>";
		}

		//Add Settings and User Pages
		function rprAddPages() {
			add_submenu_page('options-general.php','Register Plus Redux Settings', 'Register Plus Redux', 'manage_options', 'register-plus-redux', array($this, 'rprSettingsPage'));
			add_filter('plugin_action_links', array($this, 'filter_plugin_actions'), 10, 2);
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			if ( $rprSettings['email_verify'] || $rprSettings['admin_verify'] )
				add_submenu_page('users.php','Unverified Users', 'Unverified Users', 'promote_users', 'unverified-users', array($this, 'rprUnverifiedUsersPage'));
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

		function rprInitializeSettings() {
			$default = array(
				'user_set_password'	=> '0',

				'show_password_meter'	=> '0',
				'message_short_password'=> 'Too Short',
				'message_bad_password'	=> 'Bad Password',
				'message_good_password'	=> 'Good Password',
				'message_strong_password'=> 'Strong Password',

				'enable_invitation_code'			=> '0',
				'require_invitation_code' => '0',
				'invitation_code_bank'		=> array('0'),
				'enable_invitation_tracking_widget'		=> '0',

				'show_disclaimer'	=> '0',
				'message_disclaimer_title'	=> 'Disclaimer',
				'message_disclaimer'	=> '',
				'message_disclaimer_agree'=> 'Accept the Disclaimer',

				'show_license_agreement'=> '0',
				'message_license_title'		=> 'License Agreement',
				'message_license'	=> '',
				'message_license_agree'	=> 'Accept the License Agreement',

				'show_privacy_policy'	=> '0',
				'message_privacy_policy_title'		=> 'Privacy Policy',
				'message_privacy_policy'	=> '',
				'message_privacy_policy_agree'=> 'Accept the Privacy Policy',

				'email_exists'		=> '0',

				'show_firstname_field'		=> '0',
				'show_lastname_field'		=> '0',
				'show_website_field'		=> '0',
				'show_aim_field'			=> '0',
				'show_yahoo_field'			=> '0',
				'show_jabber_field'		=> '0',
				'show_about_field'			=> '0',

				'required_fields'		=> array('0'),
				'required_fields_style'		=> 'border:solid 1px #E6DB55;background-color:#FFFFE0;',

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
				'firstday'		=> 6,
				'dateformat'		=> 'mm/dd/yyyy',
				'startdate'		=> '',
				'calyear'		=> '',
				'calmonth'		=> 'cur'
			);
			if ( !get_option('plugin_register_plus_redux_settings') ) {
				#Check if settings exist, add defaults in necessary
				add_option('plugin_register_plus_redux_settings', $default);
			} else {
				#Check settings for new variables, add as necessary
				$rprSettings = get_option('plugin_register_plus_redux_settings');
				foreach ( $default as $key => $val ) {
					if ( !$rprSettings[$key] ) {
						$rprSettings[$key] = $val;
						$new = true;
					}
				}
				if ( $new ) update_option('plugin_register_plus_redux_settings', $rprSettings);
			}
		}
		
		function SaveSettings() {
			check_admin_referer('regplus-update-options');
			$update = get_option('plugin_register_plus_redux_settings');
			$update["password"] = $_POST['regplus_password'];
			$update["password_meter"] = $_POST['regplus_password_meter'];
			$update["short"] = $_POST['regplus_short'];
			$update["bad"] = $_POST['regplus_bad'];
			$update["good"] = $_POST['regplus_good'];
			$update["strong"] = $_POST['regplus_strong'];
			$update["code"] = $_POST['regplus_code'];
			if ( $_POST['regplus_code'] ) {
				$update["codepass"] = $_POST['regplus_codepass'];
				foreach ( $update["codepass"] as $k => $v ) {
					$update["codepass"][$k] = strtolower($v);
				}
				$update["code_req"] = $_POST['regplus_code_req'];
			}
			$update["disclaimer"] = $_POST['regplus_disclaimer'];
			$update["disclaimer_title"] = $_POST['regplus_disclaimer_title'];
			$update["disclaimer_content"] = $_POST['regplus_disclaimer_content'];
			$update["disclaimer_agree"] = $_POST['regplus_disclaimer_agree'];
			$update["license"] = $_POST['regplus_license'];
			$update["license_title"] = $_POST['regplus_license_title'];
			$update["license_content"] = $_POST['regplus_license_content'];
			$update["license_agree"] = $_POST['regplus_license_agree'];
			$update["privacy"] = $_POST['regplus_privacy'];
			$update["privacy_title"] = $_POST['regplus_privacy_title'];
			$update["privacy_content"] = $_POST['regplus_privacy_content'];
			$update["privacy_agree"] = $_POST['regplus_privacy_agree'];
			$update["email_exists"] = $_POST['regplus_email_exists'];
			$update["firstname"] = $_POST['regplus_firstname'];
			$update["lastname"] = $_POST['regplus_lastname'];
			$update["website"] = $_POST['regplus_website'];
			$update["aim"] = $_POST['regplus_aim'];
			$update["yahoo"] = $_POST['regplus_yahoo'];
			$update["jabber"] = $_POST['regplus_jabber'];
			$update["about"] = $_POST['regplus_about'];
			$update["profile_req"] = $_POST['regplus_profile_req'];
			$update["require_style"] = $_POST['regplus_require_style'];
			$update["dash_widget"] = $_POST['regplus_dash_widget'];
			$update["admin_verify"] = $_POST['regplus_admin_verify'];
			$update["email_verify"] = $_POST['regplus_email_verify'];
			$update["email_verify_date"] = $_POST['regplus_email_verify_date'];
			$update["email_delete_grace"] = $_POST['regplus_email_delete_grace'];
			$update["reCAP_public_key"] = $_POST['regplus_reCAP_public_key'];
			$update["reCAP_private_key"] = $_POST['regplus_reCAP_private_key'];
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
			$update['firstday'] = $_POST['regplus_firstday'];
			$update['dateformat'] = $_POST['regplus_dateformat'];
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

			update_option('register_plus_custom', $custom);
			update_option('plugin_register_plus_redux_settings', $update);
			$_POST['notice'] = __('Settings Saved', 'regplus');
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

		function SettingsHead() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
?>
<script type="text/javascript">
	function set_add_del_code() {
		jQuery('.remove_code').show();
		jQuery('.add_code').hide();
		jQuery('.add_code:last').show();
		jQuery(".code_block:only-child > .remove_code").hide();
	}

	function selremcode(clickety) {
		jQuery(clickety).parent().remove();
		set_add_del_code();
		return false;
	}

	function seladdcode(clickety) {
		jQuery('.code_block:last').after(
		jQuery('.code_block:last').clone());
		jQuery('.code_block:last input').attr('value', '');
		set_add_del_code(); 
		return false;
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
	<?php if ( !$rprSettings['enable_invitation_code']) { ?>
		jQuery('#codepass').hide(); <?php } ?>
	<?php if ( !$rprSettings['show_password_meter']) { ?>
		jQuery('#meter').hide(); <?php } ?>
	<?php if ( !$rprSettings['show_disclaimer']) { ?>
		jQuery('#disclaim_content').hide(); <?php } ?>
	<?php if ( !$rprSettings['show_license_agreement']) { ?>
		jQuery('#lic_content').hide(); <?php } ?>
	<?php if ( !$rprSettings['show_privacy_policy']) { ?>
		jQuery('#priv_content').hide(); <?php } ?>
	<?php if ( !$rprSettings['email_verify']) { ?>
		jQuery('#grace').hide(); <?php } ?>
	<?php if ( !$rprSettings['custom_msg']) { ?>
		jQuery('#enabled_msg').hide(); <?php } ?>
	<?php if ( !$rprSettings['custom_adminmsg']) { ?>
		jQuery('#enabled_adminmsg').hide(); <?php } ?>
		jQuery('#email_verify').change(function() {
			if ( jQuery('#email_verify').attr('checked') )
				jQuery('#grace').show();
			else
				jQuery('#grace').hide();
			return true;
		});
		jQuery('#code').change(function() {
			if (jQuery('#code').attr('checked') )
				jQuery('#codepass').show();
			else
				jQuery('#codepass').hide();
			return true;
		});
		jQuery('#pwm').change(function() {
			if (jQuery('#pwm').attr('checked') )
				jQuery('#meter').show();
			else
				jQuery('#meter').hide();
			return true;
		});
		jQuery('#disclaimer').change(function() {
			if (jQuery('#disclaimer').attr('checked') )
				jQuery('#disclaim_content').show();
			else
				jQuery('#disclaim_content').hide();
			return true;
		});
		jQuery('#license').change(function() {
			if (jQuery('#license').attr('checked') )
				jQuery('#lic_content').show();
			else
				jQuery('#lic_content').hide();
			return true;
		});
		jQuery('#privacy').change(function() {
			if (jQuery('#privacy').attr('checked') )
				jQuery('#priv_content').show();
			else
				jQuery('#priv_content').hide();
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
		set_add_del_code();
		set_add_del();
	});
</script>
<?php
		}

		function UnverifiedHead() {
			if ( $_GET['page'] == 'unverified-users' )
				echo "<script type='text/javascript' src='".get_option('siteurl')."/wp-admin/js/forms.js?ver=20080317'></script>";
		}

		function AdminValidate() {
			global $wpdb;
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			check_admin_referer('regplus-unverified');
			$valid = $_POST['vusers'];
			foreach ( $valid as $user_id ) {
				if ( $user_id ) {
					if ( $rprSettings['email_verify'] ) {
						$stored_user_login = get_user_meta($user_id, 'email_verify_user', false);
						//v3.5.1
						//$wpdb->query("UPDATE $wpdb->users SET user_login='$stored_user_login' WHERE ID='$user_id'");
						//trying to depreciate use of $wpdb->query
						wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($stored_user_login)));
						delete_user_meta($user_id, 'email_verify_user');
						delete_user_meta($user_id, 'email_verify');
						delete_user_meta($user_id, 'email_verify_date');
					} elseif ( $rprSettings['admin_verify'] ) {
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			check_admin_referer('regplus-unverified');
			$delete = $_POST['vusers'];
			include_once(ABSPATH . 'wp-admin/includes/user.php');
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			$user = $wpdb->get_row("SELECT user_login, user_email FROM $wpdb->users WHERE ID='$user_id'");
			$message = __('Your account has now been activated by an administrator.') . "\r\n";
			$message .= sprintf(__('Username: %s', 'regplus'), $user->user_login) . "\r\n";
			$message .= $prelink . get_option('siteurl') . "/wp-login.php" . $email_code . "\r\n"; 
			add_filter('wp_mail_from', array($this, 'userfrom'));
			add_filter('wp_mail_from_name', array($this, 'userfromname'));
			wp_mail($user->user_email, sprintf(__('[%s] User Account Activated', 'regplus'), get_option('blogname')), $message);
		}

		function rprUnverifiedUsersPage() {
			global $wpdb;
			if ( $_POST['notice'] )
				echo '<div id="message" class="updated fade"><p><strong>' . $_POST['notice'] . '.</strong></p></div>';
			$unverified = $wpdb->get_results("SELECT * FROM $wpdb->users WHERE user_login LIKE '%unverified__%'");
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			?>
<div class="wrap">
	<h2><?php _e('Unverified Users', 'regplus') ?></h2>
	<form id="verify-filter" method="post" action="">
	<?php if ( function_exists('wp_nonce_field') ) wp_nonce_field('regplus-unverified'); ?>
	<div class="tablenav">
		<div class="alignleft">
			<input value="<?php _e('Verify Checked Users','regplus'); ?>" name="verifyit" class="button-secondary" type="submit">&nbsp;
			<?php if ( $rprSettings['email_verify'] ) { ?>
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
				if ( $rprSettings['email_verify'] ) $user_login = get_user_meta($un->ID, 'email_verify_user', false);
				elseif ( $rprSettings['admin_verify'] ) $user_login = get_user_meta($un->ID, 'admin_verify_user', false);
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

		function rprSettingsPage() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			$regplus_custom = get_option('register_plus_custom');
			$plugin_url = trailingslashit(get_option('siteurl')) . 'wp-content/plugins/' . basename(dirname(__FILE__)) .'/';
			if ( $_POST['notice'] ) echo '<div id="message" class="updated fade"><p><strong>' . $_POST['notice'] . '.</strong></p></div>';
			if ( !is_array($rprSettings['required_fields']) ) $rprSettings['required_fields'] = array();
			if ( is_array($rprSettings['invitation_code_bank']) ) {
				foreach ($rprSettings['invitation_code_bank'] as $code ) {
					$codes .= '<div class="code_block">
					           	<input type="text" name="regplus_codepass[]" value="' . $code . '" /> &nbsp;
					           	<a href="#" onClick="return selremcode(this);" class="remove_code"><img src="' . $plugin_url . 'removeBtn.gif" alt="' . __("Remove Code","regplus") . '" title="' . __("Remove Code","regplus") . '" /></a>
					           	<a href="#" onClick="return seladdcode(this);" class="add_code"><img src="' . $plugin_url . 'addBtn.gif" alt="' . __("Add Code","regplus") . '" title="' . __("Add Code","regplus") . '" /></a>
 					           </div>';
				}
			}
			$types = '<option value="text">Text Field</option><option value="date">Date Field</option><option value="select">Select Field</option><option value="checkbox">Checkbox</option><option value="radio">Radio Box</option><option value="textarea">Text Area</option><option value="hidden">Hidden Field</option>';
			$extras = '<div class="extraoptions" style="float:left"><label>Extra Options: <input type="text" class="extraops" name="extraoptions[0]" value="" /></label></div>';
			if ( is_array($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					$types = '<option value="text"';
					if ( $v['fieldtype'] == 'text' ) $types .= ' selected="selected"';
					$types .='>Text Field</option><option value="date"';
					if ( $v['fieldtype'] == 'date' ) $types .= ' selected="selected"';
					$types .='>Date Field</option><option value="select"';
					if ( $v['fieldtype'] == 'select' ) $types .= ' selected="selected"';
					$types .= '>Select Field</option><option value="checkbox"';
					if ( $v['fieldtype'] == 'checkbox' ) $types .= ' selected="selected"';
					$types .= '>Checkbox</option><option value="radio"';
					if ( $v['fieldtype'] == 'radio' ) $types .= ' selected="selected"';
					$types .= '>Radio Box</option><option value="textarea"';
					if ( $v['fieldtype'] == 'textarea' ) $types .= ' selected="selected"';
					$types .= '>Text Area</option><option value="hidden"';
					if ( $v['fieldtype'] == 'hidden' ) $types .= ' selected="selected"';
					$types .= '>Hidden Field</option>';
					$extras = '<div class="extraoptions" style="float:left;"><label>Extra Options: <input type="text" name="extraoptions['.$k.']" class="extraops" value="' . $v['extraoptions'] . '" /></label></div>';
					$rows .= '<tr valign="top" class="row_block">
					          <th scope="row"><label for="custom">' . __('Custom Field', 'regplus') . '</label></th>
					          <td><input type="text" name="label['.$k.']" class="custom" style="font-size:16px;padding:2px; width:150px;" value="' . $v['label'] . '" />&nbsp;';
					$rows .= '<select name="fieldtype['.$k.']" class="fieldtype">'.$types.'</select> '.$extras.'&nbsp;';
					$rows .= '<label><input type="checkbox" name="reg['.$k.']" class="reg" value="1"';
					if ( $v['reg'] ) $rows .= ' checked="checked"';
					$rows .= ' /> ' . __('Add Registration Field', 'regplus') . '</label>&nbsp;<label><input type="checkbox" name="profile['.$k.']" class="profile" value="1"';
					if ( $v['profile'] ) $rows .= ' checked="checked"';
					$rows .= ' /> ' . __('Add Profile Field', 'regplus') . '</label>&nbsp;<label><input type="checkbox" name="required['.$k.']" class="required" value="1"';
					if ( $v['required'] ) $rows .= ' checked="checked"';
					$rows .= ' /> ' . __('Required', 'regplus') . '</label>&nbsp;
					                                               <a href="#" onClick="return selrem(this);" class="remove_row"><img src="' . $plugin_url . 'removeBtn.gif" alt="' . __("Remove Row","regplus") . '" title="' . __("Remove Row","regplus") . '" /></a>
					                                               <a href="#" onClick="return seladd(this);" class="add_row"><img src="' . $plugin_url . 'addBtn.gif" alt="' . __("Add Row","regplus") . '" title="' . __("Add Row","regplus") . '" /></a></td>
					                                               </tr>';
				}
			}
?>
<div class="wrap">
	<h2><?php _e('Register Plus Settings', 'regplus') ?></h2>
	<form method="post" action="" enctype="multipart/form-data">
	<?php if ( function_exists('wp_nonce_field')) wp_nonce_field('regplus-update-options'); ?>
	<p class="submit"><input name="Submit" value="<?php _e('Save Changes','regplus'); ?>" type="submit" />
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="password"><?php _e('Password', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_password" id="password" value="1" <?php if ( $rprSettings['user_set_password']) echo 'checked="checked"'; ?> /><?php _e('Allow New Registrations to set their own Password', 'regplus'); ?></label><br />
					<label><input type="checkbox" name="regplus_password_meter" id="pwm" value="1" <?php if ( $rprSettings['show_password_meter']) echo 'checked="checked"'; ?> /><?php _e('Enable Password Strength Meter','regplus'); ?></label>
					<div id="meter" style="margin-left:20px;">
						<label><?php _e('Short', 'regplus'); ?><input type="text" name="regplus_short" value="<?php echo $rprSettings['message_short_password']; ?>" /></label><br />
						<label><?php _e('Bad', 'regplus'); ?><input type="text" name="regplus_bad" value="<?php echo $rprSettings['message_bad_password']; ?>" /></label><br />
						<label><?php _e('Good', 'regplus'); ?><input type="text" name="regplus_good" value="<?php echo $rprSettings['message_good_password']; ?>" /></label><br />
						<label><?php _e('Strong', 'regplus'); ?><input type="text" name="regplus_strong" value="<?php echo $rprSettings['message_strong_password']; ?>" /></label><br />
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="logo"><?php _e('Custom Logo', 'regplus'); ?></label></th>
				<td>
					<input type="file" name="regplus_logo" id="logo" value="1" />&nbsp;<small><?php _e("Recommended Logo width is 292px, but any height should work.", "regplus"); ?></small><br /><img src="<?php echo $rprSettings['logo']; ?>" alt="" />
				<?php if ($rprSettings['logo']) { ?>
					<br /><label><input type="checkbox" name="remove_logo" value="1" /><?php _e('Delete Logo', 'regplus'); ?></label>
				<?php } else { ?>
					<p><small><strong><?php _e('Having troubles uploading?','regplus'); ?></strong><?php _e('Uncheck "Organize my uploads into month- and year-based folders" in','regplus'); ?><a href="<?php echo get_option('siteurl'); ?>/wp-admin/options-misc.php"><?php _e('Miscellaneous Settings', 'regplus'); ?></a>. <?php _e('(You can recheck this option after your logo has uploaded.)','regplus'); ?></small></p>
				<?php } ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="email_verify"><?php _e('Email Verification', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_email_verify" id="email_verify" value="1" <?php if ( $rprSettings['email_verify']) echo 'checked="checked"'; ?> /><?php _e('Prevent fake email address registrations.', 'regplus'); ?></label><br />
					<?php _e('Requires new registrations to click a link in the notification email to enable their account.', 'regplus'); ?>
					<div id="grace"><label for="email_delete_grace"><strong><?php _e('Grace Period (days)', 'regplus'); ?></strong>: </label><input type="text" name="regplus_email_delete_grace" id="email_delete_grace" style="width:50px;" value="<?php echo $rprSettings['email_delete_grace']; ?>" /><br />
					<?php _e('Unverified Users will be automatically deleted after grace period expires', 'regplus'); ?></div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="admin_verify"><?php _e('Admin Verification', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_admin_verify" id="admin_verify" value="1" <?php if ( $rprSettings['admin_verify']) echo 'checked="checked"'; ?> /><?php _e('Moderate all user registrations to require admin approval. NOTE: Email Verification must be DISABLED to use this feature.', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="code"><?php _e('Invitation Code', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_code" id="code" value="1" <?php if ( $rprSettings['enable_invitation_code']) echo 'checked="checked"'; ?> /><?php _e('Enable Invitation Code(s)', 'regplus'); ?></label>
					<div id="codepass">
						<label><input type="checkbox" name="regplus_dash_widget" value="1" <?php if ( $rprSettings['enable_invitation_tracking_widget']) echo 'checked="checked"';  ?>  /><?php _e('Enable Invitation Tracking Dashboard Widget', 'regplus'); ?></label><br />
						<label><input type="checkbox" name="regplus_code_req" id="code_req" value="1" <?php if ( $rprSettings['require_invitation_code']) echo 'checked="checked"'; ?> /><?php _e('Require Invitation Code to Register', 'regplus'); ?></label>
						<?php if ( $codes) { echo $codes; } else { ?>
						<div class="code_block">
							<input type="text" name="regplus_codepass[]"  value="<?php echo $rprSettings['invitation_code_bank']; ?>" /> &nbsp;
							<a href="#" onClick="return selremcode(this);" class="remove_code"><img src="<?php echo $plugin_url; ?>removeBtn.gif" alt="<?php _e("Remove Code","regplus") ?>" title="<?php _e("Remove Code","regplus") ?>" /></a>
							<a href="#" onClick="return seladdcode(this);" class="add_code"><img src="<?php echo $plugin_url; ?>addBtn.gif" alt="<?php _e("Add Code","regplus") ?>" title="<?php _e("Add Code","regplus") ?>" /></a>
						</div>
						<?php } ?>
						<small><?php _e('One of these codes will be required for users to register.', 'regplus'); ?></small>
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="disclaimer"><?php _e('Disclaimer', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_disclaimer" id="disclaimer" value="1" <?php if ( $rprSettings['show_disclaimer']) echo 'checked="checked"'; ?> /><?php _e('Enable Disclaimer','regplus'); ?></label>
					<div id="disclaim_content">
						<label for="disclaimer_title"><?php _e('Disclaimer Title','regplus'); ?></label><input type="text" name="regplus_disclaimer_title" id="disclaimer_title" value="<?php echo $rprSettings['message_disclaimer_title']; ?>" /><br />
						<label for="disclaimer_content"><?php _e('Disclaimer Content','regplus'); ?></label><br />
						<textarea name="regplus_disclaimer_content" id="disclaimer_content" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($rprSettings['message_disclaimer']); ?></textarea><br />
						<label for="disclaimer_agree"><?php _e('Agreement Text','regplus'); ?></label><input type="text" name="regplus_disclaimer_agree" id="disclaimer_agree" value="<?php echo $rprSettings['message_disclaimer_agree']; ?>" />
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="license"><?php _e('License Agreement', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_license" id="license" value="1" <?php if ( $rprSettings['show_license_agreement']) echo 'checked="checked"'; ?> /><?php _e('Enable License Agreement','regplus'); ?></label>
					<div id="lic_content">
						<label for="license_title"><?php _e('License Title','regplus'); ?></label><input type="text" name="regplus_license_title" id="license_title" value="<?php echo $rprSettings['message_license_title']; ?>" /><br />
						<label for="license_content"><?php _e('License Content','regplus'); ?></label><br />
						<textarea name="regplus_license_content" id="license_content" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($rprSettings['message_license']); ?></textarea><br />
						<label for="license_agree"><?php _e('Agreement Text','regplus'); ?></label><input type="text" name="regplus_license_agree" id="license_agree" value="<?php echo $rprSettings['message_license_agree']; ?>" />
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="privacy"><?php _e('Privacy Policy', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_privacy" id="privacy" value="1" <?php if ( $rprSettings['show_privacy_policy']) echo 'checked="checked"'; ?> /><?php _e('Enable Privacy Policy','regplus'); ?></label>
					<div id="priv_content">
						<label for="privacy_title"><?php _e('Privacy Policy Title','regplus'); ?></label><input type="text" name="regplus_privacy_title" id="privacy_title" value="<?php echo $rprSettings['message_privacy_policy_title']; ?>" /><br />
						<label for="privacy_content"><?php _e('Privacy Policy Content','regplus'); ?></label><br />
						<textarea name="regplus_privacy_content" id="privacy_content" cols="25" rows="10" style="width:80%;height:300px;display:block;"><?php echo stripslashes($rprSettings['message_privacy_policy']); ?></textarea><br />
						<label for="privacy_agree"><?php _e('Agreement Text','regplus'); ?></label><input type="text" name="regplus_privacy_agree" id="privacy_agree" value="<?php echo $rprSettings['message_privacy_policy_agree']; ?>" />
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="email_exists"><?php _e('Allow Existing Email', 'regplus'); ?></label></th>
				<td>
					<label><input type="checkbox" name="regplus_email_exists" id="email_exists" value="1" <?php if ( $rprSettings['email_exists']) echo 'checked="checked""'; ?> /><?php _e('Allow new registrations to use an email address that has been previously registered', 'regplus'); ?></label>
				</td>
			</tr>
		</tbody>
	</table>
	<h3><?php _e('Additional Profile Fields', 'regplus'); ?></h3>
	<p><?php _e('Check the fields you would like to appear on the Registration Page.', 'regplus'); ?></p>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="name"><?php _e('Name', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_firstname" id="name" value="1" <?php if ( $rprSettings['show_firstname_field']) echo 'checked="checked"'; ?> /><?php _e('First Name', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_lastname" value="1" <?php if ( $rprSettings['show_lastname_field']) echo 'checked="checked"'; ?> /><?php _e('Last Name', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="contact"><?php _e('Contact Info', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_website" id="contact" value="1" <?php if ( $rprSettings['show_website_field']) echo 'checked="checked"'; ?> /><?php _e('Website', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_aim" value="1" <?php if ( $rprSettings['show_aim_field']) echo 'checked="checked"'; ?> /><?php _e('AIM', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_yahoo" value="1" <?php if ( $rprSettings['show_yahoo_field']) echo 'checked="checked"'; ?> /><?php _e('Yahoo IM', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_jabber" value="1" <?php if ( $rprSettings['show_jabber_field']) echo 'checked="checked"'; ?> /><?php _e('Jabber / Google Talk', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="about"><?php _e('About Yourself', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_about" id="name" value="1" <?php if ( $rprSettings['show_about_field']) echo 'checked="checked"'; ?> /><?php _e('About Yourself', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="req"><?php _e('Required Profile Fields', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_profile_req[]" value="firstname" <?php if ( in_array('firstname', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('First Name', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="lastname" <?php if ( in_array('lastname', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('Last Name', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="website" <?php if ( in_array('website', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('Website', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="aim" <?php if ( in_array('aim', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('AIM', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="yahoo" <?php if ( in_array('yahoo', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('Yahoo IM', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="jabber" <?php if ( in_array('jabber', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('Jabber / Google Talk', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_profile_req[]" value="about" <?php if ( in_array('about', $rprSettings['required_fields'])) echo 'checked="checked"'; ?> /><?php _e('About Yourself', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="require_style"><?php _e('Required Field Style Rules', 'regplus'); ?></label></th>
				<td><input type="text" name="regplus_require_style" id="require_style" value="<?php echo $rprSettings['required_fields_style']; ?>" style="width: 350px;" /></td>
			</tr>
		</tbody>
	</table>
	<h3><?php _e('User Defined Fields', 'regplus'); ?></h3>
	<p><?php _e('Enter the custom fields you would like to appear on the Registration Page.', 'regplus'); ?></p>
	<p><small><?php _e('Enter Extra Options for Select, Checkboxes and Radio Fields as comma seperated values. For example, if you chose a select box for a custom field of "Gender", your extra options would be "Male,Female".','regplus'); ?></small></p>
	<table class="form-table">
		<tbody>
		<?php if ( $rows) { echo $rows; } else { ?>
			<tr valign="top" class="row_block">
				<th scope="row"><label for="custom"><?php _e('Custom Field', 'regplus'); ?></label></th>
				<td>
					<input type="text" name="label[0]" class="custom" style="font-size:16px;padding:2px; width:150px;" value="" />&nbsp;<select class="fieldtype" name="fieldtype[0]"><?php echo $types; ?></select><?php echo $extras; ?>&nbsp;<label><input type="checkbox" name="reg[0]" class="reg" value="1" />  <?php _e('Add Registration Field', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="profile[0]"  class="profile" value="1" /><?php _e('Add Profile Field', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="required[0]" class="required" value="1" /><?php _e('Required', 'regplus'); ?></label>&nbsp;
					<a href="#" onClick="return selrem(this);" class="remove_row"><img src="<?php echo $plugin_url; ?>removeBtn.gif" alt="<?php _e("Remove Row","regplus") ?>" title="<?php _e("Remove Row","regplus") ?>" /></a>
					<a href="#" onClick="return seladd(this);" class="add_row"><img src="<?php echo $plugin_url; ?>addBtn.gif" alt="<?php _e("Add Row","regplus") ?>" title="<?php _e("Add Row","regplus") ?>" /></a>
				</td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="date"><?php _e('Date Field Settings', 'regplus'); ?></label></th>
				<td>
					<label><?php _e('First Day of the Week','regplus'); ?>:
						<select type="select" name="regplus_firstday">
							<option value="7" <?php if ( $rprSettings['firstday'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Monday','regplus'); ?></option>
							<option value="1" <?php if ( $rprSettings['firstday'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Tuesday','regplus'); ?></option>
							<option value="2" <?php if ( $rprSettings['firstday'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Wednesday','regplus'); ?></option>
							<option value="3" <?php if ( $rprSettings['firstday'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Thursday','regplus'); ?></option>
							<option value="4" <?php if ( $rprSettings['firstday'] == '4' ) echo 'selected="selected"'; ?>><?php _e('Friday','regplus'); ?></option>
							<option value="5" <?php if ( $rprSettings['firstday'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Saturday','regplus'); ?></option>
							<option value="6" <?php if ( $rprSettings['firstday'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Sunday','regplus'); ?></option>
						</select>
					</label>&nbsp;
					<label for="dateformat"><?php _e('Date Format','regplus'); ?>:</label><input type="text" name="regplus_dateformat" id="dateformat" value="<?php echo $rprSettings['dateformat']; ?>" style="width:100px;" />&nbsp;
					<label for="startdate"><?php _e('First Selectable Date','regplus'); ?>:</label><input type="text" name="regplus_startdate" id="startdate" value="<?php echo $rprSettings['startdate']; ?>"  style="width:100px;" /><br />
					<label for="calyear"><?php _e('Default Year','regplus'); ?>:</label><input type="text" name="regplus_calyear" id="calyear" value="<?php echo $rprSettings['calyear']; ?>" style="width:40px;" />&nbsp;
					<label for="calmonth"><?php _e('Default Month','regplus'); ?>:</label>
					<select name="regplus_calmonth" id="calmonth">
						<option value="cur" <?php if ( $rprSettings['calmonth'] == 'cur' ) echo 'selected="selected"'; ?>><?php _e('Current Month','regplus'); ?></option>
						<option value="0" <?php if ( $rprSettings['calmonth'] == '0' ) echo 'selected="selected"'; ?>><?php _e('Jan','regplus'); ?></option>
						<option value="1" <?php if ( $rprSettings['calmonth'] == '1' ) echo 'selected="selected"'; ?>><?php _e('Feb','regplus'); ?></option>
						<option value="2" <?php if ( $rprSettings['calmonth'] == '2' ) echo 'selected="selected"'; ?>><?php _e('Mar','regplus'); ?></option>
						<option value="3" <?php if ( $rprSettings['calmonth'] == '3' ) echo 'selected="selected"'; ?>><?php _e('Apr','regplus'); ?></option>
						<option value="4" <?php if ( $rprSettings['calmonth'] == '4' ) echo 'selected="selected"'; ?>><?php _e('May','regplus'); ?></option>
						<option value="5" <?php if ( $rprSettings['calmonth'] == '5' ) echo 'selected="selected"'; ?>><?php _e('Jun','regplus'); ?></option>
						<option value="6" <?php if ( $rprSettings['calmonth'] == '6' ) echo 'selected="selected"'; ?>><?php _e('Jul','regplus'); ?></option>
						<option value="7" <?php if ( $rprSettings['calmonth'] == '7' ) echo 'selected="selected"'; ?>><?php _e('Aug','regplus'); ?></option>
						<option value="8" <?php if ( $rprSettings['calmonth'] == '8' ) echo 'selected="selected"'; ?>><?php _e('Sep','regplus'); ?></option>
						<option value="9" <?php if ( $rprSettings['calmonth'] == '9' ) echo 'selected="selected"'; ?>><?php _e('Oct','regplus'); ?></option>
						<option value="10" <?php if ( $rprSettings['calmonth'] == '10' ) echo 'selected="selected"'; ?>><?php _e('Nov','regplus'); ?></option>
						<option value="11" <?php if ( $rprSettings['calmonth'] == '11' ) echo 'selected="selected"'; ?>><?php _e('Dec','regplus'); ?></option>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
	<h3><?php _e('Auto-Complete Queries', 'regplus'); ?></h3>
	<p><?php _e('You can now link to the registration page with queries to autocomplete specific fields for the user.  I have included the query keys below and an example of a query URL.', 'regplus'); ?></p>
	<code>user_login&nbsp;user_email&nbsp;firstname&nbsp;lastname&nbsp;user_url&nbsp;aim&nbsp;yahoo&nbsp;jabber&nbsp;about&nbsp;code</code>
	<p><?php _e('For any custom fields, use your custom field label with the text all lowercase, using underscores instead of spaces. For example if your custom field was "Middle Name" your query key would be <code>middle_name</code>', 'regplus'); ?></p>
	<p><strong><?php _e('Example Query URL', 'regplus'); ?></strong></p>
	<code>http://www.skullbit.com/wp-login.php?action=register&user_login=skullbit&user_email=info@skullbit.com&firstname=Skull&lastname=Bit&user_url=www.skullbit.com&aim=skullaim&yahoo=skullhoo&jabber=skulltalk&about=I+am+a+WordPress+Plugin+developer.&code=invitation&middle_name=Danger</code>
	<h3><?php _e('Customize User Notification Email', 'regplus'); ?></h3>
	<table class="form-table"> 
		<tbody>
			<tr valign="top">
				<th scope="row"><label><?php _e('Custom User Email Notification', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_custom_msg" id="custom_msg" value="1" <?php if ( $rprSettings['custom_msg']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
			</tr>
		</tbody>
	</table>
	<div id="enabled_msg">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="from"><?php _e('From Email', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_from" id="from" style="width:250px;" value="<?php echo $rprSettings['from']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="fromname"><?php _e('From Name', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_fromname" id="fromname" style="width:250px;" value="<?php echo $rprSettings['fromname']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="subject"><?php _e('Subject', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_subject" id="subject" style="width:350px;" value="<?php echo $rprSettings['subject']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="msg"><?php _e('User Message', 'regplus'); ?></label></th>
					<td>
					<?php
					if ( $rprSettings['show_firstname_field'] ) $custom_keys .= '&nbsp;%firstname%';
					if ( $rprSettings['show_lastname_field'] ) $custom_keys .= '&nbsp;%lastname%';
					if ( $rprSettings['show_website_field'] ) $custom_keys .= '&nbsp;%user_url%';
					if ( $rprSettings['show_aim_field'] ) $custom_keys .= '&nbsp;%aim%';
					if ( $rprSettings['show_yahoo_field'] ) $custom_keys .= '&nbsp;%yahoo%';
					if ( $rprSettings['show_jabber_field'] ) $custom_keys .= '&nbsp;%jabber%';
					if ( $rprSettings['show_about_field'] ) $custom_keys .= '&nbsp;%about%';
					if ( $rprSettings['enable_invitation_code'] ) $custom_keys .= '&nbsp;%invitecode%';
					if ( is_array($regplus_custom) ) {
						foreach ( $regplus_custom as $k => $v ) {
							$meta = $this->LabelId($v['label']);
							$value = get_user_meta($user_id, $meta, false);
							$custom_keys .= '&nbsp;%'.$meta.'%';
						}
					}
					?>
						<p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong>&nbsp;%user_login% &nbsp;%user_pass%&nbsp;%user_email%&nbsp;%blogname%&nbsp;%siteurl% <?php echo $custom_keys; ?>&nbsp; %user_ip%&nbsp;%user_ref%&nbsp;%user_host%&nbsp;%user_agent% </p>
						<textarea name="regplus_msg" id="msg" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $rprSettings['msg']; ?></textarea><br /><label><input type="checkbox" name="regplus_html" id="html" value="1" <?php if ( $rprSettings['html']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML', 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_user_nl2br" id="html" value="1" <?php if ( $rprSettings['user_nl2br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)' , 'regplus'); ?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="login_redirect"><?php _e('Login Redirect URL', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_login_redirect" id="login_redirect" style="width:250px;" value="<?php echo $rprSettings['login_redirect']; ?>" /><small><?php _e('This will redirect the users login after registration.', 'regplus'); ?></small></td>
				</tr>
			</tbody>
		</table>
	</div>
	<h3><?php _e('Customize Admin Notification Email', 'regplus'); ?></h3>
	<table class="form-table"> 
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="disable_admin"><?php _e('Admin Email Notification', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_disable_admin" id="disable_admin" value="1" <?php if ( $rprSettings['disable_admin']) echo 'checked="checked"'; ?> /><?php _e('Disable', 'regplus'); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label><?php _e('Custom Admin Email Notification', 'regplus'); ?></label></th>
				<td><label><input type="checkbox" name="regplus_custom_adminmsg" id="custom_adminmsg" value="1" <?php if ( $rprSettings['custom_adminmsg']) echo 'checked="checked"'; ?> /><?php _e('Enable', 'regplus'); ?></label></td>
			</tr>
		</tbody>
	</table>
	<div id="enabled_adminmsg">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="adminfrom"><?php _e('From Email', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_adminfrom" id="adminfrom" style="width:250px;" value="<?php echo $rprSettings['adminfrom']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="adminfromname"><?php _e('From Name', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_adminfromname" id="adminfromname" style="width:250px;" value="<?php echo $rprSettings['adminfromname']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="adminsubject"><?php _e('Subject', 'regplus'); ?></label></th>
					<td><input type="text" name="regplus_adminsubject" id="adminsubject" style="width:350px;" value="<?php echo $rprSettings['adminsubject']; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="adminmsg"><?php _e('Admin Message', 'regplus'); ?></label></th>
					<td><p><strong><?php _e('Replacement Keys', 'regplus'); ?>:</strong>&nbsp;%user_login% &nbsp;%user_email%&nbsp;%blogname%&nbsp;%siteurl%  <?php echo $custom_keys; ?>&nbsp; %user_ip%&nbsp;%user_ref%&nbsp;%user_host%&nbsp;%user_agent%</p><textarea name="regplus_adminmsg" id="adminmsg" rows="10" cols="25" style="width:80%;height:300px;"><?php echo $rprSettings['adminmsg']; ?></textarea><br /><label><input type="checkbox" name="regplus_adminhtml" id="adminhtml" value="1" <?php if ( $rprSettings['adminhtml']) echo 'checked="checked"'; ?> /><?php _e('Send as HTML' , 'regplus'); ?></label>&nbsp;<label><input type="checkbox" name="regplus_admin_nl2br" id="html" value="1" <?php if ( $rprSettings['admin_nl2br']) echo 'checked="checked"'; ?> /><?php _e('Convert new lines to &lt;br/> tags (HTML only)' , 'regplus'); ?></label></td>
				</tr>
			</tbody>
		</table>
	</div>
	<br />
	<h3><?php _e('Custom CSS for Register & Login Pages', 'regplus'); ?></h3>
	<p><?php _e('CSS Rule Example:', 'regplus'); ?><code>#user_login{ font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><label for="register_css"><?php _e('Custom Register CSS', 'regplus'); ?></label></th>
				<td><textarea name="regplus_register_css" id="register_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $rprSettings['register_css']; ?></textarea></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="login_css"><?php _e('Custom Login CSS', 'regplus'); ?></label></th>
				<td><textarea name="regplus_login_css" id="login_css" rows="20" cols="40" style="width:80%; height:200px;"><?php echo $rprSettings['login_css']; ?></textarea></td>
			</tr>
		</tbody>
	</table>
	<p class="submit"><input name="Submit" value="<?php _e('Save Changes','regplus'); ?>" type="submit" />
	<input name="action" value="reg_plus_update" type="hidden" />
	</form>
</div>
<?php
		}

		#Check Required Fields
		function RegErrors( $errors ) {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			$regplus_custom = get_option('register_plus_custom');
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( $rprSettings['email_exists'] ) {
				if ($errors->errors['email_exists'] ) unset($errors->errors['email_exists']);
			}
			if ( $rprSettings['show_firstname_field'] && in_array('firstname', $rprSettings['required_fields']) ) {
				if ( empty($_POST['firstname']) || $_POST['firstname'] == '' ) {
					$errors->add('empty_firstname', __('<strong>ERROR</strong>: Please enter your First Name.', 'regplus'));
				}
			}
			if ( $rprSettings['show_lastname_field'] && in_array('lastname', $rprSettings['required_fields']) ) {
				if ( empty($_POST['lastname']) || $_POST['lastname'] == '' ) {
					$errors->add('empty_lastname', __('<strong>ERROR</strong>: Please enter your Last Name.', 'regplus'));
				}
			}
			if ( $rprSettings['show_website_field'] && in_array('website', $rprSettings['required_fields']) ) {
				if ( empty($_POST['user_url']) || $_POST['user_url'] == '' ) {
					$errors->add('empty_user_url', __('<strong>ERROR</strong>: Please enter your Website URL.', 'regplus'));
				}
			}
			if ( $rprSettings['show_aim_field'] && in_array('aim', $rprSettings['required_fields']) ) {
				if ( empty($_POST['aim']) || $_POST['aim'] == '' ) {
					$errors->add('empty_aim', __('<strong>ERROR</strong>: Please enter your AIM username.', 'regplus'));
				}
			}
			if ( $rprSettings['show_yahoo_field'] && in_array('yahoo', $rprSettings['required_fields']) ) {
				if ( empty($_POST['yahoo']) || $_POST['yahoo'] == '' ) {
					$errors->add('empty_yahoo', __('<strong>ERROR</strong>: Please enter your Yahoo IM username.', 'regplus'));
				}
			}
			if ( $rprSettings['show_jabber_field'] && in_array('jabber', $rprSettings['required_fields']) ) {
				if ( empty($_POST['jabber']) || $_POST['jabber'] == '' ) {
					$errors->add('empty_jabber', __('<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.', 'regplus'));
				}
			}
			if ( $rprSettings['show_about_field'] && in_array('about', $rprSettings['required_fields']) ) {
				if ( empty($_POST['about']) || $_POST['about'] == '' ) {
					$errors->add('empty_about', __('<strong>ERROR</strong>: Please enter some information About Yourself.', 'regplus'));
				}
			}
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					if ( $v['required'] && $v['reg'] ) {
						$id = $this->LabelId($v['label']);
						if ( empty($_POST[$id]) || $_POST[$id] == '' ) {
							$errors->add('empty_' . $id, __('<strong>ERROR</strong>: Please enter your ' . $v['label'] . '.', 'regplus'));
						}
					}
				}
			}
			if ( $rprSettings['user_set_password'] ) {
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
			if ( $rprSettings['enable_invitation_code'] && $rprSettings['require_invitation_code'] ) {
				if ( empty($_POST['regcode']) || $_POST['regcode'] == '' ) {
					$errors->add('empty_regcode', __('<strong>ERROR</strong>: Please enter the Invitation Code.', 'regplus'));
				} elseif ( !in_array(strtolower($_POST['regcode']), $rprSettings['invitation_code_bank']) ) {
					$errors->add('regcode_mismatch', __('<strong>ERROR</strong>: Your Invitation Code is incorrect.', 'regplus'));
				}
			}
			if ( $rprSettings['show_disclaimer'] ) {
				if ( !$_POST['show_disclaimer'] ) {
					$errors->add('show_disclaimer', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($rprSettings['message_disclaimer_title']) . '.');
				}
			}
			if ( $rprSettings['show_license_agreement'] ) {
				if ( !$_POST['show_license_agreement'] ) {
					$errors->add('show_license_agreement', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($rprSettings['message_license_title']) . '.');
				}
			}
			if ( $rprSettings['show_privacy_policy'] ) {
				if ( !$_POST['show_privacy_policy'] ) {
					$errors->add('show_privacy_policy', __('<strong>ERROR</strong>: Please accept the ', 'regplus') . stripslashes($rprSettings['message_privacy_policy_title']) . '.');
				}
			}
			return $errors;
		}

		function RegMsg( $errors ) {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			session_start();
			if ( $errors->errors['registered'] ) {
				//unset($errors->errors['registered']);
			}
			if ( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] ) $errors->add('registeredit', __('Please check your e-mail and click the verification link to activate your account and complete your registration.'), 'message');
			return $errors;
		}

		#Add Fields to Register Form
		function RegForm() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			$regplus_custom = get_option('register_plus_custom');
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( $rprSettings['show_firstname_field'] ) {
				if ( isset($_GET['firstname']) ) $_POST['firstname'] = $_GET['firstname']; ?>
<p><label><?php _e('First Name:', 'regplus'); ?><br /><input autocomplete="off" name="firstname" id="firstname" size="25" value="<?php echo $_POST['firstname']; ?>" type="text" tabindex="30" /></label></p>
			<?php }
			if ( $rprSettings['show_lastname_field'] ) {
				if ( isset($_GET['lastname']) ) $_POST['lastname'] = $_GET['lastname']; ?>
<p><label><?php _e('Last Name:', 'regplus'); ?><br /><input autocomplete="off" name="lastname" id="lastname" size="25" value="<?php echo $_POST['lastname']; ?>" type="text" tabindex="31" /></label></p>
			<?php }
			if ( $rprSettings['show_website_field'] ) {
				if ( isset($_GET['user_url']) ) $_POST['user_url'] = $_GET['user_url']; ?>
<p><label><?php _e('Website:', 'regplus'); ?><br /><input autocomplete="off" name="user_url" id="user_url" size="25" value="<?php echo $_POST['user_url']; ?>" type="text" tabindex="32" /></label></p>
			<?php }
			if ( $rprSettings['show_aim_field'] ) {
				if ( isset($_GET['aim']) ) $_POST['aim'] = $_GET['aim']; ?>
<p><label><?php _e('AIM:', 'regplus'); ?><br /><input autocomplete="off" name="aim" id="aim" size="25" value="<?php echo $_POST['aim']; ?>" type="text" tabindex="32" /></label></p>
			<?php }
			if ( $rprSettings['show_yahoo_field'] ) {
				if ( isset($_GET['yahoo']) ) $_POST['yahoo'] = $_GET['yahoo']; ?>
<p><label><?php _e('Yahoo IM:', 'regplus'); ?><br /><input autocomplete="off" name="yahoo" id="yahoo" size="25" value="<?php echo $_POST['yahoo']; ?>" type="text" tabindex="33" /></label></p>
			<?php }
			if ( $rprSettings['show_jabber_field'] ) {
				if ( isset($_GET['jabber']) ) $_POST['jabber'] = $_GET['jabber']; ?>
<p><label><?php _e('Jabber / Google Talk:', 'regplus'); ?><br /><input autocomplete="off" name="jabber" id="jabber" size="25" value="<?php echo $_POST['jabber']; ?>" type="text" tabindex="34" /></label></p>
			<?php }
			if ( $rprSettings['show_about_field'] ) {
				if ( isset($_GET['about']) ) $_POST['about'] = $_GET['about']; ?>
<p><label><?php _e('About Yourself:', 'regplus'); ?><br /><textarea autocomplete="off" name="about" id="about" cols="25" rows="5" tabindex="35"><?php echo stripslashes($_POST['about']); ?></textarea></label><small><?php _e('Share a little biographical information to fill out your profile. This may be shown publicly.', 'regplus'); ?></small></p>
			<?php }
			foreach ( $regplus_custom as $k => $v ) {
				if ( $v['reg'] ) {
					$id = $this->LabelId($v['label']);
					if ( isset($_GET[$id]) ) $_POST[$id] = $_GET[$id];
					if ( $v['fieldtype'] == 'text' ) { ?>
<p><label><?php echo $v['label']; ?>: <br /><input autocomplete="off" class="custom_field" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>" size="25" value="<?php echo $_POST[$id]; ?>" type="text" /></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'date' ) { ?>
<p><label><?php echo $v['label']; ?>: <br /><input autocomplete="off" class="custom_field date-pick" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>" size="25" value="<?php echo $_POST[$id]; ?>" type="text" /></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'select' ) {
						$ops = explode(',',$v['extraoptions']);
						$options = '';
						foreach ( $ops as $op ) {
							$options .= '<option value="'.$op.'" ';
							if ( $_POST[$id] == $op ) $options .= 'selected="selected"';
							$options .= '>' . $op . '</option>';
						}
						?>
<p><label><?php echo $v['label']; ?>: <br /><select class="custom_select" tabindex="36" name="<?php echo $id; ?>" id="<?php echo $id; ?>"><?php echo $options; ?></select></label><br /></p>
					<?php } elseif ( $v['fieldtype'] == 'checkbox' ) {
						$ops = explode(',',$v['extraoptions']);
						$check = '';
						foreach ( $ops as $op ) {
							$check .= '<label><input type="checkbox" class="custom_checkbox" tabindex="36" name="'.$id.'[]" id="'.$id.'" ';
							//if ( in_array($op, $_POST[$id])) $check .= 'checked="checked" ';
							$check .= 'value="'.$op.'" /> '.$op.'</label> ';
						}
						?>
<p><label><?php echo $v['label']; ?>:</label><br />
						<?php echo $check . '<br /></p>';
					} elseif ( $v['fieldtype'] == 'radio' ) {
						$ops = explode(',',$v['extraoptions']);
						$radio = '';
						foreach ( $ops as $op ) {
							$radio .= '<label><input type="radio" class="custom_radio" tabindex="36" name="'.$id.'" id="'.$id.'" ';
							//if ( in_array($op, $_POST[$id])) $radio .= 'checked="checked" ';
							$radio .= 'value="'.$op.'" /> '.$op.'</label> ';
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
			if ( $rprSettings['user_set_password'] ) {
				?>
<p>
	<label><?php _e('Password:', 'regplus'); ?><br />
	<input autocomplete="off" name="pass1" id="pass1" size="25" value="<?php echo $_POST['pass1']; ?>" type="password" tabindex="40" /></label><br />
	<label><?php _e('Confirm Password:', 'regplus'); ?><br />
	<input autocomplete="off" name="pass2" id="pass2" size="25" value="<?php echo $_POST['pass2']; ?>" type="password" tabindex="41" /></label>
	<?php if ( $rprSettings['show_password_meter']) { ?><br />
		<span id="pass-strength-result"><?php echo $rprSettings['message_short_password']; ?></span>
		<small><?php _e('Hint: Use upper and lower case characters, numbers and symbols like !"?$%^&amp;(in your password.', 'regplus'); ?></small>
	<?php } ?>
</p>
<?php
			}
			if ( $rprSettings['enable_invitation_code'] ) {
				if ( isset($_GET['regcode']) ) $_POST['regcode'] = $_GET['regcode'];
					?>
<p>
	<label><?php _e('Invitation Code:', 'regplus'); ?><br />
	<input name="regcode" id="regcode" size="25" value="<?php echo $_POST['regcode']; ?>" type="text" tabindex="45" /></label><br />
	<?php if ($rprSettings['require_invitation_code']) { ?>
		<small><?php _e('This website is currently closed to public registrations.  You will need an invitation code to register.', 'regplus'); ?></small>
	<?php } else { ?>
		<small><?php _e('Have an invitation code? Enter it here. (This is not required)', 'regplus'); ?></small>
	<?php } ?>
</p>
<?php
			}
			if ($rprSettings['show_disclaimer'] ) {
				?>
<p>
	<label><?php echo stripslashes($rprSettings['message_disclaimer_title']); ?><br />
	<span id="disclaimer"><?php echo stripslashes($rprSettings['message_disclaimer']); ?></span>
	<input name="disclaimer" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_disclaimer']) echo ' checked="checked"'; ?> /><?php echo $rprSettings['message_disclaimer_agree']; ?></label>
</p>
<?php
			}
			if ( $rprSettings['show_license_agreement'] ) {
				?>
<p>
	<label><?php echo stripslashes($rprSettings['message_license_title']); ?><br />
	<span id="license"><?php echo stripslashes($rprSettings['message_license']); ?></span>
	<input name="license" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_license_agreement']) echo ' checked="checked"'; ?> /><?php echo $rprSettings['message_license_agree']; ?></label>
</p>
<?php
			}
			if ( $rprSettings['show_privacy_policy'] ) {
				?>
<p>
	<label><?php echo stripslashes($rprSettings['message_privacy_policy_title']); ?><br />
	<span id="privacy"><?php echo stripslashes($rprSettings['message_privacy_policy']); ?></span>
	<input name="privacy" value="1" type="checkbox" tabindex="50"<?php if ( $_POST['show_privacy_policy']) echo ' checked="checked"'; ?> /><?php echo $rprSettings['message_privacy_policy_agree']; ?></label>
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			if ( isset($_GET['user_login']) ) $user_login = $_GET['user_login'];
			if ( isset($_GET['user_email']) ) $user_email = $_GET['user_email'];
			if ( isset($_GET['user_url']) ) $user_url = $_GET['user_url'];
			if ( $rprSettings['user_set_password'] ) {
				?>
<script type='text/javascript' src='<?php trailingslashit(get_option('siteurl')); ?>wp-includes/js/jquery/jquery.js?ver=1.2.3'></script>
<script type='text/javascript' src='<?php trailingslashit(get_option('siteurl')); ?>wp-admin/js/common.js?ver=20080318'></script>
<script type='text/javascript' src='<?php trailingslashit(get_option('siteurl')); ?>wp-includes/js/jquery/jquery.color.js?ver=2.0-4561'></script>
<script type='text/javascript'>
/* <![CDATA[ */
	pwsL10n={
		short: "<?php echo $rprSettings['message_short_password']; ?>",
		bad: "<?php echo $rprSettings['message_bad_password']; ?>",
		good: "<?php echo $rprSettings['message_good_password']; ?>",
		strong: "<?php echo $rprSettings['message_strong_password']; ?>"
	}
/* ]]> */
</script>
<script type='text/javascript' src='<?php trailingslashit(get_option('siteurl')); ?>wp-admin/js/password-strength-meter.js?ver=20070405'></script>
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

			$plugin_url = trailingslashit(get_option('siteurl')) . 'wp-content/plugins/' . basename(dirname(__FILE__)) .'/';
			?>
<!-- required plugins -->
<script type="text/javascript" src="<?php echo $plugin_url; ?>datepicker/date.js"></script>
<!--[if IE]><script type="text/javascript" src="<?php echo $plugin_url; ?>datepicker/jquery.bgiframe.js"></script><![endif]-->

<!-- jquery.datePicker.js -->
<script type="text/javascript" src="<?php echo $plugin_url; ?>datepicker/jquery.datePicker.js"></script>
<link href="<?php echo $plugin_url; ?>datepicker/datePicker.css" rel="stylesheet" type="text/css" />
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
Date.firstDayOfWeek = <?php echo $rprSettings['firstday']; ?>;
Date.format = '<?php echo $rprSettings['dateformat']; ?>';
jQuery(function() {
	jQuery('.date-pick').datePicker({
		clickInput:true,
		startDate:'<?php echo $rprSettings['startdate']; ?>',
		year:'<?php echo $rprSettings['calyear']; ?>',
		month:'<?php if ( $rprSettings['calmonth'] != 'cur') echo $rprSettings['calmonth']; else echo date('n')-1; ?>'
	})
});
</script>
<style type="text/css">
a.dp-choose-date { float: left; width: 16px; height: 16px; padding: 0; margin: 5px 3px 0; display: block; text-indent: -2000px; overflow: hidden; background: url(<?php echo $plugin_url; ?>datepicker/calendar.png) no-repeat; }
a.dp-choose-date.dp-disabled { background-position: 0 -20px; cursor: default; } /* makes the input field shorter once the date picker code * has run (to allow space for the calendar icon */
input.dp-applied { width: 140px; float: left; }
#pass1, #pass2, #regcode, #firstname, #lastname, #user_url, #aim, #yahoo, #jabber, #about, .custom_field {
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
			$regplus_custom = get_option('register_plus_custom');
			$custom = array();
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					if ( $v['required'] && $v['reg'] ) {
						$custom[] = ', #' . $this->LabelId($v['label']);
					}
				}
			}
			//WTF does this line accomplish?
			if ( $rprSettings['required_fields'][0] ) $profile_req = ', #' . implode(', #', $rprSettings['required_fields']);
			if ( $custom[0] ) $profile_req .= implode('', $custom);
?>
#user_login, #user_email, #pass1, #pass2 <?php echo $profile_req; ?> {
	<?php echo $rprSettings['required_fields_style']; ?>
}
<?php if ( strlen($rprSettings['message_disclaimer'] ) > 525) { ?>
#disclaimer {
	height: 200px;
	overflow:scroll;
}
<?php } ?>
<?php if ( strlen($rprSettings['message_license'] ) > 525) { ?>
#license {
	height: 200px;
	overflow:scroll;
}
<?php } ?>
<?php if ( strlen($rprSettings['message_privacy_policy']) > 525 ) { ?>
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			if ( ($rprSettings['admin_verify'] || $rprSettings['email_verify']) && $_GET['checkemail'] == 'registered' ) {
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			if ( $rprSettings['logo'] ) { 
				$logo = str_replace(trailingslashit(get_option('siteurl')), ABSPATH, $rprSettings['logo']);
				list($width, $height, $type, $attr) = getimagesize($logo);
				if ( $_GET['action'] != 'register' ) :
				?>
<script type='text/javascript' src='<?php trailingslashit(get_option('siteurl')); ?>wp-includes/js/jquery/jquery.js?ver=1.2.3'></script>
<?php
				endif; ?>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#login h1 a').attr('href', '<?php echo get_option('home'); ?>');
		jQuery('#login h1 a').attr('title', '<?php echo get_option('blogname') . ' - ' . get_option('blogdescription'); ?>');
	});
</script>
<style type="text/css">
#login h1 a {
	background-image: url(<?php echo $rprSettings['logo']; ?>);
	background-position:center top;
	width: <?php echo $width; ?>px;
	min-width:292px;
	height: <?php echo $height; ?>px;
}
<?php 
			if ( $rprSettings['register_css'] &&  $_GET['action'] == 'register' ) echo $rprSettings['register_css'];
			elseif ( $rprSettings['login_css'] ) echo $rprSettings['login_css'];
			?>
</style>
<?php
			}
		}

		function Add2Profile() {
			global $user_ID;
			get_currentuserinfo();
			if ( $_GET['user_id'] ) $user_ID = $_GET['user_id'];
			$regplus_custom = get_option('register_plus_custom');
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( count($regplus_custom) > 0 ) {
				$top = '<h3>' . __('Additional Information', 'regplus') . '</h3><table class="form-table"><tbody>';
				$bottom = '</tbody></table>';
			}
			echo $top;
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					if ( $v['profile'] ) {
						$id = $this->LabelId($v['label']);
						$value = get_user_meta($user_ID, $id, false);
						$extraops = explode(',', $v['extraoptions']);
						switch ( $v['fieldtype'] ) {
							case "text":
								$outfield = '<input type="text" name="' . $id . '" id="' . $id . '" value="' . $value . '"  />';
								break;
							case "hidden":
								$outfield = '<input type="text" disabled="disabled" name="' . $id . '" id="' . $id . '" value="' . $value . '"  />';
								break;
							case "select":
								$outfield = '<select name="' . $id . '" id="' . $id . '">';
								foreach ( $extraops as $op ) {
									$outfield .= '<option value="' . $op . '"';
									if ( $value == $op ) $outfield .= ' selected="selected"';
									$outfield .= '>' . $op . '</option>';
								}
								$outfield .= '</select>';
								break;
							case "textarea":
								$outfield = '<textarea name="' . $id . '" id="' . $id . '" cols="25" rows="10">' . stripslashes($value) . '</textarea>';
								break;
							case "checkbox":
								$outfield = '';
								$valarr = explode(', ', $value);
								foreach ( $extraops as $op ) {
									$outfield .= '<label><input type="checkbox" name="' . $id . '[]" value="' . $op . '"';
									if ( in_array($op, $valarr) ) $outfield .= ' checked="checked"';
									$outfield .= ' /> ' . $op . '</label>&nbsp;';
								}
								break;
							case "radio":
								$outfield = '';
								foreach ( $extraops as $op ) {
									$outfield .= '<label><input type="radio" name="' . $id . '" value="' . $op . '"';
									if ( $value == $op ) $outfield .= ' checked="checked"';
									$outfield .= ' /> ' . $op . '</label>&nbsp;';
								}
								break;
						}
						?>
<tr>
	<th><label for="<?php echo $id; ?>"><?php echo $v['label']; ?>:</label></th>
	<td><?php echo $outfield; ?></td>
</tr>
<?php
					}
				}
			}
			echo $bottom;
		}

		function SaveProfile() {
			global $wpdb, $user_ID;
			get_currentuserinfo();
			//v.3.5.1 code
			//if ( $_GET['user_id'] ) $user_ID = $_GET['user_id'];
			//code recommended by bitkahuna
			if( !empty($_REQUEST['user_id']) ) $user_ID = $_REQUEST['user_id'];
			$regplus_custom = get_option('register_plus_custom');
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			if ( $rprSettings['admin_verify'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">' . __('Your account will be reviewed by an administrator and you will be notified when it is activated.', 'regplus') . '</p>';
			} elseif ( $rprSettings['email_verify'] && isset($_GET['checkemail']) ) {
				echo '<p style="text-align:center;">' . __('Please activate your account using the verification link sent to your email address.', 'regplus') . '</p>';
			}
			if ( $rprSettings['email_verify'] && isset($_GET['regplus_verification']) ) {
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
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			return $rprSettings['adminfrom'];
		}

		function userfrom() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			return $rprSettings['from'];
		}

		function adminfromname() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			return $rprSettings['adminfromname'];
		}

		function userfromname() {
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			return $rprSettings['fromname'];
		}

		function DeleteInvalidUsers() {
			global $wpdb;
			$rprSettings = get_option('plugin_register_plus_redux_settings');
			$grace = $rprSettings['email_delete_grace'];
			$unverified = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key='email_verify_date'");
			$grace_date = date('Ymd', strtotime("-7 days"));
			if ( $unverified ) {
				foreach ( $unverified as $bad ) {
					if ( $grace_date > $bad->meta_value ) {
						include_once(ABSPATH . 'wp-admin/includes/user.php');
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
		$rprSettings = get_option('plugin_register_plus_redux_settings');
		$regplus_custom = get_option('register_plus_custom');
		$ref = explode('?', $_SERVER['HTTP_REFERER']);
		$ref = $ref[0];
		$admin = trailingslashit(get_option('siteurl')) . 'wp-admin/users.php';
		if ( !is_array($regplus_custom) ) $regplus_custom = array();
		if ( $rprSettings['user_set_password'] && $_POST['user_pw'] ) $plaintext_pass = $wpdb->prepare($_POST['user_pw']);
		elseif ( $ref == $admin && $_POST['pass1'] == $_POST['pass2'] ) $plaintext_pass = $wpdb->prepare($_POST['pass1']);
		else $plaintext_pass = $registerPlusRedux->RandomString(6);
		if ( $rprSettings['show_firstname_field'] && $_POST['firstname'] ) update_user_meta($user_id, 'first_name', $wpdb->prepare($_POST['firstname']));
		if ( $rprSettings['show_lastname_field'] && $_POST['lastname'] ) update_user_meta($user_id, 'last_name', $wpdb->prepare($_POST['lastname']));
		//v.3.5.1 code
		//if ( $rprSettings['show_website_field'] && $_POST['user_url'] ) update_user_meta($user_id, 'user_url', $wpdb->prepare($_POST['user_url']));
		if ( $rprSettings['show_website_field'] && $_POST['user_url'] ) {
			$url = esc_url_raw( $_POST['user_url'] );
			$user->user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is', $url) ? $url : 'http://'.$url;
			wp_update_user(array('ID' => $user_id, 'user_url' => $wpdb->prepare($url)));
		}
		if ( $rprSettings['show_aim_field'] && $_POST['aim'] ) update_user_meta($user_id, 'aim', $wpdb->prepare($_POST['aim']));
		if ( $rprSettings['show_yahoo_field'] && $_POST['yahoo'] ) update_user_meta($user_id, 'yim', $wpdb->prepare($_POST['yahoo']));
		if ( $rprSettings['show_jabber_field'] && $_POST['jabber'] ) update_user_meta($user_id, 'jabber', $wpdb->prepare($_POST['jabber']));
		if ( $rprSettings['show_about_field'] && $_POST['about'] ) update_user_meta($user_id, 'description', $wpdb->prepare($_POST['about']));
		if ( $rprSettings['enable_invitation_code'] && $_POST['regcode'] ) update_user_meta($user_id, 'invite_code', $wpdb->prepare($_POST['regcode']));
		if ( $ref != $admin && $rprSettings['admin_verify'] ) {
			update_user_meta($user_id, 'admin_verify_user', $user->user_login);
			$temp_login = 'unverified__' . $registerPlusRedux->RandomString(7);
			$notice = __('Your account requires activation by an administrator before you will be able to login.', 'regplus') . "\r\n";
		} elseif ( $ref != $admin && $rprSettings['email_verify'] ) {
			$code = $registerPlusRedux->RandomString(25);
			update_user_meta($user_id, 'email_verify', $code);
			update_user_meta($user_id, 'email_verify_date', date('Ymd'));
			update_user_meta($user_id, 'email_verify_user', $user->user_login);
			$email_code = '?regplus_verification=' . $code;
			$prelink = __('Verification URL: ', 'regplus');
			$notice = __('Please use the link above to verify and activate your account', 'regplus') . "\r\n";
			$temp_login = 'unverified__' . $registerPlusRedux->RandomString(7);
		}
		if ( !empty($regplus_custom) ) {
			foreach ( $regplus_custom as $k => $v ) {
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
		if ( !$rprSettings['custom_adminmsg'] && !$rprSettings['disable_admin']) {
			$message = sprintf(__('New user Register on your blog %s:', 'regplus'), get_option('blogname')) . "\r\n\r\n";
			$message .= sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n\r\n";
			$message .= sprintf(__('E-mail: %s', 'regplus'), $user_email) . "\r\n";
			@wp_mail(get_option('admin_email'), sprintf(__('[%s] New User Register', 'regplus'), get_option('blogname')), $message);
		} elseif ( !$rprSettings['disable_admin'] ) {
			if ( $rprSettings['adminhtml'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $rprSettings['adminfrom'] . "\r\n" . 'Reply-To: ' . $rprSettings['adminfrom'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'adminfrom'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'adminfromname'));
			$subject = $rprSettings['adminsubject'];
			$message = str_replace('%user_login%', $user_login, $rprSettings['adminmsg']);
			$message = str_replace('%user_email%', $user_email, $message);
			$message = str_replace('%blogname%', get_option('blogname'), $message);
			$message = str_replace('%user_ip%', $_SERVER['REMOTE_ADDR'], $message);
			$message = str_replace('%user_host%', gethostbyaddr($_SERVER['REMOTE_ADDR']), $message);
			$message = str_replace('%user_ref%', $_SERVER['HTTP_REFERER'], $message);
			$message = str_replace('%user_agent%', $_SERVER['HTTP_USER_AGENT'], $message);
			if ( $rprSettings['show_firstname_field'] ) $message = str_replace('%firstname%', $_POST['firstname'], $message);
			if ( $rprSettings['show_lastname_field'] ) $message = str_replace('%lastname%', $_POST['lastname'], $message);
			if ( $rprSettings['show_website_field'] ) $message = str_replace('%user_url%', $_POST['user_url'], $message);
			if ( $rprSettings['show_aim_field'] ) $message = str_replace('%aim%', $_POST['aim'], $message);
			if ( $rprSettings['show_yahoo_field'] ) $message = str_replace('%yahoo%', $_POST['yahoo'], $message);
			if ( $rprSettings['show_jabber_field'] ) $message = str_replace('%jabber%', $_POST['jabber'], $message);
			if ( $rprSettings['show_about_field'] ) $message = str_replace('%about%', $_POST['about'], $message);
			if ( $rprSettings['enable_invitation_code'] ) $message = str_replace('%invitecode%', $_POST['enable_invitation_code'], $message);
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					$meta = $registerPlusRedux->LabelId($v['label']);
					$value = get_user_meta($user_id, $meta, false);
					$message = str_replace('%'.$meta.'%', $value, $message);
				}
			}
			$siteurl = get_option('siteurl');
			$message = str_replace('%siteurl%', $siteurl, $message);
			if ( $rprSettings['adminhtml'] && $rprSettings['admin_nl2br'] )
				$message = nl2br($message);
			wp_mail(get_option('admin_email'), $subject, $message, $headers); 
		}
		if ( empty($plaintext_pass) )
			return;
		if ( !$rprSettings['custom_msg'] ) {
			$message = sprintf(__('Username: %s', 'regplus'), $user_login) . "\r\n";
			$message .= sprintf(__('Password: %s', 'regplus'), $plaintext_pass) . "\r\n";
			//$message .= get_option('siteurl') . "/wp-login.php";
			$message .= $prelink . get_option('siteurl') . "/wp-login.php" . $email_code . "\r\n"; 
			$message .= $notice; 
			wp_mail($user_email, sprintf(__('[%s] Your username and password', 'regplus'), get_option('blogname')), $message);
		} else {
			if ( $rprSettings['html'] ) {
				$headers = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			}
			//$headers .= 'From: ' . $rprSettings['from'] . "\r\n" . 'Reply-To: ' . $rprSettings['from'] . "\r\n";
			add_filter('wp_mail_from', array($registerPlusRedux, 'userfrom'));
			add_filter('wp_mail_from_name', array($registerPlusRedux, 'userfromname'));
			$subject = $rprSettings['subject'];
			$message = str_replace('%user_pass%', $plaintext_pass, $rprSettings['msg']);
			$message = str_replace('%user_login%', $user_login, $message);
			$message = str_replace('%user_email%', $user_email, $message);
			$message = str_replace('%blogname%', get_option('blogname'), $message);
			$message = str_replace('%user_ip%', $_SERVER['REMOTE_ADDR'], $message);
			$message = str_replace('%user_host%', gethostbyaddr($_SERVER['REMOTE_ADDR']), $message);
			$message = str_replace('%user_ref%', $_SERVER['HTTP_REFERER'], $message);
			$message = str_replace('%user_agent%', $_SERVER['HTTP_USER_AGENT'], $message);
			if ( $rprSettings['show_firstname_field'] ) $message = str_replace('%firstname%', $_POST['firstname'], $message);
			if ( $rprSettings['show_lastname_field'] ) $message = str_replace('%lastname%', $_POST['lastname'], $message);
			if ( $rprSettings['show_website_field'] ) $message = str_replace('%user_url%', $_POST['user_url'], $message);
			if ( $rprSettings['show_aim_field'] ) $message = str_replace('%aim%', $_POST['aim'], $message);
			if ( $rprSettings['show_yahoo_field'] ) $message = str_replace('%yahoo%', $_POST['yahoo'], $message);
			if ( $rprSettings['show_jabber_field'] ) $message = str_replace('%jabber%', $_POST['jabber'], $message);
			if ( $rprSettings['show_about_field'] ) $message = str_replace('%about%', $_POST['about'], $message);
			if ( $rprSettings['enable_invitation_code'] ) $message = str_replace('%invitecode%', $_POST['enable_invitation_code'], $message);
			if ( !is_array($regplus_custom) ) $regplus_custom = array();
			if ( !empty($regplus_custom) ) {
				foreach ( $regplus_custom as $k => $v ) {
					$meta = $registerPlusRedux->LabelId($v['label']);
					$value = get_user_meta($user_id, $meta, false);
					$message = str_replace('%'.$meta.'%', $value, $message);
				}
			}
			$redirect = 'redirect_to=' . $rprSettings['login_redirect'];
			if ( $rprSettings['email_verify'] )
				$siteurl = get_option('siteurl') . "/wp-login.php" . $email_code . '&' . $redirect;
			else
				$siteurl = get_option('siteurl') . "/wp-login.php?" . $redirect;
			$message = str_replace('%siteurl%', $siteurl, $message);
			if ( $rprSettings['html'] && $rprSettings['user_nl2br'] )
				$message = nl2br($message);
			wp_mail($user_email, $subject, $message, $headers); 
		}
		if ( $ref != $admin && ($rprSettings['email_verify'] || $rprSettings['admin_verify']) ) 
			//v3.5.1 code
			//$temp_user=$wpdb->query("UPDATE $wpdb->users SET user_login='$temp_login' WHERE ID='$user_id'"); 
			//trying to depreciate use of $wpdb->query
			wp_update_user(array('ID' => $user_id, 'user_login' => $wpdb->prepare($temp_login)));
	}
endif;
 ?>