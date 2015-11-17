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

    // register the actual initialization parameters
    this._image_server = image_server;
    this._frathis_id = frame_id;
    this._viewer_container_id = view_container_id;
    this._rois_table_id = rois_table_id;
    this._image_id = image_id;
    this._image_params = image_params;
    this._visible_rois = visible_rois; // && visible_rois.length > 0 ? visible_rois.split(",") : [];
    this._visible_roi_shape_list = [];
    this._roi_shape_thumb_popup_id = roi_shape_thumb_popup_id;
    this._show_roi_table = show_roi_table;

    // set frame reference
    this._frame = window.parent.document.getElementById(this._frame_id);

    // TODO: to change with the controller initialization
    window.viewer = new ViewerController(
        this._viewer_container_id,
        this._image_server + "/static/ome_seadragon/img/openseadragon/",
        this._image_server + "/ome_seadragon/deepzoom/get/" + this._image_id + ".dzi"
    );
    // Check viewer initialization
    if (!window.viewer) {
        console.error("Image viewer not initialized!!!");
        return
    }

    // Registers a reference to the current viewer
    this._viewer = window.viewer;


    this._model_manager = new ImageModelManager(image_server, image_id);


    // TODO: add param to change the default behaviour
    if (this._viewer) {
        this.showImage();
        this._model_manager.loadRoisInfo(function (data) {
            this._roi_id_list = data;
        });
    }

    // FIXME: just for debug
    window.addEventListener("image_server.roisInfoLoaded", function (data) {
        console.log(data);
    });

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
    console.log("VIEWER controller", this); // TODO: remove me!!!
};


/**
 * Registers a reference to a
 * @param model_manager
 */
ImageViewerController.prototype.setImageModelManager = function (model_manager) {
    this._model_manager = model_manager;
};


/**
 * Shows the image
 */
ImageViewerController.prototype.showImage = function () {
    this._viewer.buildViewer();
};
