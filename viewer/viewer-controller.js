/**
 * Create a new instance of ImageViewerController
 *
 * @param image_server the actual image server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 */
function ImageViewerController(image_server,
                               frame_id, view_container_id, rois_table_id, roi_shape_thumb_popup_id,
                               image_id, show_roi_table, image_params, visible_rois) {

    // Reference to the current scope
    var me = this;

    // register the actual initialization parameters
    me._image_server = image_server;
    me._frame_id = frame_id;
    me._viewer_container_id = view_container_id;
    me._rois_table_id = rois_table_id;
    me._image_id = image_id;
    me._image_params = image_params;
    me._visible_rois = visible_rois; // && visible_rois.length > 0 ? visible_rois.split(",") : [];
    me._visible_roi_shape_list = [];
    me._roi_shape_thumb_popup_id = roi_shape_thumb_popup_id;
    me._show_roi_table = show_roi_table;

    // set frame reference
    me._frame = window.parent.document.getElementById(me._frame_id);

    // TODO: to change with the controller initialization
    me._view = new ViewerController(
        me._viewer_container_id,
        me._image_server + "/static/ome_seadragon/img/openseadragon/",
        me._image_server + "/ome_seadragon/deepzoom/get/" + me._image_id + ".dzi"
    );

    // Check viewer initialization
    if (!me._view) {
        console.error("Image viewer not initialized!!!");
        return
    } else {
        // Binds the current viewer to the 'window' object
        window.viewer = me._view;
    }

    // initializes the ImageModelManager
    me._model = new ImageModelManager(image_server, image_id);

    // FIXME: just for debug
    window.addEventListener("image_server.roisInfoLoaded", function (data) {
        console.log(data);
    });

    // TODO: add param to change the default behaviour
    if (me._view) {
        me._view.buildViewer();
        me._view.viewer.addHandler("open", function(){
            me._annotations_canvas = new AnnotationsController('annotations_canvas');
            window.annotation_canvas = me._annotations_canvas;
            me._annotations_canvas.buildAnnotationsCanvas(me._view);
            me._view.addAnnotationsController(me._annotations_canvas, true);
        });

        me._model.loadRoisInfo(function (data) {
            //me._roi_id_list = data;
            me._current_roi_list = data;
            if (me._show_roi_table) {
                me.renderRoisTable(data);
            }
        });
    }

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
    console.log("VIEWER controller", this); // TODO: remove me!!!
};


ImageViewerController.prototype.showRoi = function (roi) {
    var me = this;
    var shapes = roi.shapes;
    for (var shape in shapes) {
        var shape_type = shapes[shape].type;
        var shape_config = {
            'fill_color': shapes[shape].fillColor,
            'fill_alpha': shapes[shape].fillAlpha,
            'stroke_color': shapes[shape].strokeColor,
            'stroke_alpha': shapes[shape].strokeAlpha,
            'stroke_width': shapes[shape].strokeWidth
        };

        switch (shape_type) {
            case "Rectangle":
                me._annotations_canvas.drawRectangle(
                    shapes[shape].id, shapes[shape].x, shapes[shape].y, shapes[shape].width,
                    shapes[shape].height, shape_config, false
                );
                break
            case "Ellipse":
                me._annotations_canvas.drawEllipse(
                    shapes[shape].id, shapes[shape].cx, shapes[shape].cy,
                    shapes[shape].rx, shapes[shape].ry, shape_config,
                    false
                );
                break;
            case "Line":
                me._annotations_canvas.drawLine(
                    shapes[shape].id, shapes[shape].x1, shapes[shape].y1,
                    shapes[shape].x2, shapes[shape].y2, shape_config,
                    false
                );
                break;
            default:
                console.warn('Unable to handle shape type ' + shape_type);
        }
    }
    me._annotations_canvas.refreshView();
};


ImageViewerController.prototype.hideRoi = function (roi) {

};


ImageViewerController.prototype._addVisibleRoiShapes = function (roi_ids) {
    if (!roi_ids.split) roi_ids = "" + [roi_ids];
    if (roi_ids != undefined && roi_ids.length > 0) {
        var roi_id_list = roi_ids.split(",");
        for (var i in roi_id_list) {
            var roi_id = roi_id_list[i];
            for (var j in this._current_roi_list) {
                var e = this._current_roi_list[j];
                if (e.id == roi_id) {
                    // FIXME: a better mechanism for selecting a shape
                    this._visible_roi_shape_list[e.id] = [e.shapes[0]];
                    break;
                }
            }
        }
    }
}

ImageViewerController.prototype._removeVisibleRoiShapes = function (roi_ids) {
    if (!roi_ids.split)
        delete this._visible_roi_shape_list[roi_ids];
    else if (roi_ids != undefined && roi_ids.length > 0) {
        var roi_id_list = roi_ids.split(",");
        for (var i in roi_id_list) {
            var roi_id = roi_id_list[i];
            console.log("ARRAY: ", this._visible_roi_shape_list);
            var index = this._visible_roi_shape_list.indexOf(roi_id);
            delete this._visible_roi_shape_list[roi_id];
            console.log("Removed visible roi element: ", this._visible_roi_shape_list);
        }
    }
}
/**
 * Resize the viewer
 *
 * @private
 */
ImageViewerController.prototype._resize = function () {
    var me = this;
    var iframe = parent.parent.document.getElementById(me._frame_id);

    if (iframe) {

        var omeroViewport = iframe.contentDocument.getElementById(me._viewer_container_id);
        var roisTable = iframe.contentDocument.getElementById(me._rois_table_id);

        console.log("iframe", iframe);
        console.log("viewport", omeroViewport);
        console.log("table", roisTable);
        if (roisTable) {
            var height = omeroViewport.offsetHeight + roisTable.offsetHeight + 300;
            iframe.style.height = height + "px";
        }
    } else {
        alert("Not found!!!");
    }
};