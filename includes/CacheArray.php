<?php

namespace SgPack;

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