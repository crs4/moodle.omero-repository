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
$showRoiTable = $_GET['showRoiTable'];


$imageParamKeys = ["m", "p", "ia", "q", "t", "z", "zm", "x", "y"];
$imageParams = array();
foreach ($imageParamKeys as $paramName) {
    if (isset($_REQUEST[$paramName]))
        $imageParams[$paramName] = $_REQUEST[$paramName];
}
$imageParamsJs = "?" .implode('&',
    array_map(function ($v, $k) { return $k . '=' . $v; }, $imageParams, array_keys($imageParams)));

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
    <!-- JQuery/Bootstrap table CSS -->
    <link href="https://cdn.datatables.net/1.10.7/css/jquery.dataTables.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/plug-ins/1.10.7/integration/bootstrap/3/dataTables.bootstrap.css"
          rel="stylesheet">

    <!-- overwrite default styles -->
    <style type="text/css">
        /* Custom style of the viewport */
        .viewport {
            width: <?= $width ?>;
            height: <?= $height ?>;
            margin-top: auto;
            margin-bottom: auto;
            margin-right: 15px;
            overflow: visible;
            padding: 5px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0;
        }

        /* Fixes position of the entry number selector */
        .dataTables_length {
            padding: 18px;
        }

        /* Fixes the search box position */
        .dataTables_filter {
            padding: 18px;
        }

        /* hide the default jquery icon of the sort controls */
        table.dataTable thead .sorting_asc {
            background-image: none;
        }

        table.dataTable thead .sorting_desc {
            background-image: none;
        }

        table.dataTable thead .sorting {
            background-image: none;
        }

        table.dataTable tbody tr td.roi_thumb {
            vertical-align: middle;
        }

        /* fixes position of the current entries caption */
        .col-sm-5 {
            margin-left: 15px;
            margin-bottom: 15px;
            padding-top: 0px;
            padding-bottom: 5px;
            width: 37%;
        }

        /* fixes position of the pagination bar */
        .col-sm-7 {
            padding: 5px;
        }

        #viewport-zsl {
            visibility: hidden;
        }

        #viewport-bot {
            visibility: hidden;
        }

    </style>


    <!-- Third part libraries *** -->
    <!-- JQuery -->
    <script type="text/javascript" src="/moodle/repository/omero/libs/jquery/jquery-2.1.4.min.js"></script>
    <!-- Bootstrap -->
    <script type="text/javascript" src="/moodle/repository/omero/libs/bootstrap/js/bootstrap.min.js"></script>
    <!-- OmeroViewer lib -->
    <script type="text/javascript" src="<?= $OMERO_SERVER ?>/static/omeroweb.viewer.min.js"></script>
    <!--<script type="text/javascript" src="/moodle/repository/omero/omeroweb.viewer.min.js"></script>-->
    <!-- OmeroViewerController -->
    <script type="text/javascript" src="/moodle/repository/omero/viewer.js"></script>
    <!-- JQuery/Bootstrap table integration -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript"
            src="https://cdn.datatables.net/plug-ins/1.10.7/integration/bootstrap/3/dataTables.bootstrap.js"></script>

    <!--  Initialization script -->
    <script type="text/javascript">

        $(document).ready(function () {
            $.ajaxSettings.cache = false;
        });

        // Get a reference to the actual omero_viewer_controller
        var viewer_ctrl = omero_viewer_controller;
        // Initialize the omero_viewer_controller
        viewer_ctrl.init("<?= $OMERO_SERVER ?>", "<?= $frameId ?>",
            "viewport", "rois-table", "roi_thumb_popup", "<?= $imageId ?>",
            "<?= $showRoiTable ?>", "<?= $imageParamsJs ?>");

        // Expose the refresh_rois method
        refresh_rois = viewer_ctrl._refresh_rois;

        //        // FIXME: just for debug
        //        window.addEventListener("message", function (event) {
        //            console.log("Message", event, window);
        //        }, false);

    </script>

</head>

<body style="background: white; border: none; padding: 10px;">

<label for="viewport-scalebar">Scalebar</label>
<input id="viewport-scalebar" type="checkbox" disabled/>

<!-- FIXME: the following are example controls; the real controls have to be defined -->
<!--<button id="viewport-show-rois" title="Show ROIs">Show ROIs</button>-->
<!--<button id="viewport-hide-rois" title="Hide ROIs">Hide ROIs</button>-->
<!--<button id="viewport-add-shapes" title="Add shapes">Add External Shapes</button>-->
<!--<button id="viewport-remove-shape-1" title="Remove shape 1">Remove External Shape #1</button>-->
<!--<button id="viewport-remove-shape-2" title="Remove shape 2">Remove External Shape #2</button>-->

<!-- container for the omero-viewer viewpoer -->
<div id="viewport" class="viewport"></div>

<!-- FIXME: Static table example: the table has to be dynamically generated -->
<img id="roi_thumb_popup" style="border: 1px solid rgb(187, 187, 187); display: none; left: 202px; top: 78px;" src="">

<?php if ($showRoiTable == "true") { ?>

    <div id="rois-table-container" class="panel panel-default"
         style="margin-top: 40px;font-size: 12pt;">

        <!-- Default panel contents -->
        <div class="panel-heading">ROI Shapes Inspector</div>

        <div style="margin-top: 10px;">
            <table id="rois-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
<?php } ?>
</body>
</html>
