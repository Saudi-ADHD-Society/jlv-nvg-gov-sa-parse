<?php
/**
Plugin Name: Saudi ADHD Society NVG.gov.sa parser
Plugin URI: https://github.com/Saudi-ADHD-Society/jlv-nvg-gov-sa-parse
Description: Fetches our latest volunteer opportunities from the Saudi National Volunteering portal
Version: 1.0.3
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
function jlv_nvg_fetch_shortcode( $atts="" ) {
	$site_url                = 'https://nvg.gov.sa/';
	$site_page               = 'Opportunities/GetOpportunities/';
	$organization_title      = 'الجمعية السعودية لاضطراب فرط الحركة وتشتت الانتباه (إشراق)';
	$urlencoded_organization = urlencode( $organization_title );
	$full_url                = $site_url . $site_page . '?organizationName=' . $urlencoded_organization;
	
	// Fetch DOM data from NVG page.
	$domxpath = jlv_nvg_get_dom_data( $full_url );
	
	// Paragraph tags and classes on NVG page, with corresponding labels.
	$tag_classes['p']['card_title']    = 'عنوان الفرصة';
	$tag_classes['p']['card_location'] = 'المدينة';
	$tag_classes['p']['card_text']     = 'وصف الفرصة';
	$tag_classes['p']['days_number']   = 'عدد الأيام المتبقية';
	$tag_classes['p']['dates']         = 'التواريخ';
	$tag_classes['p']['seats_number']  = 'عدد المقاعد';
	$tag_classes['a']['join_btn']      = 'الرابط';

	$filtered_results   = jlv_nvg_filter_html( $domxpath, $tag_classes, $site_url );
	$elements_count     = $filtered_results['count'];

	// Construct table.	
	foreach ( $tag_classes as $tag => $classes ) {
		foreach ( $classes as $class => $label ) { 
			$table_th .= '<th>' . $label . '</th>';
		}
	}
	$table_head = '<tr>' . $table_th . '</tr>';

	for ( $i = 0; $i < $elements_count; $i++ ) {
		$table_body .= '<tr>';
		foreach ( $tag_classes as $tag => $classes ) {
			foreach ( $classes as $class => $label ) { 
				$table_body .= '<td class="' . $class . '">' . $filtered_results['html'][$class][$i] . '</td>';
			}
		}
		$table_body .= '</tr>';
	}

	$table  = '<table>';
	$table .= '<thead>';
	$table .= $table_head;
	$table .= '</thead>';
	$table .= '<tbody>';
	$table .= $table_body;
	$table .= '</tbody>';
	$table .= '</table>';
	
	return $table;
}
add_shortcode( 'nvg_fetch', 'jlv_nvg_fetch_shortcode' );

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
function jlv_nvg_get_dom_data( $full_url ) {
	$html     = jlv_nvg_init_curl( $full_url );
	$dom      = new DOMDocument();
	$dom->loadHTML( $html );
	$domxpath = new DomXPath( $dom );
	
	return $domxpath;
}

/*
 * Filter the html elements by class.
 *
 */
function jlv_nvg_filter_html( $domxpath, $tag_classes, $site_url ) {
	foreach ( $tag_classes as $tag => $classes ) {
		foreach ( $classes as $class => $label ) { 
			$expression     = './/' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
			$elements       = $domxpath->evaluate( $expression );
			$elements_count = $elements->count();
			
			foreach ( $elements as $element ) {
				$filtered[$class][] = ( 'a' == $tag ) ? '<a target="_blank" href="' . $site_url . $element->getAttribute('href') . '">' . $element->nodeValue . '</a>' : $element->nodeValue;
			}
		}
	}
	$result = array( 'html' => $filtered, 'count' => $elements_count );
	
	return $result;
}
