<?php 
/**
 * Main Class Plugin
 */
class Cavalcade_List_Plugin {
	static $instance;
	public $jobs_admin_list;
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}
	public static function set_screen( $status, $option, $value ) {
		return $value;
	}
	public function plugin_menu() {
		$hook = add_menu_page(
			'Cavalcade Jobs',
			'Cavalcade Jobs',
			'manage_options',
			'cavalcade_jobs',
			[ $this, 'plugin_settings_page' ]
		);
		add_action( "load-$hook", [ $this, 'screen_option' ] );
	}
	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
        	add_thickbox();
		?>
		<div class="wrap">
			<h2>Cavalcade Jobs</h2>
			<div id="poststuff">
				<div id="post-body">
					<div id="post-body-content">
    						<div class="meta-box-sortables ui-sortable">
                            				<?php $this->jobs_admin_list->filters();?>
							<form method="post">
								<?php
								$this->jobs_admin_list->prepare_items();
								$this->jobs_admin_list->display(); ?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php
	}
	/**
	 * Screen options
	 */
	public function screen_option() {
		$option = 'per_page';
		$args   = [
			'label'   => 'Jobs',
			'default' => 20,
			'option'  => 'jobs_per_page'
		];
		add_screen_option( $option, $args );
        	$this->jobs_admin_list = new Jobs_Admin_List();
	}
	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
