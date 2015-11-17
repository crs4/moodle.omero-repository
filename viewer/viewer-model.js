/**
 * The instance of the Image Model Manager
 *
 * @type {{image__model_manager}}
 */
image_model_manager = {};

// internal shortcut for the manager instance
var mgt = image_model_manager;

/**
 * Initialize the model manager of the actual omero viewer
 *
 * @param image_server the actual image server URL (e.g., http://omero.crs4.it:8080)
 * @param image_id the ID of the image to manage
 */
mgt.init = function (image_server, image_id) {

    // register the address of the current OMERO server
    mgt._image_server = image_server;

    // register the ID of the image to manage
    mgt._image_id = image_id;

    // log init status
    console.info("image_model_manager initialized!!!")
};



/**
 * Load info of ROIs related to the current image
 *
 * @param image_id
 * @param success_callback
 * @param error_callback
 * @private
 */
mgt.loadRoisInfo = function (success_callback, error_callback) {
    var me = image_model_manager;

    $.ajax({
        url: me._image_server + "/webgateway/get_rois_json/" + mgt._image_id,

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
        success: function (data) {

            // post process data:
            // adapt the model removing OMERO complexity
            var result = [];
            $.each(data, function (index) {
                var obj = $(this)[0];
                result[index] = obj.shapes[0];
            });

            if (success_callback) {
                success_callback(result);
            }

            // Notify that ROI info are loaded
            window.dispatchEvent(new CustomEvent(
                "image_server.roisInfoLoaded",
                {
                    detail: result,
                    bubbles: true
                })
            );
        },
        error: error_callback
    });
};