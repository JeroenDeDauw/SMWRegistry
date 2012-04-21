<?php
/**
 *
 * Iterates through all RDF-URLS of the Wiki
 *
 * usage: php SMW_iterateRDF.php <url> -d <delay> -s <startpage>
 *
 * <url> is the base-url of the wiki to examine.
 * <delay> is the delay in milliseconds to wait after each results page. This value will be
 *         overwritten, when a "Crawl-delay" statement is present in the robots.txt of the host.
 * <startpage> page count to start iteration from, useful to restart partial runs
 * @author Tobias Matzner
 */


$optionsWithArgs = array('d', 's');
require_once( 'commandLine.inc' );
global $IP;
require_once( $IP. '/extensions/SMW-Registry/includes/Robots.txt.smw.class.php');

// $userAgent='SMW semantic data crawler (http://semantic-mediawiki.org)';
$userAgent = 'SMW Registry (http://semantic-mediawiki.org/wiki/Registry)';
ini_set('user_agent', $userAgent);

//check for delay
//note that the Robots_txt::urlAllowed function sleeps automatically when a Crawl-delay is specified in robots.txt
$delay = 0;
if(!Robots_txt::crawlDelay($args[0],$userAgent)  && array_key_exists( 'd', $options ) ) {//if no delay specified and parameter is given, use that
	$delay = intval($options['d']) * 1000; //given in milliseconds, convert to microseconds
}
print "Delay: $delay\n\n";

if( array_key_exists( 's', $options ) ) {// use start offset
	$offsetExp = $options['s'];
} else {
	$offsetExp = '0';
}

$baseURL = $args[0];
$getURL = $baseURL . 'Special:ExportRDF?offset=';

$offsetNum = $offsetExp+0;

do {
	if(Robots_txt::urlAllowed($getURL.$offsetExp,$userAgent)) {
		$response = @file_get_contents($getURL.$offsetExp, "rb");
	} else {
		echo "robots.txt prohibits access to: ".$getURL.$offsetExp."\n";
	}

	//check for swivt:subject entry
	$numOccurences = preg_match_all('/swivt:page .*>.*<rdfs:isDefinedBy .*>/Us',$response, &$occurences);
	if($numOccurences){
		//get the urls
		foreach($occurences[0] as $occ){
			echo "Page " . $offsetNum++ . "\n";
			preg_match('/rdfs:isDefinedBy .*>/U', $occ, &$defByLine);
			$rdfurl=preg_split('/"/',$defByLine[0]);
			fPerUrl(str_replace('&wikiurl;', $baseURL, $rdfurl[1]) );
			usleep($delay);
		}
	}
	//check for owl:Thing
	$owlThingPresent = preg_match('/owl:Thing .*>.*<rdfs:isDefinedBy .*>/Us',$response, &$owlThing);
	if($owlThingPresent){
		//get new offset
		preg_match('/rdfs:isDefinedBy .*offset=(.*)"\/>/U', $owlThing[0], &$defByLine); 
		$offsetExp=$defByLine[1];
		$offsetNum=$offsetExp+0;
	}
} while($owlThingPresent);


function fPerUrl($url){
// 	echo $url."\n";
	print ' Pinging http://pingthesemanticweb.com/rest/?url=' . rawurlencode($url) . ' ...';
	$fp = fopen('http://pingthesemanticweb.com/rest/?url=' . rawurlencode($url), 'r');
	if ($fp === false) {
		print " failed.\n";
	} else {
		fclose($fp);
		print " done.\n";
	}
}
