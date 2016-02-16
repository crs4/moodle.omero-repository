<?php

// Copyright (c) 2015-2016, CRS4
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
// COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
// IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

/**
 * This script displays one thumbnail of the image in current user's omero.
 *
 * @since      Moodle 2.0
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
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