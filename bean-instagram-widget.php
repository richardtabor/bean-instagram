<?php
/*--------------------------------------------------------------------

 	Widget Name: Bean Instagram Widget
 	Widget URI: http://themebeans.com/
 	Description:  A widget that displays your most recent Instagram posts, your Instagram feed or your liked posts on Instagram with API v1
 	Author: ThemeBeans
 	Author URI: http://www.themebeans.com
 	Version: 1.0

/*--------------------------------------------------------------------*/


// WIDGET CLASS
class widget_bean_instagram extends WP_Widget {

	/*--------------------------------------------------------------------*/
	/*	WIDGET SETUP
	/*--------------------------------------------------------------------*/
	public function __construct() {
		parent::__construct(
	 		'bean_instagram', // BASE ID
			'Bean Instagram', // NAME
			array( 'description' => __( 'Displays your Instagram feed.', 'bean' ), )
		);

		if ( is_active_widget(false, false, $this->id_base) )
            add_action( 'wp_head', array(&$this, 'load_widget_style') );
	}

	/*--------------------------------------------------------------------*/
	/*	LOAD WIDGET STYLE DEPENDING ON THE THEME ( OR DEFAULT IF NO ASSOCIATED STYLE WAS FOUND )
	/*--------------------------------------------------------------------*/

	public function load_widget_style() {
		$current_theme = wp_get_theme();

		$theme_css_path = DIRNAME(__FILE__) . '/themes/' . $current_theme . '/instagram.css';
		$theme_css_url = plugin_dir_url(__FILE__) . 'themes/' . $current_theme . '/instagram.css';
		$default_css_url = plugin_dir_url(__FILE__) . 'themes/_default/instagram.css';

		// fix spaces that may exist in the theme name
		$theme_css_url = str_replace(' ', '%20', $theme_css_url);

		if (file_exists($theme_css_path))
			wp_enqueue_style( 'bean-instagram-style', $theme_css_url, false, '1.0', 'all' );
		else
			wp_enqueue_style( 'bean-instagram-style', $default_css_url, false, '1.0', 'all' );
	}

	/*--------------------------------------------------------------------*/
	/*	DISPLAY WIDGET
	/*--------------------------------------------------------------------*/
	public function widget($args, $instance) {
		extract($args);
		if(!empty($instance['title'])){ $title = apply_filters( 'widget_title', $instance['title'] ); }

        $desc = $instance['desc'];

		echo $before_widget;
		if ( ! empty( $title ) ){ echo $before_title . $title . $after_title; }

        if($desc != '') : ?><p><?php echo $desc; ?></p><?php endif;

		$accesstoken = self::getAccessToken();

		// CHECK SETTINGS & DIE IF NOT SET
		if(!$accesstoken || empty($instance['cachetime'])){
			echo '<strong>Please fill all the widget settings under "Settings > Bean Instagram" and request an access token!</strong>' . $after_widget;
			return;
		}

		// CHECK IF CACHE NEEDS UPDATE
		$ip_instagram_plugin_last_cache_time = self::getLastCacheTime();
		$diff = time() - $ip_instagram_plugin_last_cache_time;
		$crt = $instance['cachetime'] * 3600;

	 	//	YUP, NEEDS ONE
		if($diff >= $crt || empty($ip_instagram_plugin_last_cache_time)) {

			// PREPARE THE ENDPOINT URI DEPENDING ON THE TYPE OF CONTENT TO SHOW
			switch($instance['show']) {
				case 'recent' :
								// GET THE USER ID
								$user_id = self::getUserId();

								$endpoint_uri = "https://api.instagram.com/v1/users/" . $user_id . "/media/recent/?count=8&access_token=" . $accesstoken;
								break;

				case 'liked' :
								$endpoint_uri = "https://api.instagram.com/v1/users/self/media/liked?count=8&access_token=" . $accesstoken;
								break;

				default :
								$endpoint_uri = "https://api.instagram.com/v1/users/self/feed?count=8&access_token=" . $accesstoken;
								break;
			}

			// WE HAVE AN ENDPOINT_URI SET
			if (isset($endpoint_uri) && !empty($endpoint_uri)) {
				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, $endpoint_uri);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$result = curl_exec($ch);

				curl_close($ch);

				// PARSE THE RESULT TO MAKE IT USEABLE
				$result = json_decode($result);

				// CHECK IF THE RESULT HAS SOME ERROR
				if (@isset($result->meta->error_type)) {

					$cache_error = 1;

					// CHECK IF THERE IS A PROBLEM IN THE ACCESS TOKEN (EITHER INVALID OR EXPIRED)
					if ($result->meta->error_type == 'OAuthAccessTokenError'
					 || $result->meta->error_type == 'OAuthAccessTokenException') {

					 	// DELETE THE ACCESS TOKEN
						self::deleteAccessToken();

						$cache_error = 2;
					}
				}
				else {
					// GO THROUGH THE RESULT, EXTRACT THE USEFUL INFORMATION, AND STORE IN THE ARRAY
					$posts_array = array();

					if (@isset($result->data))
					foreach($result->data as $post) {
                        if (!is_object($post)) continue;

						$post_entry = array(
									"caption" => $post->caption == "null" ? "" : preg_replace('/[^(\x20-\x7F)]*/','', isset($post->caption->text) ? $post->caption->text : ''),
									"link" => $post->link,
									"image" => $post->images->thumbnail->url
									);

						array_push($posts_array, $post_entry);
					}
				}

			}

			// SAVE POSTS TO WP OPTION
			if (isset($posts_array)) {
				self::setPostsCache($posts_array);
				self::setLastCacheTime(time());
			}

			echo '<!-- instagram cache has been updated! -->';
		}


		// DISPLAY ERROR MESSAGE IF THE ACCESS TOKEN SEEMS TO BE INVALID OR HAS EXPIRED
		if (@isset($cache_error)) {
			?>
			<p>Error while fetching cache:
			<?php
			switch($cache_error) {
				case 1 : echo "An undefined error occurred while contacting the Instagram API.";
						 break;

				case 2 : echo "The access token seems to be invalid or has expired.";
						 break;
			}?>

			</p>

			<?php
		}


		$ip_instagram_plugin_posts = self::getPostsCache();
		$ip_instagram_plugin_posts = maybe_unserialize($ip_instagram_plugin_posts);

		if (!empty($ip_instagram_plugin_posts)) {
			?>

			<div class="instagram-image-wrapper">
				<?php
				$i = 0;
				foreach($ip_instagram_plugin_posts as $post) {
				?>

					<div class="instagram_badge_image" id="instagram_badge_image<?php echo $i; ?>">
						<a href="<?php echo $post['link']; ?>" target="_blank">
							<img src="<?php echo $post['image']; ?>" alt="A post on Instagram" title="<?php echo $post['caption']; ?>">
						</a>
					</div>

				<?php

					$i++;
				}

				?>
			</div>

			<?php
		} else {
		?>
			<strong>Could not retrieve Instagram posts.</strong>
		<?php
		}

		echo $after_widget;
	}


	/*--------------------------------------------------------------------*/
	/*	UPDATE WIDGET
	/*--------------------------------------------------------------------*/
	public function update($new_instance, $old_instance) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
        $instance['desc'] = stripslashes( $new_instance['desc'] );
		$instance['cachetime'] = strip_tags( $new_instance['cachetime'] );
		$instance['show'] = strip_tags( $new_instance['show'] );

		if($old_instance['show'] != $new_instance['show']
		|| $old_instance['cachetime'] != $new_instance['cachetime'] ) {

			// SET THE LAST CACHE TIME TO ZERO SO THAT THE POSTS ARE RE-CACHED
			self::setLastCacheTime(0);
		}

		return $instance;
	}


	/*--------------------------------------------------------------------*/
	/*	WIDGET SETTINGS (FRONT END PANEL)
	/*--------------------------------------------------------------------*/
	public function form($instance) {
		$defaults = array( 'title' => 'Bean Instagram Plugin',
                           'desc'  => '',
                           'cachetime' => '5',
                           'show' => 'recent'
						 );

		$instance = wp_parse_args( (array) $instance, $defaults );


		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bean' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>

        <p style="margin-top: -8px;">
            <textarea class="widefat" rows="5" cols="15" id="<?php echo $this->get_field_id( 'desc' ); ?>" name="<?php echo $this->get_field_name( 'desc' ); ?>"><?php echo $instance['desc']; ?></textarea>
        </p>

		<p>
			<label for="<?php echo $this->get_field_id( 'show' ); ?>"><?php _e('Show:', 'bean'); ?></label>
			<select id="<?php echo $this->get_field_id( 'show' ); ?>" name="<?php echo $this->get_field_name( 'show' ); ?>" class="widefat">
				<option value='recent' <?php if ( 'recent' == $instance['show'] ) echo 'selected="selected"'; ?>>Recent Uploads</option>
				<option value='feed' <?php if ( 'feed' == $instance['show'] ) echo 'selected="selected"'; ?>>Following</option>
				<option value='liked' <?php if ( 'liked' == $instance['show'] ) echo 'selected="selected"'; ?>>Liked Posts</option>
			</select>
		</p>

		<p><label for="<?php echo $this->get_field_id( 'cachetime' ); ?>"><?php _e( 'Cache every:', 'bean' ); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'cachetime' ); ?>" id="<?php echo $this->get_field_id( 'cachetime' ); ?>" value="<?php echo esc_attr($instance['cachetime']) ?>" class="small-text" /> hours</p>

		<?php
	}

    /*--------------------------------------------------------------------*/
    /*  SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM ACCESS TOKEN
    /*--------------------------------------------------------------------*/

    private function getAccessToken() {
        return get_option('bean_inst_access_token');
    }

    /*--------------------------------------------------------------------*/
    /*  SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM ACCESS TOKEN
    /*--------------------------------------------------------------------*/

    private function getClientId() {
        return get_option('bean_inst_client_id');
    }

	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM USER ID
	/*--------------------------------------------------------------------*/

	private function getUserId() {
		return get_option('bean_inst_plugin_userid');
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM POSTS CACHE
	/*--------------------------------------------------------------------*/

	// GET THE INSTAGRAM POSTS CACHE
	private function getPostsCache() {
		return get_option('bean_inst_plugin_posts_' . $this->id);
	}

	// SET THE INSTAGRAM POSTS CACHE
	private function setPostsCache($cache) {
		update_option('bean_inst_plugin_posts_' . $this->id, $cache);
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM FEED LAST CACHE TIME
	/*--------------------------------------------------------------------*/

	// GET THE LAST CACHE TIME DEPENDING ON THE ID
	private function getLastCacheTime() {
		return get_option('bean_inst_plugin_last_cache_time_' . $this->id);
	}

	// SET THE LAST CACHE TIME DEPENDING ON THE ID
	private function setLastCacheTime($time) {
		update_option('bean_inst_plugin_last_cache_time_' . $this->id, $time);
	}

}

// A LITTLE WORK AROUND TO DELETE ALL THE OPTIONS ASSOCIATED WITH A WIDGET WHENEVER IT IS DELETED
function my_sidebar_admin_setup() {

     if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {

          $widget_id = $_POST['widget-id'];

          if ( isset( $_POST['delete_widget'] ) ) {
               if ( 1 === (int) $_POST['delete_widget'] ) {
                    if ( strpos($widget_id, 'bean_instagram') !== FALSE ) {
						delete_option('bean_inst_plugin_accesstoken_' . $widget_id);
						delete_option('bean_inst_plugin_last_cache_time_' . $widget_id);
						delete_option('bean_inst_plugin_posts_' . $widget_id);
						delete_option('bean_inst_plugin_userid_' . $widget_id);
                    }
               }
          }

     }

}

// REGISTER WIDGET
function register_ip_instagram_widget(){
	register_widget('widget_bean_instagram');
}
add_action('init', 'register_ip_instagram_widget', 1);
add_action( 'sidebar_admin_setup', 'my_sidebar_admin_setup' );





/**
 * Widget Settings Admin Page Output.
 * This section adds a "Bean Instagram" menu to the Settings dashboard link.
 *  
 *   
 * @package WordPress
 * @subpackage Bean Instagram
 * @author ThemeBeans
 * @since Bean Instagram 1.4
 */
 
/*===================================================================*/
/*  CREATE ADMIN LINK
/*===================================================================*/ 
function bean_instagram_options_page_settings() 
{
    add_options_page(
        __('Instagram Settings', 'bean'), __('Bean Instagram', 'bean'), 'manage_options', 'bean-instagram-plugin-settings', 'bean_instagram_admin_page'
    );
} //END bean_instagram_options_page_settings

add_action( 'admin_menu', 'bean_instagram_options_page_settings' );


/*===================================================================*/
/*  LOAD ADMIN JS
/*===================================================================*/ 
function bean_instagram_enqueue_scripts($hook)  {

    if ('settings_page_bean-instagram-plugin-settings' !== $hook) return;

    wp_enqueue_script( 'bean-instagram-script', BEAN_INSTAGRAM_PATH . 'js/bean-instagram.js', array(), '1.0', true );
} //END bean_instagram_enqueue_scripts

add_action( 'admin_enqueue_scripts', 'bean_instagram_enqueue_scripts' );


/*===================================================================*/
/*  REGISTER SETTINGS
/*===================================================================*/  
add_action('admin_init', 'bean_instagram_register_settings');

function bean_instagram_settings() {
    $bean_inst = array();
    $bean_inst[] = array('label' => 'Client ID:', 'name' => 'bean_inst_client_id');
    $bean_inst[] = array('label' => 'Client Secret:', 'name' => 'bean_inst_client_secret');
    $bean_inst[] = array('label' => 'Access Token:', 'name' => 'bean_inst_access_token', 'render' => false);
    $bean_inst[] = array('label' => 'User ID:', 'name' => 'bean_inst_plugin_userid', 'render' => false);

    return $bean_inst;
} //END bean_instagram_settings

function bean_instagram_register_settings() {
    $settings = bean_instagram_settings();
    foreach($settings as $setting) {
        register_setting('bean_instagram_settings', $setting['name'], isset($setting['sanitize_callback']) ? $setting['sanitize_callback'] : '');
    }
} //END bean_instagram_register_settings



/*===================================================================*/
/*  DELETE SETTINGS WHEN THE PLUGIN IS DEACTIVATED
/*===================================================================*/  

function bean_instagram_delete_plugin_options() {
    $settings = bean_instagram_settings();
    foreach($settings as $setting) {
        delete_option($setting['name']);
    }
}

register_deactivation_hook( BEAN_INSTAGRAM_PLUGIN_FILE, 'bean_instagram_delete_plugin_options' );


/*===================================================================*/
/*  CREATE THE SETTINGS PAGE
/*===================================================================*/  
function bean_instagram_admin_page($id) 
{
    if( !current_user_can('manage_options') ) { wp_die( __('Insufficient permissions', 'bean') ); }

    $settings = bean_instagram_settings();

    $settings_updated = isset($_GET['settings-updated']);

    $prefix = (!empty($_SERVER['HTTPS']) ? "https://" : "http://");
    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $redirectURI = $prefix . $_SERVER['HTTP_HOST'] . $uri_parts[0] . '?page=bean-instagram-plugin-settings';

    $code = isset($_GET['code']) ? $_GET['code'] : '';

    // Check if code parameter (from Instagram oauth) was passed and if so, get the access token from the Instagram API
    $code_valid = isset($_GET['code']) ? $_GET['code'] : '';

    if ($code_valid && !isset($_GET['settings-updated']) ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.instagram.com/oauth/access_token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "client_id=" . get_option('bean_inst_client_id') .
                                             "&client_secret=" . get_option('bean_inst_client_secret') .
                                             "&grant_type=authorization_code" .
                                             "&redirect_uri=" . urlencode($redirectURI) .
                                             "&code=" . $code );

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_REFERER, $redirectURI);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        // JSON decode the result
        $result = json_decode($result);

        if (isset($result->access_token)) {
            $accesstoken_value = $result->access_token;
            $userid_value = $result->user->id;
        }
    }

    echo '<div class="wrap">';
        screen_icon();
        echo '<h2>Bean Instagram Plugin</h2>';
        echo '<div class="wrap">'; 
        echo '<p>' . __('Display the photos of people you follow on <a href="http://instagram.com" target="_blank">Instagram</a>, or the one\'s that you\'ve uploaded yourself, or the one\'s that you\'ve liked.<br><br>Follow the steps below to get started. You can also check out our more <a href="http://themebeans.com/registering-your-instagram-app-to-retrieve-your-client-id-secret-code/">detailed tutorial</a> on how to set things up. Cheers!', 'bean' ) . '</p></br>';
        ?>
            <?php
            echo '<form method="post" action="options.php">';
                
                
                
                echo '<h4 style="font-size: 15px; font-weight: 600; color: #222; margin-bottom: 10px;">' . __('How To', 'bean' ) . '</h4>';
                echo '<ol>';
                    echo '<li>' . __( 'Header over to the Instagram Developer page: ', 'bean' ) . '<a href="http://instagram.com/developer" target="_blank">http://instagram.com/developer</a></li>';
                    /* translators: Click is used as a verb. */
                    printf( '<li>' . __( 'Click %1$s at the top bar.', 'bean' ) . '</li>', "<strong>Manage Clients</strong>");
                    /* translators: Click is used as a verb. */
                    printf( '<li>' . __( 'Click %1$s.', 'bean' ) . '</li>', "<strong>Register a New Client</strong>");
                    printf( '<li>' . __( 'Fill in all the information. Set the %1$s field exactly equal to: %2$s', 'bean' ) . '</li>' , "<strong>OAuth redirect_uri</strong>", "<strong>$redirectURI</strong>");
                    echo '<li style="margin-bottom: 20px;">' . __( 'Complete the registration.', 'bean' ) . '</li>';
                    printf( '<li>' . __( 'Copy/paste the %1$s and %2$s values of your Instagram Client in the fields below.', 'bean' ) . '</li>', "<strong>Client ID</strong>", "<strong>Client Secret</strong>" );
                    /* translators: Click the "Save Changes" button below. */
                    printf( '<li style="margin-bottom: 20px;">' . __( 'Click the %1$s button below.', 'bean' ) . '</li>', "<strong>" . __( 'Save Changes' ) . "</strong>" );
                    /* translators: Click the "Get the Access Token" button below. */
                    printf( '<li>' . __( 'Click the %1$s button below.', 'bean' ) . '</li>', '<strong>' . __( 'Get the Access Token', 'bean' ) . '</strong>' );
                    /* translators: Click the "Save Changes" button below again. */
                    printf( '<li style="margin-bottom: 20px;">' . __( 'Click the %1$s button below again.', 'bean' ) . '</li>', "<strong>" . __( 'Save Changes' ) . "</strong>" );
                    printf( '<li>' . __( 'Add the %1$s widget to a widget area in your %2$s.', 'bean' ) . '</li>', '<strong>Bean Instagram</strong>', '<a href="widgets.php"><strong>' . __( 'Widgets Dashboard' ) . '</strong></a>' );
                echo '</ol></br>';
    
                settings_fields('bean_instagram_settings');
                
                echo '<h4 style="font-size: 15px; font-weight: 600; color: #222; margin-bottom: 7px;">' . __('OAuth Codes', 'bean' ) . '</h4>';
                
                echo '<table>';
                    foreach($settings as $setting) {
                        if (isset($setting['render']) && $setting['render'] === false) continue;

                        echo '<tr>';
                            echo '<td style="padding-right: 20px;">' . $setting['label'] . '</td>';
                            echo '<td><input type="text" style="width:500px;" name="'.$setting['name'].'" id="'.$setting['name'].'" value="'.get_option($setting['name']).'" onchange="clear_accesstoken(\'' . sprintf( __( 'Not available! Kindly click %1$s after entering the Client ID above.', 'bean'), __( 'Save Changes' ) ) . '\');"></td>';
                        echo '</tr>';
                    }

                    echo '<tr>';
                        echo '<td style="padding: 0 20px 0 0;">' . __('Access Token', 'bean') . '</td>';
                        echo '<td style="padding: 30px 0;">';

                        $stored_accesstoken = get_option("bean_inst_access_token", "");
                        $stored_userid = get_option("bean_inst_plugin_userid", "");

                        if (empty($stored_accesstoken) && !isset($accesstoken_value)) {
                            $client_id_value = get_option('bean_inst_client_id');

                            if (!$code_valid && empty($client_id_value)) {
                                echo '<strong style="color: red;">';
                                printf( __( 'Not available! Kindly click %1$s after entering the Client ID above.', 'bean'), __( 'Save Changes' ) );
                                echo '</strong>';
                            } else {
                                echo '<input type="button" class="button" onclick="bean_instagram_get_access_token.call(this, \'' . $redirectURI . '\'); return false;" value="' . __( 'Get the Access Token', 'bean' ) . '">';

                                if ($code_valid && !isset($accesstoken_value)) {
                                    echo "<strong style='padding-left: 10px; line-height: 28px;'>";
                                    echo __("There was some error while trying to retrieve the access token. Make sure that the Redirect URI in your Instagram API Client is set to exactly what is suggested above.", 'bean');
                                    echo "</strong>";
                                }
                            }

                        } else {
                            echo '<strong id="bean_inst_access_token_status" style="color: green;">Available';

                            if (empty($stored_accesstoken)) {
                                echo ' – ';
                                echo __('Click "Save Changes" below to save the Access Token. (It will be lost otherwise.)', 'bean');
                            }

                            echo '</strong>';
                        }

                        echo '<input type="hidden" id="bean_inst_access_token" name="bean_inst_access_token" value="' . (isset($accesstoken_value) ? $accesstoken_value : $stored_accesstoken).'">';

                        echo '<input type="hidden" id="bean_inst_plugin_userid" name="bean_inst_plugin_userid" value="' . (isset($userid_value) ? $userid_value : $stored_userid).'">';

                        echo '</td>';
                    echo '</tr>';

                echo '</table>';
    
                submit_button();
    
            echo '</form>';
        echo '</div>';
    echo '</div>';
} //END bean_instagram_admin_page


?>