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

// set the Moodle root directory
$MOODLEROOT=dirname(dirname(dirname(dirname(__FILE__))));

// load dependencies
require_once("$MOODLEROOT/config.php");
require_once("$MOODLEROOT/repository/omero/lib.php");
require_once("$MOODLEROOT/repository/omero/locallib.php");

// check whether Moodle Env exists
defined('MOODLE_INTERNAL') || die();

// check whether the user is logged
if (!isloggedin()) {
    $moodle_url = "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . "/moodle";
    header('Location: ' . $moodle_url);
}

// omero server
$omero_server = new omero();

// get method
$method = required_param("m", PARAM_TEXT);

// get the Image ID
$image_id = required_param("id", PARAM_INT);

// set the response header
header('Content-Type: application/json');

if($method == "img_details")
    echo $omero_server->process_request(PathUtils::build_image_detail_url($image_id), false);
else if($method == "dzi")
    echo $omero_server->process_request(PathUtils::build_image_dzi_url($image_id), false);
else
    echo json_encode(array("error"=>"Not supported method!!!"));
exit;