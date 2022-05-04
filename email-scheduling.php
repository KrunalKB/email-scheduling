<?php
/**
 * Plugin Name:       Email Scheduling
 * Description:       This plugin include cronjob to send email to the users and provide WYSIWYG editor to generate custom email template.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Krunal Bhimajiyani
 * Author URI:        https://github.com/KrunalKB
 */

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class kb_Controller
{
    /**
     * Add actions.
      */
    public function __construct()
    {
        add_filter('wp_mail_content_type', array($this,'set_content_type'));
        add_action('admin_menu', array($this,'kb_admin_menu'));
        add_action('admin_enqueue_scripts', array($this,'kb_email_script'));
        add_action('admin_enqueue_scripts', array($this,'kb_template_script'));
        add_action('wp_ajax_email_template_hook', array($this,'kb_email_template'));
        add_action('wp_ajax_my_email_hook', array($this,'kb_email_event'));
        add_filter('cron_schedules', array($this,'kb_cron_job_interval'));
        add_action('custom_send_email', array($this,'kb_generate_email'));
        add_action('wp_ajax_my_ajax_hook', array($this,'kb_ajax_event'));
    }

    /**
     * Set content type of email to be send
     *
     * @since 1.0.0
     *
     */
    public function set_content_type($content_type)
    {
        return 'text/html';
    }
    
    /**
     * Register admin menu page for bulk email
     *
     * @since 1.0.0
     *
     */
    public function kb_admin_menu()
    {
        $GLOBALS['email-template'] = add_menu_page(
            'Bulk Mail',
            'Bulk Mail',
            'manage_options',
            'kb-template.php',
            array($this,'kb_template_content'),
            'dashicons-email-alt',
            111
        );

        $GLOBALS['email-test'] = add_submenu_page(
            'kb-template.php',
            'Email Test',
            'Email Test',
            'manage_options',
            'kb-email-test.php',
            array($this,'kb_testing_content'),
            'dashicons-share-alt2',
        );
    }

    /**
     * Callback function for content of email template
     *
     * @since 1.0.0
     *
     */
    public function kb_template_content()
    {
        ?>
            <h2><?php _e('Email Template:'); ?></h2>
        <?php

        if (empty(get_option("email_content"))) {
            $default_content = '';
        } else {
            $default_content = get_option("email_content");
        }
        $editor_id       = 'mail_template_id';
        $option_name     = 'mail_template';
        $default_content = html_entity_decode($default_content);
        $default_content = stripslashes($default_content);
        wp_editor($default_content, $editor_id, array(
            'textarea_name' => $option_name,
            'media_buttons' => false,
            'editor_height' => 350,
            'teeny'         => true
        )); ?>
            <br> 
            <button id="saveEmail"><?php _e('Save'); ?></button>
            <br>
            <div class="response"></div>
            <br><br>  
            <span><b><?php _e('Send mail to user:'); ?></b></span><br><br>
            <button id="sendEmail"><?php _e('Send Email'); ?></button>
            <div class="resp"></div>
        <?php
    }
    
    /**
     * Callback function for content of test email
     *
     * @since 1.0.0
     *
     */
    public function kb_testing_content()
    {
        ?>
        <h2><?php _e('Send a Test Email'); ?></h2>
        <hr>
        <form class="emailForm">
            <span class="field"><?php _e('Send to:'); ?></span>
            <input type="email" id="email" class="email">
            <p class="desc"><?php _e('Enter email address where test email will be sent.'); ?></p>  
            <hr><br>
            <button class="email_btn"><?php _e('Send Email'); ?></button>
            <img src="<?php echo plugin_dir_url(__FILE__).'assets/image/load.gif' ?>" class="loader" alt="Loader" height=20 width=20 style="margin-left:10px;">
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
     */
    public function kb_template_script($hook)
    {
        if ($GLOBALS['email-template'] == $hook) {
            wp_enqueue_script(
                'email_js',
                plugin_dir_url(__FILE__) . 'assets/js/bulk-email.js',
                array('jquery'),
                1.0,
                true
            );
            wp_localize_script(
                'email_js',
                'myVar',
                array(
                    'ajax_url' => admin_url('admin-ajax.php')
                )
            );
            wp_enqueue_style(
                'email-style',
                plugin_dir_url(__FILE__).'assets/css/bulk-email.css'
            );
        }
    }

    /**
     * Enqueue files for admin submenu(test email)
     *
     * @since 1.0.0
     *
     */
    public function kb_email_script($hook)
    {
        if ($GLOBALS['email-test'] == $hook) {
            wp_enqueue_script(
                'test_js',
                plugin_dir_url(__FILE__) . 'assets/js/test-email.js',
                array('jquery'),
                1.0,
                true
            );
            wp_localize_script(
                'test_js',
                'myVar',
                array(
                    'ajax_url' => admin_url('admin-ajax.php')
                )
            );
            wp_enqueue_style(
                'test-style',
                plugin_dir_url(__FILE__).'assets/css/test-email.css'
            );
        }
    }

    /**
     * Ajax callback for email template
     *
     * @since 1.0.0
     *
     */
    public function kb_email_template()
    {
        $content        = $_POST['content'];
        $email_body     = stripcslashes($content);
        $update_content = update_option('email_content', $email_body);
    }

    /**
     * Ajax callback for test email
     *
     * @since 1.0.0
     *
     */
    public function kb_email_event()
    {
        $usr_email      = $_POST["email"];
        $email_template = html_entity_decode(get_option('email_content'));
        $var_content    = str_replace("%", "", $email_template);
        wp_mail($usr_email, 'Testing', $var_content);
    }

    /**
     * Adding custom interval
     *
     * @since 1.0.0
     *
     */
    public function kb_cron_job_interval($schedules)
    {
        $schedules['one_minute'] = array(
            'interval' => 60,
            'display'  => __('Every one minute'),
        );
        return $schedules;
    }

    /**
     * Callback function for cron event
     *
     * @since 1.0.0
     *
     */
    public function kb_generate_email()
    {
        $bulk_user_id        = get_transient('bulk_user_email');                        //GETTING ALL USER-ID FROM DATABASE
        $bulk_email_track    = get_transient('bulk_email_track');

        $custom_mail_content = html_entity_decode(get_option('email_content'));         //GETTING TEMPLATE CONTENT FROM DATABASE
        $email_content       = strip_tags($custom_mail_content);

        $query_info          = new WP_User_Query(array('include' => $bulk_user_id));    //GETTING USER DETAIL
        $users_info          = $query_info->results;

        if (!empty($users_info)) {
            $ary_chunk       = array_chunk($users_info, 3);                             //CHUNK WHOLE ARRAY IN 3 ELEMENTS
            $end_key         = array_key_last($ary_chunk[$bulk_email_track]);
    
            foreach ($ary_chunk[$bulk_email_track] as $key => $info) {
                $email_body = str_replace('%User%', $info->display_name, $custom_mail_content);
                wp_mail($info->user_email, 'Testing', $email_body);

                if ($key == $end_key) {
                    set_transient('bulk_email_track', $bulk_email_track + 1, 60 * 60 * 24);
                }
            }

            //IF ARRAY KEY NOT EXIST THEN UNSCHEDULE EVENT
            if (!array_key_exists($bulk_email_track + 1, $ary_chunk)) {
                wp_clear_scheduled_hook('custom_send_email');
            }
        } else {
            wp_clear_scheduled_hook('custom_send_email');                            //IF USER DATA NOT FOUND THEN UNSCHEDULE CRON
        }
    }

    /**
     * Ajax callback function for send email to all users
     *
     * @since 1.0.0
     *
     */
    public function kb_ajax_event()
    {
        $all_users = get_users();
        $user_info = [];
        $count     = 0;
        foreach ($all_users as $user_info) {
            $user_info     = esc_html($user_info->ID);
            $data[$count]  = $user_info;
            $count++;
        }
        set_transient('bulk_user_email', $data, 60 * 60 * 24);
        set_transient('bulk_email_track', 0, 60 * 60 * 24);

        //SCHEDULE CRON IF CRON IS NOT SCHEDULE
        if (!wp_next_scheduled('custom_send_email')) {
            wp_schedule_event(time(), 'one_minute', 'custom_send_email');
        }
    }
}

$kb_Controller = new kb_Controller();


?>