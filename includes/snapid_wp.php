<?php

class WP_SnapID
{
	private $SnapID;
	private $CustomerID;
	private $ApplicationID;
	private $ApplicationSubID;

	public $OneStepEnabled;
	public $TwoStepEnabled;

	public $Helper;

	protected $options = 'snapid_options';

	protected $defaults = array(
		'one_step_enabled' => false,
		'two_step_enabled' => false,
		'customer_id' => '',
		'app_id' => '',
		'app_sub_id' => '',
		'terms_and_conditions' => false,
		'version_saved' => '',
	);

	/**
	 * Construct
	 */
	public function __construct( $basename, $helper, $version )
	{
		$this->basename = $basename;
		$this->Helper = $helper;
		$this->Version = $version;

		// Only run SnapID on login and admin pages.
		if( is_admin() || $this->Helper->is_login_page() ) {
		   add_action( 'init', array( $this, 'setup' ) );
	   }
   }

	/**
	 * Gets things started
	 * @return void
	 */
	public function setup()
	{
		session_start();

		// Add roles to the defaults
		$this->add_role_defaults();

		$options = $this->Helper->get_options( $this->options );
		$options = wp_parse_args( $options, $this->defaults );

		$this->OneStepEnabled = $options['one_step_enabled'];
		$this->TwoStepEnabled = $options['two_step_enabled'];
		$this->CustomerID = $options['customer_id'];
		$this->ApplicationID = $options['app_id'];
		$this->ApplicationSubID = $options['app_sub_id'];
		$this->TermsAndConditions = $options['terms_and_conditions'];
		$this->VersionSaved = $options['version_saved'];


		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'snapid_settings', array( $this, 'snapid_settings' ) );
		add_action( 'snapid_uninstall', array( $this, 'snapid_uninstall' ) );

		$this->SnapID = new SnapID( $this->CustomerID, $this->ApplicationID, $this->ApplicationSubID );

		// Let's check that the creditials entered are valid
		$this->check = $this->SnapID->perform_join( '', '', '', '', '' );

		if( $this->check->errordescr == '' && $this->TermsAndConditions && ( $this->OneStepEnabled || $this->TwoStepEnabled ) ) {
			// All is good

			// Check for updates plugin wants to make
			$this->plugin_update( $options );

			if( is_admin() ) {
				add_action( 'snapid_profile', array( $this, 'add_profile_fields' ) );
			}

			add_action( 'wp_ajax_snapid_register', array( $this, 'ajax_register' ) );
			add_action( 'wp_ajax_snapid_remove', array( $this, 'ajax_remove' ) );
			add_action( 'wp_ajax_snapid_join_check', array( $this, 'ajax_join_check' ) );

			add_action( 'wp_ajax_snapid_keyid_check', array( $this, 'ajax_keyid_check' ) );
			add_action( 'wp_ajax_nopriv_snapid_keyid_check', array( $this, 'ajax_keyid_check' ) );

			add_action( 'wp_ajax_snapid_two_step_check', array( $this, 'ajax_two_step_check' ) );
			add_action( 'wp_ajax_nopriv_snapid_two_step_check', array( $this, 'ajax_two_step_check' ) );

			add_action( 'wp_ajax_snapid_authenticate', array( $this, 'ajax_authenticate' ) );
			add_action( 'wp_ajax_nopriv_snapid_authenticate', array( $this, 'ajax_authenticate' ) );

			add_action( 'login_message', array( $this, 'login_message' ) );

			add_action( 'admin_notices', array( $this, 'setup_notice' ) );

			if( $this->TwoStepEnabled ) {
				add_filter( 'wp_authenticate_user', array( $this, 'two_step_authenticate' ), 10, 2 );

				add_action( 'login_form', array( $this, 'login_form' ) );
			}
			add_filter( 'login_body_class', array( $this, 'login_classes' ) );
		} else {
			// Nag until set up correctly
			$notice = $this->Helper->get_notice();
			if( $notice ) {
				add_action( $notice, array( $this, 'config_nag' ) );
			}
		}
	}

	/**
	 * Check for updates plugin requests to be made
	 * @return void
	 */
	private function plugin_update( $options )
	{
		$update = false;

		// 1.1 - SnapID user meta is now serialized
		if( version_compare( $this->VersionSaved, '1.1', '<' ) ) {
			$this->data_less_than_1_1();
			$update = true;
		}

		if( $update ) {
			$options['version_saved'] = sanitize_text_field( $this->Version );
			update_option( $this->options, $options );
		}
	}

	/**
	 * Plugin data updates required for 1.1
	 * User post meta is now serialized with credentials rather than a string of user proxy
	 * @return void
	 */
	private function data_less_than_1_1()
	{
		$users = get_users(
			array(
				'meta_key' => '_snapid_user_proxy',
			)
		);
		foreach( $users as $user ) {
			$user_proxy = get_user_meta( $user->ID, '_snapid_user_proxy', true );
			if( is_string( $user_proxy ) ) {
				$meta = $this->set_user_meta_args( $user_proxy );
				if( $meta ) {
					update_user_meta( $user->ID, '_snapid_user_proxy', $meta );
				}
			}
		}
	}

	/**
	 * Function to set the user meta args
	 * @param string $user_proxy
	 * @return array
	 */
	private function set_user_meta_args( $user_proxy )
	{
		if( !is_string( $user_proxy ) ) {
			return false;
		}
		return array(
				'user_proxy' => sanitize_text_field( $user_proxy ),
				'customer_id' => sanitize_text_field( $this->CustomerID ),
				'app_id' => sanitize_text_field( $this->ApplicationID ),
				'app_sub_id' => sanitize_text_field( $this->ApplicationSubID )
			);
	}

	/**
	 * Get the right user proxy for the credentials. Returns user_proxy on match, false if no match
	 * @param integer $user_id
	 * @return mixed
	 */
	private function get_user_proxy( $user_id )
	{
		$metas = get_user_meta( $user_id, '_snapid_user_proxy', false );
		if( empty( $metas ) || !is_array( $metas ) ) {
			return false;
		}
		foreach( $metas as $meta ) {
			if( $meta['customer_id'] === $this->CustomerID && $meta['app_id'] === $this->ApplicationID && $meta['app_sub_id'] === $this->ApplicationSubID ) {
				return $meta['user_proxy'];
			}
		}
		return false;
	}

	/**
	 * Nag when setup is not configured or configured correctly
	 * @action admin_notices
	 * @return void
	 */
	public function config_nag()
	{
		if( $this->check->errordescr != '' ) {
			$message = 'SnapID&trade; is not configured correctly';
			if( empty( $this->CustomerID ) || empty( $this->ApplicationID ) ) {
				$message = 'SnapID&trade; is not configured';
			}

			?>
			<div class="error">
				<p><strong><?php echo esc_html( $message ); ?></strong>. Get your <a href="https://secure.textkey.com/snapid/siteregistration" target="_blank">SnapID&trade; credentials here</a> and then <a href="<?php echo admin_url( 'options-general.php?page=snapid' ); ?>">visit the settings page</a> to continue the setup.</p>
			</div>
			<?php
		} else if( !$this->TermsAndConditions ) {
			?>
			<div class="error">
				<p><strong>Agreeing to SnapID's&trade; Terms and Conditions is required</strong>. Please <a href="<?php echo admin_url( 'options-general.php?page=snapid' ); ?>">visit the settings page</a> to continue the setup.</p>
			</div>
			<?php
		} else if( false === $this->OneStepEnabled && false === $this->TwoStepEnabled ) {
			?>
			<div class="error">
				<p><strong>SnapID&trade; has no roles set for One-Step Login or Two-Step Login</strong>. Please <a href="<?php echo admin_url( 'options-general.php?page=snapid' ); ?>">visit the settings page</a> to continue the setup.</p>
			</div>
			<?php
		}
	}

	/**
	 * Notify users to set up SnapID
	 * @action admin_notices
	 * @return void
	 */
	public function setup_notice()
	{
		$current_user = wp_get_current_user();
		$check = $this->check_user_role( $current_user->ID );
		if( !$check || $this->get_user_proxy( $current_user->ID ) ) {
			return;
		}
		if( $check == '2' ) {
			$message = 'Your user is required to use <strong>SnapID&trade; Two-Step Login</strong>. Please <a href="' . admin_url( 'profile.php#snapid' ) . '">go to your profile</a> to complete the setup.';
		} else if( $check == '1' ) {
			$message = 'Your user may use <strong>SnapID&trade; One-Step Login</strong>. Please <a href="' . admin_url( 'profile.php#snapid' ) . '">go to your profile</a> to complete the setup.';
		} else {
			return;
		}
		echo '<div class="error"> <p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Test that user authenticates
	 * @uses get_user_by, wp_check_password, wp_send_json_success, wp_send_json_error
	 * @return json
	 */
	public function ajax_two_step_check()
	{
		$user = get_user_by( 'login', $_POST['log'] );
		if( $user && $test_user = wp_check_password( $_POST['pwd'], $user->data->user_pass, $user->ID ) && $this->get_user_proxy( $user->ID ) && $this->check_user_role( $user->ID ) == '2' ) {
			wp_send_json_success();
		}
		wp_send_json_error();
	}

	/**
	 * Add roles to defaults
	 * @return void
	 */
	public function add_role_defaults()
	{
		global $wp_roles;

		foreach( $wp_roles->get_names() as $role ) {
			$role_name = strtolower( before_last_bar( $role ) );
			$this->defaults[sanitize_text_field( $role_name )] = 0;
		}
	}

	/**
	 * Check if a user's role is enabled for SnapID
	 * @uses get_option
	 * @return mixed
	 */
	public function check_user_role( $user_id )
	{
		if( !$user_id ) {
			return false;
		}
		$user = get_user_by( 'id', $user_id );
		$options = $this->Helper->get_options( $this->options );

		foreach( $user->roles as $role ) {
			if( isset( $options[$role] ) ) {
				if( ( $options[$role] == '2' && $this->TwoStepEnabled ) || ( $options[$role] == '1' && $this->OneStepEnabled ) ) {
					return $options[$role];
				}
			}
		}
		return false;
	}

	/**
	 * Backend final authentication before granting access
	 * @uses sanitize_text_field, WP_Error
	 * @return object
	 */
	public function two_step_authenticate( $user, $password )
	{
		// Check that the password is correct, if not, send 'em back
		if( !wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
			return $user;
		}

		$snapid_meta = $this->get_user_proxy( $user->ID );
		$role = $this->check_user_role( $user->ID );

		if( isset( $snapid_meta ) && !empty( $snapid_meta ) && $this->TwoStepEnabled && isset( $role ) && $role == '2' ) {

			$loginaccessidentifier = sanitize_text_field( $_SESSION['snapid_login']['loginaccessidentifier'] );
			$snapidkey = sanitize_text_field( $_SESSION['snapid_login']['snapidkey'] );
			$keycheckid = sanitize_text_field( $_SESSION['snapid_login']['keycheckid'] );

			unset( $_SESSION['snapid_login'] ); // Done with this session now, so let's get rid of it

			$response = $this->SnapID->perform_matchtouser( $loginaccessidentifier, $snapidkey, $keycheckid, '', '', '' );
			if( $response->errordescr == '' ) {
				return $user;
			} else {
				wp_logout();
				return new WP_Error( 'invalid_snapid_user_proxy', __( '<strong>Error</strong>: SnapID&trade; Two-Step Authentication is required for this user.', 'snapid' ) );
			}
		}
		return $user;
	}

	/**
	 * Add SnapID admin settings section
	 * @uses esc_attr, get_option
	 * @return void
	 */
	public function snapid_settings()
	{
		do_settings_fields( 'snapid', 'snapid_settings' );
		$options = $this->Helper->get_options( $this->options );
		$options = wp_parse_args( $options, $this->defaults );
		global $wp_roles;
		?>
		<div id="snapid-enabled-wrap" class="snapid-settings">
			<h3>Plugin Settings</h3>
			<p class="description">Use the credentials you received through email.</p>
			<table id="snapid-form-table" class="form-table">
				<tr valign="top">
					<th scope="row"><label for="snapid-customer-id">Customer ID</label></th>
					<td>
						<input type="text" id="snapid-customer-id"  name="<?php echo esc_attr( $this->options ); ?>[customer_id]" class="regular-text" value="<?php echo esc_attr( $options['customer_id'] ); ?>" />
						<p class="description">Customer ID is required.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="snapid-app-id">Application ID</label></th>
					<td>
						<input type="text" id="snapid-app-id" name="<?php echo esc_attr( $this->options ); ?>[app_id]" class="regular-text" value="<?php echo esc_attr( $options['app_id'] ); ?>" />
						<p class="description">Application ID is required.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="snapid-app-sub-id">Application Sub ID</label></th>
					<td>
						<input type="text" id="snapid-app-sub-id" name="<?php echo esc_attr( $this->options ); ?>[app_sub_id]" class="regular-text" value="<?php echo esc_attr( $options['app_sub_id'] ); ?>" />
						<p class="description">Application Sub ID is optional.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"></th>
					<td>
						<label for="snapid-terms-and-conditions"><input type="checkbox" id="snapid-terms-and-conditions" name="<?php echo esc_attr( $this->options ); ?>[terms_and_conditions]" value="1" <?php checked( '1', $options['terms_and_conditions'] ); ?> /> By checking this box, you agree to SnapID's&trade; Terms and Conditions.</label>

						<p class="description">It is required that you read and agree to SnapID's&trade; <a href="https://secure.textkey.com/snapid/termsandconditions.php" target="_blank">Terms and Conditions</a>.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="snapid-enable-one-step-y">Enable One-Step Login</label></th>
					<td>
						<label class="snapid-radio"><input type="radio" id="snapid-enabled-one-step-y" name="<?php echo esc_attr( $this->options ); ?>[one_step_enabled]" value="1" <?php checked( (bool) $options['one_step_enabled'] ); ?> /> Yes</label>
						<label class="snapid-radio"><input type="radio" id="snapid-enabled-one-step-n" name="<?php echo esc_attr( $this->options ); ?>[one_step_enabled]" value="0" <?php checked( !(bool) $options['one_step_enabled'] ); ?> /> No</label>
						<?php $one_step_toggle = (bool) $options['one_step_enabled'] ? 'display: block' : 'display: none'; ?>
						<div class="snapid-roles-wrap" style="<?php echo esc_attr( $one_step_toggle ); ?>">
							<p class="description">Allow SnapID&trade; One-Step Login for the following roles.</p>
							<?php
							foreach( $wp_roles->get_names() as $role ) {
								$role_name = before_last_bar( $role );
								echo '<p><label><input type="checkbox" name="' . esc_attr( $this->options ) . '[' . esc_attr( strtolower( $role_name ) ) . ']" value="1" ' . checked( $options[strtolower( $role_name )], 1, false ) . '/> ' . esc_html( $role_name ) . '</label></p>';
							}
							?>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="snapid-enable-two-step-y">Enable Two-Step Login</label></th>
					<td>
						<label class="snapid-radio"><input type="radio" id="snapid-enabled-two-step-y" name="<?php echo esc_attr( $this->options ); ?>[two_step_enabled]" value="1" <?php checked( (bool) $options['two_step_enabled'] ); ?> /> Yes</label>
						<label class="snapid-radio"><input type="radio" id="snapid-enabled-two-step-n" name="<?php echo esc_attr( $this->options ); ?>[two_step_enabled]" value="0" <?php checked( !(bool) $options['two_step_enabled'] ); ?> /> No</label>
						<?php $two_step_toggle = (bool) $options['two_step_enabled'] ? 'display: block' : 'display: none'; ?>
						<div class="snapid-roles-wrap" style="<?php echo esc_attr( $two_step_toggle ); ?>">
							<p class="description">Require SnapID&trade; Two-Step Login for the following roles.</p>
							<?php
							foreach( $wp_roles->get_names() as $role ) {
								$role_name = before_last_bar( $role );
								echo '<p><label><input type="checkbox" name="' . esc_attr( $this->options ) . '[' . esc_attr( strtolower( $role_name ) ) . ']" value="2" ' . checked( $options[strtolower( $role_name )], 2, false ) . '/> ' . esc_html( $role_name ) . '</label></p>';
							}
							?>
						</div>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Add SnapID admin uninstall section
	 * @uses wp_create_nonce
	 * @return void
	 */
	public function snapid_uninstall()
	{
		?>
		<div id="snapid-uninstall-wrap" class="snapid-settings">
			<h3>Uninstall &amp; Deactivate Plugin</h3>
			<p><label><input type="checkbox" id="snapid-delete-settings" name="snapid-delete-settings" /> Delete SnapID&trade; settings from WordPress.</label></p>
			<p><label><input type="checkbox" id="snapid-delete-users" name="snapid-delete-users" /> Remove user data from SnapID&trade; account and delete from WordPress.</label></p>
			<p class="description">This action of deleting data cannot be undone.</p>
		</div>
		<?php
	}

	/**
	 * Register plugin settings
	 * @action admin_init
	 * @return void
	 */
	public function register_settings()
	{
		$this->Helper->register_settings( $this, $this->options );
	}

	/**
	 * Validate SnapID options
	 * @param $options
	 * @uses santize_text_field
	 * @return array
	 */
	public function validate_options( $options )
	{
		global $wp_roles;

		if( !isset( $_POST['_wpnonce'] ) ) {
			wp_die( 'You cannot do this action' );
		}
		if( is_multisite() ) {
			if ( !current_user_can( 'manage_network_options' ) || !wp_verify_nonce( $_POST['_wpnonce'], 'snapid-settings' ) ) {
				wp_die( 'You cannot do this action' );
			}
			$options = $_POST[ $this->options ];
		}
		$options_validated = array();
		foreach( $options as $key => $value ) {
			if( isset( $this->defaults[$key] ) ) {
				switch( $key ) {
					case 'one_step_enabled':
					case 'two_step_enabled':
						$options_validated[$key] = (bool) $value;
						break;
					default:
						$options_validated[$key] = sanitize_text_field( $value );
				}
			}
		}

		// Check that roles were actually selected after enabling One-Step or Two-Step login.
		// If not, set the One-Step or Two-Step back to disabled.
		$role_call = array();
		foreach( $wp_roles->get_names() as $role ) {
			$role_name = strtolower( before_last_bar( $role ) );
			if( isset( $options_validated[$role_name] ) ) {
				$role_call[] = $options_validated[$role_name];
			}
		}
		if( empty( $role_call ) ) {
			$options_validated['one_step_enabled'] = false;
			$options_validated['two_step_enabled'] = false;
		} else {
			if( false === array_search( 1, $role_call ) ) {
				$options_validated['one_step_enabled'] = false;
			}
			if( false === array_search( 2, $role_call ) ) {
				$options_validated['two_step_enabled'] = false;
			}
		}

		if( is_multisite() ) {
			update_site_option( $this->options, $options_validated );
			wp_safe_redirect( add_query_arg( array( 'page' => 'snapid', 'updated' => 'true' ), network_admin_url( 'settings.php' ) ) );
			exit();
		}
		return $options_validated;
	}

	/**
	 * Uninstall and deactivates SnapID
	 * @uses current_user_can, wp_verify_nonce, delete_option, get_users, delete_user_meta
	 * @return void
	 */
	public function uninstall()
	{
		if( !isset( $_POST['_wpnonce'] ) ) {
			wp_die( 'You cannot do this action' );
		}

		if( is_multisite() ) {
			if ( !current_user_can( 'manage_network_options' ) || !wp_verify_nonce( $_POST['_wpnonce'], 'snapid-uninstall' ) ) {
				wp_die( 'You cannot do this action' );
			}
			$options = $_POST[ $this->options ];
		} else {
			if ( !current_user_can( 'manage_options' ) || !wp_verify_nonce( $_POST['_wpnonce'], 'snapid-uninstall' ) ) {
				wp_die( 'You cannot do this action' );
			}
		}

		if( isset( $_POST['snapid-delete-settings'] ) ) {
			$this->Helper->delete_options( $this->options );
		}

		if( isset( $_POST['snapid-delete-users'] ) ) {
			$users = get_users(
				array(
					'meta_key' => '_snapid_user_proxy',
				)
			);
			foreach( $users as $user ) {
				$metas = get_user_meta( $user->ID, '_snapid_user_proxy', false );
				if( empty( $metas ) ) {
					continue;
				}
				foreach( $metas as $meta ) {
					$snapid = new SnapID( $meta['customer_id'], $meta['app_id'], $meta['app_sub_id'] );
					$snapid->perform_remove( $meta['user_proxy'] );
					delete_user_meta( $user->ID, '_snapid_user_proxy', $meta );
				}
			}
		}

		deactivate_plugins( $this->basename );

		wp_safe_redirect( add_query_arg( array( 'deactivate' => 'true' ), $this->Helper->admin_url( 'plugins.php' ) ) );
		exit();
	}

	/**
	 * Add SnapID registration to profile
	 * @return void
	 */
	public function add_profile_fields()
	{
		global $user_id;
		$role = $this->check_user_role( $user_id );
		switch( $role ) {
			case '1':
				$role_type = 'One-Step';
				break;
			case '2':
				$role_type = 'Two-Step';
				break;
			default:
				$role = false;
				$role_type = '';
		}
		if( !$role ) {
			echo 'This user\'s role is not configured to use SnapID&trade;.';
			return;
		}
		?>
		<table id="snapid" class="form-table">
			<tr>
				<th>
					SnapID&trade; User Setup
				</th>
				<td>
					<p>
						<img src="<?php echo plugins_url( '../images/SnapIDLogo.png', __FILE__ ); ?>" width="150" />
					</p>
					<div id="snapid-example" class="modal snapid-modal">
						<a href="#" style="display: none;" id="snapid-prev">&larr; Previous</a>
						<a href="#" id="snapid-next">Next &rarr;</a>
						<?php if( $role == '1' ) { ?>
							<img class="snapid-selected" src="<?php echo plugins_url( 'images/examples/one-step-login-1.jpg', dirname( __FILE__ ) ); ?>" />
							<img src="<?php echo plugins_url( 'images/examples/one-step-login-2.jpg', dirname( __FILE__ ) ); ?>" />
						<?php } ?>
						<?php if( $role == '2' ) { ?>
							<img class="snapid-selected" src="<?php echo plugins_url( 'images/examples/two-step-login-1.jpg', dirname( __FILE__ ) ); ?>" />
							<img src="<?php echo plugins_url( 'images/examples/two-step-login-2.jpg', dirname( __FILE__ ) ); ?>" />
						<?php } ?>
					</div>
					<p class="description">This user is configured to use SnapID's&trade; <strong><?php echo esc_html( $role_type ); ?> authentication</strong> for login. <a id="snapid-learn" href="#">Learn more here</a>.</p>
					<p class="description">By using SnapID&trade; you agree to these <a href="https://secure.textkey.com/snapid/termsandconditions.php" target="_blank">Terms and Conditions</a>.</p>
					<br /></br />
					<div class="snapid-message-profile">
					</div>
					<?php $snapid_user = $this->get_user_proxy( $user_id ); ?>
					<div class="snapid-toggle" style="display: <?php echo $snapid_user ? 'none' : 'block' ?>;">
						<div class="spinner snapid-spinner"></div>
						<p><strong>This WordPress user is not using SnapID&trade;.</strong></p>
						<p><a href="#" id="snapid-join" class="button">Join SnapID&trade;</a></p>
						<?php echo $this->auth_modal( 'registration' ); ?>
					</div>

					<div class="snapid-toggle" style="display: <?php echo $snapid_user ? 'block' : 'none' ?>;">
						<div class="spinner snapid-spinner"></div>
						<p><strong>This WordPress user is using SnapID&trade;.</strong></p>
						<p><a href="#" id="snapid-remove" class="button">Remove SnapID&trade;</a></p>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * SnapID login
	 * @return void
	 */
	public function login_message()
	{
		if( isset( $_GET['action'] ) && $_GET['action'] == 'lostpassword' ) {
			return;
		}
		echo '<input type="hidden" id="snapid-nonce" value="' . wp_create_nonce( 'snapid-authenticate' ) . '" />' . "\n";
		if( $this->OneStepEnabled ) {
			?>
			<button id="snapid-login" class="button-snapid"></button>
			<h3 class="snapid-or">OR</h3>
			<?php
		}
		echo $this->auth_modal( 'login' );
	}

	/**
	 * Snapid auth modal for login and registration
	 * @return void
	 */
	public function auth_modal( $action = 'login' )
	{
		?>
		<div id="snapid-auth" class="modal snapid-modal">
			<h3>Mobile Authentication</h3>
			<p>In order to complete this <?php echo esc_html( $action ); ?>, please text the following code to <strong id="snapid-tocode">*****</strong>:</p>
			<h2 id="snapid-key">*******</h2>
			<p>You have <span class="snapid-time">90</span> seconds.</p>
			<img class="snapid-alignright" src="<?php echo plugins_url( 'images/SnapIDLogo.png', dirname( __FILE__ ) ); ?>" width="150" />
		</div>
		<?php
	}

	/**
	 * Powered by SnapID
	 * @return void
	 */
	public function login_form()
	{
		echo '<div class="snapid-protected"><span>Protected by </span><img src="' . plugins_url( 'images/SnapIDLogo.png', dirname( __FILE__ ) ) . '" width="100" /></div>';
	}

	/**
	 * Add body classes to login page
	 * @return void
	 */
	public function login_classes( $classes )
	{
		if( $this->TwoStepEnabled ) {
			$classes[] = 'snapid-two-step';
		}
		if( $this->OneStepEnabled ) {
			$classes[] = 'snapid-one-step';
		}
		return $classes;
	}

	/**
	 * Login user after authenticated
	 * @param $user_data, $response
	 * @return bool
	 */
	public function snapid_login_user( $user_data, $response )
	{
		//$meta = $this->set_user_meta_args( $user_data->userproxy );
		//$meta = "'" . serialize( $meta ) . "'";
		$args = array(
			'meta_key' => '_snapid_user_proxy',
			'meta_query' => array(
				'key' => '_snapid_user_proxy',
				'value' => '"' . $user_data->userproxy . '"',
				'compare' => 'LIKE',
			),
			'number' => 1,
		);
		$get_users = get_users( $args );
		if( !$get_users ) {
			$response->errordescr = 'Sorry, something went wrong...';
			wp_send_json_success( $response );
		}
		$get_user = $get_users[0];
		$user_id = intval( $get_user->ID );

		// Check that user_proxy is also part of this application setup for this user.
		$check_user_proxy = $this->get_user_proxy( $user_id );
		if( $check_user_proxy !== $user_data->userproxy ) {
			$response->errordescr = 'Sorry, something went wrong...';
			wp_send_json_success( $response );
		}

		// Check that this user's role can use One-Step Login
		$role = $this->check_user_role( $user_id );
		if( !$role ) {
			$response->errordescr = 'Sorry, your user is not allowed to use SnapID&trade; One-Step Login.';
			wp_send_json_success( $response ); // Sent as a success for handling reasons
		} else if( $role == '2' && !$_SESSION['snapid_login']['two_step'] ) {
			$response->errordescr = 'You have two-step login enabled for your account. Login first using your username and password.';
			wp_send_json_success( $response ); // Sent as success for handling reasons
		}

		$user = get_user_by( 'id', $user_id );
		if( $user && $role == '1') {
			wp_set_current_user( $user_id, $user->user_login );
			wp_set_auth_cookie( $user_id );
			do_action( 'wp_login', $user->user_login, $user );
		}
		return true;
	}

	/**
	 * Ajax SnapID registration endpoint
	 * @action wp_ajax_snapid_register
	 * @uses check_ajax_referer, wp_get_current_user, current_user_can, wp_send_json_error, wp_send_json_success
	 * @return json
	 */
	public function ajax_register()
	{
		$current_user = wp_get_current_user();

		if( !check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if( isset( $_POST['user_id'] ) && intval( $_POST['user_id'] ) ) {
			$user_id = $_POST['user_id'];
			$user = get_userdata( $user_id );
			$user_email = $user->user_email;
		} else {
			wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}
		if( $current_user->ID != $user_id && !current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}

		$response = $this->SnapID->perform_join( '', '', '', $user_email, '' );

		if( !$response ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		if( isset( $response->errordescr ) && $response->errordescr != '' ) {
			wp_send_json_error( $response );
		}

		$_SESSION['snapid_register'] = array(
			'keycheckid' => sanitize_text_field( $response->keycheckid ),
			'joincode' => sanitize_text_field( $response->joincode ),
			'tocode' => sanitize_text_field( $response->tocode ),
			'user_id' => intval( $_POST['user_id'] ),
		);
		foreach( $_SESSION['snapid_register'] as $item ) {
			if( !$item || empty( $item ) ) {
				wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
			}
		}

		wp_send_json_success( array( 'tocode' => $response->tocode, 'joincode' => $response->joincode ) );
	}

	/**
	 * Ajax remove registered user
	 * @return json
	 */
	public function ajax_remove()
	{
		if( !check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}

		$user_id = intval( $_POST['user_id'] );

		$response = $this->remove_snapid_user( $user_id );
		if( !$response || $response->errordescr ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}
	}

	/**
	 * Remove user from SnapID
	 * @return array
	 */
	public function remove_snapid_user( $user_id )
	{
		if( !$user_id || intval( $user_id ) == 0 ) {
			return array( 'errordescr' => 'This is not a valid user' );
		}
		$snapid_user = $this->get_user_proxy( $user_id );
		if( !$snapid_user ) {
			return array( 'errordescr' => 'This user is not set up with SnapID&trade;' );
		}
		$response = $this->SnapID->perform_remove( $snapid_user );
		if( !$response || ( isset( $response->errorDesc ) && !empty( $response->errorDesc ) ) ) {
			return array( 'errordescr' => 'This user is not set up with SnapID&trade;' );
		} else {
			$meta = $this->set_user_meta_args( $snapid_user );
			delete_user_meta( $user_id, '_snapid_user_proxy', $meta );
			return $response;
		}
	}

	/**
	 * Ajax authentication
	 * @action wp_ajax_snapid_authenticate, wp_ajax_nopriv_snapid_authenticate
	 * @return json
	 */
	public function ajax_authenticate()
	{
		if( !check_ajax_referer( 'snapid-authenticate', 'nonce', false ) ) {
			wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		$response = $this->SnapID->perform_issueSnapIDChallenge( '', '', '', '' );
		if( !$response ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		if( isset( $response->errordescr ) && $response->errordescr != '' ) {
			wp_send_json_error( $response );
		}

		$two_step = ($_GET['two_step'] === 'true');

		$loginaccessidentifier = $response->loginaccessidentifier ?: null;
		$snapidkey = $response->snapidkey ?: null;
		$keycheckid = $response->keycheckid ?: null;

		$_SESSION['snapid_login'] = array(
			'loginaccessidentifier' => $loginaccessidentifier,
			'snapidkey' => $snapidkey,
			'keycheckid' => $keycheckid,
			'two_step' => $two_step,
		);

		wp_send_json_success( array( 'tocode' => $response->tocode, 'snapidkey' => $response->snapidkey ) );
	}

	/**
	 * Ajax check for register
	 * @action wp_ajax_snapid_keyid_check, wp_ajax_nopriv_snapid_keyid_check
	 * @return json
	 */
	public function ajax_join_check()
	{
		if( !check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if( !isset( $_POST['response'] ) ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		$data = $_POST['response'];

		if( !$data ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		$keycheckid = sanitize_text_field( $_SESSION['snapid_register']['keycheckid'] );
		$joincode = sanitize_text_field( $_SESSION['snapid_register']['joincode'] );
		$user_id = intval( $_SESSION['snapid_register']['user_id'] );

		$response = $this->SnapID->perform_checkJoin( $keycheckid );

		if( !$response ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		if( $response->errordescr != '' ) {
			wp_send_json_error( $response );
		}

		if( $response->keyreceived ) {
			$user_data = $this->SnapID->perform_getUserProxy( $joincode );
			if( $user_data->userwasalreadyjoined ) {
				// Check the site for an actual user using the userproxy.
				// If no one is using it, we can register the device for this site.
				$meta = $this->set_user_meta_args( $user_data->userproxy );
				$args = array( 'number' => 1, 'meta_key' => '_snapid_user_proxy', 'meta_value' => $meta );
				$user_query = new WP_User_Query( $args );
				$results = $user_query->get_results();
				if( !empty( $results ) ) {
					$response->errordescr = 'This phone number was already used to register a user on SnapID&trade;. Please use a different phone number.';
					wp_send_json_error( $response );
				}
			}
			if( $user_id && $user_data && $user_data->errordescr == '' ) {
				$meta = $this->set_user_meta_args( $user_data->userproxy );
				$snapid_meta = add_user_meta( $user_id, '_snapid_user_proxy', $meta, false );
				if( $snapid_meta ) {
					$response->errordescr = 'Account successfully linked to SnapID&trade;.';
					wp_send_json_success( $response );
				} else {
					wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
				}
			}
		}
		wp_send_json_success( $response );
	}

	/**
	 * Ajax check for authentication
	 * @action wp_ajax_snapid_keyid_check, wp_ajax_nopriv_snapid_keyid_check
	 * @return json
	 */
	public function ajax_keyid_check()
	{
		if( !check_ajax_referer( 'snapid-authenticate', 'nonce', false ) ) {
			wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if( !isset( $_POST['response'] ) ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		$data = $_POST['response'];

		if( !$data ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		$loginaccessidentifier = sanitize_text_field( $_SESSION['snapid_login']['loginaccessidentifier'] );
		$snapidkey = sanitize_text_field( $_SESSION['snapid_login']['snapidkey'] );
		$keycheckid = sanitize_text_field( $_SESSION['snapid_login']['keycheckid'] );

		$response = $this->SnapID->perform_checkKey( $keycheckid );

		if( !$response ) {
			wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		if( $response->errordescr != '' ) {
			wp_send_json_error( $response );
		}

		if( $response->keyreceived ) {

			$user_data = $this->SnapID->perform_matchtouser( $loginaccessidentifier, $snapidkey, $keycheckid, '', '', '' );

			if( $user_data && empty( $user_data->errordescr ) ) {
				$this->snapid_login_user( $user_data, $response );
				$response->errordescr = 'Success! Logging you in to WordPress.';
			} else if( !empty( $user_data->errordescr ) && !$user_data->userexists ) {
				wp_send_json_error( array( 'errordescr' => 'Oops - you don\'t have a SnapID&trade; account yet. Login normally and follow instructions in the "Profile" section to sign up.' ) );
			} else if( !empty( $user_data->errordescr ) ) {
				wp_send_json_error( array( 'errordescr' => $user_data->errordescr ) );
			} else {
				wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
			}
		}

		wp_send_json_success( $response );
	}
}
