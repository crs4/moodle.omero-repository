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
 */
mgt.init = function (omero_server) {

    // register the address of the current OMERO server
    ctrl._omero_server = omero_server;

    // log init status
    console.info("omero_image_model_manager initialized!!!")
};



