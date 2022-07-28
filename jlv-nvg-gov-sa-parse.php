<?php
/**
Plugin Name: Saudi ADHD Society NVG.gov.sa parser
Plugin URI: https://github.com/Saudi-ADHD-Society/jlv-nvg-gov-sa-parse
Description: Fetches our latest volunteer opportunities from the Saudi National Volunteering portal
Version: 1.2.0
Author: Jeremy Varnham
Author URI: https://abuyasmeen.com
License: GPL3
 */

/*
 * Create shortcode
 *
 * [nvg_fetch detail_page=""]
 *
 */
function jlv_nvg_make_nvg_fetch_shortcode( $atts="" ) {
	$defaults = array(
		'detail_page' => null,
	);
	$atts = array_merge( $defaults, (array) $atts );

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
	$elements_array = jlv_nvg_filter_source_html( $domxpath, $tag_classes, $site_url, $atts['detail_page'] );
	
	if ( is_array( $elements_array ) ) {
		$elements_count = count( $elements_array['card_title'] );
		$html = jlv_nvg_make_html_table_output( $tag_classes, $elements_array, $elements_count );
	} else {
		$html = 'Unable to reach nvg.gov.sa – Please refresh the page.';
	}

	return $html;
}
add_shortcode( 'nvg_fetch', 'jlv_nvg_make_nvg_fetch_shortcode' );

/*
 * Create shortcode
 *
 * [nvg_fetch_details]
 *
 */
function jlv_nvg_make_nvg_fetch_details_shortcode( $atts="" ) {
	// NVG site information
	$opportunity_id     = sanitize_text_field( $_POST['opportunityid'] );

	$site_url           = 'https://nvg.gov.sa/';
	$site_detail_page   = 'Opportunities/GetDetails/';
	$site_detail_view   = 'Opportunities/Details/';
	$details_source_url = $site_url . $site_detail_page . '/';
	$details_view_url   = $site_url . $site_detail_view . $opportunity_id;
	
	
	if ( isset($opportunity_id) ) {
		$detail = jlv_nvg_get_source_dom( $details_source_url . $opportunity_id );
		$detail_array = jlv_nvg_filter_source_details_html( $detail );
		
		if ( is_array( $detail_array ) ) {
			$detail_elements_count = count( $detail_array['class'] );
			$html = jlv_nvg_make_formatted_details_output( $detail_array, $detail_elements_count );
			$html .= '<a target="_blank" class="btn btn-sm" id="' . $opportunity_id . '" href="' . $details_view_url . '">التقديم على الفرصة</a>';
		} else {
			$html = __('Unable to reach nvg.gov.sa – Please refresh the page.', 'jlv-nvg-parse');
		}
		
		return $html;
	}
}
add_shortcode( 'nvg_fetch_details', 'jlv_nvg_make_nvg_fetch_details_shortcode' );

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
	$dom->recover = true;
	$dom->strictErrorChecking = false;
	@$dom->loadHTML( $html );
	
	$domxpath = new DomXPath( $dom );
	
	return $domxpath;
}

/*
 * Filter the html elements by class.
 *
 */
function jlv_nvg_filter_source_html( $domxpath, $tag_classes, $site_url=null, $detail_page=null ) {
	foreach ( $tag_classes as $tag => $classes ) {
		foreach ( $classes as $class => $label ) { 
			$expression     = './/' . $tag . '[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
			$elements       = $domxpath->evaluate( $expression );
			
			foreach ( $elements as $element ) {
				$link       = $element->getAttribute('href');
				$link_parts = explode( "/", $link );
				$id         = $link_parts[3];
				$filtered[$class][] = ( 'a' == $tag ) ? '<form action="' . $detail_page. '" method="post"><input type="hidden" name="opportunityid" value="' . $id . '"><input type="submit" value="التفاصيل"></form>' : $element->nodeValue;
				$filtered['id'][] = $id;
			}
		}
	}
	return $filtered;
}

/*
 * Make html table for output.
 *
 */
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

function jlv_nvg_get_source_details_dom( $details_source_url, $elements_array_ids ) {
	foreach ( $elements_array_ids as $id ) {
		$details[$id] = jlv_nvg_get_source_dom( $details_source_url . $id, 'save' );
	}
	return $details;
}

/*
 * Filter the html elements by class.
 *
 */
function jlv_nvg_filter_source_details_html( $domxpath ) {
	$expression = './/div//div//div//div//div//p';
	$elements   = $domxpath->evaluate( $expression );
						
	foreach ( $elements as $element ) {
		$filtered['class'][] = $element->getAttribute('class');
		$filtered['value'][] = $element->nodeValue;
	}
			
	return $filtered;
}

/*
 * Make table for details output.
 *
 */
function jlv_nvg_make_table_details_output( $details_array, $elements_count=1 ) {
	
	// Table body.
	for ( $i = 0; $i < $elements_count; $i+=2 ) {
		$j=$i+1;
		$table_body .= '<tr>';
		$table_body .= '<th class="' . $details_array['class'][$i] . '">' . $details_array['value'][$i] . '</th>';
		$table_body .= '<td class="' . $details_array['class'][$j] . '">' . $details_array['value'][$j] . '</td>';
		$table_body .= '</tr>';
	}
	
	// Put table together.
	$table  = '<table>';
	$table .= $table_body;
	$table .= '</table>';

	return $table;
}


/*
 * Make modal divs for details output.
 *
 */
function jlv_nvg_make_formatted_details_output( $details_array, $elements_count=1 ) {
	
	$details['details']['title']       = array( 'class' => $details_array['class'][0], 'value' => $details_array['value'][0] );
	$details['details']['text']        = array( 'class' => $details_array['class'][1], 'value' => $details_array['value'][1] );
	 
	$details['date']['title']          = array( 'class' => $details_array['class'][2], 'value' => 'التواريخ' );
	$details['date']['text']           = array( 'class' => $details_array['class'][3], 'value' => $details_array['value'][2] );
	 
	$details['days']['title']          = array( 'class' => $details_array['class'][2], 'value' => 'عدد الأيام' );
	$details['days']['text']           = array( 'class' => $details_array['class'][3], 'value' => $details_array['value'][3] );
	 
	$details['seats']['title']         = array( 'class' => $details_array['class'][4], 'value' => $details_array['value'][4] );
	$details['seats']['text']          = array( 'class' => $details_array['class'][5], 'value' => $details_array['value'][5] );
	 
	$details['field']['title']         = array( 'class' => $details_array['class'][6], 'value' => $details_array['value'][6] );
	$details['field']['text']          = array( 'class' => $details_array['class'][7], 'value' => $details_array['value'][7] );
	 
	$details['type']['title']          = array( 'class' => $details_array['class'][8], 'value' => $details_array['value'][8] );
	$details['type']['text']           = array( 'class' => $details_array['class'][9], 'value' => $details_array['value'][9] );
	 
	$details['gender']['title']        = array( 'class' => $details_array['class'][10], 'value' => $details_array['value'][10] );
	$details['gender']['text']         = array( 'class' => $details_array['class'][11], 'value' => $details_array['value'][11] );
	 
	$details['qualification']['title'] = array( 'class' => $details_array['class'][12], 'value' => $details_array['value'][12] );
	$details['qualification']['text']  = array( 'class' => $details_array['class'][13], 'value' => $details_array['value'][13] );
	 
	$details['remote']['title']        = array( 'class' => $details_array['class'][14], 'value' => $details_array['value'][14] );
	$details['remote']['text']         = array( 'class' => $details_array['class'][15], 'value' => $details_array['value'][15] );
	 
	$details['urgent']['title']        = array( 'class' => $details_array['class'][16], 'value' => $details_array['value'][16] );
	$details['urgent']['text']         = array( 'class' => $details_array['class'][17], 'value' => $details_array['value'][17] );
	 
	$details['disability']['title']    = array( 'class' => $details_array['class'][18], 'value' => $details_array['value'][18] );
	$details['disability']['text']     = array( 'class' => $details_array['class'][19], 'value' => $details_array['value'][19] );
	 
	$details['interview']['title']     = array( 'class' => $details_array['class'][20], 'value' => $details_array['value'][20] );
	$details['interview']['text']      = array( 'class' => $details_array['class'][21], 'value' => $details_array['value'][21] );
	 
	$details['benefits']['title']      = array( 'class' => $details_array['class'][22], 'value' => $details_array['value'][22] );
	$details['benefits']['text']       = array( 'class' => $details_array['class'][23], 'value' => $details_array['value'][23] );
	 
	$details['benefits1']['title']     = array( 'class' => $details_array['class'][24], 'value' => $details_array['value'][24] );
	$details['benefits1']['text']      = array( 'class' => $details_array['class'][25], 'value' => $details_array['value'][25] );
	 
	$details['skills']['title']        = array( 'class' => $details_array['class'][26], 'value' => $details_array['value'][26] );
	$details['skills']['text']         = array( 'class' => $details_array['class'][27], 'value' => $details_array['value'][27] );
	 
	$details['support']['title']       = array( 'class' => $details_array['class'][28], 'value' => $details_array['value'][28] );
	$details['support']['text']        = array( 'class' => $details_array['class'][29], 'value' => $details_array['value'][29] );
	 
	$details['tasks']['title']         = array( 'class' => $details_array['class'][30], 'value' => $details_array['value'][30] );
	$details['tasks']['text']          = array( 'class' => $details_array['class'][31], 'value' => $details_array['value'][31] );
	 
	$details['profession']['title']    = array( 'class' => $details_array['class'][32], 'value' => $details_array['value'][32] );
	$details['profession']['text']     = array( 'class' => $details_array['class'][33], 'value' => $details_array['value'][33] );
	 
	$details['age']['title']           = array( 'class' => $details_array['class'][34], 'value' => $details_array['value'][34] );
	$details['age']['text']            = array( 'class' => $details_array['class'][35], 'value' => $details_array['value'][35] );
	 
	$html  = '<style>.flex{display:flex;flex-wrap:wrap;justify-content:space-around;}.flexcol{flex-direction:row;flex-wrap:wrap;flex-grow:1;margin-right:75px;}</style>';

	$html .= '<div class="opportunity-details">';

	$html .= '<h3 class="' . $details['details']['title']['class'] . '">' . $details['details']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['details']['text']['class'] . '">' . $details['details']['text']['value'] . '</p>';
	
	$html .= '<div class="flex">';
	
	$html .= '<div class="flexcol"><h3 class="' . $details['date']['title']['class'] . '">' . $details['date']['title']['value'] . '</h3><p class="' . $details['date']['text']['class'] . '">' . $details['date']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['days']['title']['class'] . '">' . $details['days']['title']['value'] . '</h3><p class="' . $details['days']['text']['class'] . '">' . $details['days']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['seats']['title']['class'] . '">' . $details['seats']['title']['value'] . '</h3><p class="' . $details['seats']['text']['class'] . '">' . $details['seats']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['field']['title']['class'] . '">' . $details['field']['title']['value'] . '</h3><p class="' . $details['field']['text']['class'] . '">' . $details['field']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['type']['title']['class'] . '">' . $details['type']['title']['value'] . '</h3><p class="' . $details['type']['text']['class'] . '">' . $details['type']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['gender']['title']['class'] . '">' . $details['gender']['title']['value'] . '</h3><p class="' . $details['gender']['text']['class'] . '">' . $details['gender']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['qualification']['title']['class'] . '">' . $details['qualification']['title']['value'] . '</h3><p class="' . $details['qualification']['text']['class'] . '">' . $details['qualification']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['remote']['title']['class'] . '">' . $details['remote']['title']['value'] . '</h3><p class="' . $details['remote']['text']['class'] . '">' . $details['remote']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['urgent']['title']['class'] . '">' . $details['urgent']['title']['value'] . '</h3><p class="' . $details['urgent']['text']['class'] . '">' . $details['urgent']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['disability']['title']['class'] . '">' . $details['disability']['title']['value'] . '</h3><p class="' . $details['disability']['text']['class'] . '">' . $details['disability']['text']['value'] . '</p></div>'; 
	$html .= '<div class="flexcol"><h3 class="' . $details['interview']['title']['class'] . '">' . $details['interview']['title']['value'] . '</h3><p class="' . $details['interview']['text']['class'] . '">' . $details['interview']['text']['value'] . '</p></div>'; 

	$html .= '</div>';

	$html .= '<h3 class="' . $details['benefits']['title']['class'] . '">' . $details['benefits']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['benefits']['text']['class'] . '">' . $details['benefits']['text']['value'] . '</p>';
		  
	$html .= '<h3 class="' . $details['benefits1']['title']['class'] . '">' . $details['benefits1']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['benefits1']['text']['class'] . '">' . $details['benefits1']['text']['value'] . '</p>';
		  
	$html .= '<h3 class="' . $details['skills']['title']['class'] . '">' . $details['skills']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['skills']['text']['class'] . '">' . $details['skills']['text']['value'] . '</p>';

	$html .= '<h3 class="' . $details['support']['title']['class'] . '">' . $details['support']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['support']['text']['class'] . '">' . $details['support']['text']['value'] . '</p>';
		  
	$html .= '<h3 class="' . $details['tasks']['title']['class'] . '">' . $details['tasks']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['tasks']['text']['class'] . '">' . $details['tasks']['text']['value'] . '</p>';
		  
	$html .= '<h3 class="' . $details['profession']['title']['class'] . '">' . $details['profession']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['profession']['text']['class'] . '">' . $details['profession']['text']['value'] . '</p>';
		  
	$html .= '<h3 class="' . $details['age']['title']['class'] . '">' . $details['age']['title']['value'] . '</h3>';
	$html .= '<p class="' . $details['age']['text']['class'] . '">' . $details['age']['text']['value'] . '</p>';
	
	$html .= '</div>';
 
	return $html;
}
