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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/oauthlib.php');

/**
 * A helper class to access omero resources
 *
 * @since      Moodle 2.0
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */
class omero extends oauth_helper
{
    /** @var string omero access type, can be omero or sandbox */
    private $mode = 'omero';
    /** @var string omero api url */
    private $omero_api;
    /** @var string omero content api url */
    private $omero_content_api;


    /**
     * Constructor for omero class
     *
     * @param array $options
     */
    function __construct($options = array())
    {
        parent::__construct($this->get_config($options));
        $this->omero_api = get_config('omero', 'omero_restendpoint');
    }

    /**
     * Returns the configuration merging default values with client definded
     * @param $options
     * @return array
     * @throws dml_exception
     */
    private function get_config($options)
    {
        // TODO: update the default settings
        return array_merge(array(
            "oauth_consumer_key" => get_config('omero', "omero_key"),
            "oauth_consumer_secret" => get_config('omero', "omero_secret"),
            "access_token" => "omero",
            "access_token_secret" => "omero"
        ), $options);
    }

    /**
     * Process request
     *
     * @param string $path
     * @param bool $decode
     * @param string $token
     * @param string $secret
     * @return mixed
     */
    public function process_request($path = '/', $decode = true, $token = '', $secret = '')
    {
        //debugging("PROCESSING REQUEST: $path - decode: $decode");
        $url = $this->omero_api . "/ome_seadragon" . $path;
        $response = $this->get($url, array(), $token, $secret);
        $result = $decode ? json_decode($response) : $response;
        //debugging("PROCESSING REQUEST OK");
        return $result;
    }


    /**
     * @param $search_text
     * @param string $token
     * @param string $secret
     * @return mixed
     */
    public function process_search($search_text, $token = '', $secret = '')
    {
        $url = $this->omero_api . "/ome_seadragon" . PathUtils::build_find_annotations_url($search_text);
        $content = $this->get($url, array(), $token, $secret);
        $data = json_decode($content);
        return $data;
    }

    /**
     * Prepares the filename to pass to omero API as part of URL
     *
     * @param string $filepath
     * @return string
     */
    protected function prepare_filepath($filepath)
    {
        $info = pathinfo($filepath);
        $dirname = $info['dirname'];
        $basename = $info['basename'];
        $filepath = $dirname . rawurlencode($basename);
        if ($dirname != '/') {
            $filepath = $dirname . '/' . $basename;
            $filepath = str_replace("%2F", "/", rawurlencode($filepath));
        }
        return $filepath;
    }

    /**
     * Retrieves the default (64x64) thumbnail for omero file
     *
     * @throws moodle_exception when file could not be downloaded
     *
     * @param string $filepath local path in omero
     * @param string $saveas path to file to save the result
     * @param int $timeout request timeout in seconds, 0 means no timeout
     * @return array with attributes 'path' and 'url'
     */
    public function get_thumbnail($filepath, $saveas, $timeout = 0)
    {
        $url = $this->omero_api . '/thumbnails/' . $this->mode . $this->prepare_filepath($filepath);
        if (!($fp = fopen($saveas, 'w'))) {
            throw new moodle_exception('cannotwritefile', 'error', '', $saveas);
        }
        $this->setup_oauth_http_options(array('timeout' => $timeout, 'file' => $fp, 'BINARYTRANSFER' => true));
        $result = $this->get($url);
        fclose($fp);
        if ($result === true) {
            return array('path' => $saveas, 'url' => $url);
        } else {
            unlink($saveas);
            throw new moodle_exception('errorwhiledownload', 'repository', '', $result);
        }
    }


    /**
     * Downloads a file from omero and saves it locally
     *
     * @throws moodle_exception when file could not be downloaded
     *
     * @param string $filepath local path in omero
     * @param string $saveas path to file to save the result
     * @param int $timeout request timeout in seconds, 0 means no timeout
     * @return array with attributes 'path' and 'url'
     */
    public function get_file($filepath, $saveas, $timeout = 0)
    {
        $url = $this->omero_api . '/files/' . $this->mode . $this->prepare_filepath($filepath);
        if (!($fp = fopen($saveas, 'w'))) {
            throw new moodle_exception('cannotwritefile', 'error', '', $saveas);
        }
        $this->setup_oauth_http_options(array('timeout' => $timeout, 'file' => $fp, 'BINARYTRANSFER' => true));
        $result = $this->get($url);
        fclose($fp);
        if ($result === true) {
            return array('path' => $saveas, 'url' => $url);
        } else {
            unlink($saveas);
            throw new moodle_exception('errorwhiledownload', 'repository', '', $result);
        }
    }

    /**
     * Returns direct link to omero file
     *
     * @param string $filepath local path in omero
     * @param int $timeout request timeout in seconds, 0 means no timeout
     * @return string|null information object or null if request failed with an error
     */
    public function get_file_share_link($filepath, $timeout = 0)
    {
        $url = $this->omero_api . '/shares/' . $this->mode . $this->prepare_filepath($filepath);
        $this->setup_oauth_http_options(array('timeout' => $timeout));
        $result = $this->post($url, array('short_url' => 0));
        if (!$this->http->get_errno()) {
            $data = json_decode($result);
            if (isset($data->url)) {
                return $data->url;
            }
        }
        return null;
    }

    /**
     * Sets omero API mode (omero or sandbox, default omero)
     *
     * @param string $mode
     */
    public function set_mode($mode)
    {
        $this->mode = $mode;
    }
}


/**
 * Utility class for building REST Api url
 */
class PathUtils
{

    public static function is_root_path($path)
    {
        return !strcmp($path, "/");
    }

    public static function is_projects_root($path)
    {
        return preg_match("/get\/projects/", $path);
    }

    public static function is_annotations_root($path)
    {
        return preg_match("/get\/annotations/", $path);
    }

    public static function is_tagset_root($path)
    {
        return preg_match("/get\/tagset\/(\d+)/", $path);
    }

    public static function is_tag($path)
    {
        return preg_match("/get\/tag\/(\d+)/", $path);
    }

    public static function is_project($path)
    {
        return preg_match("/get\/project\/(\d+)/", $path);
    }

    public static function is_dataset($path)
    {
        return preg_match("/get\/dataset\/(\d+)/", $path);
    }

    public static function is_image_file($path)
    {
        return preg_match("/get\/image/\/(\d+)/", $path);
    }

    public static function is_annotations_query($path)
    {
        return preg_match("/find\/annotations/", $path);
    }

    public static function build_project_list_url()
    {
        return "/get/projects";
    }

    public static function build_annotation_list_url()
    {
        return "/get/annotations";
    }

    public static function build_find_annotations_url($query)
    {
        return "/find/annotations?query=$query";
    }

    public static function build_tagset_deatails_url($tagset_id, $tags = true)
    {
        return "/get/tagset/$tagset_id?tags=$tags";
    }

    public static function build_tag_detail_url($tag_id)
    {
        return "/get/tag/$tag_id?images=true";
    }

    public static function build_project_detail_url($project_id)
    {
        return "/get/project/$project_id";
    }

    public static function build_dataset_list_url($project_id, $datasets = true)
    {
        return "/get/project/$project_id?datasets=$datasets";
    }

    public static function build_dataset_detail_url($dataset_id, $images = true)
    {
        return "/get/dataset/$dataset_id?images=$images";
    }

    public static function build_image_detail_url($image_id, $rois = true)
    {
        return "/get/image/$image_id?rois=$rois";
    }

    public static function build_image_dzi_url($image_id)
    {
        return "/deepzoom/image_mpp/${image_id}.dzi";
    }

    public static function build_image_thumbnail_url($image_id, $lastUpdate, $height = 128, $width = 128)
    {
        global $CFG;
        return "$CFG->wwwroot/repository/omero/thumbnail.php?id=$image_id&lastUpdate=$lastUpdate&height=$height&width=$width";
    }

    public static function get_element_id_from_url($url, $element_name)
    {
        if (preg_match("/$element_name\/(\d+)/", $url, $matches))
            return $matches[1];
        return null;
    }
}


/**
 * omero plugin cron task
 */
function repository_omero_cron()
{
    $instances = repository::get_instances(array('type' => 'omero'));
    foreach ($instances as $instance) {
        $instance->cron();
    }
}