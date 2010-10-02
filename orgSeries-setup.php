<?php

/**
* This file contains the orgSeries class which initializes the plugin and provides global variables/methods for use in the rest of the plugin.
*
* @package Organize Series
* @since 2.2
*/

if ( !class_exists('orgSeries') ) {

class orgSeries {

	var $settings;
	var $version = '2.2';
	var $org_domain = 'organize-series';
	
	//__constructor
	function orgSeries() {
		global $wp_version;
		
		// WordPress version check
		if ( version_compare($wp_version, '3.0', '<'))
			add_action('admin_notices', array($this, 'update_warning'));
			
		//install OrgSeries
		add_action('activate_'.SERIES_DIR.'/orgSeries.php', array($this, 'org_series_install'));
		
		//all other actions and filters...
		add_action('plugins_loaded', array($this, 'add_settings'));
		add_action('init', array($this, 'register_textdomain'));
		add_action('init', array($this, 'register_taxonomy'),0);
		add_action('init', array($this, 'register_scripts'));
		add_action('generate_rewrite_rules', array($this,'seriestoc_rewrite_rules'));
		//add_action('init', array($this, 'rewrite_rules'));
		add_action('parse_query', array($this, 'seriestoc_parsequery'));
		add_action('query_vars', array($this,'orgSeries_toc_request'));
		add_action('template_redirect', array($this,'orgSeries_toc_template')); //setsup the seriestoc url
					
		add_action('wp_head', array($this, 'orgSeries_header'));
		add_action( 'wp_footer', array($this, 'series_dropdown_js'), 1 );
		
		//series post list box
		add_action('the_content', array($this, 'add_series_post_list_box'));
		
		//series meta strip
		add_filter('the_content', array($this, 'add_series_meta'));
		add_filter('get_the_excerpt', array($this, 'orgseries_trim_excerpt'),1);
		add_filter('the_excerpt', array($this, 'add_series_meta_excerpt'));
		
		//joins, wheres, sortbys
		add_filter('posts_join_paged', array($this, 'sort_series_page_join'));
		add_filter('posts_where', array($this,'sort_series_page_where'));
		add_filter('posts_orderby', array($this,'sort_series_page_orderby'));
				
		//series post-navigation
		add_action('the_content', array($this, 'series_nav_filter'));
		
		//broswer page title
		add_filter('wp_title', array($this, 'add_series_wp_title'));
		
		//settings link on plugin page
		add_filter('plugin_action_links', array($this, 'AddPluginActionLink'), 10, 2);
	}
	
	function update_warning() {
		$msg = '<div id="wpp-message" class="error fade"><p>'.__('Your WordPress version is too old. Organize Series 2.2 requires at least WordPress 3.0 to function correctly. Please update your blog via Tools &gt; Upgrade.', $this->org_domain).'</p></div>';
		echo trim($msg);
	}
	
	function org_series_install() {
		global $wpdb, $wp_rewrite;
		$this->orgSeries_roles();
		
		if ( $oldversion = get_option( 'org_series_version' ) )  //register the current version of orgSeries
		update_option( 'org_series_oldversion', $this->version );
	else
		add_option("org_series_version", $this->version);
		
	//create table for series icons
	$table_name = $wpdb->prefix . "orgSeriesIcons";
	if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$sql = "CREATE TABLE " . $table_name . " (
			term_id INT NOT NULL,
			icon VARCHAR(100) NOT NULL,
			PRIMARY KEY term_id (term_id)
		);";
		require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );
		dbDelta( $sql );
	}
	
	add_option( 'series_icon_path', '' );
	add_option( 'series_icon_url', '' );
	add_option( 'series_icon_filetypes', 'jpg gif jpeg png' );
	}
	
	function register_scripts() {
		global $wp_rewrite;
		$url = WP_PLUGIN_URL.'/'.SERIES_DIR.'/js/';
		wp_register_script('inline-edit-series',$url.'inline-series.js');  
		wp_register_script( 'ajaxseries', $url.'series.js', array('wp-lists'), '20080310' );
		wp_localize_script( 'ajaxseries', 'seriesL10n', array(
				'add' => attribute_escape(__('Add', $this->org_domain)),
				'how' => __('Select "Not part of a series" to remove any series data from post', $this->org_domain)
			));
		wp_register_script( 'orgseries_options', $url.'orgseries_options.js', array('jquery', 'thickbox'));
		$wp_rewrite->flush_rules();
	}
	
	function orgSeries_roles() {
		global $wp_roles;
		$roles = array('administrator', 'editor');
		$capability = 'manage_series';
		
		foreach ($roles as $role) {
			$wp_roles->add_cap($role, $capability, true);
		}
		return true;
	}
	
	function register_textdomain() {
		$dir = SERIES_PATH.'lang';
		load_plugin_textdomain($this->org_domain, $dir);
	}
	
	function register_taxonomy() {
		$permalink_slug = $this->settings['series_custom_base'];
		$taxonomy = 'series';
		$object_type = array('post');
		$capabilities = array(
			'manage_terms' => 'manage_series',
			'edit_terms' => 'manage_series',
			'delete_terms' => 'manage_series',
			'assign_terms' => 'manage_series'
			);			
		$labels = array(
			'name' => _x('Manage Series', 'taxonomy general name', $this->org_domain),
			'singular_name' => _x('Series', 'taxonomy singular name', $this->org_domain),
			'search_items' => __('Search Series', $this->org_domain),
			'popular_items' => __('Popular Series', $this->org_domain),
			'all_items' => __('All Series', $this->org_domain),
			'edit_item' => __('Edit Series', $this->org_domain),
			'update_item' => __('Update Series', $this->org_domain),
			'add_new_item' => __('Add New Series', $this->org_domain),
			'new_item_name' => __('New Series Name', $this->org_domain)
			);
		$args = array(
			'update_count_callback' => '_update_post_term_count',
			'labels' => $labels,
			'rewrite' => array( 'slug' => $permalink_slug, 'with_front' => true ),
			'show_ui' => true,
			'capabilities' => $capabilities,
			'query_var' => $taxonomy,
			);
		register_taxonomy( $taxonomy, $object_type, $args );
	}

	function add_settings($reset = false) {
		$url = parse_url(get_bloginfo('siteurl'));
		if ( !($this->settings = get_option('org_series_options')) || $reset == true ) {
			$this->settings = array(
				//main settings
			'custom_css' => 1, 
			'kill_on_delete' => 0, //determines if all series information (including series-icon tables) will be deleted when the plugin is deleted using the delete link on the plugins page.
			'auto_tag_toggle' => 1, //sets the auto-tag insertions for the post-list box for posts that are part of series.
			'auto_tag_nav_toggle' => 1, //sets the auto-tag insertions for the series navigation strip.
			'auto_tag_seriesmeta_toggle' => 1, //sets the auto-tag insertions for the series-meta information in posts that are part of a series.
			'series_toc_url' => $url['path'] . '/series/',
			'series_custom_base' => 'series',
			'series_toc_title' => __('Series Table of Contents',$this->org_domain),
		//new template options
			'series_post_list_template' => '<div class="seriesbox"><div class="center">%series_icon_linked%<br />%series_title_linked%</div><ul class="serieslist-ul">%post_title_list%</ul></div>%postcontent%',
			'series_post_list_post_template' => '<li class="serieslist-li">%post_title_linked%</li>',
			'series_post_list_currentpost_template' => '<li class="serieslist-li-current">%post_title%</li>',
			'series_meta_template' => '<div class="seriesmeta">' . _c('This entry is part %series_part% of %total_posts_in_series% in the series |leave the %tokens% as is when translating',$this->org_domain) . '%series_title_linked%</div>%postcontent%',
			'series_meta_excerpt_template' => '<div class="seriesmeta">' ._c('This entry is part %series_part% of %total_posts_in_series% in the series |leave the %tokens% as is when translating',$this->org_domain) . '%series_title_linked%</div>%postcontent%',
			'series_table_of_contents_box_template' => '<div class="serieslist-box"><div class="imgset">%series_icon_linked%</div><div class="serieslist-content"><h2>%series_title_linked%</h2><p>%series_description%</p></div><hr style="clear: left; border: none" /></div>',
			'latest_series_before_template' => '<div class="latest-series"><ul>',
			'latest_series_inner_template' => '<li>%series_title_linked%</li>',
			'latest_series_after_template' => '</ul></div>',
			'series_post_nav_template' => '%postcontent%<fieldset><legend>'. __('Series Navigation',$this->org_domain) .'</legend><span class="series-nav-left">%previous_post%</span><span class="series-nav-right">%next_post%</span></fieldset>',
			'series_nextpost_nav_custom_text' => $series_nextpost_nav_custom_text,
			'series_prevpost_nav_custom_text' => $series_prevpost_nav_custom_text,
			//series_icon related settings
			'series_icon_width_series_page' => 200,
			'series_icon_width_post_page' =>100,
			'series_icon_width_latest_series' =>100,
			//series posts order options
			'series_posts_orderby' => 'meta_value',
			'series_posts_order' => 'ASC'
			);
			
			$this->settings = apply_filters('org_series_settings', $this->settings);
			update_option('org_series_options', $this->settings);
			return true;
		}
		
		return false;
	}
	
	function seriestoc_rewrite_rules( $wp_rewrite ) {
		if (isset($wp_rewrite) && $wp_rewrite->using_permalinks()) {
			define('SERIES_REWRITEON', '1');  //pretty permalinks please!
		} else {
			define('SERIES_REWRITEON', '0');  //old school links
		}
		$url = parse_url(get_bloginfo('siteurl'));
		$url = $url['path'] . '/';
		$settings = $this->settings;
		$custom_base = substr($settings['series_toc_url'], strlen($url));
		$new_rules = array( 
			$custom_base => 'index.php?'.$custom_base.'=1' 
			);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
		
	}
	
	function seriestoc_parsequery() {
		global $wp_query;
		$url = parse_url(get_bloginfo('siteurl'));
		$url = $url['path'] . '/';
		$settings = $this->settings;
		$custom_base = substr($settings['series_toc_url'], strlen($url));
		if (isset($wp_query->query_vars[$custom_base])) {
			$wp_query->is_single = false;
			$wp_query->is_category = false;
			$wp_query->is_tax = false;
			$wp_query->is_tag = false;
			$wp_query->is_search = false;
			$wp_query->is_front = false;
			$wp_query->is_posts_page = false;
			$wp_query->is_feed = false;
			$wp_query->is_month = false;
			$wp_query->is_author = false;
			$wp_query->is_page = false;
			$wp_query->is_archive = false;
			$wp_query->is_search = false;
			$wp_query->is_home = false;
			$wp_query->is_series = false;
			$wp_query->is_post = false;
			$wp_query->is_seriestoc = true;
			$wp_query->is_404 = false;
		}
	}
	
	function orgSeries_toc_request($qvs) {
		$url = parse_url(get_bloginfo('siteurl'));
		$url = $url['path'] . '/';
		$settings = $this->settings;
		$custom_base = substr($settings['series_toc_url'], strlen($url));
		$qvs[] = $custom_base;
		return $qvs;
	}
		
	function orgSeries_toc_template() {
		global $wp_query;
		if ( $wp_query->is_seriestoc ) {
			if (file_exists(TEMPLATEPATH . '/seriestoc.php')) {
				$template =  TEMPLATEPATH . '/seriestoc.php';
			} else {
				$template = WP_CONTENT_DIR . '/plugins/' . SERIES_DIR .'/seriestoc.php';
			}
					
			function seriestoc_title( $title ) {
				$seriestoc_title = $settings['series_toc_title'];
				if ( $seriestoc_title == '' ) $seriestoc_title = __('Series Table of Contents');
				$title = $seriestoc_title . ' &laquo; ' . $title;
				return $title;
			}
		
			add_filter('wp_title', 'seriestoc_title');
			if ($template) {
				include($template);
				exit;
			}		
		}
}
	
	//orgSeries dropdown nav js
	function series_dropdown_js() {
		if ( SERIES_REWRITEON == 0 ) {
			?>
			<script type='text/javascript'><!--
			var seriesdropdown = document.getElementById("orgseries_dropdown");
			if (seriesdropdown) {
				function onSeriesChange() {
					if ( seriesdropdown.options[seriesdropdown.selectedIndex].value != ( 0 || -1 ) ) {
						location.href = "<?php echo get_option('home'); ?>/?series="+seriesdropdown.options[seriesdropdown.selectedIndex].value;
					}
				}
				seriesdropdown.onchange = onSeriesChange;
			}
			--></script>
				<?php
			} else {
				?>
				<script type='text/javascript'><!--
			var seriesdropdown = document.getElementById("orgseries_dropdown");
			if (seriesdropdown) { 
			 function onSeriesChange() {
					if ( seriesdropdown.options[seriesdropdown.selectedIndex].value != ( 0 || -1 ) ) {
						location.href = "<?php echo get_option('home'); ?>/series/"+seriesdropdown.options[seriesdropdown.selectedIndex].value;
					}
				}
				seriesdropdown.onchange = onSeriesChange;
			}
			--></script>
			<?php
		}
	}
	
	//joins and wheres etc.
	
	function sort_series_page_join($join) {
		global $wpdb;
	if (!is_series() || ( is_series() && is_feed() ) ) return $join;
		$join .= "LEFT JOIN $wpdb->postmeta ON($wpdb->posts.ID = $wpdb->postmeta.post_id) ";
		return $join;
	}

	function sort_series_page_where($where) {
		global $wpdb;
		if (!is_series() || ( is_series() && is_feed() ) ) return $where;
		$part_key = SERIES_PART_KEY;
		$where .= " AND $wpdb->postmeta.meta_key = '$part_key' ";
		return $where;
	}

	function sort_series_page_orderby($ordering) {
		if (!is_series() || ( is_series() && is_feed() ) ) return $ordering;
		$settings = $this->settings;
		$orderby = $settings['series_posts_orderby'];
		if ( $orderby == 'meta_value' )
			$orderby = $orderby . '+ 0';
		$order = $settings['series_posts_order'];
		if (!isset($orderby)) $orderby = "post_date";
		if (!isset($order)) $order = "DESC";
		$ordering = " $orderby $order ";
		return $ordering;	
	}
	
	// Add .css to header if enabled via options
	function orgSeries_header() {
		$plugin_path = SERIES_LOC;
		if ($this->settings['custom_css']) {
			$csspath = $plugin_path.'orgSeries.css';
			$text = '<link rel="stylesheet" href="' . $csspath . '" type="text/css" media="screen" />';
		} else {
			$text = '';
		}
		echo $text;
	}
	
	//add series post-list box to a post in that series (on single.php view)
	function add_series_post_list_box($content) {
		if ($this->settings['auto_tag_toggle']) {
			if (is_single() && $postlist = wp_postlist_display() ) {
				$addcontent = $content;
				$content = str_replace('%postcontent%', $addcontent, $postlist);
			}
		}
		return $content;
	}
	
	//add series meta information to posts that belong to a series.
	function add_series_meta($content) {
		if($this->settings['auto_tag_seriesmeta_toggle']) {
			if ($series_meta = wp_seriesmeta_write()) {
				$addcontent = $content;
				$content = str_replace('%postcontent%', $addcontent, $series_meta);
			}
		}
		return $content;
	}
	
	function orgseries_trim_excerpt($content) {
		remove_filter('the_content',array($this,'add_series_meta'));
		return $content;
	}
	
	function add_series_meta_excerpt($content) {
		if ( is_single() ) return;
		if($this->settings['auto_tag_seriesmeta_toggle']) {
			if ($series_meta = wp_seriesmeta_write(true)) {
				$addcontent = $content;
				$content = str_replace('%postcontent%', $addcontent, $series_meta);
			}
		}
		return $content;
	}	
	
	//add series navigation strip to posts taht are part of a series (on single.php view)
	function series_nav_filter($content) {
		if (is_single()) {
			if($this->settings['auto_tag_nav_toggle'] && $series_nav = wp_assemble_series_nav() ) {
				$addcontent = $content;
				$content = str_replace('%postcontent%', $addcontent, $series_nav);
			}
		}
		return $content;
	}
	
	//add series information to browser title info
	function add_series_wp_title( $title ) {
		$series = single_series_title('', false);
		
		if ( !empty($series) ) {
			if ( !is_feed() )
				$title = __('Series: ',$this->org_domain) . $series . ' &laquo; ' . $title;
			else
				$title = __('Posts from the series: ',$this->org_domain) . $series . ' ('. get_bloginfo().')';
		}
		return $title;
	}
	
	//ADD in link to settings on manage plugins page.
	function AddPluginActionLink( $links, $file ) {
		static $this_plugin;
		
		if( empty($this_plugin) ) $this_plugin = plugin_basename(__FILE__);

		if ( $file == $this_plugin ) {
			$settings_link = '<a href="' . admin_url( 'options-general.php?page='.SERIES_DIR.'/orgSeries-options.php' ) . '">' . __('Settings', $this->org_domain) . '</a>';
			array_unshift( $links, $settings_link );
		}

		return $links;
	}
	
} //end of orgSeries class

$orgseries = new orgSeries();

} //end of class check
?>