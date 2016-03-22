<?php

class UiNewsroom {
    
    public function __construct(){
    }
    
    public function init() {
        $args = array(
            'labels' => array(
                'name' => __('UI Newsroom', 'ui-newsroom')
            ),
            'public' => true,
            'has_archive' => false,
            'taxonomies' => array('category'),
            'capability_type' => 'post',
            'capabilities' => array ('read_post'),
            'map_meta_cap' => true,
            'menu_position' => 5,
            'hierarchical' => false,
            'rewrite' => array(
                'slug' => 'newsroom'
            ),
            'query_var' => false,
            'delete_with_user' => false,
            'can_export' => false,
            'supports' => array( 'title', 'editor', 'thumbnail'),
        );
        register_post_type('ui_article', $args);
        //
        $args = array(
            'labels' => array(
                'name' => __('UI Subscriptions', 'ui-newsroom')
            ),
            'public' => true,
            'has_archive' => false,
            'taxonomies' => array(),
            'capability_type' => 'ui_subscription',
            'capabilities' => array (),
            'map_meta_cap' => true,
            'menu_position' => 5,
            'hierarchical' => false,
            'query_var' => false,
            'rewrite' => false,
            'delete_with_user' => false,
            'can_export' => false,
            'supports' => array( 'title', 'editor', 'thumbnail', 'post-formats'),
        );
        register_post_type('ui_subscription', $args);
        //
        $args = array(
            'labels' => array(
                'name' => __('UI Publications', 'ui-newsroom')
            ),
            'public' => true,
            'has_archive' => false,
            'taxonomies' => array(),
            'capability_type' => 'ui_publication',
            'capabilities' => array (),
            'map_meta_cap' => true,
            'menu_position' => 5,
            'hierarchical' => false,
            'query_var' => false,
            'rewrite' => false,
            'delete_with_user' => false,
            'can_export' => false,
            'supports' => array( 'title', 'editor', 'thumbnail', 'post-formats'),
        );
        register_post_type('ui_publication', $args);
        //
        add_filter( 'post_link', array($this, 'post_link'), 10, 2 );
        add_filter( 'post_type_link', array($this,'post_link'), 10, 2 );
        add_filter( 'request', array($this,'request'), 10, 1);
        add_filter( 'pre_get_posts', array($this, 'pre_get_posts') );
        add_action( 'the_post', array($this, 'the_post') );
        add_action( 'before_delete_post', array($this, 'before_delete_post'));
    }
    public function load_textdomain(){
        load_plugin_textdomain( 'ui-newsroom', false, 'ui-newsroom/languages/' );
    }
    
    public function before_delete_post($post_id){
        $post = get_post($post_id);
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
    }
    
    public function pre_get_posts( $query ) {
        if ( is_home() || is_feed() || is_category() || is_single() ){
            if(!isset($query->query['post_type'])){
                $post_types = array('post');
                $options = get_option('ui_newsroom_options');
                if(isset($options['ui_newsroom_query_articles'])&&$options['ui_newsroom_query_articles']){
                    array_push($post_types, 'ui_article');
                }
                if(isset($options['ui_newsroom_query_subscriptions'])&&$options['ui_newsroom_query_subscriptions']){
                    array_push($post_types, 'ui_subscription');
                }
                if(isset($options['ui_newsroom_query_publications'])&&$options['ui_newsroom_query_publications']){
                    array_push($post_types, 'ui_publication');
                }
                $query->set( 'post_type', $post_types );
            }
        }
        return $query;
    }
    
    public function the_post( $post_object ) {
        if($post_object->post_type == 'ui_article'){
            // ui_article need to act as a post
            $post_object->post_type = 'post';
            $post_object->real_post_type = 'ui_article';
        }
    }
    
    public function post_link($permalink, $post) {
        if($post->post_type=='ui_article'||(isset($post->real_post_type)&&$post->real_post_type=='ui_article')){
            $custom_permalink = get_post_meta( $post->ID, 'ui_article_url', true ); 
            if ( $custom_permalink ) {
                return home_url()."/".$custom_permalink;
            }
        }
        if($post->post_type=='ui_subscription'){
            $custom_permalink = get_post_meta( $post->ID, 'ui_subscription_url', true ); 
            if ( $custom_permalink ) {
                return $custom_permalink;
            }
        }
        if($post->post_type=='ui_publication'){
            $custom_permalink = get_post_meta( $post->ID, 'ui_publication_url', true ); 
            if ( $custom_permalink ) {
                return $custom_permalink;
            }
        }
        return $permalink;
    }
    
    public function original_post_link($post_id) {
        remove_filter( 'post_link', array($this, 'post_link'), 10, 2 );
        remove_filter( 'post_type_link', array($this, 'post_link'), 10, 2 );
        $originalPermalink = ltrim(str_replace(home_url(), '', get_permalink( $post_id )), '/');
        add_filter( 'post_link', array($this, 'post_link'), 10, 2 );
        add_filter( 'post_type_link', array($this, 'post_link'), 10, 2 );
        return $originalPermalink;
    }
    
    /**
     * Handle the request for the UI Article custom permalink and the update callback
     */
    public function request($query) {
        global $wpdb;
        
        $originalUrl = null;
        
        $url = parse_url(get_bloginfo('url'));
        $url = isset($url['path']) ? $url['path'] : '';
        
        $request = ltrim(substr($_SERVER['REQUEST_URI'], strlen($url)), '/');
        $request = (($pos = strpos($request, '?')) ? substr($request, 0, $pos) : $request);
        $request_noslash = preg_replace('@/+@', '/', trim($request, '/'));
        
        if($request_noslash == 'ui-newsroom-update'){
            if(isset($_GET['key'])){
                $key = $_GET['key'];
                if($key == get_option('ui_newsroom_api_key')){
                    $result = array();
                    //
                    require_once UINEWSROOM__PLUGIN_DIR . 'includes/class.ui-newsroom-api.php';
                    $api = new UiNewsroomApi();
                    // TODO only update what is necessary
                    $result['success'] = $api->update();
                    //
                    wp_send_json($result);
                }
            }
        }
        
        if ( !$request){
            return $query;
        }
        
        $sql = $wpdb->prepare(
            "SELECT $wpdb->posts.ID, $wpdb->postmeta.meta_value, $wpdb->posts.post_type FROM $wpdb->posts  " . 
            "LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) WHERE " . 
            "  (meta_key = 'ui_article_permalink' OR meta_key = 'ui_article_url') AND " . 
            "  meta_value != '' AND " . 
            "  ( LOWER(meta_value) = LEFT(LOWER('%s'), LENGTH(meta_value)) OR " . 
            "    LOWER(meta_value) = LEFT(LOWER('%s'), LENGTH(meta_value)) ) " . 
            "  AND post_status != 'trash' AND post_type = 'ui_article'" . 
            " ORDER BY LENGTH(meta_value) DESC, " . 
            " FIELD(post_status,'publish','private','draft','auto-draft','inherit')," . 
            "$wpdb->posts.ID ASC  LIMIT 1", 
            $request_noslash,
            $request_noslash . "/"
        );
        
        $posts = $wpdb->get_results($sql);
        
        if ( $posts) {
            $originalUrl = $this->original_post_link($posts[0]->ID);
            $originalUrl = preg_replace('@/+@', '/', str_replace(trim(strtolower($posts[0]->meta_value), '/'), $originalUrl, strtolower($request_noslash)));
            $originalUrl = str_replace('//', '/', $originalUrl);
            $originalUrl = rtrim($originalUrl, '/');
            
            if ( ($pos = strpos($_SERVER['REQUEST_URI'], '?')) !== false) {
                $queryVars = substr($_SERVER['REQUEST_URI'], $pos + 1);
                $originalUrl .= (strpos($originalUrl, '?') === false ? '?' : '&') . $queryVars;
            }
            
            $oldRequestUri = $_SERVER['REQUEST_URI'];
            $oldQueryString = $_SERVER['QUERY_STRING'];
            
            $_SERVER['REQUEST_URI'] = '/' . ltrim($originalUrl, '/');
            $_SERVER['QUERY_STRING'] = (($pos = strpos($originalUrl, '?')) !== false ? substr($originalUrl, $pos + 1) : '');
            
            parse_str($_SERVER['QUERY_STRING'], $queryArray);
            $oldValues = array();
            if ( is_array($queryArray)){
                foreach ($queryArray as $key => $value) {
                    $oldValues[$key] = $_REQUEST[$key];
                    $_REQUEST[$key] = $_GET[$key] = $value;
                }
            }
            
            // re-parse query without the callback
            remove_filter('request', array($this,'request'), 10, 1);
            global $wp;
            $wp->parse_request();
            $query = $wp->query_vars;
            add_filter('request', array($this,'request'), 10, 1);
            
            $_SERVER['REQUEST_URI'] = $oldRequestUri;
            $_SERVER['QUERY_STRING'] = $oldQueryString;
            foreach ($oldValues as $key => $value) {
                $_REQUEST[$key] = $value;
            }
            
            // add custom scripts for the page
            add_action( 'wp_footer', array($this, 'footer_scripts') );
        }
        
        return $query;
    }

    public function footer_scripts(){
        echo '<script type="text/javascript">
        var $ = jQuery;
        (function() {
          var _uib = window._uib || (window._uib = []);
          if (!_uib.loaded) {
            var uibs = document.createElement(\'script\');
            uibs.async = true;
            uibs.src = \'//static.urbanimmersive.com/uib.js\';
            var s = document.getElementsByTagName(\'script\')[0];
            s.parentNode.insertBefore(uibs, s);
            _uib.loaded = true;
          }
        })();
        </script>';
    }

    public function plugin_activation() {
        add_option('ui_newsroom_api_key', rtrim(base64_encode(md5(microtime())),"="));
        //
        flush_rewrite_rules();
    }

    public function plugin_deactivation() {
        flush_rewrite_rules();
    }

}
