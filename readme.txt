=== Register Plus Redux ===
Contributors: skullbit, radiok
Donate link: http://radiok.info/donate/
Tags: registration, register, plus, redux, password, invitation, code, email, verification, disclaimer, license, agreement, privacy, policy, logo, moderation, user
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 3.6.17

Enhances the user registration process with complete customization and additional administration options.

== Description ==

Register Plus Redux enables the user registration process to be customized in any way, big or small.  Is there another field you want users to fill out when registering?  Do you want to change the message your users receive after they register?  Do you want users to have to verify their email address is legitimate?  Do you want to queue up new users to be approved or denied by an administrator?  Register Plus Redux can do all that and more.

Enhancements to registration include:

* Customized registration page, including your own logo, disclaimer, license agreement, or privacy policy

* User-entered password (with password strength meter)

* Added profile fields

* Additional required fields for registration

* Invitation code system (with dashboard widget to track invites)

* User-defined fields

* Email verification of new users

* Administration verification of new users

* Customized new user message

* Customized administrator message

Also includes fixes for known Register Plus bugs. 

Register Plus Redux was forked from Register Plus, developed by skullbit, which was abandoned in 2008.

== Installation ==

1. Upload the 'register-plus-redux' directory to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Manage settings from Register Plus Redux page

== Frequently Asked Questions ==

= How is Register Plus Redux related to Register Plus? =
Register Plus was abandoned by skullbit sometime after September, 2008 following the release of Register Plus 3.5.1.  As of September, 2009 skullbit's website was undergoing maintence.  Several bugs have been reported to the Register Plus plugin forum since that time, to resolve these bugs and continue development radiok forked the project.

= Didn't Register Plus have CAPTCHA? =
Register Plus offered two different CAPTCHA methods, a simple random CAPTCHA and reCAPTCHA.  The simple one randomly created a 5-character sequence on a background image with two random lines drawn across the image, this CAPTCHA would be very easy for any OCR program to decipher as the characters were not modified in anyway and contrast was high.  reCAPTCHA is a great idea, but there is another plugin, [WP-reCAPTCHA](http://wordpress.org/extend/plugins/wp-recaptcha/) endorsed by the reCAPTCHA developers that can be used to add reCAPTCHA to the registration page.  I also endorse the use of that plugin for that purpose.

= Didn't Register Plus have a feature to allow duplicate e-mail addresses? =
Register Plus did have a feature that allowed multiple users to register with the same e-mail address.  I'm not sure when that stopped working for Register Plus, but I can assure you, that method doesn't work in WordPress 3.0 and will not work in any future revision.  Register Plus' method was pretty simple, if the email_exists error is thrown, unthrow it.  Well, that works, to a degree, but once WordPress actually builds the user it chokes up and unpleasant things happen, in my experience.  I'll leave this feature to brigher minds then my own to fix.

== Screenshots ==

1. A Modified Registration Page
2. Register Plus Settings
3. Invitation Tracking Dashboard Widget
4. Unverified User Management

== Worklog ==
FEATURE REQUEST: Registration widget, need to look into whether someone else took care of that, and if so how Redux interacts with that <http://wordpress.org/support/topic/register-plus>

FEATURE REQUEST: Invitation code link to

FEATURE REQUEST: Allow reorder of custom fields <http://wordpress.org/support/topic/plugin-pie-register-adding-new-fields-different-order>

FEATURE REQUEST: After registering, redirect to previous page <http://wordpress.org/support/topic/pie-register-and-redirect-to>

FEATURE REQUEST: BuddyPress compatability

TODO: Migrate settings from Register Plus?

TODO: User-new.php is not modified with the added fields, does not notice if username is in queue

TODO: Add localization back in

TODO: Add uninstall function

TODO: Make Redux compatible with Wordpress MU (wp-signup.php)

TODO: Fix datepicker

TODO: Nickname options

TODO: Would like to be able to upload the file a little bit nicer, almost like a form within a form

TODO: jQuery summarize user message status

TODO: Restore defaults buttons

== Changelog ==
= 3.6.17 =
October 11, 2010 by radiok

* Added buttons back to Unverified Users page
* Added ability to Edit or Delete users individually from Unverified Users page
* Added option to enforce minimum password length
* Added option to enforce case sensitive invitation codes
* Added new custom field type, URL Field, this field is sanitized as a URL, as requested by Shikant Joshi <http://radiok.info/blog/administration-redux/>
* Added invitation_code to User Profile page, as requested by janman for Pie Register <http://wordpress.org/support/topic/plugin-pie-register-invitation-code-in-user-profile-page>
* Fixed asterisks showing up on all predefined fields, not just required ones, as reported by pixelprophet <http://wordpress.org/support/topic/plugin-register-plus-redux-email-conflicts-with-another-plugin>
* Fixed loophole in Lost Password that would send an unverified user their temporary user login and allow them access using that login, as reported by AzzePis <http://wordpress.org/support/topic/plugin-register-plus-redux-user-can-register-without-confirmation-of-his-account>

= 3.6.16 =
October 9, 2010 by radiok

* Check subject for keywords, as mentioned by Shikant Joshi <http://wordpress.org/support/topic/plugin-register-plus-redux-call_user_func_array-error>
* Changed the order of usernames in Unverified username page

= 3.6.15 =
October 8, 2010 by radiok

* Fixed a little bug in custom admin messages having no from name or from email address.

= 3.6.14 =
October 8, 2010 by radiok

* Fixed issue with %user_password%, as reported by erbuc, and the.gamer <http://wordpress.org/support/topic/plugin-register-plus-redux-no-text-in-the-user-notification-email>
* Made verification message customizable, as suggested by Shikant Joshi <http://radiok.info/blog/administration-redux/>
* Added several options regarding when and when not to send messages, as discussed with Shikant Joshi <http://radiok.info/blog/administration-redux/>
* Added option to add asterisks to required fields, as suggested by pixelprophet <http://wordpress.org/support/topic/plugin-register-plus-redux-email-conflicts-with-another-plugin>
* Fixed issues with slashes in fields due to the way data is stored in MySQL, added stripslashes to applicable text fields, as reported by pixelprophet <http://wordpress.org/support/topic/plugin-register-plus-redux-email-conflicts-with-another-plugin>
* Added option to double check email addresses, as requested by MacItaly <http://wordpress.org/support/topic/plugin-register-plus-redux-double-check-email-address>
* Added %stored_user_login% keyword for messages, as discussed with richardmtl <http://wordpress.org/support/topic/plugin-register-plus-redux-call_user_func_array-error>

= 3.6.13 =
October 6, 2010 by radiok

* Fixed issue with custom user messages going out blank, as reported by kspec1212 <http://wordpress.org/support/topic/plugin-register-plus-redux-no-text-in-the-user-notification-email>
* Fixed issue with admin messages not going out, as reported by saury316 <http://wordpress.org/support/topic/plugin-register-plus-redux-admin-verification-issues>

= 3.6.12 =
October 5, 2010 by radiok

* Rewrote User Administration
* Fixed DeleteExpiredUsers
* Changed date/time format of email_verification_sent
* Added check to not allow users to register with a username already in queue to be authorized
* Added jQuery to disable invalid settings
* Added some variable checks to prevent undefined index warnings, I added a ton, but I'd need even more to eliminate all notices, as reported by overclockwork <http://wordpress.org/support/topic/plugin-register-plus-redux-settings-cleared-when-saving>
* Fixed bug with custom fields and CSS, was not appending to list of fields for CSS, as reported by saury316 <http://wordpress.org/support/topic/plugin-register-plus-redux-logo-and-other-issues>

= 3.6.11 =
September 30, 2010 by radiok

* Found errant show_about_field
* Fixed bug with replaceKeywords as reported by Angelo Dicerni <http://radiok.info/blog/the-ethos-of-register-plus-redux/>
* Started working on adding localization back in

= 3.6.10 =
September 29, 2010 by radiok

* Reduced CSS written to wp-login header
* Rewrote all CSS written to wp-login header, completely theme-able now
* Fixed bug with checkbox type not be available for custom fields
* Fixed bug with select type custom fields, was using already in use variable name, as reported by shrikantjoshi <http://wordpress.org/support/topic/plugin-register-plus-redux-new-fields-problem>
* Fixed wp_delete_user as reported by saury316 <http://wordpress.org/support/topic/plugin-register-plus-redux-error-at-user-deletion>

= 3.6.9 =
September 28, 2010 by radiok

* Rewrote nearly every echo statement to be enclosed in quotations
* Rewrote function to purge unverified users exceeding grace period
* Rewrote code for password strength indicator, resolves issue reported by iq9 on Register Plus forum <http://wordpress.org/support/topic/plugin-register-plus-couple-bugs>
* Changed default user and admin messages to match WordPress defaults
* Renamed some of the replacement keys to match their true nature or name
* Renamed several variables
* Changed wp_update_user to $wpdb->query for updating user_login
* Removed function to create random string, use wp_generate_password instead
* Reogranized wp_new_user_notification more logically

= 3.6.8 =
September 25, 2010 by radiok

* Fixed custom logo feature not persisting as reported by saury316, and added feature to supply URL to custom logo 
<http://wordpress.org/support/topic/plugin-register-plus-redux-logo-and-other-issues> and <http://wordpress.org/support/topic/plugin-register-plus-custom-logo-help>
* Disabled Allow Duplicate Email Addresses, I'll have to figure out how to work that one out

= 3.6.7 =
September 24, 2010 by radiok

* Fixed custom logo feature
* Update registration page HTML to better match Wordpress 3.0.1
* Changed add/remove buttons on settings page to not be links, no more jumping around the page
* Invitation codes are no longer stored in lowercase, making way for option to enforce case sensitivity

= 3.6.6 =
September 24, 2010 by radiok

* Introduce hooks for WPMU
* Cleaned up wp_new_user_notification
* Fixed custom fields, tested, tested, and retested text fields, more testing due for other field types

= 3.6.5 =
September 22, 2010 by radiok

* Added preview registration page buttons
* Fixed bug with saving custom fields from profile
* Fixed bug with saving settings as reported by mrpatulski, array check was missing <http://wordpress.org/support/topic/plugin-register-plus-redux-getting-fatal-error-when-activating>

= 3.6.4 =
September 21, 2010 by radiok

* Fixed dumb bug with get_user_meta returning arrays

= 3.6.3 =
September 21, 2010 by radiok

* Completed renaming of settings (hopefully)
* More redesign of settings page
* Rewrote all jQuery on settings page
* Fixed bug found by me.prosenjeet, this was due to some changes made to the jQuery previously used <http://wordpress.org/support/topic/plugin-register-plus-redux-new-fields-problem>
* Fixed bug found by craigbic, this was due to incomplete renaming of settings <http://wordpress.org/support/topic/plugin-register-plus-redux-form-cannot-accept-license-or-privacy-policy>

= 3.6.2 =
September 16, 2010 by radiok

* Fixed bug found by seanchk, shrikantjoshi, and ljmac, this was due to incomplete renaming of settings <http://wordpress.org/support/topic/plugin-register-plus-redux-settings-cleared-when-saving>
* Fixed jQuery datePicker as specified by DanoNH <http://wordpress.org/support/topic/register-plus-is-adding-s-to-all-quote-marks-in-registration-email>
* Redesigned settings page

= 3.6.1 =
September 13, 2010 by radiok

* Fixed two bugs found by Gene53 and markwadds, both typos <http://wordpress.org/support/topic/plugin-register-plus-redux-fatal-error>
* More renaming of settings

= 3.6 =
September 13, 2010 by radiok

* Cleaned up all code, spacing, tabs, formatting, etc.
* Updated stylesheet to match WordPress 3.0.1
* Removed Simple CAPTCHA and reCAPTCHA, the Simple CAPTCHA was easy to break two years ago, now it's a joke, BlaenkDenum has a very active reCAPTCHA plugin that can be used for registration, among other things <http://wordpress.org/extend/plugins/wp-recaptcha/>
* Rewrote UploadLogo as specified by nschmede <http://wordpress.org/support/topic/plugin-register-plus-register-plus-custom-logo-problems>
* Fixed SaveProfile as specified by bitkahuna <http://wordpress.org/support/topic/plugin-register-plus-does-registration-plus-work>
* Fixed Invitation Code Tracking dashboard widget as specified by robert.lang <http://wordpress.org/support/topic/plugin-register-plus-error-message-on-dashboard-panel-display>
* Fixed bug in Profile regarding website, user_url was being stored in wp_usermeta, when it should have been in wp_users, StrangeAttractor's code was most beneficial, but I made several other improvements along the way <http://wordpress.org/support/topic/plugin-register-plus-cant-update-website-in-user-profile>
* Added Settings action link to Plugins page
* Reduced use of $wpdb variable in favor of WordPress' helper functions
* Started renaming settings

= 3.5.1 =
July 29, 2008 by Skullbit

* Added Logo link to login page

= 3.5 =
July 29, 2008 by Skullbit

* Changed Logo to link to site home page instead of wordpress.org and set the Logo title to "blogname - blogdescription"
* Added Date Field ability for User Defined Fields - calendar pop-up on click with customization abilities

= 3.4.1 =
July 28, 2008 by Skullbit

* Fixed admin verification error

= 3.4 =
July 25, 2008 by Skullbit

* Fixed verification email sending errors
* Fixed Custom Fields Extra Options duplications
* Added Custom CSS option for login and register pages

= 3.3 =
July 23, 2008 by Skullbit

* Updated conflict warning error to only appear on the RegPlus options page only.

= 3.2 =
July 22, 2008 by Skullbit

* Fixed Custom Field Checkbox saving issue
* Additional field types available for Custom Fields.
* Password Meter is now optional and text is editable within options page

= 3.1 =
July 8, 2008 by Skullbit

* Added Logo Removal Option
* Updated Email Validation text after registering
* Added User Sub-Panel for resending validation emails and automatic admin validation
* Added User Moderation Ability - new registrations must be approved by admin before becoming active.
* Fixed bad version control code

= 3.0.2 =
June 23, 2008 by Skullbit

* Updated Email notifications to use a filter to replace the From Name and Email address

= 3.0.1 =
June 19, 2008 by Skullbit

* Added more localization files
* Added doccumentation for auto-complete queries
* Fixed Admin notification email to now actually really go to the administrator

= 3.0 =
June 18, 2008 by Skullbit

* Added localization to password strength text
* Added stripslashes to missing areas
* Added Login Redirect option for registration email url
* Added Ability to populate registration fields using URL GET statements
* Added Simple CAPTCHA Session check and warning if not enabled
* Added ability to email all user data in notification emails

= 2.9 =
June 10, 2008 by Skullbit

* Fixed foreach error for custom invite codes
* Custom logos can now be any size
* Login fields are now hidden after registration if email verification is enabled.

= 2.8 =
June 9, 2008 by Skullbit

* Fixed Fatal Error on Options Page

= 2.7 =
June 8, 2008 by Skullbit

* Added full customization option to User Registration Email and Admin Email.
* Added ability to disable Admin notification email.
* Added style feature for required fields
* Added Custom Logo upload for replacing WP Logo on register & login pages

= 2.6 =
May 15, 2008 by Skullbit

* Fixed error on ranpass function.

= 2.5 =
May 14, 2008 by Skullbit

* Fixed registration password email to work when user set password is disabled

= 2.4 =
May 13, 2008 by Skullbit

* Fixed localization issue
* Added License Agreement & Privacy Policy plus user defined titles and agree text for these and the Disclaimer
* Fixed Javascript error in IE

= 2.3 =
May 12, 2008 by Skullbit

* Added reCAPTCHA support
* Fixed PHP short-code issue
* Added option to not require Invite Code but still show it on registration page
* Added ability to customize the registration email's From address, Subject and add your own message to the email body.

= 2.2 =
April 27, 2008 by Skullbit

* Fixed About Us Slashes from showing with apostrophes
* Modified the Captcha code to hopefully fix some compatibility issues

= 2.1 =
April 26, 2008 by Skullbit

* Fixed Admin Registation Password issue
* Added Dashboard Widget for showing invitation code tracking
* Added Email Verification for ensuring legitimate addresses are registered.  
* Unvalidated registrations are unable to login and are deleted after a set grace period

= 2.0 =
April 20, 2008 by Skullbit

* Added Profile Fields
* Added Multiple Invitation Codes
* Added Custom User Defined Fields with Profile integration
* Added ability to ignore duplicate email registrations

= 1.2 =
April 13, 2008 by Skullbit

* Altered Options saving and retrievals for less database interactions
* Added Disclaimer Feature
* Allowed register fields to retain values on submission if there is an error.

= 1.1 =
April 10 2008 by Skullbit

* Fixed Invitation Code from displaying when disabled.
* Added Captcha Feature

== Upgrade Notice ==

= 3.6 =
First stable release by radiok with bugfixes to issues found in 3.5.1