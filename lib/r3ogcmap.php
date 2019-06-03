<?php

class R3OgcMap {

    protected $supportedFormats = array('png' => 'imagepng');
    protected $wms = array();
    protected $size = array();
    protected $extent = array();
    protected $images = array();
    protected $resultImage = null;

    public function __construct(array $extent, array $size, array $wms = array()) {
        if (!is_array($extent) || count($extent) != 4)
            throw new Exception('Extent must be an array of bottom, left, top, right coordinates');
        if (!is_array($size) || count($size) != 2)
            throw new Exception('Size must be an array of width, height in pixel');
        foreach ($extent as $val) {
            if (!is_numeric($val))
                throw new Exception('An extent coordinate is not numeric ' . $val);
        }
        foreach ($size as $val) {
            if (!is_int($val))
                throw new Exception('A size value is not an integer');
        }
        $this->extent = $extent;
        $this->size = $size;
        if (is_array($wms)) {
            if (count($wms) > 0) {
                try {
                    foreach ($wms as $val)
                        $this->registerWMS($val);
                } catch (Exception $e) {
                    throw $e;
                }
            }
        } else
            throw new Exception('wms parameter must be an array of WMS urls');
    }

    public function registerWMS(array $wms) {
        if (!is_array($wms))
            throw new Exception('wms parameter must be an array');
        
        $lowerCaseWms = array();
        foreach ($wms as $key => $val)
            $lowerCaseWms[strtolower($key)] = $val;
        foreach ($wms['parameters'] as $key => $val)
            $lowerCaseWms['parameters'][strtolower($key)] = $val;
        unset($wms);

        if (!isset($lowerCaseWms['url'])) {
            throw new Exception('wms parameter must be an array of wms url and optionally parameters');
        }
        $lowerCaseWms['url'] = R3OgcMapUtils::addPrefixToRelativeUrl($lowerCaseWms['url']);
        
        if (!empty($lowerCaseWms['parameters']['layers']) && is_array($lowerCaseWms['parameters']['layers']))
            $lowerCaseWms['parameters']['layers'] = implode(',', $lowerCaseWms['parameters']['layers']);

        array_push($this->wms, $lowerCaseWms);
    }
	
    public function addImageCanvas($canvas) {
        array_push($this->images, $canvas);
    }

    protected function downloadImages() {
        if (empty($this->wms)) {
            throw new Exception('No WMS to query');
        }
        $n = 0;
        foreach ($this->wms as $wmsKey => $wmsData) {
            $n++;
            $separator = '';
            if (!strpos($wmsData['url'], '?')) {
                $separator = '?';
            } else {
                $lastChar = substr($wmsData['url'], 0, -1);
                if ($lastChar != '?') {
                    if ($lastChar != '&' && substr($wmsData['url'], 0, -4) != '&amp;')
                        $separator = '&';
                }
            }
			
            $parameters = array();
            foreach($wmsData['parameters'] as $parameterKey => $parameterValue) {
                $parameters[strtoupper($parameterKey)] = $parameterValue;
            }
            $wmsData['parameters'] = $parameters;

            $wmsData['parameters']['BBOX'] = implode(',', $this->extent);
            $wmsData['parameters']['SERVICE'] = 'WMS';
            $wmsData['parameters']['REQUEST'] = 'GetMap';
			$wmsData['parameters']['VERSION'] = '1.1.1';
            $wmsData['parameters']['WIDTH'] = $this->size[0];
            $wmsData['parameters']['HEIGHT'] = $this->size[1];
            #$wmsData['parameters']['FORMAT'] = 'image/png';
            
			// Da precedenza ai parametri nell'url del WMS piuttosto che a quelli in $wmsData['parameters']
			$urlParts = parse_url($wmsData['url']);
			if (isset($urlParts['query'])) {
				parse_str($urlParts['query'], $queryParams);
				foreach($queryParams as $key=>$val) {
					$key = strtoupper($key);
					if (isset($wmsData['parameters'][$key])) {
						unset($wmsData['parameters'][$key]);
					}
				}
			}
			$url = $wmsData['url'] . $separator . http_build_query($wmsData['parameters']);
			
            //HACK: http_build_query trasforma da true a 1 e MS6 vuole true e non 1
            $url = str_replace("TRANSPARENT=1", "TRANSPARENT=true", $url);
            
            $this->wms[$wmsKey]['requesturl'] = $url;
            //SG: PHP BUG #52339 https://bugs.php.net/bug.php?id=52339&edit=1
            /*if (class_exists('R3AppInit')) {
                $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
                ezcLog::getInstance()->log(__METHOD__.", $elapsedTime, fetching $url", ezcLog::DEBUG);
            }*/
                        
            $url = R3OgcMapUtils::addPrefixToRelativeUrl($url);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            $image = curl_exec($ch);
            if (!$image) {
                ezcLog::getInstance()->log("Error downloading image from [{$url}], curl error: [" . curl_error($ch)."]", ezcLog::ERROR);
                throw new Exception("Error downloading image");
            }
            curl_close($ch);
            array_push($this->images, $image);
        }
    }

    protected function mergeImages() {
        if (empty($this->images))
            throw new Exception('No images to merge');

        $this->resultImage = imagecreatetruecolor($this->size[0], $this->size[1]);
        if (!$this->resultImage)
            throw new Exception('Error creating image');
        $white = imagecolorallocate($this->resultImage, 255, 255, 255);
        if (!imagefill($this->resultImage, 0, 0, $white))
            throw new Exception('Error filling background');


        foreach ($this->images as $layer) {
            $img = @imagecreatefromstring($layer);
            if ($img === FALSE) {
                $msg = 'Error reading WMS result';
                if(defined('PREVIEWMAP_DEBUG')) {
                    $msg .= '<br>'.$layer.'<br><pre>'.var_export($this, true);
                    throw new Exception($msg);
                } else {
                    continue;
                }
            }
            if (!imagealphablending($img, true))
                throw new Exception('Error with alpha');
            if (!imagesavealpha($img, true))
                throw new Exception('Error with alpha');
            $cut = imagecreatetruecolor($this->size[0], $this->size[1]);
            imagecopy($cut, $this->resultImage, 0, 0, 0, 0, $this->size[0], $this->size[1]);
            imagecopy($cut, $img, 0, 0, 0, 0, $this->size[0], $this->size[1]);
            if (!$img)
                throw new Exception('Error loading the requested images');
            if (!imagecopymerge($this->resultImage, $cut, 0, 0, 0, 0, $this->size[0], $this->size[1], 100))
                throw new Exception('Error merging images');
        }
    }

    public function createImage() {
        try {
            $this->downloadImages();
            $this->mergeImages();
            //SG: PHP BUG #52339 https://bugs.php.net/bug.php?id=52339&edit=1
            /*if (class_exists('R3AppInit')) {
                $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
                ezcLog::getInstance()->log(__METHOD__ . ", $elapsedTime, image created", ezcLog::DEBUG);
            }*/
        } catch (Exception $e) {
            throw($e);
        }
    }

    public function sldHighlight($layerName, $fileUrl, $excludeLayer = false) {
        if (empty($layerName))
            throw new Exception('Specify a layer name');
        if (empty($fileUrl))
            throw new Exception('Specify an SLD xml file url');

        $sldLayer = null;
        $selectedLayer = null;
        foreach ($this->wms as $key => $layer) {
            if ($layer['name'] == $layerName) {
                $sldLayer = $layer;
                $sldLayer['parameters']['sld'] = $fileUrl;
                $selectedLayer = $key;
            }
        }

        if (!empty($sldLayer)) {
            $this->wms['sld_'.$layerName] = $sldLayer;
            if($excludeLayer && !empty($selectedLayer))
                unset($this->wms[$selectedLayer]);
        }
    }

    protected function getUniqueRandomTmpFilename($dir) {
        $letters = '1234567890qwertyuiopasdfghjklzxcvbnm';
        $lettersLength = strlen($letters) - 1;
        $filename = 'r3ogcmap_';
        for ($n = 0; $n < 20; $n++) {
            $filename .= $letters[rand(0, $lettersLength)];
        }
        if (file_exists($filename))
            return $this->getUniqueRandomTmpFilename($dir);
        else
            return $filename;
    }

    public function getCanvas($format = 'png') {
        $filename = $this->saveImageToTmp($format);
		$content = file_get_contents($filename);
		unlink($filename);
        return $content;
    }

    public function saveImageToTmp($format = 'png', $dir = null, $return = 'path') {
        if($dir == null) {
            if (defined('R3_TMP_PATH'))
                $dir = R3_TMP_PATH;
            else
                $dir = '/tmp/';
        }
        if (substr($dir, -1) != '/')
            $dir .= '/';
        $filename = $this->getUniqueRandomTmpFilename($dir);
        if (!isset($this->supportedFormats[$format]))
            throw new Exception('Format not supported');
        $outputFunction = $this->supportedFormats[$format];
        if (!$outputFunction($this->resultImage, $dir . $filename))
            throw new Exception('Error saving image '.$dir.$filename);
        if($return == 'filename')
            return $filename;
        else
            return $dir.$filename;
    }
    
    public function saveImage($file, $format = 'png') {
        if (!isset($this->supportedFormats[$format]))
            throw new Exception('Format not supported');
        $outputFunction = $this->supportedFormats[$format];
        if (!$outputFunction($this->resultImage, $file))
            throw new Exception('Error saving file');
    }

    public function outputImage($format = 'png') {
        if (!isset($this->supportedFormats[$format]))
            throw new Exception('Format not supported');
        $outputFunction = $this->supportedFormats[$format];
        if (!$outputFunction($this->resultImage))
            throw new Exception('Error saving image');
    }

}

/*
function gc_relativeToAbsoluteUrl($url) {
    if(defined('GC_ABSOLUTE_URL') && strpos($url, 'http://') === false) {
            return GC_ABSOLUTE_URL.$url;
    }
    return $url;
}
*/

class R3OgcMapUtils {

    /**
     * Add prefix to the relative URL
     * @param string $url
     * @return string
     */
    public static function addPrefixToRelativeUrl($url) {
        if (defined('GC_RELATIVE_URL_PREFIX') && preg_match("/^(http|https):\/\//", GC_MAP_SET_URL, $matches)) {
            throw new Exception("The constant GC_MAP_SET_URL is not a relative URL");
        }
        if (defined('GC_RELATIVE_URL_PREFIX') && !preg_match("/^(http|https):\/\//", $url, $matches)) {
            return GC_RELATIVE_URL_PREFIX.$url;
        } else if (!preg_match("/^(http|https):\/\//", $url, $matches)) {
            return R3_DOMAIN_URL.$url;
        }
        return $url;
    }

}