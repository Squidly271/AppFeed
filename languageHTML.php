#!/usr/bin/php
<?
$appPaths['languageErrors']  = "/tmp/GitHub/AppFeed/languageErrors.json";
$appPaths['languageHTML']    = "/tmp/GitHub/Squidly271.github.io/languageErrors.html";

$languageErrors = json_decode(file_get_contents($appPaths['languageErrors']),true);

$o = "<!DOCTYPE html><html><head><title>Unraid Missing Translations</title></head><body>";
$o .= "The following entries are missing translations.<br>";
$o .= "Please feel free to contribute to Unraid and fill out these missing translations.  See <a href='https://forums.unraid.net/topic/93770-unraid-webgui-translations-disclaimer/' target='_blank'>This Post</a> for more details";
$o .= "<br><br>NOTE: due to the design of the language files, missing translations within helptext.txt are not able to be listed here";
$o .= "<br><br><font color='green' size='4'>Jump to:</font><br><br>";

foreach ( $languageErrors as $language => $files ) {
	$countryCode = explode(" ",$language)[0];
	$bookmarks .= "<a href='#$countryCode'>$language</a><br>";
	$l .= "<font size='8' id='$countryCode'>$language</font>";
	if ( is_array($files['files']) ) {
		$l .= "<br><br><font size='5'>The following files are missing from the language, and none of the translations are present: (You must copy the files from the en_US repository to the applicable language repository)</font><br><br>";
		foreach ($files['files'] as $file) {
			$l .= "<font color='green'>$file</font><br>";
		}
	}
	
	if ( is_array($files['missing']) ) {
		$l .= "<br><br><font size='5'>The following files do not have these phrases translated:</font>";
		foreach ( $files['missing'] as $file => $errors ) {
			$l .= "<br><br><font size='6'  color='green'>$file</font><br>";
			foreach ($errors as $error) {
				$l .= "<i>\"$error\"</i><br>";
			}
		}
		$l .= "<br><br>";
	}
	
	if ( ! is_array($files['missing']) && ! is_array($files['files']) ) {
		$l .= "<br><br><font size='5' color='green'>Language is currently up to date</font>";
	}
	$l .= "<br><br>";
}
$o .= "$bookmarks<br><br>$l</body></html>";
file_put_contents($appPaths['languageHTML'],$o);
?>