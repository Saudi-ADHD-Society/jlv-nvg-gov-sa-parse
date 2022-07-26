<?php
/**
Plugin Name: Saudi ADHD Society NVG.gov.sa parser
Plugin URI: https://github.com/Saudi-ADHD-Society/jlv-nvg-gov-sa-parse
Description: Fetches our latest volunteer opportunities from the Saudi National Volunteering portal
Version: 1.0.1
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
	$domxpath = jlv_nvg_get_curl_data( $full_url );
	
	// Paragraph element classes on NVG page.
	$classnames['card_title']    = 'عنوان الفرصة';
	$classnames['card_location'] = 'المدينة';
	$classnames['card_text']     = 'وصف الفرصة';
	$classnames['days_number']   = 'عدد الأيام المتبقية';
	$classnames['dates']         = 'التواريخ';
	$classnames['seats_number']  = 'عدد المقاعد';
		
	// Just keep the html elements with above class names.
	foreach ( $classnames as $class => $label ) {
		$expression     = './/p[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
		$elements       = $domxpath->evaluate( $expression );
		$elements_count = $elements->count();
		
		foreach ( $elements as $element ) {
			$opportunity[$class][] =  $element->nodeValue;
		}
	}
	
	// Hyperlink element.
	$linkclass = 'join_btn';
	$classnames[$linkclass]       = 'الرابط';
	
	$expression = './/a[contains(concat(" ", normalize-space(@class), " "), " ' . $linkclass . ' ")]';
	$elements   = $domxpath->evaluate( $expression );
	
	foreach ( $elements as $element ) {
		$opportunity[$linkclass][] =  '<a target="_blank" href="' . $site_url . $element->getAttribute('href') . '">' . $element->nodeValue . '</a>';
	}
	
	// Construct output table.
	$table = '<table>';
	$table .= '<thead><tr>';
	
	foreach ( $classnames as $class => $label ) {
		$table .= '<th>' . $label . '</th>';
	}
	
	$table .= '</tr></thead>';
	$table .= '<tbody>';

	for ( $i = 0; $i < $elements_count; $i++ ) {
		$table .= '<tr>';
		foreach ( $classnames as $class => $label ) {
			$table .= '<td class="' . $class . '">' . $opportunity[$class][$i] . '</td>';
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
function jlv_nvg_get_curl_data( $full_url ) {
	$html     = jlv_nvg_init_curl( $full_url );
	$dom      = new DOMDocument();
	$dom->loadHTML( $html );
	$domxpath = new DomXPath( $dom );
	
	return $domxpath;
}
