<?php

/**
 * This script displays one thumbnail of the image in current user's omero.
 * If {@link repository_omero::send_thumbnail()} can not display image
 * the default 64x64 filetype icon is returned
 *
 * @package    repository_omero
 * @copyright  2015 CRS4
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

// check whether Moodle Env exists
defined('MOODLE_INTERNAL') || die();

// check whether the user is logged
if (!isloggedin()) {
    $moodle_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . "/moodle";
    header('Location: ' . $moodle_url);
}

// get the OMERO server URL
$omero_server = get_config('omero', 'omero_restendpoint');

// get the Image ID
$image_id = required_param("id", PARAM_INT);

// get the Image LastUpdate
$image_last_update = required_param("lastUpdate", PARAM_INT);

// get the size of the image thumbnail
$image_width = optional_param("width", 128, PARAM_INT);
$image_height = optional_param("height", 128, PARAM_INT);

// optional param for forcing image reload
$force_reload = optional_param("force", false, PARAM_BOOL);

// get a reference to the cache
$cache = cache::make('repository_omero', 'thumbnail_cache');

// computer the key of the cached element
$cache_key = urlencode("${omero_server}-${image_id}-${image_last_update}");

// try to the get thumbnail from the cache
$file = $force_reload ? null : $cache->get($cache_key);

// download the file is needed and update the cache
if ($force_reload || !$file) {
    $cache->acquire_lock($cache_key);
    try {
        $file = $force_reload ? null : $cache->get($cache_key);
        if (!$file) {
            $url = "${omero_server}/ome_seadragon/deepzoom/get/thumbnail/${image_id}.dzi";
            $c = new curl();
            $file = $c->download_one($url, array("width" => $image_width, "height" => $image_height));
            if ($file) {
                $cache->set($cache_key, $file);
            }
        }
    } finally {
        $cache->release_lock($cache_key);
    }
}

// send the thumbnail
header("Content-Type: image/png");
echo $file;
exit;