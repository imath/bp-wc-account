<?php
/**
 * Moves the WooCommerce "My Account" inside the BuddyPress user's profile
 *
 * @package   BP WC Account
 * @author    imath
 * @license   GPL-2.0+
 * @link      https://imathi.eu
 *
 * @buddypress-plugin
 * Plugin Name:       BP WC Account
 * Plugin URI:        https://github.com/imath/bp-wc-account
 * Description:       Moves the WooCommerce "My Account" inside the BuddyPress user's profile
 * Version:           1.0.0-alpha
 * Author:            imath
 * Author URI:        https://github.com/imath
 * Text Domain:       bp-wc-account
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/bp-wc-account
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_WC_Account' ) ) :
/**
 * Main Class
 *
 * @since 1.0.0
 */
class BP_WC_Account {
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	/**
	 * BuddyPress version
	 */
	public static $required_bp_version = '2.5.0-beta1';

	/**
	 * Initialize the plugin
	 */
	private function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0.0
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Sets some globals for the plugin
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {
		/** Plugin globals ********************************************/
		$this->version       = '1.0.0-alpha';
		$this->domain        = 'bp-wc-account';
		$this->name          = 'BP WC Account';
		$this->file          = __FILE__;
		$this->basename      = plugin_basename( $this->file );
		$this->plugin_dir    = plugin_dir_path( $this->file );
		$this->plugin_url    = plugin_dir_url( $this->file );
		$this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );
		$this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );
	}

	/**
	 * Checks BuddyPress version
	 *
	 * @since 1.0.0
	 */
	public function version_check() {
		// taking no risk
		if ( ! function_exists( 'bp_get_version' ) ) {
			return false;
		}

		return version_compare( bp_get_version(), self::$required_bp_version, '>=' );
	}

	/**
	 * Check WooCommerce is activated
	 */
	public function depedency_checks() {
		return function_exists( 'WC' );
	}

	/**
	 * Include needed files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		if ( ! $this->version_check() || ! $this->depedency_checks() ) {
			return;
		}

		require( $this->includes_dir . 'component.php' );
	}

	/**
	 * Set hooks
	 *
	 * @since 1.0.0
	 */
	private function setup_hooks() {
		// BuddyPress version is ok & the site has an icon
		if ( $this->version_check() && $this->depedency_checks() ) {
			// Make sure JS are loaded
			add_filter( 'woocommerce_is_account_page', array( $this, 'is_account_page' ),     10, 1 );

			// Disable the WooCommerce navigation
			add_filter( 'woocommerce_locate_template', array( $this, 'disable_account_nav' ), 10, 2 );

			// Adjust the layout
			add_action( 'wp_enqueue_scripts', array( $this, 'wc_inline_style' ), 20 );

			// Plugin's ready!
			do_action( 'bp_wc_account_ready' );

		// There's something wrong, inform the Administrator
		} else {
			add_action( bp_core_do_network_admin() ? 'network_admin_notices' : 'admin_notices', array( $this, 'admin_warning' ) );
		}

		// load the languages..
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );
	}

	/**
	 * Loads a dummy template instead of the unused WooCommerce navigation one
	 *
	 * @since 1.0.0
	 */
	public function disable_account_nav( $template = '', $template_name = '' ) {
		if ( 'myaccount/navigation.php' === $template_name && bp_is_current_component( 'my-account' ) ) {
			$template = $this->includes_dir . 'dummy-wc-nav.php';
		}

		return $template;
	}

	/**
	 * Are we displaying the BuddyPress my account page ?
	 *
	 * @since 1.0.0
	 */
	public function is_account_page( $retval = false ) {
		if ( false === $retval ) {
			$retval = bp_is_current_component( 'my-account' );
		}

		return $retval;
	}

	/**
	 * Add inline style
	 */
	public function wc_inline_style() {
		if ( ! bp_is_current_component( 'my-account' ) ) {
			return;
		}

		wp_add_inline_style( 'woocommerce-general', '
			.woocommerce-account .my-account-content {
				float: none;
				width: 100%;
			}
		' );
	}

	/**
	 * Display a message to admin in case config is not as expected
	 *
	 * @since 1.0.0
	 */
	public function admin_warning() {
		$warnings = array();

		if( ! $this->version_check() ) {
			$warnings[] = sprintf( __( '%s requires at least version %s of BuddyPress.', 'bp-wc-account' ), $this->name, '2.5.0' );
		}

		if ( ! $this->depedency_checks() ) {
			$warnings[] = sprintf( __( '%s requires the WooCommerce plugin to be activated.', 'bp-wc-account' ), $this->name );
		}

		if ( ! empty( $warnings ) ) :
		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo esc_html( $warning ) ; ?>
			<?php endforeach ; ?>
		</div>
		<?php
		endif;
	}

	/**
	 * Loads the translation files
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bp-wc-account/' . $mofile;

		// Look in global /wp-content/languages/bp-wc-account folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/bp-wc-account/languages/ folder
		load_textdomain( $this->domain, $mofile_local );
	}
}

endif;

// Let's start !
function bp_wc_account() {
	return BP_WC_Account::start();
}
add_action( 'bp_include', 'bp_wc_account', 9 );
