<?php
/*
Plugin Name: Cleveritics for WordPress
Plugin URI: http://www.cleveritics.com
Description: WordPress plugin for real-time web analytics from <a href="http://cleveritics.com">Cleveritics.com</a>
Version: 1.3
Author: Vladimir Prelovac
Author URI: http://www.prelovac.com/vladimir
*/

global $wpdb, $clvr_plugin_slug, $clvr_plugin_name, $clvr_plugin_dir, $clvr_default_options, 
    $clvr_text_domain;

$clvr_plugin_slug = 'cleveritics-for-wordpress';
$clvr_plugin_name = 'Cleveritics';
$clvr_plugin_dir = WP_PLUGIN_URL . "/$clvr_plugin_slug/";
$clvr_text_domain = "$clvr_plugin_slug-domain";
$clvr_default_options = array(   
        'body_position' =>  0,
	'no_admin' => 1,
	'use_test' => 0
);

load_plugin_textdomain($clvr_text_domain);

add_action('init', 'clvr_init', 1);

/**
* @desc     Hooks this function into init() for the reminder and other stuffs
*/
function clvr_init()
{
    global $clvr_plugin_slug, $clvr_default_options;
    
    $options = $clvr_default_options;
    $options = get_option($clvr_plugin_slug);
    
    if (is_admin() // if we're in admin panel 
        && (!$options || !isset($options['id']) || !$options['id']) // and something's wrong with the options
        && $_GET['page'] != basename(__FILE__)) // and the current page is not the plugin setting page itself
    {
        // then show a warning
        add_action('admin_notices', 'clvr_warning', 1);
        
        // and do nothing else
        return false;
    }
    
    // do nothing for logged in admin if option is enabled
    if ($options['no_admin'] && current_user_can('level_10'))
      return false;

    // if we get this far, the settings are OK
    // set up necessary hooks to do our tracking stuffs now
    /*if ($options['body_position']==0)
    {
        // hook into these 2 functions to get the whole content buffer
        //add_action('get_header', 'clvr_pre_content', PHP_INT_MAX);
        //add_action('wp_footer', 'clvr_post_content', PHP_INT_MAX);
        add_action('wp_footer', 'clvr_wp_head_hook', 5);
    }
    else*/
    {
        // use normal wp_head hook
        add_action('wp_head', 'clvr_wp_head_hook', 5);
    }
}

/**
* @desc     Shows a warning to remind user to input their Cleveritics ID
*/
function clvr_warning()
{
    global $clvr_text_domain;
    echo '<div id="clvr-warning" class="updated fade"><p>';
    printf(__('<strong>Cleveritics needs your attention:</strong> Please <a href="%s">input your Cleveritics ID</a>.'), admin_url('options-general.php?page=cleveritics.php'));
    echo '</p></div>';
}

/**
* @desc     Hooks into wp_head to print the tracker script
*/
function clvr_wp_head_hook()
{
    echo clvr_get_tracker_script();
}

/**
* @desc     Generates the tracker script with the configured ID
* @return   The script string
*/
function clvr_get_tracker_script()
{
    global $clvr_plugin_slug;
    $options = get_option($clvr_plugin_slug);

    $id=$options[id];
    $code=$options[use_test] ? "cst.js" : "cs.js";
    
    return <<<EOL
<script language="javascript" type="text/javascript">
var vtrack="$id";
var cl = document.createElement('script');
cl.type = 'text/javascript'; 
cl.async = true;
cl.src = 'http://tracker.cleveritics.com/$code';
var cls = document.getElementsByTagName('script')[0]; 
cls.parentNode.insertBefore(cl, cls);
</script>
   
EOL;
}

/**
* @desc     Hooks into init to handle the options saving
* @return   void
*/
function clvr_request_handler()
{
    global $clvr_plugin_slug, $clvr_text_domain;
    
    // if $_POST['clvr_action'] is not set, this request doesn't belong to us!
    if (!isset($_POST['clvr_action'])) return false;
    
    // remember to verify the nonce for security purpose.
    if (!wp_verify_nonce($_POST['_nonce'], $clvr_plugin_slug))
    {
        die(__('Security check failed. Please try refreshing.', $clvr_text_domain));
    }
    
    switch($_POST['clvr_action'])
    {
        case 'options':
            clvr_save_options();
            break;
        default:
            return false;
    }
    exit();
}

add_action('init', 'clvr_request_handler', 5);

/**
* @desc     Saves the plugin options
*/
function clvr_save_options()
{
    global $clvr_text_domain, $clvr_plugin_slug, $clvr_default_options;
    
    $errors = array();
    
    $id = isset($_POST['id']) ? trim($_POST['id']) : false;
    if (!$id)
    {
        $errors[] = __('Please input your Cleveritics ID', $clvr_text_domain);
    }
    
    if (count($errors))
    {
        die ('<ul><li>' . implode('</li><li>', $errors) . '</li></ul>');
    }
    
    $options = array(
        'id'            => $id,
        'body_position' => $_POST['body_position'] == 'on' ? 1 : 0,
	'no_admin' => $_POST['no_admin'] == 'on' ? 1 : 0,
	'use_test' => $_POST['use_test'] == 'on' ? 1 : 0

    );
    
    get_option($clvr_plugin_slug) ? 
      update_option($clvr_plugin_slug, $options) : 
      add_option($clvr_plugin_slug, $options);
    
    die('<p>' . __('Settings saved.', $clvr_text_domain) . '</p>');
}

/**
* @desc     Renders the option form
*/
function clvr_options_form()
{
    global $wpdb, $clvr_plugin_slug, $clvr_plugin_dir, $clvr_plugin_name, $clvr_text_domain;
    if (!$options = get_option($clvr_plugin_slug))
    {
        $options = array(
            'id'            => false,
            'body_position' => 0,
            'no_admin' => 1
        );
    }
?>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo "$clvr_plugin_dir/css/admin.css"?>" />
<script type="text/javascript" src="<?php echo $clvr_plugin_dir?>js/admin-onload.js"></script>
<div class="wrap clvr-options">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2><?php _e("$clvr_plugin_name Options", $clvr_text_domain)?></h2>
    <form action="index.php" method="post" class="ajax" autocomplete="off">
        <div class="updated fade" id="result" style="display:none"></div>
        <label>
            <?php _e('Cleveritics ID', $clvr_text_domain)?> 
            <input type="text" name="id" value="<?php echo $options['id']?>" class="required" /> <em>From your tracking code eg.  var vtrack="89-41"; you would type in 89-41 in the box</em>
        </label>
      
        <label>
            <input type="checkbox" name="no_admin" <?php echo $options['no_admin'] ? 'checked="checked"' : ''?> />
            <?php _e('Do not track admin users. <em>Admin visits to the site will not be tracked</em>', $clvr_text_domain)?>
        </label>
<h4>Advanced</h4>
      
       <label>
            <input type="checkbox" name="use_test" <?php echo $options['use_test'] ? 'checked="checked"' : ''?> />
            <?php _e('Use experimental tracking code. <em>This allows access to some of the new features.</em>', $clvr_text_domain)?>
        </label>
        <p class="submit">
        <input type="hidden" value="<?php echo wp_create_nonce($clvr_plugin_slug)?>" name="_nonce" />
        <input type="hidden" name="clvr_action" value="options" />
        <input class="button-primary" name="submit" type="submit" value="<?php _e('Save Options', $clvr_text_domain)?>" />
        </p>
        <div id="loading" style="display:none"><img src="<?php echo $clvr_plugin_dir?>images/loading.gif" alt="<?php _e('Loading...', $clvr_text_domain)?>" /></div>
    </form>
</div>
<?php
}

/**
* @desc     Starts the output buffering
*/
function clvr_pre_content()
{
    ob_start();
}

/**
* @desc     Ends the output buffering to capture the whole HTML content
*           and find the <body> tag
* @todo     <body> tag in HTML comment?? (current limitation/bug)
*/
function clvr_post_content()
{
    $content = ob_get_contents();
    ob_end_clean();
    
    // a quick preg match to find the body tag (with onload, style and such noticed)
    preg_match_all("/.*(<body[^>]*>)/is", $content, $matches);
    
    if (isset($matches[1][0]))
    {
        $content = str_replace($matches[1][0], $matches[1][0] . clvr_get_tracker_script(), $content);
    }
    
    echo $content;
}

/**
 * @desc    Adds the Options menu item
 * @return  void
 */
function clvr_menu_items()
{
    global $clvr_plugin_name;
    add_options_page($clvr_plugin_name, $clvr_plugin_name, 8, basename(__FILE__), 'clvr_options_form');
}

add_action('admin_menu', 'clvr_menu_items');

/**
* @desc     A small helper to log important data
* The log file can be found in the plugin directory
* @param    mixed   The data to log
* @return   void
*/
function clvr_write_log($val)
{
    if (is_array($val))
    {
        $val = print_r($val, 1);
    }
    
    if (is_object($val))
    {
        ob_start();
        var_dump($val);
        $val = ob_get_clean();
    }
    
    $handle = fopen(dirname(__FILE__) . '/log', 'a');
    fwrite($handle, $val . PHP_EOL);
    fclose($handle);
}

/*

<label>
            <input type="checkbox" name="body_position" <?php echo $options['body_position'] ? 'checked="checked"' : ''?> />
            <?php _e('Insert tracking code in the header. <em>This is recommended for more accurate tracking but problems may arise for some Internet Explorer users if you have unclosed tags in your HTML. Check your site in IE7 after enabling this option. </em>', $clvr_text_domain)?>
        </label>
        
 */