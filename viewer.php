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
$width = $_GET['width'];
$height = $_GET['height'];

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <META HTTP-EQUIV="Content-Style-Type" CONTENT="text/css">
    <title>OMERO.web - embeded viewer</title>

    <style type="text/css">
        .viewport {
            height: <?= $height ?>;
            width: <?= $width ?>;
            padding: 5px;
        }
    </style>

    <link rel="stylesheet" type="text/css" href="<?= $OMERO_SERVER ?>/static/omeroweb.viewer.min.css">
    <script type="text/javascript" src="<?= $OMERO_SERVER ?>/static/omeroweb.viewer.min.js"></script>
    <script type="text/javascript">

        $(document).ready(function () {
            $.ajaxSettings.cache = false;
        });

        var viewport;

        var load_viewport = function () {
            if (!viewport.viewportimg.get(0).refresh_rois) {
                var options = {
                    'width': viewport.loadedImg.size.width,
                    'height': viewport.loadedImg.size.height,
                    'json_url': '<?= $OMERO_SERVER ?>/webgateway/get_rois_json/' + viewport.loadedImg.id
                };
                if (viewport.loadedImg.tiles) {
                    options['tiles'] = true;
                }

                viewport.viewportimg.roi_display(options);
                viewport.viewportimg.get(0).setRoiZoom(viewport.viewportimg.get(0).getZoom());
            }
            else
                console.log("Viewport already loaded");
        };

        var show_rois = function () {
            var theT = viewport.getTPos();
            var theZ = viewport.getZPos();

            if (!viewport.viewportimg.get(0).show_rois) {
                load_viewport();
            }

            // loads ROIs (if needed) and shows.
            viewport.viewportimg.get(0).show_rois(theZ, theT);
        };

        var refresh_rois = function () {
            console.log("embed_big_image_DEV refresh_rois method");
            // re-plots the ROIs (if currently shown) for new Z and T position
            if (viewport.viewportimg.get(0).refresh_rois) {
                var theT = viewport.getTPos();
                var theZ = viewport.getZPos();
                var filter = viewport.viewportimg.get(0).get_current_rois_filter();
                console.log("Current ROIs filter");
                console.log(filter);
                viewport.viewportimg.get(0).refresh_rois(theZ, theT, filter);
            }
        };

        var hide_rois = function () {
            // hides the display of ROIs.
            if (viewport.viewportimg.get(0).hide_rois) {
                viewport.viewportimg.get(0).hide_rois();
            }
        };

        var show_scalebar = function () {
            if (!viewport.viewportimg.get(0).show_scalebar) {
                // if the Scalebar plugin has not been initialised (method not available...) init and load Scalebar...
                var options = {
                    'pixSizeX': viewport.getPixelSizes().x,
                    'imageWidth': viewport.getSizes().width
                };
                if (viewport.loadedImg.tiles) {
                    options['tiles'] = true;
                }
                viewport.viewportimg.scalebar_display(options);
            }

            viewport.viewportimg.get(0).setScalebarZoom(viewport.getZoom() / 100);
            viewport.viewportimg.get(0).show_scalebar();

        };

        var hide_scalebar = function () {
            viewport.viewportimg.get(0).hide_scalebar();
        };

        var _imageLoad = function (ev, viewport) {

            /**
             * This function is called when an image is initially loaded.
             * This is the place to sync everything; rendering model, quality, channel buttons, etc.
             */

            /* load metadata */
            $('#image-name').html(viewport.loadedImg.meta.imageName);

            /* enable scalebar */
            tmp = viewport.getPixelSizes();
            if (tmp.x !== 0) {
                $("#viewport-scalebar").prop("disabled", false);
                $("#viewport-scalebar").prop("checked", true);
                show_scalebar();
            }

            /**
             * Attach functions to the click event on specific buttons
             */
            $("#viewport-show-rois").click(function () {
                show_rois();
            });
            $("#viewport-hide-rois").click(function () {
                hide_rois();
            });
            $("#viewport-add-shapes").click(function () {
                add_external_shapes();
            });
            $("#viewport-remove-shape-1").click(function () {
                remove_external_shape("X1", 1);
            });
            $("#viewport-remove-shape-2").click(function () {
                remove_external_shape("X2", 1);
            });


            /**
             * Attach functions to the click event on specific buttons
             */

                // 'Scalebar' checkbox to left of image
            $("#viewport-scalebar").change(function () {
                if (this.checked) {
                    show_scalebar();
                } else {
                    hide_scalebar();
                }
            });
        };

        var instant_zoom = function (e, percent) {
            if (viewport.viewportimg.get(0).setRoiZoom) {
                viewport.viewportimg.get(0).setRoiZoom(percent);
            }
            if (viewport.viewportimg.get(0).setScalebarZoom) {
                viewport.viewportimg.get(0).setScalebarZoom(percent / 100);
            }
        };

        var add_external_shapes = function () {
            if (!viewport.viewportimg.get(0).show_rois) {
                load_viewport();
            }

            var r1 = viewport.viewportimg.get_ome_rectangle(2000, 2000, 4000, 4000, 0, 0);
            var r2_shape = viewport.viewportimg.get_shape_config(undefined, undefined, undefined,
                undefined, "#0000ff");
            var r2 = viewport.viewportimg.get_ome_rectangle(3000, 3000, 4000, 4000, 0, 0,
                undefined, r2_shape);

            var vimg = viewport.viewportimg.get(0);
            vimg.push_shape("X1", 1, r1, false);
            vimg.push_shape("X2", 1, r2, true);
        };

        var remove_external_shape = function (roi_id, shape_id) {
            var vimg = viewport.viewportimg.get(0);
            vimg.remove_shape(roi_id, shape_id, true);
        };

        $(document).ready(function () {

            /* Prepare the viewport */
            viewport = $.WeblitzViewport($("#viewport"), "<?= $OMERO_SERVER ?>/webgateway/", {
                'mediaroot': "<?= $OMERO_SERVER ?>/static/"
            });

            /* Async call needs loading */
            viewport.bind('imageLoad', _imageLoad);
            /* Bind zoomimg action to the ROIs */
            viewport.bind('instant_zoom', instant_zoom);

            /* Load the selected image into the viewport */
            viewport.load(<?=$imageId?>);

        });
    </script>

</head>
<body style="background: white; padding: 10px; border: none; ">

<label for="viewport-scalebar">Scalebar</label>
<input id="viewport-scalebar" type="checkbox" disabled/>

<button id="viewport-show-rois" title="Show ROIs">Show ROIs</button>
<button id="viewport-hide-rois" title="Hide ROIs">Hide ROIs</button>

<button id="viewport-add-shapes" title="Add shapes">Add External Shapes</button>
<button id="viewport-remove-shape-1" title="Remove shape 1">Remove External Shape #1</button>
<button id="viewport-remove-shape-2" title="Remove shape 2">Remove External Shape #2</button>

<div id="viewport" class="viewport" style="margin:10px;"></div>

</body>
</html>
