<?php
/**
 * Plugin Name:       Email Scheduling
 * Description:       This plugin include cronjob to send email to the users and provide WYSIWYG editor to generate custom email template.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.0
 * Author:            Krunal Bhimajiyani
 * Author URI:        https://github.com/KrunalKB
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package emsc-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.

	exit;
}

/**
 *  Class Emsc_Setup
 */
class Emsc_Setup {

	/**
	 * Construct function
	 */
	public function __construct() {

		/* Define constants */
		define( 'EMSC_URL', plugin_dir_url( __FILE__ ) );
		define( 'EMSC_PATH', plugin_dir_path( __FILE__ ) );
		define( 'EMSC_FILE', __FILE__ );

		/* Use wp_mail_content_type hook to change the content type */
		add_filter( 'wp_mail_content_type', array( $this, 'emsc_set_content_type' ) );

		/* Use admin_menu hook for adding custom admin menu */
		add_action( 'admin_menu', array( $this, 'emsc_register_admin' ) );

		/* Load up files for test email page */
		add_action( 'admin_enqueue_scripts', array( $this, 'emsc_email_script' ) );

		/* Load up files for bulk email page */
		add_action( 'admin_enqueue_scripts', array( $this, 'emsc_template_script' ) );

		/* Execute ajax callback function */
		add_action( 'wp_ajax_email_template_hook', array( $this, 'emsc_email_template' ) );
		add_action( 'wp_ajax_my_email_hook', array( $this, 'emsc_email_test' ) );
		add_action( 'wp_ajax_my_ajax_hook', array( $this, 'emsc_schedule_cron' ) );

		/* Create a custom schedule for one minute */
		add_filter( 'cron_schedules', array( $this, 'emsc_cron_job_interval' ) );

		/* Schedule custom cron */
		add_action( 'custom_send_email', array( $this, 'emsc_generate_email' ) );
	}

	/**
	 * Set content type 'text/html'
	 *
	 * @since 1.0.0
	 *
	 * @param string $content_type content type.
	 */
	public function emsc_set_content_type( $content_type ) {
		return 'text/html';
	}

	/**
	 * Register admin menu page for bulk email
	 *
	 * @since 1.0.0
	 */
	public function emsc_register_admin() {
		$GLOBALS['emsc-email-template'] = add_menu_page(
			'Bulk Email',
			'Bulk Email',
			'manage_options',
			'emsc-template.php',
			array( $this, 'emsc_template_content' ),
			'dashicons-email-alt',
			111
		);

		$GLOBALS['emsc-email-test'] = add_submenu_page(
			'emsc-template.php',
			'Email Test',
			'Email Test',
			'manage_options',
			'emsc-email-test.php',
			array( $this, 'emsc_testing_content' ),
			'dashicons-share-alt2',
		);
	}

	/**
	 * Display callback function for bulk email page
	 *
	 * @since 1.0.0
	 */
	public function emsc_template_content() {
		// check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
			<h2><?php esc_html_e( 'Email Template:' ); ?></h2>
		<?php

		if ( empty( get_option( 'email_content' ) ) ) {
			$default_content = '';
		} else {
			$default_content = get_option( 'email_content' );
		}
		$editor_id       = 'mail_template_id';
		$option_name     = 'mail_template';
		$default_content = html_entity_decode( $default_content );

		/* Create wysiwyg editor for email template */

		wp_editor(
			$default_content,
			$editor_id,
			array(
				'textarea_name' => $option_name,
				'media_buttons' => false,
				'editor_height' => 350,
				'teeny'         => true,
			)
		);
		?>
			<br> 
			<button id="saveEmail"><?php esc_html_e( 'Save' ); ?></button>
			<br>
			<div class="response"></div>
			<br><br>  
			<span><b><?php esc_html_e( 'Send mail to user:' ); ?></b></span><br><br>
			<button id="sendEmail"><?php esc_html_e( 'Send Email' ); ?></button>
			<div class="resp"></div>
		<?php
	}

	/**
	 * Display callback function for email test page
	 *
	 * @since 1.0.0
	 */
	public function emsc_testing_content() {
		?>
		<h2><?php esc_html_e( 'Send a Test Email' ); ?></h2>
		<hr>
		<form class="emailForm">
			<span class="field"><?php esc_html_e( 'Send to:' ); ?></span>
			<input type="email" id="email" class="email">
			<p class="desc"><?php esc_html_e( 'Enter email address where test email will be sent.' ); ?></p>  
			<hr><br>
			<button class="email_btn"><?php esc_html_e( 'Send Email' ); ?></button>
			<img 
				src="<?php echo esc_attr( plugin_dir_url( __FILE__ ) . 'assets/image/load.gif' ); ?>" 
				class="loader" 
				alt="Loader"
				height=20 
				width=20 
				style="margin-left:10px;"
			>
		</form>
		<br>
		<div class="msg"></div>
		<?php
	}

	/**
	 * Enqueue files for admin menu(bulk email)
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook match with global variable.
	 */
	public function emsc_template_script( $hook ) {
		if ( $GLOBALS['emsc-email-template'] === $hook ) {
			wp_enqueue_script(
				'email_js',
				plugin_dir_url( __FILE__ ) . 'assets/js/bulk-email.js',
				array( 'jquery' ),
				1.0,
				true
			);
			wp_localize_script(
				'email_js',
				'myVar',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'emsc-template-token' ),
				)
			);
			wp_enqueue_style(
				'email-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/bulk-email.css',
				array(),
				'1.0.0',
				'all'
			);
		}
	}

	/**
	 * Enqueue files for admin submenu(test email)
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook match with global variable.
	 */
	public function emsc_email_script( $hook ) {
		if ( $GLOBALS['emsc-email-test'] === $hook ) {
			wp_enqueue_script(
				'test_js',
				plugin_dir_url( __FILE__ ) . 'assets/js/test-email.js',
				array( 'jquery' ),
				1.0,
				true
			);
			wp_localize_script(
				'test_js',
				'myVar',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'emsc-testmail-token' ),
				)
			);
			wp_enqueue_style(
				'test-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/test-email.css',
				array(),
				'1.0.0',
				'all'
			);
		}
	}

	/**
	 * Ajax callback for email template
	 *
	 * @since 1.0.0
	 */
	public function emsc_email_template() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'emsc-template-token' ) ) {
			$content        = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
			$update_content = update_option( 'email_content', $content );
		}
	}

	/**
	 * Ajax callback for test email
	 *
	 * @since 1.0.0
	 */
	public function emsc_email_test() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'emsc-testmail-token' ) ) {
			$usr_email      = filter_input( INPUT_POST, 'email', FILTER_VALIDATE_EMAIL );
			$email_template = html_entity_decode( get_option( 'email_content' ) );
			$var_content    = str_replace( '%', '', $email_template );
			wp_mail( $usr_email, 'Testing', $var_content );
		}
	}

	/**
	 * Create cron timer of one minute
	 *
	 * @since 1.0.0
	 *
	 * @param array $schedules An array of non-default cron schedules.
	 * @return array Filtered array of non-default cron schedules.
	 */
	public function emsc_cron_job_interval( $schedules ) {
		if ( ! isset( $schedules['one_minute'] ) ) {
			$schedules['one_minute'] = array(
				'interval' => 60,
				'display'  => __( 'Every one minute' ),
			);
			return $schedules;
		}
	}

	/**
	 * Callback function for cron event
	 *
	 * @since 1.0.0
	 */
	public function emsc_generate_email() {
		$bulk_user_id        = get_transient( 'bulk_user_email' );                        // GETTING ALL USER-ID FROM DATABASE.
		$bulk_email_track    = get_transient( 'bulk_email_track' );
		$custom_mail_content = html_entity_decode( get_option( 'email_content' ) );       // GETTING TEMPLATE CONTENT FROM DATABASE.

		$query_info = new WP_User_Query( array( 'include' => $bulk_user_id ) );           // GETTING USER DETAIL.
		$users_info = $query_info->results;

		if ( ! empty( $users_info ) ) {
			$ary_chunk = array_chunk( $users_info, 3 );                                   // CHUNK WHOLE ARRAY IN 3 ELEMENTS.
			$end_key   = array_key_last( $ary_chunk[ $bulk_email_track ] );

			foreach ( $ary_chunk[ $bulk_email_track ] as $key => $info ) {
				$email_body = str_replace( '%User%', $info->display_name, $custom_mail_content );
				wp_mail( $info->user_email, 'Testing', $email_body );

				if ( $key == $end_key ) {
					set_transient( 'bulk_email_track', $bulk_email_track + 1, 60 * 60 * 24 );
				}
			}

			// IF ARRAY KEY NOT EXIST THEN UNSCHEDULE EVENT.
			if ( ! array_key_exists( $bulk_email_track + 1, $ary_chunk ) ) {
				wp_clear_scheduled_hook( 'custom_send_email' );
			}
		} else {
			wp_clear_scheduled_hook( 'custom_send_email' );                            // IF USER DATA NOT FOUND THEN UNSCHEDULE CRON.
		}
	}

	/**
	 * Ajax callback function for send email to all users
	 *
	 * @since 1.0.0
	 */
	public function emsc_schedule_cron() {
		$all_users = get_users();
		$user_info = array();
		$count     = 0;
		foreach ( $all_users as $user_info ) {
			$user_info      = esc_html( $user_info->ID );
			$data[ $count ] = $user_info;
			$count++;
		}
		set_transient( 'bulk_user_email', $data, 60 * 60 * 24 );
		set_transient( 'bulk_email_track', 0, 60 * 60 * 24 );

		// SCHEDULE CRON IF CRON IS NOT SCHEDULE.
		if ( ! wp_next_scheduled( 'custom_send_email' ) ) {
			wp_schedule_event( time(), 'one_minute', 'custom_send_email' );
		}
	}
}

$emsc_setup = new Emsc_Setup();
