<?php
/**
 * Plugin Name: MUDPress
 * Plugin URI: http://scotchfield.com/
 * Description: Turn WordPress into a MUD
 * Version: 0.1
 * Author: Scott Grant
 * Author URI: http://scotchfield.com/
 * License: GPL2
 */
class WP_MUDPress {

	/**
	 * Store reference to singleton object.
	 */
	private static $instance = null;

	/**
	 * The MUDPress domain for localization.
	 */
	const DOMAIN = 'mudpress';

	/**
	 * The custom post type string for zones.
	 */
	const CPT_ZONE = 'mudpress-zone';

	/**
	 * The custom post type string for zones.
	 */
	const TAXONOMY_ZONE_TYPE = 'mudpress-zone-type';

	/**
	 * Possible types that a zone may exhibit, non-exclusive (taxonomy terms)
	 */
	const ZONE_TYPE_DESCRIPTION = 'description',
	      ZONE_TYPE_STORE       = 'store',
	      ZONE_TYPE_COMBAT      = 'combat';

	private $default_meta = array(
		'health' => 100,
		'health_max' => 100,
		'armour_head' => array(),
	);

	/**
	 * Instantiate, if necessary, and add hooks.
	 */
	public function __construct() {
		if ( isset( self::$instance ) ) {
			wp_die( esc_html__( 'The WP_MUDPress class has already been instantiated.', self::DOMAIN ) );
		}

		self::$instance = $this;

		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_action( 'the_post', array( $this, 'display_post' ) );

		add_action( 'save_post_' . self::CPT_ZONE, array( $this, 'save_zone_meta' ) );

		add_shortcode( 'mudpress_profile', array( $this, 'show_profile' ) );
	}

	/**
	 * Initialize custom types.
	 */
	public function init() {
		register_post_type(
			self::CPT_ZONE,
			array(
				'labels' => array(
					'name'               => esc_html__( 'Zones',                   self::DOMAIN ),
					'singular_name'      => esc_html__( 'Zone',                    self::DOMAIN ),
					'add_new'            => esc_html__( 'Add New Zone',            self::DOMAIN ),
					'add_new_item'       => esc_html__( 'Add New Zone',            self::DOMAIN ),
					'edit_item'          => esc_html__( 'Edit Zone',               self::DOMAIN ),
					'new_item'           => esc_html__( 'New Zone',                self::DOMAIN ),
					'view_item'          => esc_html__( 'View Zone',               self::DOMAIN ),
					'search_items'       => esc_html__( 'Search Zones',            self::DOMAIN ),
					'not_found'          => esc_html__( 'No zones found',          self::DOMAIN ),
					'not_found_in_trash' => esc_html__( 'No zones found in trash', self::DOMAIN ),
				),
				'public' => true,
				'exclude_from_search' => false,
				'show_ui' => true,
				'rewrite' => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY_ZONE_TYPE,
			self::CPT_ZONE,
			array(
				'label' => esc_html__( 'Zone Types', self::DOMAIN ),
				'labels' => array(
					'name' =>          esc_html__( 'Zone Types',          self::DOMAIN ),
					'singular_name' => esc_html__( 'Zone Type',           self::DOMAIN ),
					'all_items' =>     esc_html__( 'All Zone Types',      self::DOMAIN ),
					'edit_item' =>     esc_html__( 'Edit Zone Type',      self::DOMAIN ),
					'view_item' =>     esc_html__( 'View Zone Type',      self::DOMAIN ),
					'update_item' =>   esc_html__( 'Update Zone Type',    self::DOMAIN ),
					'add_new_item' =>  esc_html__( 'Add New Zone Type',   self::DOMAIN ),
					'new_item_name' => esc_html__( 'New Zone Type Name',  self::DOMAIN ),
					'search_items' =>  esc_html__( 'Search Zone Types',   self::DOMAIN ),
					'popular_items' => esc_html__( 'Popular Zone Types',  self::DOMAIN ),
					'not_found' =>     esc_html__( 'No Zone Types Found', self::DOMAIN ),
					// todo: complete
				),
				'public' => true,
				'show_ui' => true,
				'show_in_nav_menus' => false,
			)
		);

		if ( ! term_exists( self::ZONE_TYPE_DESCRIPTION, self::TAXONOMY_ZONE_TYPE ) ) {
			wp_insert_term(
				self::ZONE_TYPE_DESCRIPTION,
				self::TAXONOMY_ZONE_TYPE,
				array(
					'description' => esc_html__( 'Typical navigation zones with a description and navigation links.', self::DOMAIN ),
				)
			);
		}
		if ( ! term_exists( self::ZONE_TYPE_STORE, self::TAXONOMY_ZONE_TYPE ) ) {
			wp_insert_term(
				self::ZONE_TYPE_STORE,
				self::TAXONOMY_ZONE_TYPE,
				array(
					'description' => esc_html__( 'Stores that may buy and sell items from characters.', self::DOMAIN ),
				)
			);
		}
		if ( ! term_exists( self::ZONE_TYPE_COMBAT, self::TAXONOMY_ZONE_TYPE ) ) {
			wp_insert_term(
				self::ZONE_TYPE_COMBAT,
				self::TAXONOMY_ZONE_TYPE,
				array(
					'description' => esc_html__( 'Zones where combat can occur.', self::DOMAIN ),
				)
			);
		}

		$this->user_id = get_current_user_id();
		if ( 0 != $this->user_id ) {

			$this->user_meta = get_user_meta( $user_id, 'mudpress_user_meta' );

			$changed_meta = false;
			foreach ( $this->default_meta as $k => $v ) {
				if ( ! isset( $this->user_meta[ $k ] ) ) {
					$this->user_meta[ $k ] = $v;
					$changed_meta = true;
				}
			}

			if ( $changed_meta ) {
				update_user_meta( $user_id, 'mudpress_user_data', $this->user_meta );
			}

		}

	}

	/**
	 * Add menu options to the dashboard, and meta boxes to the edit pages.
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'MUDPress', self::DOMAIN ),
			esc_html__( 'MUDPress', self::DOMAIN ),
			'edit_posts',
			'mudpress_menu',
			array( $this, 'generate_admin_page' )
		);

		add_meta_box(
			'mudpress-zone-meta',
			'MUDPress Zone Details',
			array( $this, 'generate_zone_meta' ),
			self::CPT_ZONE,
			'normal'
		);
	}

	public function generate_admin_page() {

	}

	/**
	 * Show HTML for the zone details stored in post meta.
	 */
	public function generate_zone_meta( $zone ) {
		$zone_id = intval( $zone->ID );
		$zone_movement = esc_html( get_post_meta( $zone->ID, 'movement', true ) );
?>
<p><b>Zone ID</b>: <?php echo( $zone_id ); ?></p>
<p><b>Movement</b>: <input type="text" name="mudpress_movement" value="<?php echo( $zone_movement ); ?>"></p>
<?php
	}

	/**
	 * Extract the zone updates from $_POST and save in post meta.
	 */
	public function save_zone_meta( $zone_id ) {
		if ( isset( $_POST[ 'mudpress_movement' ] ) ) {
			update_post_meta( $zone_id, 'movement', $_POST[ 'mudpress_movement' ] );
		}
	}

	public function display_post( $post ) {

		if ( get_post_type( $post ) == self::CPT_ZONE ) {

			$movement = get_post_meta( $post->ID, 'movement', true );
			$movement_obj = explode( ',', $movement );

			if ( count( $movement_obj ) > 0 ) {
				$append_obj = array();
				foreach ( $movement_obj as $move ) {
					$move = explode( ':', $move );
					array_push( $append_obj, '<li><a href="?post_type=' . self::CPT_ZONE . '&p=' . $move[ 1 ] . '">' . $move[ 0 ] . '</a></li>' );
				}
				$post->post_content .= '<ul>' . implode( "\n", $append_obj ) . '</ul>';
			}
		}

	}

	public function show_profile( $atts ) {
		if ( ! isset( $this->user_meta ) ) {
			return '';
		}

		$st = '<div class="mudpress_profile"><h3>Profile for ' .
			esc_html( get_user_meta( $this->user_id, 'nickname', true ) ) .
			'</h3><ul><li>Health: ' . $this->user_meta[ 'health' ] . ' / ' .
			$this->user_meta[ 'health_max' ] . '</li>';
		$st = $st . '<li>Helm: </li>';
		$st = $st . '</ul></div>';

		print_r( $this->user_meta );

		return $st;
	}

}

$wp_mudpress = new WP_MUDPress();
