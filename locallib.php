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
 * Class confidential_oauth2_client,
 * an helper class to handle OAuth request
 * from a confidential OAuth client.
 */
class confidential_oauth2_client extends oauth2_client
{
    private $disable_login_check = false;

    protected function enable_authorization($enabled = true)
    {
        $this->disable_login_check = !$enabled;
    }

    /**
     * Returns the auth url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function auth_url()
    {
        return get_config('omero', 'omero_restendpoint') . "/oauth2/authorize/";
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function token_url()
    {
        return get_config('omero', 'omero_restendpoint') . "/oauth2/token/";
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function revoke_url()
    {
        return get_config('omero', 'omero_restendpoint') . "/oauth2/token/";
    }

    /**
     * Is the user logged in? Note that if this is called
     * after the first part of the authorisation flow the token
     * is upgraded to an accesstoken.
     *
     * @return boolean true if logged in
     */
    public function is_logged_in() {
        // Has the token expired?
        $token = $this->get_accesstoken();
        debugging("Expired: " .
        (isset($token->expires) && time() >= $token->expires)
            ? "NO" : "YES"
        );
        if (isset($token->expires) && time() >= $token->expires) {
            $this->log_out();
            return false;
        }

        // We have a token so we are logged in.
        if (isset($token->token)) {
            return true;
        }

        // This kind of client doesn't support authorization code
        return false;
    }

    public function log_out()
    {
        $this->revoke_token();
        parent::log_out();
    }


    /**
     * @param bool $refresh
     * @return bool
     * @throws moodle_exception
     */
    public function upgrade_token($refresh = false)
    {
        $token = null;
        if (!$this->disable_login_check && (!$this->get_stored_token() || $refresh)) {

            debugging("Token not found in cache");

            $this->disable_login_check = true;

            $params = array(
                'client_id' => $this->get_clientid(),
                'client_secret' => $this->get_clientsecret(),
                'grant_type' => 'client_credentials'
            );

            // clear the current token
            $this->store_token(null);

            // retrieve a new token
            $response = $this->post($this->token_url(), $params);
            $token = json_decode($response);

            // register the new token
            if ($token && isset($token->access_token)) {
                debugging("retrieved token: " . json_encode($token));
                $token->token = $token->access_token;
                $token->expires = (time() + ($token->expires_in - 10)); // Expires 10 seconds before actual expiry.
                $this->store_token($token);
                debugging("Type of retrieve object: " . gettype($token));
                return true;
            } else {
                debugging("Unable to retrieve the authentication token");
                error("Authentication Error !!!");
            }

            $this->disable_login_check = false;

        } else {
            $token = $this->get_stored_token();
            debugging("Token is in SESSION");
            debugging("Type of token object: " . gettype($token));
            return true;
        }

        return false;
    }


    /**
     * Refresh the current token
     */
    protected function refresh_access_token()
    {
        $this->upgrade_token(true);
    }

    public function revoke_token()
    {
        $token = $this->get_accesstoken();
        $params = array(
            'client_id' => $this->get_clientid(),
            'client_secret' => $this->get_clientsecret(),
            'token' => $token->token
        );

        // retrieve a new token
        $response = $this->get($this->revoke_url(), $params);
        debugging("TOKEN Revoked");
    }


    /**
     * Process a request adding the required OAuth token
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    protected function request($url, $options = array())
    {
        if (!$this->disable_login_check) {
            debugging("Is LOGGED: " . ($this->is_logged_in() ? "YES" : "NO"));
            if (!$this->is_logged_in()) {
                if ($this->upgrade_token(false)) {
                    debugging("New TOKEN: " . json_encode($this->get_accesstoken()));
                }
            } else {
                debugging("Old TOKEN: " . json_encode($this->get_accesstoken()));
            }
        }
        return parent::request($url, $options);
    }
}


/**
 * Base class of the helper to access OMERO resources.
 *
 * @since      Moodle 2.0
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */
abstract class OmeroImageRepository extends confidential_oauth2_client
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
     * Reference to the singleton instance of OmeroImageRepository
     * @var OmeroImageRepository
     */
    private static $instance = null;

    /**
     * Returns the default instance of OmeroImageRepository
     * instantiated accordingly to the repository plugin settings
     *
     * @param array $options
     * @return OmeroImageRepository
     */
    public static function get_instance($options = array())
    {
        $api_version = get_config('omero', 'omero_apiversion');
        if (!isset($api_version))
            $api_version = "OmeSeadragonImageRepository";
        if (self::$instance == null)
            self::$instance = new $api_version($options);
        return self::$instance;
    }

    /**
     * Constructor for omero class
     *
     * @param array $options
     */
    function __construct($options = array())
    {
        global $CFG;
        $config = $this->get_config($options);
        parent::__construct(
            $config["oauth_consumer_key"],
            $config["oauth_consumer_secret"],
            new moodle_url("$CFG->wwwroot/moodle/question/question.php"), // FIXME: check the correct callback URL
            "read"
        );
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
     * Returns the list of annotations (Tagsets and Tags).
     *
     * @return mixed
     */
    public abstract function get_annotations();

    /**
     * Find annotations matching the <code>query</code> parameter.
     *
     * @param $query
     * @return mixed
     */
    public abstract function find_annotations($query);

    /**
     * Returns the TagSet with ID <code>tagset_id</code>.
     *
     * @param $tagset_id
     * @param bool $tags
     * @return mixed
     */
    public abstract function get_tagset($tagset_id, $tags = true);

    /**
     * Return the Tag with ID <code>tag_id</code>.
     *
     * @param $tag_id
     * @param bool $images
     * @return mixed
     */
    public abstract function get_tag($tag_id, $images = true);

    /**
     * Returns the list of projects.
     *
     * @return mixed
     */
    public abstract function get_projects();

    /**
     * Returns the Project with ID <code>project_id</code>.
     *
     * @param $project_id
     * @param bool $datasets
     * @return mixed
     */
    public abstract function get_project($project_id, $datasets = true);

    /**
     * Returns the list of datasets related to the project with ID <code>project_id</code>.
     *
     * @param $project_id
     * @param bool $images
     * @return mixed
     */
    public abstract function get_datasets($project_id, $images = true);

    /**
     * Returns the DataSet with ID <code>dataset_id</code>.
     *
     * @param $dataset_id
     * @param bool $images
     * @return mixed
     */
    public abstract function get_dataset($dataset_id, $images = true);

    /**
     * Returns the Image with ID <code>image_id</code>.
     *
     * @param $image_id
     * @param bool $rois
     * @param bool $decode
     * @return mixed
     */
    public abstract function get_image($image_id, $rois = true, $decode = false);

    /**
     * Returns the DZI info of the Image with ID <code>image_id</code>.
     *
     * @param $image_id
     * @param bool $decode
     * @return mixed
     */
    public abstract function get_image_dzi($image_id, $decode = false);

    /**
     * Returns the MPP info of the Image with ID <code>image_id</code>.
     *
     * @param $image_id
     * @param bool $decode
     * @return mixed
     */
    public abstract function get_image_mpp($image_id, $decode = false);

    /**
     * Returns the thumbnail of the Image with ID <code>image_id</code>
     *
     * @param $image_id
     * @param $lastUpdate
     * @param int $height
     * @param int $width
     * @return mixed
     */
    public abstract function get_image_thumbnail($image_id, $height = 128, $width = 128);


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

/**
 * Class OmeSeadragonApi
 *
 * The <code>OmeroImageRepository</code> implementation compliant
 * with the OmeSeadragon interface.
 *
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */
class OmeSeadragonImageRepository extends OmeroImageRepository
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
    public function get_image_mpp($image_id, $decode = false)
    {
        return ($this->do_http_request($this->base_url . "/deepzoom/image_mpp/${image_id}.dzi", $decode));
    }

    public function get_image_thumbnail($image_id, $height = 128, $width = 128)
    {
        return $this->do_http_request($this->base_url . "/deepzoom/get/thumbnail/${image_id}.dzi?size=$height", false);
    }
}


/**
 * The <code>OmeroImageRepository</code> implementation compliant
 * with the OmeSeadragon Gateway interface.
 *
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */
class OmeSeadragonGatewayImageRepository extends OmeroImageRepository
{
    /** @var string */
    protected $base_url;

    public function __construct($options = array())
    {
        parent::__construct($options);
        $this->base_url = $this->repository_server;
    }

    public function get_annotations()
    {
        return $this->do_http_request($this->base_url . "/api/annotations");
    }

    public function find_annotations($query)
    {
        return $this->do_http_request($this->base_url . "/api/annotations/$query");
    }

    public function get_tagset($tagset_id, $tags = true)
    {
        return $this->do_http_request($this->base_url . "/api/tagsets/$tagset_id" . ($tags ? "/tags" : ""));
    }

    public function get_tag($tag_id, $images = true)
    {
        return $this->do_http_request($this->base_url . "/api/tags/$tag_id" . ($images ? "/images" : ""));
    }

    public function get_projects()
    {
        return $this->do_http_request($this->base_url . "/api/projects");
    }

    public function get_project($project_id, $datasets = true)
    {
        return $this->do_http_request($this->base_url . "/api/projects/$project_id" . ($datasets ? "/datasets" : ""));
    }

    public function get_datasets($project_id, $datasets = true)
    {
        return $this->do_http_request($this->base_url . "/api/projects/$project_id" . ($datasets ? "/datasets" : ""));
    }

    public function get_dataset($dataset_id, $images = true)
    {
        return $this->do_http_request($this->base_url . "/api/datasets/$dataset_id" . ($images ? "/images" : ""));
    }

    public function get_image($image_id, $rois = true, $decode = false)
    {
        return $this->do_http_request($this->base_url . "/api/images/$image_id" . ($rois ? "/rois" : ""), $decode);
    }

    public function get_image_dzi($image_id, $decode = false)
    {
    public function get_image_mpp($image_id, $decode = false)
    {
        return $this->do_http_request($this->base_url . "/api/image_mpp/${image_id}", $decode);
    }

    public function get_image_thumbnail($image_id, $height = 128, $width = 128)
    {
        return $this->do_http_request($this->base_url . "/api/thumbnail/${image_id}/$height/png", false);
    }
}


/**
 * Utility class to manage urls to access the OMERO image repository.
 */
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