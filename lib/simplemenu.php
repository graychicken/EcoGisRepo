<?php

class pSimpleMenu {

    var $baseclass;
    var $Items;

    // Constructor
    function pSimpleMenu($baseclass = 'menu') {
        $this->baseclass = $baseclass;
    }

    // Add Main Item
    function addMainItem($Name, $Label = '', $Link = '', $Visible = true, $Enabled = true, $Status = 'AUTO', $SubStyle = '', $Image = '', $Target = '', $Hint = null, $Tag = 0) {
        $this->Items[$Name]["main"]["Label"] = $Label;
        $this->Items[$Name]["main"]["Link"] = $Link;
        $this->Items[$Name]["main"]["Visible"] = $Visible;
        $this->Items[$Name]["main"]["Enabled"] = $Enabled;
        $this->Items[$Name]["main"]["Status"] = $Status;
        $this->Items[$Name]["main"]["SubStyle"] = $SubStyle;
        $this->Items[$Name]["main"]["Image"] = $Image;
        $this->Items[$Name]["main"]["Target"] = $Target;
        if ($Hint !== null)
            $this->Items[$Name]["main"]["Hint"] = $Hint;
        else
            $this->Items[$Name]["main"]["Hint"] = $Label;
        $this->Items[$Name]["main"]["Tag"] = $Tag;
    }

    /**
     * Add Menu sub Item
     * @since Version 1.2.0
     *
     * @param string $mainItemName  main item name
     * @param string $itemName      item name
     * @param array $opt            options
     */
    function addSimpleItem($mainItemName, $itemName, array $opt = array()) {

        if (!isset($this->Items[$mainItemName])) {
            return false;
        }
        $opt = array_merge(array('label' => null, 'url' => null, 'js' => null, 'visible' => true,
            'enabled' => true, 'style' => null, 'image' => null, 'target' => null,
            'hint' => null, 'tag' => 0), $opt);
        if ($opt['js'] !== null) {
            $opt['url'] = 'javascript:' . $opt['js'];
        }

        $index = count($this->Items[$mainItemName]) - 1;
        $this->Items[$mainItemName][$index]["ItemName"] = $itemName;
        $this->Items[$mainItemName][$index]["Label"] = $opt['label'];
        $this->Items[$mainItemName][$index]["Link"] = $opt['url'];
        $this->Items[$mainItemName][$index]["Visible"] = $opt['visible'];
        $this->Items[$mainItemName][$index]["Enabled"] = $opt['enabled'];
        $this->Items[$mainItemName][$index]["SubStyle"] = $opt['style'];
        $this->Items[$mainItemName][$index]["Image"] = $opt['image'];
        $this->Items[$mainItemName][$index]["Target"] = $opt['target'];
        if ($opt['hint'] !== null) {
            $this->Items[$mainItemName][$index]["Hint"] = $opt['hint'];
        } else {
            $this->Items[$mainItemName][$index]["Hint"] = $opt['label'];
        }
        $this->Items[$mainItemName][$index]["Tag"] = $opt['tag'];
        return true;
    }

    /**
     * Add Menu divider
     * @since Version 1.2.0
     *
     * @param string $mainItemName  
     * @param string $itemName
     * @param $Label (if value contains "-", MenuItem will be a spacer
     * @param $Link
     * @param $Visible
     * @param $Enabled
     * @param $SubStyle
     * @param $Image
     * @param $Target
     * @param $Hint
     * @param $Tag
     */
    function addDivider($mainItemName) {

        if (!isset($this->Items[$mainItemName])) {
            return false;
        }
        $index = count($this->Items[$mainItemName]) - 1;

        // check for previous divider
        if (!empty($this->Items[$mainItemName][$index - 1]) && $this->Items[$mainItemName][$index - 1]["Label"] === '-') {
            return false;
        }

        $this->Items[$mainItemName][$index]["ItemName"] = null;
        $this->Items[$mainItemName][$index]["Label"] = '-';
        $this->Items[$mainItemName][$index]["Link"] = null;
        $this->Items[$mainItemName][$index]["Visible"] = true;
        $this->Items[$mainItemName][$index]["Enabled"] = true;
        $this->Items[$mainItemName][$index]["SubStyle"] = null;
        $this->Items[$mainItemName][$index]["Image"] = null;
        $this->Items[$mainItemName][$index]["Target"] = null;
        $this->Items[$mainItemName][$index]["Hint"] = null;
        $this->Items[$mainItemName][$index]["Tag"] = null;
        return true;
    }

    /**
     * Add Menu Item
     * @since Version 1.0.0
     *
     * @param $Name
     * @param $ItemName
     * @param $Label (if value contains "-", MenuItem will be a spacer
     * @param $Link
     * @param $Visible
     * @param $Enabled
     * @param $SubStyle
     * @param $Image
     * @param $Target
     * @param $Hint
     * @param $Tag
     */
    function addItem($Name, $ItemName, $Label = '', $Link = '', $Visible = true, $Enabled = true, $SubStyle = '', $Image = '', $Target = '', $Hint = null, $Tag = 0) {
        if (!isset($this->Items[$Name]))
            return;
        $index = count($this->Items[$Name]) - 1;
        $this->Items[$Name][$index]["ItemName"] = $ItemName;
        $this->Items[$Name][$index]["Label"] = $Label;
        $this->Items[$Name][$index]["Link"] = $Link;
        $this->Items[$Name][$index]["Visible"] = $Visible;
        $this->Items[$Name][$index]["Enabled"] = $Enabled;
        $this->Items[$Name][$index]["SubStyle"] = $SubStyle;
        $this->Items[$Name][$index]["Image"] = $Image;
        $this->Items[$Name][$index]["Target"] = $Target;
        if ($Hint !== null)
            $this->Items[$Name][$index]["Hint"] = $Hint;
        else
            $this->Items[$Name][$index]["Hint"] = $ItemName;
        $this->Items[$Name][$index]["Tag"] = $Tag;
    }

    function itemOrder($order) {

        if (is_array($order)) {
            $ret = array();
            while (list($key, $val) = each($order)) {
                $ret[$val] = $this->Items[$val];
            }
        } else
            $ret = $this->Items;

        return $ret;
    }

    function isGroupOpen($groupName, $activeItem) {
        $ret = false;
        for ($i = 0; $i < (count($this->Items[$groupName]) - 1); $i++) {
            if ($this->Items[$groupName][$i]["ItemName"] == $activeItem) {
                $ret = true;
                break;
            }
        }

        return $ret;
    }

    // Parameter
    //  - order: Definition of group order (array with Main Item Names as values)
    //  - linkStyle: Tag reference for link (TD=Link as TD-Tag, A=Link as A-Tag)
    function output($order = '', $linkStyle = 'TD') {

        global $MenuItem;

        $activeItem = $MenuItem;
        $output = '';

        // Reorder Items
        $itemArr = $this->itemOrder($order);

        // Javascript
        $output .= "<script type=\"text/javascript\" language=\"JavaScript\"> \n" .
                "  function showhideGroupItems (id) { " .
                "    element = document.getElementById(id); " .
                "    if (element.style.display == '') " .
                "      element.style.display = 'none'; " .
                "    else " .
                "      element.style.display = ''; " .
                "  } " .
                "</script>\n";

        if ($itemArr === null)
            $itemArr = array();

        // Create Menu
        while (list($key, $val) = each($itemArr)) {

            // Main Item
            if ($itemArr[$key]['main']['Visible']) {

                $groupTitle = $itemArr[$key]['main']['Label'];
                $link = $itemArr[$key]['main']['Link'];
                $hint = $itemArr[$key]['main']['Hint'];

                // Get Sub-Style
                $subStyle = $itemArr[$key]['main']['SubStyle'];
                if (strlen($subStyle) > 0)
                    $subStyle = "_" . $subStyle;

                // Get Image
                $image = $itemArr[$key]['main']['Image'];
                if (strlen($image) > 0)
                    $HasImage = true;
                else
                    $HasImage = false;

                // Get LinkStyle
                if ($linkStyle == 'TD')
                    $link_th = " onclick=\"javascript:showhideGroupItems('$key');\" ";

                $output .= "<table class=\"" . $this->baseclass . $subStyle . "_table\">\n";
                $output .= "<tr class=\"" . $this->baseclass . $subStyle . "_tr\">\n";

                $hint = $itemArr[$key]['main']['Hint'];
                if ($hint == '')
                    $hint = $label;
                $hint = str_replace("'", "\\'", htmlspecialchars($hint));
                $hint = "window.status='$hint'";
                $nohint = "window.status=''";

                $output .= "<th class=\"" . $this->baseclass . $subStyle . "_th\" " .
                        "   onmouseover=\"this.className='" . $this->baseclass . $subStyle . "_th_hover';$hint;\" " .
                        "   onmouseout=\"this.className='" . $this->baseclass . $subStyle . "_th';$nohint;\" " .
                        "   $link_th " .
                        ">";
                if ($HasImage)
                    $output .= "<img src=\"$image\">";
                if ($linkStyle == 'A')
                    $output .= "<a href=\"javascript:showhideGroupItems('$key');\" class=\"" . $this->baseclass . $subStyle . "_a\">$groupTitle</a>";
                else
                    $output .= $groupTitle;
                $output .= "</th>\n";
                $output .= "</tr>\n";
                $output .= "<tr class=\"" . $this->baseclass . $subStyle . "_tr\" >\n";
                $output .= "<td class=\"" . $this->baseclass . $subStyle . "_tr\" >\n";

                // Status groupItems
                if ($itemArr[$key]['main']['Status'] == 'ON')     // ON
                    $output .= "<div id=\"$key\" class=\"menublock\">\n";
                else if ($itemArr[$key]['main']['Status'] == 'OFF')      // OFF
                    $output .= "<div id=\"$key\" class=\"menublock\" style=\"display: none;\">\n";
                else {                                                   // AUTO
                    if ($this->isGroupOpen($key, $activeItem))
                        $output .= "<div id=\"$key\" class=\"menublock\">\n";
                    else
                        $output .= "<div id=\"$key\" class=\"menublock\" style=\"display: none;\">\n";
                }

                $output .= "<table class=\"" . $this->baseclass . "\">\n";

                // Item
                for ($i = 0; $i < (count($itemArr[$key]) - 1); $i++) {

                    if ($itemArr[$key][$i]['Visible']) {

                        // Get Label + Control if is break
                        $label = $itemArr[$key][$i]['Label'];
                        if ($label == '-') {
                            $label = "<hr />";
                            $isLineBreak = true;
                        } else
                            $isLineBreak = false;

                        $link = $itemArr[$key][$i]['Link'];

                        // Highlight Item
                        if ($activeItem == $itemArr[$key][$i]['ItemName']) {
                            $class_td = '_on';
                            $class = 'item_on';
                        } else {
                            $class_td = '_off';
                            $class = 'item_off';
                        }

                        // Get Sub-Style
                        $subStyle = $itemArr[$key][$i]['SubStyle'];
                        if (strlen($subStyle) > 0)
                            $subStyle = "_" . $subStyle;

                        // Get Image
                        $image = $itemArr[$key][$i]['Image'];
                        if (strlen($image) > 0)
                            $HasImage = true;
                        else
                            $HasImage = false;

                        if ($itemArr[$key][$i]['Enabled']) {  // Enabled
                            $output .= "<tr class=\"" . $this->baseclass . $subStyle . "_tr\">\n";
                            if (!$isLineBreak) {

                                // Get Link Style
                                $j_pos = strpos(strtolower($link), 'javascript:');
                                if ($linkStyle == 'TD' && !($j_pos === false))
                                    $link = " onclick=\"$link\" ";
                                else if ($linkStyle == 'TD')
                                    $link = " onclick=\"location.href='$link';\" ";

                                $name = $itemArr[$key][$i]['ItemName'];

                                $hint = $itemArr[$key][$i]['Hint'];
                                if ($hint == '')
                                    $hint = $label;
                                $hint = str_replace("'", "\\'", htmlspecialchars($hint));
                                $hint = "window.status='$hint'";
                                $nohint = "window.status=''";

                                $output .= "<td id=\"menu-{$name}\" class=\"" . $this->baseclass . $subStyle . "_td" . $class_td . "\" " .
                                        "   onmouseover=\"this.className='" . $this->baseclass . $subStyle . "_td_hover';$hint;\" " .
                                        "   onmouseout=\"this.className='" . $this->baseclass . $subStyle . "_td" . $class_td . "';$nohint;\" " .
                                        "   $link " .
                                        ">";
                                if ($HasImage)
                                    $output .= "<img src=\"$image\" align=\"absmiddle\">&nbsp;";

                                if ($linkStyle == 'A')
                                    $output .= "<a href=\"$link\" class=\"" . $this->baseclass . $subStyle . "_$class\">$label</a>";
                                else
                                    $output .= $label;
                            } else {  // Line Break
                                $output .= "<td class=\"" . $this->baseclass . "_hr\">";
                                $output .= $label;
                            }
                        } else {                                // Disabled
                            $hint = $itemArr[$key][$i]['Hint'];
                            if ($hint == '')
                                $hint = $label;
                            $hint = str_replace("'", "\\'", htmlspecialchars($hint));
                            $hint = "window.status='$hint'";
                            $nohint = "window.status=''";

                            $output .= "<tr class=\"" . $this->baseclass . "_disabled_tr\">\n";
                            $output .= "<td class=\"" . $this->baseclass . "_disabled_td\" onmouseover=\"$hint;\" onmouseout=\"$nohint;\">\n";
                            if ($HasImage)
                                $output .= "<img src=\"$image\" align=\"absmiddle\">&nbsp;";
                            $output .= $label;
                        }
                        $output .= "</td></tr>\n";
                    }
                }

                $output .= "</table>\n";
                $output .= "</div>\n";
                $output .= "</td>\n";
                $output .= "</tr>\n";
                $output .= "</table>\n";
                $output .= "<br>\n";
            }
        }

        return $output;
    }

    /**
     * output the menu in strict html
     * @since Version 1.2.0
     *
     * @param The active item name
     * @param ?
     * @param ?
     */
    function outputStrict($activeItem = null, $order = '', $linkStyle = 'TD', $menuEventsInline = true) {

        $output = "\n<div id=\"main_menu_container\" class=\"main_menu\"> <!-- Main menu container -->\n"; // Main title
        // Reorder Items
        $itemArr = $this->itemOrder($order);

        if ($itemArr === null)
            $itemArr = array();

        // Create Menu
        while (list($key, $val) = each($itemArr)) {
            // Main Item
            if ($itemArr[$key]['main']['Visible']) {
                $groupTitle = $itemArr[$key]['main']['Label'];
                $link = $itemArr[$key]['main']['Link'];
                $hint = $itemArr[$key]['main']['Hint'];

                // Get Sub-Style
                $subStyle = $itemArr[$key]['main']['SubStyle'];
                if (strlen($subStyle) > 0)
                    $subStyle = "_" . $subStyle;
                // Get Image
                $image = $itemArr[$key]['main']['Image'];
                $hasImage = strlen($image) > 0;

                // Get LinkStyle
                if ($linkStyle == 'TD')
                    $link_th = "onclick=\"javascript:R3MenuShowHide('menu_$key');\" ";
                $output .= "<!-- $groupTitle menu ($key)-->\n";

                $hint = ($itemArr[$key]['main']['Hint'] == '' ? $lable : $itemArr[$key]['main']['Hint']);
                $hint = str_replace("'", "\\'", htmlspecialchars($hint));
                $hint = "window.status='$hint'";
                $nohint = "window.status=''";

                $output .= "  <div id=\"menu_" . $key . "_title\" class=\"" . $this->baseclass . $subStyle . "_title\"" .
                        " onmouseover=\"this.className='" . $this->baseclass . $subStyle . "_title_hover';$hint;\" " .
                        " onmouseout=\"this.className='" . $this->baseclass . $subStyle . "_title';$nohint;\" " .
                        " $link_th>";
                if ($hasImage) {
                    $output .= "<img src=\"$image\">";
                }
                if ($linkStyle == 'A') {
                    $output .= "<a href=\"javascript:R3MenuShowHide('$key');\" class=\"" . $this->baseclass . $subStyle . "_a\">$groupTitle</a>";
                } else {
                    $output .= $groupTitle;
                }
                $output .= "</div>\n";
                // Status groupItems
                if ($itemArr[$key]['main']['Status'] == 'ON') {     // ON
                    $output .= "  <ul id=\"menu_$key\" class=\"menublock\">\n";
                } else if ($itemArr[$key]['main']['Status'] == 'OFF') {     // OFF
                    $output .= "  <ul id=\"menu_$key\" class=\"menublock\" style=\"display: none;\">\n";
                } else {                                                   // AUTO
                    if ($this->isGroupOpen($key, $activeItem)) {
                        $output .= "  <ul id=\"menu_$key\" class=\"menublock\">\n";
                    } else {
                        $output .= "  <ul id=\"menu_$key\" class=\"menublock\" style=\"display: none;\">\n";
                    }
                }

                // Item
                for ($i = 0; $i < (count($itemArr[$key]) - 1); $i++) {

                    if ($itemArr[$key][$i]['Visible']) {

                        // Get Label + Control if is break
                        $label = $itemArr[$key][$i]['Label'];
                        if ($label == '-') {
                            $label = "<hr />";
                            $isLineBreak = true;
                        } else
                            $isLineBreak = false;

                        $link = $itemArr[$key][$i]['Link'];

                        // Highlight Item
                        if ($activeItem == $itemArr[$key][$i]['ItemName']) {
                            $class_td = '_on';
                            $class = 'item_on';
                        } else {
                            $class_td = '_off';
                            $class = 'item_off';
                        }

                        // Get Sub-Style
                        $subStyle = $itemArr[$key][$i]['SubStyle'];
                        if (strlen($subStyle) > 0)
                            $subStyle = "_" . $subStyle;

                        // Get Image
                        $image = $itemArr[$key][$i]['Image'];
                        if (strlen($image) > 0)
                            $hasImage = true;
                        else
                            $hasImage = false;

                        if ($itemArr[$key][$i]['Enabled']) {  // Enabled
                            if (!$isLineBreak) {

                                // Get Link Style
                                $j_pos = strpos(strtolower($link), 'javascript:');
                                if ($linkStyle == 'TD' && !($j_pos === false))
                                    $link = " onclick=\"" . substr($link, 11) . "\" ";
                                else if ($linkStyle == 'TD')
                                    $link = " onclick=\"location.href='$link';\" ";

                                $hint = $itemArr[$key][$i]['Hint'];
                                $name = $itemArr[$key][$i]['ItemName'];
                                if ($hint == '')
                                    $hint = $label;
                                $hint = str_replace("'", "\\'", htmlspecialchars($hint));
                                $hint = '';
                                $nohint = '';

                                if ($menuEventsInline) {
                                    $output .= "    <li id=\"$name\" class=\"" . $this->baseclass . $subStyle . "_item" . $class_td . "\" " .
                                            "onmouseover=\"this.className='" . $this->baseclass . $subStyle . "_item_hover';$hint;\" " .
                                            "onmouseout=\"this.className='" . $this->baseclass . $subStyle . "_item" . $class_td . "';$nohint;\" " .
                                            "$link " .
                                            ">";
                                } else {
                                    $output .= "    <li id=\"$name\" class=\"" . $this->baseclass . $subStyle . "_item" . $class_td . "\" " .
                                            "$link " .
                                            ">";
                                }
                                if ($hasImage)
                                    $output .= "<img src=\"$image\" align=\"absmiddle\">&nbsp;";

                                if ($linkStyle == 'A')
                                    $output .= "<a href=\"$link\" class=\"" . $this->baseclass . $subStyle . "_$class\">$label</a>";
                                else
                                    $output .= $label;
                            } else {
                                // Line Break
                                $output .= "    <li class=\"" . $this->baseclass . "_hr\">";
                                $output .= $label;
                            }
                        } else {                                
                            // Disabled
                            $output .= "    <li class=\"" . $this->baseclass . "_disabled\">";
                            if ($hasImage)
                                $output .= "<img src=\"$image\" align=\"absmiddle\">&nbsp;";
                            $output .= $label;
                        }
                        $output .= "</li>\n";
                    }
                }

                $output .= "  </ul> \n";
            }
        }

        $output .= "</div>  <!-- Main menu container -->\n";

        if ($activeItem != '') {
            $output .= "<script type=\"text/javascript\" language=\"JavaScript\">\n";
            $output .= "  R3MenuSetActiveById('$activeItem')\n";
            $output .= "</script>\n";
        }

        return $output;
    }

    /**
     * count assigned items
     * @since Version 1.1.0
     *
     * @param $name (Main-Item Name) Filter Option
     * @return integer
     */
    function getCountItems($name = null) {
        $ret = 0;
        if ($name !== null) {
            if (isset($this->Items[$name]))
                $ret = count($this->Items[$name]) - 1;
        } else {
            // count all
            foreach ($this->Items as $mainName => $items) {
                $ret += count($items) - 1;
            }
        }
        return $ret;
    }

}
