#!/usr/bin/php
<?php
require_once("/usr/local/emhttp/plugins/dynamix.plugin.manager/include/PluginHelpers.php");

$update = $argv[1];
$startTime = time();
$appPaths['Root'] = "/tmp/appFeed";
$appPaths['RepositoriesURL'] = "https://raw.githubusercontent.com/Squidly271/Community-Applications-Moderators/master/Repositories.json";
$appPaths['Repositories'] = "{$appPaths['Root']}/Repositories.json";
$appPaths['Templates'] = "{$appPaths['Root']}/templates/";
$appPaths['newAppFeed'] = "/tmp/GitHub/AppFeed/applicationFeed-raw.json";
$appPaths['oldAppFeed'] = "/tmp/GitHub/AppFeed/old-applicationFeed-raw.json";
$appPaths['AppFeed'] = "/tmp/GitHub/AppFeed/applicationFeed.json";
$appPaths['lastUpdated'] = "/tmp/GitHub/AppFeed/applicationFeed-lastUpdated.json";
$appPaths['firstSeen'] = "/tmp/GitHub/AppFeed/firstSeen.json";
$appPaths['repoInfo'] = "/tmp/GitHub/AppFeed/repoInfo.json";
$appPaths['caVersion'] = "/tmp/GitHub/AppFeed/caVersion";
$appPaths['Running'] = "/var/run/appfeed.pid";
$appPaths['moderationURL'] = "https://raw.githubusercontent.com/Squidly271/Community-Applications-Moderators/master/Moderation.json";
$appPaths['moderation'] = "{$appPaths['Root']}/moderation.json";
$appPaths['statistics'] = "/tmp/GitHub/AppFeed/statistics.json";
$appPaths['PluginTMP'] = "{$appPaths['Root']}/plugin.plg";
$appPaths['iconHTTPSbase'] = "/tmp/GitHub/AppFeed/https-images/";

$master_Categories = array(	
	array("Cat" => "Backup:","Des" => "Backup"),
	array("Cat" => "Cloud:","Des" => "Cloud"),
	array("Cat" => "Downloaders:","Des" => "Downloaders"),
	array("Cat" => "GameServers:","Des" => "Game Servers"),
	array("Cat" => "HomeAutomation:","Des" => "Home Automation"),
	array("Cat" => "Network:", "Des" => "Network Services", "Sub" => array(
		array("Cat" => "Network:Web","Des" => "Web"),
		array("Cat" => "Network:DNS","Des" => "DNS"),
		array("Cat" => "Network:FTP","Des" => "FTP"),
		array("Cat" => "Network:Proxy","Des" => "Proxy"),
		array("Cat" => "Network:VOIP","Des" => "VOIP"),
		array("Cat" => "Network:Management","Des" => "Management"),
		array("Cat" => "Network:Messenger","Des" => "Messenger"),
		array("Cat" => "Network:Other","Des" => "Other")
		)
	),
	array("Cat" => "MediaServer:", "Des"=>"Media Servers","Sub" => array(
		array("Cat" => "MediaServer:Video", "Des" => "Video"),
		array("Cat" => "MediaServer:Music", "Des" => "Music"),
		array("Cat" => "MediaServer:Books", "Des" => "Books"),
		array("Cat" => "MediaServer:Photos", "Des" => "Photos"),
		array("Cat" => "MediaServer:Other", "Des" => "Other")
		)
	),
	array("Cat" => "MediaApp:", "Des"=>"Media Applications", "Sub" => array(
		array("Cat" => "MediaApp:Video", "Des" => "Video"),
		array("Cat" => "MediaApp:Music", "Des" => "Music"),
		array("Cat" => "MediaApp:Books", "Des" => "Books"),
		array("Cat" => "MediaApp:Photos", "Des" => "Photos"),
		array("Cat" => "MediaApp:Other", "Des" => "Other")
		)
	),
	array("Cat" => "Productivity:", "Des" => "Productivity"),
	array("Cat" => "Tools:","Des"=>"Tools / Utilities", "Sub" => array(
		array("Cat" => "Tools:System", "Des" => "System"),
		array("Cat" => "Tools:Utilities", "Des" => "Utilities")
		)
	),
	array("Cat" => "Other:", "Des" => "Other"),
	array("Cat" => "Plugins:", "Des" => "Plugins"),
	array("Cat" => "Drivers:", "Des" => "Drivers")
);

function makeXML($template) {
	# ensure its a v2 template if the Config entries exist
	if ( $template['Config'] && ! $template['@attributes'] ) {
		$template['@attributes'] = array("version"=>2);
	}
	fixAttributes($template,"Network");
	fixAttributes($template,"Config");

	$Array2XML = new Array2XML();
	$xml = $Array2XML->createXML("Container",$template);
	return $xml->saveXML();
}
function fixAttributes(&$template,$attribute) {
	if ( ! is_array($template[$attribute]) ) return;
	if ( $template[$attribute]['@attributes'] ) {
		$template[$attribute][0]['@attributes'] = $template[$attribute]['@attributes'];
		if ( $template[$attribute]['value']) {
			$template[$attribute][0]['value'] = $template[$attribute]['value'];
		}
		unset($template[$attribute]['@attributes']);
		unset($template[$attribute]['value']);
	}

	if ( $template[$attribute] ) {
		foreach ($template[$attribute] as $tempArray) {
			$tempArray2[] = $tempArray['value'] ? array('@attributes'=>$tempArray['@attributes'],'@value'=>$tempArray['value']) : array('@attributes'=>$tempArray['@attributes']);
		}
		$template[$attribute] = $tempArray2;
	}
}
function startsWith($haystack, $needle) {
	if ( !is_string($haystack) || ! is_string($needle) ) return false;
	return $needle === "" || strripos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

function validURL($URL) {
	return filter_var($URL, FILTER_VALIDATE_URL);
}
function get_content_from_registry($url, $current=0) {
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); 
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,1);
	curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	$content = curl_exec($ch);
	if( $content === false && $current < 5 ) {
		sleep(1);
		$current++;
		$content = get_content_from_registry( $url, $current );
	} 
	curl_close($ch);
	return $content;
}
function readJsonFile($filename) {
	$json = json_decode(@file_get_contents($filename),true);
	if ( ! is_array($json) ) { $json = array(); }
	return $json;
}
function writeJsonFile($filename,$jsonArray) {
	file_put_contents($filename,json_encode($jsonArray,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
function download_url($url, $path = "", $bg = false){
	if ( ! strpos($url,"?") ) {
		$url .= "?".time(); # append time to always wind up requesting a non cached version
	}
	exec("curl --compressed --silent --insecure --location --fail ".($path ? " -o '$path' " : "")." $url ".($bg ? ">/dev/null 2>&1 &" : "2>/dev/null"), $out, $exit_code );
	return ($exit_code === 0 ) ? implode("\n", $out) : false;
}
function download_json($url,$path) {
	download_url($url,$path);
	return readJsonFile($path);
}

function removeBool(&$template) {
	foreach ($template as &$entry) {
		if (is_array($entry)) {
			removeBool($entry);
			continue;
		}
		if ($entry === false) {
			$entry = "";
		}
	}
}

function fixSecurity(&$template,&$originalTemplate) {
	foreach ($template as &$element) {
		if ( is_array($element) ) {
			fixSecurity($element,$originalTemplate);
		} else {
			$tempElement = htmlspecialchars_decode($element);
			if ( preg_match('#<script(.*?)>(.*?)</script>#is',$tempElement) || preg_match('#<iframe(.*?)>(.*?)</iframe>#is',$tempElement) ) {
				securityViolation($originalTemplate);
				return;
			}
		}
	}
}
function securityViolation(&$template) {
	logger("VERY IMPORTANT IF YOU SEE THIS: Alert the maintainers of Community Applications with the following Information:".$template['RepoName']." ".$emplate['Name']." ".$template['Repository']);
	$template['Blacklist'] = true;
	$template['ModeratorComment'] = "Blacklisted due to security violations";
}
################################################
# Returns the author from the Repository entry #
################################################
function getAuthor($template) {
	if ( !is_string($template['Repository'])) {
		return false;
	}
	if ( $template['Author'] ) {
		return strip_tags($template['Author']);
	}
	$repoEntry = explode("/",$template['Repository']);
	if (count($repoEntry) < 2) {
		$repoEntry[] = "";
	}
	return strip_tags(explode(":",$repoEntry[count($repoEntry)-2])[0]);
}
###################################################################
# Helper function to remove any formatting, etc from descriptions #
###################################################################
function fixDescription($Description) {
	if ( is_string($Description) ) {
		$Description = preg_replace("#\[br\s*\]#i", "{}", $Description);
		$Description = preg_replace("#\[b[\\\]*\s*\]#i", "||", $Description);
		$Description = preg_replace('#\[([^\]]*)\]#', '<$1>', $Description);
		$Description = preg_replace("#<span.*#si", "", $Description);
		$Description = preg_replace("#<[^>]*>#i", '', $Description);
		$Description = preg_replace("#"."{}"."#i", '<br>', $Description);
		$Description = preg_replace("#"."\|\|"."#i", '<b>', $Description);
		$Description = str_replace("&lt;","<",$Description);
		$Description = str_replace("&gt;",">",$Description);
		$Description = strip_tags($Description);
		$Description = trim($Description);
	} else {
		return "";
	}
	return $Description;
}
function moderateTemplate($template,$moderation,$repositories) {
	global $statistics, $appPaths, $master_Categories;
	
	foreach ($repositories as $repo) {
		if ( is_array($repo['duplicated']) ) {
			$duplicatedTemplate[$repo['url']] = $repo;
		}
	}
	
	if ( is_array($template['Repository']) ) {        	# due to cmer
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository'][0]][] = "Fatal: Multiple Repositories Found - Removing application from lists";
		$template['Blacklist'] = true;
		$template['Repository'] = $template['Repository'][0];
	}
	if ( $template['PluginURL'] ) {                            # due to bonienl
		$template['PluginURL'] = str_replace("raw.github.com","raw.githubusercontent.com",$template['PluginURL']);
		$template['Repository'] = $template['PluginURL'];
	}

	if ( is_array($template['Description']) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Descriptions Found";
		unset($template['Description']);
	}
	if ( is_array($template['Overview']) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Overviews Found";
		unset($template['Overview']);
	}
		

	if ( $template['Overview'] && $template['Description'] ) {
		unset($template['Description']);
	}
	if ( $template['Description'] ) {
		$template['Overview'] = $template['Description'];
		unset($template['Description']);
	}
	
 	$repoTestLwrCase = explode(":",$template['Repository']);
 	if ( ($repoTestLwrCase[0] != strtolower($repoTestLwrCase[0])) && ! $template['Plugin'] ) { # due to sdesbure
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: Invalid repository found.  Only lowercase is allowed";
		$template['Blacklist'] = true;
	}
	if ( (is_array($template['Support'])) && (count($template['Support'])) ) {
		unset($template['Support']);
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Support Tags Found";
	}
	if ( strtolower(trim($template['Support'])) == "http://localhost/" ) { # due to siwat
		unset($template['Support']);
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Invalid support thread (pointed at localhost)";
	}
	if ( ! is_string($template['Name'])  ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: Name is not a string (or multiple names present)";
		$template['Blacklist'] = true;
	}
	if ( ! is_string($template['Author']) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: No author or multiple authors found - Removing application from lists";
		$template['Blacklist'] = true;
	}
	if ( ! $template['Author'] ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: No author could be determined - Removing application from lists";
		$template['Blacklist'] = true;
	}
	if ( is_array($template['Description']) ) {
		if ( count($template['Description']) > 1 ) {
			$template['Description']="";
			$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: Multiple Description tags present";
			$statistics['caFixed']++;
			$template['Blacklist'] = true;
		}
	}
	if ( is_array($template['Beta']) ) {
		$template['Beta'] = "false";
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Beta tags found";
	} else {
		$template['Beta'] = strtolower(stripslashes($template['Beta']));
	}

	$template['Date'] = ( $template['Date'] ) ? strtotime( $template['Date'] ) : $template['DateInstalled'];
	if ( $template['Date'] > strtotime("+2 day") ) {
		echo $template['Date']."   ".strtotime("+2 day");
		$template['Date'] = 0;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Invalid Date Updated (More than 2 days in the future) Format used probably not in http://php.net/manual/en/datetime.formats.date.php";
	}
	
	if ( is_array($template['WebUI']) && count($template['WebUI'] > 1) ) {
		$template['WebUI'] = $template['WebUI'][0]; # due to lsio
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple WebUI entries.  Assuming first one";
	}
		
	if ( $template['WebUI'] ) {
		$template['WebUI'] = trim($template['WebUI']);
		if ( ! startsWith($template['WebUI'],"http:") && ! startsWith($template['WebUI'],"https:") ) { # due to bender }
			unset($template['WebUI']);
			$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Invalid WebUI";
			$statistics['caFixed']++;
		}		
	}
	if ( is_array($template['Category']) ) {
		$template['Category'] = $template['Category'][0];        # due to lsio / CHBMB
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Category tags or Category present but empty";
	}
	if ( ! $template['Category'] ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "No Category present on template";
	}
	
	$ca_Categories = array_column($master_Categories,"Cat");
	foreach ($master_Categories as $cat) {
		if ( is_array($cat['Sub']) ) {
			$ca_Categories = array_merge($ca_Categories,array_column($cat['Sub'],"Cat"));
		}
	}
	$ca_Categories = array_map('strtolower',$ca_Categories);
	
	#Fix where authors make category entries themselves, and don't include the trailing colon (due to #rix1337 and others)
	$categories = explode(" ",$template['Category']);
	unset($template['Category']);
	$colonFlag = false;
	foreach ($categories as $category) {
		if (strtolower($category) == "tools:") {   # minor change in categories
			$category = "Tools:Utilities";
		}
		if ( strlen($category) && ! strpos($category,":") ) {
			if ( ! $colonFlag ) {
				$colonFlag = true;
				$statistics['caFixed']++;
			}
			$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Improperly formed category entry $category (a colon is always present)";
			$category .= ":";
		}
		if ( trim($category) && (strtolower($category) != "status:beta" && strtolower($category) != "status:stable") ) {
			if ( !in_array(strtolower($category),$ca_Categories) ) {
				$statistics['caFixed']++;
				$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Unknown category $category";
				unset($category);
			}
		}
		if ( $category ) {
			$template['Category'] .= "$category ";
		}
	}
	$template['Category'] = trim($template['Category']);
	$template['Category'] = $template['Category'] ?: "Other:Uncategorized";
	if ( ! is_string($template['Category']) ) {
		$template['Category'] = "Other:Uncategorized";
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Category tags or Category present but empty";
	}

	if ( ! $template['Registry'] && ! $template['Plugin']) {
		$repository = explode(":",$template['Repository']);
		$official =  ( count(explode("/",$repository[0])) == 1 ) ? "_" : "r";
		$template['Registry'] = "https://hub.docker.com/$official/{$repository[0]}";
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "No Registry entry set.  Created from Repository setting";
	}
	if ( !is_string($template['Overview']) ) {
		unset($template['Overview']);
	}
	if ( is_array($template['SortAuthor']) ) {                 # due to cmer
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: Multiple Authors / Repositories Found - Removing application from lists";
		$template['Blacklist'] = true;
	}
	if ( is_array($template['PluginURL']) ) {                  # due to coppit
		$template['PluginURL'] = $template['PluginURL'][1];
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: Multiple PluginURL's found";
		$template['Blacklist'] = true;
	}
	$template['OriginalOverview'] = $template['Overview'];
	$template['OriginalDescription'] = $template['Description'];
	if ( strlen($template['Overview']) > 0 ) {
		$template['Description'] = $template['Overview'];
		$template['Description'] = preg_replace('#\[([^\]]*)\]#', '<$1>', $template['Description']);
		$template['Description'] = fixDescription($template['Description']);
		$template['Overview'] = $template['Description'];
	} else {
		$template['Overview'] = fixDescription($template['Description']);
	}
	if ( ($template['Overview'] == $template['OriginalOverview']) || ! $template['OriginalOverview'] ) {
		unset($template['OriginalOverview']);
	}
	if ( ($template['OriginalDescription'] == $template['Description']) || ! $template['OriginalDescription'] ) {
		unset($template['OriginalDescription']);
	}
	$template['Description'] = is_string($template['Overview']) ? $template['Overview'] : "";
	if ( ( ! strlen(trim($template['Overview'])) ) && ( ! strlen(trim($template['Description'])) ) && ! $template['Private'] ){
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Fatal: No valid Overview Or Description present - Application dropped from CA automatically - Possibly far too many formatting tags present";
		$template['Blacklist'] = true;
	}
	if ( is_string($template['Description']) && (stripos($template['Description'],"Converted By Community Applications") !== false) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Blacklisted: Obvious CA conversion templates are disallowed";
		$template['CABlacklist'] = true;
		$template['CAComment'] = "Obvious CA converted templates are disallowed in appfeed";
	}
	if ( is_string($template['Description']) && (stripos($template['Description'],"	Converted By @JustinAiken using Community Applications") !== false) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Blacklisted: Obvious CA conversion templates are disallowed";
		$template['CABlacklist'] = true;
		$template['CAComment'] = "Obvious CA converted templates are disallowed in appfeed";
	}
	
	if ( ( stripos($template['RepoName'],' beta') > 0 )  ) {
		$template['Beta'] = "true";
	}
	$template['Support'] = validURL($template['Support']) ?: "";
	$template['Project'] = validURL($template['Project']) ?: "";
	$template['DonateLink'] = validURL($template['DonateLink']) ?: "";
	$template['DonateText'] = str_replace("'","&#39;",$template['DonateText']);
	$template['DonateText'] = str_replace('"','&quot;',$template['DonateText']);
	
	if ( ! $template['Date'] ) {
		$template['Date'] = (is_numeric($template['DateInstalled'])) ? $template['DateInstalled'] : 0;
	}
	#$template['Date'] = max($template['Date'],$template['FirstSeen']);
	if ($template['Date'] == 1) { # 1 is the default value given by the initial run of appfeed when no date was present
		unset($template['Date']);
	}

	# support v6.2 redefining deprecating the <Beta> tag and moving it to a category
	if ( stripos($template['Category'],":Beta") ) {
		$template['Beta'] = "true";
	} else {
		if ( $template['Beta'] === "true" ) {
			$template['Category'] .= " Status:Beta";
		}
	}

	if ( $template['Private'] ) {
		$statistics = $origStats;
	}

	# fix where template author includes <Blacklist> or <Deprecated> entries in template (CA used booleans, but appfeed winds up saying "FALSE" which equates to be true
	$template['Deprecated'] = filter_var($template['Deprecated'],FILTER_VALIDATE_BOOLEAN);
	$template['Blacklist'] = filter_var($template['Blacklist'],FILTER_VALIDATE_BOOLEAN);

	if ( $template['CPUset'] ) {
		unset($template['CPUset']);
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "CPU pinning removed from template";
	}
	if ( is_string($template['ExtraParams']) ) {
		if ( strpos($template['ExtraParams'],"--cpuset-cpus") !== false ) {
			$extraParams = explode(" ",$template['ExtraParams']);
			foreach ($extraParams as $param) {
				if ( strpos($param,"--cpuset-cpus") === false ) {
					$params .= "$param ";
				}
			}
			$template['ExtraParams'] = rtrim($params);
			$statistics['caFixed']++;
			$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "CPU pinning removed from template";
		}
	}
	if ( ! $template['Icon'] && ! $template['IconFA'] || $template['Icon'] == "/plugins/dynamix.docker.manager/images/question.png" ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "No Icon specified within the application template";
	}
	if ( is_array($template['Icon']) ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "Multiple Icons found";
		unset($template['Icon']);
	}

	if ( $template['Icon'] ) {
		$filename = $appPaths['iconHTTPSbase'].strtolower(str_replace(":","",$template['Repository'])."/".pathinfo($template['Icon'],PATHINFO_BASENAME));

		$filename = str_replace("//","/",$filename);
		$filename = str_replace("%20","_",$filename);
		if (strpos($filename,"?")) {
			$filename = strstr($filename,"?",true);
		}
		exec("mkdir -p ".pathinfo($filename,PATHINFO_DIRNAME));
		if ( download_url($template['Icon'],$filename) === false) {
			if ( ! is_file($filename) ) {
				unset($template['Icon']);
			}
		}
		if (pathinfo($filename,PATHINFO_EXTENSION) !== "svg") {
			if ( ! @getimagesize($filename) ) {
				@unlink($filename);
				unset($template['Icon']);
			}
		}
	}

	if ( $template['Icon']) {
		$template['IconHTTPS'] = str_replace($appPaths['iconHTTPSbase'],"",$filename);
	}
	

	if ( ! $template['Support'] && ! $template['Registry']) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "No Support Link Present";
	}	
	if ( ! $template['Support'] && $template['Registry'] ) {
		$statistics['caFixed']++;
		$statistics['fixedTemplates'][$template['Repo']][$template['Repository']][] = "No Support Link Present.  Using Registry Link instead";
		$template['Support'] = $template['Registry'];
	}
	

	$templateTMP = is_array($moderation[$template['Repository']]) ? array_merge($template,$moderation[$template['Repository']]) : $template;

	if ( $duplicatedTemplate[$templateTMP['RepoURL']]['duplicated'][$template['Repository']] ) {
		$templateTMP['Blacklist'] = true;
		$templateTMP['ModeratorComment'] = "Duplicated Template";
	}
	$template['Changes'] = trim($template['Changes']);
	$template['Overview'] = trim($template['Overview']);
	$template['Description'] = trim($template['Description']);
	if ( $templateTMP['Blacklist'] || $templateTMP['CABlacklist'] ) { #save some bandwidth on blacklisted apps since CA won't let them install
		unset($templateTMP['Registry']);
		unset($templateTMP['GitHub']);
		unset($templateTMP['BindTime']);
		unset($templateTMP['Privileged']);
		unset($templateTMP['Environment']);
		unset($templateTMP['Networking']);
		unset($templateTMP['Network']);
		unset($templateTMP['Data']);
		unset($templateTMP['WebUI']);
		unset($templateTMP['Banner']);
		unset($templateTMP['Date']);
		unset($templateTMP['Config']);
		unset($templateTMP['TemplateURL']);
		unset($templateTMP['ExtraParams']);
		unset($templateTMP['PostArgs']);
		unset($templateTMP['Labels']);
		unset($templateTMP['OriginalOverview']);
		unset($templateTMP['OriginalDescription']);
		unset($templateTMP['Overview']);
		unset($templateTMP['CPUset']);
		unset($templateTMP['MyIP']);
		unset($templateTMP['Shell']);
		unset($templateTMP['DateInstalled']);
	}
	if (! $templateTMP['Blacklist']) {
		unset($templateTMP['Blacklist']);
	}
	if (! $templateTMP['Deprecated']) {
		unset($templateTMP['Deprecated']);
	}
	unset($templateTMP['Banner']);
	unset($templateTMP['DateInstalled']);
	unset($templateTMP['RepoURL']);
	return $templateTMP;
}
#### begin main

if ( is_file($appPaths['Running']) && file_exists("/proc/".@file_get_contents($appPaths['Running'])) ) {
	echo "Already Running\n";
	exit();
}

file_put_contents($appPaths['Running'],getmypid());

exec("rm -rf {$appPaths['Root']}");
exec("mkdir -p {$appPaths['Root']}");

$DockerTemplates = new DockerTemplates();

echo "Downloading Repositories";
$repositories = download_json($appPaths['RepositoriesURL'],$appPaths['Repositories']);
if ( ! $repositories ) { 
	echo "Failed to download repositories";
	@unlink($appPaths['Running']);
	exit(1);
}

echo "\nDownloading Moderation";
$moderation = download_json($appPaths['moderationURL'],$appPaths['moderation']);
if ( ! $moderation ) {
	echo "Failed to download moderation file";
	@unlink($appPaths['Running']);
	exit(1);
}

echo "\n\n\n";


foreach ($repositories as $repo) {
	$friendlyName = str_replace(array(" ","'",'"','\\',"/"),"",$repo['name']);
	$localPath = "{$appPaths['Templates']}$friendlyName";
	echo "Downloading {$repo['name']}: {$repo['url']} -> $localPath";
	
	$downloadURL = tempnam($appPaths['Root'],"CA-Temp-");
	file_put_contents($downloadURL, $repo['url']);

	for ($i=0;$i<5;$i++) {
		$downloaded = $DockerTemplates->downloadTemplates($localPath, $downloadURL);
		if ( $downloaded ) break;
		sleep(30);
		@unlink($downloadURL);
	}
	if ( ! $downloaded ) {
		echo "   Failed...\n";
	} else {
		$templatePaths[] = $info;
		echo "   Success!\n";
		@unlink($downloadURL);
		foreach ($downloaded[$repo['url']] as $xmlFile) {
			echo "Processing $xmlFile...";
			$xml = file_get_contents($xmlFile);
			$o = TypeConverter::xmlToArray($xml,TypeConverter::XML_GROUP);
 			if ( $o['TemplateURL'] == 'https://raw.githubusercontent.com/linuxserver/docker-templates/master/linuxserver.io/$TEMPLATENAME.xml' ) {
				continue;
			}
			if ( ! $o ) {
				echo "Failed\n";
			}
			if ( ! $o['Repository'] && ! $o['PluginURL'] ) {
				echo "Not a valid application xml\n";
				unset($o);
				$o['TemplatePath'] = $xmlFile;
				$apps[] = $o;
				continue;
			}
			
			$o['Repo'] = $repo['name'];
			$o['RepoURL'] = $repo['url'];
			$o['Forum'] = $repo['forum'];
			$o['Support'] = $o['Support'] ?: $o['Forum'];
			if ( !is_string($template['Overview']) ) {
				unset($template['Overview']);
			}

			if ( $o['Date'] == 0 || ! $o['Date']) unset($o['Date']);

			$o['Support'] = validURL($o['Support']) ?: "";
			$o['Project'] = validURL($o['Project']) ?: "";
			$o['Forum'] = validURL($o['Forum']) ?: "";
			$o['DonateLink'] = validURL($o['DonateLink']) ?: $repo['donatelink'];
			$o['DonateText'] = $o['DonateText'] ?: $repo['DonateText'];
			$o['Profile'] = $repo['profile'];
			$o['ModeratorComment'] = $o['ModeratorComment'] ?: $repo['RepoComment'];
			$o['Deprecated'] = $o['Deprecated'] ?: $repo['Deprecated'];
			$o['WebPageURL']       = $repo['web'];
			$o['Logo']             = $repo['logo'];
			$o['Licence']       = $o['License']; # support both spellings
			$o['Author']        = getAuthor($o);
			$o['DockerHubName'] = strtolower($o['Name']);
			$o['RepoName']      = $o['Repo'];
			$o['SortAuthor']    = $o['Author'];
			$o['SortName']      = $o['Name'];
			if ( $o['PluginURL'] ) {
				$o['Author']        = $o['PluginAuthor'];
				$o['Repository']    = $o['PluginURL'];
				$o['SortAuthor']    = $o['Author'];
				$o['SortName']      = $o['Name'];
				if ( strpos($o['Category'],"Plugins:") === false ) {
					$o['Category']      .= " Plugins: ";
				}
				$o['Plugin'] = true;
				unset($o['Changes']);
				@unlink($appPaths['PluginTMP']);
				download_url($o['PluginURL'],$appPaths['PluginTMP']);
				$version = @plugin("version",$appPaths['PluginTMP']);
				$minver = @plugin("min",$appPaths['PluginTMP']);
				$maxver = @plugin("max",$appPaths['PluginTMP']);
				$name = @plugin("name",$appPaths['PluginTMP']);
				@unlink($appPaths['PluginTMP']);
				if ($version) {
					$o['pluginVersion'] = $version;
					$version = preg_replace("/[^0-9\.]/","",$version);
					$version = str_replace(".","-",$version);	
					$versionDate = DateTime::createFromFormat("Y-m-d",$version);
					$o['DateInstalled'] = @date_format($versionDate,"U");
					if ( $o['DateInstalled'] ) {
						$explodeDate = explode("-",$version);
						$o['DateInstalled'] = mktime(0,0,0,$explodeDate[1],$explodeDate[2],$explodeDate[0]);
					}
						
				}
				unset($o['Date']);
				if ($minver) {
					$o['MinVer'] = $minver;
				}
				if ($maxver) {
					$o['MaxVer'] = $maxver;
				}
				if ( $repo['donatelink'] && ! $o['DonateLink'] ) 
					$o['DonateLink'] = $repo['donatelink'];
				if ( $repo['donatetext'] && ! $o['DonateText'] ) 
					$o['DonateText'] = $repo['donatetext'];
			}
			$o = moderateTemplate($o,$moderation,$repositories);
			if ( !$o['Support'] ) unset($o['Support']);
			if ( !$o['Project'] ) unset($o['Project']);
			unset($o['Forum']);
			if ( !$o['DonateLink'] ) unset($o['DonateLink']);
			if ( !$o['ModeratorComment'] ) unset($o['ModeratorComment']);
			if ( !$o['WebPageURL'] ) unset($o['WebPageURL']);
			unset($o['Logo']);
			if ( !$o['Licence'] ) unset($o['Licence']);
			if ( !$o['Changes'] ) unset($o['Changes']);

			if ( !$o['Beta'] || $o['Beta'] == "false") unset($o['Beta']);
			if ( !$o['Date'] || $o['Date'] == 0) unset($o['Date']);
			$o['DonateText'] = str_replace("'","&#39;",$o['DonateText']);
			$o['DonateText'] = str_replace('"','&quot;',$o['DonateText']);
			if ( !$o['DonateText'] ) unset($o['DonateText']);
			
			if ( ( stripos($o['Repo'],' beta') > 0 )  ) {
				$template['Beta'] = "true";
			}

			if ( stripos($o['Category'],":Beta") ) {
				$o['Beta'] = "true";
			} else {
				if ( $o['Beta'] === "true" ) {
					$o['Category'] .= " Status:Beta";
				}
			}
			removeBool($o);
			fixSecurity($o,$o);

			$apps[] = $o;

			echo "Success\n";
		}
		echo "\n\n";
		
	}
}

echo "\n\nUpdating stats on containers\n\n";
$repoInfo = readJsonFile($appPaths['repoInfo']);
$firstSeen = readJsonFile($appPaths['firstSeen']);

foreach ($apps as $app) {
	if ( is_array($app['Repository']) ) { continue; }
	$updateRequired =  ( ( ($startTime - $repoInfo[$app['Repository']]['Time']) > 2592000 )  );
	if ( ! $app['PluginURL'] ) {
		if ( $updateRequired ) {
			$registry = get_content_from_registry( "https://registry.hub.docker.com/v2/repositories/".str_replace(":latest","",$app['Repository']) );
			$registry_json = json_decode( $registry );

/* 			$repotmp = $app['Registry'];
			$repotmp = str_replace( 'https://registry.hub.docker.com/u/', 'https://hub.docker.com/r/', $repotmp );
			$repotmp = ( substr( $repotmp, -1) !== '/') ? $repotmp.'/' : $repotmp;
			$page_data2 = @file_get_contents( $repotmp.'~/dockerfile/' );
			$page_data2 = strip_tags( $page_data2 );
			$page_data2 = trim( preg_replace( '/\s+/', ' ', $page_data2 ) );
			$test3 = explode( 'FROM', $page_data2 );
			$base = trim(preg_replace('/\s+/', ' ', $test3[1] ));
			$base = explode( ' ', trim($base) );
			$base = str_replace('Comments', '', $base[0]);
			if( !empty( $base ) ) {
				$base_image = $base;
			} else {
				$base_image = 'unknown';
			} */
			$base_image = 'unknown';

			$app['Base'] = $base_image;
			$oldDownloads = $repoInfo[$app['Repository']]['Downloads'];
			$app['downloads'] = $registry_json->pull_count;
			$app['stars'] = $registry_json->star_count;
			if ($oldDownloads && $app['downloads']) {
				$app['trending'] = round(( ($app['downloads'] - $oldDownloads) / $app['downloads']) * 100,3);
				$trends = $repoInfo[$app['Repository']]['trends'] ?: array($repoInfo[$app['Repository']]['trending']);
				$trendsDate = $repoInfo[$app['Repository']]['trendsDate'] ?: array();

				if ( $app['trending'] ) {
					$trends[] = $app['trending'];
					$trendsDate[] = time();
				}
				if ( count($trends) > 13 ) {
					$trends = array_shift($trends);
					$trendsDate = array_shift($trendsDate);
				}
				$app['trends'] = $trends;
				$app['trendsDate'] = $trendsDate;
				if ( $app['downloads'] ) {
					$downtrend = $repoInfo[$app['Repository']]['downloadtrend'] ?: array($oldDownloads);
				}
				$downtrend[] = $app['downloads'];
				if ( count($downtrend) > 13 ) {
					$downtrend = array_shift($downtrend);
				}
				$app['downloadtrend'] = $downtrend;
			}
			if ( ($app['downloads'] < 10000) || ($oldDownloads < 10000 ) ) {
				$app['trending'] = 0;
			}
			$app['LastUpdateScan'] = time();

			echo "{$app['Repository']} Downloads: {$app['downloads']}  Stars: {$app['stars']}";
			echo "  Base: {$app['Base']}\n";
		} else {
			$app['Base'] = $repoInfo[$app['Repository']]['Base'];
			$app['downloads'] = $repoInfo[$app['Repository']]['Downloads'];
			$app['stars'] = $repoInfo[$app['Repository']]['Stars'];
			$app['trending'] = $repoInfo[$app['Repository']]['trending'];
			$app['trends'] = $repoInfo[$app['Repository']]['trends'];
			$app['trendsDate'] = $repoInfo[$app['Repository']]['trendsDate'];
			$app['downloadtrend'] = $repoInfo[$app['Repository']]['downloadtrend'];
			$app['LastUpdateScan'] = $repoInfo[$app['Repository']]['Time'];
			
			if ($app['stars'] == 0 || !$app['stars']) unset($app['stars']);
		}
		
		if ( is_array($app['trends']) ) {
			$app['trends'] = array_filter($app['trends'], function($value) { return $value !== null; });
			$app['trends'] = array_values($app['trends']);
		}
		if ( ! $app['trending'] ) { unset($app['trending']); unset($app['trends']); unset($app['downloadtrend']); }
		if ( ! $app['trends'] ) { unset($app['trends']); unset($app['downloadtrend']); unset($app['trendsDate']); }
		if ( ! $app['downloadtrend'] ) unset($app['downloadtrend']);
		if ( ! $app['stars'] ) unset($app['stars']);
		if ( ! $app['downloads'] ) unset($app['downloads']);
		if ( $app['Base'] == "unknown" || !$app['Base']) unset($app['Base']);
	} else {
		if ( $updateRequired ) {
			$app['LastUpdateScan'] = time();
		}
	}
	
	$appRepo = $app['PluginURL'] ?: $app['Repository'];
	if ( ! $firstSeen[$appRepo] ) {
		$firstSeen[$appRepo] = time();
	}
	$app['FirstSeen'] = $firstSeen[$appRepo];

	unset($app['Author']);
	unset($app['DockerHubName']);
	unset($app['SortName']);
	unset($app['SortAuthor']);
	unset($app['RepoName']);

	if ( ! $o['PluginURL'] ) {
		$xml = $app;

		if ( $xml['OriginalOverview'] ) {
			$xml['Overview'] = $xml['OriginalOverview'];
			unset($xml['OriginalOverview']);
		}
		if ( $xml['OriginalDescription'] ) {
			$xml['Description'] = $xml['OriginalDescription'];
			unset($xml['OriginalDescription']);
		}
		unset($xml['Network']);
		unset($xml['MyIP']);
		unset($xml['Shell']);
		unset($xml['Privileged']);
		unset($xml['WebUI']);
		unset($xml['TemplateURL']);
		unset($xml['ExtraParams']);
		unset($xml['PostArgs']);
		unset($xml['CPUset']);
		unset($xml['Networking']);
		unset($xml['Data']);
		unset($xml['Environment']);
		unset($xml['Labels']);
		unset($xml['Config']);
		unset($xml['BindTime']);
		unset($xml['Network']);
	}

	$newApps[] = $app;
	if ( $updateRequired ) {
		$repoInfo[$app['Repository']] = array("Base" => $app['Base'],"Downloads" => $app['downloads'],"Stars" => $app['stars'],"Time" => $app['LastUpdateScan'],"trending" => $app['trending'], "trends" => $app['trends'], "downloadtrend" => $app['downloadtrend'], "trendsDate"=>$app['trendsDate'] );
	}
}
if ( count($newApps) < 500 ) {
	echo "Something really went wrong.  Too few apps";
	@unlink($appPaths['Running']);
	exit(1);
}

echo "Getting current CA Version";
exec("plugin check community.applications.plg");
$caVersion = exec("plugin version /tmp/plugins/community.applications.plg",$output,$returnVal);
if ($returnVal > 0) {
	unset($caVersion);
} else {
	file_put_contents($appPaths['caVersion'],$caVersion);
}

rename($appPaths['newAppFeed'],$appPaths['oldAppFeed']);
writeJsonFile($appPaths['newAppFeed'],$newApps);
$oldApps = readJsonFile($appPaths['oldAppFeed']);
if ( $oldApps !== $newApps ) {
	$appFeed['apps'] = count($newApps);
	$appFeed['last_updated_timestamp'] = $startTime;
	$appFeed['last_updated'] = date("Y-m-d H:i",$startTime);
	$appFeed['categories'] = $master_Categories;
	$appFeed['applist'] = $newApps;

	$lastUpdated['last_updated_timestamp'] = $startTime;
	writeJsonFile($appPaths['AppFeed'],$appFeed);
	writeJsonFile($appPaths['lastUpdated'],$lastUpdated);
}	

writeJsonFile($appPaths['firstSeen'],$firstSeen);
writeJsonFile($appPaths['repoInfo'],$repoInfo);
writeJsonFile($appPaths['statistics'],$statistics);

echo "\nUpdating GitHub\n\n";
passthru("/tmp/GitHub/AppFeed/updateGit.sh");

@unlink($appPaths['Running']);








 /**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/type-converter
 */

/**
 * A class that handles the detection and conversion of certain resource formats / content types into other formats.
 * The current formats are supported: XML, JSON, Array, Object, Serialized
 *
 * @version	2.0.0
 * @package	mjohnson.utility
 */
class TypeConverter {

	/**
	 * Disregard XML attributes and only return the value.
	 */
	const XML_NONE = 0;

	/**
	 * Merge attributes and the value into a single dimension; the values key will be "value".
	 */
	const XML_MERGE = 1;

	/**
	 * Group the attributes into a key "attributes" and the value into a key of "value".
	 */
	const XML_GROUP = 2;

	/**
	 * Attributes will only be returned.
	 */
	const XML_OVERWRITE = 3;

	/**
	 * Returns a string for the detected type.
	 *
	 * @access public
	 * @param mixed $data
	 * @return string
	 * @static
	 */
	public static function is($data) {
		if (self::isArray($data)) {
			return 'array';

		} else if (self::isObject($data)) {
			return 'object';

		} else if (self::isJson($data)) {
			return 'json';

		} else if (self::isSerialized($data)) {
			return 'serialized';

		} else if (self::isXml($data)) {
			return 'xml';
		}

		return 'other';
	}

	/**
	 * Check to see if data passed is an array.
	 *
	 * @access public
	 * @param mixed $data
	 * @return boolean
	 * @static
	 */
	public static function isArray($data) {
		return is_array($data);
	}

	/**
	 * Check to see if data passed is a JSON object.
	 *
	 * @access public
	 * @param mixed $data
	 * @return boolean
	 * @static
	 */
	public static function isJson($data) {
		return (@json_decode($data) !== null);
	}

	/**
	 * Check to see if data passed is an object.
	 *
	 * @access public
	 * @param mixed $data
	 * @return boolean
	 * @static
	 */
	public static function isObject($data) {
		return is_object($data);
	}

	/**
	 * Check to see if data passed has been serialized.
	 *
	 * @access public
	 * @param mixed $data
	 * @return boolean
	 * @static
	 */
	public static function isSerialized($data) {
		$ser = @unserialize($data);

		return ($ser !== false) ? $ser : false;
	}

	/**
	 * Check to see if data passed is an XML document.
	 *
	 * @access public
	 * @param mixed $data
	 * @return boolean
	 * @static
	 */
	public static function isXml($data) {
		$xml = @simplexml_load_string($data);

		return ($xml instanceof SimpleXmlElement) ? $xml : false;
	}

	/**
	 * Transforms a resource into an array.
	 *
	 * @access public
	 * @param mixed $resource
	 * @return array
	 * @static
	 */
	public static function toArray($resource) {
		if (self::isArray($resource)) {
			return $resource;

		} else if (self::isObject($resource)) {
			return self::buildArray($resource);

		} else if (self::isJson($resource)) {
			return json_decode($resource, true);

		} else if ($ser = self::isSerialized($resource)) {
			return self::toArray($ser);

		} else if ($xml = self::isXml($resource)) {
			return self::xmlToArray($xml);
		}

		return $resource;
	}

	/**
	 * Transforms a resource into a JSON object.
	 *
	 * @access public
	 * @param mixed $resource
	 * @return string (json)
	 * @static
	 */
	public static function toJson($resource) {
		if (self::isJson($resource)) {
			return $resource;
		}

		if ($xml = self::isXml($resource)) {
			$resource = self::xmlToArray($xml);

		} else if ($ser = self::isSerialized($resource)) {
			$resource = $ser;
		}

		return json_encode($resource);
	}

	/**
	 * Transforms a resource into an object.
	 *
	 * @access public
	 * @param mixed $resource
	 * @return object
	 * @static
	 */
	public static function toObject($resource) {
		if (self::isObject($resource)) {
			return $resource;

		} else if (self::isArray($resource)) {
			return self::buildObject($resource);

		} else if (self::isJson($resource)) {
			return json_decode($resource);

		} else if ($ser = self::isSerialized($resource)) {
			return self::toObject($ser);

		} else if ($xml = self::isXml($resource)) {
			return $xml;
		}

		return $resource;
	}

	/**
	 * Transforms a resource into a serialized form.
	 *
	 * @access public
	 * @param mixed $resource
	 * @return string
	 * @static
	 */
	public static function toSerialize($resource) {
		if (!self::isArray($resource)) {
			$resource = self::toArray($resource);
		}

		return serialize($resource);
	}

	/**
	 * Transforms a resource into an XML document.
	 *
	 * @access public
	 * @param mixed $resource
	 * @param string $root
	 * @return string (xml)
	 * @static
	 */
	public static function toXml($resource, $root = 'root') {
		if (self::isXml($resource)) {
			return $resource;
		}

		$array = self::toArray($resource);

		if (!empty($array)) {
			$xml = simplexml_load_string('<?xml version="1.0" encoding="utf-8"?><'. $root .'></'. $root .'>');
			$response = self::buildXml($xml, $array);

			return $response->asXML();
		}

		return $resource;
	}

	/**
	 * Turn an object into an array. Alternative to array_map magic.
	 *
	 * @access public
	 * @param object $object
	 * @return array
	 */
	public static function buildArray($object) {
		$array = array();

		foreach ($object as $key => $value) {
			if (is_object($value)) {
				$array[$key] = self::buildArray($value);
			} else {
				$array[$key] = $value;
			}
		}

		return $array;
	}

	/**
	 * Turn an array into an object. Alternative to array_map magic.
	 *
	 * @access public
	 * @param array $array
	 * @return object
	 */
	public static function buildObject($array) {
		$obj = new \stdClass();

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$obj->{$key} = self::buildObject($value);
			} else {
				$obj->{$key} = $value;
			}
		}

		return $obj;
	}

	/**
	 * Turn an array into an XML document. Alternative to array_map magic.
	 *
	 * @access public
	 * @param object $xml
	 * @param array $array
	 * @return object
	 */
	public static function buildXml(&$xml, $array) {
		if (is_array($array)) {
			foreach ($array as $key => $value) {
				// XML_NONE
				if (!is_array($value)) {
					$xml->addChild($key, $value);
					continue;
				}

				// Multiple nodes of the same name
				if (isset($value[0])) {
					foreach ($value as $kValue) {
						if (is_array($kValue)) {
							self::buildXml($xml, array($key => $kValue));
						} else {
							$xml->addChild($key, $kValue);
						}
					}

				// XML_GROUP
				} else if (isset($value['@attributes'])) {
					if (is_array($value['value'])) {
						$node = $xml->addChild($key);
						self::buildXml($node, $value['value']);
					} else {
						$node = $xml->addChild($key, $value['value']);
					}

					if (!empty($value['@attributes'])) {
						foreach ($value['@attributes'] as $aKey => $aValue) {
							$node->addAttribute($aKey, $aValue);
						}
					}

				// XML_MERGE
				} else if (isset($value['value'])) {
					$node = $xml->addChild($key, $value['value']);
					unset($value['value']);

					if (!empty($value)) {
						foreach ($value as $aKey => $aValue) {
							if (is_array($aValue)) {
								self::buildXml($node, array($aKey => $aValue));
							} else {
								$node->addAttribute($aKey, $aValue);
							}
						}
					}

				// XML_OVERWRITE
				} else {
					$node = $xml->addChild($key);

					if (!empty($value)) {
						foreach ($value as $aKey => $aValue) {
							if (is_array($aValue)) {
								self::buildXml($node, array($aKey => $aValue));
							} else {
								$node->addChild($aKey, $aValue);
							}
						}
					}
				}
			}
		}

		return $xml;
	}

	/**
	 * Convert a SimpleXML object into an array.
	 *
	 * @access public
	 * @param object $xml
	 * @param int $format
	 * @return array
	 */
	public static function xmlToArray($xml, $format = self::XML_GROUP) {
		if (is_string($xml)) {
			$xml = @simplexml_load_string($xml);
		}
		if ( ! $xml ) { return false; }
		if (count($xml->children()) <= 0) {
			return (string)$xml;
		}

		$array = array();

		foreach ($xml->children() as $element => $node) {
			$data = array();

			if (!isset($array[$element])) {
#				$array[$element] = "";
				$array[$element] = [];
			}

			if (!$node->attributes() || $format === self::XML_NONE) {
				$data = self::xmlToArray($node, $format);

			} else {
				switch ($format) {
					case self::XML_GROUP:
						$data = array(
							'@attributes' => array(),
							'value' => (string)$node
						);

						if (count($node->children()) > 0) {
							$data['value'] = self::xmlToArray($node, $format);
						}

						foreach ($node->attributes() as $attr => $value) {
							$data['@attributes'][$attr] = (string)$value;
						}
					break;

					case self::XML_MERGE:
					case self::XML_OVERWRITE:
						if ($format === self::XML_MERGE) {
							if (count($node->children()) > 0) {
								$data = $data + self::xmlToArray($node, $format);
							} else {
								$data['value'] = (string)$node;
							}
						}

						foreach ($node->attributes() as $attr => $value) {
							$data[$attr] = (string)$value;
						}
					break;
				}
			}

			if (count($xml->{$element}) > 1) {
				$array[$element][] = $data;
			} else {
				$array[$element] = $data;
			}
		}

		return $array;
	}

	/**
	 * Encode a resource object for UTF-8.
	 *
	 * @access public
	 * @param mixed $data
	 * @return array|string
	 * @static
	 */
	public static function utf8Encode($data) {
		if (is_string($data)) {
			return utf8_encode($data);

		} else if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[utf8_encode($key)] = self::utf8Encode($value);
			}

		} else if (is_object($data)) {
			foreach ($data as $key => $value) {
				$data->{$key} = self::utf8Encode($value);
			}
		}

		return $data;
	}

	/**
	 * Decode a resource object for UTF-8.
	 *
	 * @access public
	 * @param mixed $data
	 * @return array|string
	 * @static
	 */
	public static function utf8Decode($data) {
		if (is_string($data)) {
			return utf8_decode($data);

		} else if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[utf8_decode($key)] = self::utf8Decode($value);
			}

		} else if (is_object($data)) {
			foreach ($data as $key => $value) {
				$data->{$key} = self::utf8Decode($value);
			}
		}

		return $data;
	}

}

 /**
 * Array2XML: A class to convert array in PHP to XML
 * It also takes into account attributes names unlike SimpleXML in PHP
 * It returns the XML in form of DOMDocument class for further manipulation.
 * It throws exception if the tag name or attribute name has illegal chars.
 *
 * Author : Lalit Patel
 * Website: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (10 July 2011)
 * Version: 0.2 (16 August 2011)
 *          - replaced htmlentities() with htmlspecialchars() (Thanks to Liel Dulev)
 *          - fixed a edge case where root node has a false/null/0 value. (Thanks to Liel Dulev)
 * Version: 0.3 (22 August 2011)
 *          - fixed tag sanitize regex which didn't allow tagnames with single character.
 * Version: 0.4 (18 September 2011)
 *          - Added support for CDATA section using @cdata instead of @value.
 * Version: 0.5 (07 December 2011)
 *          - Changed logic to check numeric array indices not starting from 0.
 * Version: 0.6 (04 March 2012)
 *          - Code now doesn't @cdata to be placed in an empty array
 * Version: 0.7 (24 March 2012)
 *          - Reverted to version 0.5
 * Version: 0.8 (02 May 2012)
 *          - Removed htmlspecialchars() before adding to text node or attributes.
 *
 * Usage:
 *       $xml = Array2XML::createXML('root_node_name', $php_array);
 *       echo $xml->saveXML();
 */
class Array2XML {
		private static $xml = null;
	private static $encoding = 'UTF-8';
		/**
		 * Initialize the root XML node [optional]
		 * @param $version
		 * @param $encoding
		 * @param $format_output
		 */
		public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true) {
				self::$xml = new DomDocument($version, $encoding);
				self::$xml->formatOutput = $format_output;
		self::$encoding = $encoding;
		}
		/**
		 * Convert an Array to XML
		 * @param string $node_name - name of the root node to be converted
		 * @param array $arr - aray to be converterd
		 * @return DomDocument
		 */
		public static function &createXML($node_name, $arr=array()) {
				$xml = self::getXMLRoot();
				$xml->appendChild(self::convert($node_name, $arr));
				self::$xml = null;    // clear the xml node in the class for 2nd time use.
				return $xml;
		}
		/**
		 * Convert an Array to XML
		 * @param string $node_name - name of the root node to be converted
		 * @param array $arr - aray to be converterd
		 * @return DOMNode
		 */
		private static function &convert($node_name, $arr=array()) {
				//print_arr($node_name);
				$xml = self::getXMLRoot();
				$node = $xml->createElement($node_name);
				if(is_array($arr)){
						// get the attributes first.;
						if(isset($arr['@attributes'])) {
								foreach($arr['@attributes'] as $key => $value) {
										if(!self::isValidTagName($key)) {
												throw new Exception('[Array2XML] Illegal character in attribute name. attribute: '.$key.' in node: '.$node_name);
										}
										$node->setAttribute($key, self::bool2str($value));
								}
								unset($arr['@attributes']); //remove the key from the array once done.
						}
						// check if it has a value stored in @value, if yes store the value and return
						// else check if its directly stored as string
						if(isset($arr['@value'])) {
								$node->appendChild($xml->createTextNode(self::bool2str($arr['@value'])));
								unset($arr['@value']);    //remove the key from the array once done.
								//return from recursion, as a note with value cannot have child nodes.
								return $node;
						} else if(isset($arr['@cdata'])) {
								$node->appendChild($xml->createCDATASection(self::bool2str($arr['@cdata'])));
								unset($arr['@cdata']);    //remove the key from the array once done.
								//return from recursion, as a note with cdata cannot have child nodes.
								return $node;
						}
				}
				//create subnodes using recursion
				if(is_array($arr)){
						// recurse to get the node for that key
						foreach($arr as $key=>$value){
								if(!self::isValidTagName($key)) {
										throw new Exception('[Array2XML] Illegal character in tag name. tag: '.$key.' in node: '.$node_name);
								}
								if(is_array($value) && is_numeric(key($value))) {
										// MORE THAN ONE NODE OF ITS KIND;
										// if the new array is numeric index, means it is array of nodes of the same kind
										// it should follow the parent key name
										foreach($value as $k=>$v){
												$node->appendChild(self::convert($key, $v));
										}
								} else {
										// ONLY ONE NODE OF ITS KIND
										$node->appendChild(self::convert($key, $value));
								}
								unset($arr[$key]); //remove the key from the array once done.
						}
				}
				// after we are done with all the keys in the array (if it is one)
				// we check if it has any text value, if yes, append it.
				if(!is_array($arr)) {
						$node->appendChild($xml->createTextNode(self::bool2str($arr)));
				}
				return $node;
		}
		/*
		 * Get the root XML node, if there isn't one, create it.
		 */
		private static function getXMLRoot(){
				if(empty(self::$xml)) {
						self::init();
				}
				return self::$xml;
		}
		/*
		 * Get string representation of boolean value
		 */
		private static function bool2str($v){
				//convert boolean to text value.
				$v = $v === true ? 'true' : $v;
				$v = $v === false ? 'false' : $v;
				return $v;
		}
		/*
		 * Check if the tag name or attribute name contains illegal characters
		 * Ref: http://www.w3.org/TR/xml/#sec-common-syn
		 */
		private static function isValidTagName($tag){
				$pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';
				return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
		}
}

/**
 * XML2Array: A class to convert XML to array in PHP
 * It returns the array which can be converted back to XML using the Array2XML script
 * It takes an XML string or a DOMDocument object as an input.
 *
 * See Array2XML: http://www.lalit.org/lab/convert-php-array-to-xml-with-attributes
 *
 * Author : Lalit Patel
 * Website: http://www.lalit.org/lab/convert-xml-to-array-in-php-xml2array
 * License: Apache License 2.0
 *          http://www.apache.org/licenses/LICENSE-2.0
 * Version: 0.1 (07 Dec 2011)
 * Version: 0.2 (04 Mar 2012)
 * 			Fixed typo 'DomDocument' to 'DOMDocument'
 *
 * Usage:
 *       $array = XML2Array::createArray($xml);
 */

class XML2Array {

		private static $xml = null;
	private static $encoding = 'UTF-8';

		/**
		 * Initialize the root XML node [optional]
		 * @param $version
		 * @param $encoding
		 * @param $format_output
		 */
		public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true) {
				self::$xml = new DOMDocument($version, $encoding);
				self::$xml->formatOutput = $format_output;
		self::$encoding = $encoding;
		}

		/**
		 * Convert an XML to Array
		 * @param string $node_name - name of the root node to be converted
		 * @param array $arr - aray to be converterd
		 * @return DOMDocument
		 */
		public static function &createArray($input_xml) {
				$xml = self::getXMLRoot();
		if(is_string($input_xml)) {
			$parsed = $xml->loadXML($input_xml);
			if(!$parsed) {
				throw new Exception('[XML2Array] Error parsing the XML string.');
			}
		} else {
			if(get_class($input_xml) != 'DOMDocument') {
				throw new Exception('[XML2Array] The input XML object should be of type: DOMDocument.');
			}
			$xml = self::$xml = $input_xml;
		}
		$array[$xml->documentElement->tagName] = self::convert($xml->documentElement);
				self::$xml = null;    // clear the xml node in the class for 2nd time use.
				return $array;
		}

		/**
		 * Convert an Array to XML
		 * @param mixed $node - XML as a string or as an object of DOMDocument
		 * @return mixed
		 */
		private static function &convert($node) {
		$output = array();

		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
				$output['@cdata'] = trim($node->textContent);
				break;

			case XML_TEXT_NODE:
				$output = trim($node->textContent);
				break;

			case XML_ELEMENT_NODE:

				// for each child node, call the covert function recursively
				for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
					$child = $node->childNodes->item($i);
					$v = self::convert($child);
					if(isset($child->tagName)) {
						$t = $child->tagName;

						// assume more nodes of same kind are coming
						if(!isset($output[$t])) {
							$output[$t] = array();
						}
						$output[$t][] = $v;
					} else {
						//check if it is not an empty text node
						if($v !== '') {
							$output = $v;
						}
					}
				}

				if(is_array($output)) {
					// if only one node of its kind, assign it directly instead if array($value);
					foreach ($output as $t => $v) {
						if(is_array($v) && count($v)==1) {
							$output[$t] = $v[0];
						}
					}
					if(empty($output)) {
						//for empty nodes
						$output = '';
					}
				}

				// loop through the attributes and collect them
				if($node->attributes->length) {
					$a = array();
					foreach($node->attributes as $attrName => $attrNode) {
						$a[$attrName] = (string) $attrNode->value;
					}
					// if its an leaf node, store the value in @value instead of directly storing it.
					if(!is_array($output)) {
						$output = array('@value' => $output);
					}
					$output['@attributes'] = $a;
				}
				break;
		}
		return $output;
		}

		/*
		 * Get the root XML node, if there isn't one, create it.
		 */
		private static function getXMLRoot(){
				if(empty(self::$xml)) {
						self::init();
				}
				return self::$xml;
		}
}

class DockerTemplates {
	public $verbose = false;

	private function debug($m) {
		if ($this->verbose) echo $m."\n";
	}

	private function removeDir($path) {
		if (is_dir($path)) {
			$files = array_diff(scandir($path), ['.', '..']);
			foreach ($files as $file) {
				$this->removeDir(realpath($path).'/'.$file);
			}
			return rmdir($path);
		} elseif (is_file($path)) return unlink($path);
		return false;
	}

	public function download_url($url, $path='', $bg=false) {
		exec('curl --silent --insecure --location --fail '.($path ? ' -o '.escapeshellarg($path) : '').' '.escapeshellarg($url).' '.($bg ? '>/dev/null 2>&1 &' : '2>/dev/null'), $out, $exit_code);
		return $exit_code===0 ? implode("\n", $out) : false;
	}

	public function listDir($root, $ext=null) {
		$iter = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($root,
						RecursiveDirectoryIterator::SKIP_DOTS),
						RecursiveIteratorIterator::SELF_FIRST,
						RecursiveIteratorIterator::CATCH_GET_CHILD);
		$paths = [];
		foreach ($iter as $path => $fileinfo) {
			$fext = $fileinfo->getExtension();
			if ($ext && $ext != $fext) continue;
			if (substr(basename($path),0,1) == ".")  continue;
			if ($fileinfo->isFile()) $paths[] = ['path' => $path, 'prefix' => basename(dirname($path)), 'name' => $fileinfo->getBasename(".$fext")];
		}
		return $paths;
	}

	public function getTemplates($type) {
		global $dockerManPaths;
		$tmpls = $dirs = [];
		switch ($type) {
		case 'all':
			$dirs[] = $dockerManPaths['templates-user'];
			$dirs[] = $dockerManPaths['templates-usb'];
			break;
		case 'user':
			$dirs[] = $dockerManPaths['templates-user'];
			break;
		case 'default':
			$dirs[] = $dockerManPaths['templates-usb'];
			break;
		default:
			$dirs[] = $type;
		}
		foreach ($dirs as $dir) {
			if (!is_dir($dir)) @mkdir($dir, 0755, true);
			$tmpls = array_merge($tmpls, $this->listDir($dir, 'xml'));
		}
		array_multisort(array_column($tmpls,'name'), SORT_NATURAL|SORT_FLAG_CASE, $tmpls);
		return $tmpls;
	}

	public function downloadTemplates($Dest=null, $Urls=null) {
		global $dockerManPaths;
		$Dest = $Dest ?: $dockerManPaths['templates-usb'];
		$Urls = $Urls ?: $dockerManPaths['template-repos'];
		$repotemplates = $output = [];
		$tmp_dir = '/tmp/tmp-'.mt_rand();
		if (!file_exists($dockerManPaths['template-repos'])) {
			@mkdir(dirname($dockerManPaths['template-repos']), 0777, true);
			@file_put_contents($dockerManPaths['template-repos'], 'https://github.com/limetech/docker-templates');
		}
		$urls = @file($Urls, FILE_IGNORE_NEW_LINES);
		if (!is_array($urls)) return false;
		//$this->debug("\nURLs:\n   ".implode("\n   ", $urls));
		$github_api_regexes = [
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)$%i',
			'%/.*github.com/([^/]*)/(.*).git%i',
			'%/.*github.com/([^/]*)/(.*)%i'
		];
		foreach ($urls as $url) {
			$github_api = ['url' => ''];
			foreach ($github_api_regexes as $api_regex) {
				if (preg_match($api_regex, $url, $matches)) {
					$github_api['user']   = $matches[1] ?? '';
					$github_api['repo']   = $matches[2] ?? '';
					$github_api['branch'] = $matches[3] ?? 'master';
					$github_api['path']   = $matches[4] ?? '';
					$github_api['url']    = sprintf('https://github.com/%s/%s/archive/%s.tar.gz', $github_api['user'], $github_api['repo'], $github_api['branch']);
					break;
				}
			}
			// if after above we don't have a valid url, check for GitLab
			if (empty($github_api['url'])) {
				$source = file_get_contents($url);
				// the following should always exist for GitLab Community Edition or GitLab Enterprise Edition
				if (preg_match("/<meta content='GitLab (Community|Enterprise) Edition' name='description'>/", $source) > 0) {
					$parse = parse_url($url);
					$custom_api_regexes = [
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)$%i',
						'%/'.$parse['host'].'/([^/]*)/(.*).git%i',
						'%/'.$parse['host'].'/([^/]*)/(.*)%i',
					];
					foreach ($custom_api_regexes as $api_regex) {
						if (preg_match($api_regex, $url, $matches)) {
							$github_api['user']   = $matches[1] ?? '';
							$github_api['repo']   = $matches[2] ?? '';
							$github_api['branch'] = $matches[3] ?? 'master';
							$github_api['path']   = $matches[4] ?? '';
							$github_api['url']    = sprintf('https://'.$parse['host'].'/%s/%s/repository/archive.tar.gz?ref=%s', $github_api['user'], $github_api['repo'], $github_api['branch']);
							break;
						}
					}
				}
			}
			if (empty($github_api['url'])) {
				//$this->debug("\n Cannot parse URL ".$url." for Templates.");
				continue;
			}
			if ($this->download_url($github_api['url'], "$tmp_dir.tar.gz") === false) {
				//$this->debug("\n Download ".$github_api['url']." has failed.");
				@unlink("$tmp_dir.tar.gz");
				return null;
			} else {
				@mkdir($tmp_dir, 0777, true);
				shell_exec("tar -zxf $tmp_dir.tar.gz --strip=1 -C $tmp_dir/ 2>&1");
				unlink("$tmp_dir.tar.gz");
			}
			$tmplsStor = [];
			//$this->debug("\n Templates found in ".$github_api['url']);
			foreach ($this->getTemplates($tmp_dir) as $template) {
				$storPath = sprintf('%s/%s', $Dest, str_replace($tmp_dir.'/', '', $template['path']));
				$tmplsStor[] = $storPath;
				if (!is_dir(dirname($storPath))) @mkdir(dirname($storPath), 0777, true);
				if (is_file($storPath)) {
					if (sha1_file($template['path']) === sha1_file($storPath)) {
						//$this->debug("   Skipped: ".$template['prefix'].'/'.$template['name']);
						continue;
					} else {
						@copy($template['path'], $storPath);
						//$this->debug("   Updated: ".$template['prefix'].'/'.$template['name']);
					}
				} else {
					@copy($template['path'], $storPath);
					//$this->debug("   Added: ".$template['prefix'].'/'.$template['name']);
				}
			}
			$repotemplates = array_merge($repotemplates, $tmplsStor);
			$output[$url] = $tmplsStor;
			$this->removeDir($tmp_dir);
		}
		// Delete any templates not in the repos
		foreach ($this->listDir($Dest, 'xml') as $arrLocalTemplate) {
			if (!in_array($arrLocalTemplate['path'], $repotemplates)) {
				unlink($arrLocalTemplate['path']);
				//$this->debug("   Removed: ".$arrLocalTemplate['prefix'].'/'.$arrLocalTemplate['name']."\n");
				// Any other files left in this template folder? if not delete the folder too
				$files = array_diff(scandir(dirname($arrLocalTemplate['path'])), ['.', '..']);
				if (empty($files)) {
					rmdir(dirname($arrLocalTemplate['path']));
					//$this->debug("   Removed: ".$arrLocalTemplate['prefix']);
				}
			}
		}
		return $output;
	}

	public function getTemplateValue($Repository, $field, $scope='all') {
		foreach ($this->getTemplates($scope) as $file) {
			$doc = new DOMDocument();
			$doc->load($file['path']);
			$TemplateRepository = DockerUtil::ensureImageTag($doc->getElementsByTagName('Repository')->item(0)->nodeValue);
			if ($Repository == $TemplateRepository) {
				$TemplateField = $doc->getElementsByTagName($field)->item(0)->nodeValue;
				return trim($TemplateField);
			}
		}
		return null;
	}

	public function getUserTemplate($Container) {
		foreach ($this->getTemplates('user') as $file) {
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->load($file['path']);
			$Name = $doc->getElementsByTagName('Name')->item(0)->nodeValue;
			if ($Name==$Container) return $file['path'];
		}
		return false;
	}

	private function getControlURL(&$ct, $myIP) {
		global $host;
		$port = &$ct['Ports'][0];
		$myIP = $myIP ?: $this->getTemplateValue($ct['Image'], 'MyIP') ?: ($ct['NetworkMode']=='host'||$port['NAT'] ? $host : ($port['IP'] ?: DockerUtil::myIP($ct['Name'])));
		$WebUI = preg_replace("%\[IP\]%", $myIP, $this->getTemplateValue($ct['Image'], 'WebUI'));
		if (preg_match("%\[PORT:(\d+)\]%", $WebUI, $matches)) {
			$ConfigPort = $matches[1];
			foreach ($ct['Ports'] as $port) {
				if ($port['NAT'] && $port['PrivatePort']==$ConfigPort) {$ConfigPort = $port['PublicPort']; break;}
			}
			$WebUI = preg_replace("%\[PORT:\d+\]%", $ConfigPort, $WebUI);
		}
		return $WebUI;
	}

	public function getAllInfo($reload=false) {
		global $dockerManPaths, $host;
		$DockerClient = new DockerClient();
		$DockerUpdate = new DockerUpdate();
		//$DockerUpdate->verbose = $this->verbose;
		$info = DockerUtil::loadJSON($dockerManPaths['webui-info']);
		$autoStart = array_map('var_split', @file($dockerManPaths['autostart-file'], FILE_IGNORE_NEW_LINES) ?: []);
		foreach ($DockerClient->getDockerContainers() as $ct) {
			$name = $ct['Name'];
			$image = $ct['Image'];
			$tmp = &$info[$name] ?? [];
			$tmp['running'] = $ct['Running'];
			$tmp['paused'] = $ct['Paused'];
			$tmp['autostart'] = in_array($name, $autoStart);
			$tmp['cpuset'] = $ct['CPUset'];
			if (!is_file($tmp['icon']) || $reload) $tmp['icon'] = $this->getIcon($image);
			if ($ct['Running']) {
				$port = &$ct['Ports'][0];
				$ip = ($ct['NetworkMode']=='host'||$port['NAT'] ? $host : $port['IP']);
				$tmp['url'] = strpos($tmp['url'],$ip)!==false ? $tmp['url'] : $this->getControlURL($ct, $ip);
				$tmp['shell'] = $tmp['shell'] ?? $this->getTemplateValue($image, 'Shell');
			}
			$tmp['registry'] = $tmp['registry'] ?? $this->getTemplateValue($image, 'Registry');
			$tmp['Support'] = $tmp['Support'] ?? $this->getTemplateValue($image, 'Support');
			$tmp['Project'] = $tmp['Project'] ?? $this->getTemplateValue($image, 'Project');
			if (!$tmp['updated'] || $reload) {
				if ($reload) $DockerUpdate->reloadUpdateStatus($image);
				$vs = $DockerUpdate->getUpdateStatus($image);
				$tmp['updated'] = $vs===null ? null : ($vs===true ? 'true' : 'false');
			}
			if (!$tmp['template'] || $reload) $tmp['template'] = $this->getUserTemplate($name);
			if ($reload) $DockerUpdate->updateUserTemplate($name);
			//$this->debug("\n$name");
			//foreach ($tmp as $c => $d) $this->debug(sprintf('   %-10s: %s', $c, $d));
		}
		DockerUtil::saveJSON($dockerManPaths['webui-info'], $info);
		return $info;
	}

	public function getIcon($Repository) {
		global $docroot, $dockerManPaths;
		$imgUrl = $this->getTemplateValue($Repository, 'Icon');
		preg_match_all("/(.*?):([\S]*$)/i", $Repository, $matches);
		$name = preg_replace("%\/|\\\%", '-', $matches[1][0]);
		$version = $matches[2][0];
		$iconRAM = sprintf('%s/%s-%s-%s.png', $dockerManPaths['images-ram'], $name, $version, 'icon');
		$iconUSB = sprintf('%s/%s-%s-%s.png', $dockerManPaths['images-usb'], $name, $version, 'icon');
		if (!is_dir(dirname($iconRAM))) mkdir(dirname($iconRAM), 0755, true);
		if (!is_dir(dirname($iconUSB))) mkdir(dirname($iconUSB), 0755, true);
		if (!is_file($iconRAM)) {
			if (!is_file($iconUSB)) $this->download_url($imgUrl, $iconUSB);
			@copy($iconUSB, $iconRAM);
		}
		return (is_file($iconRAM)) ? str_replace($docroot, '', $iconRAM) : '';
	}
}
?>