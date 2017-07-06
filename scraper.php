<?php
//
// Fingal County Council Planning Applications
// John Handelaar 2017-07-03


require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';
include_once('vendor/phayes/geophp/geoPHP.inc');
$date_format = 'Y-m-d';
$cookie_file = '/tmp/cookies.txt';
$remote_uri = 'http://planning.fingalcoco.ie/swiftlg/apas/run/WPHAPPCRITERIA';
$monthago = time() - (30*24*60*60);

$formfields = array(
  'APNID.MAINBODY.WPACIS.1' => '',
  'JUSTLOCATION.MAINBODY.WPACIS.1' => '',
  'JUSTDEVDESC.MAINBODY.WPACIS.1' => '',
  'SURNAME.MAINBODY.WPACIS.1' => '',
  'REGFROMDATE.MAINBODY.WPACIS.1' => date('d/m/Y',$monthago),
  'REGTODATE.MAINBODY.WPACIS.1' => date('d/m/Y'),
  'DECFROMDATE.MAINBODY.WPACIS.1' => '',
  'DECTODATE.MAINBODY.WPACIS.1' => '',
  'FINALGRANTFROM.MAINBODY.WPACIS.1' => '',
  'FINALGRANTTO.MAINBODY.WPACIS.1' => '',
  'APELDGDATFROM.MAINBODY.WPACIS.1' => '',
  'APELDGDATTO.MAINBODY.WPACIS.1' => '',
  'APEDECDATFROM.MAINBODY.WPACIS.1' => '',
  'APEDECDATTO.MAINBODY.WPACIS.1' => '',
  'SEARCHBUTTON.MAINBODY.WPACIS.1' => 'Search'  
);

//url-ify the data for the POST
foreach($formfields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');

// Get search form to acquire session cookie 
$curl = curl_init('http://planning.fingalcoco.ie/swiftlg/apas/run/wphappcriteria.display');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
$got_cookie = curl_exec($curl);
curl_close($curl);
unset($got_cookie);

# Get page one of the search results
$curl = curl_init($remote_uri);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PlanningAlerts/0.1; +http://www.planningalerts.org/)");
curl_setopt($curl, CURLOPT_POST, count($formfields));
curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
$response = curl_exec($curl);
curl_close($curl);

$resultslist = '';
$resultslist .= extractRows($response);

// Collect other search URIs
$pageparser = new simple_html_dom();
$pageparser->load($response);
$links = $pageparser->find('#apas_form_text a');    

$pages = array();
foreach ($links as $link) {
  $parts = explode('BackURL=',$link->href);
  $pages[] .= 'http://planning.fingalcoco.ie/swiftlg/apas/run/' . $parts[0];
}
unset($pageparser);

// Append table rows from all subsequent pages to $resultslist
foreach($pages as $page) {
    $resultslist .= extractRows(file_get_contents($page));
}

// Finally actually process the data
$resultparser = new simple_html_dom();
$resultparser->load($resultslist);
foreach ($resultparser->find('tr') as $application) {

 					              /*
 								<tr>
 									<td class="apas_tblContent"><a href="WPHAPPDETAIL.DisplayUrl?theApnID=F17A/0320&backURL=<a href=wphappcriteria.display?paSearchKey=1661268>Search Criteria</a> > <a href='wphappsearchres.displayResultsURL?ResultID=1737440%26StartIndex=1%26SortOrder=rgndat,apnid%26DispResultsAs=WPHAPPSEARCHRES%26BackURL=<a href=wphappcriteria.display?paSearchKey=1661268>Search Criteria</a>'>Search Results</a>">F17A/0320</a></td>
 					                  		<td class="apas_tblContent">Permission for the separation of existing dwelling into 2 no. dwellings with a</td>
 					                  		<td class="apas_tblContent"><input type="hidden" name="ORDERCOUNTER.PAHEADER.PACIS2.3-1" value="" class="input-box" size="7" />
 											      1 Balkill Park, Howth, Co Dublin
 					                  		</td>
 								</tr>
 					              */
	
	$council_reference = trim($application->find('td',0)->find('a')->plaintext);
	echo "Ref: $council_reference\n";
	$address = trim($application->find('td',2)->plaintext);
	echo "Address: $address\n";
	$urlparts = explode('backURL=',$application->find('td',0)->find('a')->href);
	print_r($urlparts);
	$info_url = 'http://planning.fingalcoco.ie/swiftlg/apas/run/' . $urlparts[0];
	unset($urlparts);
	$comment_url = 'http://planning.fingalcoco.ie/swiftlg/apas/run/WPHAPPDETAIL.DisplayUrl?theApnID=' . $council_reference;
	
	// uncut extra data is TWO more URLgets away, annoyingly
	$remaininginfo = file_get_contents($info_url);
	$details = new simple_html_dom();
	$details->load($remaininginfo);
	echo $info_url;
	$date_received = date($date_format,strtotime($details->find('#apas_form',0)->find('div',2)->find('p',0)->plaintext));
	$date_scraped = date($date_format);
	$on_notice_from = $date_received;
	$todate = $details->find('#apas_form')->find('div',13)->find('p',0)->plaintext;
	if(stristr($todate,'application may be made on or before ')) {
		$todate = explode('application may be made on or before ',$todate);
		$on_notice_to = $todate[1];
	} elseif (stristr($todate,'period for this application expired on ')) {
		$todate = explode('period for this application expired on ',$todate);
		$on_notice_to = $todate[1];
	} else {
		die("can't determine date from this: \n\n$todate");
	}
	unset($todate,$details,$remaininginfo);
	
	$descriptionpage = file_get_contents($info_url . '&theTabNo=11');
	$descriptionscraper = new simple_html_dom();
	$descriptionscraper->load($descriptionpage);
	$description = $descriptionscraper->find('input[name=DEVLDESC.PAPROPOSAL.PACIS2.1-1]')->value;
	
	// Remember when you thought this was the most longwinded part of this?
	$coords = getPointFromJSONURI($council_reference);

	
    $application = array(
        'council_reference' => $council_reference,
        'address' => $address,
        'lat' => $coords['lat'],
        'lng' => $coords['lng'],
        'description' => $description,
        'info_url' => $info_url,
        'comment_url' => $comment_url,
        'date_scraped' => $date_scraped,
        'date_received' => $date_received,
        'on_notice_from' => $on_notice_from,
        'on_notice_to' => $on_notice_to
    );

	print_r($application);	
	exit();
}


echo $resultslist;
exit();


function extractRows($html) {
	$split1 = explode('<th class="apas_tblHead"><input type="submit" name="COMPADDBUT.MAINBODY.PACIS2.1" value="Location" class="apas_tblHead_button" /></th>',$html);
	$split2 = explode('</table>',$split1[1]);
	$split3 = explode('</tr>',$split2[0]);
	unset ($split3[0]);
	return implode('</tr>',$split3);
}


function getPointFromJSONURI($ref) {
  $uri = file_get_contents('http://gis.fingal.ie/arcgis/rest/services/Planning/PlanningApplicationsWeb/MapServer/2/query?f=json&where=PLANNING_REFERENCE%3D%27' . urlencode($ref) .'%27&returnGeometry=true&spatialRel=esriSpatialRelIntersects&maxAllowableOffset=0.00001&outFields=*&outSR=4326');
  $application = json_decode($uri);
  $geojson = makeGeoJSON($application->features[0]->geometry);
  $polygon = geoPHP::load($geojson,'json');
  $centroid = $polygon->getCentroid();
  $lng = $centroid->getX();
  $lat = $centroid->getY();
  return array(
    'lat' => $lat,
    'lng' => $lng,
  );
}

function makeGeoJson($object) {
  $partial = '';
  foreach ($object->rings as $points) {
    $coords = array();
    foreach ($points as $point) {
      $coords[] .= "[ " . implode(",",$point) . " ]";
    }
    $partial .= '"geometry": {' . "\n" . '"type": "Polygon", ' . "\n" . '"coordinates": [' . "\n[\n";
    $partial .= implode(",",$coords);
    $partial .= "\n]\n]\n}";
  }
  $geojson = '{ "type": "Feature",' . $partial . '}';
  
  // This line is for no reason but improved legibility if dumping for debug
  $geojson=json_encode(json_decode($geojson), JSON_PRETTY_PRINT);
  return $geojson;
}

?>
