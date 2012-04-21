<?php

class SMWRegistrySQL{

	/**
	 * Initialize the table for storing SMW registry information
	 */
	function setup($verbose = true) {
		global $g_datafields;
		print "setting up table for SMW Registry...";
		$db =& wfGetDB( DB_MASTER );
		
		$dbfields = array();
		foreach ($g_datafields as $key => $settings) {
			$dbfields[$key] = $settings[0];
		}

		extract( $db->tableNames('smw_smw_registry') );

		$this->setupTable($smw_smw_registry, $dbfields, $db, $verbose); // FIXME: unknown $smw_smw_registry
		$this->setupIndex($smw_smw_registry, array('base_url','version','timestamp','last_attempt'), $db);
		print "\n\nDone.\n";
	}

	/**
	 * Processes a url: checks for db entry, creates one if not yet present, updates entry otherwise
	 * returns an array.
	 * A field named "action" contains one of the following values:
	 * "new" new entry created
	 * "confirmed" already registered SMW was confirmed 
	 * "updated" version of registered SMW was updated
	 * "na" registered SMW no longer available as indicated by the Special:Version page
	 * "robots" the robots.txt prevents access to Special:Version page
	 * "fail" an error occured
	 *
	 *  A result field named "version" contains the entered, confirmed or new version.
	 *  If the SMW is not accessible or an error occured (na, robots, fail), this field is not defined.
	 *  In case of an error, a field called "msg" contains the exception message (if available)
	 */
	function updateData($url) {
		//check for entry in database
		$entries=$this->getEntryByURL($url);
		$oldversion = '0';
		if ($entries !== false) { // check if we are post 1.1
			$oldversion = $entries[1];
		}
		$curversion = $oldversion;
		if (version_compare($curversion, '1.0.2', '<' )) { // unknown or old wiki, try to find it
			$result = $this->getSpecialVersionData($url);
			if ($result['action'] == 'ok') {
				$curversion = $result['version'];
			}
		}

		if (version_compare($curversion, '1.0.2', '>=' ) || ($result['action'] != 'ok')) { // new wiki, get current stats
			$result2 = $this->getRDFStatData($url);
			if ($result2['action'] == 'ok') {
				$result = $result2;
			}
		}

		$result['base_url'] = $url;

		if ($result['action'] != 'ok') {
			if ($entries !== false) { // write updates only for registered wikis
				$this->updateEntry($url,array ('last_attempt'=>'NOW()'));
			}
			return $result;
		} else { // SMW installation found, check details
			$result['last_attempt'] = 'NOW()';
			$result['timestamp'] = 'NOW()';
			if ($entries !== false) { // wiki is known
				$this->updateEntry($url,$result);
				if ($result['version'] == $entries[1]) { // no version changes
					$result['action']="confirmed";
				} else {  // no changes
					$result['action']="updated";
				}
			} else { // wiki is new, add to database
				$this->updateEntry($url,$result,true);
				$result['action'] = 'new';
			}
			return $result;
		}
	}

	/**
	 * Gets the Special:Version page from some url and tries to find out the SMW version. Returns 
	 * an array.
	 *
	 * A field named "action" contains one of the following values:
	 * "ok" SMW found
	 * "na" registered SMW no longer available as indicated by the Special:Version page
	 * "robots" the robots.txt prevents access to Special:Version page
	 * "fail" an error occured
	 *
	 *  If "action" is "ok", the a result field named "version" contains the version.
	 *  In case of an error, a field called "msg" contains the exception message (if available)
	 */
	protected function getSpecialVersionData($url) {
		global $userAgent;

		$result = array();
		$getURL = $url . 'Special:Version';
		try {
			if ( Robots_txt::urlAllowed($getURL,$userAgent) ) {
				$response = @file_get_contents($getURL, "rb");
			} else { // robots.txt forbids access to Special:Version
				$result['action']="robots";
				return $result;
			}
		} catch (Exception $e) { // something went wrong, maybe illegal URL
			$result['action']="fail";
			$result['msg']=$e->getMessage();
			return $result;
		}

		$occurences = preg_match('/Semantic&nbsp;MediaWiki(<\/a>)? \([^\)]* (.*)\)/',$response, &$smwOccurences);
		if (!$occurences) { // try another one
			$occurences = preg_match('/Semantic MediaWiki(<\/a>)? \([V|v]ersion (.*)\)/',$response, &$smwOccurences);
		}

		if ($occurences) {
			$result['action']="ok";
			$result['version']=$smwOccurences[2];
		} else { //handle no or too many (?) occurences of SMW on the Special:Version page.
			$result['action']="na";
		}
		return $result;
	}


	/**
	 * Gets the Special:ExportRDF?stats page from some url and tries to find out the SMW 
	 * version. Returns an array.
	 *
	 * A field named "action" contains one of the following values:
	 * "ok" SMW found
	 * "robots" the robots.txt prevents access to Special:Version page
	 * "fail" an error occured (includes the case that SMW is not installed, i.e. 404 on stats)
	 *
	 *  If "action" is "ok", the following result fields might be set:
	 *  name, version, user_count, admin_count, page_count, goodpage_count,
	 *  media_count, edit_count, view_count, language
	 *  In case of an error, a field called "msg" contains the exception message (if available)
	 */
	protected function getRDFStatData($url) {
		global $userAgent,$g_datafields;

		$result = array();
		if (strpos($url,'?')) {
			$getURL = $url . 'Special:ExportRDF&stats';
		} else {
			$getURL = $url . 'Special:ExportRDF?stats';
		}
		try {
			if ( Robots_txt::urlAllowed($getURL,$userAgent) ) {
				$response = @file_get_contents($getURL, "rb");
			} else { // robots.txt forbids access to Special:Version
				$result['action']="robots";
				return $result;
			}
		} catch (Exception $e) { // something went wrong, maybe illegal URL
			$result['action']="fail";
			$result['msg']=$e->getMessage();
			return $result;
		}
		$result['action']="ok";

		foreach ($g_datafields as $key => $settings) {
			if ( ($settings[1]) && (preg_match('+>(.*)</' . $settings[1] . '>+',$response, &$match)) ) {
				$result[$key] = $match[1];
			}
		}
		if (!array_key_exists('version',$result)) {
			$result['action'] = 'fail';
			$result['msg']= 'Could not find any recent version of SMW.';
		}
		return $result;
	}


	/**
	 * Fetch all known wikis from DB.
	 */
	function getAll($just_current = false, $limit = 0){
		global $g_datafields;
		$db =& wfGetDB( DB_MASTER );

		$conds = array();
		$options = array();

		if ($just_current) {
			$conds['timestamp'] = 'last_attempt';
		}
		if ($limit > 0) {
			$options['ORDER BY'] = 'last_attempt DESC';
			$options['LIMIT'] = $limit;
		}

		$res = $db->select(
			'smw_smw_registry',
			array( '*' ),
			$conds,
			__METHOD__,
			$options
		);

		$result = false;
		while($row = $db->fetchObject($res)) {
			$data = array();
			foreach ($g_datafields as $key => $settings) {
				$data[$key] = $row->$key; // well, that's PHP to you ...
// 				print "Set $key to " . $row->$key . ".\n";
			}
			$result[] = $data;
		}
		return $result;
	}

	/**
	 * Return data about a single URL, or false if URL was not registered yet.
	 */
	function getEntryByURL($baseURL){
		$db =& wfGetDB( DB_MASTER );
		$res = $db->query("SELECT * FROM smw_smw_registry WHERE base_url = " . $db->addQuotes($baseURL) ,'SMWRegistrySQL::getEntryByURL');
		$result = false;
		while($row = $db->fetchObject($res)) {
			$result = array($row->base_url,$row->version,$row->timestamp);
		}
		return $result;
	}

	/**
	 * Write the given data array (rowname => value) to the DB.
	 */
	function updateEntry($baseURL, $data, $new=false){
		global $g_datafields;
		$db =& wfGetDB( DB_MASTER );
		//$db->update('smw_smw_registry', array('version' => $version), array('base_url' => $baseURL), 'SMWRegistrySQL::updateEntry');
		//the timestamp seems not to be updated on "UPDATE" calls, so force it.
		$setpart = '';
		foreach ($data as $field => $value) {
			if ( ( ($field != 'base_url') || $new) && (array_key_exists($field, $g_datafields)) ) {
				if($setpart != '') {
					$setpart .= ",";
				}
				$setpart .= "$field = ";
				$setpart .= $value=="NOW()" ? $value : $db->addQuotes($value);
			}
		}
		if ($new) {
			$db->query("INSERT smw_smw_registry SET " . $setpart, 'SMWRegistrySQL::updateEntry::new');
		} else {
			$db->query("UPDATE smw_smw_registry SET " . $setpart . " WHERE base_url = " . $db->addQuotes($baseURL), 'SMWRegistrySQL::updateEntry');
		}
	}
	
	function deleteEntry($baseURL){
		$db =& wfGetDB( DB_MASTER );
		$db->delete('smw_smw_registry', array('base_url'=>$baseURL), 'SMWRegistrySQL::deleteEntry');
	}

/*
 *  All following code from SMW_SQLStore.php
 */

	protected function setupTable($table, $fields, $db, $verbose) {
		global $wgDBname;
		$this->reportProgress("Setting up table $table ...\n",$verbose);
		if ($db->tableExists($table) === false) { // create new table
			$sql = 'CREATE TABLE ' . $wgDBname . '.' . $table . ' (';
			$first = true;
			foreach ($fields as $name => $type) {
			if ($first) {
				$first = false;
			} else {
				$sql .= ',';
			}
			$sql .= $name . '  ' . $type;
			}
			$sql .= ') TYPE=innodb';
			$db->query( $sql, 'SMWSQLStore::setupTable' );
			$this->reportProgress("   ... new table created\n",$verbose);
			return array();
		} else { // check table signature
			$this->reportProgress("   ... table exists already, checking structure ...\n",$verbose);
			$res = $db->query( 'DESCRIBE ' . $table, 'SMWSQLStore::setupTable' );
			$curfields = array();
			$result = array();
			while ($row = $db->fetchObject($res)) {
			$type = strtoupper($row->Type);
			if ($row->Null != 'YES') {
				$type .= ' NOT NULL';
			}
			$curfields[$row->Field] = $type;
			}
			$position = 'FIRST';
			foreach ($fields as $name => $type) {
			if ( !array_key_exists($name,$curfields) ) {
				$this->reportProgress(" ... creating column $name ... ",$verbose);
				$db->query("ALTER TABLE $table ADD `$name` $type $position", 'SMWSQLStore::setupTable');
				$result[$name] = 'new';
				$this->reportProgress("done \n",$verbose);
			} elseif ($curfields[$name] != $type) {
				$this->reportProgress("   ... changing type of column $name from '$curfields[$name]' to '$type' ... ",$verbose);
				$db->query("ALTER TABLE $table CHANGE `$name` `$name` $type $position", 'SMWSQLStore::setupTable');
				$result[$name] = 'up';
				$curfields[$name] = false;
				$this->reportProgress("done.\n",$verbose);
			} else {
				$this->reportProgress("   ... column $name is fine\n",$verbose);
				$curfields[$name] = false;
			}
			$position = "AFTER $name";
			}
			foreach ($curfields as $name => $value) {
			if ($value !== false) { // not encountered yet --> delete
				$this->reportProgress("   ... deleting obsolete column $name ... ",$verbose);
				$db->query("ALTER TABLE $table DROP COLUMN `$name`", 'SMWSQLStore::setupTable');
				$result[$name] = 'del';
				$this->reportProgress("done.\n",$verbose);
			}
			}
			$this->reportProgress("   ... table $table set up successfully.\n",$verbose);
			return $result;
		}
	}

	/**
	 * Make sure that each of the column descriptions in the given array is indexed by *one* index
	 * in the given DB table.
	 */
	protected function setupIndex($table, $columns, $db) {
		$table = $db->tableName($table);
		$res = $db->query( 'SHOW INDEX FROM ' . $table , 'SMW::SetupIndex');
		if ( !$res ) {
			return false;
		}
		$indexes = array();
		while ( $row = $db->fetchObject( $res ) ) {
			if (!array_key_exists($row->Key_name, $indexes)) {
			$indexes[$row->Key_name] = array();
			}
			$indexes[$row->Key_name][$row->Seq_in_index] = $row->Column_name;
		}
		foreach ($indexes as $key => $index) { // clean up existing indexes
			$id = array_search(implode(',', $index), $columns );
			if ( $id !== false ) {
			$columns[$id] = false;
			} else { // duplicate or unrequired index
			$db->query( 'DROP INDEX ' . $key . ' ON ' . $table, 'SMW::SetupIndex');
			}
		}

		foreach ($columns as $column) { // add remaining indexes
			if ($column != false) {
				$db->query( "ALTER TABLE $table ADD INDEX ( $column )", 'SMW::SetupIndex');
			}
		}
		return true;
	}

	/**
	 * Print some output to indicate progress. The output message is given by
	 * $msg, while $verbose indicates whether or not output is desired at all.
	 */
	protected function reportProgress($msg, $verbose) {
		if (!$verbose) {
			return;
		}
		if (ob_get_level() == 0) { // be sure to have some buffer, otherwise some PHPs complain
			ob_start();
		}
		print $msg;
		ob_flush();
		flush();
	}

}
