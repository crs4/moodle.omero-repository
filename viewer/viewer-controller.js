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
    this._frame_id = frame_id;
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
    this._view = new ViewerController(
        this._viewer_container_id,
        this._image_server + "/static/ome_seadragon/img/openseadragon/",
        this._image_server + "/ome_seadragon/deepzoom/get/" + this._image_id + ".dzi"
    );

    // Check viewer initialization
    if (!this._view) {
        console.error("Image viewer not initialized!!!");
        return
    }else{
        // Binds the current viewer to the 'window' object
        window.viewer = this._view;
    }

    // initializes the ImageModelManager
    this._model = new ImageModelManager(image_server, image_id);

    // FIXME: just for debug
    window.addEventListener("image_server.roisInfoLoaded", function (data) {
        console.log(data);
    });

    // TODO: add param to change the default behaviour
    if (this._view) {
        this._view.buildViewer();
        this._model.loadRoisInfo(function (data) {
            this._roi_id_list = data;
        });
    }

    // log controller initialization status
    console.log("image_viewer_controller initialized!!!");
    console.log("VIEWER controller", this); // TODO: remove me!!!
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