<?php

/*
Plugin Name: Simple Subscriber Signup Widget
Plugin URI: 
Description: This plugin allows visitors to submit their email and name and be added to the subscribers list
Author: Harry Gr
Version: 1.0
Author URI: http://harryg.me
License: MIT
*/

//Instantiate the AJAX handler and load our jQuery
add_action( 'wp_enqueue_scripts', 'simsignup_enqueue_scripts' );
function simsignup_enqueue_scripts(){
    wp_enqueue_script( 'simsignup-ajax-handle', plugin_dir_url( __FILE__ ) . 'ajax.js', array( 'jquery' ) );
    wp_localize_script( 'simsignup-ajax-handle', 'simsignup_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

// The AJAX add actions
add_action( 'wp_ajax_do_ajax_simsignup', 'do_ajax_simsignup' );
add_action( 'wp_ajax_nopriv_do_ajax_simsignup', 'do_ajax_simsignup' );

/**
* This will perform the server-side AJAX stuff and return a response
*
*/
function do_ajax_simsignup(){
 $email = $_POST['simsignup_email'];
 $email = apply_filters( 'user_registration_email', $email);
 $name = isset($_POST['simsignup_name']) ? $_POST['simsignup_name'] : $email;
 $name = sanitize_user( str_replace('@','', $name));
 $result = simsignup_add_user($email, $name);
 echo json_encode($result);
 die();
}

/**
* Validate the input and return a response
*
* @param string sanitized email address
* @param string sanitized username
* @return array
*/
function simsignup_add_user($email, $name) {
   if ( empty($email) ){
    return array(
        'status' => 'error',
        'message' => 'You did not enter an email address.',
        );
}
if ( !is_email($email) ) {
    return array(
        'status' => 'error',
        'message' => 'You did not enter a valid email address.',
        );
}
if ( email_exists( $email ) ) {
    return array(
        'status' => 'error',
        'message' => 'This email address is already registered.',
        );
}
//If the username exists we'll just append it with an incrementing number until it no longer does.
if (username_exists( $name )) {
    $test_name = $name;
    $i = 1;
    while (username_exists( $test_name )) {
        $test_name = $name . $i;
        $i++;
    }
    $name = $test_name;
}
$user_pass = substr( md5( $email . uniqid( microtime() ) ), 0, 7);
$user_id = wp_create_user( $name, $user_pass, $email );
$user = new WP_User($user_id);
$user->set_role('subscriber');
if ( !$user_id ) {
    return array(
        'status' => 'error',
        'message' => 'Oh no, something went wrong.',
        );
}
return array(
    'status' => 'success',
    'message' => 'Thanks for registering.',
    );
}

//Register our widget
add_action('widgets_init', 'init_subscriber_simsignup_widget');
function init_subscriber_simsignup_widget() {
    register_widget('SubscribersimSignupWidget');
}

/**
* Here be the class that is the widget itself
*
*
*/
class SubscribersimSignupWidget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'subscriber_simsignup_widget',
            'Subscriber Signup Widget',
            array('description' => 'Allows visitors to submit their email and name and be added to the subscribers list.')
            );
    }

    public function widget($args, $instance) {
        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        $hide_name_input = isset($instance['hide_name_input']);
        $ajax_action = plugins_url( 'ajax-simsignup.php' , __FILE__ ); //This is where we'll post the sign up data

        echo $before_widget;
        if (!empty($title)) echo $before_title . $title . $after_title;
        ?>
        <form role="form" id="simsignup_widget_form" method="post" action="#">
            <input name="action" type="hidden" value="do_ajax_simsignup" />
            <?php if (!$hide_name_input) : ?>
            <div class="form-group">
                <label for="simsignup_name" class="sr-only">Name</label>
                <input type="text" class="form-control" id="simsignup_name" name="simsignup_name" placeholder="Your Name" />
            </div>
        <?php endif; ?>
        <!--  <div class="input-group"> -->
        <div class="input-group">

            <input type="email" class="form-control" id="simsignup_email" name="simsignup_email" placeholder="Email" />
            <label for="simsignup_email"  class="sr-only">Email</label>
            <span class="input-group-btn">
                <button type="submit" class="btn btn-default">Sign Up</button>
            </span>
        </div>
        <!-- </div> -->
    </form>
    <div class="alert" id="simsignup_form_response" style="display:none;"></div>
    <?php
    echo $after_widget;
}

public function form($instance) {
    $field_data = array(
        'title' => array(
            'id'    => $this->get_field_id('title'),
            'name'  => $this->get_field_name('title'),
            'value' => (isset($instance['title'])) ? $instance['title'] : __('Sign Up')
            ),
        'hide_name_input' => array(
            'id'    => $this->get_field_id('hide_name_input'),
            'name'  => $this->get_field_name('hide_name_input'),
            'value' => (isset($instance['hide_name_input'])) ? 'true' : ''
            ),            
        );
        ?>
        <p>
            <label for="<?php echo $field_data['title']['id']; ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $field_data['title']['id']; ?>" name="<?php echo $field_data['title']['name']; ?>" type="text" value="<?php echo esc_attr($field_data['title']['value']); ?>">
        </p>


        <p style='font-weight: bold;'><?php _e('Options:'); ?></p>

        <p>
            <input id="<?php echo $field_data['hide_name_input']['id']; ?>" name="<?php echo $field_data['hide_name_input']['name']; ?>" type="checkbox" value="true" <?php checked($field_data['hide_name_input']['value'], 'true'); ?>>
            <label for="<?php echo $field_data['hide_name_input']['id']; ?>"><?php _e('Hide the name field. Subscriber name will be derived from the email address instead'); ?></label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['hide_name_input'] = $new_instance['hide_name_input'];
        return $instance;
    }
}

