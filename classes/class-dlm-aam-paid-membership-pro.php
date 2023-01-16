<?php
class DLM_AMM_PAID_MEMBERSHIP_PRO {

	const VERSION = '1.0.0';
	
	/**
	 * Holds the class object.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Load plugin text domain
		load_plugin_textdomain( 'dlm-paid-membership-pro-integration', false, dirname( plugin_basename( DLM_AAM_PMP_FILE ) ) . '/languages/' );

		if( 'ok' !== $this->core_exists() && $this->is_dlm_admin_page() ){

			add_action( 'admin_notices', array( $this, 'display_notice_core_missing' ), 8 );

		}else{
			add_filter( 'dlm_aam_group', array( $this, 'add_groups' ), 15, 1 );
			add_filter( 'dlm_aam_group_value_pmpmembership', array( $this, 'pmpmembership_group_value' ), 15 );
			add_filter( 'dlm_aam_restriction', array( $this, 'restrictions' ), 15, 1 );
			add_filter( 'dlm_aam_rest_variables', array( $this, 'rest_variables' ), 25, 1 );
			add_filter( 'dlm_aam_rule_pmpmembership_applies', array( $this, 'pmpmembership_rule' ), 15, 2 );
			add_filter( 'dlm_aam_meets_restriction', array( $this, 'pmpmembership_restrictions' ), 15, 2 );
		}
		
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return object The DLM_AMM_PAID_MEMBERSHIP_PRO object.
	 *
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof DLM_AMM_PAID_MEMBERSHIP_PRO ) ) {
			self::$instance = new DLM_AMM_PAID_MEMBERSHIP_PRO();
		}

		return self::$instance;

	}

	/**
	 * Add Paid Membership PRO to the list of rules
	 *
	 * @param [type] $groups
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function add_groups( $groups ) {
	
		$groups[] = array(
			'key'        => 'pmpmembership',
			'name'       => esc_html__( 'PMPro Membership Levels', 'dlm-paid-membership-pro-integration' ),
			'conditions' => array(
				'includes' => array(
					'restriction' => array( 'null', 'amount', 'global_amount', 'daily_amount', 'monthly_amount', 'daily_global_amount', 'monthly_global_amount', 'date', 'pmpmembershiplength' ),
				),
			),
			'field_type' => 'select',
		);

		return $groups;
	}

	/**
	 * Returns all membership levels.
	 *
	 * @return OBJECT
	 *
	 * @since 1.0.0
	 */
	private function pmp_get_membership_levels(){
		global $wpdb;
		$sqlQuery = "SELECT * FROM $wpdb->pmpro_membership_levels ";
		$levels = $wpdb->get_results($sqlQuery, OBJECT );

		return $levels;
	}


	/**
	 * Returns current user's membership data. Returns array if $filter is not sent, string otherwise.
	 * Possible values for $filter are: user_id, membership_id, code_id, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, status, startdate, enddate, modified
	 *
	 * @param string $filter
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	private function pmp_get_user_membership( $filter = false ){
		global $wpdb;
		$current_user = wp_get_current_user();

		$sqlQuery = "SELECT * FROM $wpdb->pmpro_memberships_users WHERE user_id = $current_user->ID";
		$membership = $wpdb->get_results($sqlQuery, OBJECT );

		if( ! isset( $membership[0] ) ){
			return array();
		}

		if( $filter ){
			return isset( $membership[0]->$filter ) ? $membership[0]->$filter : $membership[0];
		}
		return $membership[0];
	
	}

	/**
	 * Add Paid Membership PRO groups to group values
	 *
	 * @param object $return
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function pmpmembership_group_value( $return ) {

		// Paid Membership PRO groups.
		$groups[] = array(
			'key'  => 'null',
			'name' => esc_html__( 'None', 'dlm-paid-membership-pro-integration' ),
		);

		global $wpdb;
		$pmp_membership_levels = $this->pmp_get_membership_levels();
		// check, loop & add to $roles.
		if ( ! empty( $pmp_membership_levels ) ) {
			foreach ( $pmp_membership_levels as $group ) {
				$groups[] = array(
					'key'  => $group->id,
					'name' => $group->name,
				);
			}
		}

		return wp_send_json( $groups );
	}

	/**
	 * Add Paid Membership PRO to restrictions
	 *
	 * @param array $restrictions
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function restrictions( $restrictions ) {
		$restrictions[] = array(
			'key'  => 'pmpmembershiplength',
			'name' => esc_html__( 'Membership Length', 'dlm-advanced-access-manager' ),
			'type' => esc_html( 'input' ),
			'conditions' => array(
				'includes' => array(
					'group' => array(  'null', 'role', 'user', 'ip' )
				)
			),
		);
		foreach ( $restrictions as $key => $restriction ) {
			if ( isset( $restriction['conditions']['includes']['group'] ) ) {
				$restrictions[ $key ]['conditions']['includes']['group'][] = 'pmpmembership';
			}
		}

		 return $restrictions;
	}

	/**
	 * Add Paid Membership PRO to rest variables
	 *
	 * @param [type] $rest_variables
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function rest_variables( $rest_variables ) {

        $vars['str_pmpmembership'] = esc_html__( 'PMPro Membership Levels', 'dlm-paid-membership-pro-integration' );

		// Paid Membership PRO groups.
		$groups = array();

		// Get Paid Membership PRO groups.
		$pmp_membership_levels = $this->pmp_get_membership_levels( );
		// check, loop & add to $roles.
		if ( ! empty( $pmp_membership_levels ) ) {
			foreach ( $pmp_membership_levels as $group ) {
				$groups[] = array(
					'key'  => $group->id,
					'name' => $group->name,
				);
			}
		}

		$rest_variables['pmpmembership_groups'] = json_encode( $groups );

		return $rest_variables;
	}

	/**
	 * Add rule for Paid Membership PRO
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function pmpmembership_rule( $applies, $rule ) {
		
		$current_user = wp_get_current_user();
		if ( ( $current_user instanceof WP_User ) && 0 != $current_user->ID ) {
			if ( !empty( $this->pmp_get_user_membership( 'membership_id' ) ) && absint( $rule->get_group_value() ) === absint( $this->pmp_get_user_membership( 'membership_id' ) ) ) {
				
				$applies = true;
			}
		}

		return $applies;
	}

	/**
	 * Counts the number of downloads in the membership period.
	 *
	 * @return absint
	 *
	 * @since 1.0.0
	 */
	public function pmp_membership_amount_downloaded(){
		global $wpdb;
		$current_user = wp_get_current_user();
		
		$exclude_query = "SELECT `post_id` FROM {$wpdb->postmeta} WHERE `meta_key`='_except_from_restriction' AND `meta_value`=1";
		$exclude_ids = $wpdb->get_col( $exclude_query );

		if( empty( $exclude_ids ) ){
			$exclude_ids[] = 0;
		}

		$exclude_ids_in = '(' . implode( ',', $exclude_ids ) . ')';

		$membership = $this->pmp_get_user_membership();

		if ( empty( $membership ) || 'expired' === $membership->status ) {
			$meets_restriction = false;
		}

		if( strtotime( $membership->enddate ) >= 1 ){
			$exp_date = $membership->enddate;
			$start_date = $membership->startdate;

			$amount_downloaded = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->download_log} WHERE `user_id` = %s AND `download_status` IN ( 'completed', 'redirected' ) AND `download_date` >= '%s' AND `download_date` <= '%s' AND download_id NOT IN {$exclude_ids_in}", $current_user->ID, $start_date, $exp_date ) ) );
	
		}else{
			$start_date = $membership->startdate;

			$amount_downloaded = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->download_log} WHERE `user_id` = %s AND `download_status` IN ( 'completed', 'redirected' ) AND `download_date` >= '%s' AND download_id NOT IN {$exclude_ids_in}", $current_user->ID, $start_date) ) );
	
		}

		return $amount_downloaded;
	}

	/**
	 * Checks if the user exceeded the number of dwonloads limit. 
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function pmpmembership_restrictions( $meets_restrictions, $rule){

		if( 'pmpmembershiplength' == $rule->get_restriction() ){

			// get amount of times this IP address downloaded file
			$amount_downloaded = $this->pmp_membership_amount_downloaded();

			// check if times download is equal to or smaller than allowed amount
			if ( $amount_downloaded >= absint( $rule->get_restriction_value() ) ) {
				// nope
				$meets_restrictions = false;
			}
		}
		return $meets_restrictions;
	}

	/**
	 * Check if Download Monitor & Download Monitor Advanced Access Manager are installed and active.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function core_exists() {

		$missing = array();

		// check for Download Monitor
		if( !defined( 'DLM_VERSION' ) ){
			$missing[] = 'missing_dlm';
		}

		// check for DLM Advanced Access Manager
		if( ! class_exists( 'DLM_Advanced_Access_Manager' ) ){
			$missing[] = 'missing_aam';
		}
		
		// check for Paid Membership PRO
		if ( !defined( 'PMPRO_VERSION' ) ){
			$missing[] = 'missing_pmp';
		}

		if ( 3 == count( $missing ) ) {
			  return 'missing_all';
		}

		if ( 2 == count( $missing ) ) {
			if ( ! array_diff( array( 'missing_dlm', 'missing_aam' ), $missing ) ) {
				return 'missing_dlm_amm';
			}
			if ( ! array_diff( array( 'missing_dlm', 'missing_pmp' ), $missing ) ) {
				return 'missing_dlm_pmp';
			}
			if ( ! array_diff( array( 'missing_aam', 'missing_pmp' ), $missing ) ) {
				return 'missing_amm_pmp';
			}
		}

		if ( 1 == count( $missing ) ) {
			return $missing[0];

		}

		return 'ok';
	}

	/**
	 * Core notice
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function display_notice_core_missing() {

		$dlm_link = '<a href="https://wordpress.org/plugins/download-monitor/" target="_blank"><strong>' . __( 'Download Monitor', 'dlm-paid-membership-pro-integration' ) . '</strong></a>';
		$pmp_link = '<a href="https://wordpress.org/plugins/paid-memberships-pro/" target="_blank"><strong>' . __( 'Paid Membership PRO', 'dlm-paid-membership-pro-integration' ) . '</strong></a>';
		$aam_link = '<a href="https://www.download-monitor.com/extensions/advanced-access-manager/?utm_source=download-monitor&utm_medium=rcp-integration&utm_campaign=upsell" target="_blank"><strong>' . __( 'Download Monitor - Advanced Access Manager', 'dlm-paid-membership-pro-integration' ) . '</strong></a>';

		$core_exists = $this->core_exists();
		$notice_messages = array(
			'missing_dlm' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s in order to work.', 'dlm-paid-membership-pro-integration' ), $dlm_link ),
			'missing_aam'	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s addon in order to work.', 'dlm-paid-membership-pro-integration' ), $aam_link ),
			'missing_pmp' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s in order to work.', 'dlm-paid-membership-pro-integration' ), $pmp_link ),
			'missing_dlm_amm' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s & %s addon in order to work.', 'dlm-paid-membership-pro-integration' ), $dlm_link, $aam_link ),
			'missing_dlm_pmp' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s & %s plugin in order to work.', 'dlm-paid-membership-pro-integration' ), $dlm_link, $pmp_link ),
			'missing_amm_pmp' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s addon & %s plugin in order to work.', 'dlm-paid-membership-pro-integration' ), $aam_link, $pmp_link ),
			'missing_all' 	=> sprintf( __( 'Download Monitor & Restrict content integration requires %s & %s addon & %s plugin in order to work.', 'dlm-paid-membership-pro-integration' ), $dlm_link, $aam_link, $pmp_link ),
		);
		$class = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $notice_messages[ $core_exists ] ) ); 


	}

	/**
	 * Check if we are on a dlm page
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function is_dlm_admin_page() {
		global $pagenow;

		if( 'plugins.php' === $pagenow || ( isset( $_GET['post_type'] ) && 'dlm_download' === $_GET['post_type'] ) ){
			return true;
		}

		return false;
	}

}