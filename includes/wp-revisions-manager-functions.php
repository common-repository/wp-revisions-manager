<?php
if ( ! defined( 'WPINC' ) ) {   die; }

/***************************************************************
 * Print Style in admin header
 ***************************************************************/

function wprd_add_admin_style() {
	echo '
	<style>
		#wprd-clear-revisions,
		.wprd-no-js {
			display:none;
		}
		.wprd-loading {
			display:none;
			background-image: url(' . admin_url('images/spinner-2x.gif') . ');
			display: none;
			width: 18px;
			height: 18px;
			background-size: cover;
			margin: 0 0 -5px 4px;
		}
		#wprd-clear-revisions .wprd-link.sucess {
			color: #444;
			font-weight: 600;
		}
		#wprd-clear-revisions .wprd-link.error {
			display: block
			color: #a00;
			font-weight: normal;
		}
		.wprd-no-js:before {
			color: #888;
			content: "\f182";
			font: 400 20px/1 dashicons;
			speak: none;
			display: inline-block;
			padding: 0 2px 0 0;
			top: 0;
			left: -1px;
			position: relative;
			vertical-align: top;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			text-decoration: none!important;
		}
		.wp-core-ui .action.wprd-btn {
			display: inline-block;
			margin-left: 10px;
		}
	</style>
	<noscript>
		<style>
			.wprd-no-js {
				display:block;
			}
		</style>
	</noscript>
	';
}
add_action( 'admin_print_styles-post-new.php', 'wprd_add_admin_style');
add_action( 'admin_print_styles-post.php', 'wprd_add_admin_style');


/***************************************************************
 * Check if revisions are activated on plugin load
***************************************************************/
function wprd_norev_check(){
	if ( !WP_POST_REVISIONS ){
		//Keep in memory if revisions are deactivated
		set_transient( 'wprd_norev', true, 0 );
	}
}
register_activation_hook( __FILE__, 'wprd_norev_check' );


/***************************************************************
 * Display the notice if revisions are deactivated
***************************************************************/
function wprd_norev_notice(){
	if ( current_user_can( 'activate_plugins' ) && 	!WP_POST_REVISIONS ){
		// Exit if no notice
		if ( ! ( get_transient( 'wprd_norev' ) ) )
			return;

		//Build the dismiss notice link
		$dismiss = '
			<a class="wprd-dismiss" href="' . admin_url( 'admin-post.php?action=wprd_norev_dismiss' ) . '" style="float: right; text-decoration: none;">
				' . __('Dismiss') . '<span class="dashicons dashicons-no-alt"></span>
			</a>
		';

		//Prepare the notice
		add_settings_error(
			'wprd-admin-norev',
			'wprd_norev',
			__( 'Revisions are deactivated on this site, you don\'t need to install the plugin "WP Revisions Manager".', 'wp-revisions-manager' ) . ' ' . $dismiss,
			'error'
		);

		//Display the notice
		settings_errors( 'wprd-admin-norev' );
	}
}
add_action( 'admin_notices', 'wprd_norev_notice' );


/***************************************************************
 * Dismiss the notice if revisions are deactivated
***************************************************************/
function wprd_norev_dismiss(){
	// Only redirect if accesed direclty & transients has already been deleted
	if ( ( get_transient( 'wprd_norev' ) ) ) {
		delete_transient( 'wprd_norev' );
	}

	//Redirect to previous page
	wp_safe_redirect( wp_get_referer() );
}
add_action( 'admin_post_wprd_norev_dismiss', 'wprd_norev_dismiss' );


/***************************************************************
 * Post types supported list
 ***************************************************************/
function wprd_post_types_default(){

	if ( function_exists('post_type_supports') ) {
        $postTypes = array();
        $_types = get_post_types();
        foreach ( $_types as $type ) {
            if ( post_type_supports($type, 'revisions') )
                $postTypes[] = $type;
        }
    } else {
        $postTypes = array('post', 'page');
    }

	return $postTypes = apply_filters( 'wprd_post_types_list', $postTypes );
}


/***************************************************************
 * Hack to prevent 'W3 Total Cache' caching the notice transient
 * Thanks to @doublesharp http://wordpress.stackexchange.com/a/123537
 ***************************************************************/
function wprd_disable_linked_in_cached( $value=null ){
	if( is_admin() ) {
		global $pagenow;
		if( 'edit.php' == $pagenow ) {
			global $_wp_using_ext_object_cache;
			if ( !empty( $_wp_using_ext_object_cache ) ){
				$_wp_using_ext_object_cache_prev = $_wp_using_ext_object_cache;
				$_wp_using_ext_object_cache = false;
			}
		}
	}
	return $value;
}
add_filter( 'pre_set_transient_wprd_settings_errors', 'wprd_disable_linked_in_cached' );
add_filter( 'pre_transient_wprd_settings_errors', 'wprd_disable_linked_in_cached' );
add_action( 'delete_transient_wprd_settings_errors', 'wprd_disable_linked_in_cached' );

function wprd_enable_linked_in_cached( $value=null ){
	if( is_admin() ) {
		global $pagenow;
		if( 'edit.php' == $pagenow ) {
			global $_wp_using_ext_object_cache;
			if ( !empty( $_wp_using_ext_object_cache ) ){
				$_wp_using_ext_object_cache = $_wp_using_ext_object_cache_prev;
			}
		}
	}
	return $value;
}
add_action( 'set_transient_wprd_settings_errors', 'wprd_enable_linked_in_cached' );
add_filter( 'transient_wprd_settings_errors', 'wprd_enable_linked_in_cached' );
add_action( 'deleted_transient_wprd_settings_errors', 'wprd_enable_linked_in_cached' );


/***************************************************************
 * Display admin notice after purging revisions
 ***************************************************************/
function wprd_notice_display(){

	// Exit if no notice
	if ( !( $notices = get_transient( 'wprd_settings_errors' ) ) )
		return;

	$noticeCode = array( 'wprd_notice', 'wprd_notice_WP_error' );

	//Rebuild the notice
	foreach ( (array) $notices as $notice ) {
		if( isset( $notice[ 'code' ] ) && in_array( $notice[ 'code' ] , $noticeCode ) ) {
			add_settings_error(
				$notice[ 'setting' ],
				$notice[ 'code' ],
				$notice[ 'message' ],
				$notice[ 'type' ]
			);
		}
	}

	//Display the notice
	settings_errors( $notice[ 'setting' ] );

	// Remove the transient after displaying the notice
	delete_transient( 'wprd_settings_errors' );

}
add_action( 'admin_notices', 'wprd_notice_display', 0 );
