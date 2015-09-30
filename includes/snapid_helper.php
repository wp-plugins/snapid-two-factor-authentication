<?php
class WP_SnapID_Helper
{
	/**
	 * Construct
	 * @param boolean $is_multi
	 */
	public function __construct( $is_multi )
	{
		$this->is_multi = $is_multi;
	}

	/**
	 * Helper for admin_menu
	 * @param object $snapid
	 */
	public function admin_menu( $snapid )
	{
		if( $this->is_multi ) {
			add_action( 'network_admin_menu', array( $snapid, 'add_network_menu' ) );
		} else {
			add_action( 'admin_menu', array( $snapid, 'add_admin_menu' ) );
		}
	}

	/**
	 * Check if the page is the login page
	 * @return boolean
	 */
	public function is_login_page()
	{
		return in_array( $GLOBALS['pagenow'], array( 'wp-login.php' ) );
	}

	/**
	 * Helper for settings and uninstall links
	 * @param array $snapid
	 * @return array
	 */
	public function get_actions_settings( $snapid )
	{
		if( $this->is_multi ) {
			$snapid['settings'] = 'edit.php?action=save_snapid_settings';
			$snapid['uninstall'] = 'edit.php?action=uninstall_snapid_settings';
		} else {
			$snapid['settings'] = 'options.php';
			$snapid['uninstall'] = 'admin-post.php?action=snapid_uninstall';
		}
		return $snapid;
	}

	/**
	 * Helper for settings fields
	 */
	public function settings_fields()
	{
		if( $this->is_multi ) {
			echo '<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'snapid-settings' ) . '" />';
		} else {
			settings_fields( 'snapid_settings' );
		}
	}

	/**
	 * Helper for get options
	 * @param array $options
	 * @return array
	 */
	public function get_options( $options )
	{
		return $this->is_multi ? get_site_option( $options ) : get_option( $options );
	}

	/**
	 * Helper for get notice
	 * @return mixed
	 */
	public function get_notice()
	{
		if( $this->is_multi && current_user_can( 'manage_network_options' ) ) {
			return 'network_admin_notices';
		} else if ( !is_multisite() && current_user_can( 'manage_options' ) ) {
			return 'admin_notices';
		}
		return false;
	}

	/**
	 * Helper for register settings
	 * @param object $snapid
	 * @param array $options
	 */
	public function register_settings( $snapid, $options )
	{
		if( $this->is_multi ) {
			add_action( 'network_admin_edit_save_snapid_settings', array( $snapid, 'validate_options' ) );
			add_action( 'network_admin_edit_uninstall_snapid_settings', array( $snapid, 'uninstall' ) );
		} else {
			register_setting( 'snapid_settings', $options, array( $snapid, 'validate_options' ) );
			add_action( 'admin_post_snapid_uninstall', array( $snapid, 'uninstall' ) );
		}
	}

	/**
	 * Helper for delete options
	 * @param array $options
	 */
	public function delete_options( $options )
	{
		if( $this->is_multi ) {
			return delete_site_option( $options );
		} else {
			return delete_option( $options );
		}
	}

	/**
	 * Helper for admin_url
	 * @param string $url
	 * @return string
	 */
	public function admin_url( $url )
	{
		return $this->is_multi ? network_admin_url( $url ) : admin_url( $url );
	}
}
