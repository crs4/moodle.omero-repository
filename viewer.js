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

ctrl.show_rois = function () {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    var theT = viewport.getTPos();
    var theZ = viewport.getZPos();

    if (!viewport.viewportimg.get(0).show_rois) {
        me.load_viewport();
    }

    // loads ROIs (if needed) and shows.
    me.viewport.viewportimg.get(0).show_rois(theZ, theT);
};

ctrl.refresh_rois = function () {
    var me = omero_viewer_controller;
    var viewport = me.viewport;
    console.log("embed_big_image_DEV refresh_rois method");
    // re-plots the ROIs (if currently shown) for new Z and T position
    if (me.viewport.viewportimg.get(0).refresh_rois) {
        var theT = viewport.getTPos();
        var theZ = viewport.getZPos();
        var filter = viewport.viewportimg.get(0).get_current_rois_filter();
        console.log("Current ROIs filter");
        console.log(filter);
        me.viewport.viewportimg.get(0).refresh_rois(theZ, theT, filter);
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
    me._render_rois_table(image_id);

    /* Resize the current viewer */
    if (resize || resize != false)
        me.resize();
};


ctrl._render_rois_table = function (image_id) {

    console.log("Rendering table started .... ");

    var dataSet = [
        {
            "id": 10,
            "z": 1,
            "y": 1,
            "text": "Text",
            "preview": "...",
            "visibility": 1
        },
        {
            "id": 12,
            "z": 1,
            "y": 1,
            "text": "Pippo",
            "preview": "...",
            "visibility": 0
        },
        {
            "id": 12,
            "z": 1,
            "y": 1,
            "text": "Text X",
            "preview": "...",
            "visibility": 0
        }
    ];


    $('#rois-table').dataTable({
        "data": dataSet,
        "columns": [
            {"title": "ID", data: "id"},
            {"title": "Z", data: "z"},
            {"title": "Y", data: "y"},
            {"title": "Text", data: "text", "class": "center"},
            {"title": "Preview", data: "preview", "class": "center"},
            {
                "title": "Visibility",
                "data": "Active",
                "render": function (data, type, row) {
                    if (type === 'display') {
                        return '<input type="checkbox" class="editor-active">';
                    }
                    return data;
                },
                "className": "dt-body-center",
                "class": "center"
            }
        ],

        rowCallback: function (row, data) {
            // Set the checked state of the checkbox in the table
            console.log("DATA", row, data, data[4], data[5]);
            $('input.editor-active', row).prop('checked', data.visibility);
        }
    });


    omero_viewer_controller.resize();

    // Handle the selection of a given row (image)
    // FIXME: it is just an example of event notification
    $('#rois-table').on('change', function (event) {
        console.log($('td', this), event);
        console.log("CHECKED: ", $('#' + id).is(":checked"));
        var name = $('td', this).eq(0).text();
        if (name) {
            alert('click on ' + name + '\'s row');
            window.postMessage({event: "row_clicked", image_id: name}, "*");
        }
    });

    // Resize after pagination FIXME: is really needed?
    $('#rois-table').on('page.dt', function () {
        var info = $('#rois-table').DataTable().page.info();
        $('#pageInfo').html('Showing page: ' + info.page + ' of ' + info.pages);
        omero_viewer_controller.resize();
    });

    // Resize after every draw
    $('#rois-table').on('draw.dt', function () {
        console.log('Redraw occurred at: ' + new Date().getTime());
        omero_viewer_controller.resize();
    });

    console.log("Rendering table stopped .... ");
};


ctrl.get_rois_info = function (image_id, success_callback, error_callback) {
    var me = omero_viewer_controller;

    $.ajax({
        url: me.omero_server + "/webgateway/get_rois_json/201/",

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
        success: success_callback,
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
