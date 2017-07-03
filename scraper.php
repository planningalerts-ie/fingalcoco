<?php
//
// Fingal County Council Planning Applications
// John Handelaar 2017-07-03


require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';
include_once('vendor/phayes/geophp/geoPHP.inc');


/* 
 * Presently this does nowt but convert a single planning reference from Fingal,
 * which is represented in its GIS maps as a SRID-2157 ITM Polygon, into a 
 * WGS84 point of its centroid, then spit out that centroid. More to follow
 */

$coords = getPointFromJSONURI('F17A/0314');

print_r($coords);

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
