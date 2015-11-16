/**
 * The instance of the controller for the Image Viewer
 *
 * @type {{image_viewer_controller}}
 */
image_viewer_controller = {};

// internal shortcut for the controller instance
var ctrl = image_viewer_controller;

/**
 * Initialize the controller of the actual image viewer
 *
 * @param image_server the actual image server URL (e.g., http://10.211.55.33:4789/moodle)
 * @param frame_id the frame containing the viewer if it exists
 * @param image_id the image of the image to immediately view after the initialization
 */
ctrl.init = function (image_server, frame_id, viewport_id, rois_table_id, roi_shape_thumb_popup_id,
                      image_id, show_roi_table, image_params, visible_rois) {

    var me = image_viewer_controller;

    // register the actual initialization parameters
    me._image_server = _image_server;
    me._frame_id = _frame_id;
    me._viewport_id = _viewport_id;
    me._rois_table_id = _rois_table_id;
    me._image_id = _image_id;
    me._image_params = image_params;
    me._visible_rois = visible_rois; // && visible_rois.length > 0 ? visible_rois.split(",") : [];
    me._visible_roi_shape_list = [];
    me._roi_shape_thumb_popup_id = roi_shape_thumb_popup_id;
    me._show_roi_table = show_roi_table;

    // set frame reference
    me._frame = window.parent.document.getElementById(me._frame_id);

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
};



