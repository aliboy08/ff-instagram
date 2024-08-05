<?php
if ( ! defined( 'ABSPATH' ) ) die();

class FF_Instagram {

    private static $instance = null;

    public $api_args = [
        'appId' => FF_INSTA['app_id'],
        'appSecret' => FF_INSTA['app_secret'],
        'redirectUri' => FF_INSTA['redirect_uri'],
    ];

    public $limit = 12;
    public $access_token = '';

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new FF_Instagram();
        }
        return self::$instance;
    }

    public function init(){

        $this->limit = $this->get_limit();
        $this->access_token = $this->get_token();

        add_action( 'init', function(){
            $this->setup_cron();
        });

        add_action('admin_menu', [$this, 'admin_menu']);

        $this->items = get_option('ff_instagram_items');
        if( !$this->items ) $this->items = [];
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

        // fix for some images missing
        add_filter('image_sideload_extensions', function($allowed_extensions){
            $allowed_extensions[] = 'heic';
            return $allowed_extensions;
        });
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $is_empty = !$this->items;
        $has_changes = false;

        $i = 0;
        foreach( $this->instagram_feed->data as $item ) { $i++;
            
            if( $i > $this->limit ) break;

            if( $this->item_exists( $item->id ) ) continue; // already exists, skip

            // new item
            
            $image_url = ( $item->media_type === 'VIDEO' ) ? $item->thumbnail_url : $item->media_url;
        
            $image_id = media_sideload_image( $image_url, 0, null, 'id' );

            if( !$image_id ) continue;

            // image upload success

            // save data to attachment post meta
            $meta = [
                'link' => $item->permalink,
                'timestamp' => $item->timestamp,
                'username' => $item->username,
                'media_type' => $item->media_type,
                'image_url' => $image_url,
                'caption' => $item->caption,
            ];
            foreach( $meta as $key => $value ) {
                update_post_meta( $image_id, $key, $value );
            }

            $item_data = [
                'id' => $item->id,
                'image_id' => $image_id,
            ];

            if( $is_empty ) {
                $this->items[] = $item_data;
            }
            else {
                // remove old item
                $this->delete_last_item();
                // add new item on first index
                array_unshift($this->items, $item_data);
            }
            
            $has_changes = true;
            
        }

        if( $has_changes ) {
            update_option('ff_instagram_items', $this->items, false);
        }

    }

    // check if item already exists
    public function item_exists_old( $id ) {
        $args = [
            'post_type' => 'ff_instagram_feed',
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
    
    public function item_exists( $id ) {

        if( !$this->items ) return false;
        
        foreach( $this->items as $item ) {
            if( $item['id'] == $id ) return true;
        }
        
        return false;
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

    public function get_token(){
        $access_token_refreshed = get_option( 'ff_instagram_refreshed_access_token' );
        if( $access_token_refreshed ) {
            return $access_token_refreshed;
        }
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

    public function delete_items(){
        if( !$this->items ) return;
        foreach( $this->items as $item ) {
            wp_delete_attachment( $item['image_id'], true );
        }
        update_option('ff_instagram_items', [], false);
    }

    public function delete_last_item(){
        $last_index = count($this->items);
        if( $last_index < $this->limit ) return;
        $last_item = $this->items[$last_index-1];
        wp_delete_attachment( $last_item['image_id'], true );
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

    public function admin_menu(){
        add_submenu_page( 'fivebyfive', 'Instagram', 'Instagram', 'manage_options', 'ff_instagram', [$this, 'admin_page'] );
    }

    public function admin_page(){
        include_once 'admin/settings.php';
    }

}

$ff_instagram = FF_Instagram::getInstance();
$ff_instagram->init();