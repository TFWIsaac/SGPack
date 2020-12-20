<?php
file_put_contents("/var/www/tmp/test.txt", "1");
if(!defined('MEDIAWIKI')) { die('This filse is a MediaWiki extension, it is not a valid entry point'); }

// Default credits for the extension-pack
$sgpCredits = array(
    'path' => __FILE__,
    'name' => 'SGPack',
    'version' => '1.5.01',
    'url' => 'http://www.stargate-wiki.de/wiki/Benutzer:Rene/SGPack',
    'author' => '[http://www.stargate-wiki.de/wiki/Benutzer:Rene Ren&eacute; Raule]',
);
$sgpCreditsDescription = array(); // place where classes put their description

$wgAutoloadClasses['BlockSpammer'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['PageProtection'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['DDInsert'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['NewArticle'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['ParserAdds'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['CacheArray'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['SGHTML'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['AddWhosOnline'] = dirname(__FILE__)."/SGPack_body.php";
$wgAutoloadClasses['VideoPlayer'] = dirname(__FILE__)."/SGPack_body.php";

// register extensions
$wgExtensionFunctions[] = 'sgpSetup'; // install classes
$wgHooks['LanguageGetMagic'][] = 'sgpMagic';  // define parser words
$wgHooks['BeforePageDisplay'][] = 'sgpBeforePageDisplay';
$wgExtensionMessagesFiles['sgpMessages'] = dirname(__FILE__) . '/SGPack.i18n.php';	// messages

// Load resources Javascript/CSS
$wgResourceModules['ext.SGPack'] = array(
    'scripts' => array("SGPack.js","jwplayer/jwplayer.js"),
    'styles' => array("SGPack.css"),
    'messages' => '',
    'dependencies' => "ext.wikiEditor.toolbar",
    'position' => 'top',
    'remoteExtPath' => 'SGPack',
    'localBasePath' => dirname(__FILE__)
);

// Classes default parameters

// Bockspammer
$wgSgpBlockSpammer = true;
$wgBlockSpammerStayEdit = true;

// PageProtection
$wgSgpPageProtection = true;
$wgAvailableRights[] = 'pageprotection';
$wgPageProtectBlockNamespaces = array(NS_USER);
$wgPageProtectOpenNamespaces = array(NS_USER_TALK);
$wgPageProtectOwnerAlways = true;
$wgGroupPermissions['sysop']['pageprotection'] = true;

// DDInsert
$wgSgpDDInsert = true;

// VideoPlayer
$wgSgpVideoPlayer = true;
$wgSgpVideoPlayerCacheOff = false;
$wgSgpVideoPlayerEngine = "jwplayer";

// SGHTML
$wgSgpSGHTML = false;
$wgSGHTMLImageTop = $wgScriptPath.'/extensions/SGPack/arrow-up-icon.png';
$wgSGHTMLImageEdit = $wgScriptPath.'/extensions/SGPack/pencil-edit-icon.png';

$wgSgpNewArticle = true;
$wgSgpParserAdds = true;
$wgSgpCacheArray = true;
$wgSgpAddWhosOnline = false;
$wgSgpUseIE9Hack = true;

// Register parser-extension 
function sgpSetup() {
    global $wgExtensionCredits,$sgpCredits,$sgpCreditsDescription,$wgOut;
    global $wgSgpBlockSpammer,$wgSgpPageProtection,$wgSgpDDInsert,$wgSgpNewArticle,$wgSgpParserAdds;
    global $wgSgpCacheArray,$wgSgpSGHTML,$wgSgpAddWhosOnline,$wgSgpVideoPlayer;

    // Load Resources
    $wgOut->addModules('ext.SGPack');
    // create all used extensions-classes
    if($wgSgpBlockSpammer) new BlockSpammer();
    if($wgSgpPageProtection) new PageProtection();
    if($wgSgpDDInsert) new DDInsert();
    if($wgSgpNewArticle) new NewArticle();
    if($wgSgpParserAdds) new ParserAdds();
    if($wgSgpCacheArray) new CacheArray();
    if($wgSgpSGHTML) new SGHTML();
    if($wgSgpVideoPlayer) new VideoPlayer();
    if($wgSgpAddWhosOnline and function_exists('wfWhosOnline_update_data')) new AddWhosOnline();
    // create wgExtensionsCredits for all classes
    foreach($sgpCreditsDescription as $key => $value) {	// loop all keys
    	$credits = $sgpCredits;	// most parts are the same
        $credits['description'] = wfMessage('sgpack-desc')->text();	// title for the description
        $credits['description'] .= "<ul><li>".implode("</li><li>",$value)."</li></ul>";	// create list of descriptions
        $wgExtensionCredits[$key][] = $credits;	// set
    }
    // if($wgSgpAddWhosOnline) $wgExtensionAliasesFiles['WhosOnline'] = dirname(__FILE__) . '/WhosOnline.alias.php';
}

// Define magic words
function sgpMagic( &$magicWords, $langCode ) {
    global $wgSgpParserAdds,$wgSgpCacheArray,$wgSgpVideoPlayer;

    if($wgSgpCacheArray) {	// CacheArray commands
        $magicWords['carray'] = array( 0, 'carray' );
        $magicWords['keys']   = array( 0, 'keys' );
    }
    if($wgSgpParserAdds) {	// ParserAdds commands
        $magicWords['trim']   = array( 0, 'trim' );
        $magicWords['tocmod']   = array( 0, 'tocmod' );
        $magicWords['userinfo']   = array( 0, 'userinfo' );
        $magicWords['recursiv'] = array( 0, 'recursiv' );
        $magicWords['in'] = array(0, 'in');
        $magicWords['link'] = array(0, 'link');
    }
    if($wgSgpVideoPlayer) {	// VideoPlayer commands
        $magicWords['vplayer'] = array( 0, 'vplayer' );
    }
    return true;
}

//
function sgpBeforePageDisplay(&$out,&$skin) {
    global $wgSgpUseIE9Hack;

    // if IE9 hack, set JSVariable to activate hack in SGPack.js
    if($wgSgpUseIE9Hack) {
        $out->addJsConfigVars("mw.sgpack.ie9_hack",1);
    }
}
