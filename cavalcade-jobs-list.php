<?php
/**
 * Cavalcade Jobs List Class
 */
class Jobs_Admin_List extends WP_List_Table {
    	public $cavalcade_per_page = 20;
	public $logs_nonce;
	public $jobs_nonce;
	public $manual_run_nonce;
	public $reschedule_nonce;
	public $delete_nonce;
	public $cavalcade_list_screen;
	public $logs_popup_url;
	public $cavalcade_page_slug;
	/** Class constructor */
	public function __construct() {
		global $cavalcade_utilities;
		parent::__construct( [
			'singular' => __( 'Job', 'cavalcade' ),
			'plural'   => __( 'Jobs', 'cavalcade' ),
			'ajax'     => false
		] );
		$cavalcade_list_screen      = get_current_screen();
		$this->cavalcade_page_slug  = ( isset( $_REQUEST['page'] ) ) ? esc_attr( $_REQUEST['page'] ) : '';
		$this->logs_nonce           = $cavalcade_utilities->__n( 'logs_nonce' );
		$this->jobs_nonce           = $cavalcade_utilities->__n( 'jobs_nonce' ); 
		$this->manual_run_nonce     = $cavalcade_utilities->__n( 'manual_run_nonce' );
		$this->reschedule_nonce     = $cavalcade_utilities->__n( 'reschedule_nonce' );
		$this->delete_nonce         = $cavalcade_utilities->__n( 'delete_nonce' );
		$this->cavalcade_per_page   = $cavalcade_list_screen->get_option( 'per_page', 'default' );
		$this->logs_popup_url       = admin_url('admin-ajax.php?nonce=' . $this->logs_nonce . '&action=' . $cavalcade_utilities->logs_ajax_action . '&&job_id=') ;
	}
	protected static function get_table() {
		return $cavalcade_utilities->get_jobs_table();
	}
	/**
	 * Record Count
	 */
	public static function record_count() {
		global $wpdb, $cavalcade_utilities;
    	return $wpdb->get_var( $cavalcade_utilities->__sql( 'count(*)' ) );
	}
	/**
	 * Get Records
	 */
	public static function get_records( $per_page = 5, $page_number = 1 ) {
		global $wpdb, $cavalcade_utilities;		
		$result = $wpdb->get_results( $cavalcade_utilities->__sql( '*', $per_page, $page_number ), 'ARRAY_A' );
		return $result;
	}
	/**
	 * Get Columns
	 */
	function get_columns() {
		$columns = [
            'cb'       => '<input type="checkbox" />',
            'id'       => __( 'Job', 'cavalcade' ),
			'hook'     => __( 'Hook', 'cavalcade' ),
			'schedule' => __( 'Schedule', 'cavalcade' ),
            'status'   => __( 'Status', 'cavalcade' ),
            'args'     => __( 'Arguments', 'cavalcade' ),
            'start'    => __( 'Start (UTC)', 'cavalcade' ),
            'nextrun'  => __( 'Next Run (UTC)', 'cavalcade' ),
            'logs'     => __( 'Logs', 'cavalcade' ),
		];
		return $columns;
	}
	/**
	 * Get Sortable Columns
	 */
	public function get_sortable_columns() {
		$sortable_columns = [
            'id'     => [ 'id', true ],
			'hook'     => [ 'hook', true ],
            'status'   => [ 'status', false ],
            'schedule' => [ 'schedule', false ],
            'start'    => [ 'start', false ],
            'nextrun'  => [ 'nextrun', false ],
        ];
		return $sortable_columns;
	}
	/**
	 * Get Bulk Actions
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];
		return $actions;
	}
	/**
	 * Prepare Items
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();
		$this->process_bulk_action();
		$per_page     = $this->get_items_per_page( 'jobs_per_page', $this->cavalcade_per_page );
		$current_page = $this->get_pagenum();		
		$total_items  = $this->record_count();
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
		$this->items = $this->get_records( $per_page, $current_page );
	}
	/**
	 * No Jobs
	 */
    public function no_items() {
		_e( 'No jobs avaliable.', 'cavalcade' );
	}
	/**
	 * Column Default
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return $this->column_name( $item );
            case 'hook':
			case 'status':
                return $item[ $column_name ];
            case 'schedule':
                return empty( $item[ $column_name ] ) ? 'single': $item[ $column_name ];
            case 'args':
                $unserialized = unserialize( $item[ $column_name ] );
                return !empty( $unserialized ) ? implode( ',', $unserialized ) : '---';
            case 'logs':
                return sprintf( '<a href="%s?TB_iframe=true&width=400&height=200" class="thickbox button">View Log</a>', $this->logs_popup_url . $item['id'] );
			default:
				return $item[ $column_name ];
		}
	}
	/**
	 * Call Bulk Action Callback
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
		);
	}
	/**
	 * Name Column Mehod
	 */
	function column_name( $item ) {
		global $cavalcade_utilities;
		$title = '<strong>JOB ID : ' . $item['id'] . '</strong>';
		$jobid = absint( $item['id'] );
		$actions = [
			'delete'     => sprintf( '<a href="?page=%s&action=%s&job=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure?\')" >Delete Now</a>', $this->cavalcade_page_slug, 'delete-now', $jobid, $this->delete_nonce ),
			'manual-run' => sprintf( '<a href="?page=%s&action=%s&job=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure?\')" >Run in ' . $cavalcade_utilities->manual_latency . 'sec </a>', $this->cavalcade_page_slug, 'manual-run', $jobid, $this->manual_run_nonce ),
			'reschedule' => sprintf( '<a href="?page=%s&action=%s&job=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure?\')" >Reschedule</a>', $this->cavalcade_page_slug, 'reschedule', $jobid, $this->reschedule_nonce )
		];
		return $title . $this->row_actions( $actions );
	}
	/**
     * Filter 
     */
    public function filters(){
		global $cavalcade_utilities;
		$cavalcade_utilities->__filters();
    }
	/**
	 * Bulk Actions
	 */
	public function process_bulk_action() {
		global $cavalcade_utilities;
		if ( 'delete-now' === $this->current_action() ) {
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );
			if (  wp_verify_nonce( $nonce, 'delete_nonce' ) ) {
				$cavalcade_utilities->__delete_job_by_id( $_GET['job'] );
				return;
			}
		}
		if ( 'manual-run' === $this->current_action() ) {
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );
			if (  wp_verify_nonce( $nonce, 'manual_run_nonce' ) ) {
				$cavalcade_utilities->__manual_init( $_GET['job'] );
				return;
			}
		}
		if ( 'reschedule' === $this->current_action() ) {
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );
			if (  wp_verify_nonce( $nonce, 'reschedule_nonce' ) ) {
				$cavalcade_utilities->__reschedule( $_GET['job'] );
				return;
			}			
		}
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {
			$delete_ids = esc_sql( $_POST['bulk-delete'] );
			if( is_array( $delete_ids ) && ! empty( $delete_ids ) ){
				foreach ( $delete_ids as $id ) {
					$cavalcade_utilities->__delete_job_by_id( $id );
				}
				return;
			}
		}
	}
}
