<?php
require_once __DIR__ . '/../../author/config/config.php';

$db = GCApp::getDB();
$user = new GCUser();
$showAsPublic = 1;
if ($user->isAuthenticated()) {
    if (empty($_REQUEST['show_as_public'])) {
        $showAsPublic = 0;
    }
    
}

$lang = 'it';
if (!empty($_REQUEST['lang'])) {
    $lang = $_REQUEST['lang'];
}

//$mapsetName = 'r3-trees'; 
//$project = 'demo';
if (!empty($_REQUEST['mapset'])) {
    $mapsetName = $_REQUEST['mapset'];
}

$sql = 'SELECT project_name, mapset_srid FROM '.DB_SCHEMA.'.mapset WHERE mapset_name = :name';
$stmt = $db->prepare($sql);
$stmt->execute(array('name'=>$mapsetName));

$mapset = $stmt->fetch();
if ($mapset === false) {
    header("HTTP/1.0 404 Not Found");
    die("Mapset \"{$mapsetName}\" not found");
}

$project = $mapset['project_name'];
$srid = $mapset['mapset_srid'];
$spatial = $db->query("SELECT * FROM public.spatial_ref_sys WHERE srid = {$srid}")->fetch();

$mapsetURL = PUBLIC_URL;  // "http://freegis.r3-gis.com/author/"
$queryParams = array();
parse_str($_SERVER['QUERY_STRING'], $queryParams);
$queryParams['lang'] = 'it';
$linkIT = '?'.http_build_query($queryParams);
$queryParams['lang'] = 'de';
$linkDE = '?'.http_build_query($queryParams);
$queryParams['lang'] = 'en';
$linkEN = '?'.http_build_query($queryParams);

$v = 1;

$helpPage = 'help';
if ($lang != 'it') {
    $helpPage .= '_'.$lang;
}
$helpPage .= '.html';

?><!DOCTYPE html>
<head>
    <title></title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <!-- CSS -->
        <link type="text/css" href="css/main.css" rel="Stylesheet" />
        <link type="text/css" href="external/jquery-ui/smoothness/jquery-ui-1.10.4.custom.min.css" rel="stylesheet" />
        <link type="text/css" href="css/jqueryUi.icons.css" rel="Stylesheet" />

    <!-- JS -->
        <script type="text/javascript" src="external/OpenLayers/OpenLayers.js"></script>
        <script type="text/javascript" src="languages/lang-it.js?v=<?php echo $v ?>"></script>
        <script type="text/javascript" src="languages/lang-de.js?v=<?php echo $v ?>"></script>
        <script type="text/javascript" src="external/proj4js/proj4.js"></script>
        <script type="text/javascript">
            Proj4js.defs["EPSG:<?php echo $spatial['srid'] ?>"] = "<?php echo $spatial['proj4text'] ?>";
            OpenLayers.Lang.setCode('<?php echo $lang ?>');
        </script>
        <!--script type="text/javascript" src="http://code.jquery.com/jquery-1.11.1.js"></script-->
        <script type="text/javascript" src="external/jquery/jquery-1.11.1.min.js"></script>
        <!--script type="text/javascript" src="https://code.jquery.com/ui/1.10.4/jquery-ui.js"></script-->
        <script type="text/javascript" src="external/jquery-ui/jquery-ui-1.10.4.min.js"></script>
        <script type="text/javascript" src="external/jstree/jquery.jstree.min.js"></script>
        <script type="text/javascript" src="external/plugin-jquery/jquery.maxzindex.js"></script>
        <script type="text/javascript" src="external/plugin-jquery/mColorPicker_min.js"></script>
        <script type="text/javascript" src="external/plugin-jquery/jquery.ie-select-width.min.js"></script>
        <script type="text/javascript" src="external/helperScript/cookieFunctions.js"></script>
        <script type="text/javascript" src="external/plugin-jquery/jquery.ajaxfileupload.js"></script>
        <script type="text/javascript" src="js/init-layout.js"></script>

    <!-- R3layout -->
        <link type="text/css" href="js/R3layout/css/R3layout.css" rel="Stylesheet" />
        <!--[if lt IE 9]>  
            <script type="text/javascript" src="js/R3layout/js/forIE.js"></script>   
            <link type="text/css" href="js/R3layout/css/forIE.css" rel="Stylesheet" />
        <![endif]-->
        <script type="text/javascript" src="js/R3layout/js/R3layout.js"></script>
        <style>
            #header{
                top: 0;
                height: 33px;
                position: absolute;
                width: 100%;
            }
            
            #wrapper{
                top: 33px;
                position: absolute;
                width: 100%;
                bottom: 20px;
            }
            
            #footer{
                height: 20px;
                position: absolute;
                width: 100%;
                bottom:0;
            }
        </style>
    <!-- GisClient -->
        <!-- jqgrid --> 
        <link type="text/css" href="external/jqGrid/ui.jqgrid.css" rel="Stylesheet" />
        <script type="text/javascript" src="external/jqGrid/grid.locale-it.js"></script>
        <script type="text/javascript" src="external/jqGrid/jquery.jqGrid.min.js"></script> 
        <script type="text/javascript" src="external/jqGrid/jquery.fmatter.js"></script>
           
    <!-- OpenLayers style -->
        <link type="text/css" href="external/OpenLayers/theme/default/style.css" rel="stylesheet"  media="all" />
        <style>
            #div_viewtable table {
                width:100%;
                border:1px solid black;
            }
            #div_viewtable tr {
                border:1px solid black;
            }
            #div_viewtable td {
                border:1px solid black;
            }
            #div_viewtable tr:hover {
                background-color:#cccccc;
            }
            #layout_container { padding: 0px; }
            #toolbar { padding-left: 15px; }
            div.olControlAttribution{bottom:0px}
        </style>
    
    
    <script type="text/javascript" src="js/widgetGisClient.js?v=<?php echo $v ?>"></script>
    <script typE="text/javascript" src="js/searchEngine.js?v=<?php echo $v ?>"></script>
    
    <!-- Gc Tools -->
    <script type="text/javascript" src="js/gcTool.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/zoomToMaxExtent.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/zoomToHistoryPrevious.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/zoomToHistoryNext.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/pan.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/zoomIn.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/zoomOut.js?v=<?php echo $v ?>"></script>
    <script typE="text/javascript" src="js/searchEngine.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/selectFromMap.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/measureLine.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/measureArea.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/mapImageDownload.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/drawFeature.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/mapContext.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/reloadLayers.js?v=<?php echo $v ?>"></script>
    <!--script type="text/javascript" src="js/gcTool/easySelectFromMap.js?v=<?php echo $v ?>"></script-->
    <script type="text/javascript" src="js/gcTool/redline.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/wfstEdit.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/mapImageDownload.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/toolTip.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/mapPrint.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/selectBox.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/selectPoint.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/selectFeatures.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/unselectFeatures.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/NavigationHistory.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/toStreetView.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/mapHelp.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcTool/geolocate.js?v=<?php echo $v ?>"></script>
    
    <!-- Gc Components -->
    <script type="text/javascript" src="js/gcComponent.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/gcLayerTree.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/gcLegendTree.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/mapInfo.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/snapPoint.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/referenceMap.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/mapImageDialog.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/scaleDropDown.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/layerTools.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/contextHandler.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/searchForm.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/viewTable.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/detailTable.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/errorHandler.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/loadingHandler.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/gcLayersManager.js?v=<?php echo $v ?>"></script>
    <script type="text/javascript" src="js/gcComponent/geolocator.js?v=<?php echo $v ?>"></script>
    
    
        <!-- external -->
        
    <script type="text/javascript">
    
if (!$.ui.dialog.prototype._makeDraggableBase) {
    $.ui.dialog.prototype._makeDraggableBase = $.ui.dialog.prototype._makeDraggable;
    $.ui.dialog.prototype._makeDraggable = function() {
        this._makeDraggableBase();
        this.uiDialog.draggable("option", "containment", false);
    };
}

    
    $(window).load(function() {
        OpenLayers.ImgPath = "images/icons/";
        GCMAP = $("#mapOL").gisclientmap({
        'project_name':'<?php echo $project ?>',
        'mapsetName':'<?php echo $mapsetName ?>',
        'mapsetURL' : '<?php echo $mapsetURL ?>',
        'showAsPublic' : <?php echo $showAsPublic ?>,
        'language':'<?php echo $lang ?>',
        'tools':{
            'zoomFull':'zoom_full',
            'zoomPrev':'zoom_prev',
            'zoomNext':'zoom_next',
            'zoomIn':'zoom_in',
            'zoomOut':'zoom_out',
            'Pan':'pan',
            'selectFromMap':'select_by_box',
            'unselectFeatures':'unselect_features',
            // 'easySelectFromMap':'easy_select_from_map',
            'toStreetView': 'to_street_view',
            'measureLine':'measure_line',
            'measureArea':'measure_polygon',
            'mapContext': 'map_context',
            'reloadLayers': 'reload_layers',
            'redline': 'redline',
            'mapImageDownload': 'mapimage_download',
            'toolTip': 'tooltip',
            'wfstEdit': 'edit_feature',
            'geolocate': 'geolocate',
            'mapHelp': 'map_help',
            'mapPrint': 'print'
        },
        toolsOptions: {
            mapHelp: {
                helpUrl: '<?php echo $helpPage ?>'
            }
        },
        componentsOptions: {
            gcLayerTree: {
                showMoveLayersButtons: false, //true= bottoni sposta su giu
                showLayerTools: true,
                showLayerMetadata:true,
                defaultThemeOptions: {
                    radio: false,
                    moveable: true,
                    deleteable: false
                }
            },
            mapImageDialog: {
                logoDx: '',
                allowedPrintFormats: ['A4','A3','A2','A1','A0'],
                displayBox: true
            },
            contextHandler: {
                saveOnZoomEnd: false
            }
        },
        mapOptions:{
            fractionalZoom:true,            //scale fisse: false=SI, true=NO
        },
        "layerTree":'treeList',
        "displayposition":'coordinates',
        "displaymeasure":'coordinates',
        "legend":true,
        "querytemplate":true,
        "baseLayerFirst":false,
        'gisclientready': function() {
            gisclient.startMap();

            <?php if (!empty($_REQUEST['context'])) { ?>
                gisclient.componentObjects.contextHandler.getContext(<?php echo $_REQUEST['context'] ?>);
            <?php } ?>
            
            var titleString = $(document).attr('title');
            if(titleString.length > 0) titleString += ' - ';
            titleString += gisclient.getMapOptions().mapsetTitle;
            $(document).prop('title', titleString);
            
            <?php if(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'zoomon' && !empty($_REQUEST['featureType']) && !empty($_REQUEST['fieldName']) && !empty($_REQUEST['value'])) {
            $highlight = !empty($_REQUEST['highlight']) ? 'true' : 'false';
            ?>
            gisclient.zoomOn({
                featureType: '<?php echo $_REQUEST['featureType'] ?>',
                field: '<?php echo $_REQUEST['fieldName'] ?>',
                value: '<?php echo $_REQUEST['value'] ?>'
            }, <?php echo $highlight ?>);
            <?php } ?>
            
            var themes=gisclient.componentObjects.gcLayersManager.getThemes();
            $.each(themes, function(themeName, data) {
                var layers = gisclient.componentObjects.gcLayersManager.getLayers(themeName);
                $.each(layers, function(layerName, data2) {
                    var theLayer = gisclient.componentObjects.gcLayersManager.getLayer(themeName, layerName);
                    if (theLayer) {
                        theLayer.olLayer.addOptions({transitionEffect: 'resize'}, true);
                    }
                });
            });
                                
        },
        'gctreeloaded': function() {
            $('#treeDiv a.up').button({ icons: { primary: "ui-icon-triangle-1-n" }, text:false });
            $('#treeDiv a.down').button({ icons: { primary: "ui-icon-triangle-1-s" }, text:false });
            $('#treeDiv a.opacity').button({ icons: { primary: "ui-icon-wrench" }, text:false });
            $('#treeDiv a.delete').button({ icons: { primary: "ui-icon-trash" }, text:false });
            $('#treeDiv a.info').button({ icons: { primary: "ui-icon-info" }, text:false });
        },
        "divs": {
            selectionSettings: 'selection_settings',
            lineMeasure: 'misure',
            lineMeasurePartial: 'misure_partial',
            areaMeasure: 'misure',
            redlineDialog: 'redline_dialog',
            referenceMap: 'refMapContainer',
            footer: 'footer',
            mapInfoScale: 'mapInfoScale',
            mapInfoMousePosition: 'mapInfoMousePosition',
            mapInfoMousePositionLatLon: 'mapInfoMousePositionLatLon',
            mapInfoRefSystemDescription: 'mapInfoRefSystemDescription',
            mapInfoRefSystem: 'mapInfoRefSystem',
            toolBar: 'toolbar',
            detailTable: 'detailTable',
            scaleDropDown: 'scaleDropDown',
            searchList: 'searchList',
            editingSettings: 'editing_settings',
            treeList: 'treeList',
            legendList: 'legendList',
            dataList: 'dataList',
            tree: 'treeDiv',
            loading: 'loading_indicator',
            errors: 'errors_indicator',
            geolocator: 'geolocator',
            snapOptionsId: 'snap_options',
            viewTable: 'div_viewtable',
            positionLink: 'position_link'
        },
            activateKeyboardControl: false,
        });
    });
    </script>
    
</head>
<body>
    <div id="layout_container">
        <div id="header" class="ui-layout-north">
            <div id="toolbar" class="fg-toolbar ui-widget-header ui-corner-all">
                <span> <!-- reload -->
                    <button id="reload_layers">Ricarica</button>
                </span>
                <span> <!-- navigazione -->
                    <button id="zoom_full">Zoom estensione</button>
                    <button id="zoom_prev">Vista precedente</button>
                    <button id="zoom_next">Vista successiva</button>
                </span>
                <span> <!-- zooms -->
                    <input type="radio" id="pan" name="gc-toolbar-button" checked="checked" /><label for="pan">Pan</label>
                    <input type="radio" id="zoom_in" name="gc-toolbar-button" /><label for="zoom_in">Zoom in</label>
                    <input type="radio" id="zoom_out" name="gc-toolbar-button" /><label for="zoom_out">Zoom out</label>
                    <button id="geolocate">Geolocate</button>
                    <input type="text" name="scaleDropDown" id="scaleDropDown">
                </span>
                <span> <!-- misure -->
                    <input type="radio" id="measure_line" name="gc-toolbar-button" /><label for="measure_line">Misura lunghezza</label>
                    <input type="radio" id="measure_polygon" name="gc-toolbar-button" /><label for="measure_polygon">Misura area</label>
                </span>
                <span> <!-- print -->
                    <button id="print"><?php echo $lang == 'it' ? 'Stampa' : 'Drucken' ?></button>
                    <button id="mapimage_download">Download geotiff</button>
                </span>

                <span> <!-- strumenti selezione -->
                    <input type="radio" id="tooltip" name="gc-toolbar-button"/><label for="tooltip">Tooltip</label>
                    <input type="radio" id="select_by_box" name="gc-toolbar-button"/><label for="select_by_box"><?php echo $lang == 'it' ? 'Seleziona' : 'Auswählen' ?></label>
                   <!-- <input type="radio" id="easy_select_from_map" name="gc-toolbar-button" /><label for="easy_select_from_map">Info</label>-->
                    <button id="unselect_features"><?php echo $lang == 'it' ? 'unselect_features' : 'unselect_features' ?></button>
                </span>  
                <span> <!-- editing WFS -->
                    <button id="edit_feature">Edit</button>
                </span>
                    
                <span> <!-- Annotazione e Salva vista WFS -->
                    <input type="radio" id="redline" name="gc-toolbar-button" /><label for="redline">redline</label>
                    <?php if(!empty($_SESSION['USERNAME'])) { ?><button id="map_context">Map Context</button><?php } ?>
                </span>
                <span> <!-- Street View -->
                    <input type="radio" id="to_street_view" name="gc-toolbar-button" /><label for="to_street_view">To Street View</label>
                </span> 
                <span>
                    <input type="radio" id="map_help" name="gc-toolbar-button" /><label for="map_help">Help</label>
                </span> 
                    
                <span class="gc-buttonset spanSearchTextfield" style="float: right !important; *text-align:right; position:absolute; right: 150px; margin-top: 4px;">                
                 <?php echo ($lang == 'de') ? 'Suche' : 'Ricerca rapida' ?>: 
                    <input type="text" id="geolocator" name="geolocator" style="width:200px;">
                </span>
                    
                <!-- logout -->
                <div style="float:right;">
                    <a href="/"><img src="images/icons/back.png" border="0" style="position:absolute; right:35px; top: 9px;"></a>
                    <a href="/logout.php"><img src="images/logout.png" border="0" style="position:absolute; right:10px; top: 9px;"></a>
                </div>
                <!-- Tasti di cambio lingua -->
                <div style="float:right;">
                    <a href="<?php echo $linkIT ?>"><img src="images/it.png" border="0" style="position:absolute; right:60px"></a>
                    <a href="<?php echo $linkDE ?>"><img src="images/de.png" border="0" style="position:absolute; right:90px"></a>
                    <a href="<?php echo $linkEN ?>"><img src="images/uk.png" border="0" style="position:absolute; right:120px"></a>
                </div>               
            </div>
        </div>
        <!-- END HEADER -->
        <!-- START BODY -->
        <div id="wrapper">
            <!-- MAPPA -->    
            <div id="map">  
                <div id="mapOL" class="ui-layout-center" tabindex="100" style="position:absolute; top:0; left:0; right:0; bottom:0;" >
                    <div id="north_arrow" style="position:absolute;top:10px;right:10px;z-index:1500;">
                        <img src="images/n_arrow_little.png">
                    </div>
                    <div id="ll_mouse"></div><div style="margin-left:50px" id="utm_mouse"></div>
                    <div id="searchForm"></div>
                </div>
            </div>
            <!-- BARRA LATERALE -->   
            <div class="ui-layout-east" id="sidebarSx">
                <!-- LOGO -->   
                <div class="east-north" id="logo">
                 <div class="ui-widget-content" style="text-align:center; height:50px">
                    <a href="http://freegis.r3-gis.com/" target="_blank"><img src="images/logo_map.png" alt="GisClient" height="50" ></a>
                </div>
                </div>
                <!-- LIVELLI LEGENDA RICERCA DATI -->   
                <div class="east-center" style="overflow:auto !important;" id="treeDiv">
                    <ul>
                        <li><a href="#treeList"><?php if($lang == 'it') echo 'Livelli'; else echo 'Layer'; ?></a></li>
                        <li><a href="#legendList"><?php if($lang == 'it') echo 'Legenda'; else echo 'Legende'; ?></a></li>
                        <li><a href="#searchList"><?php if($lang == 'it') echo 'Ricerca'; else echo 'Suche'; ?></a></li>
                        <li id="dataListTab"><a href="#dataList"><?php if($lang == 'it') echo 'Dati'; else echo 'Daten'; ?></a></li>
                    </ul>
                    <div id="treeList" class="ui-layout-east"></div>
                    <div id="legendList">Legenda</div>
                    <div id="searchList">Search</div>
                    <div id="dataList">Data</div>         
                </div>
                <!-- REFERENCE MAP -->  
                <div id="minimap">
                  <div class="ui-widget-content east-south" id="refMapContainer" style="padding:5px">
                  </div>
                </div>
            </div>
        </div>
        <!-- END BODY -->
        <div id="footer" class="ui-layout-south">
            <div class="fg-toolbar ui-widget-header ui-corner-all ui-helper-clearfix">
                <span id="position_link"></span>&nbsp;
                <span id="misure"></span>&nbsp;
                <span id="misure_partial"></span>
                <span id="mapInfoScale"></span>&nbsp; | <span id="mapInfoRefSystemDescription"></span> = <span id="mapInfoMousePosition"></span>&nbsp; | 
                <span id="mapInfoMousePositionLatLon"></span>&nbsp;<span id="mapInfoRefSystem"></span>
                <span id="copyright" style="position:absolute; right:10px">Powered by: <a href="http://freegis.r3-gis.com/" target="_blank">Freegis Maps</a></span>
            </div>
        </div>
    </div>

    <div id="altro_da_posizionare">
        <div id="redline_dialog" style="display:none;">
        </div>
        <div id="editing_settings" style="display:none;">
        </div>
        <div id="selection_settings" style="display:none;">
        </div>
        <div id="loading_indicator" style="background-color:white;position:absolute;top:150px;left:600px;display:none;">Sto caricando...
        </div>
        <div id="errors_indicator" style="position:absolute;top:150px;left:600px;display:none;">Errore!<br /><span></span>
        </div>
        <div id="div_rototraslazione" style="display:none;"></div>
        <div id="div_tagliadbtopo" style="display:none;"></div>
        <div id="div_associacatasto" style="display:none;"></div>
        <div id="div_viewtable" style="display:none;"></div>
        <div id="div_querytofeatures" style="display:none"><textarea name="ciao" cols="40" rows="10"></textarea><button name="query">Query</button><button name="clear">Clear</button></div>
        <div id="div_linkcatastodbtopo" style="display:none;"></div>
        <div id="div_layermanager" style="display:none;"></div>
    </div>
    <script>
        var wrapper = document.getElementById('wrapper');
        var main = document.getElementById('map');
        var sidebar = document.getElementById('sidebarSx');
        
        var callback = function(){
            window.setTimeout(function(){
                gisclient.map.updateSize();
                $('.searchResults').setGridWidth($('#dataList').width() - 20);
            },400);
        }
        
        var x = new R3layout(wrapper,main,sidebar, 'right', 300);
        x.collapsible(callback);
        x.resizable(callback);
        
        var logo = document.getElementById('logo');
        var content = document.getElementById('treeDiv');
        
        var y = new R3layout(sidebar, content, logo, 'top', 52);
        y.collapsible();
        
        var minimap = document.getElementById('minimap');
        
        var z = new R3layout(sidebar, content, minimap, 'bottom',177);
        z.collapsible();
    </script>
</body>
</html>
