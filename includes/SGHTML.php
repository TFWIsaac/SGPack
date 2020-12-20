<?php

namespace SgPack;

class SGHTML
{
    function __construct()
    {
        global $wgHooks, $sgpCreditsDescription, $wgVersion;

        // HTML manipulation only works with MW 1.24 and more
        if(version_compare($wgVersion,"1.24",">=")) {
            $wgHooks['OutputPageBeforeHTML'][] = array($this, 'SGHtml');
        }
        $wgHooks['BeforePageDisplay'][] = array($this, 'fnSGHtmlBPD');
        $sgpCreditsDescription['other'][] = wfMessage('sghtml-desc');; // description
    }

    // Last updates before page display
    function fnSGHtmlBPD(&$out) {
        $keywords = wfMessage('sghtml-keywords');    // get keywords
        if ($keywords != '<sghtml-keywords>') {    // test if keywords are defined
            $out->addMeta('keywords', $keywords);
            // foreach($keywords as $word) $out->addKeyword($word);	// put keywords
        }
        return true;
    }

    function SGHtml(&$out, &$text)
    {
        global $wgSGHTMLImageTop, $wgSGHTMLImageEdit;

        // Jump to Top, Edit-Image
        $suchen = array('<h2><span class="mw-headline"',
            '>' . wfMessage('edit') . '</a><span class="mw-editsection-bracket">',
            '<span class="mw-editsection-bracket">[</span>',
            '<span class="mw-editsection-bracket">]</span>');
        $ersatz = array('<h2><a href="javascript:window.scrollTo(0,0);" title="' . wfMessage('sghtml-top') . '" style="vertical-align: top; float: right;"><img src="' . $wgSGHTMLImageTop . '" alt="^" /></a><span class="mw-headline"',
            '><img src="' . $wgSGHTMLImageEdit . '" alt="[' . wfMessage('edit') . ']" style="vertical-align:top; margin-top:-3px;" /></a><span class="mw-editsection-bracket">',
            '', '');
        $text = str_replace($suchen, $ersatz, $text);
        return true;
    }
}