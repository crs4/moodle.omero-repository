<?php

/**
 * To perform a test outside Moodle, use a link like this:
 * http://<MOODLE_SERVER_URL>/repository/omero/viewer.php
 *              ?id=<IMAGE_ID>
 *              &frame=<FRAME_ID>
 *              &width=92%25&height=100%25
 */

// Moodle ROOT directory
$MOODLE_ROOT = dirname(__FILE__) . "/../../../";
// Include Moodle configuration
require_once("$MOODLE_ROOT/config.php");

//
defined('MOODLE_INTERNAL') || die();

// check whether the user is logged
if (!isloggedin()) {
    $moodle_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . "/moodle";
    header('Location: ' . $moodle_url);
}


// FIXME: do not include the string 'webgateway'
// FIXME: change strings to hide OMERO dependencies
// build the OMERO server URL
$OMERO_WEBGATEWAY = get_config('omero', 'omero_restendpoint');
$IMAGE_SERVER = substr($OMERO_WEBGATEWAY, 0, strpos($OMERO_WEBGATEWAY, "/webgateway"));

// set the ID of the viewer container
$IMAGE_VIEWER_CONTAINER = "openseadragon_viewer";

// Read parameters from the actual URL
$imageId = $_GET['id'];
$frameId = $_GET['frame'];
$width = $_GET['width'] = "600px";//? !empty($_GET['width']) : "80%";
$height = $_GET['height']; //? !empty($_GET['height']) : "100%";
$showRoiTable = isset($_GET['showRoiTable']) ? $_GET['showRoiTable'] : "false";
$visibleRoiList = isset($_GET['visibleRois']) ? $_GET['visibleRois'] : "";


$imageParamKeys = ["m", "p", "ia", "q", "t", "z", "zm", "x", "y"];
$imageParams = array();
foreach ($imageParamKeys as $paramName) {
    if (isset($_REQUEST[$paramName]))
        $imageParams[$paramName] = $_REQUEST[$paramName];
}
$imageParamsJs = "?" . implode('&',
        array_map(function ($v, $k) {
            return $k . '=' . $v;
        }, $imageParams, array_keys($imageParams)));

?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <title>Embedded OPENSEADRAGON Viewer for Moodle</title>

    <!-- ImageViewerController -->
    <script type="text/javascript" src="/moodle/repository/omero/viewer/viewer-controller.js"></script>

    <!-- ImageModelManager -->
    <script type="text/javascript" src="/moodle/repository/omero/viewer/viewer-model.js"></script>

    <!-- OME_SEADRAGON dependencies -->
    <script src="<?php echo $IMAGE_SERVER ?>/static/ome_seadragon/js/openseadragon.min.js"></script>
    <script src="<?php echo $IMAGE_SERVER ?>/static/ome_seadragon/js/jquery-1.11.3.min.js"></script>
    <script src="<?php echo $IMAGE_SERVER ?>/static/ome_seadragon/js/ome_seadragon.min.js"></script>
    <script src="<?php echo $IMAGE_SERVER ?>/static/webgateway/js/ome.csrf.js"></script>

    <!-- JQuery/Bootstrap table integration -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript"
            src="https://cdn.datatables.net/plug-ins/1.10.7/integration/bootstrap/3/dataTables.bootstrap.js"></script>


    <script type="text/javascript">

        $(document).ready(function () {

            // Get a reference to the actual image_viewer_controller
            var viewer_ctrl = image_viewer_controller;
            // Initialize the image_viewer_controller
            viewer_ctrl.init("<?= $IMAGE_SERVER ?>", "<?= $frameId ?>",
                "viewport", "rois-table", "roi_thumb_popup", "<?= $imageId ?>",
                "<?= $showRoiTable ?>", "<?= $imageParamsJs ?>", "<?= $visibleRoiList ?>");

            // Get a reference to the actual image_model_manager
            var image_mgt = image_model_manager;
            // Initialize the image_model_maanger
            image_mgt.init("<?= $IMAGE_SERVER ?>", "<?= $imageId ?>");
        });
    </script>
</head>
<body>
<div id="<?= $IMAGE_VIEWER_CONTAINER ?>" style="width: <?= $width ?>; height: <?= $height ?>"></div>
</body>
</html>