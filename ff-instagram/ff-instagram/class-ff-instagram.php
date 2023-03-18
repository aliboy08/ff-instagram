<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class FF_Instagram {

    private static $instance = null;

    const POST_TYPE = 'ff_instagram_feed';

    public function __construct(){

        $this->dir = WP_PLUGIN_DIR .'/ff-instagram/';

        $this->api_args = [
            'appId' => FF_INSTA['app_id'],
            'appSecret' => FF_INSTA['app_secret'],
            'redirectUri' => FF_INSTA['redirect_uri'],
        ];

        $this->limit = $this->get_limit();
        $this->access_token = $this->get_token();

        add_action( 'init', function(){
            $this->setup_post_type();
            $this->setup_cron();
            $this->admin_page();
        } );
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new FF_Instagram();
        }
        return self::$instance;
    }

    // setup custom post type
    public function setup_post_type(){
        $labels = [
            "name" => __( "Instagram Feed", "ff" ),
            "singular_name" => __( "Instagram Feed", "ff" ),
        ];
    
        $args = [
            "label" => __( "Instagram", "ff" ),
            "labels" => $labels,
            "description" => "",
            "public" => true,
            "publicly_queryable" => false,
            "show_ui" => true,
            "show_in_rest" => false,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "has_archive" => false,
            "show_in_menu" => true,
            "show_in_nav_menus" => false,
            "delete_with_user" => false,
            "exclude_from_search" => true,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "hierarchical" => false,
            "rewrite" => [ "slug" => self::POST_TYPE, "with_front" => false ],
            "query_var" => true,
            "menu_icon" => "dashicons-instagram",
            "supports" => [ "title", "editor", "thumbnail", "custom-fields" ],
            "show_in_graphql" => false,
        ];
    
        register_post_type( self::POST_TYPE, $args );
    }
    
    // fetch data from instagram api
    public function fetch_data(){
        if( !$this->access_token ) return false;
        include_once 'InstagramBasicDisplay.php';
        $instagram = new EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay( $this->api_args );
        $instagram->setAccessToken( $this->access_token );
        $instagram_feed = $instagram->getUserMedia( 'me', $this->limit );
        return $instagram_feed;
    }

    // create instagram as post from api feed
    public function refresh_feed(){
        
        $this->instagram_feed = $this->fetch_data();

        if( !$this->instagram_feed ) return;

        if( isset( $this->instagram_feed->error ) ) {
        	pre_debug( 'INSTAGRAM FEED: '. $this->instagram_feed->error->message );
        	return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $images_to_add = [];

        $i = 0;
        foreach( $this->instagram_feed->data as $row ) { $i++;
            
            if( $i > $this->limit ) break;

            if( $this->item_exists( $row->id ) ) continue; // post already exists, skip
            
            $type = $row->media_type;
            
            $post_fields = [
                'post_type' => self::POST_TYPE,
                'post_title' => $row->id,
                'post_content' => $row->caption,
                'post_status' => 'publish',
            ];
            
            $post_metas = [
                'type' => $type,
                'id' => $row->id,
                'link' => $row->permalink,
                'timestamp' => $row->timestamp,
                'username' => $row->username,
                'media_url' => $row->media_url,
            ];

            $image_url = ( $type === 'VIDEO' ) ? $row->thumbnail_url : $row->media_url;
            
            $images_to_add[] = [
                'post_fields' => $post_fields,
                'post_metas' => $post_metas,
                'image_url' => $image_url,
            ];
            
        }

        if( !count($images_to_add) ) return;

        // reverse order - latest will be added last
        $images_to_add = array_reverse( $images_to_add );
        $have_new_images = false;

        foreach( $images_to_add as $item ) {
            // create new post
            $post_id = wp_insert_post( $item['post_fields'] );
            if( $post_id ) {
                foreach( $item['post_metas'] as $meta_key => $meta_value ) {
                    update_post_meta( $post_id, $meta_key, $meta_value );
                }
                // upload image
                $image_id = media_sideload_image( $item['image_url'], $post_id, $row->id, 'id' );
                if( $image_id ) {
                    // set as featured image
                    set_post_thumbnail( $post_id, $image_id ); 
                }
                $have_new_images = true;
            }
        }

        
        if( count($images_to_add) ) {
            // trigger delete old posts
            $this->delete_old_posts();
        }

    }

    // check if item already exists
    public function item_exists( $id ) {
        $args = [
            'post_type' => self::POST_TYPE,
            'fields' => 'ids',
            'showposts' => 1,
            'no_found_rows' => true,
            'title' => $id,
        ];
        $q = get_posts( $args );
        if( $q ) {
            return $q[0];
        }
        return false;
    }

    // delete items
    public function delete_items() {
        $args = [
            'post_type' => self::POST_TYPE,
            'showposts' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ];
        $q = get_posts( $args );
        foreach( $q as $post_id ) {
            $this->delete_item( $post_id );
        }
    }
    
    public function delete_item( $post_id ) {
        // delete image
        $attachment_id = get_post_thumbnail_id( $post_id );
        wp_delete_attachment( $attachment_id, true );

        // delete post
        wp_delete_post( $post_id, true );
    }

    public function delete_token() {
        delete_option( 'ff_instagram_refreshed_access_token' );
    }
    
    // get instagram posts
    // can specify image_size & count
    public static function get_items( $options = [] ){

        $count = ( isset( $options['count'] ) ) ? $options['count'] : -1;
        $image_size = ( isset( $options['image_size'] ) ) ? $options['image_size'] : 'medium';
        
        $data = [];
        $args = [
            'post_type' => self::POST_TYPE,
            'showposts' => $count,
            'fields' => 'ids',
            'no_found_rows' => true,
            'order' => 'DESC',
        ];
        $q = get_posts( $args );
        if( !$q ) return false; 
        foreach( $q as $post_id ) {
            $data[] = [
                'post_id' => $post_id,
                'image' => get_the_post_thumbnail_url( $post_id, $image_size ),
                'type' => get_post_meta( $post_id, 'type', true ),
                'link' => get_post_meta( $post_id, 'link', true ),
            ];
        }
        return $data;
    }

    public function get_token(){
        $access_token_refreshed = get_option( 'ff_instagram_refreshed_access_token' );
        if( $access_token_refreshed ) {
            return $access_token_refreshed;
        }
        //return FF_INSTA['access_token'];
        return get_option( 'ff_instagram_initial_access_token' );
    }

    public function get_limit(){
        $num = get_option( 'ff_instagram_num' );
        if( $num ) {
            return $num;
        }
        return 15;
    }

    public function refresh_token(){
        if( !$this->access_token ) return;
        include_once 'InstagramBasicDisplay.php';
        $instagram = new EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay( $this->api_args );
        $token_refreshed = $instagram->refreshToken( $this->access_token, true );
        if( $token_refreshed ) {
            update_option( 'ff_instagram_refreshed_access_token', $token_refreshed );
        }
    }

    public function delete_old_posts() {
        $args = [
            'post_type' => self::POST_TYPE,
            'showposts' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ];
        $q = get_posts( $args );
        $items_count = count($q);
        if( $items_count <= $this->limit ) return;

        rsort($q); // ensure in correct order
        
        for( $i = $this->limit; $i < $items_count; $i++ ) {
            $this->delete_item( $q[$i] );
        }
    }

    public function setup_cron(){
        if ( ! wp_next_scheduled( 'ff_instagram_token_refresh' ) ) {
            wp_schedule_event( time(), 'weekly', 'ff_instagram_token_refresh' );
        }
        add_action( 'ff_instagram_token_refresh', [ $this, 'refresh_token' ] );

        if ( ! wp_next_scheduled( 'ff_instagram_feed_refresh' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'ff_instagram_feed_refresh' );
        }
        add_action( 'ff_instagram_feed_refresh', [ $this, 'refresh_feed' ] );
    }

    public function admin_page(){
        add_filter( 'ff_tools_tabs', function( $tabs ){
            $tabs[] = [
                'label' => 'Instagram',
                'slug' => 'instagram',
                'file' => $this->dir .'admin/tools.php',
            ];
            return $tabs;
        });
    }

}
$ff_instagram = FF_Instagram::getInstance();