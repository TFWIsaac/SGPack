<?php
if (!defined('MEDIAWIKI')) {
    die('This filse is a MediaWiki extension, it is not a valid entry point');
}

/* UTF-8 save rawencode string for Javascriptcode (=rawurlencode_UTF8) */
function sgpEncode($text)
{
    $encoded = '';
    $length = mb_strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $encoded .= '%' . wordwrap(bin2hex(mb_substr($text, $i, 1)), 2, '%', true);
    }
    return $encoded;
}

/* Extensions-Classes
 * 
 * Importent information how to add new classes.
 * Each class must set its own hook (wgHook, wgParser->setHook or ...).
 * The description must put in $sgpCreditsDescription[<Typ>][].
 * Set the used globalparameter $wgXYZ to default
 * All this must be done in the class-constructor  
 */
