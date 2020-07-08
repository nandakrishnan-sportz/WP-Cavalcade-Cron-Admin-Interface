<?php
/**
 * Cavalcade Logs List Class
 */
class Cavalcade_Utilities {
    public $cavalcade_jobs_table;
    public $cavalcade_logs_table;
    public $manual_latency   = 30;
    public $logs_ajax_action = 'cc_logs';
    public $logs_count = 50;
    public $cavalcade_page_slug;
    public $cavalcade_manual_hook = 'cavalcade_manual_job_re_run';
    public function __construct(){
        $this->cavalcade_jobs_table = $this->get_jobs_table();
        $this->cavalcade_logs_table = $this->get_logs_table();
        $this->cavalcade_page_slug  = ( isset( $_REQUEST['page'] ) ) ? esc_attr( $_REQUEST['page'] ) : '';
        add_action( 'cavalcade_manual_job_re_run', [ $this, '__manual_run' ] );
        add_action('wp_ajax_' . $this->logs_ajax_action, [ $this, '__job_logs_by_id' ] );
        add_action('wp_ajax_nopriv_' . $this->logs_ajax_action, [ $this, '__job_logs_by_id' ] );
    }
    /**
     * Get Jobs Table
     */
    protected static function get_jobs_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'cavalcade_jobs';
    }
    /**
     * Get Logs Table
     */
    protected static function get_logs_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'cavalcade_jobs';
    }
    /**
     * Get SQL Query
     */
    public function __sql( $type = '*', $per_page = 5, $page_number = 1 ){
        $sql = "SELECT {$type} FROM {$this->cavalcade_jobs_table}";
        $where = [];
        if ( isset( $_REQUEST['cc_hook'] ) && !empty( trim( $_REQUEST['cc_hook'] ) ) ) {
            $cc_hook = trim( $_REQUEST['cc_hook'] );
            $where[] = "hook like '%" . $cc_hook . "%'";
        }
        if ( isset( $_REQUEST['cc_next_run'] ) && !empty( trim( $_REQUEST['cc_next_run'] ) ) ) {
            $cc_next_run = trim( $_REQUEST['cc_next_run'] );
            $where[] = "nextrun like '%" . $cc_next_run . "%'";
        }
        if ( isset( $_REQUEST['cc_status'] ) && !empty( trim( $_REQUEST['cc_status'] ) ) ) {
            $cc_status = trim( $_REQUEST['cc_status'] );
            $where[] = "status = '" . $cc_status . "'";
        }
        if ( isset( $_REQUEST['cc_schedule'] ) && !empty( trim( $_REQUEST['cc_schedule'] ) ) ) {
            $cc_schedule = trim( $_REQUEST['cc_schedule'] );
            $where[] = "schedule = '" . $cc_schedule . "'";
        }
        if ( is_multisite() ) {
            $where[] = "site = " . get_current_blog_id();
        }
        if( ! empty( $where) ){
            $where_clause = " where " . implode( " and ", $where );
            $sql .= " $where_clause ";
        }
        if( $type === '*' ){
            if ( ! empty( $_REQUEST['orderby'] ) ) {
                $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
                $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
            }
            $sql .= " LIMIT $per_page";
            $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;    
        } 
        return $sql;
    }
    /**
     * Create Nonce
     */
    public function __n( $string ) {
		return wp_create_nonce( $string );
    }
    /**
     * Get Job Details
     */
    public function __get_job_by_id( $job ){
        global $wpdb;
        $job = absint( $job );
        $suppress = $wpdb->suppress_errors();
        $job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->base_prefix . 'cavalcade_jobs WHERE id = %d', $job ) );
        $wpdb->suppress_errors( $suppress );
        if ( ! $job ) {
            return null;
        }
        return $job;
    }
    /**
     * Delete Job Details
     */
    public function __delete_job_by_id( $job ){
        global $wpdb;
        if( ! absint( $job ) ) {
            $this->__notify( 'error', 'No ID', __( 'Try again later..', 'cavalcade'  ) );
            return ;
        }
        $job = $this->__get_job_by_id( $job );
		if ( $job->status === 'running' ) {
            $this->__notify( 'error', $job->id, __( 'Cannot delete running jobs', 'cavalcade'  ) );
            return ;
		}
		$result = $wpdb->delete(
			$this->cavalcade_jobs_table,
			[ 'id' => $job->id ],
			[ '%d' ]
		);
		wp_cache_set( 'last_changed', microtime(), 'cavalcade-jobs' );
        wp_cache_delete( "job::{$job->id}", 'cavalcade-jobs' );
        $this->__notify( 'success', $job->id, __( 'Deleted Successfully', 'cavalcade' ) );
        return ;
    }

    /**
     * Job Logs by ID
     */
    public function __job_logs_by_id()
    {
        check_ajax_referer( 'logs_nonce', 'nonce');
        $job_id = ( isset( $_REQUEST['job_id'] ) && absint( $_REQUEST['job_id'] ) ) ? absint( $_REQUEST['job_id'] ) : false;
        if( ! $job_id ) {
           echo  _e( 'No job id supplied.', 'sp' ); die;
        }
        echo sprintf( '<h3 class="h3-jobs">CAVALCADE JOB LOGS FOR ID : %d</h3>', $job_id );
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}cavalcade_logs where job = {$job_id} ORDER BY id DESC limit {$this->logs_count} ");
        if( ! is_wp_error( $result ) && is_array( $result ) && sizeof( $result ) >= 1 ) {
            echo '<style>.h3-jobs{text-align:center;}.cc_logs{width: 100%;padding: 10px;text-transform: capitalize;border-bottom: 1px solid #d2caca;margin: 5px auto;}</style>';
            foreach( $result as $log ) {
                echo sprintf( '<table class="cc_logs"><tr><td>%s</td></tr><tr><td>%s</td></tr><tr><td>%s</td></tr></table>', $log->status, $log->content, $log->timestamp );
            }
        } else  {
            echo  _e( 'Nothing to list.', 'sp' ); die;
         }
        die();
    }
    /**
     * Manual Cron Init
     */
    public function __manual_init( $job ){
        global $wpdb;
        $job = $this->__get_job_by_id( $job );
        if( $job ) {
            $nextrun = date( 'Y-m-d H:i:s', time() + $this->manual_latency );
            $status  = 'waiting';
            $wpdb->update(  $wpdb->base_prefix . 'cavalcade_jobs', [
                'status'  => $status, 
                'nextrun' => $nextrun,
                ],
                [ 'id' => $job->id ]
            );
            $this->__notify( 'success', $job->id, __( "Will be initiated in {$this->manual_latency} seconds", 'cavalcade'  ) );
            return true;
        }
        $this->__notify( 'error', 'No ID', __( 'Try again later..', 'cavalcade'  ) );
        return false;
    }
    /**
     * Reschedule
     */
    public function __reschedule( $job ){
        global $wpdb;
        $job = $this->__get_job_by_id( $job );
        if( $job ) {
            $nextrun = date( 'Y-m-d H:i:s', strtotime( $job->nextrun ) + $job->interval );
            $status  = 'waiting';
            $wpdb->update(  $wpdb->base_prefix . 'cavalcade_jobs', [
                'status'  => $status, 
                'nextrun' => $nextrun,
                ],
                [ 'id' => $job->id ]
            );
            $this->__notify( 'success', $job->id, __( 'Successfully Rescheduled to ' . $nextrun, 'cavalcade'  ) );
            return true;
        }
        $this->__notify( 'error', 'No ID', __( 'Try again later..', 'cavalcade'  ) );
        return false;
    }
    /**
     * Admin Notices 
     */
    public function __notify( $type = 'error', $id = false, $msg = 'Try again later..' ){
        if( $id ) {
            echo sprintf( '<div class="notice notice-' . $type . ' is-dismissible"> <p><strong>Job ID :%s - %s</strong>.</p></div><script>window.history.pushState( "", "", "?page=%s" );</script>', $id, $msg, $this->cavalcade_page_slug );
        }
        return true;
    }
    /**
     * Filter 
     */
    public function __filters(){
       ?>
       <style>.time-beat{ font-weight:bolder; }.cc-filters input, .cc-filters button, .cc-filters select, .cc-filters a, .cc-filters td{ width:100%; text-align:center;}</style>
       <form>
		    <input type="hidden" name="page" value="<?php echo $this->cavalcade_page_slug;?>" >
            <table class="wp-list-table widefat fixed striped cc-filters">
                <thead>
                    <tr>
                        <td><?php $this->__hook_input();?></td>
                        <td><?php $this->__date_input();?></td>
                        <td><select name="cc_status" class="alignleft"><?php $this->__statuses_options()?></select></td>
                        <td><select name="cc_schedule" class="alignleft"><?php $this->__schedule_options(); ?></select></td>
                        <td><button type="submit" name="cc_submit" value="1" class="button alignleft">Apply Filter</button></td>
                        <td><a href="<?php echo '?page=' . $this->cavalcade_page_slug;?>"class="button alignleft">Reset Filter</a></td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="utcclock" colspan="3"></td>
                        <td id="_wpclock" colspan="3"></td>
                    </tr>
                </tbody>
            </table>    
        </form>
        <script>
			setInterval( function(){
				var x = new Date()
				document.getElementById('utcclock').innerHTML = 'UTC Time: ' + x.toUTCString();
				document.getElementById('_wpclock').innerHTML = 'Browser Time: ' + x;
                document.getElementById('utcclock').classList.toggle("time-beat");
                document.getElementById('_wpclock').classList.toggle("time-beat");
			}, 1000);
		</script>
       <?php
    }
    /**
     * Get Cavalcade Date 
     */
    public function __date_input(){
        $cc_next_run = '';
        if ( isset( $_REQUEST['cc_next_run'] ) && (bool)( $_REQUEST['cc_next_run'] ) ) {
            $cc_next_run = trim( $_REQUEST['cc_next_run'] );
		}
        echo sprintf( '<input type="date" class="alignleft" name="cc_next_run" max="%s" value="%s"/>', date( "Y-m-d" ), $cc_next_run );
    }
    /**
     * Get Cavalcade Date 
     */
    public function __hook_input(){
        $cc_hook = '';
        if ( isset( $_REQUEST['cc_hook'] ) && (bool)( $_REQUEST['cc_hook'] ) ) {
            $cc_hook = trim( $_REQUEST['cc_hook'] );
		}
        echo sprintf( '<input type="text" placeholder="%s" class="alignright" name="cc_hook" value="%s" style="%s"/>', 'Type..', $cc_hook, "width:100%;" );
    }
    /**
     * Get Cavalcade status
     */
    public function __statuses_options(){
        $selected = '0';
        if ( isset( $_REQUEST['cc_status'] ) && (bool)( $_REQUEST['cc_status'] ) ) {
            $selected = trim( $_REQUEST['cc_status'] );
		}
        $status_list = [
            '0'         => 'Select Status',
            'running'   => 'Running',
            'waiting'   => 'Waiting',
            'completed' => 'Completed',
            'failed'    => 'Failed',
        ];
        foreach( $status_list as $value => $status ){
            $selected_check = '';
            if( $selected === $value ){
                $selected_check = 'selected';
            }
            echo sprintf( '<option %s value="%s" />%s</option>', $selected_check, $value, $status );
        }
    }
    /**
     * Get Cavalcade Schedule
     */
    public function __schedule_options(){
        $selected = '0';
        if ( isset( $_REQUEST['cc_schedule'] ) && (bool)( $_REQUEST['cc_schedule'] ) ) {
            $selected = trim( $_REQUEST['cc_schedule'] );
		}
        $status_list = wp_get_schedules();
        echo '<option value="0" />Select Schedule</option>';
        foreach( $status_list as $value => $status ){
            $selected_check = '';
            if( $selected === $value ){
                $selected_check = 'selected';
            }
            echo sprintf( '<option %s value="%s" />%s</option>', $selected_check, $value, $status['display'] );
        }
    }
}
