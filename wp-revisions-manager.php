<?php
/**
* Plugin Name: WP Revisions Manager
* Plugin URI:  http://wordpress.org/plugins/wp-revisions-manager
* Description: WP Revisions Manager let you purge revisions via AJAX. There is also a Bulk action in the post lists.You can also limit the number of revisions to be stored.
* Tags: Tags: wp revision manager, wp revisions control, wp revisions limit, revision manager, revisions control, revision limit, revision delete
* Author: P. Roy
* Author URI: https://www.proy.info
* Version: 1.0.2
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: wp-revisions-manager
**/

if ( ! defined( 'WPINC' ) ) {   die; }

class WP_Revisions_Manager {

    //protected $loader;
    protected $plugin_name;
    protected $version;
    public $nonceName = 'wprevisionmanager_options';
    var $options = array( 'per-type' => array('post' => '1000', 'page' => '1000', 'all' => '1000') );

    public function __construct() {

        $this->plugin_name = 'wp-revisions-manager';
        $this->version = '1.0.0';
        $this->load_dependencies();
        $this->load_options();

        //add_action( 'admin_bar_menu', array( $this, 'adminbar_menu'), 150 );

        add_action( 'admin_menu', array( $this, 'addMenuPages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

        add_filter( 'wp_revisions_to_keep', array( $this, 'limit_revision_size'), 10, 2 );

        add_action( 'post_submitbox_misc_actions',          array( $this, 'purge_revisions_button'), 3 );
        add_action( 'wp_ajax_wprd_purge_revisions',         array( $this, 'purge_revisions' ) );
        add_action( 'admin_post_wprd_purge_revisions',      array( $this, 'purge_revisions' ) );

        add_action('admin_footer',                          array( $this, 'single_revision_delete_button') );
        add_action( 'wp_ajax_wprd_single_revision_delete',  array( $this, 'single_revision_delete' ) );

        add_action('admin_footer-edit.php',                 array( $this, 'bulk_purge_select_action') );
        add_action( 'load-edit.php',                        array( $this, 'bulk_purge_action' ) );

        add_action( 'wp_ajax_wprd_purge_allrevisions',      array( $this, 'purge_allrevisions' ) );
    }

    private function load_dependencies() {
        require_once plugin_dir_path(  __FILE__  ) . 'includes/wp-revisions-manager-functions.php';
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wprm-script.js', array('jquery'), $this->version, false);
    }

    public function addMenuPages()  {

        add_options_page(
            __('WP Revisions Manager', $this->plugin_name),
            __('WP Revisions Manager', $this->plugin_name),
            'manage_options',
            $this->plugin_name . '_options',
            array(
                $this,
                'settingsPage'
            )
        );

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'), 10, 2 );

    }

    public function plugin_settings_link($links, $file) {
        $settings_link = '<a href="options-general.php?page=wp-revisions-manager_options">' . __('Settings', $this->plugin_name) . '</a>';
        array_unshift($links, $settings_link); // before other links
        return $links;
    }

    public function adminbar_menu( $meta = TRUE ) {
        global $wp_admin_bar;
        if ( !is_user_logged_in() ) { return; }
        if ( !is_admin_bar_showing() ) { return; }

        $wp_admin_bar->add_menu( array(
            'id' => 'wprd-adminbarmenu',
            'title' => __( '<span class="dashicons dashicons-backup ab-icon" style="margin-top: 3px;font-size: 19px;"></span><span class="ab-label">Purge All Revisions</span>' ),
            'href'  => '#',
            'meta'  => array(
                'title' => __('Purge All Revisions'),
                'class' => 'action wprd-btn all'
            ),

            )
        );

        /*$wp_admin_bar->add_menu( array(
            'id'    => 'my-sub-item',
            'parent' => 'wprd-adminbarmenu',
            'title' => 'My Sub Menu Item',
            'href'  => '#',
            'meta'  => array(
                'title' => __('My Sub Menu Item'),
                'target' => '_blank',
                'class' => 'my_menu_item_class'
            )
        ));*/
    }

    public function settingsPage() {
        global $revision_control;
        global $_registered_pages, $_parent_pages, $menu, $admin_page_hooks, $submenu;

        $this->saveSettings();

        echo "<div class='wrap'>";
        echo '<h1>' . __('WP Revisions Manager Options', $this->plugin_name) . '</h1>';
        echo '<h3>' . __('Set default revision status for <em>Post Types</em>', $this->plugin_name) . '</h3>';


        echo '<form method="post" action="options-general.php?page=wp-revisions-manager_options">';
        wp_nonce_field($this->nonceName, $this->nonceName, true, true);

        echo '<table class="form-table">';
        $postTypeList = wprd_post_types_default();
        foreach ( $postTypeList as $post_type ) {
            $post_type_name = $post_type;
            if ( !in_array($post_type, array('post', 'page')) && function_exists('get_post_type_object') ) {
                $pt = get_post_type_object($post_type);
                $post_type_name = $pt->label;
                unset($pt);
            } else {
                if ( 'post' == $post_type )
                    $post_type_name = _n('Post', 'Posts', 5, $this->plugin_name);
                elseif ( 'page' == $post_type )
                    $post_type_name = _n('Page', 'Pages', 5, $this->plugin_name);

            }

            echo '<tr><th><label for="options_per-type_' . $post_type . '"> <em>' . $post_type_name . '</em></label></th>';
            echo '<td align="left"><select name="options[per-type][' . $post_type . ']" id="options_per-type_' . $post_type . '">';
            $current = $this->get_option($post_type, 'per-type');
            echo '<option value="1000" ' . ($current == '1000' ? ' selected="selected"' : '') . '>Unlimited Revisions</option>';
            echo '<option value="never" ' . ($current == 'never' ? ' selected="selected"' : '') . '>Do Not Store Revisions</option>';
            for( $revision = 2; $revision<=20;$revision++ ) {
                if ( 'defaults' == $revision )  continue;
                $selected = ($current == $revision ? ' selected="selected"' : '');
                echo '<option value="' . esc_attr($revision) . '"' . $selected . '>' . esc_html('Maximum '.$revision.' Revisions') . '</option>';
                if($revision > 4)$revision = $revision + 4;
            }
            echo '</select></td></tr>';
        }

        echo '</table>';
        submit_button( __('Save Changes', $this->plugin_name) );
        echo '
        </form>';
        echo '</div>';
    }

    private function saveSettings() {
        global $menu;

        if (!isset($_POST[$this->nonceName])) {
            return false;
        }

        $verify = check_admin_referer($this->nonceName, $this->nonceName);

        ///print_r($_POST['options']['per-type']); exit;

        $data = stripslashes_deep($_POST['options']);
        foreach ( $data as $option => $val ) {
            if ( is_string($val) ) // Option is the keyname
                 $this->set_option($option, $val);
            elseif ( is_array($val) ) // Option is the bucket, key => val are the options in the group.
                foreach ( $val as $subkey => $subval )
                     $this->set_option($subkey, $subval, $option);
        }


        // we'll redirect to same page when saved to see results.
        // redirection will be done with js, due to headers error when done with wp_redirect
        //$adminPageUrl = admin_url('options-general.php?page=wp-revisions-manager_options&saved='.$savedSuccess);
        wp_safe_redirect( add_query_arg('updated', 'true', wp_get_referer() ) );
        //wp_safe_redirect( $adminPageUrl ); exit;
    }

    private function load_options() {
        $original = $options = get_option('wprd-revision-control', array());
        if ( $options != $original ) // Update it if an upgrade has taken place.
            update_option('wprd-revision-control', $options);

        $this->options = array_merge($this->options, $options); // Some default options may be set here, unless the user modifies them
    }

    public function get_option($key, $bucket = false, $default = false ) {
        if ( $bucket )
            return isset($this->options[$bucket][$key]) ? $this->options[$bucket][$key] : $default;
        else
            return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    private function set_option($key, $value, $bucket = false) {
        if ( $bucket )
            $this->options[$bucket][$key] = $value;
        else
            $this->options[$key] = $value;

        update_option('wprd-revision-control', $this->options);
    }

    public function purge_revisions_button() {
        global $post;
        $postTypeList = wprd_post_types_default();

        if ( !in_array( get_post_type( $post->ID ), $postTypeList ) )
            return;

        $revisions = wp_get_post_revisions( $post->ID );

        if( !empty ( $revisions ) ) {
            //Check if user can delete revisions
            if ( !current_user_can( apply_filters( 'wprd_capability', 'delete_post' ), $post->ID ) )
                return;

            $nonce = wp_create_nonce( 'delete-revisions_' . $post->ID );

            $content = '<span id="wprd-clear-revisions">&nbsp;&nbsp;';
            $content .= '<a href="#clear-revisions" class="wprd-link once" data-nonce="' . $nonce . '" data-action="' . esc_attr__( 'Purging', $this->plugin_name ) . '" data-error="' . esc_attr__( 'Something went wrong....', $this->plugin_name ) . '">';
            $content .= __( 'Purge', $this->plugin_name );
            $content .= '</a>';
            $content .= '<span class="wprd-loading"></span>';
            $content .= '</span>';

            $content .= '<div class="misc-pub-section wprd-no-js">';
            $content .= '<a class="" href="' . admin_url( 'admin-post.php?action=wprd_purge_revisions&wprd-post_ID=' . $post->ID . '&wprd-nonce=' . $nonce ) . '">' . esc_attr__( 'Purge revisions', $this->plugin_name ) . '</a>';
            $content .= '</div>';

            echo $content;
        }
    }

    public function purge_revisions(){
        //Get var from GET
        $postID = intval($_GET[ 'wprd-post_ID' ]);
        $nonce = $_GET[ 'wprd-nonce' ];
        $revisions_count = 0;

        //Nonce check
        if ( ! wp_verify_nonce( $nonce, 'delete-revisions_' . $postID ) ) {
            $output = array( 'success' => 'error', 'data' => __( 'You can\'t do this...', $this->plugin_name ) );
        } else {
            $revisions = wp_get_post_revisions( $postID );
        }

        //Check revisions & delete them
        if( isset( $revisions ) && !empty ( $revisions ) ) {
            $output = array( 'success' => 'success', 'data' => __( 'Purged', $this->plugin_name ) );

            foreach ( $revisions as $revision ) {
                $revDelete = wp_delete_post_revision( $revision );

                if( is_wp_error( $revDelete ) ) {
                    $output = array( 'success' => 'error', 'data' => $revDelete->get_error_message() );
                } else {
                    $revisions_count++;
                }

            }

        } else {
            $output = array( 'success' => 'error', 'data' => __( 'There is no revisions for this post', $this->plugin_name ) );
        }

        //Output for AJAX call or no JS fallback
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

            ( $output['success'] == 'success' ? wp_send_json_success( $output[ 'data' ] ) : wp_send_json_error( $output[ 'data' ] ) );

        } else {

            //Prepare the notice
            add_settings_error(
                'wprd-admin-notice',
                'wprd_notice',
                $output[ 'data' ],
                ( $output[ 'success' ] == 'success'  ? 'updated' : 'error' )
            );

            //Store the notice for the redirection
            set_transient('wprd_settings_errors', get_settings_errors(), 30);

            //Build the redirection
            $redirect = add_query_arg( 'rev_purged', $revisions_count, wp_get_referer() );

            wp_redirect( $redirect );
            exit;

        }
    }

    public function single_revision_delete_button() {
        global $post, $pagenow;
        if( 'post.php' == $pagenow ) {
            $postTypeList = wprd_post_types_default();

            if ( !isset( $post->ID ) )
                return;

            if ( current_user_can( apply_filters( 'wprd_capability', 'delete_post' ), $post->ID ) && in_array( get_post_type( $post->ID ), $postTypeList ) ) {
                echo '<div id="wprd-btn-container" style="display:none"><a href="#delete-revision" class="action wprd-btn once">' . __( 'Delete Revision' ) . '</a></div>';
            }
        }
    }

    public function single_revision_delete() {

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

            //Get var from GET
            $postID = intval($_GET[ 'wprd-post_ID' ]);
            $revID = intval($_GET[ 'revID' ]);

            $postTypeList = wprd_post_types_default();

            if ( !current_user_can( apply_filters( 'wprd_capability', 'delete_post' ), $postID ) && !in_array( get_post_type( $postID ), $postTypeList ) ) {
                wp_send_json_error( __( 'You can\'t do this...', $this->plugin_name ) );
            }

            if ( !empty( $revID ) && $postID == wp_is_post_revision( $revID ) ) {

                $revDelete = wp_delete_post_revision( $revID );

                if( is_wp_error( $revDelete ) ) {
                    //Extra error notice if WP error return something
                    $output = array( 'success' => 'error', 'data' => $revDelete->get_error_message() );
                } else {
                    $output = array( 'success' => 'success', 'data' => __( 'Deleted' ) );
                }

                ( $output['success'] == 'success' ? wp_send_json_success( $output[ 'data' ] ) : wp_send_json_error( $output[ 'data' ] ) );

            } else {
                wp_send_json_error( __( 'Something went... wrong', $this->plugin_name ) );
            }

        }

        //If accessed directly
         wp_die( __( 'You can\'t do this...', $this->plugin_name ) );
    }

    public function bulk_purge_select_action() {
        global $post_type;

        $postTypeList = wprd_post_types_default();

        if( in_array( $post_type, $postTypeList ) ) {
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('<option>').val('wprd-purge').text('<?php _e( 'Purge Revisions', $this->plugin_name ) ?>').appendTo("select[name='action'], select[name='action2']");
                });
            </script>
        <?php
        }
    }

    public function bulk_purge_action() {

        if ( empty( $_REQUEST[ 'post' ] ) )
        return;

        $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
        $action = $wp_list_table->current_action();


        if ( 'wprd-purge' == $action ) {

            // Security check
            check_admin_referer( 'bulk-posts' );

            $revisions_count = 0;
            $post_ids = array_map( 'intval', $_REQUEST[ 'post' ] );

            foreach ( $post_ids as $post_id ) {

                $postTypeList = wprd_post_types_default();
                $userCapability = apply_filters( 'wprd_capability', 'delete_post' );

                $extraNotice = '';
                if ( $userCapability == 'delete_post' ){
                    $extraNotice = '&nbsp;&nbsp;&nbsp;<i style="font-weight:normal">' . __( 'Note: You can only purge revisions for the posts you\'re allowed to delete', $this->plugin_name ) . '</i>';
                }

                if ( current_user_can( $userCapability, $post_id ) && in_array( get_post_type( $post_id ), $postTypeList ) ) {

                    $revisions = wp_get_post_revisions( $post_id );

                    //Check revisions & delete them
                    if( isset( $revisions ) && !empty ( $revisions ) ) {

                        foreach ( $revisions as $revision ) {
                            $revDelete = wp_delete_post_revision( $revision );

                            if( is_wp_error( $revDelete ) ) {
                                //Extra error notice if WP error return something
                                $outputWpError = $revDelete->get_error_message();
                            } else {
                                $revisions_count++;

                                $output = array(
                                    'success' => 'success', 'data' => sprintf( _n( '1 revision has been deleted', '%s revisions have been deleted', $revisions_count, $this->plugin_name ), $revisions_count ) . $extraNotice
                                );
                            }

                        }

                    }

                }

            }

            if ( $revisions_count == 0 ){
                $output = array(
                    'success' => 'error', 'data' => __( 'No revision to delete', $this->plugin_name ) . $extraNotice
                );
            }

            //Prepare the WP ERROR notice
            if ( isset( $outputWpError ) && !empty( $outputWpError ) ) {
                add_settings_error(
                    'wprd-admin-notice',
                    'wprd_notice_WP_error',
                    $outputWpError,
                    'error'
                );
            }

            //Prepare the default notice
            if ( !empty( $output ) ) {
                add_settings_error(
                    'wprd-admin-notice',
                    'wprd_notice',
                    $output[ 'data' ],
                    ( $output[ 'success' ] == 'success'  ? 'updated' : 'error' )
                );
            }

            //Store the notice(s) for the redirection
            set_transient( 'wprd_settings_errors', get_settings_errors(), 30 );

            //cleanup the arguments
            $sendback = remove_query_arg( array( 'exported', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );

            if ( ! $sendback )
                $sendback = add_query_arg( array( 'post_type', get_post_type() ), admin_url( 'edit.php' ) );

            //retrieve the pagination
            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg( array( 'paged' => $pagenum, 'rev_purged' => $revisions_count ), $sendback );

            wp_safe_redirect( $sendback );
            exit();

        }
    }

    public function purge_allrevisions() {

        if( empty( $_REQUEST[ 'action' ] ) ) return;

        $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
        $action = $wp_list_table->current_action();

        $revisions_count = 0;

        $post_ids = get_posts(array('fields' => 'ids', 'posts_per_page'  => -1 ));

        foreach ( $post_ids as $post_id ) {

            $postTypeList = wprd_post_types_default();
            $userCapability = apply_filters( 'wprd_capability', 'delete_post' );

            $extraNotice = '';
            if ( $userCapability == 'delete_post' ){
                $extraNotice = '&nbsp;&nbsp;&nbsp;<i style="font-weight:normal">' . __( 'Note: You can only purge revisions for the posts you\'re allowed to delete', $this->plugin_name ) . '</i>';
            }

            if ( current_user_can( $userCapability, $post_id ) && in_array( get_post_type( $post_id ), $postTypeList ) ) {

                $revisions = wp_get_post_revisions( $post_id );

                //Check revisions & delete them
                if( isset( $revisions ) && !empty ( $revisions ) ) {

                    foreach ( $revisions as $revision ) {
                        $revDelete = wp_delete_post_revision( $revision );

                        if( is_wp_error( $revDelete ) ) {
                            //Extra error notice if WP error return something
                            $outputWpError = $revDelete->get_error_message();
                        } else {
                            $revisions_count++;

                            $output = array(
                                'success' => 'success', 'data' => sprintf( _n( '1 revision has been deleted', '%s revisions have been deleted', $revisions_count, $this->plugin_name ), $revisions_count ) . $extraNotice
                            );
                        }

                    }

                }

            }

        }

        if ( $revisions_count == 0 ){
            $output = array(
                'success' => 'error', 'data' => __( 'No revision to delete', $this->plugin_name ) . $extraNotice
            );
        }

        //Prepare the WP ERROR notice
        if ( isset( $outputWpError ) && !empty( $outputWpError ) ) {
            add_settings_error(
                'wprd-admin-notice',
                'wprd_notice_WP_error',
                $outputWpError,
                'error'
            );
        }

        //Prepare the default notice
        if ( !empty( $output ) ) {
            add_settings_error(
                'wprd-admin-notice',
                'wprd_notice',
                $output[ 'data' ],
                ( $output[ 'success' ] == 'success'  ? 'updated' : 'error' )
            );
        }

        //Store the notice(s) for the redirection
        set_transient( 'wprd_settings_errors', get_settings_errors(), 30 );

        //cleanup the arguments
        $sendback = remove_query_arg( array( 'exported', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );

        if ( ! $sendback )
            $sendback = add_query_arg( array( 'post_type', get_post_type() ), admin_url( 'edit.php' ) );

        //retrieve the pagination
        $pagenum = $wp_list_table->get_pagenum();
        $sendback = add_query_arg( array( 'paged' => $pagenum, 'rev_purged' => $revisions_count ), $sendback );

        wp_safe_redirect( $sendback );
        exit();
    }

    public function limit_revision_size( $num, $post ) {
        $postTypeList = wprd_post_types_default();
        foreach ( $postTypeList as $post_type ) {
            if( $post_type == $post->post_type ) {
                $num = $this->get_option($post->post_type, 'per-type');
            }
        }
        return $num;
    }
}

new WP_Revisions_Manager();


