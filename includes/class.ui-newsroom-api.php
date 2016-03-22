<?php
class UiNewsroomApi {
    
    private $apiBase = 'https://api.urbanimmersive.com';
    private $apiVersion = 'v1.1';
    private $configurations;
    private $categories;
    
    public function __construct(){
        require_once(ABSPATH . "wp-admin/includes/taxonomy.php");
    }
    
    public function getBlogs(){
        $url = $this->_formatURLRequest('blogs');
        $result = $this->_getAllResults($url);
        //
        if(!$result['success']){
            return null;
        }
        //
        return $result['data'];
    }
    
    public function getCategories(){
        $newsroom_id = $this->_getCurrentNewsroomId();
        $url = $this->_formatURLRequest('blogs/'.$newsroom_id.'/categories');
        $result = $this->_getAllResults($url);
        //
        if(!$result['success']){
            return null;
        }
        //
        return $result['data'];
    }
    
    public function getArticles(){
        $newsroom_id = $this->_getCurrentNewsroomId();
        $url = $this->_formatURLRequest('blogs/'.$newsroom_id.'/articles');
        $result = $this->_getAllResults($url);
        //
        if(!$result['success']){
            return null;
        }
        //
        return $result['data'];
    }
    
    public function getPublications(){
        $newsroom_id = $this->_getCurrentNewsroomId();
        $url = $this->_formatURLRequest('blogs/'.$newsroom_id.'/publications');
        $result = $this->_getAllResults($url);
        //
        if(!$result['success']){
            return null;
        }
        //
        return $result['data'];
    }
    
    public function update(){
        //
        // 10 mins should be enough
        @set_time_limit(600);
        //
        $configurations = $this->getConfigurations();
        //
        $blogs = $this->getBlogs();
        //
        if(is_null($blogs)){
            return false;
        }
        //
        if($configurations['ui_newsroom_fecth_categories']){
            $categories = $this->getCategories();
            //
            if(is_null($categories)){
                return false;
            }
            //
            $categories_ids = array();
            //
            if(!empty($categories)){
                foreach($categories as $category){
                    $category_id = $this->_updateCategory($category['id'], $category);
                    array_push($categories_ids, $category['id']);
                }
            }
            //
            // delete not found categories
            $categories_relations = $this->getCategoriesRelations(true);
            //
            foreach($categories_ids as $category_id){
                unset($categories_relations[$category_id]);
            }
            foreach($categories_relations as $category_id=>$term_id){
                wp_delete_term($term_id, 'category');
            }
            // update categories
            $this->getCategoriesRelations(true);
        }
        //
        if($configurations['ui_newsroom_fecth_articles'] || $configurations['ui_newsroom_fecth_subscriptions']){
            $articles = $this->getArticles();
            //
            if(is_null($articles)){
                return false;
            }
            //
            $articles_post_ids = array();
            $subscriptions_posts_ids = array();
            //
            if(!empty($articles)){
                foreach($articles as $article){
                    if($configurations['ui_newsroom_fecth_articles']&&$article['object_type']=='Article'){
                        $post_id = $this->_updateArticle($article['object_data']['id'], $article['object_data']);
                        array_push($articles_post_ids, $post_id);
                    }
                    if($configurations['ui_newsroom_fecth_subscriptions']&&$article['object_type']=='ExternalArticle'){
                        $post_id = $this->_updateSubscription($article['object_data']['id'], $article['object_data']);
                        array_push($subscriptions_posts_ids, $post_id);
                    }
                }
            }
            //
            if($configurations['ui_newsroom_fecth_articles']){
                // delete all post ids that are not found
                $args = array (
                    'post__not_in' => $articles_post_ids,
                    'post_type' => 'ui_article',
                    'nopaging' => true
                );
                $query = new WP_Query($args);
                while ($query->have_posts()) {
                    $query->the_post();
                    $id = get_the_ID();
                    wp_delete_post($id, true);
                }
            }
            if($configurations['ui_newsroom_fecth_subscriptions']){
                // delete all post ids that are not found
                $args = array (
                    'post__not_in' => $subscriptions_posts_ids,
                    'post_type' => 'ui_subscription',
                    'nopaging' => true
                );
                $query = new WP_Query($args);
                while ($query->have_posts()) {
                    $query->the_post();
                    $id = get_the_ID();
                    wp_delete_post($id, true);
                }
            }
        }
        //
        if($configurations['ui_newsroom_fecth_publications']){
            $publications = $this->getPublications();
            //
            if(is_null($publications)){
                return false;
            }
            //
            $publications_post_ids = array();
            //
            if(!empty($publications)){
                foreach($publications as $publication){
                    $post_id = $this->_updatePublication($publication['id'], $publication);
                    array_push($publications_post_ids, $post_id);
                }
            }
            //
            // delete all post ids that are not found
            $args = array (
                'post__not_in' => $publications_post_ids,
                'post_type' => 'ui_publication',
                'nopaging' => true
            );
            $query = new WP_Query($args);
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                wp_delete_post($id, true);
            }
        }
        //
        update_option('ui_newsroom_last_update', time());
        //
        return true;
    }
    
    public function testCredentials(){
        $configurations = $this->getConfigurations();
        //
        if(!isset($configurations['ui_newsroom_client_id'])||!isset($configurations['ui_newsroom_client_secret'])){
            return false;
        }
        //
        $blogs = $this->getBlogs();
        // set the default newsroom
        if(!empty($blogs)){
            update_option('ui_newsroom_working_newsroom', $blogs[0]);
        } else {
            delete_option('ui_newsroom_working_newsroom');
        }
        //
        return !is_null($blogs);
    }
    
    private function getCategoriesRelations($force = false){
        if(!$force&&$this->categories){
            return $this->categories;
        }
        global $wpdb;
        $categories_results = $wpdb->get_results("SELECT $wpdb->terms.term_id, $wpdb->termmeta.meta_value FROM $wpdb->terms LEFT JOIN $wpdb->termmeta ON ($wpdb->termmeta.term_id = $wpdb->terms.term_id) WHERE meta_key = 'ui_category_id'");
        //
        $categories = array();
        //
        if(!empty($categories_results)){
            foreach($categories_results as $categories_result){
                $categories[$categories_result->meta_value] = $categories_result->term_id;
            }
        }
        $this->categories = $categories;
        return $this->categories;
    }
    
    private function _updateCategory($id, $category = null, $force = false){
        // find category if it exists
        $categories_relations = $this->getCategoriesRelations();
        //
        $category_id = null;
        $exists = false;
        $update = false;
        if(isset($categories_relations[$id])){
            $exists = true;
            $category_id = $categories_relations[$id];
            if($category){
                // do we need to update the post based on the modified date
                $stored_category_modified = get_term_meta( $category_id, 'ui_category_modified', true);
                $current_category_modified = strtotime($category['modified']);
                //
                if($current_category_modified>$stored_category_modified){
                    $update = true;
                }
            }
        }
        if(!$exists||$update||$force){
            $category_entry = array(
              'cat_name' => $category['title']
            );
            $metas = array();
            if($category_id){
                $category_entry['cat_ID'] = $category_id;
            } else {
                array_push($metas, array('key'=>'ui_category_id', 'value'=>$id));
            }
            //
            $category_id = wp_insert_category( $category_entry );
            array_push($metas, array('key'=>'ui_category_modified', 'value'=>strtotime($category['modified'])));
            //
            if(!$category_id){
                return false;
            }
            //
            foreach($metas as $meta){
                update_term_meta($category_id, $meta['key'], $meta['value']);
            }
        }
        return $category_id;
    }
    
    private function _updatePublication($id, $publication = null, $force = false){
        // find post if it exists
        $args = array(
            'meta_key' => 'ui_publication_id',
            'meta_value' => $id,
            'post_type' => 'ui_publication',
            'post_status' => 'any',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        //
        $post_id = null;
        $exists = false;
        $update = false;
        if(!empty($posts)){
            $exists = true;
            $post_id = $posts[0]->ID;
            if($publication){
                // do we need to update the post based on the modified date
                $stored_publication_modified = get_post_meta( $post_id, 'ui_publication_modified');
                $current_publication_modified = strtotime($publication['modified']);
                //
                if($current_publication_modified>$stored_publication_modified){
                    $update = true;
                }
            }
        }
        if(!$exists||$update||$force){
            //
            $publication_data = $publication;
            //
            if($publication_data['status']!='ONLINE'){
                if($exists){
                    // remove post
                    wp_delete_post( $post_id, true );
                }
                return $post_id;
            }
            //
            $url = $publication_data['object_data']['url'];
            $online_date = date('Y-m-d H:i:s', strtotime($publication_data['online_date']));
            $modified_date = date('Y-m-d H:i:s', strtotime($publication_data['modified']));
            //
            $metas = array();
            $metas['ui_publication_id'] = $id;
            $metas['ui_publication_modified'] = strtotime($publication_data['modified']);
            $metas['ui_publication_url'] = $url;
            $metas['ui_publication_type'] = $publication_data['object_type'];
            $metas['ui_publication_data'] = serialize($publication_data['object_data']);
            //
            $post = array();
            if($post_id){
                $post['ID'] = $post_id;
                //
                $medias = get_attached_media( 'image', $post_id );
                foreach($medias as $media){
                    wp_delete_attachment($media->ID);
                }
            }
            //
            $post['post_author'] = $this->_getDefaultAuthor();
            $post['post_type'] = 'ui_publication';
            $post['post_title'] = $publication_data['object_data']['title'];
            $post['post_excerpt'] = $publication_data['object_data']['description'];
            $post['post_content'] = $publication_data['object_data']['content'];
            $post['post_status'] = 'publish';
            $post['post_date'] = $online_date;
            $post['post_modified'] = $modified_date;
            $post['post_date_gmt'] = $online_date;
            $post['post_modified_gmt'] = $modified_date;
            $post['meta_input'] = $metas;
            //
            $post_id = wp_insert_post($post);
            //
            if(!$post_id){
                return false;
            }
            //
            wp_set_object_terms( $post_id, 'post-format-ui_publication', 'post_format' );
            //
            $thumbnail = $publication_data['object_data']['hosted_image_url'];
            $attach_id = $this->_downloadAndSaveImageAsAttachment($thumbnail, $id, $post_id, 'Thumbnail for publication #' . $post_id);
            //
            set_post_thumbnail( $post_id, $attach_id );
        }
        return $post_id;
    }
    
    private function _updateSubscription($id, $article = null, $force = false){
        // find post if it exists
        $args = array(
            'meta_key' => 'ui_subscription_id',
            'meta_value' => $id,
            'post_type' => 'ui_subscription',
            'post_status' => 'any',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        //
        $post_id = null;
        $exists = false;
        $update = false;
        if(!empty($posts)){
            $exists = true;
            $post_id = $posts[0]->ID;
            if($article){
                // do we need to update the post based on the modified date
                $stored_article_modified = get_post_meta( $post_id, 'ui_subscription_modified');
                $current_article_modified = strtotime($article['modified']);
                //
                if($current_article_modified>$stored_article_modified){
                    $update = true;
                }
            }
        }
        if(!$exists||$update||$force){
            //
            $article_data = $article;
            //
            if($article_data['status']!='ONLINE'){
                if($exists){
                    // remove post
                    wp_delete_post( $post_id, true );
                }
                return $post_id;
            }
            //
            $url = $article_data['url'];
            $online_date = date('Y-m-d H:i:s', strtotime($article_data['online_date']));
            $modified_date = date('Y-m-d H:i:s', strtotime($article_data['modified']));
            //
            $metas = array();
            $metas['ui_subscription_id'] = $id;
            $metas['ui_subscription_modified'] = strtotime($article_data['modified']);
            $metas['ui_subscription_url'] = $url;
            $metas['ui_subscription_via_name'] = $article_data['via']['title'];
            $metas['ui_subscription_via_url'] = $article_data['via']['url'];
            //
            $post = array();
            if($post_id){
                $post['ID'] = $post_id;
                //
                $medias = get_attached_media( 'image', $post_id );
                foreach($medias as $media){
                    wp_delete_attachment($media->ID);
                }
            }
            //
            $post['post_author'] = $this->_getDefaultAuthor();
            $post['post_type'] = 'ui_subscription';
            $post['post_title'] = $article_data['title'];
            $post['post_excerpt'] = $article_data['excerpt'];
            $post['post_status'] = 'publish';
            $post['post_date'] = $online_date;
            $post['post_modified'] = $modified_date;
            $post['post_date_gmt'] = $online_date;
            $post['post_modified_gmt'] = $modified_date;
            $post['meta_input'] = $metas;
            //
            $post_id = wp_insert_post($post);
            //
            if(!$post_id){
                return false;
            }
            //
            wp_set_object_terms( $post_id, 'post-format-ui_subscription', 'post_format' );
            //
            $thumbnail = $article_data['thumbnail'];
            $attach_id = $this->_downloadAndSaveImageAsAttachment($thumbnail, $id, $post_id, 'Thumbnail for subscription #' . $post_id);
            //
            set_post_thumbnail( $post_id, $attach_id );
        }
        return $post_id;
    }
    
    private function _updateArticle($id, $article = null, $force = false){
        // find post if it exists
        $args = array(
            'meta_key' => 'ui_article_id',
            'meta_value' => $id,
            'post_type' => 'ui_article',
            'post_status' => 'any',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        //
        $post_id = null;
        $exists = false;
        $update = false;
        if(!empty($posts)){
            $exists = true;
            $post_id = $posts[0]->ID;
            if($article){
                // do we need to update the post based on the modified date
                $stored_article_modified = get_post_meta( $post_id, 'ui_article_modified');
                $current_article_modified = strtotime($article['modified']);
                //
                if($current_article_modified>$stored_article_modified){
                    $update = true;
                }
            }
        }
        if(!$exists||$update||$force){
            // get full post
            $newsroom_id = $this->_getCurrentNewsroomId();
            $url = $this->_formatURLRequest('blogs/'.$newsroom_id.'/articles/' . $id);
            $result = $this->_getResults($url);
            //
            if(!$result['success']){
                return false;
            }
            //
            $article_data = $result['data'];
            //
            if($article_data['status']!='ONLINE'){
                if($exists){
                    // remove post
                    wp_delete_post( $post_id, true );
                }
                return $post_id;
            }
            //
            $article_content = $this->_formatArticleContentFromPages($article_data['pages']);
            //
            $permalink = substr(parse_url($article_data['permalink'], PHP_URL_PATH), 1);
            $url = substr(parse_url($article_data['url'], PHP_URL_PATH), 1);
            $online_date = date('Y-m-d H:i:s', strtotime($article_data['online_date']));
            $modified_date = date('Y-m-d H:i:s', strtotime($article_data['modified']));
            //
            $metas = array();
            $metas['ui_article_id'] = $id;
            $metas['ui_article_modified'] = strtotime($article_data['modified']);
            $metas['ui_article_permalink'] = $permalink;
            $metas['ui_article_url'] = $url;
            //
            $post = array();
            if($post_id){
                $post['ID'] = $post_id;
                //
                $medias = get_attached_media( 'image', $post_id );
                foreach($medias as $media){
                    wp_delete_attachment($media->ID);
                }
            }
            //
            $post['post_author'] = $this->_getDefaultAuthor();
            $post['post_type'] = 'ui_article';
            $post['post_title'] = $article_data['title'];
            $post['post_content'] = $article_content;
            $post['post_status'] = 'publish';
            $post['post_date'] = $online_date;
            $post['post_modified'] = $modified_date;
            $post['post_date_gmt'] = $online_date;
            $post['post_modified_gmt'] = $modified_date;
            $post['meta_input'] = $metas;
            //
            $post_id = wp_insert_post($post);
            //
            if(!$post_id){
                return false;
            }
            //
            $configurations = $this->getConfigurations();
            //
            if($configurations['ui_newsroom_download_images']&&!empty($article_data['images'])){
                $image_map = array();
                foreach($article_data['images'] as $image){
                    $image_map[$image['key']] = $image['url'];
                }
                $this->image_map = $image_map;
                $this->current_article_id = $id;
                $this->current_post_id = $post_id;
                //
                $post['ID'] = $post_id;
                $post['post_content'] = preg_replace_callback('/<img.*data-image-key="(.*)".*>/isU', array($this, '_downloadAndReplaceImage'), $article_content);
                //
                wp_insert_post($post);
            }
            //
            $thumbnail = $article_data['thumbnail'];
            $attach_id = $this->_downloadAndSaveImageAsAttachment($thumbnail, $id, $post_id, 'Thumbnail for post #' . $post_id);
            //
            set_post_thumbnail( $post_id, $attach_id );
            //
            $categories_relations = $this->getCategoriesRelations(true);
            //
            $category_id = null;
            if(isset($article_data['category']['id'])){
                $category_id = $article_data['category']['id'];
            }
            if($category_id&&isset($categories_relations[$category_id])){
                wp_set_post_categories($post_id, $categories_relations[$category_id]);
            }
        }
        return $post_id;
    }

    private function _getDefaultAuthor(){
        if(isset($this->default_author)){
            return $this->default_author;
        }
        //
        $configurations = $this->getConfigurations();
        //
        $author_id = null;
        if(isset($configurations['ui_newsroom_default_author'])){
            $author_id = $configurations['ui_newsroom_default_author'];
        }
        //
        if($author_id&&!get_user_by('id',$author_id)){
            $author_id = null;
        }
        //
        if(!$author_id){
            // get first user
            $users = get_users(array('number'=>1));
            //
            $author_id = $users[0]->ID;
        }
        //
        $this->default_author = $author_id;
        //
        return $this->default_author;
    }

    private function _downloadAndReplaceImage($matches){
        $value = $matches[0];
        $key = $matches[1];
        if(isset($this->image_map[$key])){
            $thumbnail = $this->image_map[$key];
            $thumbnail_filename = explode('/', $thumbnail);
            $thumbnail_filename = $thumbnail_filename[count($thumbnail_filename)-1];
            //
            $attach_id = $this->_downloadAndSaveImageAsAttachment($thumbnail, $this->current_article_id, $this->current_post_id, 'Image in post #' . $this->current_post_id);
            //
            $wp_upload_dir = wp_upload_dir();
            $replace = $wp_upload_dir['baseurl'].'/newsroom/'.$this->current_article_id.'/'.$thumbnail_filename;
            $value = preg_replace('/src=".*"/isU', 'src="'.$replace.'"', $value);
        }
        return $value;
    }
    
    private function getConfigurations(){
        if(!$this->configurations){
            $this->configurations = get_option( 'ui_newsroom_options' );
        }
        //
        if(!isset($this->configurations['ui_newsroom_download_images'])){
            $this->configurations['ui_newsroom_download_images'] = true;
        }
        if(!isset($this->configurations['ui_newsroom_fecth_articles'])){
            $this->configurations['ui_newsroom_fecth_articles'] = true;
        }
        if(!isset($this->configurations['ui_newsroom_fecth_categories'])){
            $this->configurations['ui_newsroom_fecth_categories'] = true;
        }
        if(!isset($this->configurations['ui_newsroom_fecth_subscriptions'])){
            $this->configurations['ui_newsroom_fecth_subscriptions'] = true;
        }
        if(!isset($this->configurations['ui_newsroom_fecth_publications'])){
            $this->configurations['ui_newsroom_fecth_publications'] = true;
        }
        //
        return $this->configurations;
    }
    
    private function _downloadAndSaveImageAsAttachment($thumbnail, $article_id, $post_id, $name){
        $wp_upload_dir = wp_upload_dir();
        $full_dirname = $wp_upload_dir['basedir'] . '/newsroom/' . $article_id;
        //
        if ( ! file_exists( $full_dirname ) ) {
            wp_mkdir_p( $full_dirname );
        }
        //
        $thumbnail_filename = explode('/', $thumbnail);
        $thumbnail_filename = $thumbnail_filename[count($thumbnail_filename)-1];
        $thumbnail_filename = preg_replace('/\?.*/is', '', $thumbnail_filename);
        //
        if(strpos($thumbnail_filename, '.')===false){
            $thumbnail_filename .= '.jpg';
        }
        //
        $filename = $full_dirname . '/' . $thumbnail_filename;
        //
        $content = @file_get_contents($thumbnail);
        if(!$content){
            return false;
        }
        $save = @file_put_contents($filename, $content);
        if(!$save){
            return false;
        }
        //
        $filetype = wp_check_filetype( $filename , null );
        //
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace( '/\.[^.]+$/', '', $name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        //
        $attach_id = wp_insert_attachment( $attachment, $filename, $post_id );
        //
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        //
        $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        //
        return $attach_id;
    }

    private function _formatArticleContentFromPages($pages){
        return implode('<p><!--nextpage--></p>', $pages);
    }
    
    private function _getCurrentNewsroomId(){
        $newsroom = get_option('ui_newsroom_working_newsroom');
        if(!$newsroom){
            return false;
        }
        return $newsroom['id'];
    }
    
    private function _getAllResults($url){
        $results = $this->_getResults($url);
        if(!$results['success']){
            return $results;
        }
        // fetch pages
        $all_data = $results['data'];
        if($results['paging']['next']){
            $next = $results['paging']['next'];
            $next = $this->_addCredentials($next);
            //
            while($next){
                $results = $this->_getResults($next);
                if(!$results['success']){
                    return $results;
                }
                foreach($results['data'] as $data){
                    array_push($all_data, $data);
                }
                if($results['paging']['next']){
                    $next = $results['paging']['next'];
                    $next = $this->_addCredentials($next);
                } else {
                    $next = false;
                }
            }
        }
        $results['data'] = $all_data;
        //
        return $results;
    }
    
    private function _getResults($url){
        $response = wp_remote_get( $url );
        //
        if(is_a($response, 'WP_Error') ){
            return array('success'=>false, 'reason'=>'WP_Error');
        }
        //
        $body = json_decode($response['body'], true);
        //
        if($response['response']['code'] == '401'){
            return array('success'=>false, 'reason'=>$body['error']);
        }
        if($response['response']['code'] != '200'){
            return array('success'=>false, 'reason'=>$response['response']['message']);
        }
        //
        return array('success'=>true, 'data'=>$body['data'], 'paging'=>$body['paging']);
    }
    
    private function _addCredentials($endpoint){
        //
        $configurations = $this->getConfigurations();
        //
        if(!isset($configurations['ui_newsroom_client_id'])||!isset($configurations['ui_newsroom_client_secret'])){
            return false;
        }
        //
        if(strpos($endpoint, '?')!==false){
            return $endpoint . '&client_id=' . $configurations['ui_newsroom_client_id'] . '&client_secret=' . $configurations['ui_newsroom_client_secret'];
        }
        return $endpoint . '?client_id=' . $configurations['ui_newsroom_client_id'] . '&client_secret=' . $configurations['ui_newsroom_client_secret'];
    }
    
    private function _formatURLRequest($endpoint){
        $endpoint = $this->apiBase . '/' . $this->apiVersion . '/' . $endpoint;
        //
        return $this->_addCredentials($endpoint);
    }
}