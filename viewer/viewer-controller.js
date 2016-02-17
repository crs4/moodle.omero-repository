// Copyright (c) 2015-2016, CRS4
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
// COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
// IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

/**
 * Create a new instance of ImageViewerController
 *
 * @param image_server the actual image server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 *
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */
function ImageViewerController(image_server, viewer_model_server,
                               frame_id, view_container_id, rois_table_id, roi_shape_thumb_popup_id,
                               image_id, show_roi_table, image_params, visible_rois) {

    // Reference to the current scope
    var me = this;

    // register the actual initialization parameters
    me._image_server = image_server;
    me._viewer_model_server = viewer_model_server,
    me._frame_id = frame_id;
    me._viewer_container_id = view_container_id;
    me._rois_table_id = rois_table_id;
    me._image_id = image_id;
    me._image_params = image_params;
    me._visible_rois = visible_rois; // && visible_rois.length > 0 ? visible_rois.split(",") : [];
    me._visible_roi_shape_list = [];
    me._roi_shape_thumb_popup_id = roi_shape_thumb_popup_id;
    me._show_roi_table = show_roi_table;

    me._event_listeners = [];

    // set frame reference
    me._frame = window.parent.document.getElementById(me._frame_id);

    var please_wait_ID = me._viewer_container_id + "-loading-dialog";
    me._loading_dialog = $(me._frame.contentDocument.getElementById(please_wait_ID));

    // get url params
    var image_params = parseImageParams();
    me._image_params = image_params;

    // initializes the ImageModelManager
    me._model = new ImageModelManager(this._viewer_model_server, image_id);

    var viewer_config = {
        'showNavigator': true,
        'showFullPageControl': false,
        'animationTime': 0.01
    };

    // TODO: to change with the controller initialization
    me._viewer_controller = new ViewerController(
        me._viewer_container_id,
        me._image_server + "/static/ome_seadragon/img/openseadragon/",
        me._image_server + "/ome_seadragon/deepzoom/get/" + me._image_id + ".dzi",
        viewer_config
    );


    // Check viewer initialization
    if (!me._viewer_controller) {
        console.error("Image viewer not initialized!!!");
        return
    } else {
        // Binds the current viewer to the 'window' object
        window.viewer = me._viewer_controller;
    }


    // FIXME: just for debug
    window.addEventListener("image_server.roisInfoLoaded", function (data) {
        console.log(data);
    });

    // builds and initializes the Viewer
    if (me._viewer_controller) {
        me._viewer_controller.buildViewer();

        //
        me._viewer_controller.viewer.addHandler("open", function () {

            // Ignore lowest-resolution levels in order to improve load times
            me._viewer_controller.setMinDZILevel(8);

            // Adds the annotation controller
            me._annotations_controller = new AnnotationsController('annotations_canvas');
            window.annotation_canvas = me._annotations_controller;
            me._annotations_controller.buildAnnotationsCanvas(me._viewer_controller);
            me._viewer_controller.addAnnotationsController(me._annotations_controller, true);

            //
            me._model.loadRoisInfo(function (data) {

                //me._roi_id_list = data;
                me._current_roi_list = data;
                if (me._show_roi_table) {
                    me.renderRoisTable(data);
                }


                // Initialize the list of ROIs
                for (var roi in data) {
                    var shapes = data[roi].shapes;
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
                                me._annotations_controller.drawRectangle(
                                    shapes[shape].id, shapes[shape].x, shapes[shape].y, shapes[shape].width,
                                    shapes[shape].height,
                                    TransformMatrixHelper.fromOMETransform(shapes[shape].transform),
                                    shape_config, false
                                );
                                break;
                            case "Ellipse":
                                me._annotations_controller.drawEllipse(
                                    shapes[shape].id, shapes[shape].cx, shapes[shape].cy,
                                    shapes[shape].rx, shapes[shape].ry,
                                    TransformMatrixHelper.fromOMETransform(shapes[shape].transform),
                                    shape_config,
                                    false
                                );
                                break;
                            case "Line":
                                me._annotations_controller.drawLine(
                                    shapes[shape].id, shapes[shape].x1, shapes[shape].y1,
                                    shapes[shape].x2, shapes[shape].y2,
                                    TransformMatrixHelper.fromOMETransform(shapes[shape].transform),
                                    shape_config,
                                    false
                                );
                                break;
                            default:
                                console.warn('Unable to handle shape type ' + shape_type);
                        }
                    }
                }

                // Hide all shapes
                me._annotations_controller.hideShapes(undefined, false);

                // initialize the list of visible ROIs
                if (image_params.visibleRois !== undefined && image_params.visibleRois.length > 0) {
                    var roi_shape_list = image_params.visibleRois.split(",");
                    if (roi_shape_list && roi_shape_list.length > 0) {
                        me._visible_roi_shape_list = roi_shape_list;
                        me._annotations_controller.showShapes(me._visible_roi_shape_list);
                    }
                }

                // Restore the previous status of the view (i.e., zoom and center(x,y))
                if (image_params.x && image_params.y) {
                    var image_center = me._viewer_controller.getViewportCoordinates(image_params.x, image_params.y);
                    if (image_params.zm) {
                        me._viewer_controller.jumpTo(image_params.zm, image_center.x, image_center.y);
                        console.log("Setting zoom level: " + image_params.zm);
                    } else {
                        me._viewer_controller.jumpToPoint(image_center.x, image_center.y);
                    }

                    console.log("Jumping to " + image_center.x + " -- " + image_center.y);
                }

                // Scalebar initialization
                me._model.getImageDZI(function(data){
                    // Scalebar setup
                    var image_mpp = data.image_mpp ? data.image_mpp : 0;
                    var scalebar_config = {
                        "xOffset": 10,
                        "yOffset": 10,
                        "barThickness": 5,
                        "color": "#777777",
                        "fontColor": "#000000",
                        "backgroundColor": 'rgba(255, 255, 255, 0.5)'
                    };
                    me._viewer_controller.enableScalebar(image_mpp, scalebar_config);
                });

                // notifies listeners
                for (var i in me._event_listeners) {
                    var callback = me._event_listeners[i];
                    if (callback) {
                        callback(me);
                    }
                }
            });
        });
    }
    //});

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
    console.log("VIEWER controller", this); // TODO: remove me!!!
};


ImageViewerController.prototype.onViewerInitialized = function (listener) {
    this._event_listeners.push(listener);
};


/**
 * Returns the modelManager related to this controller
 *
 * @returns {ImageModelManager|*}
 */
ImageViewerController.prototype.getModel = function () {
    return this._model;
};


/**
 * Returns a relative URL containing all relevant info to display
 * the image currently managed by this ViewerController:
 *
 *  i.e., <IMAGE_ID>?t=<T level>&z=<Z level>&zm=<ZOOM level>
 *                              &x=<X center>
 *                              &y=<Y center>
 *
 * @returns {*}
 */
ImageViewerController.prototype.buildDetailedImageRelativeUrl = function () {
    var result = null;
    var viewport_details = this._viewer_controller.getViewportDetails();
    if (viewport_details) {
        return "/omero-image-repository/" + this._image_id
            + "?"
            + "id=" + this._image_id + "&"
            + "t=" + 1 + "&" // TODO: to update with the actual T value (we not support only T=1)
            + "z=" + 1 + "&" // TODO: to update with the actual Z value (we not support only Z=1)
            + "zm=" + viewport_details.zoom_level + "&"
            + "x=" + viewport_details.center_x + "&"
            + "y=" + viewport_details.center_y
    }
    return result;
};


ImageViewerController.prototype.updateViewFromProperties = function (image_properties) {
    var me = this;
    if(!image_properties || !image_properties.center){
        console.warn("incomplete image properties");
        return false;
    }
    
    var image_center = me._viewer_controller.getViewportCoordinates(
        image_properties.center.x, image_properties.center.y
    );
    if (image_properties.zoom_level) {
        me._viewer_controller.jumpTo(image_properties.zoom_level, image_center.x, image_center.y);
        console.log("Setting zoom level: " + image_properties.zoom_level);
    } else {
        me._viewer_controller.jumpToPoint(image_center.x, image_center.y);
    }

    console.log("Jumping to " + image_center.x + " -- " + image_center.y);
};

ImageViewerController.prototype.getImageProperties = function () {
    var p = this._viewer_controller.getViewportDetails();
    return {
        "id": this._image_id,
        "center": {
            "x": p.center_x,
            "y": p.center_y,
        },
        "t": 1,
        "z": 1,
        "zoom_level": p.zoom_level
    };
};

/**
 * Returns the list of ROI shapes related to the current image
 * @returns {*}
 */
ImageViewerController.prototype.getRoiList = function () {
    return this._current_roi_list;
};


/**
 * Display the list of ROI shapes identified by their ID
 *
 * @param shape_id_list
 */
ImageViewerController.prototype.showRoiShapes = function (shape_id_list) {
    this._annotations_controller.showShapes(shape_id_list, true);
};

/**
 * Hide the list of ROIs identified by their ID
 * @param shape_id_list
 */
ImageViewerController.prototype.hideRoiShapes = function (shape_id_list) {
    this._annotations_controller.hideShapes(shape_id_list, true);
};

/**
 * Set focus on a given ROI shape
 * @param shape_id
 */
ImageViewerController.prototype.setFocusOnRoiShape = function (shape_id) {
    var shape_position = this._annotations_controller.getShapeCenter(shape_id);
    shape_position = this._viewer_controller.getViewportCoordinates(shape_position.x, shape_position.y);
    this._viewer_controller.jumpToPoint(shape_position.x, shape_position.y);
    this._annotations_controller.selectShape(shape_id, true, true);
};


/**
 * Add a list of ROIs to the list of ROI to show
 *
 * @param roi_ids
 * @private
 */
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
    console.log("Visible ROI list", this._visible_roi_shape_list);
};


/**
 * Removes a list of ROIs to the list of ROI to show
 *
 * @param roi_ids
 * @private
 */
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
    console.log("Visible ROI list", this._visible_roi_shape_list);
};


/**
 * Render the ROI table
 *
 * @param dataSet
 * @private
 */
ImageViewerController.prototype.renderRoisTable = function (dataSet) {

    var me = this;

    console.log("Rendering table started .... ");
    return false;
    var roi_table = $('#rois-table');
    roi_table.dataTable({
        "data": dataSet,
        "cell-border": true,
        "pageLength": 10,
        "lengthMenu": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        "columns": [
            {"title": "ID", data: "id", "width": "20px", "className": "dt-head-center dt-body-center"},
            {"title": "Z", data: "shapes[0].theZ", "width": "20px", "className": "dt-head-center dt-body-center"},
            {"title": "T", data: "shapes[0].theT", "width": "20px", "className": "dt-head-center dt-body-center"},
            {
                "title": "Description",
                "data": "shapes[0].textValue",
                "className": "roi-description dt-head-center dt-body-left"
            },
            {
                "title": "Visibility",
                "data": "id",
                "className": "dt-head-center dt-body-center roi-visibility-selector",
                "render": function (data, type, row) {
                    if (type === 'display') {
                        return '<input id="visibility_selector_' + data + '" ' +
                            (me._visible_rois.indexOf(data.toString()) != -1 ? " checked " : "") +
                            ' type="checkbox" class="editor-active" style="text-align: center;">';
                    }
                    return data;
                }
            }
        ],

        rowCallback: function (row, data) {
            // Set the checked state of the checkbox in the table
            console.log("DATA", row, data, data[4], data[5]);
            $('input.editor-active', row).prop('checked', data.visibility);
        }
    });


    // Handle row selection, i.e., selection of the corresponding ROI shape
    $('#rois-table tbody').on('click', 'tr', function (event) {
        var data_table = roi_table.DataTable();
        var selected_roi_shape = data_table.row(this).data();
        var selected = true;
        var target = (event.target || event.srcElement);
        if ($(this).hasClass('selected') && target.type != "checkbox") {
            // Deselects an already selected row:
            // skips the deselection if the click has been triggered by a checkbox
            selected = false;
            $(this).removeClass('selected');
            me._annotations_controller.deselectShape(selected_roi_shape.id, true);
            console.log("Deselected ROI shape: " + selected_roi_shape.id, selected_roi_shape);
        } else {
            // Selected a table row
            data_table.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
            console.log("Selected ROI shape: " + selected_roi_shape.id, selected_roi_shape);

            console.log(selected_roi_shape);

            me._addVisibleRoiShapes(selected_roi_shape.id);
            me.setFocusOnRoiShape(selected_roi_shape.id);
        }

        window.dispatchEvent(new CustomEvent(
            "roiShape" + (selected ? "Selected" : "Deselected"),
            {
                detail: {
                    id: selected_roi_shape.id + "@" + selected_roi_shape.shapes[0].id,
                    roiId: selected_roi_shape.id,
                    shapeId: selected_roi_shape.shapes[0].id,
                    detail: selected_roi_shape
                },
                bubbles: true
            })
        );
    });


    // Handle the selection of a given row (image)
    roi_table.on('change', function (event) {
        //var me = this;
        var target = (event.target || event.srcElement);
        var selectorId = target.id;
        if (selectorId) {
            var roiId = selectorId.match(/[0-9]+/);
            if (roiId) {

                var checked = $("#" + selectorId).is(":checked");
                console.log("Changed visibility of " + roiId + ": " + (checked ? "display" : "hidden"));

                var selected_roi_info = $.grep(me._current_roi_list, function (e) {
                    return e.id == roiId;
                });
                if (selected_roi_info.length > 0) {
                    // update var to point to the selected ROI
                    selected_roi_info = selected_roi_info[0];
                    // prepare ROI shape info
                    var selected_shape_info = {};
                    // FIXME: a better mechanism for shape selection
                    selected_shape_info[selected_roi_info.id] = [selected_roi_info.shapes[0]];
                    checked ?
                        me._annotations_controller.showShape(selected_roi_info.id) :
                        me._annotations_controller.hideShape(selected_roi_info.id);

                    // Notifies the event
                    window.dispatchEvent(new CustomEvent(
                        "roiVisibilityChanged",
                        {
                            detail: {
                                id: selected_roi_info.id + "@" + selected_roi_info.shapes[0].id,
                                roiId: selected_roi_info.id,
                                shapeId: selected_roi_info.shapes[0].id,
                                detail: selected_roi_info,
                                visible: checked
                            },
                            bubbles: true
                        })
                    );
                }
                // FIXME: it is just an example of event notification
                //window.postMessage({
                //    roiId: roiId,
                //    event: "roi_visibility_changed",
                //    visibility: (checked ? "display" : "hidden")
                //}, "*");
            }
        }
    });

    // now bind mouseover: enable/disable shape thumbnail popup
    var roi_thumb_popup = $("#" + me.roi_shape_thumb_popup_id);
    roi_thumb_popup.updateShapeThumbnails = function () {
        $('.roi_thumb').hover(function (e) {
            roi_thumb_popup.attr('src', $(this).attr('src')).show();
        }, function (e) {
            roi_thumb_popup.hide();
        });

        $('.roi_thumb').mousemove(function (e) {
            roi_thumb_popup.css({'left': e.pageX + 5, 'top': e.pageY + 5})
        });
    };

    // Resize after pagination FIXME: is really needed?
    roi_table.on('page.dt', function () {
        var info = $('#rois-table').DataTable().page.info();
        $('#pageInfo').html('Showing page: ' + info.page + ' of ' + info.pages);
        me._resize();
        roi_thumb_popup.updateShapeThumbnails();
    });

    // Resize after every draw
    roi_table.on('draw.dt', function () {
        console.log('Redraw occurred at: ' + new Date().getTime());
        me._resize();
        roi_thumb_popup.updateShapeThumbnails();
    });

    // call resize
    me._resize();
    // first roi_thumb_popup initialization
    roi_thumb_popup.updateShapeThumbnails();
    console.log("Rendering table stopped .... ");
};


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
            var height = omeroViewport.offsetHeight + roisTable.offsetHeight + 450;
            iframe.style.height = height + "px";
        }
    } else {
        alert("Not found!!!");
    }
};


function parseImageParams() {
    var x = _parseImageParams(window.location.search);
    console.log(x);
    return x;
}

function _parseImageParams(string_params) {
    var float_params = ["x", "y", "zm"];
    console.log(string_params.substr(1).split('&'));
    return (function (a) {
        if (a == "") return {};
        var b = {};
        for (var i = 0; i < a.length; ++i) {
            var p = a[i].split('=', 2);
            var name = p[0].trim();
            console.log("PARAM: " + i + " -- " + name);
            if (p.length == 1)
                if (float_params.indexOf(name) !== -1)
                    b[name] = 0.0;
                else
                    b[name] = "";
            else {
                var value = p[1];
                console.log("value of " + name + " is: " + value, float_params.indexOf(name) !== -1);
                if (float_params.indexOf(name) !== -1)
                    b[name] = value !== undefined ? parseFloat(value) : 0.0;
                else b[name] = value !== undefined ? decodeURIComponent(value.replace(/\+/g, " ")) : "";
            }
        }
        return b;
    })(string_params.substr(1).split('&'));
}
