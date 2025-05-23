<?php
if ( ! defined( 'ABSPATH' ) ) die();

class FF_Instagram {

    private static $instance = null;
    public $items = [];

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

        add_action('plugins_loaded', function(){
            add_action('admin_menu', [$this, 'admin_menu']);
        });

        $this->items = get_option('ff_instagram_items');
        if( !$this->items ) $this->items = [];
    }
    
    // fetch data from instagram api
    public function fetch_data(){
        if( !$this->access_token ) return false;
        include_once 'InstagramBasicDisplay.php';
        $instagram = new FF\InstagramBasicDisplay( $this->api_args );
        $instagram->setAccessToken( $this->access_token );
        $instagram_feed = $instagram->getUserMedia( 'me', $this->limit );
        return $instagram_feed;
    }

    // create instagram as post from api feed
    public function refresh_feed(){
        
        $this->instagram_feed = $this->fetch_data();
        
        if( isset( $this->instagram_feed->error ) ) {
        	return $this->instagram_feed->error->message;
        }

        // fix for some images missing
        add_filter('image_sideload_extensions', function($allowed_extensions){
            $allowed_extensions[] = 'heic';
            return $allowed_extensions;
        });
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $updated_items = [];

        foreach( $this->instagram_feed->data as $item ) {

            $existing_item = $this->get_arr_item($this->items, 'id', $item->id);
            if( $existing_item ) {
                $updated_items[] = $existing_item;
                continue;
            }
            
            // new item
            $image_url = ( $item->media_type === 'VIDEO' ) ? $item->thumbnail_url : $item->media_url;
        
            $image_id = media_sideload_image($image_url, 0, null, 'id');
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

            $updated_items[] = [
                'id' => $item->id,
                'image_id' => $image_id,
            ];
        }
        
        $this->delete_old_items($updated_items);
        
        if( count($updated_items) ) {
            update_option('ff_instagram_items', $updated_items, false);
        }
    }

    function delete_old_items($updated_items){
        foreach( $this->items as $item ) {
            $remove_item = $this->get_arr_item($updated_items, 'image_id', $item['image_id']);
            if( !$remove_item ) {
                $this->delete_item($item['image_id']);
            }
        }
    }

    public function get_arr_item($arr, $key, $key_value){
        foreach( $arr as $item ) {
            if( $item[$key] == $key_value ) return $item;
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
        return 12;
    }

    public static function get_items( $options = [] ){

        $count = $options['count'] ?? -1;
        $image_size = $options['image_size'] ?? 'medium';
        
        $instagram_items = get_option('ff_instagram_items');
        if( !$instagram_items ) return [];
        
        $items = [];
        
        $i = 0;
        foreach( $instagram_items as $item ) { $i++;

            $items[] = [
                'id' => $item['id'],
                'image_id' => $item['image_id'],
                'image_url' => get_post_meta( $item['image_id'], 'image_url', true ),
                'media_type' => get_post_meta( $item['image_id'], 'media_type', true ),
                'link' => get_post_meta( $item['image_id'], 'link', true ),
            ];

            if( $count == $i ) break;
        }

        return $items;
    }

    public function refresh_token(){
        if( !$this->access_token ) return;
        include_once 'InstagramBasicDisplay.php';
        $instagram = new FF\InstagramBasicDisplay( $this->api_args );
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

    public function setup_cron(){
        // if ( ! wp_next_scheduled( 'ff_instagram_token_refresh' ) ) {
        //     wp_schedule_event( time(), 'weekly', 'ff_instagram_token_refresh' );
        // }
        // add_action( 'ff_instagram_token_refresh', [ $this, 'refresh_token' ] );

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