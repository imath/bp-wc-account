<?php
/**
 * Create the WooCommerce My Account pages inside the BuddyPress user's profile.
 *
 * @package BP WC Account
 * @subpackage Component
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Component Class.
 *
 * @since 1.0.0
 */
class BP_WC_Account_Component extends BP_Component {

	/**
	 * Start the attachments component setup process.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::start(
			'bp_wc_account',
			__( 'My Account', 'bp-wc-account' ),
			'',
			array(
				'adminbar_myaccount_order' => (int) apply_filters( 'bp_wc_account_main_nav_position', 10 ),
			)
		);

		$this->setup_hooks();
	}

	/**
	 * Set some hooks to maximize BuddyPress integration.
	 *
	 * @since 1.0.0
	 */
	public function setup_hooks() {
		add_filter( 'woocommerce_get_endpoint_url',             array( $this, 'get_url' ),     10, 4 );
		add_filter( 'woocommerce_get_myaccount_page_permalink', array( $this, 'account_url' ), 10, 1 );
	}

	/**
	 * Include component files.
	 *
	 * @since 1.0.0
	 */
	public function includes( $includes = array() ) {}

	/**
	 * Set up component global variables.
	 *
	 * @since 1.0.0
	 */
	public function setup_globals( $args = array() ) {
		$bp = buddypress();

		$bp->active_components[$this->id] = '1';

		// Define a slug, if necessary
		if ( ! defined( 'BP_WC_ACCOUNT_SLUG' ) ) {
			define( 'BP_WC_ACCOUNT_SLUG', 'my-account' );
		}

		// All globals for attachments component.
		$args = array(
			'slug'                  => BP_WC_ACCOUNT_SLUG,
			'has_directory'         => false,
		);

		parent::setup_globals( $args );


		// Get Available WooCommerce User Account items
		$this->wc_items = (array) apply_filters( 'bp_wc_account_bp_nav_items', wc_get_account_menu_items() );

		$this->wc_account_link = '';
		if ( is_user_logged_in() ) {
			$this->wc_account_link = trailingslashit( bp_loggedin_user_domain() . $this->slug );
		}
	}

	/**
	 * Set up component navigation.
	 *
	 * @since 1.0.0
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		// Stop if there is no loggedin user
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Default screen callback
		$screen_function = 'set_screen';

		$main_nav = array(
			'name'                    => __( 'My Account', 'bp-wc-account' ),
			'slug'                    => $this->slug,
			'position'                => (int) apply_filters( 'bp_wc_account_main_nav_position', 10 ),
			'screen_function'         => array( $this, $screen_function ),
			'default_subnav_slug'     => 'dashboard',
			'item_css_id'             => $this->id,
			'show_for_displayed_user' => false,
		);

		// Subnav position
		$position = 10;

		// Add the subnav items to the attachments nav item if we are using a theme that supports this
		$sub_nav['main'] = array(
			'name'            => __( 'Dashboard', 'bp-wc-account' ),
			'slug'            => 'dashboard',
			'parent_url'      => $this->wc_account_link,
			'parent_slug'     => $this->slug,
			'screen_function' => array( $this, $screen_function ),
			'position'        => $position,
			'user_has_access' => bp_is_my_profile(),
		);

		foreach ( $this->wc_items as $key_item => $item ) {
			if ( 'dashboard' === $key_item || 'customer-logout' === $key_item ) {
				continue;
			}

			// Do not include the edit account part if BuddyPress is dealing with it
			if ( bp_is_active( 'settings' ) && 'edit-account' === $key_item ) {
				continue;
			}

			$position += 10;

			// Filter here to override the default screen function
			$item_screen_function = apply_filters( 'bp_wc_account_item_screen_callback', $screen_function, $key_item );

			if ( method_exists( $this, $item_screen_function ) ) {
				$item_screen_function = array( $this, $item_screen_function );
			} elseif ( ! function_exists( $item_screen_function ) ) {
				continue;
			}

			$sub_nav[ $key_item ] = array(
				'name'            => $item,
				'slug'            => $key_item,
				'parent_url'      => $this->wc_account_link,
				'parent_slug'     => $this->slug,
				'screen_function' => $item_screen_function,
				'position'        => $position,
				'user_has_access' => bp_is_my_profile(),
			);
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up the component entries in the WordPress Admin Bar.
	 *
	 * WooCommerce disables the Admin Bar for regular users.
	 *
	 * @since 1.0.0
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {
		// Menus for logged in user
		if ( is_user_logged_in() ) {

			// Add the "My Account" sub menus
			$wp_admin_nav[] = array(
				'parent' => buddypress()->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => __( 'My Account', 'bp-wc-account' ),
				'href'   => $this->wc_account_link
			);

			// Dashboard default submenu
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-dashboard',
				'title'  => __( 'Dashboard', 'bp-wc-account' ),
				'href'   => $this->wc_account_link
			);

			// Add submenus
			foreach( $this->wc_items as $key_item => $item ) {
				if ( 'dashboard' === $key_item || 'customer-logout' === $key_item ) {
					continue;
				}

				// Do not include the edit account part if BuddyPress is dealing with it
				if ( bp_is_active( 'settings' ) && 'edit-account' === $key_item ) {
					continue;
				}

				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-' . $key_item,
					'title'  => $item,
					'href'   => trailingslashit( $this->wc_account_link . $key_item )
				);
			}
		}

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Set the BuddyPress screen for the requested action
	 *
	 * Strangely WooCommerce seems to not use a pagination within their loops
	 * for the orders or the downloads, so we don't have to deal with it.
	 *
	 * @since 1.0.0
	 */
	public function set_screen() {
		// Allow plugins to do things there..
		do_action( 'bp_wc_account_screen' );

		if ( bp_is_current_action( 'orders' ) && 'view-order' === bp_action_variable( 0 ) && is_numeric( bp_action_variable( 1 ) ) ) {
			/**
			 * WooCommerce is using the $wp global to set is variables
			 *
			 * Here we need to unset the orders var to be able to load the single order.
			 */
			global $wp;

			unset( $wp->query_vars['orders'] );
			$wp->query_vars['view-order'] = (int) bp_action_variable( 1 );
		}

		// The title is restricted to BuddyPress in this part
		remove_filter( 'the_title', 'wc_page_endpoint_title' );

		// Prepare the WooCommerce template part.
		add_action( 'bp_template_content', array( $this, 'wc_content' ) );

		// Load the template
		bp_core_load_template( apply_filters( 'bp_wc_account_template', 'members/single/plugins' ) );
	}

	/**
	 * Output the WooCommerce My Account page.
	 *
	 * @since 1.0.0
	 */
	public function wc_content() {
		echo apply_filters( 'the_content', get_post_field( 'post_content', wc_get_page_id( 'myaccount' ) ) );
	}

	/**
	 * Filter the account urls if needed.
	 *
	 * @since 1.0.0
	 */
	public function get_url( $url, $endpoint, $value, $permalink ) {
		if ( ! bp_is_current_component( 'my-account' ) && ! apply_filters( 'bp_wc_account_url_use_everywhere', false ) ) {
			return $url;
		}

		$my_account_slugs = array_merge( wc_get_account_menu_items(), array( 'view-order' => true ) );

		if ( isset( $my_account_slugs[ $endpoint ] ) ) {
			$slug = $endpoint;

			if ( 'view-order' === $endpoint ) {
				$slug = 'orders/' . $slug;
			}

			$url = trailingslashit( bp_loggedin_user_domain() . $this->slug ) . $slug;

			if ( ! empty( $value ) ) {
				$url = trailingslashit( $url ) . $value;
			}
		}

		return $url;
	}

	/**
	 * Filter the account root url if needed.
	 *
	 * WooCommerce use this to redirect the user when a form is submitted.
	 *
	 * @since 1.0.0
	 */
	public function account_url( $url = '' ) {
		global $wp;

		// WooCommerce may redirect the loggedin user to his "my account" page...
		$loggedin_redirected = false !== strpos( wp_get_referer(), 'wp-login.php' ) && ! current_user_can( 'edit_posts' );

		if ( ! bp_is_current_component( 'my-account' ) && ! apply_filters( 'bp_wc_account_url_use_everywhere', false ) && ! isset( $wp->query_vars['customer-logout'] ) && ! $loggedin_redirected ) {
			return $url;
		}

		if ( isset( $wp->query_vars['customer-logout'] ) ) {
			return bp_get_root_domain();
		} else {
			return trailingslashit( bp_loggedin_user_domain() . $this->slug );
		}
	}
}

/**
 * Bootstrap the component.
 */
function bp_wc_account_component() {
	buddypress()->bp_wc_account = new BP_WC_Account_Component();
}
add_action( 'bp_wc_account_ready', 'bp_wc_account_component', 20 );
