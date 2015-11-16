<?php

/**
 * To perform a test outside Moodle, use a link like this:
 * http://<MOODLE_SERVER_URL>/repository/omero/viewer.php
 *              ?id=<IMAGE_ID>
 *              &frame=<FRAME_ID>
 *              &width=92%25&height=100%25
 */

// Moodle ROOT directory
$MOODLE_ROOT = dirname(__FILE__) . "/../../";
// Include Moodle configuration
require_once("$MOODLE_ROOT/config.php");

//
defined('MOODLE_INTERNAL') || die();

// check whether the user is logged
if (!isloggedin()) {
    $moodle_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . "/moodle";
    header('Location: ' . $moodle_url);
}


// build the OMERO server URL
$OMERO_WEBGATEWAY = get_config('omero', 'omero_restendpoint');
$OMERO_SERVER = substr($OMERO_WEBGATEWAY, 0, strpos($OMERO_WEBGATEWAY, "/webgateway"));


// Read parameters from the actual URL
$imageId = $_GET['id'];
$frameId = $_GET['frame'];
$width = $_GET['width'] ? !empty($_GET['width']) : "80%";
$height = $_GET['height']; //? !empty($_GET['height']) : "100%";
$showRoiTable = isset($_GET['showRoiTable']) ? $_GET['showRoiTable'] : "false";
$visibleRoiList = isset($_GET['visibleRois']) ? $_GET['visibleRois'] : "";


$imageParamKeys = ["m", "p", "ia", "q", "t", "z", "zm", "x", "y"];
$imageParams = array();
foreach ($imageParamKeys as $paramName) {
    if (isset($_REQUEST[$paramName]))
        $imageParams[$paramName] = $_REQUEST[$paramName];
}
$imageParamsJs = "?" .implode('&',
    array_map(function ($v, $k) { return $k . '=' . $v; }, $imageParams, array_keys($imageParams)));

$OME_SEADRAGON = "http://omero-test.crs4.it:8080"

?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <title>OPENSEADRAGON TEST VIEWER</title>



    <script src="<?php echo $OME_SEADRAGON ?>/static/ome_seadragon/js/openseadragon.min.js"></script>
    <script src="<?php echo $OME_SEADRAGON ?>/static/ome_seadragon/js/jquery-1.11.3.min.js"></script>
    <script src="<?php echo $OME_SEADRAGON ?>/static/ome_seadragon/js/ome_seadragon.min.js"></script>
    <script src="<?php echo $OME_SEADRAGON ?>/static/webgateway/js/ome.csrf.js"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            console.log("Loading openseadragon viewer");
            window.viewer = new ViewerController(
                "openseadragon_viewer",
                "<?php echo $OME_SEADRAGON ?>/static/ome_seadragon/img/openseadragon/",
                "<?php echo $OME_SEADRAGON ?>/ome_seadragon/deepzoom/get/<?php echo $imageId ?>.dzi"
            );
            viewer.buildViewer();
        });
    </script>
</head>
<body>
<div id="openseadragon_viewer" style="width: 800px; height: 600px"></div>
</body>
</html>