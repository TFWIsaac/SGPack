<?php

namespace SgPack;

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