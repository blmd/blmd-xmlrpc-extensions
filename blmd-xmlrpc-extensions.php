<?php
/*
Plugin Name: BLMD XMLRPC Extensions
Plugin URI: https://github.com/blmd/blmd-xmlrpc-extensions
Description: XMLRPC extensions
Author: blmd
Author URI: https://github.com/blmd
Version: 0.2

GitHub Plugin URI: https://github.com/blmd/blmd-xmlrpc-extensions
*/

!defined( 'ABSPATH' ) && die;
define( 'BLMD_XMLRPC_EXTENSIONS_VERSION', '0.2' );
define( 'BLMD_XMLRPC_EXTENSIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'BLMD_XMLRPC_EXTENSIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLMD_XMLRPC_EXTENSIONS_BASENAME', plugin_basename( __FILE__ ) );


class BLMD_XMLRPC_Extensions {

	public static function factory() {
		static $instance = null;
		if ( ! ( $instance instanceof self ) ) {
			$instance = new self;
			$instance->setup_actions();
		}
		return $instance;
	}

	protected function setup_actions() {
		add_filter( 'is_protected_meta', array( $this, 'is_protected_meta' ), 10, 3 );
		add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
	}

	public function is_protected_meta( $prot, $meta_key ) {
		if ( strpos( $meta_key, '_' ) !== 0 ) { return false; }
		// $cur = wp_get_current_user();
		// $is_xmlrpc_admin = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && in_array( 'administrator', $cur->roles );
		$protect = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && current_user_can( 'manage_options' );
		return !$protect;
	}

	public function xmlrpc_methods( $args ) {
		$args['blmd.getOption']           = array( $this, 'xmlrpc_get_option' );
		$args['blmd.setOption']           = array( $this, 'xmlrpc_set_option' );
		$args['blmd.getThemes']           = array( $this, 'xmlrpc_get_themes' );
		$args['blmd.getPlugins']          = array( $this, 'xmlrpc_get_plugins' );
		$args['blmd.switchTheme']         = array( $this, 'xmlrpc_switch_theme' );
		$args['blmd.activatePlugin']      = array( $this, 'xmlrpc_activate_plugin' );
		$args['blmd.flushRewriteRules']   = array( $this, 'xmlrpc_flush_rewrite_rules' );
		$args['blmd.wpRequest']           = array( $this, 'xmlrpc_wp_request' );
		$args['blmd.getMenus']           	= array( $this, 'xmlrpc_get_menus' );
		$args['blmd.newMenu']           	= array( $this, 'xmlrpc_new_menu' );

		unset( $args['pingback.ping'] );
		return $args;
	}

	public function xmlrpc_get_option( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$ret = array();
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int) $args[0];
		$username = $args[1];
		$password = $args[2];
		$name     = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'edit_pages' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit pages.' ) );

		if ( empty( $name ) )
			return new IXR_Error( 403, 'Sorry, name is a required param.' );

		if ( strpos( $name, '%' ) !== false ) {
			$opt_name = esc_sql( $name );
			$results = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '$opt_name'" );
			foreach ( $results as $r ) {
				$ret[$r->option_name] = get_option( $r->option_name );
			}
		}
		else {
			$ret = array( get_option( $name ) );
		}
		return $ret;
	}

	public function xmlrpc_set_option( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int) $args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, "Sorry, you can not manage_options." );

		if ( empty( $struct['name'] ) || !isset( $struct['value'] ) )
			return new IXR_Error( 403, 'Sorry, name and value are required params.' );

		$name  = stripslashes_deep( $struct['name'] );
		$value = stripslashes_deep( $struct['value'] );

		$main_name = $name;
		if ( is_array( $name ) ) {
			if ( count( $name ) < 2 ) {
				return new IXR_Error( 403, 'name called with Array, expecting > 1 values.' );
			}
			$main_name       = array_shift( $name );
			$existing_option = get_option( $main_name );
			if ( !is_array( $existing_option ) ) { $existing_option = array(); }
			if ( count( $name ) == 1 ) {
				@$existing_option[(string)$name[0]] = $value;
			}
			elseif ( count( $name ) == 2 ) {
				@$existing_option[(string)$name[0]][(string)$name[1]] = $value;
			}
			elseif ( count( $name ) == 3 ) {
				@$existing_option[(string)$name[0]][(string)$name[1]][(string)$name[2]] = $value;
			}
			elseif ( count( $name ) == 4 ) {
				@$existing_option[(string)$name[0]][(string)$name[1]][(string)$name[2]][(string)$name[3]] = $value;
			}
			elseif ( count( $name ) == 5 ) {
				@$existing_option[(string)$name[0]][(string)$name[1]][(string)$name[2]][(string)$name[3]][(string)$name[4]] = $value;
			}
			elseif ( count( $name ) > 5 ) {
				return new IXR_Error( 403, 'name Array > 5 levels deep, unsupported.' );
			}
			update_option( $main_name, $existing_option );
		}
		else {
			$ret = update_option( $main_name, $value );
		}

		return get_option( $main_name );
	}

	public function xmlrpc_get_themes( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		$themes = array();
		$cur = wp_get_theme();
		foreach ( wp_get_themes() as $k => $v ) {
			$themes[] = array(
				'Name'       => $v->display( 'Name' ),
				'Version'    => $v->display( 'Version' ),
				'Template'   => $v->get_template(),
				'Stylesheet' => $v->get_stylesheet(),
				'_Symlink'   => is_link( $v->get_stylesheet_directory() ),
				'_Active'     => $cur->get( 'Name' ) == $v->get( 'Name' ),
			);
		}
		return $themes;
	}

	public function xmlrpc_get_plugins( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		$plugins = array();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		foreach ( $plugins as $k => $v ) {
			$plugins[ $k ]['_Symlink']        = is_link( WP_PLUGIN_DIR . "/{$v}" );
			$plugins[ $k ]['_Active']         = is_plugin_active( $k );
			// $plugins[ $k ]['_ActiveNetwork'] = is_plugin_active_for_network( $k );
		}
		return $plugins;
	}

	public function xmlrpc_switch_theme( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		if ( empty( $struct['stylesheet'] ) )
			return new IXR_Error( 403, __( "Missing 'stylesheet'." ) );

		return switch_theme( $struct['stylesheet'] );
	}

	public function xmlrpc_activate_plugin( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		if ( empty( $struct['plugin_name'] ) )
			return new IXR_Error( 403, __( "Missing 'plugin_name'." ) );

		$ret = null;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( !is_plugin_active( $struct['plugin_name'] ) ) {
			$ret = activate_plugin( $struct['plugin_name'] ); //, $redirect = '', $network_wide = false, $silent = false );
		}
		if ( is_wp_error( $ret ) ) {
			return new IXR_Error( 403, $ret->get_error_message() );
		}

		return $ret;
	}

	public function xmlrpc_flush_rewrite_fules( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		require_once ABSPATH.'wp-includes/rewrite.php';
		flush_rewrite_rules();
		return $ret;
	}

	public function xmlrpc_wp_request( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args[0] );
		$wp_xmlrpc_server->escape( $args[1] );
		$wp_xmlrpc_server->escape( $args[2] );

		$blog_id  = (int)$args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot manage_options.' ) );

		$cur = wp_get_current_user();
		if ( !in_array( 'administrator', $cur->roles ) )
			return new IXR_Error( 401, __( 'Sorry, administrators only.' ) );

		// add_filter('allowed_http_origin', '__return_false');
		$res = wp_remote_request( $struct['url'], $struct['args'] );
		if ( is_wp_error( $res ) )
			return new IXR_Error( 403, __( $res->get_error_message() ) );

		if ( wp_remote_retrieve_response_code( $res ) > 299 )
			return new IXR_Error( 403, __( print_r( $res['response'], 1 ) ) );

		$body = wp_remote_retrieve_body( $res );
		return $body;
	}

	public function xmlrpc_get_menus( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$ret = array();
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int) $args[0];
		$username = $args[1];
		$password = $args[2];
		$name     = isset($args[3]) ? $args[3] : null;

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'edit_pages' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot get menus.' ) );

		if ( empty( $name ) )
			return new IXR_Error( 403, 'Sorry, name is a required param.' );
		$regs = get_registered_nav_menus();
		$locs = array_flip(get_nav_menu_locations());
		$menus = array();
		foreach (wp_get_nav_menus() as $nm) {
			$ID = (int)$nm->term_id;
			$items = array();
			foreach ((array) wp_get_nav_menu_items($ID) as $item) {
				$items[] = array(
					'ID' => (int)$item->ID,
					'title' => $item->title,
					'url' => $item->url,
					'classes' => count($item->classes) > 1 ? $item->classes : array(),
				);
			}
			
			$menus[$nm->name] = array(
				'ID' => (int)$ID,
				'name' => $nm->name,
				'slug' => $nm->slug,
				'parent' => (int)$nm->parent,
				'count' => (int)$nm->count,
				'location' => isset($locs[$ID]) ? $locs[$ID] : '',
				'location_name' => isset($locs[$ID]) ? $regs[$locs[$ID]] : '',
				'items' => $items,
			);
			
		}
		return $menus;
	}

	public function xmlrpc_new_menu( $args ) {
		global $wpdb, $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );

		$blog_id  = (int) $args[0];
		$username = $args[1];
		$password = $args[2];
		$struct   = $args[3];

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		if ( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 401, "Sorry, you can not manage_options." );

		if ( empty( $struct['name'] ) || empty( $struct['items'] ) )
			return new IXR_Error( 403, 'Sorry, name and items are required params.' );

		$struct        = stripslashes_deep( $struct );
		$name          = $struct['name'];
		$location      = !empty( $struct['location'] ) ? $struct['location'] : null;
		$parent        = !empty( $struct['parent'] ) ? $struct['parent'] : null;
		$location_name = !empty( $struct['location_name'] ) ? $struct['location_name'] : null;
		$items         = !empty( $struct['items'] ) ? $struct['items'] : array();

		$locs = get_nav_menu_locations();
		$regs = get_registered_nav_menus();
		$menu_id = null;

		foreach ( (array) wp_get_nav_menus() as $menu ) {
			if ( $menu->name == $name ) {
				$menu_id = (int)$menu->term_id;
				break;
			}
		}

		if ( !$menu_id ) {
			$menu_id = wp_create_nav_menu( $name );
		}

		foreach ( (array) $items as $item ) {
			$data = array();
			if ( empty( $item['path'] ) ) { continue; }

			if ( empty( $item['type'] ) ) {
				$item['type'] = 'custom';
			}

			if ( !empty( $item['description'] ) ) {
				$data['menu-item-description'] = $item['description'];
			}
			
			if ( !empty( $item['classes'] ) ) {
				$data['menu-item-classes'] = $item['classes'];
			}

			if ( $item['type'] == 'url' || preg_match( '/^https?:\/\//i', $item['path'] ) ) {
				$data['menu-item-type'] = 'custom';
				$data['menu-item-url']  = $item['path'];
			}
			elseif ( strpos( $item['type'], 'rel' ) === 0 || strpos( $item['path'], '/' ) === 0 ) {
				$data['menu-item-type'] = 'custom';
				$data['menu-item-url']  = user_trailingslashit( home_url( $item['path'] ) );
			}
			elseif ( strpos( $item['type'], 'cat' ) === 0 ) {
				$term = get_term_by( 'slug', $item['path'], 'category' );
				if ( empty( $term->term_id ) ) { continue; }
				$data['menu-item-type']      = 'taxonomy';
				$data['menu-item-object']    = 'category';
				$data['menu-item-object-id'] = (int)$term->term_id;
			}
			elseif ( strpos( $item['type'], 'product-cat' ) === 0 ) {
				$term = get_term_by( 'slug', $item['path'], 'product_cat' );
				if ( empty( $term->term_id ) ) { continue; }
				$data['menu-item-type']      = 'taxonomy';
				$data['menu-item-object']    = 'product_cat';
				$data['menu-item-object-id'] = (int)$term->term_id;
			}
			elseif ( $item['type'] == 'product' || $item['type'] == 'prod') {
				$page = get_page_by_path($item['path'], OBJECT, 'product');
				if (empty($page->ID)) { continue; }
				$data['menu-item-type']      = 'post_type';
				$data['menu-item-object']    = 'product';
				$data['menu-item-object-id'] = $page->ID;
				// $data['menu-item-url']    = home_url($item['path']);
			}
			elseif (strpos($item['type'], 'page') === 0 || true) {
				$page = get_page_by_path($item['path']);
				if (empty($page->ID)) { continue; }
				$data['menu-item-type']      = 'post_type';
				$data['menu-item-object']    = 'page';
				$data['menu-item-object-id'] = $page->ID;
				// $data['menu-item-url']    = home_url($item['path']);
			}
			$data['menu-item-title']     = !empty($item['title']) ? $item['title'] : '';
			$data['menu-item-status']    = 'publish';
			$data['menu-item-parent-id'] = 0;
			// $data['menu-item-position'] = 0;

			$item_id = wp_update_nav_menu_item( $menu_id, 0, $data );
			if (!empty($item['items'])) {
				foreach ((array) $item['items'] as $item2) {
					$data2 = array();
					if ( empty( $item2['path'] ) ) { continue; }

					if ( empty( $item2['type'] ) ) {
						$item2['type'] = 'custom';
					}

					if ( !empty( $item2['description'] ) ) {
						$data2['menu-item-description'] = $item2['description'];
					}
			
					if ( !empty( $item2['classes'] ) ) {
						$data2['menu-item-classes'] = $item2['classes'];
					}

					if ( $item2['type'] == 'url' || preg_match( '/^https?:\/\//i', $item2['path'] ) ) {
						$data2['menu-item-type'] = 'custom';
						$data2['menu-item-url']  = $item2['path'];
					}
					elseif ( strpos( $item2['type'], 'rel' ) === 0 || strpos( $item2['path'], '/' ) === 0 ) {
						$data2['menu-item-type'] = 'custom';
						$data2['menu-item-url']  = user_trailingslashit( home_url( $item2['path'] ) );
					}
					elseif ( strpos( $item2['type'], 'cat' ) === 0 ) {
						$term = get_term_by( 'slug', $item2['path'], 'category' );
						if ( empty( $term->term_id ) ) { continue; }
						$data2['menu-item-type']      = 'taxonomy';
						$data2['menu-item-object']    = 'category';
						$data2['menu-item-object-id'] = (int)$term->term_id;
					}
					elseif ( strpos( $item2['type'], 'product-cat' ) === 0 ) {
						$term = get_term_by( 'slug', $item2['path'], 'product_cat' );
						if ( empty( $term->term_id ) ) { continue; }
						$data2['menu-item-type']      = 'taxonomy';
						$data2['menu-item-object']    = 'product_cat';
						$data2['menu-item-object-id'] = (int)$term->term_id;
					}
					elseif ( $item2['type'] == 'product' || $item2['type'] == 'prod') {
						$page = get_page_by_path($item2['path'], OBJECT, 'product');
						if (empty($page->ID)) { continue; }
						$data2['menu-item-type']      = 'post_type';
						$data2['menu-item-object']    = 'product';
						$data2['menu-item-object-id'] = $page->ID;
						// $data2['menu-item-url']    = home_url($item2['path']);
					}
					elseif (strpos($item2['type'], 'page') === 0 || true) {
						$page = get_page_by_path($item2['path']);
						if (empty($page->ID)) { continue; }
						$data2['menu-item-type']      = 'post_type';
						$data2['menu-item-object']    = 'page';
						$data2['menu-item-object-id'] = $page->ID;
						// $data2['menu-item-url']    = home_url($item2['path']);
					}
					$data2['menu-item-title']     = !empty($item2['title']) ? $item2['title'] : '';
					$data2['menu-item-status']    = 'publish';
					$data2['menu-item-parent-id'] = (int)$item_id;
					// $data2['menu-item-position'] = 0;

					$item2_id = wp_update_nav_menu_item( $menu_id, 0, $data2 );
					
				}
			}
			
		}
		
		if ( !empty( $location ) ) {
			// regex
			if ( $location[0] == '/' ) { // regex
				$location = array_shift( preg_grep( $location, array_keys( $regs ) ) );
			}
			if ($location && isset($regs[$location])) {
				$locs[$location] = $menu_id;
				foreach ( array_keys( $regs ) as $r ) {
					if ( !isset( $locs[$r] ) ) { $locs[$r] = 0; }
				}
				set_theme_mod( 'nav_menu_locations', $locs );
			}
		}
		elseif ( !empty( $location_name ) ) {
			$location = array_search( $location_name, $regs );
			if ( $location ) {
				$locs[$location] = $menu_id;
				foreach ( array_keys( $regs ) as $r ) {
					if ( !isset( $locs[$r] ) ) { $locs[$r] = 0; }
				}
				set_theme_mod( 'nav_menu_locations', $locs );
			}
		}
		do_action('wp_create_nav_menu', $menu_id);
		return $menu_id;

	}


	public function __construct() { }

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'core-plugin' ), '0.1' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'core-plugin' ), '0.1' );
	}

};

function BLMD_XMLRPC_Extensions() {
	return BLMD_XMLRPC_Extensions::factory();
}

BLMD_XMLRPC_Extensions();
