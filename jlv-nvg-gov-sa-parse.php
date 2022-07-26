<?php
/**
Plugin Name: Saudi ADHD Society NVG.gov.sa parser
Plugin URI: https://github.com/Saudi-ADHD-Society/jlv-nvg-gov-sa-parse
Description: Fetches our latest volunteer opportunities from the Saudi National Volunteering portal
Version: 1.0.2
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
	
	// Paragraph element classes on NVG page.
	$classnames_p['card_title']    = 'عنوان الفرصة';
	$classnames_p['card_location'] = 'المدينة';
	$classnames_p['card_text']     = 'وصف الفرصة';
	$classnames_p['days_number']   = 'عدد الأيام المتبقية';
	$classnames_p['dates']         = 'التواريخ';
	$classnames_p['seats_number']  = 'عدد المقاعد';
	$classnames_a['join_btn']      = 'الرابط';
	$classnames                    = array_merge( $classnames_p, $classnames_a );
	
	$filtered_results_p = jlv_nvg_filter_html( $domxpath, 'p', $classnames_p, null );
	$filtered_results_a = jlv_nvg_filter_html( $domxpath, 'a', $classnames_a, $site_url );
	
	$filtered_results   = array_merge( $filtered_results_p['html'], $filtered_results_a['html'] );
	$elements_count     = $filtered_results_p['count'] + $filtered_results_a['count'];

	// Construct output table.
	$table  = '<table>';
	$table .= '<thead><tr>';
	
	foreach ( $classnames as $class => $label ) {
		$table .= '<th>' . $label . '</th>';
	}
	
	$table .= '</tr></thead>';
	$table .= '<tbody>';

	for ( $i = 0; $i < $elements_count; $i++ ) {
		$table .= '<tr>';
		foreach ( $classnames as $class => $label ) {
			$table .= '<td class="' . $class . '">' . $filtered_results[$class][$i] . '</td>';
		}
		$table .= '</tr>';
	}
	
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
function jlv_nvg_filter_html( $domxpath, $html_element, $classnames, $site_url=null ) {
	foreach ( $classnames as $class => $label ) {
		$expression     = './/' . $html_element . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
		$elements       = $domxpath->evaluate( $expression );
		$elements_count = $elements->count();
		
		foreach ( $elements as $element ) {
			$filtered[$class][] = ( $html_element == 'a' ) ? $result[$class][] =  '<a target="_blank" href="' . $site_url . $element->getAttribute('href') . '">' . $element->nodeValue . '</a>' : $element->nodeValue;
		}
	}
	
	$result = array( 'html' => $filtered, 'count' => $elements_count );
	return $result;
}
