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

	const DOMAIN = 'mudpress';

	/**
	 * The custom post type string for zones.
	 */
	const CPT_ZONE = 'mudpress-zone';

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

		add_action( 'save_post', array( $this, 'save_zone_meta' ) );
	}

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
	}

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

	public function generate_zone_meta( $zone ) {
		$zone_id = intval( $zone->ID );
?>
<p><b>Zone ID</b>: <?php echo( $zone_id ); ?></p>
<?php
	}

	public function save_zone_meta( $zone ) {
		if ( self::CPT_ZONE == $zone->post_type ) {
			// update post meta
		}
	}
}

$wp_mudpress = new WP_MUDPress();
