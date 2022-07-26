<?php
/**
Plugin Name: Saudi ADHD Society NVG.gov.sa parser
Plugin URI: https://github.com/Saudi-ADHD-Society/jlv-nvg-gov-sa-parse
Description: Fetches our latest volunteer opportunities from the Saudi National Volunteering portal
Version: 1.0.5
Author: Jeremy Varnham
Author URI: https://abuyasmeen.com
License: GPL3
 */

/*
 * Create shortcode
 *
 * [nvg_fetch]
 *
 */
function jlv_nvg_make_nvg_fetch_shortcode( $atts="" ) {
	// NVG site information
	$site_url               = 'https://nvg.gov.sa/';
	$site_page              = 'Opportunities/GetOpportunities/';
	$search_field           = 'organizationName';
	$search_term            = 'الجمعية السعودية لاضطراب فرط الحركة وتشتت الانتباه (إشراق)';
	$urlencoded_search_term = urlencode( $search_term );
	$source_url             = $site_url . $site_page . '?' . $search_field . '=' . $urlencoded_search_term;
	
	// Tags and classes from NVG page, with corresponding labels.
	$tag_classes['p']['card_title']    = 'عنوان الفرصة'; // used for count below
	$tag_classes['p']['card_location'] = 'المدينة';
	$tag_classes['p']['card_text']     = 'وصف الفرصة';
	$tag_classes['p']['days_number']   = 'عدد الأيام المتبقية';
	$tag_classes['p']['dates']         = 'التواريخ';
	$tag_classes['p']['seats_number']  = 'عدد المقاعد';
	$tag_classes['a']['join_btn']      = 'الرابط';

	// Fetch DOM data from NVG page and just keep elements defined above.
	$domxpath       = jlv_nvg_get_source_dom( $source_url );
	$elements_array = jlv_nvg_filter_html_input( $domxpath, $tag_classes, $site_url );
	$elements_count = count( $elements_array['card_title'] );

	$html = jlv_nvg_make_html_table_output( $tag_classes, $elements_array, $elements_count );
	
	return $html;
}
add_shortcode( 'nvg_fetch', 'jlv_nvg_make_nvg_fetch_shortcode' );

/*
 * Run the curl command.
 *
 */
function jlv_nvg_init_curl( $url ) {
	$ch      = curl_init();
	$timeout = 5;
	
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)" );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST,true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER,true );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 100 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
	
	$data    = curl_exec( $ch );
	
	curl_close( $ch );
	
	return $data;
}

/*
 * Fetch the remote html content.
 *
 */
function jlv_nvg_get_source_dom( $source_url ) {
	$html     = jlv_nvg_init_curl( $source_url );
	$dom      = new DOMDocument();
	$dom->loadHTML( $html );
	$domxpath = new DomXPath( $dom );
	
	return $domxpath;
}

/*
 * Filter the html elements by class.
 *
 */
function jlv_nvg_filter_html_input( $domxpath, $tag_classes, $site_url=null ) {
	foreach ( $tag_classes as $tag => $classes ) {
		foreach ( $classes as $class => $label ) { 
			$expression     = './/' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
			$elements       = $domxpath->evaluate( $expression );
			//$elements_count = $elements->count();
			
			foreach ( $elements as $element ) {
				$filtered[$class][] = ( 'a' == $tag ) ? '<a target="_blank" href="' . $site_url . $element->getAttribute('href') . '">' . $element->nodeValue . '</a>' : $element->nodeValue;
			}
		}
	}
	//$result = array( 'html' => $filtered, 'count' => $elements_count );
	$result = $filtered;
	
	return $result;
}

function jlv_nvg_make_html_table_output( $tag_classes, $elements_array, $elements_count ) {
	
	// Table header.	
	foreach ( $tag_classes as $tag => $classes ) {
		foreach ( $classes as $class => $label ) { 
			$table_th .= '<th>' . $label . '</th>';
		}
	}
	$table_head = '<tr>' . $table_th . '</tr>';

	// Table body.
	for ( $i = 0; $i < $elements_count; $i++ ) {
		$table_body .= '<tr>';
		foreach ( $tag_classes as $tag => $classes ) {
			foreach ( $classes as $class => $label ) { 
				$table_body .= '<td class="' . $class . '">' . $elements_array[$class][$i] . '</td>';
			}
		}
		$table_body .= '</tr>';
	}

	// Put table together.
	$table  = '<table>';
	$table .= '<thead>';
	$table .= $table_head;
	$table .= '</thead>';
	$table .= '<tbody>';
	$table .= $table_body;
	$table .= '</tbody>';
	$table .= '</table>';
	$table .= 'Entries: ' . $elements_count;
	
	return $table;
}
