<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

$tab = 'newsroom';
if(isset($_GET['tab'])){
    switch($_GET['tab']){
        case 'newsroom':
            $tab = 'newsroom';
            break;
        case 'configurations':
            $tab = 'configurations';
            break;
        case 'tools':
            $tab = 'tools';
            break;
    }
}

$current_uri = home_url( add_query_arg( NULL, NULL ) );

?>

<?php if(isset($this->notices)): ?>
    <?php foreach($this->notices as $notice): if(isset($notice['shown'])&&$notice['shown']){ continue; } ?>
        <?php
            $class = 'notice-success';
            if($notice['type']=='warning'){
                $class = 'notice-warning';
            } else if($notice['type']=='error'){
                $class = 'notice-error';
            }
        ?>
        <div class="notice <?php echo $class; ?>"><p><?php echo $notice['message']; ?></p></div>
    <?php endforeach; ?> 
<?php endif; ?>

<style>
    .ui-newsroom h1{
        margin:0px 0px 10px 0px;
    }
    .ui-newsroom .ui-newsroom-content{
        padding:20px; background-color:#fff;
    }
</style>

<div class="wrap ui-newsroom">
    
    <h1><?php _e('Urbanimmersive Newsroom Settings', 'ui-newsroom'); ?></h1>
    <p><?php printf(__('Need help? Visit %s for more details.', 'ui-newsroom'), '<a href="https://cms.urbanimmersive.com/wordpress'.(isset($this->working_newsroom['id'])?'?blog='.$this->working_newsroom['id']:'').'" target="_blank">https://cms.urbanimmersive.com/wordpress</a>'); ?></p>
    
    <h2 class="nav-tab-wrapper">
        <a href="options-general.php?page=ui_newsroom_options&tab=newsroom" class="nav-tab<?php echo $tab == 'newsroom' ? ' nav-tab-active' : ''; ?>"><?php _e('Your newsroom', 'ui-newsroom'); ?></a>
        <a href="options-general.php?page=ui_newsroom_options&tab=configurations" class="nav-tab<?php echo $tab == 'configurations' ? ' nav-tab-active' : ''; ?>"><?php _e('Configurations', 'ui-newsroom'); ?></a>
        <a href="options-general.php?page=ui_newsroom_options&tab=tools" class="nav-tab<?php echo $tab == 'tools' ? ' nav-tab-active' : ''; ?>"><?php _e('Debug tools', 'ui-newsroom'); ?></a>
    </h2>
    
    <div class="ui-newsroom-content">
        
        <?php if($tab == 'newsroom'): ?>
            <h2><?php _e( 'Your newsroom' , 'ui-newsroom');?></h2>
            <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e( 'Newsroom' , 'ui-newsroom');?></th>
                            <td>
                                <?php if(isset($this->working_newsroom['id'])): ?>
                                    <?php echo $this->working_newsroom['title']; ?>
                                <?php else: ?>
                                    <?php _e( 'N/A' , 'ui-newsroom');?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Your real-time update URL' , 'ui-newsroom');?></th>
                            <td>
                                <input id="ui-newsroom-real-time-url" type="text" value="<?php echo home_url(); ?>/ui-newsroom-update?key=<?php echo $this->update_key; ?>" class="regular-text code" style="min-width:50%;">
                                <p class="description">
                                    <?php
                                    $url = 'https://cms.urbanimmersive.com/wordpress?'.(isset($this->working_newsroom['id'])?'blog='.$this->working_newsroom['id'].'&':'').'update_url='.home_url().'/ui-newsroom-update?key='.$this->update_key;
                                    printf(__('<a href="%s" target="_blank">Click here</a> to update your Urbanimmersive CMS Newsroom configurations', 'ui-newsroom'), $url);
                                    ?>
                                </p>
                            </td>
                        </tr>
                   </tbody>
            </table>
            
            <script>
                (function($){
                    $('#ui-newsroom-real-time-url').focus(function(){
                        $(this).select();
                    });
                })(jQuery);
            </script>
        <?php endif; ?>
        
        <?php if($tab == 'configurations'): ?>
            <?php if(!empty($this->options)&&!$this->api_test_results['result']): ?>
                <div class="notice notice-error inline"><p><span class="dashicons dashicons-no"></span> <?php _e('Your Urbanimmersive Newsroom is not syncing. Please make sure your credentials are valid.'); ?></p></div>
            <?php elseif(isset($this->working_newsroom['id'])&&(!isset($this->last_update)||!is_numeric($this->last_update))): ?>
                <div class="notice notice-warning inline"><p><span class="dashicons dashicons-warning"></span> <?php printf(__('Your <strong>%s</strong> newsroom is not synced. Click the button below to sync now.', 'ui-newsroom'), $this->working_newsroom['title']); ?></p></div>
                <form method="post" action="<?php echo esc_url($current_uri); ?>">
                    <input type="hidden" name="action" value="fetch" />
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Sync newsroom' , 'ui-newsroom');?>">
                    <p class="description"><?php _e( 'Note: this can take a while' , 'ui-newsroom');?></p>
                </form>
            <?php endif; ?>
            <form method="post" action="options.php">
            <?php
                settings_fields( 'ui_newsroom_options_group' );
                do_settings_sections( 'ui_newsroom_options_section' );
                submit_button();
            ?>
            </form>
        <?php endif; ?>
        
        <?php if($tab == 'tools'): ?>
            <h2><?php _e( 'Test your credentials' , 'ui-newsroom');?></h2>
            <p><?php _e( 'To make sure your integration with the Urbanimmersive API is working correctly, you can test the API credentials' , 'ui-newsroom');?></p>
            <form method="post" action="<?php echo esc_url($current_uri); ?>">
                <input type="hidden" name="action" value="test" />
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e( 'Test now' , 'ui-newsroom');?></th>
                            <td>
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Test API credentials' , 'ui-newsroom');?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Result' , 'ui-newsroom');?></th>
                            <td>
                                <?php if(isset($this->api_test_results['result'])): ?>
                                    <?php echo $this->api_test_results['result']?'<span class="dashicons dashicons-yes"></span> '.__('Success', 'ui-newsroom'):'<span class="dashicons dashicons-no-alt"></span> '.__('Failed', 'ui-newsroom'); ?>
                                <?php else: ?>
                                    <?php _e( 'N/A' , 'ui-newsroom');?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Last test date' , 'ui-newsroom');?></th>
                            <td>
                                <?php if(isset($this->api_test_results['time'])): ?>
                                    <?php echo get_date_from_gmt( date( 'Y-m-d H:i:s', $this->api_test_results['time'] ), 'F j, Y H:i:s' );?>
                                <?php else: ?>
                                    <?php _e( 'Never' , 'ui-newsroom');?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
            <h2><?php _e( 'Manual update' , 'ui-newsroom');?></h2>
            <p><?php _e( 'Sometimes, WordPress can be out of sync with your Newsroom. You can manually update all your articles here.' , 'ui-newsroom');?></p>
            <form method="post" action="<?php echo esc_url($current_uri); ?>">
                <input type="hidden" name="action" value="fetch" />
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e( 'Update now' , 'ui-newsroom');?></th>
                            <td>
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Update now' , 'ui-newsroom');?>">
                                <p class="description"><?php _e( 'Note: this can take a while' , 'ui-newsroom');?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Last update date' , 'ui-newsroom');?></th>
                            <td>
                                <?php if(isset($this->last_update)&&is_numeric($this->last_update)): ?>
                                    <?php echo get_date_from_gmt( date( 'Y-m-d H:i:s', $this->last_update ), 'F j, Y H:i:s' );?>
                                <?php else: ?>
                                    <?php _e( 'Never' , 'ui-newsroom');?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php endif; ?>
        
    </div>
</div>