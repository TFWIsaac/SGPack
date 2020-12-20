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

/* Simple Spam Blocker */

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

/* Page Protection */

class PageProtection
{
    // buffer results from fnSGPageProtection to speed up program
    private $lastResult = array();

    function __construct()
    {
        global $wgHooks, $wgParser, $sgpCreditsDescription;

        $wgHooks['userCan'][] = array($this, 'permissionTest'); // check the page permissions
        $wgParser->setHook('user', array($this, 'userTag'));
        $sgpCreditsDescription['parserhook'][] = wfMessage('pageprotection-desc')->text(); // description
    }

    // Handel user-tags, just drop them
    function userTag($input, $argv, $parser)
    {
        return '';
    }

    function permissionTest($title, $user, $action, &$result)
    {
        global $wgPageProtectBlockNamespaces, $wgPageProtectOpenNamespaces, $wgPageProtectOwnerAlways;

        $ttext = $title->getText(); // get Title
        // if article was already tested for this action just give the result
        if (isset($this->lastResult[$ttext][$action])) {
            return $this->lastResult[$ttext][$action];
        }
        // Name of actual user
        $username = $user->getName();
        // do nothing if ...
        // ... action not edit or move
        if (!($action == 'edit' || $action == 'move')) {
            $this->lastResult[$ttext][$action] = true;
            return true;
        }
        //  ... usergroup is free
        if ($user->isAllowed('pageprotection')) {
            $this->lastResult[$ttext][$action] = true;
            return true;
        }
        // ... namespace is not set for PageProtection
        if (!in_array($title->getNamespace(), array_merge($wgPageProtectBlockNamespaces, $wgPageProtectOpenNamespaces))) {
            $this->lastResult[$ttext][$action] = true;
            return true;
        }
        // ... user is owner of article and ownerflag is set (only in USER & USER_TALK namespaces)
        if (($title->getNamespace() == NS_USER) || ($title->getNamespace() == NS_USER_TALK)) {
            // get user name, title and get user part out of title
            $fulltitle = $ttext;
            list($usertitle, $subtitle) = explode('/', $fulltitle, 2);
            if ($wgPageProtectOwnerAlways && ($usertitle == $username)) {
                $this->lastResult[$ttext][$action] = true;
                return true;
            }
        }
        // simple checks are over now, look for user-tags in article content
        // get article (? can we find it already somewhere ?)
        $article = new Article($title);
        $text = $article->fetchContent(0);
        // drop <nowiki>xxx</nowiki> parts
        $expr = '/(.*?)<\s*nowiki\s*>(?s).*?<\/\s*nowiki\s*>(.*?)/i';;
        $replace = '$1';
        $text = preg_replace($expr, $replace, $text);
        // look for <user>**</user>
        if (preg_match("/<\s*user\s*>\s*\*\*\s*<\/\s*user\s*>/", $text) > 0) {
            $this->lastResult[$ttext][$action] = true;
            return true;
        }
        if ($user->mId != 0) {  // normal user
            // look for <user>$username</user>
            if (preg_match("/<\s*user\s*>\s*$username\s*<\/\s*user\s*>/", $text) > 0) {
                $this->lastResult[$ttext][$action] = true;
                return true;
            }
            // look for <user>*</user>
            if (preg_match("/<\s*user\s*>\s*\*\s*<\/\s*user\s*>/", $text) > 0) {
                $this->lastResult[$ttext][$action] = true;
                return true;
            }
        }
        // if open namespaces and no user-tag is defined
        if (in_array($title->getNamespace(), $wgPageProtectOpenNamespaces) && preg_match("/<\s*user\s*>.*<\/\s*user\s*>/", $text) == 0) {
            $this->lastResult[$ttext][$action] = true;
            return true;
        }
        /* Nothing matched so the result ist NOT ALLOWED */
        $this->lastResult[$ttext][$action] = false;
        $result = false;
        return false;
    }
}

/* DropDownInsert */

class DDInsert
{
    private $ddIBlock = array();

    function __construct()
    {
        global $wgParser, $sgpCreditsDescription;

        $wgParser->setHook('jsbutton', array($this, 'JSButton'));
        $wgParser->setHook('ddselect', array($this, 'ddISelect'));
        $wgParser->setHook('ddvalue', array($this, 'ddIValue'));
        $wgParser->setHook('ddbutton', array($this, 'ddIButton'));
        $sgpCreditsDescription['parserhook'][] = wfMessage('ddinsert-desc')->text(); // description
    }

    // JSButton - just for normal use in page
    function JSButton($input, $argv, $parser, $frame)
    {

        $param = 'type="button"';
        $param .= isset($argv['name']) ? ' name = "' . $argv['name'] . '"' : ' name = "jsbutton"';
        $param .= isset($argv['id']) ? ' id = "' . $argv['id'] . '"' : '';
        $param .= isset($argv['value']) ? ' value = "' . $argv['value'] . '"' : '';
        $param .= isset($argv['class']) ? ' class = "' . $argv['class'] . '"' : ' class = "jsbutton"';
        $param .= isset($argv['style']) ? ' style = "' . $argv['style'] . '"' : '';
        $param .= isset($argv['click']) ? ' onclick = "' . $argv['click'] . '"' : '';
        $param .= isset($argv['mover']) ? ' onmouseover = "' . $argv['mover'] . '"' : '';
        $param .= isset($argv['mout']) ? ' onmouseout = "' . $argv['mout'] . '"' : '';
        return '<button ' . $param . '>' . $parser->recursiveTagParse($input, $frame) . '</button>';
    }

    // button
    function ddIButton($input, $argv, $parser, $frame)
    {

        // if no show parameter is given use input also as showText
        $show = isset($argv['show']) ? htmlspecialchars($argv['show']) : $input;
        // get sampleText if given
        $sample = isset($argv['sample']) ? $argv['sample'] : '';
        // picture
        if (isset($argv['picture'])) {
            $image = wfFindFile($argv['picture']);
            if ($image) {
                $iwidth = $image->getWidth();
                $iheight = $image->getHeight();
                // test if picture parameter (iwidth, iheight)
                if (isset($argv['iwidth'])) {
                    $iwidth = intval($argv['iwidth']);
                }
                if (isset($argv['iheight'])) {
                    $iheight = intval($argv['iheight']);
                }
                $show = '<img src="' . $image->getURL() . '" width="' . $iwidth . '" height="' . $iheight . '" />';
            }
        }
        $einput = explode('+', $input);    // split parameter
        $einput[] = '';
        $einput[] = '';    // if to few parameters, fill with ''
        $output = '<a class="ibutton" href="#" onclick="';
        $output .= "mw.sgpack.insert('" . sgpEncode($einput[0] . "+" . $einput[1] . "+" . $sample) . "'); ";
        $output .= 'return false;">' . $show . '</a>';
        return $output;
    }

    // <ddselect title="titleText" size="sizeInt" name="nameText">...</ddselect>
    function ddISelect($input, $argv, $parser, $frame)
    {
        $this->ddIBlock = array('size' => 1, 'name' => 'DDSelect-' . mt_rand(), 'title' => wfMessage('ddinsert-selecttitle'), 'pwidth' => 0, 'pheight' => 1, 'values' => array());
        if (isset($argv['title'])) {
            $this->ddIBlock['title'] = $argv['title'];
        }
        if (isset($argv['size'])) {
            $this->ddIBlock['size'] = $argv['size'];
        }
        if (isset($argv['name'])) {
            $this->ddIBlock['name'] = $argv['name'];
        }
        $parser->recursiveTagParse($input, $frame);
        return $this->ddIOutput();
    }

    // <ddvalue show="showText" sample="sampleText" picture="name">value</ddvalue>
    function ddIValue($input, $argv, $paser, $frame)
    {
        // if no show parameter is given use input also as showText
        $show = isset($argv['show']) ? $argv['show'] : $input;
        // get sampleText if given
        $sample = isset($argv['sample']) ? $argv['sample'] : '';
        // add + to input if not set - need for javascript-split
        if (strpos($input, "+") === false) {
            $input .= "+";
        }
        // picture
        $iURL = '';
        if (isset($argv['picture'])) {
            $image = wfFindFile($argv['picture']);
            if ($image) {
                $iURL = $image->getURL();
                $iwidth = $image->getWidth();
                $iheight = $image->getHeight();
                if ($iwidth > ($this->ddIBlock['pwidth'] - 5)) {
                    $this->ddIBlock['pwidth'] = $iwidth + 5;
                }
                if ($iheight > ($this->ddIBlock['pheight'])) {
                    $this->ddIBlock['pheight'] = $iheight;
                }
            }
        }
        // save parameter to global array
        $this->ddIBlock['values'][] = array('value' => $input . '+' . $sample, 'text' => $show, 'image' => $iURL);
        return '';
    }

    // Create Output
    function ddIOutput()
    {

        $output = '';
        $output .= '<select size="' . $this->ddIBlock['size'] . '" name="' . $this->ddIBlock['name'] . '"';
        $output .= ' onchange="';
        $output .= 'mw.sgpack.insertSelect(this); this.options.selectedIndex = 0; ';
        $output .= 'return false;">';
        $output .= '<option value="++" selected="selected">';
        $output .= $this->ddIBlock['title'];
        $output .= '</option>';
        foreach ($this->ddIBlock['values'] as $values) {
            $output .= $this->ddILine($values['text'], $values['value'], $values['image']);
        }
        $output .= "</select>";
        return $output;
    }

    // create option line
    function ddILine($text, $value, $image)
    {
        if ($this->ddIBlock['pwidth'] > 0) {
            if (!empty($image)) {
                $css = 'style="height: ' . $this->ddIBlock['pheight'] . 'px; padding-left: ' . $this->ddIBlock['pwidth'] . 'px; padding-right: 5px; background-repeat: no-repeat; background-image: url(' . $image . ');"';
            } else {
                $css = 'style="padding-left: ' . $this->ddIBlock['pwidth'] . 'px; padding-right: 5px;"';
            }
        } else {
            $css = '';
        }
        $output = '<option ' . $css . ' value="' . sgpEncode($value) . '">' . $text . '</option>' . "\n";
        return $output;
    }
}

/* Template for new Article */

class NewArticle
{

    function __construct()
    {
        global $wgHooks, $sgpCreditsDescription;

        $wgHooks['AlternateEdit'][] = array($this, 'NewArticle');
        $sgpCreditsDescription['other'][] = wfMessage('newarticle-desc')->text(); // description
    }

    /* Filter article, noinclude remove */
    function FilterPage($text)
    {
        // <noinclude> every thing between remove
        $expr = '/(.*)<noinclude>(?s).*<\/noinclude>(.*)/';
        $replace = '$1$2';
        $text = preg_replace($expr, $replace, $text);
        // <includeonly>, </includeonly> tags just remove
        $expr = '/(.*)<includeonly>|<\/includeonly>(.*)/';
        $replace = '$1$2';
        $text = preg_replace($expr, $replace, $text);
        return $text;
    }

    function NewArticle($seite)
    {
        global $wgOut, $wgParser;

        // check is new article
        if (!$seite->getArticle()->exists()) {
            // load control page "MediaWiki:NewArticle-NS"
            $page = WikiPage::factory(Title::makeTitle(8, 'NewArticle-' . $seite->mTitle->mNamespace));
            $content = $page->getContent();
            // check if something is loaded
            if (!empty($content)) {
                // init buffer
                $html = '';
                $idNr = 0;
                // Seite parsen
                $text = $wgParser->parse($content->getNativeData(), $page->getTitle(), new ParserOptions());
                // Definition der Auswahlliste(n) herrauslösen
                $teile = preg_split('/(\[\[\[.*?\]\]\])/s', $text->mText, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                foreach ($teile as $teil) {
                    // Wenn Auswahlliste [[[...]]]
                    if (substr($teil, 0, 3) == '[[[' and substr($teil, -3, 3) == ']]]') {
                        $teil = substr($teil, 3, count($teil) - 4); // Klammern entfernen
                        $tarray = explode(',', $teil);
                        // Nur ein Argument -> Button statt Liste
                        if (count($tarray) == 1) {
                            $idNr += 1;
                            $zeile = explode('|', $tarray[0]);
                            $zeile[] = '';
                            $zeile[] = '';
                            // Artikel einlesen umwandeln und im HTML Code ablegen
                            $artikel = new Article(Title::makeTitle(10, trim($zeile[0])));
                            $artikel->getContent();
                            if ($artikel->mContentLoaded) {
                                $html .= Xml::element('button', array(
                                    'onclick' => "mw.sgpack.insert('" . sgpEncode('+' . $this->FilterPage($artikel->fetchContent()) . '+') . "');",
                                    'id' => 'NewArticleButton' . $idNr,
                                    'type' => 'button'),
                                    $zeile[1]);
                            }
                            unset($artikel);
                        }
                        if (count($tarray) > 1) {
                            $idNr += 1;
                            // Dropdown Auswahl erstellen
                            $html .= '<select size="1" id="NewArticleSelect' . $idNr . '" onchange="mw.sgpack.insertSelect(this);">' . "\n";
                            // Erstes Element ist Bezeichnung für die "Überschrift"
                            $erst = empty($tarray[0]) ? wfMessage('newarticle-selecttitle') : $tarray[0];
                            $html .= Xml::element('option', array(
                                'selected' => 'selected',
                                'value' => ''),
                                $erst);
                            unset($tarray[0]);
                            foreach ($tarray as $index => $value) {
                                // Die Zeile aufteilen
                                $zeile = explode('|', $value);
                                $zeile[] = '';
                                $zeile[] = '';
                                // Artikel einlesen umwandeln und im HTML Code ablegen
                                $artikel = new Article(Title::makeTitle(10, trim($zeile[0])));
                                $artikel->getContent();
                                if ($artikel->mContentLoaded) {
                                    $html .= Xml::element('option', array(
                                        'value' => sgpEncode('+' . $this->FilterPage($artikel->fetchContent()) . '+')),
                                        $zeile[1]);
                                }
                                unset($artikel);
                            }
                            $html .= '</select>';
                        }
                    } else {    // Sonstigen Text nur übernehmen
                        $html .= $teil;
                    }
                }
                // Ergebniss in die Ausgabe einfuegen
                $wgOut->addHTML($html);
            }
        }
        return true;
    }
}

/* Addon for WhosOnline */

class AddWhosOnline
{
    function __construct()
    {
        global $wgHooks, $sgpCreditsDescription, $wgSpecialPageGroups;

        $wgSpecialPageGroups['WhosOnline'] = 'users';
        $wgHooks['UserLogout'][] = array($this, 'logOut');
        $wgHooks['PersonalUrls'][] = array($this, 'PersonalUrls');
        $sgpCreditsDescription['other'][] = wfMessage('addwhosonline-desc')->text(); // description
    }

    // New Personal Tabs
    function PersonalUrls(&$personal_urls, &$title)
    {
        $sp = Title::makeTitle(NS_SPECIAL, 'WhosOnline');    // title of the whosonline specialpage
        if ($title->mNamespace != NS_SPECIAL or SpecialPage::getTitleFor('WhosOnline', false)->mTextform != $title->mTextform) {    // be sure we are not on the specialpage
            $a['online'] = array('text' => wfMessage('addwhosonline-pmenu')->text(), 'href' => $sp->getLocalURL());
            array_splice($personal_urls, -1, 0, $a);   // place new item(s) on second last position
        }
        return true;
    }

    function logOut(&$user)
    {
        global $wgDBname;

        $db = wfGetDB(DB_MASTER);
        $db->selectDB($wgDBname);
        $db->delete('online', array('userid = ' . $user->mId), __METHOD__);
        return true;
    }
}

/* ParserAdds */

class ParserAdds
{
    function __construct()
    {
        global $wgParser, $sgpCreditsDescription;

        $wgParser->setFunctionHook('trim', array($this, 'trim'), SFH_NO_HASH);
        $wgParser->setFunctionHook('userinfo', array($this, 'userinfo'), SFH_NO_HASH);
        $wgParser->setFunctionHook('in', array($this, 'in'));
        $wgParser->setFunctionHook('tocmod', array($this, 'tocmod'));
        $wgParser->setFunctionHook('recursiv', array($this, 'recursiv'));
        $wgParser->setFunctionHook('link', array($this, 'link'));
        $sgpCreditsDescription['parserhook'][] = wfMessage('parseradds-desc'); // description
    }

    function link(&$parser, $rel = '', $page = '', $title = '')
    {
        global $wgOut;

        if (empty($rel)) return '<strong class="error">' . wfMsgForContent('parseradds_link_norel') . '</strong>';
        if (empty($page)) return '<strong class="error">' . wfMsgForContent('parseradds_link_nopage') . '</strong>';
        if (empty($title)) $title = $page;
        if (!($pt = Title::newFromText($page))) return '<strong class="error">' . wfMsgForContent('parseradds_link_illegalpage') . '</strong>';
        if ($pt->exists()) $wgOut->addLink(array('rel' => $rel, 'title' => $title, 'href' => $pt->getFullURL()));
        return '';
    }

    /* in - ermittelt ob ein oder mehrere Werte in einer Menge enthalten sind
     * $element: Wert(e) die gesucht werden. Mehrere Werte müssen durch $trenn getrennt werden
     * $menge: Menge von Elementen, getrennt durch $trenn
     * $trenn: Trennzeichen, default = ','
     * $modus: Art der Suche. 'a' - Alle Elemente, 'e' - Ein Element
     * $result: Rückgabe bei Erfolg bzw. Misserfolg durch $trenn getrennt 
     * Rückgabe: Gefundene Elemente oder Leer 
     */
    function in(&$parser, $element = '', $menge = '', $trenn = ',', $modus = 'a', $result = '')
    {
        // Parameter prüfen
        if (empty($trenn)) {
            $trenn = ',';
        }
        if (empty($modus)) {
            $modus = 'a';
        }
        // Variablen vorbereiten
        $back = '';
        // Listen in Arrays umwandeln
        $result = explode($trenn, $result);
        $aelement = explode($trenn, $element);
        $amenge = explode($trenn, $menge);
        // Prüfen ob alle Elemente in der Menge
        if ($modus == 'a') {
            $count = count($aelement);
            foreach ($aelement as $wert) {
                if (in_array($wert, $amenge)) {
                    $count -= 1;
                }
            }
            // Alle Elemente gefunden wenn Zähler auf Null
            if ($count == 0) {
                $back = $element;
            }
        }
        // Prüfen ob ein Element in der Menge
        if ($modus == 'e' or $modus == 's') {
            foreach ($aelement as $wert) {
                if (in_array($wert, $amenge)) {
                    $back .= (empty($back) ? '' : $trenn) . $wert;
                }
            }
        }
        // Prüfen ob spezielle Rückgabe erforderlich
        if (empty($back)) {
            if (isset($result[1])) {
                $back = $result[1];
            }
        } else {
            if (!empty($result[0])) {
                $back = $result[0];
            }
        }
        return array($back, 'noparse' => true);
    }

    // trim
    function trim(&$parser, $inStr = '')
    {
        return array(trim($inStr), 'noparse' => true);
    }

    function tocmod(&$parser, $inPara = '', $Default = 'set')
    {
        global $wgOut;

        //$parser->disableCache();
        $back = '';
        if (empty($inPara)) $inPara = $Default;
        $arPara = explode(',', $inPara);
        foreach ($arPara as $para) {
            switch (strtolower($para)) {
                case 'no' :
                    $back .= '__NOTOC__';
                    break;
                case 'set' :
                    $back .= '__TOC__';
                    break;
                case 'hide' :
                    // $wgOut->addInlineScript("function tocHide() { if(document.getElementById('toc')) { var toc = document.getElementById('toc').getElementsByTagName('ul')[0]; var toggleLink = document.getElementById('togglelink'); if(toc.style.display != 'none') { changeText(toggleLink, tocShowText); toc.style.display = 'none'; }}} addOnloadHook(tocHide);");
                    /*$wgOut->addInlineScript("$(function() {
                      var $tocList = $('#toc ul:first');
                      if($tocList.length()) {
                        if(!$tocList.is(':hidden')) {
                          util.toggleToc($('#togglelink'));
                        }
                      }
                    });");*/
                    break;
                case 'show' :
                    // $wgOut->addInlineScript("function tocShow() { if(document.getElementById('toc')) { var toc = document.getElementById('toc').getElementsByTagName('ul')[0]; var toggleLink = document.getElementById('togglelink'); if(toc.style.display != 'block') { changeText(toggleLink, tocHideText); toc.style.display = 'block'; }}} addOnloadHook(tocShow);");
                    break;
                case 'force' :
                    $back .= '__FORCETOC__';
                    break;
            }
        }
        return array($back, 'found' => true);

    }

    // 
    function userinfo(&$parser, $inStr = 'name', $inPara = '')
    {
        global $wgUser, $wgDBname;

        $parser->disableCache();
        $back = '';
        $user = $wgUser;
        switch (strtolower($inStr)) {
            case 'name' :
                $back = $user->mName;
                break;
            case 'id' :
                $back = $user->mId;
                break;
            case 'realname' :
                $back = $user->mRealName;
                break;
            case 'email' :
                if (!empty($inPara)) {
                    $user = User::NewFromName($inPara);
                    if ($user === false) return '<strong class="error">' . wfMsgForContent('parseradds_userinfo_illegal') . '</strong>';
                }
                $back = $user->mEmail;
                break;
            case 'skin' :
                $back = $user->getSkin()->skinname;
                break;
            case 'home' :
                if (!empty($inPara)) {
                    $user = User::NewFromName($inPara);
                    if ($user === false) return '<strong class="error">' . wfMsgForContent('parseradds_userinfo_illegal') . '</strong>';
                }
                $back = '[[' . $user->getUserPage()->getFullText() . ']]';
                break;
            case 'talk' :
                if (!empty($inPara)) {
                    $user = User::NewFromName($inPara);
                    if ($user === false) return '<strong class="error">' . wfMsgForContent('parseradds_userinfo_illegal') . '</strong>';
                }
                $back = '[[' . $user->getUserPage()->getTalkNsText() . $wgUser->mName . ']]';
                break;
            case 'groups' :
                $back = implode(",", $wgUser->mGroups);
                break;
            case 'group' :
                $back = in_array($inPara, $wgUser->mGroups) ? $inPara : '';
                break;
            case 'browser' :
                $back = $_SERVER['HTTP_USER_AGENT'];
                if (!empty($inPara)) {
                    if (FALSE === strpos($back, $inPara)) {
                        $back = '';
                    } else {
                        $back = $inPara;
                    }
                }
                break;
            case 'online' :
                if (function_exists('wfWhosOnline_update_data')) {
                    $db = wfGetDB(DB_SLAVE);
                    $db->selectDB($wgDBname);
                    $res = $db->selectField('online', array('count(*)'), array('username' => $inPara));
                    $back = $res == '1' ? 'online' : 'offline';
                } else {
                    $back = 'unknown';
                }
                break;
        }
        return array($back, 'noparse' => true);
    }

    /* Vorlage mehrfach aufrufen
     * Alle Ausdrücke in () werden an die Vorlage "calltemplate" übergeben.
     * Weitere Parameter "callparameter" werden ebenfalls übergeben.
     * Ausdrücke in [[]] werden nicht beachtet
    */
    function recursiv()
    {
        $p = func_get_args();
        $parser = $p[0];
        $calltemplate = isset($p[1]) ? $p[1] : '';
        $parstext = isset($p[2]) ? $p[2] : '';
        // Weitere Übergabeparameter vorbereiten
        $callparameter = '';
        $i = 3;
        while (isset($p[$i])) {
            $callparameter .= '|' . $p[$i];
            $i++;
        }
        $output = '';
        // Text aufspalten in geklammerte und nicht geklammerte Teile, Elemente in [[]] werden nicht beachtet
        $split = preg_split('/(\[\[.*?\]\]|\(.*?\))/i', $parstext, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        // Alle Elemente parsen
        foreach ($split as $para) {
            if ($para[0] == '(' and $para[strlen($para) - 1] == ')') {
                $sub = substr($para, 1, strlen($para) - 2); // "Ausklammern"
            } else {
                $sub = $para;
            }
            $ask = '{{' . $calltemplate . '|' . $sub . $callparameter . '}}';  // Erzeuge Anfrage
            $result = $parser->recursiveTagParse($ask);
            // Wenn Ergebniss == leer oder == Anfrage dann kennt die Vorlage den Parameter nicht
            if (empty($result) or $result == trim($sub)) {  // Leerzeichen werden nicht zurückgegeben
                $output .= $para;  // Eingabe 1:1 in Ausgabe einfügen
            } else {
                $output .= $ask;  // Ersetze Ausdruck durch Vorlage
            }
        }
        return array($output, 'noparse' => false);
    }

}

/* Cache Array */

class CacheArray
{
    private $Cache = array();
    private $Key_Delimiter = '_';

    function __construct()
    {
        global $wgParser, $sgpCreditsDescription;

        $wgParser->setFunctionHook('carray', array($this, 'carray'), SFH_NO_HASH);
        $wgParser->setFunctionHook('keys', array($this, 'keys'), SFH_NO_HASH);
        $sgpCreditsDescription['parserhook'][] = wfMessage('cachearray-desc'); // description
    }

    // combinate keys 
    function keys()
    {
        // get the parser parameter 
        $param = func_get_args();
        $parser = current($param);
        // get the parts for the key 
        $key = '';
        while ($value = next($param)) {
            // get key-modifier(s) m:key
            $mod = explode(':', $value, 2);
            // if count(mod[]) == 2 means we also have modifier
            if (count($mod) == 2) {
                $value = $mod[1];
                if (strpos($mod[0], 'u') !== false) {  // uppercase
                    $value = strtoupper($value);
                }
                if (strpos($mod[0], 'l') !== false) {  // lowercase
                    $value = strtolower($value);
                }
            } else {
                $value = $mod[0];
            }
            // keys always trim  
            $value = trim($value);
            // drop empty mw-variables
            $value = preg_replace('/\{\{\{.*?\}\}\}/', '', $value);
            // if value is not empty add to key
            if (!empty($value)) {
                if (!empty($key)) {
                    $key .= $this->Key_Delimiter;
                }
                $key .= $value;
            }
        }
        return $key;
    }

    // carray main part
    function carray()
    {
        // minimum parser, cachenumber and action are needed    
        if (func_num_args() < 3) {
            return array('', 'noparse' => true);
        }
        // get the parser parameter 
        $param = func_get_args();
        $parser = current($param);
        // get the first two wiki-parameters (chachenumber, action) 
        $cnumber = trim(next($param));
        $action = strtolower(trim(next($param)));
        // default output is empty
        $output = '';
        // action
        switch ($action) {
            case 'f' :
            case 'file' :
            case 'fr' :
            case 'fileread' :
                // read array out of "file"
                $file = next($param);
                // if carray is already set do not read it again (cache!)
                if (!isset($this->Cache[$cnumber])) {
                    $wp = new WikiPage(Title::newFromText($file));
                    $text = $wp->getContent(Revision::RAW);
                    if ($text) {
                        $content = ContentHandler::getContentText($text);
                        $cont = explode('|', $content);
                        foreach ($cont as $line) {
                            $sp = explode('=', $line, 2);
                            if (count($sp) == 2) {
                                $this->Cache[$cnumber][trim($sp[0])] = trim($sp[1]);
                            }
                        }
                    }
                }
                // leave switch (only if file)
                if (($action === 'f') or ($action === 'file')) {
                    break;
                }
                // read key
                $key = trim(next($param));
                // read cache, if no value, look for default
                if (isset($this->Cache[$cnumber][$key])) {
                    $output = $this->Cache[$cnumber][$key];
                } else {
                    if (isset($this->Cache[$cnumber]['#default'])) {
                        $output = str_replace('{{K}}', $key, $this->Cache[$cnumber]['#default']);
                    }
                }
                break;
            case 'w' :  // only create new carray
            case 'write' :
            case 'rw' :    // write new carray and read one value
            case 'readwrite' :
                // read key (only if readwrite)
                if (($action === 'rw') or ($action === 'readwrite')) {
                    $key = trim(next($param));
                }
                // if carray is already set do not read it again (cache!)
                if (!isset($this->Cache[$cnumber])) {
                    // read the keys and values and save in carray
                    while ($values = next($param)) {
                        $sp = explode('=', $values, 2);
                        if (count($sp) == 2) {
                            $this->Cache[$cnumber][trim($sp[0])] = trim($sp[1]);
                        }
                    }
                }
                // leave switch (only if write)
                if (($action === 'w') or ($action === 'write')) {
                    break;
                }
            case 'r' :    // read value out of carray
            case 'read' :
                // read key, if not already set by action readwrite
                if (!isset($key)) {
                    $key = trim(next($param));
                }
                // read cache, if no value, look for default
                if (isset($this->Cache[$cnumber][$key])) {
                    $output = $this->Cache[$cnumber][$key];
                } else {
                    if (isset($this->Cache[$cnumber]['#default'])) {
                        $output = str_replace('{{K}}', $key, $this->Cache[$cnumber]['#default']);
                    }
                }
                break;
            case 'd' :  // delete carray
            case 'delete' :
                unset($this->Cache[$cnumber]);
                break;
            case 'c' :    // count elements in carray
            case 'count' :
                $output = count($this->Cache[$cnumber]);
                break;
            case 'u' :    // test if cache is used
            case 'used' :
                // if carray is used give size
                if (isset($this->Cache[$cnumber])) {
                    $output = count($this->Cache[$cnumber]);
                }
                break;
        }
        return array($output, 'noparse' => false);
    }
}

class VideoPlayer
{
    var $number = 0;    // counter for the player id on the page

    function __construct()
    {
        global $wgParser, $sgpCreditsDescription;

        $wgParser->setFunctionHook('vplayer', array($this, 'VideoPlayer'));
        //$wgParser->setHook('aplayer',array($this,'AudioPlayer'));
        $sgpCreditsDescription['parserhook'][] = wfMessage('videoplayer-desc')->text(); // description
    }

    // Special trim, looks also for " and ' characters
    private function bigtrim($str)
    {
        return trim($str, " \t\n\r\0\x0B'\"");
    }

    /**
     * Converts an array of values in form [0] => "name=value" into a real
     * associative array in form [name] => value. If value is not set "name"
     * create [name] => 1, used for parameter without a value
     *
     * @param array string $options
     * @return array $results
     */
    function extractOptions(array $options)
    {
        $results = array();

        foreach ($options as $option) {
            $pair = explode('=', $option, 2);
            if (count($pair) == 2) {
                $name = $this->bigtrim($pair[0]);
                $value = $this->bigtrim($pair[1]);
                $results[$name] = $value;
            } else {
                $name = $this->bigtrim($option);
                $value = 1;
                $results[$name] = $value;
            }
        }
        return $results;
    }

    // Audioplayer
    function AudioPlayer($file, $args, $parser, $frame)
    {
    }

    // Videoplayer
    function VideoPlayer(&$parser)
    {
        global $wgOut, $wgScriptPath, $wgSgpVideoPlayerEngine, $wgSgpVideoPlayerCacheOff;

        $opts = func_get_args();    // get all parameters
        unset($opts[0]);    // remove first parameter (==$parser)
        $options = $this->VideoPlayerParser($opts); // create token array out of parameter array

        // No video or playlist -> error
        if (empty($options['video']["value"]) && empty($options['playlist']["value"])) {
            return array('<strong class="error">' . wfMsgForContent('videoplayer-error') . '</strong>', 'noparse' => true, 'isHTML' => true);
        }
        // create single ID for each player
        $this->number++;
        $id = 'video_player_' . $this->number;
        switch($wgSgpVideoPlayerEngine) {  // create output for chosen videoplayer
            case 'jwplayer':
                // container with defined css-class (empty if not defined)
                $html = "<div class='video_player {$options['css']['value']}' id='{$id}'>".wfMsgForContent('videoplayer-load')."</div>";
                if(empty($options["video"]["value"])) { // no video -> it is a playlist
                    $js = "jwplayer('{$id}').setup({ playlist: [ "; // player call, start playlist
                    $comma = "";
                    foreach($options["playlist"]["value"] as $key => $value) { // for each video in the playlist create object
                        $js .= $comma."{ sources: [{ file: '{$value}' }]";  // insert video
                        foreach(array("title","poster","description") as $name) {    // more parameters
                            $js .= " ,{$name}: '{$options[$name]['value'][$key]}'";
                        }
                        $image = wfFindFile($options['poster']['value'][$key]); // look for wiki image
                        if ($image) {   // if image found
                            $js .= " ,image: '".$image->getURL()."'";
                        }
                        $js .= '}'; // close source object
                        $comma = ',';
                    }
                    $js .= "] ";    // close playlist
                    $js .= " ,listbar: { "; // create listbar object
                    $comma = "";
                    foreach(array("position","size","layout") as $name) {
                        $js .= $comma.$name.": '".$options["l.".$name]["value"]."'";
                        $comma = ",";
                    }
                    $js .= "}";     // listbar
                } else {    // single video
                    $js = "jwplayer('{$id}').setup({ file: '{$options['video']['value']}'";    // single player call
                    foreach(array("title","description") as $name) {    // more parameters
                        $js .= " ,{$name}: '{$options[$name]['value'][0]}'";    // all parameters are defined as list!
                    }
                    $image = wfFindFile($options['poster']['value'][0]); // look for wiki image, defined as list!
                    if ($image) {   // if image found
                        $js .= " ,image: '".$image->getURL()."'";
                    }
                }
                // following parameters are the same for playliste and single video
                foreach(array("width","height","autostart","repeat","mute","controls","stretching","displaytitle") as $name) {
                    $js .= " ,{$name}: '{$options[$name]['value']}'";
                }
                // position of player scripts, needed because of problems with mw-resources loader
                $js .= " ,flashplayer: '" . $wgScriptPath . "/extensions/SGPack/jwplayer/jwplayer.flash.swf'";
                $js .= " ,html5player: '" . $wgScriptPath . "/extensions/SGPack/jwplayer/jwplayer.html5.js'";
                // close player init
                $js .= "});";
                break;
            default:    // no player -> error
                return array('<strong class="error">' . wfMsgForContent('videoplayer-noplayer') . '</strong>', 'noparse' => true, 'isHTML' => true);
        }
        $wgOut->addInlineScript($js);   // javascript for the player
        if($wgSgpVideoPlayerCacheOff) { // if no cache
            $parser->disableCache();
        }
        return array($html, 'noparse' => true, 'isHTML' => true); // need HTML
    }

    // Videoplayer Parameter Parser
    private function VideoPlayerParser($parameters) {
        // Define the typ of the value
        define("vp_int",1);     // Integer
        define("vp_str",2);     // String
        define("vp_bool",4);    // Bool - set value true if parameter is given
        define("vp_bool_false",8);  // Bool - set value false if parameter is given
        define("vp_list",1024);  // Comma separated list
        $token = array( "video" => array( "typ" => vp_str, "value" => ""),
            "playlist" => array( "typ" => vp_str|vp_list, "value" => ""),
            "height" => array( "typ" => vp_int, "value" => 270),
            "width" => array( "typ" => vp_int, "value" => 480),
            "title" => array( "typ" => vp_str|vp_list, "value" => ""),
            "poster" => array( "typ" => vp_str|vp_list, "value" => ""),
            "description" => array( "typ" => vp_str|vp_list, "value" => ""),
            "stretching" => array( "typ" => vp_str, "value" => "uniform"),
            "css" => array( "typ" => vp_str, "value" => ""),
            "autostart" => array( "typ" => vp_bool, "value" => "false"),
            "repeat" => array( "typ" => vp_bool, "value" => "false"),
            "mute" => array( "typ" => vp_bool, "value" => "false"),
            "controls" => array( "typ" => vp_bool_false, "value" => "true"),
            "displaytitle" => array( "typ" => vp_bool_false, "value" => "true"),
            "l.position" => array( "typ" => vp_str, "value" => "right"),
            "l.size" => array( "typ" => vp_int, "value" => "150"),
            "l.layout" => array( "typ" => vp_str, "value" => "extended"),

        );

        // split arguments name=value to [name]=>value
        $options = $this->extractOptions($parameters);

        // test every token if set in parameter array
        foreach( $token as $name => $def) {
            if( isset($options[$name])) {   // if parameter is set
                if( $def["typ"] & vp_list) {    // if parameter can be comma list
                    // at the moment we only have vp_str in list
                    $value = explode(",",$options[$name]); // split list
                    foreach($value as $key => $str) {       // trim all parts of the list
                        $value[$key] = $this->bigtrim($str);
                    }
                } else {    // no list parameters
                    $value = $options[$name];
                    switch($options["typ"]) {   // create value by typ od the parameter
                        case vp_int : $value = intval($options[$name]);
                            break;
                        case vp_str : $value = $options[$name];
                            break;
                        case vp_bool : $value = "true";
                            break;
                        case vp_bool_false : $value = "false";
                            break;
                    }
                }
                $token[$name]["value"] = $value;
            }
        }
        return $token;
    }

}

/* Modify HTML page */

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

