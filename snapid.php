<?php
/*
 * Plugin Name: SnapID™ Two-Factor Authentication
 * Description: Get the most secure & convenient two-factor authentication plugin for your WordPress website. With SnapID™ you will never have to remember your username and password ever again and be more secure than ever.
 * Author: TextPower Inc.
 * Version: 1.0
 * Author URI: http://www.textpower.com/
 */

class WP_SnapID_Setup
{
    private static $__instance = null;
    public $version = '1.0';
    public $basename;
    public $Helper;
    private $SnapID;

    /**
     * Construct
     */
    private function __construct()
    {
        // Nothing to see here...
    }

    /**
     * Setup 
     */
    private function setup()
    {
        require_once( 'includes/snapid_helper.php' );
        require_once( 'includes/snapid_rest.php' );
        require_once( 'includes/snapid_wp.php' );

        $this->basename = plugin_basename( __FILE__ );
        $this->Helper = new WP_SnapID_Helper( is_multisite() );

        add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'plugin_action_links' ) );

        // init SnapID
        $this->SnapID = new WP_SnapID( $this->basename, $this->Helper );

        $this->Helper->admin_menu( $this );

        if( is_admin() ) {
            add_action( 'show_user_profile', array( $this, 'profile_action' ) );
            add_action( 'edit_user_profile', array( $this, 'profile_action' ) );
        }
        
        add_action( 'login_enqueue_scripts', array( $this, 'login_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
    }

    /**
     * Singleton implementation
     * @return object
     */
    public static function instance()
    {
        if( !is_a( self::$__instance, 'WP_SnapID_Setup' ) ) {
            self::$__instance = new WP_SnapID_Setup;
            self::$__instance->setup();
        }
        return self::$__instance;
    }

    /**
     * Adds links to plugin page entry
     * @param $links
     * @uses admin_url
     * @return array
     */
    public function plugin_action_links( $links )
    {
        $uninstall = sprintf( '<a href="%s" title="Uninstall and deactivate this plugin ">%s</a>', admin_url( 'options-general.php?page=snapid#snapid-uninstall-wrap' ), _x( 'Uninstall', 'Uninstall and deactivate this plugin', 'snapid' ) );
        array_unshift( $links, $uninstall );

        $settings = sprintf( '<a href="%s" title="Plugin configuration and preferences">%s</a>', admin_url( 'options-general.php?page=snapid#snapid-settings-wrap' ), _x( 'Settings', 'Plugin configuration and preferences', 'snapid' ) );
        array_unshift( $links, $settings );

        return $links;
    }

    /**
     * SnapID login assets
     * @return void
     */
    public function login_assets()
    {
        $ver = $this->version;
        wp_enqueue_style( 'snapid_login_css', plugin_dir_url( __FILE__ ) . 'css/snapid_login.css', array(), $ver );
        wp_enqueue_style( 'snapid_jquery_modal_css', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.css', array(), $ver );
        wp_enqueue_style( 'snapid_css', plugin_dir_url( __FILE__ ) . 'css/snapid.css', array(), $ver );
        wp_enqueue_script( 'snapid_jquery_modal_js', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.min.js', array( 'jquery' ), $ver, true );
        wp_enqueue_script( 'snapid_js', plugin_dir_url( __FILE__ ) . 'js/snapid.js', array( 'jquery', 'snapid_jquery_modal_js' ), $ver, true );
        wp_enqueue_script( 'snapid_login_js', plugin_dir_url( __FILE__ ) . 'js/snapid_login.js', array( 'jquery', 'snapid_jquery_modal_js', 'snapid_js' ), $ver, true );

        $snapid = array();
        $snapid['ajaxurl'] = admin_url( 'admin-ajax.php' );

        if( isset( $_GET['redirect_to'] ) ) {
            $snapid['redirect_to'] = esc_url( urldecode( $_GET['redirect_to'] ) );
        } else {
            $snapid['redirect_to'] = admin_url();
        }

        $snapid['one_step_enabled'] = $this->SnapID->OneStepEnabled;
        $snapid['two_step_enabled'] = $this->SnapID->TwoStepEnabled;

        wp_localize_script( 'snapid_js', 'snapid', $snapid );
    }

    /**
     * SnapID admin assets
     * @return void
     */
    public function admin_assets()
    {
        $ver = $this->version;
        wp_enqueue_style( 'snapid_jquery_modal_css', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.css', array(), $ver );
        wp_enqueue_style( 'snapid_css', plugin_dir_url( __FILE__ ) . 'css/snapid.css', array(), $ver );
        wp_enqueue_script( 'snapid_jquery_modal_js', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.min.js', array( 'jquery' ), $ver, true );
        wp_enqueue_script( 'snapid_js', plugin_dir_url( __FILE__ ) . 'js/snapid.js', array( 'jquery', 'snapid_jquery_modal_js' ), $ver, true );
        wp_enqueue_style( 'snapid_admin_css', plugin_dir_url( __FILE__ ) . 'css/snapid_admin.css', array( 'snapid_css' ), $ver );
        wp_enqueue_script( 'snapid_admin_js', plugin_dir_url( __FILE__ ) . 'js/snapid_admin.js', array( 'jquery', 'snapid_jquery_modal_js', 'snapid_js' ), $ver, true );

        $snapid = array();
        $snapid['ajaxurl'] = admin_url( 'admin-ajax.php' );

        wp_localize_script( 'snapid_js', 'snapid', $snapid );
    }

    /**
     * Add SnapID to the admin menu
     * @action admin_menu
     * @uses add_options_page
     * @return void
     */
    public function add_admin_menu()
    {
        add_options_page( 'SnapID&trade;', 'SnapID&trade;', 'manage_options', 'snapid', array( $this, 'add_settings_page' ) );
    }

    /**
     * Add SnapID to the network menu for multisite
     * @action network_admin_menu
     * @uses add_submenu_page
     * @return void
     */
    public function add_network_menu()
    {
       add_submenu_page( 'settings.php', 'SnapID&trade;', 'SnapID&trade;', 'manage_options', 'snapid', array( $this, 'add_settings_page' ) );
    }

    /**
     *  Add SnapID section on profile page
     * @uses wp_create_nonce, do_action
     * @return void
     */
	public function profile_action()
	{
        if( !$this->SnapID->OneStepEnabled && !$this->SnapID->TwoStepEnabled ) {
            return;
        }
        global $user_id;
        echo '<input type="hidden" id="snapid-nonce" value="' . wp_create_nonce( 'snapid-register' ) . '" />' . "\n";
        echo '<input type="hidden" id="snapid-user-id" value="' . intval( $user_id ) . '" />' . "\n";
		do_action( 'snapid_profile' );
	}

    /**
     * Add SnapID admin settings page
     * @uses settings_fields, do_settings_sections, do_action, submit_buttons, esc_attr
     * @return void
     */
    public function add_settings_page()
    {
        $snapid = array(
            'settings' => '',
            'uninstall' => '',
        );

        $snapid = $this->Helper->get_actions_settings( $snapid )

        ?>
        <div class="wrap">
            <h2><img src="<?php echo plugins_url( 'images/SnapIDLogo.png', __FILE__ ); ?>" width="150" /></h2>
            <form method="post" class="postbox snapid-form" action="<?php echo esc_attr( $snapid['settings'] ); ?>">
                <?php $this->Helper->settings_fields(); ?>
                <?php do_action( 'snapid_settings' ); ?>
                <?php submit_button(); ?>
            </form>
            <form id="snapid-uninstall-form" method="post" class="postbox snapid-form" action="<?php echo esc_attr( $snapid['uninstall'] ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'snapid-uninstall' ); ?>" />
                <?php do_action( 'snapid_uninstall' ); ?>
                <?php submit_button( 'Uninstall SnapID&trade;', 'secondary' ); ?>
            </form>
        </div><!-- .wrap -->
        <?php
    }
}

WP_SnapID_Setup::instance();
