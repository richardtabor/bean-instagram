<?php
/*--------------------------------------------------------------------

 	Widget Name: Bean Instagram Feed Widget
 	Widget URI: http://themebeans.com/
 	Description:  A widget that displays your most recent Instagram posts, your Instagram feed or your liked posts on Instagram with API v1
 	Author: ThemeBeans
 	Author URI: http://www.themebeans.com
 	Version: 1.1

/*--------------------------------------------------------------------*/


// WIDGET CLASS
class widget_bean_instagram extends WP_Widget {

	/*--------------------------------------------------------------------*/
	/*	WIDGET SETUP
	/*--------------------------------------------------------------------*/
	public function __construct() {
		parent::__construct(
	 		'bean_instagram', // BASE ID
			'Bean Instagram (ThemeBeans)', // NAME
			array( 'description' => __( 'A widget that displays your Instagram feed, posts, or likes', 'bean' ), )
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

		echo $before_widget;
		if ( ! empty( $title ) ){ echo $before_title . $title . $after_title; }

		$accesstoken = self::getAccessToken();

		// CHECK SETTINGS & DIE IF NOT SET
		if(empty($instance['clientid']) || !$accesstoken || empty($instance['cachetime'])){
			echo '<strong>Please fill all the widget settings and request an access token!</strong>' . $after_widget;
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
						$post_entry = array(
									"caption" => $post->caption == "null" ? "" : preg_replace('/[^(\x20-\x7F)]*/','', $post->caption->text),
									"link" => $post->link,
									"image" => $post->images->thumbnail->url
									);

						array_push($posts_array, $post_entry);
					}
				}

			}

			// SAVE POSTS TO WP OPTION
			if (@isset($posts_array)) {
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
		$instance['clientid'] = strip_tags( $new_instance['clientid'] );
		$instance['clientsecret'] = strip_tags( $new_instance['clientsecret'] );
		$instance['cachetime'] = strip_tags( $new_instance['cachetime'] );
		$instance['show'] = strip_tags( $new_instance['show'] );

		if($old_instance['clientid'] != $new_instance['clientid']
		|| $old_instance['clientsecret'] != $new_instance['clientsecret']
		|| $old_instance['show'] != $new_instance['show']
		|| $old_instance['cachetime'] != $new_instance['cachetime'] ) {

			// SET THE LAST CACHE TIME TO ZERO SO THAT THE POSTS ARE RE-CACHED
			self::setLastCacheTime(0);

			if ($old_instance['clientid'] != $new_instance['clientid']
			|| $old_instance['clientsecret'] != $new_instance['clientsecret'])
				self::deleteAccessToken();
		}

		return $instance;
	}


	/*--------------------------------------------------------------------*/
	/*	WIDGET SETTINGS (FRONT END PANEL)
	/*--------------------------------------------------------------------*/
	public function form($instance) {
		$defaults = array( 'title' => 'Bean Instagram Plugin',
						   'clientid' => '',
						   'clientsecret' => '',
						   'cachetime' => '5',
						   'show' => 'recent'
						 );

		$instance = wp_parse_args( (array) $instance, $defaults );

		$prefix = (!empty($_SERVER['HTTPS']) ? "https://" : "http://");
		$uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
		$redirectURI = $prefix . $_SERVER['HTTP_HOST'] . $uri_parts[0];

		$code = $_GET['code'];

		// Check if code parameter (from Instagram oauth) was passed and if so, get the access token from the Instagram API
		$code_valid = $_GET['code'];

		// Check if the code was sent for this object of the widget
		$widget_id_valid = $_GET['widget_id'] == $this->id;

		if ($code_valid && !self::getAccessToken() && $widget_id_valid) {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://api.instagram.com/oauth/access_token");
			curl_setopt($ch, CURLOPT_POSTFIELDS, "client_id=" . $instance['clientid'] .
												 "&client_secret=" . $instance['clientsecret'] .
												 "&grant_type=authorization_code" .
												 "&redirect_uri=" . urlencode($redirectURI . "?widget_id=" . $this->id) .
												 "&code=" . $code );

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);

			curl_close($ch);

			// JSON decode the result
			$result = json_decode($result);

			if (isset($result->access_token)) {
				self::setAccessToken($result->access_token);
				self::setUserId($result->user->id);
			}
		}

		// Retrieve the accesstoken
		$accesstoken = self::getAccessToken();

		?>

		<p style="margin-bottom: 25px;">This widget requires that you register an application on the <a href="http://instagram.com/developer" target="blank">Instagram Developer</a> page in order to access your feed. <br><a href="http://themebeans.com/registering-your-instagram-app-to-retrieve-your-client-id-secret-code/?ref=plugin_bean_instagram" target="blank">Find Out More</a> 

		<?php if (!isset($accesstoken) || empty($accesstoken)) { ?>
		
			<br><br>
			<label>Redirect URI:</label><br>
			<span style="color: #797979;">When registering your app, set the Redirect URI field to the following: "<?php echo get_admin_url(); ?>widgets.php"</span>
		
		<?php } ?>

		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bean' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'clientid' ); ?>"><?php _e( 'Client ID:', 'bean' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'clientid' ); ?>" name="<?php echo $this->get_field_name( 'clientid' ); ?>" type="text" value="<?php echo esc_attr( $instance['clientid'] ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'clientsecret' ); ?>"><?php _e( 'Client Secret:', 'bean' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'clientsecret' ); ?>" name="<?php echo $this->get_field_name( 'clientsecret' ); ?>" type="text" value="<?php echo esc_attr( $instance['clientsecret'] ); ?>" />
		</p>

		<p>
			<label><?php _e( 'Access Token: ', 'bean' ); ?></label>
			<?php

			if ( empty($accesstoken) ) {
			?>
				<span style="color: red;">Not available.</span>
		
				<br>
				<br>

				<?php
				if (empty($instance['clientid']) || empty($instance['clientsecret'] ) ) {
				?>
					<span style="color: #797979;">Please set the "Client ID" and "Client Secret" fields and press save to retrieve the access token.</span>
					<br>
					<br>

				<?php
				} else {

				$button_id = "getaccesstoken_" . rand();

				?>	
					<label><a href="javascript:void(0)" id="<?php echo $button_id; ?>">Get Access Token</a></label>

					<br>
					<span style="color: #797979;">Click to retrieve your access token.</span>

				<?php
				}
				?>

			<?php
			} else {
			?>
				<label><span style="color: green;">Available</span></label>
			<?php
			}
			?>
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


		<script type="text/javascript">
			var getAccessTokenButton = document.getElementById('<?php echo $button_id; ?>');
			var newWindow = null;
			var timer = null;

			if (getAccessTokenButton)
			getAccessTokenButton.addEventListener('click', function(event) {
				var clientid = "<?php echo $instance['clientid']; ?>";
				var redirect_uri = window.location.href;
				redirect_uri = redirect_uri.indexOf('?') > -1 ? redirect_uri.split('?')[0] : redirect_uri;
				redirect_uri += "?widget_id=<?php echo $this->id; ?>";

	            var form = document.createElement("form");
	            form.setAttribute('id', 'instagram_auth');
	            form.setAttribute('name', 'instagram_auth');
	            form.setAttribute('action', 'https://api.instagram.com/oauth/authorize/');
	            form.setAttribute('method', 'GET');

	            var responseType = document.createElement('input');
	            responseType.setAttribute('type', 'hidden');
	            responseType.setAttribute('name', 'response_type');
	            responseType.setAttribute('value', 'code');
	            responseType.setAttribute('id', 'instagram_auth_response_type');

	            var redirectURI = document.createElement('input');
	            redirectURI.setAttribute('type', 'hidden');
	            redirectURI.setAttribute('name', 'redirect_uri');
	            redirectURI.setAttribute('value', redirect_uri);
	            redirectURI.setAttribute('id', 'instagram_auth_redirect_uri');

	            var clientID = document.createElement('input');
	            clientID.setAttribute('type', 'hidden');
	            clientID.setAttribute('name', 'client_id');
	            clientID.setAttribute('value', clientid);
	            clientID.setAttribute('id', 'instagram_auth_client_id');

	            form.appendChild(responseType);
	            form.appendChild(redirectURI);
	            form.appendChild(clientID);

				form.submit();

				event.preventDefault();
				return false;
			});

			function instagram_authentication_timer() {
				try {
					if (typeof newWindow.closeWindow == "function") {
						newWindow.closeWindow();
						clearInterval(timer);
						self.location.reload(true);
					}
				} catch(e) {}

				if (newWindow.closed) {
					clearInterval(timer);
					self.location.reload(true);
				}
			}

		</script>

		<?php
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM ACCESS TOKEN
	/*--------------------------------------------------------------------*/

	// GET THE ACCESS TOKEN OF THE CURRENT INSTAGRAM PLUGIN OBJECT DEPENDING ON THE ID
	private function getAccessToken() {
		$return_access_token = get_option('ip_instagram_plugin_accesstoken_' . $this->id);

		if (isset($return_access_token) && !empty($return_access_token))
		return $return_access_token;

		return false;
	}

	// SET THE ACCESS TOKEN OF THE CURRENT INSTAGRAM PLUGIN OBJECT DEPENDING ON THE ID
	private function setAccessToken($accesstoken) {
		update_option('ip_instagram_plugin_accesstoken_' . $this->id, $accesstoken);
	}

	// DELETE THE ACCESS TOKEN OF THE CURRENT INSTAGRAM PLUGIN OBJECT DEPENDING ON THE ID
	private function deleteAccessToken() {
		delete_option('ip_instagram_plugin_accesstoken_' . $this->id);
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM USER ID
	/*--------------------------------------------------------------------*/

	// GET THE USER ID OF THE CURRENT INSTAGRAM PLUGIN OBJECT DEPENDING ON THE ID
	private function getUserId() {
		return get_option('ip_instagram_plugin_userid_' . $this->id);
	}

	// SET THE USER ID OF THE CURRENT INSTAGRAM PLUGIN OBJECT DEPENDING ON THE ID
	private function setUserId($userid) {
		update_option('ip_instagram_plugin_userid_' . $this->id, $userid);
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM POSTS CACHE
	/*--------------------------------------------------------------------*/

	// GET THE INSTAGRAM POSTS CACHE
	private function getPostsCache() {
		return get_option('ip_instagram_plugin_posts_' . $this->id);
	}

	// SET THE INSTAGRAM POSTS CACHE
	private function setPostsCache($cache) {
		update_option('ip_instagram_plugin_posts_' . $this->id, $cache);
	}


	/*--------------------------------------------------------------------*/
	/*	SUPPORT FUNCTIONS TO HANDLE THE INSTAGRAM FEED LAST CACHE TIME
	/*--------------------------------------------------------------------*/

	// GET THE LAST CACHE TIME DEPENDING ON THE ID
	private function getLastCacheTime() {
		return get_option('ip_instagram_plugin_last_cache_time_' . $this->id);
	}

	// SET THE LAST CACHE TIME DEPENDING ON THE ID
	private function setLastCacheTime($time) {
		update_option('ip_instagram_plugin_last_cache_time_' . $this->id, $time);
	}

}

// A LITTLE WORK AROUND TO DELETE ALL THE OPTIONS ASSOCIATED WITH A WIDGET WHENEVER IT IS DELETED
function my_sidebar_admin_setup() {

     if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {

          $widget_id = $_POST['widget-id'];

          if ( isset( $_POST['delete_widget'] ) ) {
               if ( 1 === (int) $_POST['delete_widget'] ) {
                    if ( strpos($widget_id, 'bean_instagram') !== FALSE ) {
						delete_option('ip_instagram_plugin_accesstoken_' . $widget_id);
						delete_option('ip_instagram_plugin_last_cache_time_' . $widget_id);
						delete_option('ip_instagram_plugin_posts_' . $widget_id);
						delete_option('ip_instagram_plugin_userid_' . $widget_id);
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


?>