<?php

class R3PrintMap {

    protected $options;

    protected $tiles = array();
	protected $extent = array();
	protected $dimensions = array(
		'vertical'=>array(
			'A4'=>array('w'=>17,'h'=>22.5),
			'A3'=>array('w'=>25.8,'h'=>35),
			'A2'=>array('w'=>38,'h'=>52),
			'A1'=>array('w'=>55,'h'=>76),
			'A0'=>array('w'=>80,'h'=>111)
		),
		'horizontal'=>array(
			'A4' => array('w'=>25.8,'h'=>14),
			'A3' => array('w'=>38,'h'=>22.5),
			'A2' => array('w'=>55,'h'=>34),
			'A1' => array('w'=>80,'h'=>52),
			'A0' => array('w'=>115,'h'=>77)
		)
	);
	protected $wmsList = array();
	protected $imageSize = array();
	protected $documentiSize = array();
	protected $documentElements = array();
	protected $imageFileName = '';
	protected $legendArray = array();
	protected $db = null;
	protected $textVectors = array();
	protected $wmsVectors = array();
	protected $getLegendGraphicWmsList = array();

    public function __construct(array $tiles, array $options = array()) {
        $defaultOptions = array(
            'format' => 'A4',
            'dpi' => 150,
            'direction' => 'vertical',
            'TMP_PATH' => R3_WEB_TMP_DIR,
			'TMP_URL' => R3_WEB_TMP_URL,
			'legend' => null,
			'scale_mode' => 'auto',
			'extent' => null,
			'viewport_size' => array(),
			'center' => array(),
			'srid' => null,
			'pixels_distance' => null,
			'legendDownloadMethod' => 'file' // downloadMethod = 'wms' to get images with getlegendgraphic
        );
        $this->options = array_merge($defaultOptions, $options);
		
		if(substr($this->options['TMP_PATH'], -1) != '/') $this->options['TMP_PATH'] .= '/';
		if(substr($this->options['TMP_URL'], -1) != '/') $this->options['TMP_URL'] .= '/';
		
		if(empty($tiles))
			throw new Exception('No tiles');
		$this->tiles = $tiles;
		
		if(!isset($this->dimensions[$this->options['direction']])) throw new Exception('Invalid direction');
		if(!isset($this->dimensions[$this->options['direction']][$this->options['format']])) throw new Exception('Invalid print format');
		
		if($options['scale_mode'] == 'user') {
			if(empty($options['extent']) || count($options['extent']) != 4)
				throw new Exception('For user-defined scale mode, an array of bottom, left, top, right coordinates must be provided');
		} else {
			if(empty($options['pixels_distance']) || empty($options['viewport_size']) || empty($options['center']))
				throw new Exception('For auto scale mode, pixels_distance, viewport_size and center must be provided');
		}
	}
	
	public function setDocumentElement($key, $value) {
		$this->documentElements[$key] = $value;
	}
	
	public function setDB($db) {
		$this->db = $db;
	}
	
	public function printMapHTML($xslfile = null) {
		if($xslfile == null) {
			if(file_exists(GC_PRINT_TPL_DIR.'print_map_html.xsl')) {
				$xslfile = GC_PRINT_TPL_DIR.'print_map_html.xsl';
			} else {
				throw new Exception('Please provide an xsl file');
			}
		}
		
		try {
			$dom = $this->buildDOM();
			$tmpdoc = new DOMDocument();
			$xsl = new XSLTProcessor();

			$tmpdoc->load($xslfile);
			$xsl->importStyleSheet($tmpdoc);

			$content = $xsl->transformToXML($dom);
			$filename = 'printmap_'.rand(0,99999999).'.html';
			file_put_contents($this->options['TMP_PATH'].$filename, $content);
			$this->deleteOldTmpFiles();
		} catch(Exception $e) {
			throw $e;
		}
		return $this->options['TMP_URL'].$filename;
	}
	
	public function printMapPDF($xslfile = null, $xmlfile = null) {
		if($xslfile == null) {
			if(file_exists(GC_PRINT_TPL_DIR.'print_map.xsl')) {
				$xslfile = GC_PRINT_TPL_DIR.'print_map.xsl';
			} else {
				throw new Exception('Please provide an xsl file');
			}
		}
		
		try {
			$dom = $this->buildDOM();
			$xml = $dom->saveXML();
                        if ($xmlfile !== null) {
                            $dom->save($xmlfile);
                        }

			$pdfFile = runFOP($dom, $xslfile, array('tmp_path'=>$this->options['TMP_PATH'], 'prefix'=>'GCPrintMap-'));
			$pdfFile = str_replace($this->options['TMP_PATH'], $this->options['TMP_URL'], $pdfFile);
		} catch (Exception $e) {
			throw $e;
		}
		return $pdfFile;
	}
	
	public function addVectors($vectors, $srid) {
		foreach($vectors as $vector) {
			if(isset($vector['text'])) array_push($this->textVectors, $vector);
			else array_push($this->wmsVectors, $vector);
		}
	}
	
	protected function createVectorWms($vectors, $srid) {
		return;
		$vectorsByType = array(
			'POINT'=>array(),
			'LINESTRING'=>array(),
			'POLYGON'=>array()
		);
		
		$styles = array();
		
		foreach($vectors as $vector) {
			$style = $vector['linecolor'].'|'.$vector['fillcolor'];
			$styleIndex = array_search($style, $styles);
			if(!$styleIndex) {
				array_push($styles, $style);
				$styleIndex = count($styles);
			}
			$vector['class'] = $styleIndex;
			foreach($vectorsByType as $geomType => $foo) {
				if(strpos($vector, $geomType) !== false) array_push($vectorsByType[$geomType], $vector);
			}
		}
		unset($vectors);
		
		$db = ($this->db == null) ? $db = ezcDbInstance::get() : $db = $this->db;

		$utils = new R3ImportUtils($db, array('schema' => R3_IMPORT_SCHEMA));
		$utils->dropTemporaryTableBySessionID(session_id());
		$utils->createImportSystemTable();
		
		$tables = array();
		
		foreach($vectorsByType as $geomType => $vectors) {
			$tmpTableName = $utils->getSchema() . '.' . $utils->createTemporaryTableEntry('pointgeoms', 'pointgeoms', session_id());
			$sql = "CREATE TABLE ".$utils->getSchema().".".$tmpTableName."(
						gid serial NOT NULL,
						class integer,
						the_geom geometry,
						CONSTRAINT ".$tmpTableName."_gid PRIMARY KEY (gid),
						CONSTRAINT enforce_dims_the_geom CHECK (st_ndims(the_geom) = 2),
						CONSTRAINT enforce_geotype_the_geom CHECK (geometrytype(the_geom) = 'POINT'::text OR the_geom IS NULL),
						CONSTRAINT enforce_srid_the_geom CHECK (st_srid(the_geom) = ".$srid.")
					)
					WITH (
						OIDS=FALSE
					);
					GRANT SELECT ON TABLE ".$utils->getSchema().".".$tmpTableName." TO mapserver;";
			$db_res = $db->prepare($sql);
			$db_res->execute();
			
			foreach($vectors as $vector) {
				$sql = "insert into ".$utils->getSchema().".".$tmpTableName." (class, the_geom) select ".$vector['class'].", st_geomfromtext('".$vector['geometry']."')";
				$db_res = $db->prepare($sql);
				$db_res->execute();
			}
			
			array_push($tables, $utils->getSchema().'.'.$tmpTableName);
		}
		
		$wms = array(
			'url'=>GC_VECTOR_MAPFILE_URL,
			'parameters'=>array(
				'tables'=>implode(',',$tables)
			)
		);
		foreach($styles as $styleIndex => $style) {
			$wms['parameters']['style'.$styleIndex] = $style;
		}
		return $wms;
	}
	
	protected function drawTextRedline($vector, $srid) {
		// TODO with GD
	}
	
	protected function buildWmsList() {
		foreach($this->tiles as $key => $tile) {
			$pos = strpos($tile['url'], '?');
			if($pos == false) throw new Exception('URL without query string');
			
			$queryString = substr($tile['url'], ($pos+1));
			$parameters = array();
            $tmpParameters = array();
			parse_str($queryString, $tmpParameters);
            foreach($tmpParameters as $parameterName => $parameterValue)
                $parameters[strtoupper($parameterName)] = $parameterValue;
            
            
			$wms = array('url'=>substr($tile['url'], 0, $pos),'parameters'=>$parameters);
			array_push($this->wmsList, $wms);
			
			if($this->options['legendDownloadMethod'] == 'wms') {
				$legendGraphicRequest = array_merge($parameters, array(
					'url'=>substr($tile['url'], 0, $pos+1),
					'PROJECT'=>$parameters['PROJECT'],
					'REQUEST' => 'GetLegendGraphic',
					'ICONW' => 24,
					'ICONH' => 16,
					'GCLEGENDTEXT' => 0
				));
				$this->getLegendGraphicWmsList[$parameters['MAP']] = $legendGraphicRequest;
			}
		}
	}
	
	protected function buildLegendArray() {
		if(empty($this->options['legend'])) return null;
		try {
			$legendImages = array();
            if (class_exists('R3AppInit')) {
                $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
                ezcLog::getInstance()->log(__METHOD__.", $elapsedTime, themes in legend:".
                        var_export($this->options['legend']['themes'], true), ezcLog::DEBUG);
            }
            
			foreach($this->options['legend']['themes'] as $theme) {
				$themeArray = array('id'=>$theme['id'],'title'=>$theme['title'],'groups'=>array());
				foreach($theme['groups'] as $group) {
					$groupArray = array('id'=>$group['id'],'title'=>$group['title'],'layers'=>array());
					foreach($group['layers'] as $key => $layer) {
						$tmpFileId = $theme['id'].'-'.$group['id'];
						if($this->options['legendDownloadMethod'] == 'wms') {
							if(!isset($legendImages[$layer['url']])) {
								$params = array('theme'=>$theme['id'], 'group'=>$group['id']);
								$legendImages[$layer['url']] = $this->getLegendImageWMS($theme['id'], $group['id'], $tmpFileId);
							}
							if(!$legendImages[$layer['url']]) continue;
							$source = imagecreatefrompng($legendImages[$layer['url']]);
							$dest = imagecreatetruecolor(24, 16);
							$offset = $key*16;
							imagecopy($dest, $source, 0, 0, 0, $offset, 24, 16);
							$filename = $tmpFileId.'-'.$key.'.png';
							imagepng($dest, $this->options['TMP_PATH'].$filename);
						} else {
							if(!isset($legendImages[$layer['url']])) {
								$legendImages[$layer['url']] = $this->getLegendImage($layer['url'], $tmpFileId);
							}
							$source = imagecreatefrompng($legendImages[$layer['url']]);
							$dest = imagecreatetruecolor(24, 16);
							$offset = $key*24;
							imagecopy($dest, $source, 0, 0, $offset, 0, 24, 16);
							$filename = $tmpFileId.'-'.$key.'.png';
							imagepng($dest, $this->options['TMP_PATH'].$filename);
						}
						array_push($groupArray['layers'], array('title'=>$layer['title'],'img'=>$this->options['TMP_URL'].$filename));
					}
					array_push($themeArray['groups'], $groupArray);
				}
				array_push($this->legendArray, $themeArray);
			}
		} catch(Exception $e) {
			throw $e;
		}
	}
	
	protected function getLegendImageWMS($theme, $group, $tmpFileId) {
		if(!isset($this->getLegendGraphicWmsList[$theme])) return false;
		$request = $this->getLegendGraphicWmsList[$theme];
		$request['LAYER'] = $group;
		$url = $request['url'];
		unset($request['url']);
		$queryString = http_build_query($request);
        if (class_exists('R3AppInit')) {
            $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
            ezcLog::getInstance()->log(__METHOD__ . ", $elapsedTime, URL : $url$queryString", ezcLog::DEBUG);
        }

        return $this->getLegendImage($url.$queryString, $tmpFileId);
	}
	
	protected function getLegendImage($url, $tmpFileId) {
        $dest = $this->options['TMP_PATH'] . $tmpFileId . '.png';
        $url = R3OgcMapUtils::addPrefixToRelativeUrl($url);
        $ch = curl_init($url);
        if (($fp = fopen($dest, "wb")) === FALSE) {
            throw new Exception("Could not open $dest");
        }

        $options = array(CURLOPT_FILE => $fp, CURLOPT_HEADER => 0, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 60);
        curl_setopt_array($ch, $options);

        if (($rv = curl_exec($ch)) === FALSE) {
            throw new Exception("Could not get data from $url");
        }
        curl_close($ch);
        fclose($fp);
        return $dest;
    }

    protected function calculateSizes() {
		if($this->options['direction'] == 'horizontal') {
			$dimension = array(
				'w'=>$this->dimensions[$this->options['direction']][$this->options['format']]['w'], 
				'h'=>$this->dimensions[$this->options['direction']][$this->options['format']]['h']
			);
		} else {
			$dimension = array(
				'w'=>$this->dimensions[$this->options['direction']][$this->options['format']]['w'], 
				'h'=>$this->dimensions[$this->options['direction']][$this->options['format']]['h']
			);
		}

		$this->imageSize = array((int)round(($dimension['w']/(2.54))*$this->options['dpi']), (int)round(($dimension['h']/(2.54))*$this->options['dpi']));
		
		$this->documentiSize = array($this->dimensions[$this->options['direction']][$this->options['format']]['w'], $this->dimensions[$this->options['direction']][$this->options['format']]['h']);
	}
	
	protected function getMapImage() {
        try {
            if (class_exists('R3AppInit')) {
                $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
                ezcLog::getInstance()->log(__METHOD__ . ", $elapsedTime, start", ezcLog::DEBUG);
            }
            $this->calculateSizes();
            $this->buildWmsList();
            if ($this->options['scale_mode'] == 'user') {
                $this->calculateExtent();
            } else {
                $this->adaptExtentToSize();
            }

            $r3OgcMap = new R3OgcMap($this->extent, $this->imageSize, $this->wmsList);

            #$wms = $this->createVectorWms();
            #$r3OgcMap->registerWMS($wms);
            #$textWms = $this->drawTextVectors();
            // TODO: draw vector features

            $r3OgcMap->createImage();
            $this->imageFileName = $r3OgcMap->saveImageToTmp('png', $this->options['TMP_PATH'], 'filename');
            if (class_exists('R3AppInit')) {
                $elapsedTime = sprintf("elapsed time: %.3f s", microtime(true) - R3AppInit::$startTime);
                ezcLog::getInstance()->log(__METHOD__ . ", $elapsedTime, end", ezcLog::DEBUG);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function adaptExtentToSize() {
		$leftBottom = "st_geomfromtext('POINT(".$this->options['extent'][0]." ".$this->options['extent'][1].")', ".$this->options['srid'].")";
		$rightBottom = "st_geomfromtext('POINT(".$this->options['extent'][2]." ".$this->options['extent'][1].")', ".$this->options['srid'].")";
		$rightTop = "st_geomfromtext('POINT(".$this->options['extent'][2]." ".$this->options['extent'][3].")', ".$this->options['srid'].")";
		$leftTop = "st_geomfromtext('POINT(".$this->options['extent'][0]." ".$this->options['extent'][3].")', ".$this->options['srid'].")";
		$sql = "select st_length(st_makeline($leftBottom, $rightBottom)) as w, st_length(st_makeline($leftBottom, $leftTop)) as h";
		$measures = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
		
		if($measures['w'] > $measures['h']) {
			$longEdge = 'w';
			$height = ($measures['w']/$this->imageSize[0])*$this->imageSize[1];
			$buffer = ($height - $measures['h'])/2;
			$width = $measures['w'];
		} else {
			$longEdge = 'h';
			$width = ($measures['h']/$this->imageSize[1])*$this->imageSize[0];
			$buffer = ($width - $measures['w'])/2;
		}
		$extentPolygon = "st_polygon(st_makeline(ARRAY[$leftBottom, $rightBottom, $rightTop, $leftTop, $leftBottom]), ".$this->options['srid'].")";
                $extentPolygon = <<<SQL
                    SELECT ST_SetSrid(ST_Extent(the_geom), {$this->options['srid']}) FROM (
                        SELECT {$leftBottom} AS the_geom
                         UNION
                        SELECT {$rightBottom} AS the_geom
                         UNION
                        SELECT {$rightTop} AS the_geom
                         UNION
                        SELECT {$leftTop} AS the_geom
                    ) AS foo
SQL;
		
		$sql = "select box2d(st_buffer((".$extentPolygon."), $buffer))";
		$box = $this->db->query($sql)->fetchColumn(0);
		$box = $this->parseBox($box);
		
		if($longEdge == 'w') {
			$this->extent = array($this->options['extent'][0], $box[1], $this->options['extent'][2], $box[3]);
		} else {
			$this->extent = array($box[0], $this->options['extent'][1], $box[2], $this->options['extent'][3]);
		}
		
		$mapMetersW = ($this->dimensions[$this->options['direction']][$this->options['format']]['w']/100);
		$scale = round($width/$mapMetersW);
		
		$this->setDocumentElement('map-scale', $scale);
	}
	
	protected function calculateExtent() {
		if($this->imageSize[0] > $this->imageSize[1]) {
			$longEdge = 'w';
			$shortEdgeDividend = $this->imageSize[1];
			$longEdgeDividend = $this->imageSize[0];
		} else {
			$longEdge = 'h';
			$shortEdgeDividend = $this->imageSize[0];
			$longEdgeDividend = $this->imageSize[1];
		}

		$shortBuffer = ($shortEdgeDividend / $this->options['pixels_distance'])/2;
		$longBuffer = ($longEdgeDividend / $this->options['pixels_distance'])/2;
		$center = "st_geomfromtext('POINT(".$this->options['center'][0]." ".$this->options['center'][1].")', ".$this->options['srid'].")";
		$sql = "select box2d(st_buffer($center, $shortBuffer)) as short, box2d(st_buffer($center, $longBuffer)) as long";
		$boxes = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
		$boxes = $this->parseBoxes($boxes);
		
		if($longEdge == 'w') {
			$this->extent = array($boxes['long'][0], $boxes['short'][1], $boxes['long'][2], $boxes['short'][3]);
		} else {
			$this->extent = array($boxes['short'][0], $boxes['long'][1], $boxes['short'][2], $boxes['long'][3]);
		}
	}
	
	protected function parseBoxes($boxes) {
		$parsedBoxes = array();
		foreach(array('long', 'short') as $type) {
			$parsedBoxes[$type] = $this->parseBox($boxes[$type]);
		}
		return $parsedBoxes;
	}
	
	protected function parseBox($box) {
		$split = explode(',', str_replace(array('BOX(',')'), '', $box));
		list($l, $b) = explode(' ', $split[0]);
		list($r, $t) = explode(' ', $split[1]);
		return array($l, $b, $r, $t);
	}
	
	protected function buildDOM() {
		try {
			$this->getMapImage();
			$this->buildLegendArray();
		} catch(Exception $e) {
			throw $e;
		}

		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->formatOutput = true;
		$dom_map = $dom->appendChild(new DOMElement('map'));

		$dom_layout = $dom_map->appendChild(new DOMElement('page-layout'));
		
		$direction = ($this->options['direction'] == 'vertical') ? 'P' : 'L';
		$layout = $this->options['format'].$direction;
		
		$dom_layout->appendChild(new DOMText($layout));

		$dom_width = $dom_map->appendChild(new DOMElement('map-width'));
		$dom_width->appendChild(new DOMText($this->documentiSize[0]));

		$dom_height = $dom_map->appendChild(new DOMElement('map-height'));
		$dom_height->appendChild(new DOMText($this->documentiSize[1]));

		$dom_img = $dom_map->appendChild(new DOMElement('map-img'));
		$dom_img->appendChild(new DOMText($this->options['TMP_URL'].$this->imageFileName));

		if(isset($this->documentElements['map-date'])) {
			$dom_date = $dom_map->appendChild(new DOMElement('map-date'));
			$dom_date->appendChild(new DOMText($this->documentElements['map-date']));
		}

		foreach($this->documentElements as $key => $val) {
			$dom_element = $dom_map->appendChild(new DOMElement($key));
			$dom_element->appendChild(new DOMText($val));
		}

		if(!empty($this->legendArray)) {
			$dom_legend = $dom_map->appendChild(new DOMElement('map-legend'));
			
			$i = 0;			
			foreach($this->legendArray as $theme) {
				if(empty($theme['groups'])) continue;
				$continue = true;
				foreach($theme['groups'] as $group) {
					if(!empty($group['layers'])) $continue = false;
				}
				if($continue) continue;
				$dom_group = $dom_legend->appendChild(new DOMElement('legend-group'));
				$dom_title = $dom_group->appendChild(new DOMElement('group-title'));
				$dom_title->appendChild(new DOMText($theme['title']));

				$dom_icon = $dom_group->appendChild(new DOMElement('group-icon'));
				$dom_icon->appendChild(new DOMText(''));
				
				foreach($theme['groups'] as $group) {
					foreach($group['layers'] as $layer) {
						if ($i % 3 == 0) $dom_grp_block = $dom_group->appendChild(new DOMElement('group-block'));
						$i++;
						$dom_item = $dom_grp_block->appendChild(new DOMElement('group-item'));
						$dom_item_attr = $dom_item->appendChild(new DOMElement('title'));
						$dom_item_attr->appendChild(new DOMText($layer['title']));
						$dom_item_attr = $dom_item->appendChild(new DOMElement('icon'));
						$dom_item_attr->appendChild(new DOMText($layer['img']));
					}		
				}
				while($i % 3 != 0) {
					$dom_item = $dom_grp_block->appendChild(new DOMElement('group-item'));
					$i++;
				}
			}
		}
		return $dom;
	}
	
	protected function deleteOldTmpFiles() {
		if ($handle = opendir($this->options['TMP_PATH'])) {
			while (false !== ($file = readdir($handle))) {
				if ($file[0] == '.')
					continue;

				$name = $this->options['TMP_PATH'] . '/' . $file;
				$isold = (time() - filectime($name)) > 5 * 60 * 60;
				$ext = strtolower(strrchr($name, '.'));
				if (is_file($name) && $isold) {
					unlink($name);
				}
			}
			closedir($handle);
		}
	}
	
}
