<?php

require_once __DIR__.'/../r3ogcmap.php';
require_once __DIR__.'/../r3geom2d.php';

define('WMS_LAYER_TYPE', 1);
define('GMAP2_LAYER_TYPE', 2);
define('VMAP_LAYER_TYPE', 3);
define('YMAP_LAYER_TYPE', 4);
define('OSM_LAYER_TYPE', 5);
define('TMS_LAYER_TYPE', 6);
define('GMAP3_LAYER_TYPE', 7);
define('BING_LAYER_TYPE', 8);
define('WMTS_LAYER_TYPE', 9);

class R3PreviewMap {

    protected $options;
    protected $includeWms = array();
    protected $excludeWms = array();
    protected $extent = array();
    protected $sldFolderUrl = null;
    protected $db = null;
    protected $additionalHighlights = array();
    protected $globalWmsParameters = array();
    protected $wmsParameters = array();
    protected $temporaryFiles = array();

    public function __construct($initMapUrl, array $options = array()) { // passare wmslist da options
        $defaultOptions = array(
            'table' => null,
            'id_field' => null,
            'id_value' => null,
            'size' => array(200, 200),
            'unit_dimensions' => array(50, 50),
            'db' => null,
            'mergeRequest' => false,
            'singleRequest' => false,
            'mergeSLD' => true,
            'force_contains_object' => false
        );
        $this->options = array_merge($defaultOptions, $options);

        if (empty($options['db']))
            $this->db = ezcDbInstance::get();
        else
            $this->db = $options['db'];
        $this->initMapUrl = R3OgcMapUtils::addPrefixToRelativeUrl($initMapUrl);

        if (!is_array($this->options['size']) || count($this->options['size']) != 2) {
            throw new Exception('size option must be an array of width,height pixel size');
        }

        if (!is_array($this->options['unit_dimensions']) || count($this->options['unit_dimensions']) != 2) {
            throw new Exception('unit_dimensions option must be an array of dimensions in coordinate units');
        }

        $this->sldFolderUrl = R3_APP_URL . 'sld/';
    }

    protected function validateInitMapUrl() {
        $urlInfo = parse_url($this->initMapUrl);

        // check required parameter: jsonformat
        if (strpos($urlInfo['query'], 'jsonformat=') === false)
            throw new Exception('initMapUrl: missing get parameter "jsonformat"');

        // check required parameter: mapset
        if (strpos($urlInfo['query'], 'mapset=') === false)
            throw new Exception('initMapUrl: missing get parameter "mapset"');
    }

    public function setSldFolderUrl($folder) {
        if (!empty($folder)) {
            $this->sldFolderUrl = R3OgcMapUtils::addPrefixToRelativeUrl($folder);
        }
    }

    public function includeWms($layergroupName) {
        if (empty($layergroupName)) {
            throw new Exception('Layergroup is empty');
        }
        array_push($this->includeWms, $layergroupName);
    }
    
    public function excludeWms($layergroupName) {
        if (empty($layergroupName)) {
            throw new Exception('Layergroup is empty');
        }
        array_push($this->excludeWms, $layergroupName);
    }

    public function hasValidExtent() { //TODO: this is not used!
        // if empty try to calculate
        if (empty($this->extent)) {
            $this->extent = $this->calculateExtent();
        }
        // check if 4 coordinates are available
        if (!is_array($this->extent) || count($this->extent) != 4) {
            return false;
        }

        // check if coordinates are numeric
        foreach ($this->extent as $val) {
            if (!is_numeric($val))
                return false;
        }

        return true;
    }

    public function setBoxExtent(array $extent) {
        try {
            $box = R3GeomBox::resize($extent, $this->options['unit_dimensions'], $this->options['force_contains_object']);
            $this->extent = R3GeomBox::exSquare($box);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function addHighlight($layergroup, $id_field, $id_value, $exclusive = true, $url = null) {
        if (!isset($this->additionalHighlights[$layergroup])) {
            $this->additionalHighlights[$layergroup] = array();
        }
        array_push($this->additionalHighlights[$layergroup], array(
            'id_field' => $id_field,
            'id_value' => $id_value,
            'exclusive' => $exclusive,
            'url' => $url
        ));
    }

    public function sldHighlight(array &$wmsList, $layerName, $fileUrl, $excludeLayer = false) {
        if (empty($layerName))
            throw new Exception('Specify a layer name');
        if (empty($fileUrl))
            throw new Exception('Specify an SLD xml file url');

        $sldLayer = null;
        $selectedLayer = null;
        foreach ($wmsList as $key => $layer) {
            if ($layer['name'] == $layerName) {
                $sldLayer = $layer;
                if (empty($sldLayer['parameters']['sld'])) {
                    $sldLayer['parameters']['sld'] = $fileUrl;
                }
                $sldLayer['options']['gc_id'] = "{$layerName}_highlight.sld";
                $selectedLayer = $key;
            }
        }

        if (!empty($sldLayer)) {
            $wmsList['sld_' . $layerName] = $sldLayer;
            if ($excludeLayer && !empty($selectedLayer))
                unset($wmsList[$selectedLayer]);
        }
    }
    
    protected function getWmsLayersAsArray($layers) {
        if (is_array($layers)) {
            $layerList =  $layers;
        } else if (is_string($layers)){
            $layerList = explode(',', $layers);
        } else {
            throw new Exception("wms parameter layer is unreadable");
        }
        
        return $layerList;
    }

    protected function mergeWMSList(array $wmsList, array $sldList = array()) {
        $ret = array();
        $gcUrlInfo = pathinfo(GC_INITMAP_URL);
        // echo count($wmsList)."\n";
        $prevWms = array();
        $layersAggr = array();
        $aggregateId = 0;
        foreach ($wmsList as $wmsLayerGroup => $wmsConfig) {
            if ($prevWms) {
                $merge = true;
                if (!isset($prevWms['url']) || !isset($wmsConfig['url']) || $prevWms['url'] != $wmsConfig['url'] ) {
                    $merge = false;
                } else if (isset($wmsConfig['options']['opacity']) && $wmsConfig['options']['opacity'] != 1.0) {
                    $merge = false;
                } else if (isset($prevWms['options']['opacity']) && $prevWms['options']['opacity'] != 1.0)  {
                    $merge = false;
                } else if ((empty($prevWms['parameters']['sld']) && !empty($wmsConfig['parameters']['sld'])) ||
                (!empty($prevWms['parameters']['sld']) && empty($wmsConfig['parameters']['sld'])) ||
                (!empty($prevWms['parameters']['sld']) && !empty($wmsConfig['parameters']['sld']) && $prevWms['parameters']['sld'] != $wmsConfig['parameters']['sld'])) {
                    $merge = false;
                }                            
                if (!$merge) {
                    $ret['merged'.$aggregateId] = $prevWms;
                    $ret['merged'.$aggregateId]['parameters']['layers'] = $layersAggr;
                    $aggregateId++;
                    $layersAggr = $this->getWmsLayersAsArray($wmsConfig['parameters']['layers']);
                    // echo "stop merging, start over\n";
                } else {
                    $layersAggr = array_merge($layersAggr, $this->getWmsLayersAsArray($wmsConfig['parameters']['layers']));
                    // echo "merged ".implode(", ",$layersAggr )."\n";
                }
            } else {
                $layersAggr = array_merge($layersAggr, $this->getWmsLayersAsArray($wmsConfig['parameters']['layers']));
                    // echo "merged ".implode(", ",$layersAggr )."\n";
            }   
            // echo $mergeName . "<br>";
            $prevWms = $wmsConfig;
        }
        
        if (count($layersAggr) > 0) {
            $ret['merged'.$aggregateId] = $wmsConfig;
            $ret['merged'.$aggregateId]['parameters']['layers'] = $layersAggr;
            $aggregateId++;
            $layersAggr = array();
        }
        // echo count($ret)."\n"; die();
        return $ret;
    }
    
    protected function mergeWMSListOrig(array $wmsList, array $sldList = array()) {
        $ret = array();
        
        $gcUrlInfo = pathinfo(GC_INITMAP_URL);
        
        foreach ($wmsList as $wmsLayerGroup => $wmsConfig) {
            $wmsUrlInfo = array();
            if (isset($wmsConfig['url'])) {
                $wmsUrlInfo = pathinfo($wmsConfig['url']);
            }
            
            if (!isset($wmsConfig['url']) || $gcUrlInfo['dirname'] === $wmsUrlInfo['dirname']) {
                
                if (substr($wmsLayerGroup, 0, 4) == "sld_") {
                    $mergeName = $wmsLayerGroup;
                } else if ($this->options['singleRequest'] && isset($wmsConfig['parameters']['map'])) {
                    $mergeName = array_shift(explode('.', $wmsConfig['parameters']['map']));
                } else {
                    $mergeName = array_shift(explode('.', $wmsConfig['options']['gc_id']));
                }
            } else {
                // THIS IS THE CURRENT CASE!!
                $mergeName = $wmsConfig['options']['gc_id'];
            }
            // echo $mergeName . "<br>";

            if (empty($ret[$mergeName])) {
                $ret[$mergeName] = $wmsConfig;
            } else {
                $ret[$mergeName]['parameters']['layers'] = array_merge($ret[$mergeName]['parameters']['layers'], $wmsConfig['parameters']['layers']);
                if (!empty($wmsConfig['parameters']['sld']))
                    $ret[$mergeName]['parameters']['sld'] = $wmsConfig['parameters']['sld'];
            }
        }
        return $ret;
    }

    protected function getUrlContent($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch ,CURLOPT_TIMEOUT, 10);
        
        // follow redirects (for those sites using http forward to https)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $responseContent = curl_exec($ch);
        if ($responseContent === FALSE)
            throw new Exception("Error opening url: {$url} - " . curl_error($ch));
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpStatus != 200) {
                throw new RunTimeException("Call to {$url} returned with error code $httpStatus");
        }

        curl_close($ch);
        return $responseContent;
    }

    protected function mergeSLD(array &$wmsList) {
        $sldList = array();
        foreach ($wmsList as $wmsLayerGroup => $wmsConfig) {
            if (substr($wmsLayerGroup, 0, 4) == "sld_") {
                $mergeName = $wmsLayerGroup;
            } else if ($this->options['singleRequest'] && isset($wmsConfig['parameters']['map'])) {
                $mergeName = array_shift(explode('.', $wmsConfig['parameters']['map']));
            } else {
                $mergeName = array_shift(explode('.', $wmsConfig['options']['gc_id']));
            }

            if (!empty($wmsConfig['parameters']['sld'])) {
                if (empty($sldList[$mergeName]))
                    $sldList[$mergeName] = array();

                if (!in_array($wmsConfig['parameters']['sld'], $sldList[$mergeName]))
                    $sldList[$mergeName][] = $wmsConfig['parameters']['sld'];
            }
        }

        $ret = array();
        foreach ($sldList as $sldName => $sldUrls) {
            $dom = new DOMDocument('1.0', 'UTF-8');

            // create element StyledLayerDescriptor
            $element = $dom->createElement('StyledLayerDescriptor');
            $element->setAttribute("version", "1.1.0");
            $element->setAttribute("xmlns:gml", "http://www.opengis.net/gml");
            $element->setAttribute("xmlns:ogc", "http://www.opengis.net/ogc");
            $element->setAttribute("xmlns:ows", "http://www.opengis.net/ows");
            $element->setAttribute("xmlns", "http://www.opengis.net/sld");
            $element->setAttribute("xmlns:wms", "http://www.opengis.net/ows");
            $element->setAttribute("xmlns:xlink", "http://www.w3.org/1999/xlink");
            $element->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
            $element->setAttribute("xsi:schemaLocation", "http://www.opengis.net/sld http://schemas.opengis.net/sld/1.1.0/StyledLayerDescriptor.xsd");

            $dom->appendChild($element);
//            echo $sldName .":\n";
            foreach ($sldUrls as $url) {
//            echo $url ."\n";
                /*
                  if (file_exists(R3_WEB_DIR . "sld/" . basename($url))) {
                  echo R3_WEB_DIR . "sld/" . basename($url);
                  $slddom = DOMDocument::load(R3_WEB_DIR . "sld/" . basename($url));
                  } else {
                  echo $url;
                  $slddom = DOMDocument::load($url);
                  }
                 */
                
                $slddom = DOMDocument::loadXML($this->getUrlContent($url));
                
                $nodeList = $slddom->getElementsByTagName('NamedLayer');
                foreach ($nodeList as $domElement) {
                    $domNode = $dom->importNode($domElement, true);
                    $element->appendChild($domNode);
                }
            }

            $ret[$sldName] = tempnam(R3_WEB_DIR . "sld/", "{$sldName}_sld");
            $this->temporaryFiles[] = $ret[$sldName];
            chmod($ret[$sldName], 0664);
            $dom->save($ret[$sldName]);
        }

        foreach ($wmsList as $wmsLayerGroup => $wmsConfig) {
            if (substr($wmsLayerGroup, 0, 4) == "sld_") {
                $mergeName = $wmsLayerGroup;
            } else if ($this->options['singleRequest'] && isset($wmsConfig['parameters']['map'])) {
                $mergeName = array_shift(explode('.', $wmsConfig['parameters']['map']));
            } else {
                $mergeName = array_shift(explode('.', $wmsConfig['options']['gc_id']));
            }

            if (!empty($wmsConfig['parameters']['sld']) && !empty($ret[$mergeName])) {
                $sldFileName = basename($ret[$mergeName]);
                $wmsList[$wmsLayerGroup]['parameters']['sld'] = $this->sldFolderUrl . "{$sldFileName}";
            } else {
                unset($wmsList[$wmsLayerGroup]['parameters']['sld']);
            }
        }

        return $ret;
    }

    public function generatePreviewMap(array $mbr, $format = 'png', $layergroupHighlight = null) {
        try {
            $wmsList = $this->getWmsList(); // empty($this->wmsList) ? $this->getWmsList(); : $this->wmsList;
            foreach ($wmsList as $i => $wmsService) {
                if (!empty($this->globalWmsParameters)) {
                    $wmsList[$i]['parameters'] = array_merge($wmsList[$i]['parameters'], $this->globalWmsParameters);
                }
                if (!empty($this->wmsParameters[$wmsService['name']])) {
                    $wmsList[$i]['parameters'] = array_merge($wmsList[$i]['parameters'], $this->wmsParameters[$wmsService['name']]);
                }
            }

            if (empty($this->extent)) {
                $this->extent = $this->calculateExtent($mbr);
            }
            if (!empty($layergroupHighlight)) {
                $this->sldHighlight($wmsList, $layergroupHighlight, $this->sldFolderUrl . $layergroupHighlight . '_sld.php?field=' . $this->options['id_field'] . '&id=' . $this->options['id_value']);
            }
            if (!empty($this->additionalHighlights)) {
                foreach ($this->additionalHighlights as $layergroup => $highlights) {
                    foreach ($highlights as $highlightParameters) {
                        $url = empty($highlightParameters['url']) ? $this->sldFolderUrl : $highlightParameters['url'];
                        $this->sldHighlight($wmsList, $layergroup, $url . $layergroup . '_sld.php?field=' . $highlightParameters['id_field'] . '&id=' . $highlightParameters['id_value'], $highlightParameters['exclusive']);
                    }
                }
            }
            // performance boost merging SLD & WMS requests (by theme, complete merge has problems with layerorder)
            if ($this->options['mergeSLD']) {
                $sldList = $this->mergeSLD($wmsList);
            }
            if ($this->options['mergeRequest']) {
                $wmsList = $this->mergeWMSList($wmsList);
            }
            $time_start = microtime(true);
            $preview = new R3OgcMap($this->extent, $this->options['size'], $wmsList);
            $preview->createImage();
            $fileContent = $preview->getCanvas();

            // remove temporary sld files
            if ($this->options['mergeSLD']) {
                foreach ($sldList as $sldFileName) {
                    unlink($sldFileName);
                }
            }

            $time_end = microtime(true);
            $time = $time_end - $time_start;

            // echo "In $time Sekunden Preview estellt\n"; die();
        } catch (Exception $e) {
            throw $e;
        }
        return $fileContent;
    }

    protected function calculateExtent(array $mbr) {
        $box = R3GeomBox::resize($mbr, $this->options['unit_dimensions'], $this->options['force_contains_object']);
        return R3GeomBox::exSquare($box);
    }
    
    protected function tms2Wms(array $tms) {
        $wms = $tms;
        
        /*if(preg_match("/project=([A-Za-z-_]+)&map=([A-Za-z-_]+)/", $tms['options']['owsurl'], $matches)) {
            $project = $matches[1];
            $map = $matches[2];
        } else {
            return $tms;
        }*/
        
        $wms['type'] = WMS_LAYER_TYPE;
        $wms['url'] = str_replace('/tms/', '/wms/', $wms['url']);
        $wms['parameters'] = array(
            'service' => 'WMS',
            'request' => 'GetMap',
            //'project' => $project,
            //'map' => $map,
            'layers' => array(substr($wms['options']['layers'], 0, strpos($wms['options']['layers'], '@'))),
            'version' => '1.1.1',
            'format' => 'image/png'
        );
        
        // TODO: ???
        // opacity: layer.opacity ? (layer.opacity * 100) : 100
                            
        return $wms;
    }

    protected function getWmsList() {
        $this->validateInitMapUrl();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->initMapUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        if (($content = curl_exec($ch)) === FALSE) {
            throw new Exception("Error while accessing {$this->initMapUrl}");
        }
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpStatus != 200) {
                throw new RunTimeException("Call to {$this->initMapUrl} returned with error code $httpStatus");
        }

        curl_close($ch);
        
        $initMap = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
                throw new RunTimeException("Could not parse $content as JSON");
        }
        if (defined('GC_PREVIEW_MAP_LAYERGROUP_LIST') && ((boolean) trim(GC_PREVIEW_MAP_LAYERGROUP_LIST))) {
            $layergroups = explode(',', GC_PREVIEW_MAP_LAYERGROUP_LIST);
        } else {
            $layergroups = array();
        }
        $wms = array();
        if (!is_null($initMap) && is_array($initMap)) {
            $theme = array_reverse($initMap['theme']);
            foreach ($theme as $theme) {
                $layers = array_filter($theme, 'is_array');
                $layers = array_reverse($layers);
                //$layers = array_reverse($theme['layers']);
                foreach ($layers as $layerName => $layer) {
                    if (!is_array($layer))
                        continue;
                    
                    // try to convert tms to wms layer
                    if ($layer['type'] == TMS_LAYER_TYPE) {
                        $layer = $this->tms2Wms($layer);
                    }
                    
                    $include = false;
                    if (in_array($layerName, $this->includeWms)) {
                        $include = true;
                    } else if (!in_array($layerName, $this->excludeWms)) {
                        if (!empty($layergroups)) {
                            if (in_array($layerName, $layergroups))
                                $include = true;
                        } else if (!isset($layer['options']['visibility']) || $layer['options']['visibility'] === true) {
                            $include = true;
                        }
                    }
                    if ($include) {
                        $layer['name'] = $layerName;
                        $layer['parameters']['srs'] = $initMap['projection'];
                        array_push($wms, $layer);
                    }
                }
            }
        }
        
        return $wms;
    }
    
    public function getTemporaryFiles() {
        return $this->temporaryFiles;
    }

    public function setWmsParameters($layerGroupName, array $parameters) {
        if (empty($this->wmsParameters[$layerGroupName]))
            $this->wmsParameters[$layerGroupName] = array();
        $this->wmsParameters[$layerGroupName] = array_merge($this->wmsParameters[$layerGroupName], $parameters);
    }

    public function setGlobalWmsParameters(array $parameters) {
        $this->globalWmsParameters = array_merge($this->globalWmsParameters, $parameters);
    }

}
