<?php

namespace SgPack;

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