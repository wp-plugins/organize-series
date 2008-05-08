<?php
##SERIES-ICON RELATED STUFF
#much of this code has added/modified from the Category-Icon plugin by Ivan Georgiev (GNU-GPL v2) [http://devcorner.georgievi.net/wp-plugins/wp-category-icons/].

function default_seriesicons_upload() {
	$def_path = str_replace(ABSPATH, '', get_option('upload_path'));
	$def_url = trailingslashit(get_bloginfo('wpurl')) . $def_path;
	return array($def_path, $def_url);
}

/**
* Get series icons from database
* @param int $series Series ID
* @return icon url
*/
function series_get_icons($series) {
	global $wpdb;
	$tablename = $wpdb->prefix . 'orgSeriesIcons';
	$series = $wpdb->escape($series);
	if ($row = $wpdb->get_row("SELECT icon FROM $tablename WHERE term_id='$series'")) {
		return $row->icon;
	} else return false;
}

/**
* Get series path and url (next two functions)
* function seriesicons_path
	@return path of series icons
* function seriesicons_url
	@return path of series urls
*/

function seriesicons_path() {
	$def = default_seriesicons_upload();
	return ABSPATH . $def[0];
}

function seriesicons_url() {
	$def = default_seriesicons_upload();
	return $def[1];
}

/**
* Utility function to compute a rectangle to fit a given rectangle by maintaining the aspect ration.
* @return array containing computed height and width
*/
function series_fit_rect($width, $height, $max_width=-1, $max_height=-1, $expand=false) {
	$h = $height;
	$w = $width;
	if ($max_width>0 && ($w > $max_width || $expand)) {
		$w = $max_width;
		$h = floor(($w*$height)/$width);
	}
	if ($max_height>0 && $h>$max_height) {
		$h = $max_height;
		$w = floor(($h*$width)/$height);
	}
	return array($w,$h);
}

/**
* Utility function to take in a referenced variable and sanitize the contents.  First seen in the category-icons plugin by Ivan Georgiev (ivan@georgievi.net)
*/
if (!function_exists('stripslaghes_gpc_arr')) {
function stripslaghes_gpc_arr(&$arr) {
	if (get_magic_quotes_gpc()) {
		foreach(array_keys($arr) as $k) $arr[$k] = stripslashes($arr[$k]);
	}
}
}

/**
* Database write function to add the series icon/series relationship to the database
* @param int $series Series ID
* @param string $icon Series icon
* @return boolean true if db write is successful
*/
function seriesicons_write($series, $icon) {
	global $wpdb;
	$tablename = $wpdb->prefix . 'orgSeriesIcons'; 
	
	if ( empty($series)  || '' == $series || empty($icon) || '' == $icon )	return false;
		
	$series = $wpdb->escape($series);
	$icon = $wpdb->escape($icon);
	
	if ($wpdb->get_var("SELECT term_id FROM $tablename WHERE term_id='$series'")) {
		$wpdb->query("UPDATE $tablename SET icon='$icon' WHERE term_id='$series'");
	} else {
		$wpdb->query("INSERT INTO $tablename (term_id, icon) VALUES ('$series','$icon')");
	}
	return true;
}

/**
* Database delete function to remove the series icon/series relationship from the database.
* @param int $series Series ID
* @param string $icon Series Icon
* @return boolean true if db delete is successful
*/
function seriesicons_delete($series) {
	global $wpdb;
	$tablename = $wpdb->prefix . 'orgSeriesIcons';
	
	if ( empty($series)  || '' == $series  )	return false;

	$series = $wpdb->escape($series);
		
	$wpdb->query("DELETE FROM $tablename WHERE term_id='$series'");
	return true;
}
?>