<?php
/**
 * @author Tobias Matzner
 *
 */

class SMWRegistry extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('SMWRegistry', '', false);
	}

	function execute($url = '') {
		global $wgOut, $wgRequest;
		$wgOut->setHTMLtitle('SMW Registry');
		if ('' == $url) {
			$url = $wgRequest->getVal( 'url' );
		}
		$limit = $wgRequest->getVal( 'l' ); // limit
		if ($limit == '') {
			$limit = 10;
		}
		$wiki = $wgRequest->getVal( 'w' ); // wiki text output?
		$html = '';

		if ('' == $url) {
			$specpage = $wgRequest->getVal( 'specpage' );
			if (substr($specpage,-15) == 'Special:Version') {
				$url = substr($specpage, 0, strlen($specpage)-15);
			} elseif ($specpage != '') {
				$html .= '<p>Sorry, the URL you specifed did not end with "Special:Version".</p>';
			}
		}

		$SQLAcc=new SMWRegistrySQL;
		if ('' == $url) {//TODO: hier was sinnvolles ausgeben.

			$html .= '<p>This service allows you to register your semantic wiki site. Doing this helps the SMW Project in many ways: <a href="http://semantic-mediawiki.org/wiki/Registry">Find out why you should register</a>.</p> <p>If you are an administrator, just go to the page <tt>Special:SMWAdmin</tt> on your wiki to register. Otherwise, just enter the URL of the page <tt>Special:Version</tt> on your wiki below (will only work if the URL ends with "Special:Version" whatever language the wiki has).</p>';
			$spectitle = Title::makeTitle( NS_SPECIAL, 'SMWRegistry' );
			$html .= '<form name="smwbrowse" action="' . $spectitle->escapeLocalURL() . '" method="get">' . "\n";
			$html .= '<input type="hidden" name="title" value="' . $spectitle->getPrefixedText() . '"/>' ;
			$html .= '<input type="text" name="specpage" style="width: 80%;" value="http://semantic-mediawiki.org/wiki/Special:Version" />' . "\n";
			$html .= '<input type="submit" value="Register'. "\"/>\n</form>\n";
		    $html .= '</form>';
		} else {
			$query = str_replace( "_", "%20", $url );
			$query = urldecode($query);
			$res=$SQLAcc->updateData($query);
			switch($res['action']){
			case "new": $html .= '<p>Thanks, you have successfully registered your Semantic MediaWiki version ' .  htmlspecialchars($res['version']) . '!</p>';
				break;
			case "confirmed": $html .= '<p>Thanks. Your Semantic MediaWiki version ' . htmlspecialchars($res['version']) .  ' was already registered. Its data has now been updated.</p>';
				break;
			case "updated": $html .= '<p>Thanks, you successfully registered your Semantic MediaWiki update to version ' . htmlspecialchars($res['version']) . '.</p>';;
				break;
			case "na": $html .= "<p>Semantic MediaWiki could not be found at " . htmlspecialchars($query) . " (either the page <a href=\"" .$query. "Special:Version\">Special:Version</a> was not found at all, or SMW's version could not be determined from that page).</p>";
				break;
			case "robots": $html .= "<p>According to robots.txt access to <a href=\"" .$query. "Special:Version\">Special:Version</a> is not allowed. </p>";
				break;
			case "fail": $html .= "<p>An error occured: ".$res['msg']."</p>";
				break;
			}

			$returnto = $wgRequest->getVal( 'return' );
			if ('' != $returnto) {
				$returnto = htmlspecialchars(urldecode($returnto));
				$html .= "<p>Return to  <a href=\"$query$returnto\">$returnto</a>.</p>";
			} else {
				$html .= "<p>Return to  <a href=\"http://semantic-mediawiki.org/wiki/Special:SMWRegistry\">SMW Registry</a>.</p>";
			}
		}

		$html .= '<p><a href="http://semantic-mediawiki.org/wiki/Registry">More information about this service.</a></p>';
		
		$known_sites = $SQLAcc->getAll(true,$limit);
		SMWOutputs::requireHeadItem(SMW_HEADER_SORTTABLE);
		SMWOutputs::commitToOutputPage($wgOut);
		$html .= '<p>Showing the ten most recently updated wikis:</p>';
		$html .= '<table class="smwtable" id="1"><tr><th>Sitename</th><th>SMW version</th><th>Last checked</th><th>Page count</th><th>User count</th><th>Language</th></tr>';
		foreach ($known_sites as $site) {
			$html .= "\n<tr>";
			if ( array_key_exists('name',$site) && ($site['name'] != '') ) {
				$name = $site['name'];
			} else {
				$name = $site['base_url'];
			}
			if ($wiki) {
				$html .= '<td>[' . $site['base_url'] . ' ' . $name . ']</td>';
			} else {
				$html .= '<td><a href="' . $site['base_url'] . '">' . $name . '</a></td>';
			}
			$html .= '<td>' . $site['version'] . '</td>';
			$html .= '<td>' . $site['timestamp'] . '</td>';
			if ( array_key_exists('page_count',$site) ) {
				$html .= '<td>' . $site['page_count'] . '</td>';
			}
			if ( array_key_exists('user_count',$site) ) {
				$html .= '<td>' . $site['user_count'] . '</td>';
			}
			if ( array_key_exists('language',$site) ) {
				$html .= '<td>' . $site['language'] . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		
		$wgOut->addHtml($html);
	}

}

