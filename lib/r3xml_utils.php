<?php

/**********************************************************************/
/* R3 XML Utils                                                       */
/* Version: 1.0.1                                                     */
/* Bug Fix [15/09/2009] if node already exists, call also R3XmlEncode */
/**********************************************************************/

/**
 * Replace characters, which should not be in xml text elements with corresponding entities
 * @param sting/array $string
 *
 * @return string/array with replaced characters
 */
function R3XmlEncode($string) {
    $table = array('&' => "&amp;",
                   '"' => "&quot;",
                   "'" => "&apos;",
                   '<' => '&lt;',
                   '>' => '&gt;');
    $retval = str_replace(array_keys($table), $table, $string, $count);
    return $retval;
}

function _INTERNAL_array2xml($data, $opt, $dom, $mainnode, $prev_key, $level) {
    
    foreach($data as $key=>$val) {
        if (is_array($val)) {
            if (is_numeric($key)) {
                $element = $dom->createElement($prev_key);
                $node = $mainnode->appendChild($element);
                
                _INTERNAL_array2xml($val, $opt, $dom, $node, $key, $level + 1);
            } else {
                $node = null;
                /* Attribute */
                if ($key == $opt['attribute_key']/* $key == 'ATTRIBUTE' */) {
                    foreach($val as $attrKey=>$attrVal) {
                        $attribute = $dom->createAttribute($attrKey);
                        $mainnode->appendChild($attribute);
                        $element = $dom->createTextNode($attrVal);
                        $attribute->appendChild($element);
                    }          
                } else {
                    /* node */
                    if (is_numeric(key($val))) {
                        _INTERNAL_array2xml($val, $opt, $dom, $mainnode, $key, $level + 1);
                    } else {
                        $element = $dom->createElement($key);
                        $node = $mainnode->appendChild($element);
                        _INTERNAL_array2xml($val, $opt, $dom, $node, $key, $level + 1);
                    }
                }
            }
        } else {
        
			// cast to string, otherwise $key == 0 would make this TRUE
            if ((string)$key == $opt['value_key']) {
                $mainnode->nodeValue = R3XmlEncode($val);                
            } else {
                if (is_numeric($key)) {
                    /* prevent numeric keys */
                    $key = 'data';
                }
                $encVal = R3XmlEncode($val);
                $element = $dom->createElement($key, $encVal);
                if ($element === false){
                    throw new Exception('Could not create element');
                }
                $mainnode->appendChild($element);
            }
        }
    }
}

function _INTERNAL_xml2array($node, $level=0) {

    $result = array();
    if ($node->hasChildNodes()) {
        foreach($node->childNodes as $n) {
            if ($n->nodeType == XML_ELEMENT_NODE) {
                $result[$n->nodeName][] = _INTERNAL_xml2array($n, 1);
            } else  {
                $a = _INTERNAL_xml2array($n, -1);
                if (!is_array($a) > 0) {
                    return $a;
                }
            }
        }
    }
    if ($node->nodeType == XML_TEXT_NODE) {
        $content = rtrim($node->nodeValue);
        if (!empty($content)) {
            return $content;
        }
    }
    return $result;
}


/**
 * convert an array to an xml
 * 
 *
 * @param array   the array to convert
 * @param array   the option array. The follow parameters are accepted:
 *                  version: XML version. Default 1.0
 *                  encoding: XML encoding. Default UTF-8
 *                  attribute_key: the name of the key to identify the attribute element
 *                  document_element: the document element. Default empty. If empty the 1st key is used
 * @return resource  The dom object
 * <code>
 *   $data = array();
 *   $data['node0']['node1'][0]['row'] = 'Value1';    
 *   $data['node0']['node1'][1]['row'] = 'Value2';
 *   $data['node0']['node1'][0]['__ATTRIB__']['attr_name'] = 'value1';  // Set the value of an attribute
 * </code>
 * <code>
 *   // Set the attribute and the value of the last node  
 *   $data = array();
 *   $data['node0']['node1'][0]['row']['__ATTRIB__']['pippo'] = 'attributo';
 *   $data['node0']['node1'][0]['row']['__VALUE__'] = 'valore';
 * </code>
*/
function array2xml(array $data, array $opt=array()) {

    $opt = array_merge(array('version'=>'1.0', 'encoding'=>'UTF-8', 
                             'attribute_key'=>'__ATTRIB__', 
                             'value_key'=>'__VALUE__', 'document_element'=>'', 
                             'format_output'=>true), $opt);
    
    
    if ($opt['document_element'] == '' && count($data) != 1){
        throw new Exception('please specify XML root element');
    }
    $dom = new DOMDocument($opt['version'], $opt['encoding']); 
    if ($opt['format_output'] === true) {
        $dom->formatOutput = true;
    }
    if ($opt['document_element'] == '') {
        $key = key($data);
        $opt['document_element'] = $key;
        $data = $data[$key];
    }
    
    $node = $dom->createElement($opt['document_element']);
    $dom->appendChild($node);
    _INTERNAL_array2xml($data, $opt, $dom, $node, $opt['document_element'], 1);

    return $dom;    
}

/**
 * Convert an xml in dom format to an array
 *
 * @param dom  the xml
 * @param array   the option array. 
 * return array
*/
function xml2array($dom, array $opt=array()) {

    $node = $dom->documentElement;
    $result[$dom->documentElement->nodeName] = _INTERNAL_xml2array($node);
    return $result;
}

/**
 * Convert an xml in text format to an array
 *
 * @param string  the xml as string
 * @param array   the option array. 
 * return array
*/
function xmltext2array($text, array $opt=array()) {
    $dom = new DOMDocument();
    $dom->loadXML($text);
    return xml2array($dom, $opt);
}
    
?>
