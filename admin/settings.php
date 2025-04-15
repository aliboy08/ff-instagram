<?php
if ( ! defined( 'ABSPATH' ) ) die();

echo '<h1>Instagram Settings</h1>';

$page_slug = 'instagram';

$main_url = '?page=ff_instagram';

$action = isset( $_GET['action'] ) ? $_GET['action'] : false;
$action_complete = isset( $_GET['action_complete'] ) ? $_GET['action_complete'] : false;
$refresh_url = $main_url .'&action_complete='. $action;

$ff_instagram = FF_Instagram::getInstance();

// Refresh Feed
if( $action == 'refresh-feed' ) {
    $error = $ff_instagram->refresh_feed();
    if( $error ) {
        pre_debug('ERROR: '. $error);
    }
    else {
        // wp_redirect( $refresh_url );
    }
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


$setting_fields = [
    'ff_instagram_app_id' => 'App ID',
    'ff_instagram_app_secret' => 'App Secret',
    'ff_instagram_webhook_verify_token' => 'Webhook Verify Token',
    'ff_instagram_initial_access_token' => 'Access Token',
    'ff_instagram_redirect_uri' => 'Redirect URI',
    'ff_instagram_num' => 'Number of items',
];

// save
if( isset( $_POST['ff_instagram_update_settings'] ) ) {
    foreach( $setting_fields as $key => $label ) {
        if( !isset($_POST[$key]) ) continue;
        update_option($key, sanitize_text_field($_POST[$key]));
    }

    echo '<p>Saved</p>';
}
?>

<form action="" method="post">
<table class="form-table">

    <?php foreach( $setting_fields as $key => $label ) : ?>
        <tr>
            <th><?=$label?></th>
            <td>
                <input type="text" name="<?=$key?>" value="<?=get_option($key)?>" class="regular-text">
            </td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <th></th>
        <td><input type="submit" value="Save" class="button button-primary" name="ff_instagram_update_settings"></td>
    </tr>

    <?php /* ?>
    <tr>
        <th>Current Access Token: </th>
        <td>
            <?php
            echo '<div>'. $ff_instagram->access_token .'</div>';
            ?>

            <br/>
            <a href="<?php echo $main_url; ?>&action=refresh-token" class="button button-primary">Refresh Access Token</a>

            <br/><br/><br/>
            
            <a href="<?php echo $main_url; ?>&action=delete-refreshed-token" class="button">DELETE Refreshed Access Token</a>
            <br/><br/>
            Delete refreshed token if you have set a new token for a different account

        </td>
    </tr>
    <?php */ ?>

    <tr>
        <th>Instagram Feed</th>
        <td>
            <?php
            foreach( $ff_instagram->items as $item ) {
                $image_url = wp_get_attachment_image_url($item['image_id'], 'thumbnail');
                echo '<div style="display:inline-block; width: 100px; height: 100px; margin: 5px; background-color: #eee;"><img src="'. $image_url .'" width="100"></div>';
            }
            ?>
            <br/><br/>
            <a href="<?php echo $main_url; ?>&action=refresh-feed" class="button button-primary">Refresh Feed</a>

            <br/><br/><br/>
            <a href="<?php echo $main_url; ?>&action=delete-feed" class="button">Delete Feed</a>
        </td>
    </tr>
    
</table>
</form>