/**
 * The instance of the controller for the Omero Viewer
 *
 * @type {{omero_viewer_controller}}
 */
omero_viewer_controller = {};

// internal shortcut for the controller instance
var ctrl = omero_viewer_controller;

/**
 * Initialize the controller of the actual omero viewer
 *
 * @param omero_server the actual omero server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 */
ctrl.init = function (omero_server, frame_id, viewport_id, rois_table_id, image_id) {

    var me = omero_viewer_controller;

    // register the actual initialization parameters
    me.omero_server = omero_server;
    me.frame_id = frame_id;
    me.viewport_id = viewport_id;
    me.rois_table_id = rois_table_id;
    me.image_id = image_id;

    // creates the viewport
    $(document).ready(function () {

        /* Prepare the viewport */
        me.viewport = $.WeblitzViewport($("#viewport"), omero_server + "/webgateway/", {
            'mediaroot': omero_server + "/static/"
        });

        /* Async call needs loading */
        me.viewport.bind('imageLoad', me._imageLoad);
        /* Bind zoomimg action to the ROIs */
        me.viewport.bind('instant_zoom', me.instant_zoom);
        /* load and render the image if provided */
        if (image_id != undefined)
            me.load_and_render_image(image_id, true);

        // notify viewport creation
        window.postMessage({type: "omero_viewport_created"}, "*");
    });

    console.log("Initialization Ok!!!!");
};


ctrl.load_viewport = function () {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    if (!viewport.viewportimg.get(0).refresh_rois) {
        console.log("Loading viewport....");
        var options = {
            'width': viewport.loadedImg.size.width,
            'height': viewport.loadedImg.size.height,
            'json_url': me.omero_server + '/webgateway/get_rois_json/' + viewport.loadedImg.id
        };
        if (me.viewport.loadedImg.tiles) {
            options['tiles'] = true;
        }

        me.viewport.viewportimg.roi_display(options);
        me.viewport.viewportimg.get(0).setRoiZoom(viewport.viewportimg.get(0).getZoom());
    }
    else
        console.log("Viewport already loaded");
};

ctrl.show_rois = function (roi_list) {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    var theT = viewport.getTPos();
    var theZ = viewport.getZPos();

    if (!viewport.viewportimg.get(0).show_rois) {
        me.load_viewport();
    }

    me.viewport.viewportimg.get(0).show_rois(theT, theZ, roi_list);
};

ctrl.refresh_rois = function (roi_list) {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    console.log("embed_big_image_DEV refresh_rois method");
    // re-plots the ROIs (if currently shown) for new Z and T position
    if (me.viewport.viewportimg.get(0).refresh_rois) {
        var theT = viewport.getTPos();
        var theZ = viewport.getZPos();
        //var filter = roi_list ? roi_list : viewport.viewportimg.get(0).get_current_rois_filter();
        console.log("Current ROIs filter", roi_list);
        console.log(roi_list);
        me.viewport.viewportimg.get(0).refresh_rois(theZ, theT, roi_list);
    }
};

ctrl.hide_rois = function () {
    var me = omero_viewer_controller;
    // hides the display of ROIs.
    if (me.viewport.viewportimg.get(0).hide_rois) {
        me.viewport.viewportimg.get(0).hide_rois();
    }
};


ctrl.show_scalebar = function () {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    if (!me.viewport.viewportimg.get(0).show_scalebar) {
        // if the Scalebar plugin has not been initialised (method not available...) init and load Scalebar...
        var options = {
            'pixSizeX': viewport.getPixelSizes().x,
            'imageWidth': viewport.getSizes().width
        };
        if (me.viewport.loadedImg.tiles) {
            options['tiles'] = true;
        }
        viewport.viewportimg.scalebar_display(options);
    }

    me.viewport.viewportimg.get(0).setScalebarZoom(viewport.getZoom() / 100);
    me.viewport.viewportimg.get(0).show_scalebar();

};

ctrl.hide_scalebar = function () {
    var me = omero_viewer_controller;
    me.viewport.viewportimg.get(0).hide_scalebar();
};

ctrl._imageLoad = function (ev, viewport) {

    var me = omero_viewer_controller;

    /**
     * This function is called when an image is initially loaded.
     * This is the place to sync everything; rendering model, quality, channel buttons, etc.
     */

    /* load metadata */
    $('#image-name').html(viewport.loadedImg.meta.imageName);

    /* enable scalebar */
    tmp = viewport.getPixelSizes();
    if (tmp.x !== 0) {
        $("#viewport-scalebar").prop("disabled", false);
        $("#viewport-scalebar").prop("checked", true);
        me.show_scalebar();
    }

    /**
     * Attach functions to the click event on specific buttons
     */
    $("#viewport-show-rois").click(function () {
        me.show_rois();
    });
    $("#viewport-hide-rois").click(function () {
        me.hide_rois();
    });
    $("#viewport-add-shapes").click(function () {
        me.add_external_shapes();
    });
    $("#viewport-remove-shape-1").click(function () {
        me.remove_external_shape("X1", 1);
    });
    $("#viewport-remove-shape-2").click(function () {
        me.remove_external_shape("X2", 1);
    });


    /**
     * Attach functions to the click event on specific buttons
     */
        // 'Scalebar' checkbox to left of image
    $("#viewport-scalebar").change(function () {
        if (this.checked) {
            me.show_scalebar();
        } else {
            me.hide_scalebar();
        }
    });
};

ctrl.instant_zoom = function (e, percent) {
    var me = omero_viewer_controller;
    if (me.viewport.viewportimg.get(0).setRoiZoom) {
        me.viewport.viewportimg.get(0).setRoiZoom(percent);
    }
    if (me.viewport.viewportimg.get(0).setScalebarZoom) {
        me.viewport.viewportimg.get(0).setScalebarZoom(percent / 100);
    }
};

ctrl.add_external_shapes = function () {
    var me = omero_viewer_controller;
    if (!me.viewport.viewportimg.get(0).show_rois) {
        me.load_viewport();
    }

    var r1 = me.viewport.viewportimg.get_ome_rectangle(2000, 2000, 4000, 4000, 0, 0);
    var r2_shape = me.viewport.viewportimg.get_shape_config(undefined, undefined, undefined,
        undefined, "#0000ff");
    var r2 = me.viewport.viewportimg.get_ome_rectangle(3000, 3000, 4000, 4000, 0, 0,
        undefined, r2_shape);

    var vimg = me.viewport.viewportimg.get(0);
    vimg.push_shape("X1", 1, r1, false);
    vimg.push_shape("X2", 1, r2, true);
};

ctrl.remove_external_shape = function (roi_id, shape_id) {
    var me = omero_viewer_controller;
    var vimg = me.viewport.viewportimg.get(0);
    vimg.remove_shape(roi_id, shape_id, true);
};


/**
 * Load and render the image identified by 'image_id',
 * resizing the viewer container
 *
 * @param image_id the image to load
 * @param resize <code>true</code> if the container has to be resized;
 *               <code>false</code> otherwise
 */
ctrl.load_and_render_image = function (image_id, resize) {

    var me = omero_viewer_controller;

    /* Load the selected image into the viewport */
    me.viewport.load(image_id);

    /* Render the rois table */
    me.get_rois_info(image_id, function (data) {
        me._current_roi_list = data;
        me._render_rois_table(image_id, data);
    }, function (data) {
        console.log("Error", data);
        alert("Error during ROIs info loading..."); //FIXME: remove alert!!!
    });


    /* Resize the current viewer */
    if (resize || resize != false)
        me.resize();
};


ctrl._render_rois_table = function (image_id, dataSet) {

    var me = omero_viewer_controller;

    console.log("Rendering table started .... ");

    var roi_table = $('#rois-table');
    roi_table.dataTable({
        "data": dataSet,
        "cell-border": true,
        "pageLength": 2,
        "lengthMenu": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        "columns": [
            {"title": "ID", data: "id", "width": "20px", "className": "dt-head-center dt-body-center"},
            {"title": "Z", data: "shapes[0].theZ", "width": "20px", "className": "dt-head-center dt-body-center"},
            {"title": "T", data: "shapes[0].theT", "width": "20px", "className": "dt-head-center dt-body-center"},
            {"title": "Description", data: "shapes[0].description", "className": "roi-description dt-head-center dt-body-left"},
            {
                "title": "Preview",
                "data": "shapes[0].id",
                "className": "dt-head-center dt-body-center",
                "width": "100px",
                "render": function (data, type, row) {
                    if (type === 'display') {
                        return '<img src=" ' + me.omero_server +
                            '/webgateway/render_shape_thumbnail/0' + data + '/?color=f00" ' +
                            'id="' + data + '_shape_thumb" ' +
                            'class="roi_thumb shape_thumb" ' +
                            'color="f00" width="100" height="100" style="display: inline;">';
                    }
                    return data;
                }
            },
            {
                "title": "Visibility",
                "data": "id",
                "className": "dt-head-center dt-body-center",
                "width": "20px",
                "render": function (data, type, row) {
                    if (type === 'display') {
                        return '<input id="visibility_selector_'
                            + data + '" type="checkbox" class="editor-active" style="width: 20px">';
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


    $('#rois-table tbody').on('click', 'tr', function () {
        var selected_roi_shape = roi_table.DataTable().row(this).data();
        if (selected_roi_shape) {
            console.log("Selected ROI shape", selected_roi_shape);

        }
    });


    // Handle the selection of a given row (image)
    roi_table.on('change', function (event) {
        var me = omero_viewer_controller;
        var selectorId = event.srcElement.id;
        if (selectorId) {
            var roiId = selectorId.match(/[0-9]+/);
            if (roiId) {

                var checked = $("#" + selectorId).is(":checked");
                console.log("Changed visibility of " + roiId + ": " + (checked ? "display" : "hidden"));

                var selected_roi_info = $.grep(me._current_roi_list, function (e) {
                    return e.id == roiId;
                });
                if (selected_roi_info.length > 0) {
                    var selected_shape_info = {};
                    selected_shape_info[selected_roi_info[0].id] = [selected_roi_info[0].shapes[0]]; // FIXME: a better mechanism for shape selection
                    checked ? me.show_rois(selected_shape_info) : me.hide_rois(selected_roi_info);
                }

                // FIXME: it is just an example of event notification
                window.postMessage({
                    roiId: roiId,
                    event: "roi_visibility_changed",
                    visibility: (checked ? "display" : "hidden")
                }, "*");
            }
        }
    });

    // Resize after pagination FIXME: is really needed?
    roi_table.on('page.dt', function () {
        var info = $('#rois-table').DataTable().page.info();
        $('#pageInfo').html('Showing page: ' + info.page + ' of ' + info.pages);
        omero_viewer_controller.resize();
    });

    // Resize after every draw
    roi_table.on('draw.dt', function () {
        console.log('Redraw occurred at: ' + new Date().getTime());
        omero_viewer_controller.resize();
    });


    // call resize
    omero_viewer_controller.resize();
    console.log("Rendering table stopped .... ");
};


ctrl.get_rois_info = function (image_id, success_callback, error_callback) {
    var me = omero_viewer_controller;

    $.ajax({
        url: me.omero_server + "/webgateway/get_rois_json/" + image_id,

        // The name of the callback parameter, as specified by the YQL service
        jsonp: "callback",

        // Tell jQuery we're expecting JSONP
        dataType: "jsonp",

        // Request parameters
        data: {
            q: "", //FIXME: not required
            format: "json"
        },

        // Set callback methods
        success: function(data){

            // post process data
            $.each(data, function(index){
                var obj = $(this)[0]
                console.log("current", index, obj);
                obj.shapes[0].description = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
            });

            if(success_callback){
                success_callback(data);
            }
        },
        error: error_callback
    });
};

ctrl.resize = function () {
    var me = omero_viewer_controller;
    var iframe = parent.parent.document.getElementById(me.frame_id);
    var omeroViewport = iframe.contentDocument.getElementById(me.viewport_id);
    var roisTable = iframe.contentDocument.getElementById(me.rois_table_id);

    console.log("iframe", iframe);
    console.log("viewport", omeroViewport);
    console.log("table", roisTable);

    var height = omeroViewport.offsetHeight + roisTable.offsetHeight + 300;
    iframe.style.height = height + "px";
};
