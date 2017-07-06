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
  $parts = explode('&BackURL=',$link->href);
  $pages[] .= 'http://planning.fingalcoco.ie/swiftlg/apas/run/' . $parts[0];
}

// Append table rows from all subsequent pages to $resultslist
foreach($pages as $page) {
    $resultslist .= extractRows(file_get_contents($page));
}

echo $resultslist;
exit();

#$coords = getPointFromJSONURI('F16A/0583');
#print_r($coords);

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
