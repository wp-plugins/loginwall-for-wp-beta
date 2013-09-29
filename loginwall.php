<?php

/**
 * @package LoginWall
 * @version 0.1.1
 */
/*
Plugin Name: LoginWall
Plugin URI: http://www.loginwall.com/wordpress/
Description: This plugin enables LoginWall Protection for WordPress logins.
Author: LoginWall ltd.
Version: 0.1.1
Author URI: http://www.loginwall.com/
*/

function disable_password_reset() { return false; }
//add_filter ( 'allow_password_reset', 'disable_password_reset' );

function remove_password_reset_text ( $text ) { if ( $text == 'Lost your password?' ) { $text = ''; } return $text;  }

 add_filter( 'show_password_fields', 'disable_password_reset' );
//add_filter( 'allow_password_reset', 'disable_password_reset' );
//add_filter( 'gettext', 'remove_password_reset_text' );

//add_action('login_head','show_loginwall', 0);

add_filter('retrieve_password_message','retrieve_password_message_loginwall');

add_action('plugins_loaded', 'show_loginwall', 0);

register_activation_hook( __FILE__, 'loginwall_activate' );

function loginwall_activate() {

    $app_id = get_option("loginwall_ikey");
    $app_secret = get_option("loginwall_skey");

    if (get_option("loginwall_ikey", "") == "" || get_option("loginwall_skey", "") == "") {
        $admin_email = get_option( 'admin_email' );
        $blog_url = get_bloginfo('siteurl');

        global $current_user;
        get_currentuserinfo();

        $first_name = $current_user->user_firstname;
        $last_name =  $current_user->user_lastname;
        $blog_name = get_bloginfo('name');

        // API call to generate loginwall keys from loginwall server
        $data_url = "http://www.loginwall.com/wordpress/createaccount.php?"
            . "blog_url=" . $blog_url . "&email=" . $admin_email."&first_name=".$first_name."&last_name=".$last_name."&$blog_name=".$blog_name;

        $data = json_decode(file_get_contents($data_url));

        if (isset($data->ikey) && isset($data->skey))
        {
            update_option("loginwall_ikey", $data->ikey);
            update_option("loginwall_skey", $data->skey);            
        }        
    }

}




function retrieve_password_message_loginwall($old_message, $key)
{
    $server = "https://login.loginwall.com/";
    $app_id = get_option("loginwall_ikey");
    $app_secret = get_option("loginwall_skey");

    if (isset($_POST['user_login']) && $_POST['user_login']!=='')
    {
        if ( strpos($_POST['user_login'], '@') ) {
            $useremail = $_POST['user_login'];
            $user = get_user_by( 'email', $useremail );
        }
        else
        {
            $username = $_POST['user_login'];
            $user = get_user_by( 'login', $username );
        }
        //check that user exist
        if ($user!=false)
        {
            $username = $user->user_login;

            $user_login = $user->user_login;
            $user_email = $user->user_email;

            if (is_super_admin($user->ID) )
            {
                // api call for reset password
                $data_url = $server . "/api/password.php?"
                   . "client_id=" . $app_id . "&client_secret=" . $app_secret . "&email=" . $username;

                $data = json_decode(file_get_contents($data_url));

                if ($data->code!='')
                {

                    $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
                    $message .= network_site_url() . "\r\n\r\n";
                    $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
                    $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
                    $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
                    $message .= '<' . network_site_url("wp-login.php?action=lostpassword&code=".$data->code). ">\r\n";
                    return $message;
                }
                else
                {
                    return $old_message;
                }
            }
            else
            {
                return $old_message;
            }
        }
        else
        {
            return $old_message;
        }
    }
    else
    {
        return $old_message;
    }
}

function is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php'));
}

function is_loginwall_active(){
    $current_error_reporting = error_reporting();
    error_reporting(0);

    $server = "https://login.loginwall.com/";
    $app_id = get_option("loginwall_ikey");
    $app_secret = get_option("loginwall_skey");
    if (get_option("loginwall_ikey", "") == "" || get_option("loginwall_skey", "") == "") {
        return false;
    }

    //check that loginwall server is live
    $data_url = $server . "/api/active.php?"
        . "client_id=" . $app_id;
    $opts = array('http' =>
      array(
        'content' => $body,
        'timeout' => 5
      )
    );

    $context  = stream_context_create($opts);
    $data = json_decode(file_get_contents($data_url,false, $context, -1, 40000));

    error_reporting($current_error_reporting);

    if ($data->data!='LoginWall active')
    {
    //    var_dump($data);
        return false;

    }
    return true;
}

function show_loginwall(){
    if (!(is_login_page()))
    {
        return;
    }

    //check that loginwall server is live
    if (!(is_loginwall_active()))
    {
        return;
    }
    
    $server = "https://login.loginwall.com/";
    $app_id = get_option("loginwall_ikey");
    $app_secret = get_option("loginwall_skey");
    if (get_option("loginwall_ikey", "") == "" || get_option("loginwall_skey", "") == "") {
        return;
    }

    $my_url = site_url()."/wp-login.php";

    if (isset($_REQUEST["code"]))
   {
       $code = $_REQUEST["code"];
       //var_dump($code);
       //die;
   }

    if(empty($code)) {
        // check action
        if (isset($_GET['action']))
        {
            //action register
            if ($_GET['action']=='logout')
            {                
                return;
            }
            if ($_GET['action']=='register')
            {                
                return;
            }
            if ($_GET['action']=='rp')
            {
                return;
            }
            if ($_GET['action']=='lostpassword')
            {
                if (isset($_POST['user_login']) && $_POST['user_login']!=='')
                {
                    if ( strpos($_POST['user_login'], '@') ) {
                        $useremail = $_POST['user_login'];
                        $user = get_user_by( 'email', $useremail );
                    }
                    else
                    {
                        $username = $_POST['user_login'];
                        $user = get_user_by( 'login', $username );
                    }
                    
                    //check that user exist
                    if ($user!=false)
                    {
                        $username = $user->user_login;

                        $user_login = $user->user_login;
        	        $user_email = $user->user_email;

                        if (is_super_admin($user->ID) )
                        {
                            // api call for reset password
                            $data_url = $server . "/api/password.php?"
                               . "client_id=" . $app_id . "&client_secret=" . $app_secret . "&email=" . $username;

                            $data = json_decode(file_get_contents($data_url));

                            $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
                            $message .= network_site_url() . "\r\n\r\n";
                            $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
                            $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
                            $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
                            $message .= '<' . network_site_url("wp-login.php?action=lostpassword&code=".$data->code). ">\r\n";

                            if ( is_multisite() )
                                $blogname = $GLOBALS['current_site']->site_name;
                            else
                            // The blogname option is escaped with esc_html on the way into the database in sanitize_option
                            // we want to reverse this for the plain text arena of emails.
                            $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

                            $title = sprintf( __('[%s] Password Reset'), $blogname );

                            $title = apply_filters('retrieve_password_title', $title);
                            $message = apply_filters('retrieve_password_message', $message, $key);

                            if ( $message && !wp_mail($user_email, $title, $message) )
                                wp_die( __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function...') );
	                    
                            // send mail with the url:
                            //echo site_url()."/wp-login.php?action=lostpassword&code=".$data->code;
                             exit;
                        }
                    }

                }
                return;
            }
            // action lostpassword
        }
            // action login
         $_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
         $dialog_url = $server."/wplogin.php?client_id="
           . $app_id . "&redirect_uri=" . urlencode($my_url) . "&state="
           . $_SESSION['state'];

         wp_redirect($dialog_url);
         exit;
    }
    else
    {
        if ($_GET['action']=='lostpassword')
        {
                $my_url = site_url()."/wp-login.php?action=setpassword";
                $_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
             $dialog_url = $server."/wpreset.php?client_id="
               . $app_id . "&reset_uri=" . urlencode($my_url). "&login_uri=" . urlencode($my_url) . "&state="
               . $_SESSION['state']."&usercode=".$code;

             wp_redirect($dialog_url);
             exit;            
        }
        //if($_REQUEST['state'] == $_SESSION['state']) {
            $data_url = $server . "/api/auth.php?"
           . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
           . "&client_secret=" . $app_secret . "&code=" . $code;

             $user = json_decode(file_get_contents($data_url));
             //var_dump($user);
             //die;
             // login on wordpress
             if ($_GET['action']=='register')
             {
              // insert the user to wordpress
                 
             }
             else if ($_GET['action']=='setpassword')
             {
                 // find user id by username
                 $userData = get_user_by( 'login', $user->name );
                 $user_id = $userData->ID;

                 wp_set_password( $user->password, $user_id );
                 update_option("loginwall_setpwd", '1');
                 wp_redirect(site_url()."/wp-admin/");
                 exit;
             }
             else
             {
                 $creds = array();
                $creds['user_login'] = $user->name;
                $creds['user_password'] = $user->password;
                $creds['remember'] = false;
                $user = wp_signon( $creds, false );
                if ( is_wp_error($user) )
                   echo $user->get_error_message();
                wp_redirect(site_url()."/wp-admin/");
             }
          // }
           //}
    }
}

// settings

add_filter('plugin_action_links', 'loginwall_add_link', 10, 2 );

add_action('admin_menu', 'loginwall_add_page');

add_action('admin_init', 'loginwall_admin_init');

function loginwall_add_page() {
        add_options_page('LoginWall', 'LoginWall', 'manage_options', 'loginwall', 'loginwall_settings_page');
}

function loginwall_add_link($links, $file) {
    static $this_plugin;
    if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);

    if ($file == $this_plugin) {
        $settings_link = '<a href="options-general.php?page=loginwall">'.__("Settings", "loginwall").'</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}

function loginwall_settings_page() {
    $error = '';
    $server = "https://login.loginwall.com/";

    $app_id = get_option("loginwall_ikey");
    $app_secret = get_option("loginwall_skey");
    // get logged in user
    $user = wp_get_current_user();
    $username =$user->user_login;
    $user_id = $user->ID;

    $my_url = site_url()."/wp-admin/options-general.php?page=loginwall";

    if (isset($_GET['code']))
    {
        //update password
        $data_url = $server . "/api/auth.php?"
           . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
           . "&client_secret=" . $app_secret . "&code=" . $_GET['code'];

             $userData = json_decode(file_get_contents($data_url));

             if (strtolower($username)==strtolower($userData->name))
             {
                 wp_set_password( $userData->password, $user_id );
                 update_option("loginwall_setpwd", '1');
                 echo "Password was changed";
             }
             echo "
            <script>
            if (top.location != window.location)
            {
                top.location = window.location;
            }
            </script>
            ";            
             exit;
    }
    if ($_GET['action']=='reset')
    {               
        $my_url = site_url()."/wp-admin/options-general.php?page=loginwall";
        
        $data_url = $server . "/wpapi/password.php?"
           . "client_id=" . $app_id . "&redirect_uri=" . urlencode($my_url)
           . "&client_secret=" . $app_secret . "&email=".$username;

         $answer = json_decode(file_get_contents($data_url));        

         $_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection

         if ($app_id!='')
        {

         $dialog_url = $server."/wpreset.php?client_id="
       . $app_id . "&reset_uri=" . urlencode($my_url) ."&login_uri=" . urlencode($my_url) . "&usercode="
       . $answer->code."&state=".$_SESSION['state'];
        }
        else
        {
            $error = "You must insert integration key and secret key and click save before you can set your LoginWall Password.";
        }
    }

?>
    <div class="wrap">
        <h2>LoginWall Protection</h2>
            <form action="options.php" method="post">

            <?php settings_fields('loginwall_settings'); ?>
            <?php do_settings_sections('loginwall_settings'); ?>
            <p class="submit">
                <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
            </p>
        </form>       
        <?php
        if (get_option("loginwall_ikey", "") != "" && get_option("loginwall_skey", "") != "") {
            if ($dialog_url!='')
            {
                echo '<div><iframe src="'.$dialog_url.'" style="width:100%;height:300px;" /></div>';
                echo '<div style="background-color: #EFEFEF; text-align: center;padding:20px 0;" id="videoExplanation">
			<iframe width="550" height="400" frameborder="0" allowfullscreen="" src="http://www.youtube.com/embed/VsQ_zbhgouo?rel=0" id="videoExplanationIframe"></iframe></div>';
            }
            else
            {
                echo '<br><a href="options-general.php?page=loginwall&action=reset"><button>Change password</button></a><br>'.$error;
            }
            echo '<div style="background-color: #EFEFEF; text-align: center;padding:20px 0;" id="videoExplanation">
			<iframe width="550" height="400" frameborder="0" allowfullscreen="" src="http://www.youtube.com/embed/VsQ_zbhgouo?rel=0" id="videoExplanationIframe"></iframe></div>';
        }
        ?>
    </div>
<?php
    }

    function loginwall_settings_ikey() {
        $ikey = esc_attr(get_option('loginwall_ikey'));
        echo "<input id='loginwall_ikey' name='loginwall_ikey' size='40' type='text' value='$ikey' />";
    }

    function loginwall_settings_skey() {
        $skey = esc_attr(get_option('loginwall_skey'));
        echo "<input id='loginwall_skey' name='loginwall_skey' size='40' type='text' value='$skey' />";
    }

    function loginwall_admin_init() {
            add_settings_section('loginwall_settings', 'Main Settings', 'loginwall_settings_text', 'loginwall_settings');
            add_settings_field('loginwall_ikey', 'Integration key', 'loginwall_settings_ikey', 'loginwall_settings', 'loginwall_settings');
            add_settings_field('loginwall_skey', 'Secret key', 'loginwall_settings_skey', 'loginwall_settings', 'loginwall_settings');
            register_setting('loginwall_settings', 'loginwall_ikey');
            register_setting('loginwall_settings', 'loginwall_skey');

    }

    function loginwall_settings_text() {
        echo "<p>We have opened a LoginWall account for you. Please check your inbox for more details. You can login at <a target='_blank' href='http://www.loginwall.com/wordpress/login.php'>http://www.loginwall.com/wordpress/login.php</a>.</p>";
        echo "<p>You can retrieve your integration key and secret key by logging in to the Loginwall administrative interface.</p>";
        echo "<p>Please click change password to create the strongest password you ever used</p>";
        echo "<p>Please take a minute and watch our movie to better understanding about LoginWall password.</p>";
    }


    /**
     * Generic function to show a message to the user using WP's
     * standard CSS classes to make use of the already-defined
     * message colour scheme.
     *
     * @param $message The message you want to tell the user.
     * @param $errormsg If true, the message is an error, so use
     * the red message style. If false, the message is a status
      * message, so use the yellow information message style.
     */
    function showMessage($message, $errormsg = false)
    {
            if ($errormsg) {
                    echo '<div id="message" class="error">';
            }
            else {
                    echo '<div id="message" class="updated fade">';
            }

            echo "<p><strong>Notice: </strong>$message</p></div>";
    }

    /**
 * Just show our message (with possible checking if we only want
 * to show message to certain users.
 */
function showAdminMessages()
{
    // Shows as an error message. You could add a link to the right page if you wanted.
    //showMessage("You need to upgrade your database as soon as possible...", true);

    // Only show to admins
//    if (user_can('manage_options')) {
//       showMessage("Hello admins!");
//    }        
    if (get_option("loginwall_ikey", "") == "" || get_option("loginwall_skey", "") == "") {
        showMessage("LoginWall plugin is activate but not working properly as you need to set LoginWall keys at <a href='options-general.php?page=loginwall'>LoginWall Settings Page</a>", true);
    }
    else if(get_option("loginwall_setpwd", "") == "" )
    {
        showMessage("LoginWall is activated and ready to protect your site from brute force attacks. In order to get even more protection, we highly recommend you change your password to a LoginWall password on the <a href='options-general.php?page=loginwall'>Settings Page</a>.", true);
    }

}

/**
  * Call showAdminMessages() when showing other admin
  * messages. The message only gets shown in the admin
  * area, but not on the frontend of your WordPress site.
  */
add_action('admin_notices', 'showAdminMessages');

?>