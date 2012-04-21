<?php

/**
 * Initialization file for the SMWRegistry extension.
 *
 * @file SMWRegistry.php
 * @ingroup SMWRegistry
 *
 * @author Tobias Matzner
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @licence GNU GPL v2+
 */

/**
 * This documentation group collects source code files belonging to the SMWRegistry extension.
 *
 * @defgroup SMWRegistry SMWRegistry
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

define( 'SMWR_VERSION', '0.2' );

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'SMWRegistry',
	'version' => SMWR_VERSION,
	'author' => array(
		'Tobias Matzner',
		'[http://www.mediawiki.org/wiki/User:Jeroen_De_Dauw Jeroen De Dauw]',
	),
	'url' => 'http://semantic-mediawiki.org/',
	'descriptionmsg' => 'smwregistry-desc',
);

// i18n
$wgExtensionMessagesFiles['SMWRegistry'] = dirname( __FILE__ ) . '/SMWRegistry.i18n.php';

// Autoloading
$wgAutoloadClasses['SMWRegistry'] = dirname( __FILE__ ) . '/specials/SMWRegistry.php';
$wgAutoloadClasses['Robots_txt'] = dirname( __FILE__ ) . '/includes/Robots.txt.smw.class.php';
$wgAutoloadClasses['SMWRegistrySQL'] = dirname( __FILE__ ) . '/includes/SMWRegistrySQL.php';

$userAgent = 'SMW Registry (http://semantic-mediawiki.org/wiki/Registry)';
ini_set('user_agent', $userAgent);

// central registry: which fields do we store and how are they called in SMW's export?
$g_datafields = array(
	'name'           => array('VARCHAR(255) NOT NULL','swivt:siteName'),
	'base_url'       => array('VARCHAR(255) NOT NULL', false),
	'version'        => array('VARCHAR(255) NOT NULL','swivt:smwVersion'),
	'user_count'     => array('INT(8) UNSIGNED','swivt:userCount'),
	'admin_count'    => array('INT(8) UNSIGNED','swivt:adminCount'),
	'page_count'     => array('INT(8) UNSIGNED','swivt:pageCount'),
	'goodpage_count' => array('INT(8) UNSIGNED','swivt:contentPageCount'),
	'media_count'    => array('INT(8) UNSIGNED','swivt:mediaCount'),
	'edit_count'     => array('INT(8) UNSIGNED','swivt:editCount'),
	'view_count'     => array('INT(8) UNSIGNED','swivt:viewCount'),
	'language'       => array('VARCHAR(10) NOT NULL','swivt:langCode'),
	'timestamp'      => array('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', false),
	'last_attempt'   => array('TIMESTAMP', false)
);