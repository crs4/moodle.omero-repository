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
abstract class omero extends oauth_helper
{
    /** @var string omero access type, can be omero or sandbox */
    protected $mode = 'omero';
    /** @var string omero api url */
    protected $repository_server;
    /** @var string omero content api url */
    protected $omero_content_api;
    /** @var RepositoryUrls */
    public $URLS;


    /**
     * Constructor for omero class
     *
     * @param array $options
     */
    function __construct($options = array())
    {
        parent::__construct($this->get_config($options));
        $this->repository_server = get_config('omero', 'omero_restendpoint');
        $this->URLS = new RepositoryUrls();
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
     * @param string $request
     * @param bool $decode
     * @return mixed
     */
    public function process_request($request = '/', $decode = true)
    {
        debugging("PROCESSING REQUEST: $request - decode: $decode" . ($decode ? "yes" : "no"));
        $request_info = RepositoryUrls::extract_request($request);
        if (!$request_info)
            throw new InvalidArgumentException("Invalid request: unable to identify the actual request '$request'");

        $result = false;
        if (isset($request_info["id"])) {
            $result = $this->{$request_info["request"]}($request_info["id"])
                ? isset($request_info["id"])
                : $this->{$request_info["request"]}();
        }
        $result = $result ? $decode : json_encode($result);
        debugging("PROCESSING REQUEST OK");
        return $result;
    }

    protected function do_http_request($request, $decode = true)
    {
        debugging("Processing HTTP request: $request, $decode ....");
        $response = $this->get($request, array(), $this->access_token_api, $this->access_token_api);
        $result = $decode ? json_decode($response) : $response;
        debugging("HTTP request processed: $request, $decode");
        return $result;
    }


    public abstract function get_annotations();

    public abstract function find_annotations($query);

    public abstract function get_tagset($tagset_id, $tags = true);

    public abstract function get_tag($tag_id, $images = true);

    public abstract function get_projects();

    public abstract function get_project($project_id, $datasets = true);

    public abstract function get_datasets($project_id, $images = true);

    public abstract function get_dataset($dataset_id, $images = true);

    public abstract function get_image($image_id, $rois = true, $decode = false);

    public abstract function get_image_dzi($image_id, $decode = false);

    public abstract function get_image_thumbnail($image_id, $lastUpdate, $height = 128, $width = 128);

    /**
     * @param $search
     * @return mixed
     */
    public function process_search($search)
    {
        debugging("Processing SEARCH: $search ...");
        $search_info = RepositoryUrls::extract_query($search);
        if (!$search_info)
            throw new InvalidArgumentException("Invalid request: unable to identify the actual request '$search'");
        $response = $this->{$search_info["request"]}($search_info["query"]);
        $data = json_decode($response);
        debugging("Processed SEARCH: $search OK");
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
        $url = $this->repository_server . '/thumbnails/' . $this->mode . $this->prepare_filepath($filepath);
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
        $url = $this->repository_server . '/files/' . $this->mode . $this->prepare_filepath($filepath);
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
        $url = $this->repository_server . '/shares/' . $this->mode . $this->prepare_filepath($filepath);
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


class OmeSeadragonApi extends omero
{
    /** @var string */
    protected $base_url;

    public function __construct($options = array())
    {
        parent::__construct($options);
        $this->base_url = $this->repository_server . "/ome_seadragon";
    }


    public function get_annotations()
    {
        return $this->do_http_request($this->base_url . "/get/annotations");
    }

    public function find_annotations($query)
    {
        return $this->do_http_request($this->base_url . "/find/annotations?query=$query");
    }

    public function get_tagset($tagset_id, $tags = true)
    {
        return $this->do_http_request($this->base_url . "/get/tagset/$tagset_id?tags=$tags");
    }

    public function get_tag($tag_id, $images = true)
    {
        return $this->do_http_request($this->base_url . "/get/tag/$tag_id?images=$images");
    }

    public function get_projects()
    {
        return $this->do_http_request($this->base_url . "/get/projects");
    }

    public function get_project($project_id, $datasets = true)
    {
        return $this->do_http_request($this->base_url . "/get/project/$project_id?datasets=$datasets");
    }

    public function get_datasets($project_id, $datasets = true)
    {
        return $this->do_http_request($this->base_url . "/get/project/$project_id?datasets=$datasets");
    }

    public function get_dataset($dataset_id, $images = true)
    {
        return $this->do_http_request($this->base_url . "/get/dataset/$dataset_id?images=$images");
    }

    public function get_image($image_id, $rois = true, $decode = false)
    {
        return $this->do_http_request($this->base_url . "/get/image/$image_id?rois=$rois", $decode);
    }

    public function get_image_dzi($image_id, $decode = false)
    {
        $result = ($this->do_http_request($this->base_url . "/deepzoom/image_mpp/${image_id}.dzi", $decode));
        return $result;
    }

    public function get_image_thumbnail($image_id, $lastUpdate, $height = 128, $width = 128)
    {
        return $this->do_http_request($this->base_url . "/deepzoom/get/thumbnail/${image_id}.dzi", false);
    }
}


class RepositoryUrls
{
    const ROOT = "/";
    const PROJECT = "/get_project";
    const PROJECTS = "/get_projects";
    const DATASET = "/get_dataset";
    const DATASETS = "/get_datasets";
    const ANNOTATIONS = "/get_annotations";
    const TAG = "/get_tag";
    const TAGS = "/get_tags";
    const TAGSET = "/get_tagset";
    const TAGSETS = "/get_tagsets";
    const IMAGE = "/get_image";
    const IMAGE_DZI = "/get_image_dzi";
    const IMAGES = "/get_images";
    const THUMBNAIL = "/get_image_thumbnail";


    private static function get_pattern($path, $with_id = false)
    {
        if ($with_id) $path .= '/(\d+)';
        return '/' . str_replace('/', '\/', $path) . '(\/)?/';
    }

    private static function is_url_type($type, $path, $with_id = false)
    {
        return preg_match(self::get_pattern($type, $with_id), $path);
    }

    public static function extract_request($request_url)
    {
        $result = false;
        if (preg_match("/\/([^\/]+)(\/(\d+)(\/)?)?/", $request_url, $matches)) {
            $result = array("request" => $matches[1]);
            if (count($matches) == 4)
                $result["id"] = $matches[3];
        }
        return $result;
    }

    public static function extract_query($request_url)
    {
        $result = false;
        if (preg_match("/\/([^\/]+)(\/(\w+)(\/)?)?/", $request_url, $matches)) {
            $result = array("request" => $matches[1]);
            if (count($matches) == 4)
                $result["query"] = $matches[3];
        }
        return $result;
    }

    public function get_root_url()
    {
        return self::ROOT;
    }

    public function is_root_url($path)
    {
        return !strcmp($path, self::ROOT);
    }

    public function is_projects_url($path)
    {
        return self::is_url_type(self::PROJECTS, $path);
    }

    public function get_projects_url()
    {
        return self::PROJECTS;
    }

    public function is_annotations_url($path)
    {
        return self::is_url_type(self::ANNOTATIONS, $path);
    }

    public function get_annotations_url()
    {
        return self::ANNOTATIONS;
    }

    public function is_annotations_query_url($path)
    {
        return self::is_url_type(self::ANNOTATIONS, $path, true);
    }

    public function get_annotations_query_url($query)
    {
        return self::ANNOTATIONS . "/" . $query;
    }

    public function is_tagset_url($path)
    {
        return self::is_url_type(self::TAGSET, $path, true);
    }

    public function get_tagset_url($tagset_id)
    {
        return self::TAGSET . "/" . $tagset_id;
    }

    public function is_tag_url($path)
    {
        return self::is_url_type(self::TAG, $path, true);
    }

    public function get_tag_url($tag_id)
    {
        return self::TAG . "/" . $tag_id;
    }

    public function is_project_url($path)
    {
        return self::is_url_type(self::PROJECT, $path, true);
    }

    public function get_project_url($project_id)
    {
        return self::PROJECT . "/" . $project_id;
    }

    public function is_dataset_url($path)
    {
        return self::is_url_type(self::DATASET, $path, true);
    }

    public function get_dataset_url($dataset_id)
    {
        return self::DATASET . "/" . $dataset_id;
    }

    public function is_image_url($path)
    {
        return self::is_url_type(self::IMAGE, $path, true);
    }

    public function get_image_url($image_id)
    {
        return self::IMAGE . "/" . $image_id;
    }

    public function get_image_dzi_url($image_id)
    {
        return self::IMAGE_DZI . "/" . $image_id;
    }

    public function is_image_thumnail_url($path)
    {
        return self::is_url_type(self::THUMBNAIL, $path, true);
    }

    public function get_image_thumbnail_url($image_id, $lastUpdate, $height = 128, $width = 128)
    {
        global $CFG;
        return "$CFG->wwwroot/repository/omero/thumbnail.php?" .
        "id=$image_id&lastUpdate=$lastUpdate&height=$height&width=$width";
    }

    public function get_element_id_from_url($url)
    {
        $result = self::extract_request($url);
        return ($result && isset($result["id"])) ? $result["id"] : false;
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