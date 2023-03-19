<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

$page_slug = 'instagram';

$action = isset( $_GET['action'] ) ? $_GET['action'] : false;
$action_complete = isset( $_GET['action_complete'] ) ? $_GET['action_complete'] : false;
$refresh_url = '?page=5x5-tools&tab='. $page_slug .'&action_complete='. $action;

if( isset( $_POST['ff_instagram_update_settings'] ) ) {
    echo '<p>Saved</p>';
    update_option( 'ff_instagram_initial_access_token', sanitize_text_field( $_POST['ff_instagram_initial_access_token'] ) );
    update_option( 'ff_instagram_num', sanitize_text_field( $_POST['ff_instagram_num'] ) );
}

$ff_instagram = FF_Instagram::getInstance();

// Refresh Feed
if( $action == 'refresh-feed' ) {
    $ff_instagram->refresh_feed();
    wp_redirect( $refresh_url );
}

// Refresh Access Token
if( $action == 'refresh-token' ) {
    $ff_instagram->refresh_token();
    wp_redirect( $refresh_url );
}

// Delete Access Token
if( $action == 'delete-refreshed-token' ) {
    $ff_instagram->delete_token();
    wp_redirect( $refresh_url );
}

// Delete feed items + post & attachment images
if( $action == 'delete-feed' ) {
    $ff_instagram->delete_items();
    wp_redirect( $refresh_url );
}

if( $action_complete == 'refresh-feed' ) {
    echo '<p>Instagram feed refreshed</p>';
}

if( $action_complete == 'refresh-token' ) {
    echo '<p>Instagram token refreshed</p>';
}

if( $action_complete == 'delete-refreshed-token' ) {
    echo '<p>Refreshed Token Removed</p>';
}

if( $action_complete == 'delete-feed' ) {
    echo '<p>Feed Removed</p>';
}

$initial_access_token = get_option( 'ff_instagram_initial_access_token' );
$num = get_option( 'ff_instagram_num' );
if( !$num ) $num = 15;
?>

<form action="" method="post">
<table class="form-table">
    
    <tr>
        <th>Initial Access Token</th>
        <td>
            <input type="text" name="ff_instagram_initial_access_token" value="<?php echo $initial_access_token; ?>" class="regular-text">
        </td>
    </tr>

    <tr>
        <th>Number of items</th>
        <td>
            <input type="text" name="ff_instagram_num" value="<?php echo $num; ?>" class="regular-text">
        </td>
    </tr>

    <tr>
        <th></th>
        <td><input type="submit" value="Save" class="button button-primary" name="ff_instagram_update_settings"></td>
    </tr>

    <tr>
        <th>Current Access Token: </th>
        <td>
            <?php
            echo '<div>'. $ff_instagram->access_token .'</div>';
            ?>

            <br/>
            <a href="?page=5x5-tools&tab=instagram&action=refresh-token" class="button button-primary">Refresh Access Token</a>

            <br/><br/><br/>
            
            <a href="?page=5x5-tools&tab=instagram&action=delete-refreshed-token" class="button">DELETE Refreshed Access Token</a>
            <br/><br/>
            Delete refreshed token if you have set a new token for a different account

        </td>
    </tr>

    <tr>
        <th>Instagram Feed</th>
        <td>
            <?php
            // Feed preview
            $instagram_feed = FF_Instagram::get_items();
            if( $instagram_feed ) {
                foreach( $instagram_feed as $item ) {
                    echo '<div style="display:inline-block; width: 100px; height: 100px; margin: 5px; background-color: #eee;"><img src="'. $item['image_url'] .'" width="100"></div>';
                }
            }
            ?>
            <br/><br/>
            <a href="?page=5x5-tools&tab=instagram&action=refresh-feed" class="button button-primary">Refresh Feed</a>

            <br/><br/><br/>
            <a href="?page=5x5-tools&tab=instagram&action=delete-feed" class="button">Delete Feed</a>
        </td>
    </tr>
    
</table>
</form>