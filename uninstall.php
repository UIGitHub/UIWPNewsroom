<?php
/**
 *
 * @link       http://www.urbanimmersive.com
 * @since      0.9.2
 *
 * @package    ui-newsroom
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option('ui_newsroom_options');
delete_option('ui_newsroom_test');
delete_option('ui_newsroom_api_key');
delete_option('ui_newsroom_working_newsroom');
delete_option('ui_newsroom_last_update');
//
$args = array (
    'post_type' => array('ui_article', 'ui_publication', 'ui_subscription'),
    'nopaging' => true
);
$query = new WP_Query($args);
while ($query->have_posts()) {
    $query->the_post();
    $id = get_the_ID();
    //
    $post = get_post($id);
    //
    $article_id = null;
    if( $post->post_type == 'ui_article' || (isset($post->real_post_type) && $post->real_post_type == 'ui_article' )){
        $article_id = get_post_meta( $post->ID, 'ui_article_id', true);
    } else if( $post->post_type == 'ui_subscription'){
        $article_id = get_post_meta( $post->ID, 'ui_subscription_id', true);
    } else if( $post->post_type == 'ui_publication'){
        $article_id = get_post_meta( $post->ID, 'ui_publication_id', true);
    }
    //
    if($article_id){
        $medias = get_attached_media( 'image', $post_id );
        foreach($medias as $media){
            wp_delete_attachment($media->ID);
        }
        // remove media folder
        $wp_upload_dir = wp_upload_dir();
        $full_dirname = $wp_upload_dir['basedir'] . '/newsroom/' . $article_id;
        if(file_exists($full_dirname)){
            @rmdir($full_dirname);
        }
    }
    //
    wp_delete_post($id, true);
}
//
$wp_upload_dir = wp_upload_dir();
$full_dirname = $wp_upload_dir['basedir'] . '/newsroom/';
if(file_exists($full_dirname)){
    @rmdir($full_dirname);
}
//
// delete categories
global $wpdb;
$categories_results = $wpdb->get_results("SELECT $wpdb->terms.term_id, $wpdb->termmeta.meta_value FROM $wpdb->terms LEFT JOIN $wpdb->termmeta ON ($wpdb->termmeta.term_id = $wpdb->terms.term_id) WHERE meta_key = 'ui_category_id'");
if(!empty($categories_results)){
    foreach($categories_results as $categories_result){
        wp_delete_term($categories_result->term_id, 'category');
    }
}
//
flush_rewrite_rules();
wp_reset_postdata();