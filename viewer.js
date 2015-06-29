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
ctrl.init = function(omero_server, frame_id, image_id){

    var me = omero_viewer_controller;

    // register the actual initialization parameters
    me.omero_server = omero_server;
    me.frame_id = frame_id;
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
        /* draw the image if provided */
        if(image_id!=undefined)
            me.draw_image(image_id, true);

        // notify viewport creation
        window.postMessage({type: "omero_viewport_created"}, "*");
    });

    console.log("Initialization Ok!!!!");
};


ctrl.load_viewport = function () {
    var me = omero_viewer_controller;
    if (!viewport.viewportimg.get(0).refresh_rois) {
        var options = {
            'width': viewport.loadedImg.size.width,
            'height': viewport.loadedImg.size.height,
            'json_url': '<?= $OMERO_SERVER ?>/webgateway/get_rois_json/' + viewport.loadedImg.id
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
        load_viewport();
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
        show_rois();
    });
    $("#viewport-hide-rois").click(function () {
        hide_rois();
    });
    $("#viewport-add-shapes").click(function () {
        add_external_shapes();
    });
    $("#viewport-remove-shape-1").click(function () {
        remove_external_shape("X1", 1);
    });
    $("#viewport-remove-shape-2").click(function () {
        remove_external_shape("X2", 1);
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




ctrl.draw_image = function(image_id, resize){

    var me = omero_viewer_controller;

    /* Load the selected image into the viewport */
    me.viewport.load(image_id);

    /* Resize the current viewer */
    me.resize();
};



ctrl.resize = function(){
    var me = omero_viewer_controller;
    var iframe = parent.parent.document.getElementById(me.frame_id);
    var omeroViewport = iframe.contentDocument.getElementById("viewport");
    var roisTable = iframe.contentDocument.getElementById("roistable");

    console.log("iframe", iframe);
    console.log("viewport", omeroViewport);
    console.log("table", roisTable);

    var height = omeroViewport.offsetHeight + roisTable.offsetHeight + 200;
    iframe.style.height = height + "px";
};
