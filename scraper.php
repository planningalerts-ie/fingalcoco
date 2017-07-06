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
  'REGFROMDATE.MAINBODY.WPACIS.1' => date('d/m/Y',$monthago),
  'REGTODATE.MAINBODY.WPACIS.1' => date('d/m/Y'),
  'SEARCHBUT.MAINBODY.WPACIS.1' => 'Search',
  );

//url-ify the data for the POST
foreach($formfields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');

# Get page one of the search results
$curl = curl_init($remote_uri);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; PlanningAlerts/0.1; +http://www.planningalerts.org/)");
curl_setopt($curl,CURLOPT_POST, count($formfields));
curl_setopt($curl,CURLOPT_POSTFIELDS, $fields_string);
$response = curl_exec($curl);
curl_close($curl);

echo $response;
exit();





/* 
 * Presently this does nowt but convert a single planning reference from Fingal,
 * which is represented in its GIS maps as a SRID-2157 ITM Polygon, into a 
 * WGS84 point of its centroid, then spit out that centroid. More to follow
 */

#$coords = getPointFromJSONURI('F16A/0583');
#print_r($coords);

echo "\n\n....done.";

exit();

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
