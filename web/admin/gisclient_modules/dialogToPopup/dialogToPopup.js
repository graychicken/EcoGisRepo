/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


(function($, undefined) {


	$.widget("gcTool.dialogToPopup", $.ui.gcTool, {

		widgetEventPrefix: "dialogToPopup",

		options: {
			label: OpenLayers.i18n('Open map in another window'), // TODO: use as default value OpenLayers.i18n...
			icons: {
				primary: 'dialogToPopup'
			},
			popupLink: 'gisclient.php?',
			text: false
		},

		internalVars: {
		},

		_create: function() {
			var self = this;
			
			$.ui.gcTool.prototype._create.apply(self, arguments);
			
		},
		
		_click: function(event) {
			var self = event.data.self;
			
			var params = {action:'zoomon'};
			if(gisclient.internalVars.lastZoomOn != null) {
				params = $.extend(params, gisclient.internalVars.lastZoomOn);
			} else {
				params.extent = gisclient.map.getExtent().toString();
			}
			var url = self.options.popupLink+OpenLayers.Util.getParameterString(params);

			DoOpenMap(url, 'GisClient');
		}
		
	});

	$.extend($.gcTool.dialogToPopup, {
		version: "3.0.0"
	});
})(jQuery);