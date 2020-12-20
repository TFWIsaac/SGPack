<?php

namespace SgPack;

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