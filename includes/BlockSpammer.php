<?php

namespace SgPack;

class BlockSpammer
{
    private $patterns = array();

    function __construct()
    {
        global $wgHooks, $sgpCreditsDescription;

        $wgHooks['EditFilter'][] = array($this, 'CheckBlockSpammer');  // register
        $sgpCreditsDescription['antispam'][] = wfMessage('blockspammer-desc')->text();  // description
        $this->patterns = explode(',', wfMessage('blockspammer-regex')->text());// searchexpression (default no external links)
    }

    /* Text-diff
    * http://paulbutler.org/archives/a-simple-diff-algorithm-in-php/
    */
    function _diff($old, $new)
    {
        $maxlen = 0;
        foreach ($old as $oindex => $ovalue) {
            $nkeys = array_keys($new, $ovalue);
            foreach ($nkeys as $nindex) {
                $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ? $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
                if ($matrix[$oindex][$nindex] > $maxlen) {
                    $maxlen = $matrix[$oindex][$nindex];
                    $omax = $oindex + 1 - $maxlen;
                    $nmax = $nindex + 1 - $maxlen;
                }
            }
        }
        if ($maxlen == 0) return array(array('d' => $old, 'i' => $new));
        return array_merge(
            $this->_diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
            array_slice($new, $nmax, $maxlen),
            $this->_diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
    }

    function diff($old, $new)
    {
        // Split string in "words", split on "\r","\n" and " "
        $old = str_replace(array("\r", "\n"), ' ', $old);
        $new = str_replace(array("\r", "\n"), ' ', $new);
        $aold = explode(' ', $old);
        $anew = explode(' ', $new);
        $d = $this->_diff($aold, $anew);
        $iText = $dText = $oText = '';
        foreach ($d as $k) {
            if (is_array($k)) {
                if (!empty($k['d'])) {
                    $dText .= implode(' ', $k['d']);
                }
                if (!empty($k['i'])) {
                    $iText .= implode(' ', $k['i']);
                }
            } else {
                $oText .= $k . ' ';
            }
        }
        return array('o' => $oText, 'd' => $dText, 'i' => $iText);
    }

    function CheckBlockSpammer($editpage, $text, $section, &$error, $sumary)
    {
        global $wgBlockSpammerStayEdit, $wgOut, $wgUser;

        if ($wgUser->mId == 0) { // Test only IP user
            $content = $editpage->getArticle()->getPage()->getContent();    // Original content
            if (!empty($section)) {      // If edit section, only need section content
                $content = $content->getSection($section);
            }
            $diffs = $this->diff($content->getNativeData(), $text);      // Get diff from RAW content
            foreach ($this->patterns as $re) { // loop all spammpatterns
                if (preg_match($re, $sumary . $diffs['i'], $s_matches) === 1) {    // test for pattern
                    // show error page
                    $wgOut->setPageTitle(wfMsg('spamprotectiontitle'));
                    $wgOut->setRobotPolicy('noindex,nofollow');
                    $wgOut->setArticleRelated(false);
                    $wgOut->addWikiMsg('spamprotectiontext');
                    $wgOut->addWikiMsg('spamprotectionmatch', "<nowiki>{$s_matches[0]}</nowiki>");

                    if ($wgBlockSpammerStayEdit) {    // if want to stay in editor
                        $error = wfMessage('blockspammer-stayedit')->text();    // need to set $error
                        return true;    // and return true
                    }
                    $wgOut->returnToMain(false, $editpage->mTitle);
                    return false;
                }
            }
        }
        return true;
    }
}