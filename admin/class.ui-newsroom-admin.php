<?php

require_once UINEWSROOM__PLUGIN_DIR . 'includes/class.ui-newsroom-api.php';
class UiNewsroomAdmin {
    
    public function init(){
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_head', array($this, 'admin_head'));
        add_filter('post_row_actions', array($this, 'post_row_actions'), 10,2);
        add_action('add_option_ui_newsroom_options', array($this, 'updated_credentials'), 10, 2);
        add_action('update_option_ui_newsroom_options', array($this, 'updated_credentials'), 10, 2);
        
        //
        $this->_loadConfigurations();
        //
        add_action('admin_notices', array($this, 'admin_notices'));
        if(empty($this->options)){
            $this->_addNotice('warning',sprintf(__('Your Urbanimmersive Newsroom is not yet configured. <a href="%s">Please configure it here.</a>', 'ui-newsroom'), 'options-general.php?page=ui_newsroom_options&tab=configurations'));
        }
        if(!empty($this->options)&&!$this->api_test_results['result']){
            $this->_addNotice('warning',sprintf(__('It seems your Urbanimmersive Newsroom is not syncing. <a href="%s">Please visit the settings to find a solution.</a>', 'ui-newsroom'), 'options-general.php?page=ui_newsroom_options&tab=configurations'));
        }
    }
    
    private function _loadConfigurations(){
        $this->options = get_option( 'ui_newsroom_options' );
        $this->api_test_results = get_option( 'ui_newsroom_test' );
        $this->update_key = get_option( 'ui_newsroom_api_key' );
        $this->working_newsroom = get_option( 'ui_newsroom_working_newsroom' );
        $this->last_update = get_option( 'ui_newsroom_last_update' );
    }
    
    public function post_row_actions( $actions ) {
        global $post;
        if( $post->post_type == 'ui_article' || (isset($post->real_post_type) && $post->real_post_type == 'ui_article') ) {
            unset($actions['inline hide-if-no-js']);
            unset($actions['edit']);
            unset($actions['trash']);
        }
        return $actions;
    }
    
    private function _addNotice($type, $message){
        $this->notices[] = array('type'=>$type, 'message'=>$message);
    }
    
    public function admin_notices(){
        $notices = array();
        if(isset($this->notices)){
            $notices = $this->notices;
        }
        if(!empty($notices)){
            foreach($notices as $key => $notice){
                $notices[$key]['shown'] = true;
                $class = 'notice-success';
                if($notice['type']=='warning'){
                    $class = 'notice-warning';
                } else if($notice['type']=='error'){
                    $class = 'notice-error';
                }
                echo '<div class="notice ' . $class . '"><p>'.$notice['message'].'</p></div>';
            }
            $this->notices = $notices;
        }
    }
    
    public function add_menu(){
        add_options_page(__("UI Newsroom", 'ui-newsroom'),__("UI Newsroom", 'ui-newsroom'),"manage_options",'ui_newsroom_options', array($this, 'options'));
        remove_submenu_page('edit.php?post_type=ui_article','post-new.php?post_type=ui_article');
        remove_submenu_page('edit.php?post_type=ui_article','edit-tags.php?taxonomy=category&amp;post_type=ui_article');
    }
    
    public function add_my_media_button(){
        global $post;
        $meta_data = get_post_meta( $post->ID, 'ui_article_id' );
        $article_id = $meta_data[0];
        echo '<a href="https://cms.urbanimmersive.com/articles/edit/' . $article_id . '?blog=' . $this->working_newsroom['id'] . '" target="_blank" class="button">'.__('Edit this article in Urbanimmersive CMS', 'ui-newsroom').'</a>';
    }
    
    public function admin_head() {
      global $_REQUEST,$pagenow;
      if (!empty($_REQUEST['post_type']) && 'ui_article' == $_REQUEST['post_type'] && !empty($pagenow) && 'post-new.php' == $pagenow){
        wp_safe_redirect(admin_url('edit.php?post_type=ui_article'));
      }
      
      global $post_new_file,$post_type_object;
      if (!isset($post_type_object) || 'ui_article' != $post_type_object->name){
          return false;
      }
      $post_type_object->labels->add_new = __('Return to Index', 'ui-newsroom');
      $post_new_file = 'edit.php?post_type=ui_article';
      
      global $post;
      if( $post && ($post->post_type == 'ui_article' || (isset($post->real_post_type) && $post->real_post_type == 'ui_article')) ) {
          remove_action( 'media_buttons', 'media_buttons' );
          add_action('media_buttons', array($this, 'add_my_media_button'));
          $this->_addNotice('warning', sprintf(__('Please note that your Urbanimmersive Newsrooms is <strong>read-only</strong> in WordPress. Modifications need to be made in the <a href="%s" target="_blank">Urbanimmersive CMS</a>.', 'ui-newsroom'), 'https://cms.urbanimmersive.com/blogs'.(isset($this->working_newsroom['id'])?'?blog='.$this->working_newsroom['id']:'')));
          echo '<style>.postbox-container { display: none; } #post-body{ margin-right:0px !important; }</style>';
      }
    }
    
    public function testAPI() {
        $api = new UiNewsroomApi();
        $result = $api->testCredentials();
        update_option( 'ui_newsroom_test', array('time'=>time(),'result'=>$result) );
        return $result;
    }
    
    public function update(){
        $api = new UiNewsroomApi();
        return $api->update();
    }
    
    public function options(){
        
        if(!current_user_can('manage_options')){
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if(!empty($_POST)&&isset($_POST['action'])){
            switch($_POST['action']){
                case 'test':
                    if(!$this->testAPI()){
                        $this->_addNotice('warning', __('Your credentials are invalid.', 'ui-newsroom'));
                    } else {
                        $this->_addNotice('valid', __('Your credentials are valid.', 'ui-newsroom'));
                    }
                    $this->_loadConfigurations();
                    break;
                case 'fetch':
                    if($this->update()){
                        $this->_addNotice('valid', __('Your newsroom was successfully updated.', 'ui-newsroom'));
                    } else {
                        $this->_addNotice('warning', __('Error during download.', 'ui-newsroom'));
                    }
                    $this->_loadConfigurations();
                    break;
            }
        }
        
        require_once UINEWSROOM__PLUGIN_DIR . '/admin/views/settings.php';
    }
    
    public function updated_credentials($new, $old){
        //
        if(isset($new['ui_newsroom_client_id'])&&isset($old['ui_newsroom_client_id'])&&isset($new['ui_newsroom_client_secret'])&&isset($old['ui_newsroom_client_secret'])){
            if($new['ui_newsroom_client_id']!=$old['ui_newsroom_client_id']||$new['ui_newsroom_client_secret']!=$old['ui_newsroom_client_secret']){
                delete_option('ui_newsroom_last_update');
            }
        }
        //
        $this->_loadConfigurations();
        if($this->testAPI()){
            $this->_addNotice('valid', __('Your credentials are valid.', 'ui-newsroom'));
        } else {
            $this->_addNotice('warning', __('Your credentials are invalid.', 'ui-newsroom'));
        }
    }
    
    public function page_init(){
        if (!empty($_POST) && isset($_POST['post_type']) && $_POST['post_type'] == 'ui_article') {
            if (true === DOING_AJAX) {
              exit;
            }
            if (!empty($_POST['post_ID'])) {
              wp_safe_redirect(admin_url('post.php?post='.$_POST['post_ID'].'&action=edit'));
              exit;
            } else {
              wp_safe_redirect(admin_url('edit.php?post_type=ui_article'));
              exit;
            }
        }
        register_setting(
            'ui_newsroom_options_group',
            'ui_newsroom_options',
            array( $this, 'sanitize' )
        );
        add_settings_section(
            'ui_newsroom_options_section_credentials',
            __('Urbanimmersive API credentials', 'ui-newsroom'),
            array( $this, 'print_section_credentials_info' ),
            'ui_newsroom_options_section'
        );
        add_settings_field(
            'ui_newsroom_client_id',
            __('Client ID', 'ui-newsroom'),
            array( $this, 'client_id_callback' ),
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_credentials'
        );
        add_settings_field(
            'ui_newsroom_client_secret', 
            __('Client Secret', 'ui-newsroom'), 
            array( $this, 'client_secret_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_credentials'
        );
        //
        add_settings_section(
            'ui_newsroom_options_section_fetch',
            __('What should be fetched?', 'ui-newsroom'),
            null,
            'ui_newsroom_options_section'
        );
        add_settings_field(
            'ui_newsroom_fecth_articles', 
            __('Fetch articles', 'ui-newsroom'), 
            array( $this, 'fecth_articles_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_fetch'
        );
        add_settings_field(
            'ui_newsroom_fecth_categories', 
            __('Fetch categories', 'ui-newsroom'), 
            array( $this, 'fecth_categories_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_fetch'
        );
        add_settings_field(
            'ui_newsroom_fecth_subscriptions', 
            __('Fetch subscriptions', 'ui-newsroom'), 
            array( $this, 'fecth_subscriptions_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_fetch'
        );
        add_settings_field(
            'ui_newsroom_fecth_publications', 
            __('Fetch publications', 'ui-newsroom'), 
            array( $this, 'fecth_publications_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_fetch'
        );
        //
        add_settings_section(
            'ui_newsroom_options_section_query',
            __('What should be shown by default?', 'ui-newsroom'),
            array( $this, 'print_section_query_info' ),
            'ui_newsroom_options_section'
        );
        add_settings_field(
            'ui_newsroom_query_articles', 
            __('Show articles', 'ui-newsroom'), 
            array( $this, 'query_articles_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_query'
        );
        add_settings_field(
            'ui_newsroom_query_subscriptions', 
            __('Show subscriptions', 'ui-newsroom'), 
            array( $this, 'query_subscriptions_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_query'
        );
        add_settings_field(
            'ui_newsroom_query_publications', 
            __('Show publications', 'ui-newsroom'), 
            array( $this, 'query_publications_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_query'
        );
        //
        add_settings_section(
            'ui_newsroom_options_section_options',
            __('Configurations', 'ui-newsroom'),
            null,
            'ui_newsroom_options_section'
        );
        add_settings_field(
            'ui_newsroom_default_author', 
            __('Default author', 'ui-newsroom'), 
            array( $this, 'default_author_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_options'
        );
        add_settings_field(
            'ui_newsroom_download_images', 
            __('Download and host article images', 'ui-newsroom'), 
            array( $this, 'download_images_callback' ), 
            'ui_newsroom_options_section', 
            'ui_newsroom_options_section_options'
        );
    }
    
    public function sanitize( $input ){
        $new_input = array();
        if( isset( $input['ui_newsroom_client_id'] ) ){
            $new_input['ui_newsroom_client_id'] = sanitize_text_field( $input['ui_newsroom_client_id'] );
        }
        if( isset( $input['ui_newsroom_client_secret'] ) ){
            $new_input['ui_newsroom_client_secret'] = sanitize_text_field( $input['ui_newsroom_client_secret'] );
        }
        if( isset( $input['ui_newsroom_default_author'] ) ){
            $new_input['ui_newsroom_default_author'] = sanitize_text_field( $input['ui_newsroom_default_author'] );
        }
        if( isset( $input['ui_newsroom_download_images'] ) ){
            $new_input['ui_newsroom_download_images'] = true;
        } else {
            $new_input['ui_newsroom_download_images'] = false;
        }
        if( isset( $input['ui_newsroom_fecth_articles'] ) ){
            $new_input['ui_newsroom_fecth_articles'] = true;
        } else {
            $new_input['ui_newsroom_fecth_articles'] = false;
        }
        if( isset( $input['ui_newsroom_fecth_categories'] ) ){
            $new_input['ui_newsroom_fecth_categories'] = true;
        } else {
            $new_input['ui_newsroom_fecth_categories'] = false;
        }
        if( isset( $input['ui_newsroom_fecth_subscriptions'] ) ){
            $new_input['ui_newsroom_fecth_subscriptions'] = true;
        } else {
            $new_input['ui_newsroom_fecth_subscriptions'] = false;
        }
        if( isset( $input['ui_newsroom_fecth_publications'] ) ){
            $new_input['ui_newsroom_fecth_publications'] = true;
        } else {
            $new_input['ui_newsroom_fecth_publications'] = false;
        }
        if( isset( $input['ui_newsroom_query_publications'] ) ){
            $new_input['ui_newsroom_query_publications'] = true;
        } else {
            $new_input['ui_newsroom_query_publications'] = false;
        }
        if( isset( $input['ui_newsroom_query_articles'] ) ){
            $new_input['ui_newsroom_query_articles'] = true;
        } else {
            $new_input['ui_newsroom_query_articles'] = false;
        }
        if( isset( $input['ui_newsroom_query_subscriptions'] ) ){
            $new_input['ui_newsroom_query_subscriptions'] = true;
        } else {
            $new_input['ui_newsroom_query_subscriptions'] = false;
        }
        return $new_input;
    }

    public function print_section_credentials_info(){
        echo '<p>' . sprintf(__('Enter your credentials below. If you do not have your credentials, you can get them free at : <a href="%s" target="_blank">%s</a>', 'ui-newsroom'), 'https://cms.urbanimmersive.com/wordpress', 'https://cms.urbanimmersive.com/wordpress').'</p>';
    }

    public function print_section_query_info(){
        echo '<p>' . sprintf(__('Select the custom posts types that will be added to the WordPress Query. Otherwise, your theme has to override the WordPress Query for each custom posts types for them to be shown on your pages.', 'ui-newsroom')).'</p>';
    }

    public function query_articles_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_query_articles]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_query_articles'] ) || $this->options['ui_newsroom_query_articles']) ? 'checked="checked"' : ''
        );
    }
    
    public function query_subscriptions_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_query_subscriptions]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_query_subscriptions'] ) || $this->options['ui_newsroom_query_subscriptions']) ? 'checked="checked"' : ''
        );
    }
    
    public function query_publications_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_query_publications]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_query_publications'] ) || $this->options['ui_newsroom_query_publications']) ? 'checked="checked"' : ''
        );
    }
    
    public function fecth_articles_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_fecth_articles]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_fecth_articles'] ) || $this->options['ui_newsroom_fecth_articles']) ? 'checked="checked"' : ''
        );
    }
    
    public function fecth_categories_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_fecth_categories]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_fecth_categories'] ) || $this->options['ui_newsroom_fecth_categories']) ? 'checked="checked"' : ''
        );
    }
    
    public function fecth_subscriptions_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_fecth_subscriptions]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_fecth_subscriptions'] ) || $this->options['ui_newsroom_fecth_subscriptions']) ? 'checked="checked"' : ''
        );
    }
    
    public function fecth_publications_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_fecth_publications]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_fecth_publications'] ) || $this->options['ui_newsroom_fecth_publications']) ? 'checked="checked"' : ''
        );
    }

    public function download_images_callback(){
        printf(
            '<label><input type="checkbox" name="ui_newsroom_options[ui_newsroom_download_images]" %s class="" /> '.__('Check to enable', 'ui-newsroom').'</label>',
            (!isset( $this->options['ui_newsroom_download_images'] ) || $this->options['ui_newsroom_download_images']) ? 'checked="checked"' : ''
        );
    }
    
    public function default_author_callback(){
        wp_dropdown_users(array('name' => 'ui_newsroom_options[ui_newsroom_default_author]', 'selected'=>isset( $this->options['ui_newsroom_default_author'] ) ? esc_attr( $this->options['ui_newsroom_default_author']) : ''));
        echo '<p class="description">'.__('Your data will be assigned to this author', 'ui-newsroom').'</p>';
    }

    public function client_id_callback(){
        printf(
            '<input type="text" name="ui_newsroom_options[ui_newsroom_client_id]" value="%s" class="regular-text code" />',
            isset( $this->options['ui_newsroom_client_id'] ) ? esc_attr( $this->options['ui_newsroom_client_id']) : ''
        );
    }
    
    public function client_secret_callback(){
        printf(
            '<input type="text" name="ui_newsroom_options[ui_newsroom_client_secret]" value="%s" class="regular-text code" /><p class="description">'.__('These settings should be kept secret', 'ui-newsroom').'</p>',
            isset( $this->options['ui_newsroom_client_secret'] ) ? esc_attr( $this->options['ui_newsroom_client_secret']) : ''
        );
    }
    
}