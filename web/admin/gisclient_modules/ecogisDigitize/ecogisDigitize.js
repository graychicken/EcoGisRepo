/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


(function($, undefined) {


    $.widget("gcTool.ecogisDigitize", $.ui.gcTool, {

        widgetEventPrefix: "ecogisDigitize",

        options: {
            label: OpenLayers.i18n('Digitize'), // TODO: use as default value OpenLayers.i18n...
            icons: {
                primary: 'ecogisDigitize'
            },
            text: false,
            targets: [],
            url: 'edit.php',
            defaultParams: {
                method: 'selectFeature', 
                on: 'gisclient'
            },
            allowSelection: true,
            allowEditing: true,
            preload: null,
            save: function(){},
            instructions: {
                select_mode: 'Select mode',
                select: 'Select an object from map',
                draw: 'Draw an object'
            }
        },

        internalVars: {
            selectedFeatures: [],
            controls: {
                edit: null, 
                select: null
            },
            highlightLayer: null,
            editingLayer: null,
            mode: null,
            currentId: 0,
            target: null
        },

        _create: function() {
            var self = this;
			
            $.ui.gcTool.prototype._create.apply(self, arguments);
			
            var html = '<div id="gc_ecogis_select_settings"><div class="instructions">'+OpenLayers.i18n('Select mode')+'</div><br />'+
            '<div class="mode_selection"><label>'+OpenLayers.i18n('Selection label')+'</label><select name="mode">';
            if(self.options.allowSelection) html += '<option value="select">'+OpenLayers.i18n('Selection')+'</option>';
            if(self.options.allowEditing) html += '<option value="edit">'+OpenLayers.i18n('Draw')+'</option>';
            html += '</select></div>'+
            '<div class="target_selection"><label>'+OpenLayers.i18n('Layer')+'</label><select name="target">';
            $.each(self.options.targets, function(e, target) {
                html += '<option value="'+target.key+'">'+target.val+'</option>';
            });
            html += '</select></div>'+
            '<div class="features"><table><tr><th style="display:none;">'+OpenLayers.i18n('Type')+'</th><th>Id</th><th style="width:80px;">'+OpenLayers.i18n('Tools')+'</th></tr></table></div>'+
            '<div class="logs"></div>'+
            '<div class="buttons"><button name="save">'+OpenLayers.i18n('Save')+'</button><button name="abort">'+OpenLayers.i18n('Abort')+'</button></div>'+
            '</div>';

            $('body').append('<div id="gc_ecogis_select"></div>');
            $('#gc_ecogis_select').html(html);
            $('#gc_ecogis_select').dialog({
                draggable:true,
                title:OpenLayers.i18n('Digitize'),
                position: [200,40],
                width: 400,
                autoOpen: false,
                close: function(event, self) {
                    var self  = gisclient.toolObjects.ecogisDigitize;
                    self._abort();
                }
            });
            self._showButtons([]);
            $('#gc_ecogis_select button[name="abort"]').click(function() {
                self._abort();
            });
            $('#gc_ecogis_select button[name="save"]').click(function() {
                self._save();
            });
            
            if($('#gc_ecogis_select select[name="mode"] option').length < 1) {
                self.internalVars.mode = $('#gc_ecogis_select select[name="mode"] option').val();
                $('#gc_ecogis_select div.mode_selection').hide();
                self._switchMode();
            } else {
                $('#gc_ecogis_select select[name="mode"]').change(function() {
                    self.internalVars.mode = $(this).val();
                    self._switchMode();
                });
            }
            if($('#gc_ecogis_select select[name="target"] option').length < 2) {
                $('#gc_ecogis_select div.target_selection').hide();
            } else {
                $('#gc_ecogis_select select[name="target"]').change(function() {
                    self.internalVars.target = $(this).val();
                });
            }
            
            $('#gc_ecogis_select div.features').hide();
			
            //if(self.options.allowSelection) {
                self.internalVars.controls.select = new OpenLayers.Control({
                    autoActivate:false
                });
                OpenLayers.Util.extend(self.internalVars.controls.select, {
                    draw: function () {
                        this.point = new OpenLayers.Handler.Point(self.internalVars.controls.select,
                        {
                            'done': self._handleSelection
                        }
                        );
                    },
                    CLASS_NAME: 'OpenLayers.Control.selectEcogisFeatures'
                });
                self.internalVars.controls.select.self = self;
                self.internalVars.controls.select.events.register('activate',self,function() {
                    self.internalVars.controls.select.point.activate();
                });
                self.internalVars.controls.select.events.register('deactivate',self,function() {
                    self.internalVars.controls.select.point.deactivate();
                });
                gisclient.map.addControl(self.internalVars.controls.select);
                self.options.control = self.internalVars.controls.select;
            //}
            //if(self.options.allowEditing) {
                self.internalVars.editingLayer = gisclient.componentObjects.gcLayersManager.getEditingLayer();
                self.internalVars.controls.edit = new OpenLayers.Control.DrawFeature(self.internalVars.editingLayer, OpenLayers.Handler.Polygon);
                self.internalVars.controls.edit.events.register('featureadded', self, function(event) {
                    self._addDrawnFeature(event.feature);
                });
                gisclient.map.addControl(self.internalVars.controls.edit);
                self.options.control = self.internalVars.controls.edit;
            //}
            
            self.internalVars.highlightLayer = gisclient.componentObjects.gcLayersManager.getHighlightLayer();
        },
		
        _click: function(event) {
            var self = event.data.self;
            $('#gc_ecogis_select').dialog('open');
            
            var buttons = [];
            if(self.internalVars.selectedFeatures.length > 0) buttons.push('save', 'abort');
            self._showButtons(buttons);
			
            $.ui.gcTool.prototype._click.apply(self, arguments);
            
            self.internalVars.mode = $('#gc_ecogis_select select[name="mode"]').val();
            self._switchMode();
            
            self.internalVars.target = $('#gc_ecogis_select select[name="target"]').val();
            
            if (self.options.preload != null) {
                $.ajax({
                    url: self.options.url,
                    type: 'GET',
                    dataType: 'json',
                    data: self.options.preload,
                    success: function(response) {
                        if(response == null || typeof(response) != 'object' || typeof(response.status) == 'undefined' || response.status != 'OK' || typeof(response.data) != 'object') {
                            return $('#gc_ecogis_select div.logs').html(OpenLayers.i18n('System error'));
                        }
                        // Load from temporary table
                        self._parseFeatures(response);
                        // Zoom to data
                        var selectionLayer = gisclient.componentObjects.gcLayersManager.getSelectionLayer();
                        if(selectionLayer.features.length == 0) return;
                        var bounds = selectionLayer.getDataExtent();
                        var extendedBounds = new OpenLayers.Bounds(
                            bounds.left-50,
                            bounds.bottom-50,
                            bounds.right+50,
                            bounds.top+50
                            );
                        gisclient.map.zoomToExtent(extendedBounds);
                    },
                    error: function() {
                        return $('#gc_ecogis_select div.logs').html(OpenLayers.i18n('System error'));
                    }
                });
            }
        },
            
		
        _deactivate: function() {
            var self = this;
			
            self._abort();
            $('#gc_ecogis_select').dialog('close');
            
            $.each(self.internalVars.controls, function(e, control) {
                control.deactivate();
            });
        },
		
        _handleErrorResponse: function(response) {
            console.log(response);
            var errorText = OpenLayers.i18n('System error');
            if(response != null && typeof(response) == 'object') {
                if (response.exception) {
                    errorText = response.exception;
                }
                if (response.error && response.error.text) {
                    errorText = response.error.text;
                }
            }
            return $('#gc_ecogis_select div.logs').html(errorText);
        },
        _handleSelection: function(geom) {
            var self = this.self;
			
            $('#gc_ecogis_select div.logs').empty();
            
            var point = new OpenLayers.Geometry.Point(geom.x, geom.y);
            
            var params = $.extend({}, self.options.defaultParams, {
                point: point.toString(),
                target: self.internalVars.target
            });
            
            $.ajax({
                url: self.options.url,
                type: 'GET',
                dataType: 'json',
                data: params,
                success: function(response) {
                    if(response == null || typeof(response) != 'object' || typeof(response.status) == 'undefined' || response.status != 'OK' || typeof(response.data) != 'object') {
                        return self._handleErrorResponse(response); 
                    }
                    if(response.data.length < 1) {
                        return alert(OpenLayers.i18n('Nessun oggetto trovato'));
                    }
                    self._parseFeatures(response);
                },
                error: function() {
                    return $('#gc_ecogis_select div.logs').html(OpenLayers.i18n('System error'));
                }
            });
        },
        
        _parseFeatures: function(response) {
            var self = this;
            
            var features = [];
            $.each(response.data, function(e, feature) {
                var alreadyAdded = false;
                $.each(self.internalVars.selectedFeatures, function(e, selFeature) {
                    if(typeof(selFeature) == 'undefined') return;
                    if(typeof(selFeature.attributes.refId) == 'undefined') return;
                    if(feature.type == selFeature.attributes.type && 
                        feature.id == selFeature.attributes.refId) alreadyAdded = true;
                });
                if(alreadyAdded) return;
                        
                var geometry = OpenLayers.Geometry.fromWKT(feature.geom);
                self.internalVars.currentId += 1;
                var feature = new OpenLayers.Feature.Vector(geometry, {
                    type:feature.type, 
                    id: self.internalVars.currentId, 
                    refId: feature.id
                });
                self.internalVars.selectedFeatures.push(feature);
                features.push(feature);
                self._addRow(self.internalVars.currentId, feature.attributes);
            });
                    
            var selectionLayer = gisclient.componentObjects.gcLayersManager.getSelectionLayer();
            selectionLayer.addFeatures(features);
                    
            self._showButtons(['save','abort']);
            $('#gc_ecogis_select div.features').show();
        },
		
        _removeFeature: function(id) {
            var self = this;
            var selectionLayer = gisclient.componentObjects.gcLayersManager.getSelectionLayer();
			
            $.each(self.internalVars.selectedFeatures, function(e, feature) {
                if(typeof(feature) == 'undefined') return;
                if(typeof(feature.attributes.id) != 'undefined' && feature.attributes.id == id) {
                    selectionLayer.removeFeatures([feature]);
                    $('#gc_ecogis_select div.features > table tr[data-role="row_'+id+'"]').remove();
                    delete self.internalVars.selectedFeatures[e];
                }
            });
            
            var buttons = ['abort'];
            if(self._countSelectedFeatures() > 0) buttons.push('save');
            self._showButtons(buttons);
            
            self.internalVars.highlightLayer.removeAllFeatures();
        },
        
        _countSelectedFeatures: function() {
            var self = this;
            
            var count = 0;
            $.each(self.internalVars.selectedFeatures, function(e, feature) {
                if(typeof(feature) != 'undefined') count += 1;
            });
            return count;
        },
		
        _highlightFeature: function(id) {
            var self = this;
			
            self.internalVars.highlightLayer.removeAllFeatures();
            $.each(self.internalVars.selectedFeatures, function(e, feature) {
                if(typeof(feature) == 'undefined') return;
                if(feature.attributes.id == id) {
                    var geometry = feature.geometry.clone();
                    var highlightFeature = new OpenLayers.Feature.Vector(geometry);
                    self.internalVars.highlightLayer.addFeatures([highlightFeature]);
                }
            });
        },
        
        _save: function() {
            var self = this;
            
            var uiHash = self._getUIHash();
            
            uiHash.geometries = [];
            $.each(self.internalVars.selectedFeatures, function(e, feature) {
                if(typeof(feature) == 'undefined' || typeof(feature.geometry) == 'undefined') return;
                uiHash.geometries.push(feature.geometry.toString());
            });
            
            self._trigger( "save", null, uiHash);
            
        },

        _abort: function() {
            var self = this;
			
            var selectionLayer = gisclient.componentObjects.gcLayersManager.getSelectionLayer();
            $.each(selectionLayer.features, function(e, feature) {
                $('#gc_ecogis_select div.features > table tr[data-role="row_'+feature.attributes.id+'"]').remove();
            });
            $('#gc_ecogis_select div.features').hide();
            selectionLayer.removeAllFeatures();
            self.internalVars.selectedFeatures = [];
            if(self.internalVars.highlightLayer != null) self.internalVars.highlightLayer.removeAllFeatures();
            self._showButtons([]);
            $('#gc_ecogis_select div.logs').empty();
        },
        
        _addDrawnFeature: function(feature) {
            var self = this;
            
            self.internalVars.currentId += 1;
            feature.attributes.id = self.internalVars.currentId;
            feature.attributes.type = OpenLayers.i18n('Disegno');
            
            self.internalVars.editingLayer.removeFeatures([feature]);
            var selectionLayer = gisclient.componentObjects.gcLayersManager.getSelectionLayer();
            selectionLayer.addFeatures([feature]);
            self.internalVars.selectedFeatures.push(feature);
            
            self._addRow(self.internalVars.currentId, feature.attributes);
            self._showButtons(['save','abort']);
            $('#gc_ecogis_select div.features').show();
        },
        
        _addRow: function(id, attributes) {
            var self = this;
            
            if(typeof(attributes.id) == 'undefined') attributes.id = '';
            
            var html = '<tr data-role="row_'+id+'"><td style="display:none;">'+attributes.type+'</td><td>'+attributes.id+'</td><td><a href="#" data-role="highlight" rel="'+id+'" class="highlight" title="'+OpenLayers.i18n('Highlight')+'"><span> </span></a><a href="#" data-role="elimina_feature" rel="'+id+'" class="del" title="'+OpenLayers.i18n('Delete')+'"><span> </span></a></td></tr>';
            //var html = '<tr data-role="row_'+id+'"><td style="display:none;">'+attributes.type+'</td><td>'+attributes.id+'</td><td><a href="#" data-role="highlight" rel="'+id+'"><img src="'+OpenLayers.ImgPath+'highlight.png" border="0"></a> <a href="#" data-role="elimina_feature" rel="'+id+'" class="del"><span>'+OpenLayers.i18n('Delete')+'</span></a></td></tr>';
            $('#gc_ecogis_select div.features > table').append(html);
        
            $('#gc_ecogis_select div.features > table a[data-role="elimina_feature"]').unbind('click');
            $('#gc_ecogis_select div.features > table a[data-role="highlight"]').unbind('click');
        
            $('#gc_ecogis_select div.features > table a[data-role="elimina_feature"]').click(function(event) {
                event.preventDefault();
                self._removeFeature($(this).attr('rel'));
            });
            $('#gc_ecogis_select div.features > table a[data-role="highlight"]').click(function(event) {
                event.preventDefault();
                self._highlightFeature($(this).attr('rel'));
            });
        },
        
        _switchMode: function() {
            var self = this;
            
            $.each(self.internalVars.controls, function(e, control) {
                control.deactivate();
            });
            
            switch(self.internalVars.mode) {
                case 'select':
                    self.internalVars.controls.select.activate();
                    self._showInstructions('select');
                    $('#gc_ecogis_select div.target_selection').show();
                    break;
                case 'edit':
                    self.internalVars.controls.edit.activate();
                    self._showInstructions('draw');
                    $('#gc_ecogis_select div.target_selection').hide();
                    break;
                default:
                    alert('Undefined mode '+self.internalVars.mode);
                    break;
            }
        },
        
        _showButtons: function(array) {
            var self = this;
            $('#gc_ecogis_select div.buttons button').hide();
			
            $.each(array, function(e, buttonName) {
                $('#gc_ecogis_select div.buttons button[name="'+buttonName+'"]').show();
            });
        },
        
        _showInstructions: function(key) {
            var self = this;
            
            $('#gc_ecogis_select div.instructions').html(OpenLayers.i18n(self.options.instructions[key]));
        }
		
    });

    $.extend($.gcTool.ecogisDigitize, {
        version: "3.0.0"
    });
})(jQuery);