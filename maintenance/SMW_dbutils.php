<?php
  /**
   *
   * clears or prints db
   * @author Tobias Matzner
   */


global $IP;


require_once( 'commandLine.inc' );
require_once( $IP. '/extensions/SMW-Registry/includes/SMWRegistrySQL.php');


$SQLAcc = new SMWRegistrySQL;


if ( array_key_exists( 'printdb' , $options ) ) {
	$entries = $SQLAcc->getAll();
	foreach($entries as $en){
	print $en['base_url']." version" . $en['version']. ", last check ". $en[timestamp] . ", last retry " . $en['last_attempt']. "\n";
	}
	die();
}



if ( array_key_exists( 'wipe' , $options ) ) {
	$SQLAcc->wipe();
	die();
}
