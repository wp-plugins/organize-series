<?php
##SERIES-ICON RELATED STUFF
#much of this code has added/modified from the Category-Icon plugin by Ivan Georgiev (GNU-GPL v2) [http://devcorner.georgievi.net/wp-plugins/wp-category-icons/].
/**

* Template tag for insertion of series-icons
* @param $fit_width int[-1] Maximum width (or desired width if $expanded=true) of the image.
@param $fit_height int[-1] Macimum height (or desired height if $expanded=true) of the image.
 * @param $expand boolean [false] Whether the image should be expanded to fit the rectangle specified by fit_xxx.
 * @param $series int Series ID. If not specified, the current category is used or the current post's category.
 * @param $prefix string String to echo before the image tag. If no image, no otuput.
 * @param $suffix string String to echo after the image tag. Ignored if no image found.
 * @param $class string [] Class attribute for the image tag.
 * @param $link boolean [1] If true the image is made a hyperlink (wrapped by anchor tag).
 *
 * @return boolean True if image found.
 */
 function get_series_icon ($params='') {
	parse_str($params, $p);
	if (!isset($p['fit_width'])) $p['fit_width']=-1;
	if (!isset($p['fit_height'])) $p['fit_height']=-1;
	if (!isset($p['expand'])) $p['expand']=false;
	if (!isset($p['series'])) $p['series']=$GLOBALS['cat'];
	if (!isset($p['prefix'])) $p['prefix'] = '';
	if (!isset($p['suffix'])) $p['suffix'] = '';
	if (!isset($p['class'])) $p['class'] = '';
	if (!isset($p['link'])) $p['link'] = 1;
	stripslaghes_gpc_arr($p);
	
	if (empty($p['series']) && isset($GLOBALS['series'])) {
		$serieslist = get_the_series($GLOBALS['post']->ID);
		if ( is_array($serieslist) ) $p['series'] = $serieslist[0]->series_ID;
	}
	
	if (!isset($p['series'])) return;
	
	$icon = series_get_icons($p['series']);
	$file = seriesicons_path() . '/$icon';
	$url = seriesicons_url() . '/$icon';
	
	if ($p['link']) {
		$p['prefix'] .= '<a href="' . get_series_link($p['series']) . '">';
		$p['suffix'] = '</a>' . $p['suffix'];
	}
	
	if (is_file($file)) {
		list($width, $height, $type, $attr) = getimagesize($file);
		list($w, $h) = series_fit_rect($width, $height, $p['fit_width'], $p['fit_height'], $p['expand']);
		echo("$p[prefix]<img class=\"$p[class]\" src=\"$url\" width=\"$w\" height=\"$h\" />$p[suffix]");
		return true;
	}
	return false;
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
		return $row->url;
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
	$path = get_option('series_icon_path');
	$def = default_seriesicons_upload();
	if ( '' == $path )
		return ABSPATH . $def[0];
	else
		return ABSPATH . $path;
}

function seriesicons_url() {
	$url = get_option('series_icon_url');
	$def = default_seriesicons_upload();
	if ( '' == $url )
		return $def[1];
	else
		return $url;
}

/**
* Get file types to show when selecting icons
* @return types
*/
function seriesicons_filetypes() {
	$types = get_option('series_icon_filetypes');
	if (''==$types)
		return get_option('fileupload_allowedtypes');
	else
		return $types;
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
* Database write function to add the series icon/series relationship to the database
* @param int $series Series ID
* @param string $icon Series icon
* @return boolean true if db write is successful
*/
function seriesicons_write($series, $icon) {
	global $wpdb;
	$tablename = $wpdb->prefix . 'orgSeriesIcons'; 
	
	if ( empty($series)  || '' = $series || empty($icon) || '' = $icon )	return false;
		
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
function seriesicons_delete($series, $icon) {
	global $wpdb;
	$tablename = $wpdb->prefix . 'orgSeriesIcons';
	
	if ( empty($series)  || '' = $series || empty($icon) || '' = $icon )	return false;

	$series = $wpdb->escape($series);
	$icon = $wpdb->escape($icon);
	
	$wpdb->query("DELETE FROM $tablename WHERE term_id='$series'");
	return true;
}