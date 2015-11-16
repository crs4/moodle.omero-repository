/**
 * The instance of the controller for the Image Viewer
 *
 * @type {{image_viewer_controller}}
 */
image_viewer_controller = {};

// internal shortcut for the controller instance
var me = image_viewer_controller;

/**
 * Initialize the controller of the actual image viewer
 *
 * @param image_server the actual image server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 */
me.init = function (image_server, frame_id, viewport_id, rois_table_id, roi_shape_thumb_popup_id,
                    image_id, show_roi_table, image_params, visible_rois) {

    // register the actual initialization parameters
    me._image_server = image_server;
    me._frame_id = frame_id;
    me._viewport_id = viewport_id;
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
    if (!me._viewer) {
        console.warn("ViewerController not initialized!!!");
    }

    // TODO: add param to change the default behaviour
    if (me._viewer) {
        me.showImage();
    }

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!", me);
};


/**
 * Registers a reference to the concrete ImageViewerController
 * @param viewer
 */
me.setViewer = function (viewer) {
    me._viewer = viewer;
};

/**
 * Shows the image
 */
me.showImage = function () {
    me._viewer.buildViewer();
};




