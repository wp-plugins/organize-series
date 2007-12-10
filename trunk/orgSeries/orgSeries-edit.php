<?php
//This file contains the code for the Management Controls for OrgSeries (i.e. on the write-post screen)
function get_series_list( $default = 0 ) { //copied from get_nested_categories in template.php
	global $post, $postdata, $mode, $wpdb, $checked_series, $content;
	$post_ID = isset($post) ? $post->ID : $postdata->ID;
	
	if ( empty($checked_series) ) {
		if ( $post_ID ) {
			$checked_series = wp_get_post_series($post_ID);
			
			if ( count( $checked_series ) == 0 ) {
				// No selected series, strange...and yes I even copied this text!!
			$checked_series[] = $default;
			}
		} else {
			$checked_series[] = $default;
		}
		
	$series = get_series("hide_empty=0&fields=ids");
	
	$result = array ();
	
	if ( is_array( $series ) ) {
		foreach ($series as $serial) {
			$result[$serial]['series_ID'] = $serial;
			$result[$serial]['checked'] = in_array( $serial, $checked_series );
			$result[$serial]['ser_name'] = get_the_series_by_ID( $serial );
		}
	}
	
	$result = apply_filters('get_series_list', $result);
	usort( $result, '_usort_terms_by_name' );
	
	return $result;
}
}

function write_series_list( $series ) { //copied from write_nested_categories in template.php
		echo '<li id="series-0"><label for ="in-series-0" class="selectit"><input value="0" type="radio" name="post_series" id="in-series-0" checked=checked>Not part of a series</label></li>';
		foreach ( $series as $serial ) {
			echo '<li id="series-', $serial['series_ID'],'"><label for="in-series-', $serial['series_ID'], '" class="selectit"><input value="', $serial['series_ID'], '" type="radio" name="post_series" id="in-series-', $serial['series_ID'], '"', ($serial['checked'] ? ' checked="checked"' : '' ), '/> ' , wp_specialchars( $serial['ser_name'] ), "</label></li>";
			
		}
}

function get_series_to_select( $default = 0 ) {//This will call up a list of existing series and  have the series that the post belongs to already selected. This is going to be tricky because at this point I don't want it possible for a post to belong to more than one series...we'll have to work in that check somehow...
	write_series_list( get_series_list( $default) );
}

add_action('dbx_post_sidebar', 'series_edit_box');
function series_edit_box() {
?>
	<fieldset id="seriesdiv" class="dbx-box">
		<h3 class="dbx-handle"><?php _e("Organize Series") ?></h3>
		<div class="dbx-content">
			<p id="jaxseries"></p>
			<?php _e("Manage the addition of this post to a series here") ?><br />
			<ul id="serieschecklist">	<?php get_series_to_select(); ?></ul>
		
		<p id="jax-posts-in-series"></p> <?php /* place holder for calling up the other posts in a series that is selected...ajaxified */ ?> 
		</div>
	</fieldset>
	<?php
}
	
//TODO add in custom-field box for determining the order that this post will be in the series...this will be tricky because I will have to have default settings for append and prepend.  Perhapse there should just be a 'dropdown' selection box?  But then that would have to be "ajaxified" to cull the numbers as selections from the results of the posts in series list.  Also this should only display as an option IF there is more than one post in a series...


//TODO add a function/ajaxified code  for calling up the other posts in the selected series. and have a box for choosing the order of the current post.
