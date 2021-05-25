<?php 
/**
 * Fetch our TAXOPRESS Tag Clouds option.
 *
 * @return mixed
 */
function taxopress_get_tagcloud_data()
{
    return array_filter( (array)apply_filters('taxopress_get_tagcloud_data', get_option('taxopress_tagclouds', []), get_current_blog_id()));
}

/**
 * Get the selected tagcloud from the $_POST global.
 *
 * @return bool|string False on no result, sanitized tagcloud if set.
 * @internal
 *
 */
function taxopress_get_current_tagcloud()
{

    $tagclouds = false;

    if (!empty($_GET) && isset($_GET['taxopress_tagcloud'])) {
        $tagclouds = sanitize_text_field($_GET['taxopress_tagcloud']);
    } else {
        $tagclouds = taxopress_get_tagcloud_data();
        if (!empty($tagclouds)) {
            // Will return the first array key.
            $tagclouds = key($tagclouds);
        }
    }

    /**
     * Filters the current tagcloud to edit.
     *
     * @param string $tagclouds tagcloud slug.
     */
    return apply_filters('taxopress_current_tagcloud', $tagclouds);
}

/**
 * Handle the save and deletion of tagcloud data.
 */
function taxopress_process_tagcloud()
{

    if (wp_doing_ajax()) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if (empty($_GET)) {
        return;
    }

    if (!isset($_GET['page'])) {
        return;
    }
    if ('st_tagclouds' !== $_GET['page']) {
        return;
    }

    if (isset($_GET['new_tagcloud'])) {
        if ((int)$_GET['new_tagcloud'] === 1) {
            add_action('admin_notices', "taxopress_tagcloud_update_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_saved_tagcloud_filter_removable_query_args');
        }
    }

    if (isset($_GET['deleted_tagcloud'])) {
        if ((int)$_GET['deleted_tagcloud'] === 1) {
            add_action('admin_notices', "taxopress_tagcloud_delete_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_deleted_tagcloud_filter_removable_query_args');
        }
    }


    if (!empty($_POST) && isset($_POST['tagcloud_submit'])) {
        $result = '';
        if (isset($_POST['tagcloud_submit'])) {
            check_admin_referer('taxopress_addedit_tagcloud_nonce_action', 'taxopress_addedit_tagcloud_nonce_field');
            $result = taxopress_update_tagcloud($_POST);
        }

        if ($result) {
            wp_safe_redirect(
                add_query_arg(
                [
                    'page'               => 'st_tagclouds',
                    'add'                => 'new_item',
                    'action'             => 'edit',
                    'taxopress_tagcloud' => $result,
                    'new_tagcloud'       => 1,
                ],
                taxopress_admin_url('admin.php')
                )
            );
            
            exit();
        }
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-delete-tagcloud') {
        $nonce = esc_attr($_REQUEST['_wpnonce']);
        if (wp_verify_nonce($nonce, 'tagcloud-action-request-nonce')) {
            taxopress_action_delete_tagcloud($_REQUEST['taxopress_tagcloud']);
        }
        add_filter('removable_query_args', 'taxopress_delete_tagcloud_filter_removable_query_args');
    }
}

add_action('init', 'taxopress_process_tagcloud', 8);


/**
 * Add to or update our TAXOPRESS option with new data.
 *
 *
 * @param array $data Array of tagcloud data to update. Optional.
 * @return bool|string False on failure, string on success.
 * @internal
 *
 */
function taxopress_update_tagcloud($data = [])
{
    foreach ($data as $key => $value) {

        if (is_string($value)) {
            $data[$key] = sanitize_text_field($value);
        } else {
            array_map('sanitize_text_field', $data[$key]);
        }
    }

    $tagclouds          = taxopress_get_tagcloud_data();

    $title                        = $data['taxopress_tag_cloud']['title'];
    $title                        = str_replace('"', '', htmlspecialchars_decode($title));
    $title                        = htmlspecialchars($title, ENT_QUOTES);
    $title                        = trim($title);
    $data['taxopress_tag_cloud']['title'] = stripslashes_deep($title);

    $xformat                       = $data['taxopress_tag_cloud']['xformat'];
    $data['taxopress_tag_cloud']['xformat'] = stripslashes_deep($xformat);

    if( !empty($data['taxopress_tag_cloud']['color'])){ 
        $data['taxopress_tag_cloud']['color'] = taxopress_disp_boolean($data['taxopress_tag_cloud']['color']);
    }
    
    if (isset($data['edited_tagcloud'])) {
        $tagcloud_id = $data['edited_tagcloud'];
        $tagclouds[$tagcloud_id] = $data['taxopress_tag_cloud'];
        $success = update_option('taxopress_tagclouds', $tagclouds);
        //return 'update_success';
    }else{
        $tagcloud_id = time();
        $data['taxopress_tag_cloud']['ID'] = $tagcloud_id;
        $tagclouds[$tagcloud_id] = $data['taxopress_tag_cloud'];
        $success = update_option('taxopress_tagclouds', $tagclouds);
        //return 'add_success';
    }
    return $tagcloud_id;
    
}

/**
 * Successful update callback.
 */
function taxopress_tagcloud_update_success_admin_notice()
{
    echo taxopress_admin_notices_helper(__('Settings updated successfully.', 'simpletags'));
}

/**
 * Successful deleted callback.
 */
function taxopress_tagcloud_delete_success_admin_notice()
{
    echo taxopress_admin_notices_helper(__('Tag cloud successfully deleted.', 'simpletags'), false);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_saved_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'new_tagcloud',
    ]);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_deleted_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'deleted_tagcloud',
    ]);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_delete_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'action',
        'taxopress_tagcloud',
        '_wpnonce',
    ]);
}

/**
 * Delete our custom taxonomy from the array of taxonomies.
 * @return bool|string False on failure, string on success.
 */
function taxopress_action_delete_tagcloud($tagcloud_id)
{
    $tagclouds = taxopress_get_tagcloud_data();

    if (array_key_exists($tagcloud_id, $tagclouds)) {
        unset($tagclouds[$tagcloud_id]);
        $success = update_option('taxopress_tagclouds', $tagclouds);
    }

    if (isset($success)) {
        add_action('admin_notices', "taxopress_taxdeleted_admin_notice");
        wp_safe_redirect(
            add_query_arg(
            [
                'page'               => 'st_tagclouds',
                'deleted_tagcloud'   => 1,
            ],
            taxopress_admin_url('admin.php')
            )
        );   
        exit();
    }
}

add_shortcode('taxopress_tagcloud', 'taxopress_tagcloud_shortcode');
function taxopress_tagcloud_shortcode($atts)
    {
        extract(shortcode_atts(array(
            'id' => 0
        ), $atts));

        $tagcloud_id = $id;
        $tagclouds = taxopress_get_tagcloud_data();

        ob_start();
        if (array_key_exists($tagcloud_id, $tagclouds)) {
            $tagcloud_arg = build_query($tagclouds[$tagcloud_id]);

            echo SimpleTags_Client_TagCloud::extendedTagCloud( $tagcloud_arg );

        } else {
            echo __('Tag cloud not found.', 'simpletags');
        }

        $html = ob_get_clean();
        return $html;


    }
?>