<?php
/*
Plugin Name: 		GoUrl BBPRESS - Premium Membership Mode with Bitcoin Payments
Plugin URI: 		https://gourl.io/bbpress-premium-membership.html
Description: 		This simple plugin will add Premium Membership Mode to bbPress Forum. You can mark some topics on your forum as Premium and can easily monetise your forum with Bitcoins. Pay to read bbPress Premium Topics and Replies, Pay to add new replies to the topic, Pay to create new topics on bbPress
Version: 			1.0.0
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/bbPress-Premium-Membership-Bitcoins
*/


if (!defined( 'ABSPATH' )) exit;  // Exit if accessed directly in wordpress

if (!function_exists('gourl_bbp_gateway_load'))
{
	// gateway load
	add_action( 'plugins_loaded', 'gourl_bbp_gateway_load', 20);
	
	DEFINE('GOURLBB', "bbpress-gourl");
	
	function gourl_bbp_gateway_load()
	{
		class GoUrl_Bbpress
		{
			private $save_flag  	= false;
			private $fields 		= array("read_topic" => "Read First Post in the Topic", "read_reply" => "Read Comments/Replies", "create_reply" => "Add Comments/Replies");
			private $free			= array();
			private $premium		= array();
			private $reply_box 		= false;
			private $topic_box 		= false;
			private $coin_names 	= array();
			private $payments		= array();
			private $languages		= array();
			private $def			= array();
			private $create_topics 	= 0;
			private $create_premium = 0;
			private $checkout_page 	= 0;
			
			// localization
			private $texts 	= array("premium"		=> "Premium:",
									
									"read_topic" 	=> "You must be logged in to view this post.",
									"read_topic2"	=> "You need to have a premium account to view this post.",
							
									"read_reply"	=> "You must be logged in to view this response.",
									"read_reply2"	=> "You need to have a premium account to view this response.",
							
									"create_topic"	=> 'You must be logged in to create new topics.',
									"create_topic2"	=> 'You need to have a premium account to create new topic. Buy premium membership using secure anonymous payments with Bitcoins. What is <a href="https://bitcoin.org">Bitcoin</a>?',
					
									"create_reply"	=> 'You must be logged in to reply to this topic.',
									"create_reply2"	=> 'You need to have a premium account to post messages on this topic. Buy premium membership using secure anonymous payments with Bitcoins. What is <a href="https://bitcoin.org">Bitcoin</a>?',
					
									"login_text"	=> "Join today!",
									"join_text"		=> "Join today!",
					
									"btn_reply"		=> "Reply",
									"btn_topic"		=> "New Topic"
											
							);
			
			private $texts2 = array("premium"		=> "Add to Premium Topic Title",
									
									"read_topic" 	=> "Topic - First Post (Not Logged User)",
									"read_topic2"	=> "Topic - First Post (Logged User)",
							
									"read_reply" 	=> "Topic - Replies (Not Logged User)",
									"read_reply2"	=> "Topic - Replies (Logged User)",
							
									"create_topic"	=> "Create Topic (Not Logged User)",
									"create_topic2"	=> "Create Topic (Logged User)",
					
									"create_reply"	=> "Add Reply (Not Logged User)",
									"create_reply2"	=> "Add Reply (Logged User)",
					
									"login_text"	=> "Login Link Text",	
									"join_text"		=> "Payment Link Text",	
					
									"btn_reply"		=> "Reply Button",
									"btn_topic"		=> "New Topic Button"
			);
				
			
			
			/*
			 *  1
			*/
			public function __construct()
			{
				$this->def = $this->texts;
				
				if (is_admin()) 
				{
					if (isset($_GET["page"]) && $_GET["page"] == "bbpress_premium" && isset($_GET["post_type"]) && $_GET["post_type"] == "forum" && strpos($_SERVER["SCRIPT_NAME"], "edit.php"))
					{
						if (isset($_POST["free_read_topic"]) && isset($_POST["free_read_topic"])) $this->save_settings();
						add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);
					}
					
					add_action('admin_menu', array( &$this, 'admin_menu'));
	        		add_action('admin_head', array(&$this, 'admin_head'));
	        		
	        		add_action('manage_topic_posts_columns', array(&$this, 'admin_post_columns'), 1000);
	        		add_action('manage_topic_posts_custom_column', array(&$this, 'admin_columns_data'), 1000, 2);
	        		add_action('save_post', array(&$this, 'admin_save_post'));
				}
				
				// Create New Reply
				add_action('bbp_theme_before_reply_form', array(&$this, 'action_create_reply_start'));
				add_action('bbp_theme_after_reply_form',  array(&$this, 'action_create_reply_end'));
					
				// Create New Topic
				add_action('bbp_theme_before_topic_form', array(&$this, 'action_create_topic_start'));
				add_action('bbp_theme_after_topic_form',  array(&$this, 'action_create_topic_end'));
				add_action( 'bbp_new_topic', array(&$this, 'action_create_new_topic'));
				
				// Read Topic/Replies
				add_filter('bbp_get_reply_content', array(&$this, 'action_read_topics'), 1111, 2);

				// Add 'Premium' to Topic Title
				add_filter('bbp_get_topic_title', array(&$this, 'action_premium_title'), 1111, 2);
				
				// Plugin Links
				add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
	
				// Settings
				$this->get_settings();
				
				return true;
			}
	
			
			
			/*
			 * 2
			*/
			public function plugin_action_links($links, $file)
			{
				static $this_plugin;
			
				if (false === isset($this_plugin) || true === empty($this_plugin)) {
					$this_plugin = plugin_basename(__FILE__);
				}
			
				if ($file == $this_plugin) {
					$settings_link = '<a href="'.admin_url('edit.php?post_type=forum&page=bbpress_premium').'">'.__( 'Settings', GOURLBB ).'</a>';
					array_unshift($links, $settings_link);
				}
			
				return $links;
			}
	
	
	
			/*
			 * 3
			*/
			public function admin_menu()
			{
				add_submenu_page('edit.php?post_type=forum', __('Premium Settings', GOURLBB), __('Premium Settings', GOURLBB), 'add_users', 'bbpress_premium', array(&$this, 'settings_page')); 
				
				add_meta_box('premium-topic-meta', __("Premium", GOURLBB), array(&$this, 'premium_topic_meta'), 'topic', 'side', 'high');
					
				return true;
			}
	
	
	
			/*
			 * 4
			*/
			public function admin_head() 
			{
		        echo '<style type="text/css">th.column-premium_topic, td.column-premium_topic { width: 3%; text-align: center; } .gourlbblink {border-bottom:1px dashed;text-decoration:none}</style>';
		        
		        return true;
		    }
	
	
	
		    /*
		     * 5
		    */
		    public function admin_post_columns($columns) 
			{
				$columns['premium_topic'] = '<img src="'.plugins_url("/images/premium.png", __FILE__).'" width="16" height="16" alt="'.__("Premium Topic", GOURLBB).'" title="'.__("Premium Topic", GOURLBB).'" />';
				return $columns;
			}
	
	
	
			/*
			 * 6
			*/
			public function admin_columns_data($column, $id) 
			{
				if ($column == 'premium_topic') 
				{
					$premium = $this->is_premium_topic( $id );
									
					if ($premium == 1) $tmp = '<img border="0" src="'.plugins_url("/images/checked.gif", __FILE__).'" width="21" height="16" alt="'.__("Premium Topic", GOURLBB).'" title="'.__("Premium Topic", GOURLBB).'" />';
					else $tmp = '<img border="0" src="'.plugins_url("/images/unchecked.gif", __FILE__).'" width="21" height="16" alt="'.__("No Premium", GOURLBB).'" title="'.__("No Premium", GOURLBB).'" />';
					
					echo '<a href='.admin_url('post.php?post='.$id.'&action=edit').'>'.$tmp.'</a>';
				}
				
				return true;
			}
	
	
	
			/*
			 * 7
			*/
			public function premium_topic_meta() 
			{
				global $post;
				
				$premium = $this->is_premium_topic( $post->ID );
				
				$tmp  = '<strong>Premium Topic ?</strong> &#160; &#160; ';
				$tmp .= '<input type="radio" name="'.GOURLBB.'premium_topic" value="0"'.$this->chk($premium, 0).'> '.__('No', GOURLBB ).' &#160; &#160; <input type="radio" name="'.GOURLBB.'premium_topic" value="1"'.$this->chk($premium, '1').'> '.__('Yes', GOURLBB );
				$tmp .= '<p>'.sprintf(__('<a href="%s">More settings</a>', GOURLBB ), 'edit.php?post_type=forum&page=bbpress_premium') . '</p>';
					
				echo $tmp;
			}
	
	
			
			/*
			 * 8 
			*/
			private function is_premium_topic( $id )
			{
				$premium = 0;
				$values  = get_post_custom( $id );
				if (isset($values[GOURLBB.'premium_topic'])) $premium = current($values[GOURLBB.'premium_topic']);
				if (!$premium || !in_array($premium, array(0, 1))) $premium = 0;
						
				return $premium;
			}
			
			
			
			/*
			 * 9
			*/
			public function admin_save_post($post_id) 
			{
				if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
				if (!isset($_POST[GOURLBB.'premium_topic']) || !in_array($_POST[GOURLBB.'premium_topic'], array(0, 1))) return;
				if (!current_user_can('edit_post')) return;
				
				update_post_meta($post_id, GOURLBB.'premium_topic', $_POST[GOURLBB.'premium_topic']);
	
	        	return true;
			}
	
	
	
			/*
			 * 10
			*/
			private function save_settings()
			{
				foreach ($this->fields as $k => $v)
					foreach (array("free_", "premium_") as $type)
					{
						if (isset($_POST[$type.$k]) && in_array(intval($_POST[$type.$k]), array(0, 1)))
						{
							update_option(GOURLBB.$type.$k, intval($_POST[$type.$k]));
							$this->save_flag = true;
						}
					}
					
					update_option(GOURLBB."create_topics",  intval($_POST["create_topics"]));
					update_option(GOURLBB."create_premium", intval($_POST["create_premium"]));
					if (isset($_POST["checkout_page"])) update_option(GOURLBB."checkout_page",  intval($_POST["checkout_page"]));
				
				
				$arr = array_keys($this->texts);
				foreach ($arr as $k)
					if (isset($_POST[$k]))
					{
						update_option(GOURLBB.$k, trim(stripslashes($_POST[$k])));
						$this->save_flag = true;
					}
				
					
				return true;
			}
	
	
	
			/*
			 * 11
			*/
			private function get_settings()
			{
				global $gourl;
				
				foreach ($this->fields as $k => $v)
				{
					$v = get_option(GOURLBB."free_".$k);
					if (!in_array($v, array("0", "1"))) $v = 0;
					$this->free[$k] = intval($v); 
					
					$v = get_option(GOURLBB."premium_".$k);
					if ($v === false || !in_array($v, array("0", "1"))) $v = ($k == "read_topic") ? 0 : 1;
					$this->premium[$k] = intval($v); 
				} 
				
				if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl) && true === version_compare(GOURL_VERSION, '1.2.11', '>='))
				{
						$this->payments 			= $gourl->payments(); 		// Activated Payments
						$this->coin_names			= $gourl->coin_names(); 	// All Coins
						$this->languages			= $gourl->languages(); 		// All Languages
				}
				
				$this->create_topics  = intval(get_option(GOURLBB."create_topics"));
				$this->create_premium = intval(get_option(GOURLBB."create_premium"));
				$this->checkout_page  = intval(get_option(GOURLBB."checkout_page"));
				
				$arr = array_keys($this->texts);
				foreach ($arr as $k)
				{
					$v = get_option(GOURLBB.$k);
					if (is_string($v)) $v = trim(stripslashes($v));
				
					if ($v === false || (mb_strlen(trim($v)) < 3 && $k != "premium" && $k != "login_text")) $v = $this->def[$k];
					$this->texts[$k] = $v;
				}
				
				return true;
			}
			
	
			
			/*
			 * 12. Create checkout page
			*/
			private function recheck_checkout_page()
			{
				$create 	= true;
				$the_page 	= false;
				
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				if ($this->checkout_page) $the_page = get_post($this->checkout_page);
				if (!$the_page || stripos($the_page->post_content, '[gourl-membership-checkout') === false) $the_page = get_page_by_title("Membership");

				if ($the_page && stripos($the_page->post_content, '[gourl-membership-checkout') !== false)
				{
					$create = false;
					if ($the_page->post_status != 'publish') 
					{
						$the_page->post_status = 'publish';
						wp_update_post($the_page);
					}
					$this->checkout_page = $the_page->ID;
				}
				
				if ($create)
				{
					// Create post object
					$_p = array();
					$_p['post_title'] = "Membership";
					$_p['post_content'] = '[gourl-membership-checkout img="image1.png"]';
					$_p['post_status'] = 'publish';
					$_p['post_type'] = 'page';
					$_p['comment_status'] = 'closed';
					$_p['ping_status'] = 'closed';
					$_p['post_category'] = array(1); // the default 'Uncatrgorised'
						
					// Insert the post into the database
					$this->checkout_page = wp_insert_post( $_p );
				}

				update_option(GOURLBB."checkout_page", intval($this->checkout_page));
				
				return true;
			}	
				
	
	
			/*
			 * 13
			*/
			public function settings_page()
			{
				global $gourl;
				
				$this->recheck_checkout_page();
				
				$tmp  = "<div style='margin:30px 20px'>";
				$tmp .= "<form accept-charset='utf-8' action='".admin_url('edit.php?post_type=forum&page=bbpress_premium')."' method='post'>";
				
				$tmp .= "<h2>".__('BBPRESS Forum - Premium Settings', GOURLBB);
				$tmp .= "<div style='float:right; margin-top:-20px'><a href='https://gourl.io/' target='_blank'><img title='".__('Bitcoin Payment Gateway for Your Website', GOURLBB)."' src='".plugins_url('/images/gourl.png', __FILE__)."' border='0'></a></div>";
				$tmp .= "</h2>";
				
				if ($this->save_flag) $tmp .= "<br><div class='updated'><p>".__('Settings has been saved <strong>successfully</strong>', GOURLBB)."</p></div><br>";
					
				$tmp .= "<table class='widefat' cellspacing='20' style='padding:10px 25px;'>";
				$tmp .= "<tr><td>";
					
				
				// I
				$tmp .= "<table style='max-width:1000px;'>";
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'>";
				$tmp .= "<p>";
				$tmp .= sprintf(__('You can mark some topics on your bbPress Forum as <a href="%s">Premium</a> and can easily monetise your forum with <a href="https://gourl.io/#coins">Bitcoin / Altcoins</a>.<br>Pay to read bbPress <a href="%s">Premium Topics</a> and read comments/replies, Pay to add new replies to the topic, Pay to create new topics on bbPress', GOURLBB), plugins_url("/images/newtopic.png", __FILE__), plugins_url("/images/newtopic.png", __FILE__)).'.<br>';
				$tmp .= sprintf(__( 'Accept %s membership payments online in bbPress.', GOURLBB), ucwords(implode(", ", $this->coin_names))).'<br/>';
				$tmp .= "</p><p>";
				$tmp .= sprintf(__( '<a href="%s">Plugin Homepage</a> & <a href="%s">Screenshots &#187;</a>', GOURLBB), "https://gourl.io/bbpress-premium-membership.html", "https://gourl.io/bbpress-premium-membership.html#screenshot");
				$tmp .= "</p>";
				$tmp .= "</th>";
				$tmp .= "</tr>";
	
				if ($this->coin_names)
				{
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("Premium Users", GOURLBB ).":</b></th>";
					$tmp .= '<td><a class="gourlbblink" href="'.GOURL_ADMIN.GOURL.'paypermembership_users">'.__('List of All Premium Users', GOURLBB).'</a></td>';
					$tmp .= "</tr>";
					
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("New Subscription", GOURLBB ).":</b></th>";
					$tmp .= '<td>Manually create user &#160;<a class="gourlbblink" href="'.GOURL_ADMIN.GOURL.'paypermembership_user">'.__('Premium Membership', GOURLBB).'</a></td>';
					$tmp .= "</tr>";
					
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("Payments", GOURLBB ).":</b></th>";
					$tmp .= '<td>'.sprintf(__( 'All <a class="gourlbblink" href="%s">Membership Payments</a>', GOURLBB ), admin_url("admin.php?page=gourlpayments&s=membership")).'</td>';
					$tmp .= "</tr>";
						
					$s = $gourl->get_membership();
					$lock_level_membership = array("Registered Subscribers", "Registered Subscribers/Contributors", "Registered Subscribers/Contributors/Authors");
						
					if (!intval($s["ppmLevel"]))
					{
						$tmp .= "<tr valign='top'>";
						$tmp .= "<th colspan='2'>";
						$tmp .= '<div class="error"><h3 style="color:red">'.__("Important", GOURLBB ).' -</h3><p>' .sprintf(__( 'Please change settings to <b>Lock Page Level</b>: "<a href="%s">Registered Subscribers/Contributors</a>" or higher. <a href="%s">Goto &#187;</a>', GOURLBB ), GOURL_ADMIN.GOURL."paypermembership#gourlform", GOURL_ADMIN.GOURL."paypermembership#gourlform").'</p></div><br><br>';
						$tmp .= "</th>";
						$tmp .= "</tr>";
					}
					
				}
				else
				{
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th colspan='2'>";
					$tmp .= "<h3 style='color:green'>".sprintf(__("Please install <a target='_blank' href='%s'>Bitcoin</a> Gateway", GOURLBB ), "https://bitcoin.org/")." -</h3>";
					$tmp .= '<div class="error">' .sprintf(__( '<p><b>You need to install GoUrl Official Bitcoin Gateway for Wordpress also. &#160; Go to - &#160;<a href="%s">Automatic installation</a> &#160;or&#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Manual</a></b>.</p><p>Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Main Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a></p>', GOURLBB ), admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads")).'</div><br><br>';
					$tmp .= "</th>";
					$tmp .= "</tr>";
				}
				
				
				
				// II
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><br><h3 class='title'>".__('New Topics in bbPress', GOURLBB )."</h3></th>";
				$tmp .= "</tr>";
					
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th scope='row'><b>".__( "Who can Create Topics", GOURLBB ).":</b></th>";
				$tmp .= "<td><input type='radio' name='create_topics' value='0'".$this->chk($this->create_topics, 0).">".__('All users', GOURLBB )." &#160; &#160; &#160; &#160; &#160; <input type='radio' name='create_topics' value='1'".$this->chk($this->create_topics, 1).">".__('Premium Users only', GOURLBB );
				$tmp .= "</tr>";
				
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th scope='row'><b>".__( "Default New Topic", GOURLBB ).":</b></th>";
				$tmp .= "<td><input type='radio' name='create_premium' value='0'".$this->chk($this->create_premium, 0).">".__('Non-Premium', GOURLBB )." &#160; &#160; &#160; &#160; &#160; <input type='radio' name='create_premium' value='1'".$this->chk($this->create_premium, 1).">".__('Premium', GOURLBB );
				$tmp .= "</tr>";
				
				
				// III
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><br><h3 class='title'>".__('Non-premium Topics in bbPress', GOURLBB )."</h3></th>";
				$tmp .= "</tr>";
					
				foreach ($this->fields as $k => $v)
				{
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__( $v, GOURLBB ).":</b></th>";
					$tmp .= "<td><input type='radio' name='free_".$k."' value='0'".$this->chk($this->free[$k], 0).">".__('All users', GOURLBB )." &#160; &#160; &#160; &#160; &#160; <input type='radio' name='free_".$k."' value='1'".$this->chk($this->free[$k], 1).">".__('Premium Users only', GOURLBB );
					$tmp .= "</tr>";
				}
				
				// IV
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><br><h3 class='title'><img src='".plugins_url("/images/premium.png", __FILE__)."' width='16' height='16'> &#160;".__('Premium Topics in bbPress', GOURLBB )."</h3></th>";
				$tmp .= "</tr>";
				
				foreach ($this->fields as $k => $v)
				{
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__( $v, GOURLBB ).":</b></th>";
					$tmp .= "<td><input type='radio' name='premium_".$k."' value='0'".$this->chk($this->premium[$k], 0).">".__('All users', GOURLBB )." &#160; &#160; &#160; &#160; &#160; <input type='radio' name='premium_".$k."' value='1'".$this->chk($this->premium[$k], 1).">".__('Premium Users only', GOURLBB );
					$tmp .= "</tr>";
				}
					
				
				if ($this->coin_names)
				{
					// V
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th colspan='2'><br><br><br><h3 class='title'>".__('Page', GOURLBB )."</h3></th>";
					$tmp .= "</tr>";
					
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__( "Payment/Checkout Page", GOURLBB ).":</b></th>";
					$tmp .= "<td>";
					$tmp .= wp_dropdown_pages(array(
												"name" 				=> "checkout_page", 
												"show_option_none"	=> __( "-- Choose One --", GOURLBB ),
												"selected"			=>	$this->checkout_page, 
												"echo"				=> false 
												));
					if($this->checkout_page) 
							$tmp .= '<a target="_blank" href="post.php?post='.$this->checkout_page.'&action=edit" class="button button-secondary">'.__( "edit page", GOURLBB ).'</a> &#160; '.
									'<a target="_blank" href="'.get_permalink($this->checkout_page).'" class="button button-secondary">'.__( "view page", GOURLBB ).'</a>';
					
					$tmp .= '<br><small>'.__( "Include the shortcode", GOURLBB ).' &#160; [gourl-membership-checkout ...]</small>';
					$tmp .= "</td>";
					$tmp .= "</tr>";
						
					// VI
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th colspan='2'><br><br><h3 class='title'>".__('Payment Box', GOURLBB )."</h3></th>";
					$tmp .= "</tr>";
						
					
					if ($s["ppmPrice"] > 0) $price = $s["ppmPrice"] . " USD " . __('per', GOURLBB ) . " " . $s["ppmExpiry"];   
					else					$price = $s["ppmPriceCoin"] . " " . $s["ppmPriceLabel"] . " " . __('per', GOURLBB ) . " " . $s["ppmExpiry"];
				
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("Price", GOURLBB ).":</b></th>";
					$tmp .= "<td><a class='gourlbblink' href='".GOURL_ADMIN.GOURL."paypermembership#gourlform'>" . $price . "</a></td>";
					$tmp .= "</tr>";
					
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("Upgrade Membership For", GOURLBB ).":</b></th>";
					$tmp .= "<td>".__("Users with following permissions/roles require premium membership -<br>Forum Roles: &#160; Spectator and Participant<br>with Website Roles", GOURLBB ).": &#160; <a class='gourlbblink' href='".GOURL_ADMIN.GOURL."paypermembership#gourlform'>".$lock_level_membership[$s["ppmLevel"]]."</a></td>";
					$tmp .= "</tr>";
					
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__("Payment Box Style", GOURLBB ).":</b></th>";
					$tmp .= '<td>'.sprintf(__( '<a target="_blank" href="%s">Sizes</a> and border <a target="_blank" href="%s">shadow</a> you can change <a class="gourlbblink" href="%s">here</a>', GOURLBB ), plugins_url("/images/sizes.png", __FILE__), plugins_url("/images/styles.png", __FILE__), GOURL_ADMIN.GOURL."settings#gourlvericoinprivate_key").'</td>';
					$tmp .= "</tr>";
				}	
				
				// VII
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><br><br><h3 class='title'>".__('Customize Texts', GOURLBB )."</h3></th>";
				$tmp .= "</tr>";
				
				
				
				foreach ($this->texts2 as $k => $v)
				{
					$tmp .= "<tr valign='top'>";
					$tmp .= "<th scope='row'><b>".__($v, GOURLBB ).":</b></th>";
					if (strpos($k, "create_") === 0) $tmp .= '<td style="max-width:600px"><textarea name="'.$k.'" id="'.$k.'" class="widefat" style="height:80px;">'.htmlspecialchars($this->texts[$k], ENT_QUOTES).'</textarea>';
					else $tmp .= '<td><input type="text" name="'.$k.'" id="'.$k.'" value="'.htmlspecialchars($this->texts[$k], ENT_QUOTES).'" class="widefat">';
					$tmp .= '<p>'.htmlspecialchars(__('Default: '.$this->def[$k], GOURLBB), ENT_QUOTES).'</p></td>';
					$tmp .= "</tr>";
						
				}		
						
				
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><br><br>";
				$tmp .= "<input type='submit' class='button button-primary' name='submit' value='".__('Save Settings', GOURLBB)."'> &#160; &#160; &#160; ";
				$tmp .= "<br><br></th>";
				$tmp .= "</tr>";
				$tmp .= "</table>";
					
				
				$tmp .= "</td></tr>";
				$tmp .= "</table>";
				$tmp .= "</form>";
				$tmp .= "</div>";
					
				echo $tmp;
				
				return true;
			}
	
	
	
			/*
			 * 14
			*/
			public function admin_footer_text()
			{
				return sprintf( __( 'If you like <strong>bbPress Forum - Premium Membership Mode</strong> please leave us a <a href="%1$s" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a> rating on <a href="%1$s" target="_blank">WordPress.org</a>. A huge thank you from GoUrl.io in advance!', GOURLBB ), 'https://wordpress.org/support/view/plugin-reviews/gourl-bbpress-premium-membership-bitcoin-payments?filter=5#postform');
			}
	
	
			
			/*
			 * 15
			*/
			private function admin_forum_roles()
			{
				// Forum Roles: Spectator and Participant only
				if (is_user_logged_in() && !in_array(bbp_get_user_role(get_current_user_id()), array('', 'bbp_spectator', 'bbp_participant'))) return true;
				else return false;
			}
			
			
				
			/* 16. Action 1a */
			public function action_create_reply_start() 
			{
				global $post, $gourl;
	
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				// if Admin/Moderator on Forum
				if ($this->admin_forum_roles()) return true;
				
				// Premium topic or not
				$prem_topic = $this->is_premium_topic($post->ID);
				
				// Need user upgrade membership or not
				$upgrade = $gourl->is_need_membership_upgrade();
					
				// Only premium users can post reply
				if ($upgrade && (($prem_topic && $this->premium["create_reply"]) || (!$prem_topic && $this->free["create_reply"])))
				{
					$this->reply_box = true;
					ob_end_flush();
					ob_start();
				}
				
				
				return true;			
			}
			
			
			
			/* 17. Action 1b */
			public function action_create_reply_end()
			{

				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				// if Admin/Moderator on Forum
				if ($this->admin_forum_roles()) return true;
								
				// if not upgrade payment box needed
				if (!$this->reply_box) return true;
				
				// Delete Post Form
				ob_end_clean();
				
				echo "</form>";
				
				// Not logged user
				if (is_user_logged_in())
				{
					$url = get_permalink($this->checkout_page);
					echo "<div align='right'><button onclick='javascript:location.href=\"".$url."\"' class='button'>".$this->texts["btn_reply"]."</button></div><br><br>";
					echo "<div class='bbp-template-notice'><p>".$this->texts["create_reply2"]."</p><p><a href='".$url."'>".$this->texts["join_text"]." &#187;</a></p></div>";
				}
				else echo "<div class='bbp-template-notice'><p>".$this->texts["create_reply"]." &#160; ".($this->texts["login_text"]?"<a href='".wp_login_url()."'>".$this->texts["login_text"]."</a></p>":"")."</div>";
				
				
				
				return true;			
			}
			
			
			
			
			/* 18. Action 2a */
			public function action_create_topic_start() 
			{
				global $gourl;
	
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				// if Admin/Moderator on Forum
				if ($this->admin_forum_roles()) return true;
				
				// Need user upgrade membership or not
				$upgrade = $gourl->is_need_membership_upgrade();
				
				// Only premium users can post reply
				if ($upgrade && $this->create_topics)
				{
					$this->topic_box = true;
					ob_end_flush();
					ob_start();
				}
				
				
				return true;			
			}
			
			
			
			/* 19. Action 2b */
			public function action_create_topic_end()
			{
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				// if Admin/Moderator on Forum
				if ($this->admin_forum_roles()) return true;
								
				// if not upgrade payment box needed
				if (!$this->topic_box) return true;
				
				// Delete Post Form
				ob_end_clean();
				
				echo "</form>";

				// Not logged user
				if (is_user_logged_in()) 
				{	
					$url = get_permalink($this->checkout_page);
					echo "<div align='right'><button onclick='javascript:location.href=\"".$url."\"' class='button'>".$this->texts["btn_topic"]."</button></div><br><br>";
					echo "<div class='bbp-template-notice'><p>".$this->texts["create_topic2"]."</p><p><a href='".$url."'>".$this->texts["join_text"]." &#187;</a></p></div>";
				}
				else echo "<div class='bbp-template-notice'><p>".$this->texts["create_topic"]." &#160; ".($this->texts["login_text"]?"<a href='".wp_login_url()."'>".$this->texts["login_text"]."</a></p>":"")."</div>";
				
				return true;			
			}
						
			
			
			/* 20. Action 2c */
			public function action_create_new_topic ($post_id)
			{
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return true;
				
				if (!$this->create_premium) return true;
				
				// Premium Topic by default
				update_post_meta($post_id, GOURLBB.'premium_topic', 1);
				
				return true;
			}
			
			
			
			/* 21. Action 3 */
			public function action_read_topics ($content, $post_id)
			{
				global $gourl;
				
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return $content;
				
				// if Admin/Moderator on Forum
				if ($this->admin_forum_roles()) return $content;
								
				// 1. Topic ID
				$id = bbp_get_reply_topic_id();
				
				// Not valid topic id
				if (!intval($id)) return $content;
				
				// 2. Need user upgrade membership or not
				$upgrade = $gourl->is_need_membership_upgrade();
				
				// Don't need upgrade 
				if (!$upgrade) return $content;
				
				// 3. Logged user == reply owner ?
				$p  = get_post( $post_id );
				if (is_user_logged_in() && $p->post_author == get_current_user_id()) return $content;
				
				// 4. Premium topic or not
				$prem_topic = $this->is_premium_topic($id);
				
				// 5. Only premium users can read first posts or replies
				if (($post_id == $id && (($prem_topic && $this->premium["read_topic"]) || (!$prem_topic && $this->free["read_topic"]))) ||
					($post_id != $id && (($prem_topic && $this->premium["read_reply"]) || (!$prem_topic && $this->free["read_reply"]))))
				{
					
					if (!is_user_logged_in())
					{ 
						$text = ($post_id == $id) ? $this->texts["read_topic"] : $this->texts["read_reply"];
						if ($this->texts["login_text"]) $text .= "&#160; <a href='".wp_login_url()."'>".$this->texts["login_text"]."</a>";
					}
					else 
					{
						$text = ($post_id == $id) ? $this->texts["read_topic2"] : $this->texts["read_reply2"];
						if ($this->texts["join_text"]) $text .= "&#160; <a href='".get_permalink($this->checkout_page)."'>".$this->texts["join_text"]."</a>";
					}
					
					$content = "<div style='clear:none;width:300px' class='bbp-template-notice'><p>".$text."</p></div>";
				}
				
				return $content;
					
			}
			
			
			
			/* 22. Action 4 */
			public function action_premium_title ($title, $id)
			{
				// Need to install main GoUrl Payment Gateway
				if (!$this->coin_names) return $title;
				
				// Not valid topic id
				if (!intval($id)) return $title;
				
				// No text
				if (!$this->texts["premium"]) return $title;

				// Premium topic or not
				$prem_topic = $this->is_premium_topic($id);
				
				if ($prem_topic) $title = $this->texts["premium"] . " " . $title;
				
				return $title;
			}
			
			
			
	
			/*
			 * 23
			*/
			public function right($str, $findme, $firstpos = true)
			{
				$pos = ($firstpos)? mb_stripos($str, $findme) : mb_strripos($str, $findme);
			
				if ($pos === false) return $str;
				else return mb_substr($str, $pos + mb_strlen($findme));
			}
	
	
	
			/*
			 * 24
			*/
			private function chk($val1, $val2)
			{
				$tmp = (strval($val1) == strval($val2)) ? ' checked="checked"' : '';
			
				return $tmp;
			}
				
		}
		// end class                             
		
		if (class_exists('bbPress')) new GoUrl_Bbpress;
	
	}
	// end gourl_bbp_gateway_load()     
	
}
