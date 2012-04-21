<?php
  /**
   *
   * Visits all registered SMW installations and updates version information and
   * timestamps.
   *
   * With the --verbose option progress and results are diplayed. 
   *
   * With option --init the table in the database is initialized.
   * Repeated calls with this option should not destroy the data.
   *
   * With option --show, the current entries will just be listed
   *
   * @author Tobias Matzner
   */




global $IP;


require_once( 'commandLine.inc' );
require_once( $IP. '/extensions/SMW-Registry/includes/SMWRegistrySQL.php');


$SQLAcc = new SMWRegistrySQL;

$verbose=false;
if ( array_key_exists( 'verbose' , $options ) ) {
	$verbose=true;
}

$showonly=false;
if ( array_key_exists( 'show' , $options ) ) {
	$showonly=true;
}


if ( array_key_exists( 'init' , $options ) ) {
	$SQLAcc->setup();
	die();
}


$entries = $SQLAcc->getAll();

foreach($entries as $en){
	if ($showonly) {
		print '* ' . $en['base_url'] . " version" . $en['version']. ", last check ". $en[timestamp] . ", last retry " . $en['last_attempt'] . "\n";
	} else {
		if($verbose){
			print '* ' . $en['base_url'] . " version" . $en['version']. ", last check ". $en[timestamp] . ", last retry " . $en['last_attempt'] . " ... ";
		}
		
		$res = $SQLAcc->updateData($en['base_url']);
		
		if($verbose) {
			switch($res[action]){
			case "confirmed": print "confirmed \n";
				break;
			case "updated": print "updated to version " . $res['version'] . "\n";
				break;
			case "na": print "not available \n";
				break;
			case "robots": print "robots.txt prohibits crawling \n";
				break;
			case "fail": print "error: " . $res['msg'] . "\n";	
				break;
			}
		}
	}
}
print "done\n";
