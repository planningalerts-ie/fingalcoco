<?
// Get feature ID of planning application
// http://www.dublincity.ie/LocationPublisher/SearchResult.aspx?Group_ID=1&amp;SearchString=3190/17&amp;IsSearchSP=1&amp;RefreshGuid=9a9919ee-7457-4412-9b43-58e74dcbd3cc
//
// Get geometry of feature ID
// POST to http://www.dublincity.ie/LPA/GetGeoJSONFeatureGeometry.asmx/GetFeatureBySearchInformation
// POST data {"nSearchID":3,"nFeatureKeyFieldValue":64931145}
// 
use geoPHP;
require 'scraperwiki.php';
require 'scraperwiki/simple_html_dom.php';
//
// // Read in a page
// $html = scraperwiki::scrape("http://foo.com");
//
// // Find something on the page using css selectors
// $dom = new simple_html_dom();
// $dom->load($html);
// print_r($dom->find("table.list"));
//
// // Write out to the sqlite database using scraperwiki library
// scraperwiki::save_sqlite(array('name'), array('name' => 'susan', 'occupation' => 'software developer'));
//
// // An arbitrary query against the database
// scraperwiki::select("* from data where 'name'='peter'")

// You don't have to do things with the ScraperWiki library.
// You can use whatever libraries you want: https://morph.io/documentation/php
// All that matters is that your final data is written to an SQLite database
// called "data.sqlite" in the current working directory which has at least a table
// called "data".
?>
