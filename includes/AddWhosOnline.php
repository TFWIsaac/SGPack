<?php

class AddWhosOnline extends SpecialPage
{
    /*function __construct()
    {
        global $wgHooks, $sgpCreditsDescription, $wgSpecialPageGroups;

        $wgSpecialPageGroups['WhosOnline'] = 'users';
        $wgHooks['UserLogout'][] = array($this, 'logOut');
        $wgHooks['PersonalUrls'][] = array($this, 'PersonalUrls');
        $sgpCreditsDescription['other'][] = wfMessage('addwhosonline-desc')->text(); // description
    }*/

    // New Personal Tabs
    static function PersonalUrls(&$personal_urls, &$title)
    {
        global $wgSpecialPageGroups;
        $wgSpecialPageGroups['WhosOnline'] = 'users';
        $sp = \Title::makeTitle(NS_SPECIAL, 'WhosOnline');    // title of the whosonline specialpage
        if ($title->mNamespace != NS_SPECIAL or SpecialPage::getTitleFor('WhosOnline', false)->mTextform != $title->mTextform) {    // be sure we are not on the specialpage
            $a['online'] = array('text' => 'addwhosonline-pmenu', 'href' => $sp->getLocalURL());
            array_splice($personal_urls, -1, 0, $a);   // place new item(s) on second last position
        }
        return true;
    }

    static function logOut(&$user)
    {
        global $wgDBname;

        $db = wfGetDB(DB_MASTER);
        $db->selectDB($wgDBname);
        $db->delete('online', array('userid = ' . $user->mId), __METHOD__);
        return true;
    }
}