/**
 * The instance of the controller for the Omero Viewer
 *
 * @type {{omero_image_viewer_controller}}
 */
omero_image_viewer_controller = {};

// internal shortcut for the controller instance
var ctrl = omero_image_viewer_controller;

/**
 * Initialize the controller of the actual omero viewer
 *
 * @param omero_server the actual omero server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 */
ctrl.init = function (omero_server, frame_id, viewport_id, rois_table_id, roi_shape_thumb_popup_id,
                      image_id, show_roi_table, image_params, visible_rois) {

    var me = omero_viewer_controller;

    // register the actual initialization parameters
    me.omero_server = omero_server;
    me.frame_id = frame_id;
    me.viewport_id = viewport_id;
    me.rois_table_id = rois_table_id;
    me.image_id = image_id;
    me.image_params = image_params;
    me.visible_rois = visible_rois; // && visible_rois.length > 0 ? visible_rois.split(",") : [];
    me._visible_roi_shape_list = [];
    me.roi_shape_thumb_popup_id = roi_shape_thumb_popup_id;
    me._show_roi_table = show_roi_table;
    me.window = window;

    // set frame reference
    me._frame = window.parent.document.getElementById(me.frame_id);

    // creates the viewport
    $(document).ready(function () {
        console.log("Ready!!!");
    });
};



