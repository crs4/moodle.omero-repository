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
        me._view.viewer.addHandler("open", function () {
            me._annotations_canvas = new AnnotationsController('annotations_canvas');
            window.annotation_canvas = me._annotations_canvas;
            me._annotations_canvas.buildAnnotationsCanvas(me._view);
            me._view.addAnnotationsController(me._annotations_canvas, true);

            me._model.loadRoisInfo(function (data) {
                //me._roi_id_list = data;
                me._current_roi_list = data;
                if (me._show_roi_table) {
                    me.renderRoisTable(data);
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
                                    me._annotations_canvas.drawRectangle(
                                        shapes[shape].id, shapes[shape].x, shapes[shape].y, shapes[shape].width,
                                        shapes[shape].height, shape_config, false
                                    );
                                    break;
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
                    }
                }
                // Hide all shapes
                me._annotations_canvas.hideShapes();
            });
        });
    }

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
    console.log("VIEWER controller", this); // TODO: remove me!!!
};


ImageViewerController.prototype.showRoi = function (roi) {
    this._annotations_canvas.showShape(roi.id);
};


ImageViewerController.prototype.hideRoi = function (roi) {
    this._annotations_canvas.hideShape(roi.id);
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
};

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
            //{
            //    "title": "Preview",
            //    "data": "shapes[0].id",
            //    "className": "dt-head-center dt-body-center",
            //    "width": "100px",
            //    "render": function (data, type, row) {
            //        if (type === 'display') {
            //            return '<div class="shape-thumb-container" style=""><img src=" ' + me.omero_server +
            //                '/webgateway/render_shape_thumbnail/0' + data + '/?color=f00" ' +
            //                'id="' + data + '_shape_thumb" ' +
            //                'class="roi_thumb shape_thumb" ' +
            //                'style="vertical-align: top;"  ' +
            //                'color="f00" width="150px" height="150px" /></div>';
            //        }
            //        return data;
            //    }
            //},
            {
                "title": "Visibility",
                "data": "id",
                "className": "dt-head-center dt-body-center",
                "width": "20px",
                "render": function (data, type, row) {
                    if (type === 'display') {
                        return '<input id="visibility_selector_' + data + '" ' +
                            (me._visible_rois.indexOf(data.toString()) != -1 ? " checked " : "") +
                            ' type="checkbox" class="editor-active"  style="width: 20px">';
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

        //"drawCallback": function ( settings ) {
        //    var api = this.api();
        //    var rows = api.rows( {page:'current'} ).nodes();
        //    var last=null;
        //
        //    api.column(2, {page:'current'} ).data().each( function ( group, i ) {
        //        if ( last !== group ) {
        //            $(rows).eq( i ).before(
        //                '<tr class="group"><td colspan="5">'+group+'</td></tr>'
        //            );
        //
        //            last = group;
        //        }
        //    } );
        //}
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
            console.log("Deselected ROI shape: " + selected_roi_shape.id, selected_roi_shape);
        } else {
            // Selected a table row
            data_table.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
            console.log("Selected ROI shape: " + selected_roi_shape.id, selected_roi_shape);

            console.log(selected_roi_shape);

            me._addVisibleRoiShapes(selected_roi_shape.id);
            //me._handleShapeRowClick(selected_roi_shape.shapes[0]);
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
                    selected_shape_info[selected_roi_info.id] = [selected_roi_info.shapes[0]]; // FIXME: a better mechanism for shape selection
                    //checked ? me._show_rois(selected_shape_info) : me.hide_rois(selected_roi_info);

                    checked ? me.showRoi(selected_roi_info) : me.hideRoi(selected_roi_info);


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
            var height = omeroViewport.offsetHeight + roisTable.offsetHeight + 300;
            iframe.style.height = height + "px";
        }
    } else {
        alert("Not found!!!");
    }
};