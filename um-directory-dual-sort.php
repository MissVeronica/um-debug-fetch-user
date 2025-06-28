<?php
/**
 * Plugin Name:     Ultimate Member - Debug usage of fetch user 
 * Description:     Extension to Ultimate Member for logging of the usage of the UM fetch user function when sending notification emails.
 * Version:         1.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Plugin URI:      https://github.com/MissVeronica/um-debug-fetch-user
 * Update URI:      https://github.com/MissVeronica/um-debug-fetch-user
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.10.5
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Debug_Fetch_User {

    public $log_file         = WP_CONTENT_DIR . '/um_trace_log.html';
    public $tracer_last_line = '';
    public $time_format      = 'Y-m-d H:i:s ';
    public $all_log_lines    = array();

    function __construct() {

        add_filter( 'um_user_permissions_filter',           array( $this, 'um_fetch_user_tracer' ), 990, 2 );
        add_action( 'um_before_email_notification_sending', array( $this, 'um_fetch_user_id_fix' ), 990, 3 );
    }

    public function um_fetch_user_tracer( $role_meta, $user_id ) {

        global $current_user;

        $e = new \Exception;
        $stack = $e->getTraceAsString();

        if ( str_contains( $stack, 'um_fetch_user()' )) {

            $lines = explode( "\n", $stack );
            foreach( $lines as $line ) {

                if ( str_contains( $line, 'um_fetch_user()' ) && $this->tracer_last_line != $line ) {

                    $this->tracer_last_line = $line;
                    $output = str_replace( 'um_fetch_user()', 'um_fetch_user(' . $user_id . ')', str_replace( ABSPATH, '...', $line ) );
                    $output = str_replace( array( '#5', '#4' ), 'current_user->ID ' . $current_user->ID, $output );

                    $this->all_log_lines[] = $output;
                    break;
                }
            }
        }

        return $role_meta;
    }

    public function um_fetch_user_id_fix( $email, $template, $args ) {

        global $current_user;

        $this->write_log_contents( '<h4>New entry</h4>' );
        $this->write_log_contents( '... Email notification template ' . $template );

        $user = get_user_by( 'email', $email );
        if ( ! empty( $user ) && isset( $user->ID )) {

            $this->write_log_contents( '... current_user->ID ' . $current_user->ID );
            $this->write_log_contents( '... email user ID ' . $user->ID );
            $this->write_log_contents( '... um_user(ID) ' . um_user( 'ID' ));
            $this->write_log_contents( '... REQUEST<pre>' . print_r ( $_REQUEST, true ) . '</pre>' );

            if ( $user->ID != um_user( 'ID' )) {

                um_fetch_user( $user->ID );
                $this->write_log_contents( '... ID is now fixed by um_fetch_user for ID ' . $user->ID );

                $e = new \Exception;
                $stack = str_replace( ABSPATH, '...', $e->getTraceAsString() );
                $this->write_log_contents( '... trace<pre>' . print_r ( explode( "\n", $stack ), true ) . '</pre>' );

            } else {
                $this->write_log_contents( '... No fix is required by um_fetch_user for ID ' . $user->ID );
            }

            if ( is_array( $args['tags_replace'] )) {
                foreach( $args['tags_replace'] as $key => $tag ) {
                    $args['tags_replace'][$key] = str_replace( get_site_url(), '...', $tag );
                }
            }

            $this->write_log_contents( '... args<pre>' . print_r ( $args, true ) . '</pre>' );

            $last_log_lines = array_slice( $this->all_log_lines, -10 );
            $this->write_log_contents( ' ... Prior fetch_user calls:' );
            foreach( $last_log_lines as $last_log_line ) {
                $this->write_log_contents( $last_log_line );
            }

        } else {
            $this->write_log_contents( '... Get userdata via email address failed' );
        }

        $this->all_log_lines = array();
    }

    public function write_log_contents( $output ) {

        $time = date_i18n( $this->time_format, current_time( 'timestamp' ));
        file_put_contents( $this->log_file, '<div>' . $time . $output . '</div>', FILE_APPEND );
    }
}

new UM_Debug_Fetch_User();
