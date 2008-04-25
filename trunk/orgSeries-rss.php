<?php
//This file contains all the feed related functions for organize series
function get_series_rss_link($echo = false, $series_id, $series_nicename) {
	$permalink_structure = get_option('permalink_structure');
	
	if ( '' == $permalink_structure ) {
		$link = get_option('home') . '?feed=rss2&amp;series=' . $series_id;
	} else {
		$link = get_series_link($series_id);
		$link = trailingslashit($link) . user_trailingslashit('feed', 'feed');
	}
	
	$link = apply_filters('series_feed_link', $link);
	
	if ( $echo )
		echo $link;
	return $link;
}

function get_the_series_rss($type = 'rss') {
	$series = get_the_series();
	$home = get_bloginfo_rss('home');
	$the_list = '';
	$series_names = array();
	
	$filter = 'rss';
	if ( 'atom' == $type )
		$filter = 'raw';
		
	if ( !empty($series) ) foreach ( (array) $series as $serial ) {
		$series_names[] = sanitize_term_field('name', $serial->name, $serial->term_id, 'series', $filter);
	}
	
	$series_names = array_unique($series_names);
	
	foreach ( $series_names as $series_name ) {
		if ( 'rdf' == $type )
			$the_list .= "\n\t\t<dc:subject><![CDATA[$series_name]]></dc:subject>\n";
		elseif ( 'atom' == $type )
			$the_list .= sprintf( '<series scheme="%1$s" term="%2$s" />' , attribute_escape( apply_filters( 'get_bloginfo_rss', get_bloginfo( 'url' ) ) ), attribute_escape( $series_name ) );
		else
			$the_list .= "\n\t\t<category><![CDATA[$series_name]]></category>\n";
	}
	
	return apply_filters('the_series_rss', $the_list, $type);
}


function the_series_rss($type = 'rss') {
	echo get_the_series_rss($type);
}

function the_series_atom($type = 'atom') {
	echo get_the_series_atom($type);
}

function series_ns() {
	$ns = 'xmlns:series="http://unfoldingneurons.com"' . "\n\t";
	echo $ns;
}

//add_actions for rss/atom
add_action('rss2_item', 'the_series_rss');
add_action('atom_entry', 'the_series_atom');
add_action('rss2_ns','series_ns');
add_action('atom_ns', 'series_ns');
?>