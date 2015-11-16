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



