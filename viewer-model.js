/**
 * The instance of the Omero Image model Manager
 *
 * @type {{omero_image__model_manager}}
 */
omero_image_model_manager = {};

// internal shortcut for the manager instance
var mgt = omero_image_model_manager;

/**
 * Initialize the model manager of the actual omero viewer
 *
 * @param omero_server the actual omero server URL (e.g., http://omero.crs4.it:8080)
 * @param image_id the ID of the image to manage
 */
mgt.init = function (omero_server, image_id) {

    // register the address of the current OMERO server
    mgt._omero_server = omero_server;

    // register the ID of the image to manage
    mgt._image_id = image_id;

    // log init status
    console.info("omero_image_model_manager initialized!!!")
};



