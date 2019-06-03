<?php

class R3Help {

    /*
     * Return html-part of the help
     *
     * param string $fileName   the help file to read
     * param string $id         the id to extract
     * return string|null       the help part of null if $id not found
     */
    public static function getHelpPartFromFile ( $fileName, $id ) {
        global $smarty; 
        $auth = R3AuthInstance::get();
        $helpDoc = new DOMDocument('1.0', 'UTF-8');
        // $helpDoc->loadHTMLFile ( $fileName );
        
        // Smarty help template parse
        // $smarty = R3AppInit::getInstance()->createSmarty();
        $smarty->assign('NUM_LANGUAGES', $auth->getConfigValue('APPLICATION', 'NUM_LANGUAGES'));
        $helpDoc->loadHTML ( $smarty->fetch($fileName) );
        if (($node = $helpDoc->getElementById ( $id )) === null)
            return null;  // id not found
        $title = $node->getAttribute ('title');
        //$body = $node->nodeValue;
        //echo "[$body]";
        //return array('title'=>$title, 'body'=>$body);

        // Create a new document
        $helpPartDoc = new DOMDocument('1.0', 'UTF-8');
        // Add a root node needed by xml
        $helpPartDoc->loadXML("<root></root>");
        // Import the node, and all its children, to the document
        $node = $helpPartDoc->importNode($node, true);
        // And then append it to the "<root>" node
        $helpPartDoc->documentElement->appendChild($node);
        // return the part (without root node)
        $body = substr($helpPartDoc->saveHTML(), 6, -8);
        // SS: TODO: Find a better way to remove the tag
        $body = substr($body, strpos($body, '>') + 1);
        $body = substr($body, 0, strrpos($body, '<'));
        return array('title'=>$title, 'body'=>$body);
        //return substr($helpPartDoc->saveHTML(), 6, -8);
    }
    
    public static function getHelpPartFromSection ( $section, $id, $lang) {
        //echo "[$section, $id, $lang]";
        $fileName = R3_SMARTY_TEMPLATE_DIR_HELP . "{$section}_{$lang}.tpl";
        if (file_exists ( $fileName ) && ( $help = R3Help::getHelpPartFromFile($fileName, $id)) !== null)
            return $help;
        $fileName = R3_SMARTY_TEMPLATE_DIR_HELP . "{$section}.tpl";
        if (file_exists ( $fileName ) && ( $help = R3Help::getHelpPartFromFile($fileName, $id)) !== null)
            return $help;
        return null;    
    }
}

?>