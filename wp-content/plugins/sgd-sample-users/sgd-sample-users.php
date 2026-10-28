<?php
/**
 * Plugin Name: SGD Sample Users
 * Description: Creates an admin and an employee user for demo purposes, plus a login widget.
 * Version: 1.0
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

// Register Employee role on activation
function sgd_add_employee_role() {
    if ( get_role( 'employee' ) ) {
        return;
    }
    $subscriber = get_role( 'subscriber' );
    add_role( 'employee', __( 'Employee', 'sgd-sample-users' ), $subscriber->capabilities );
}

// Create sample users on activation
function sgd_create_sample_users() {
    // Admin account (already created by WP installer, but ensure existence)
    $admin_email = 'admin@example.com';
    if ( ! email_exists( $admin_email ) ) {
        wp_insert_user( [
            'user_login' => 'admin',
            'user_pass'  => 'Admin123!',
            'user_email' => $admin_email,
            'role'       => 'administrator',
            'display_name' => 'Demo Admin',
        ] );
    }
    // Employee account
    $emp_email = 'employee@example.com';
    if ( ! email_exists( $emp_email ) ) {
        wp_insert_user( [
            'user_login' => 'employee',
            'user_pass'  => 'Emp123!',
            'user_email' => $emp_email,
            'role'       => 'employee',
            'display_name' => 'Demo Employee',
        ] );
    }
}

function sgd_sample_users_activate() {
    sgd_add_employee_role();
    sgd_create_sample_users();
}
register_activation_hook( __FILE__, 'sgd_sample_users_activate' );

// Simple login widget
class SGD_Sample_Login_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'sgd_sample_login_widget',
            __( 'SGD Login', 'sgd-sample-users' ),
            [ 'description' => __( 'Shows a login/logout link.', 'sgd-sample-users' ) ]
        );
    }
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            echo '<p>Welcome, ' . esc_html( $user->display_name ) . '!</p>';
            echo '<a href="' . esc_url( wp_logout_url( home_url() ) ) . '" class="button">Log out</a>';
        } else {
            echo '<a href="' . esc_url( wp_login_url() ) . '" class="button">Log in</a>';
        }
        echo $args['after_widget'];
    }
}
function sgd_register_login_widget() {
    register_widget( 'SGD_Sample_Login_Widget' );
}
add_action( 'widgets_init', 'sgd_register_login_widget' );
?>