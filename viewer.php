<?php

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
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <META HTTP-EQUIV="Content-Style-Type" CONTENT="text/css">
    <title>OMERO.web-viewer</title>

    <!-- Third part CSS stylesheets *** -->

    <!-- OmeroWeb CSS -->
    <link rel="stylesheet" type="text/css" href="<?= $OMERO_SERVER ?>/static/omeroweb.viewer.min.css">

    <!-- Bootstrap CSS -->
    <link href="/moodle/repository/omero/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- overwrite default styles -->
    <style type="text/css">
        .viewport {
            width: 100%;
            height: 600px;
            margin-top: auto;
            margin-bottom: auto;
            margin-right: 15px;
            overflow: visible;
            padding: 5px;
        }
    </style>


    <!-- Third part libraries *** -->

    <!-- JQuery -->
    <script type="text/javascript" src="/moodle/repository/omero/libs/jquery/jquery-2.1.4.min.js"></script>
    <!-- Bootstrap -->
    <script type="text/javascript" src="/moodle/repository/omero/libs/bootstrap/js/bootstrap.min.js"></script>
    <!-- OmeroViewer lib -->
    <script type="text/javascript" src="<?= $OMERO_SERVER ?>/static/omeroweb.viewer.min.js"></script>
    <!-- OmeroViewerController -->
    <script type="text/javascript" src="/moodle/repository/omero/viewer.js"></script>

    <!--  Initialization script -->
    <script type="text/javascript">

        $(document).ready(function () {
            $.ajaxSettings.cache = false;
        });

        // FIXME: just for debug
        window.addEventListener("message", function (event) {
            console.log("Message", event);
        }, false);

        // Get a reference to the actual omero_viewer_controller
        var viewer_ctrl = omero_viewer_controller;
        // Initialize the omero_viewer_controller
        viewer_ctrl.init("<?= $OMERO_SERVER ?>", "<?= $frameId ?>", "<?= $imageId ?>");

    </script>

</head>

<body style="background: white; padding: 10px; border: none;">

<label for="viewport-scalebar">Scalebar</label>
<input id="viewport-scalebar" type="checkbox" disabled/>

<!-- FIXME: the following are example controls; the real controls have to be defined -->
<button id="viewport-show-rois" title="Show ROIs">Show ROIs</button>
<button id="viewport-hide-rois" title="Hide ROIs">Hide ROIs</button>
<button id="viewport-add-shapes" title="Add shapes">Add External Shapes</button>
<button id="viewport-remove-shape-1" title="Remove shape 1">Remove External Shape #1</button>
<button id="viewport-remove-shape-2" title="Remove shape 2">Remove External Shape #2</button>

<!-- container for the omero-viewer viewpoer -->
<div id="viewport" class="viewport"></div>

<!-- FIXME: Static table example: the table has to be dynamically generated -->
<div id="rois-table-container" class="panel panel-default" style="margin-top: 20px;font-size: 12pt;">

    <!-- Default panel contents -->
    <div class="panel-heading">ROI Inspector</div>

    <!-- Table -->
    <table id="rois-table" class="table" data-toggle="table">
        <thead>
        <tr>
            <th>Id</th>
            <th>Shape</th>
            <th>Color</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>1</td>
            <td>1</td>
            <td>1</td>
        </tr>
        <tr>
            <td>1</td>
            <td>1</td>
            <td>1</td>
        </tr>
        <tr>
            <td>1</td>
            <td>1</td>
            <td>1</td>
        </tr>
        <tr>
            <td>1</td>
            <td>1</td>
            <td>1</td>
        </tr>
        </tbody>
    </table>
</div>

</body>
</html>
