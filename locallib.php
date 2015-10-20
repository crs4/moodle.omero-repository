<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A helper class to access omero resources
 *
 * @since Moodle 2.0
 * @package    repository_omero
 * @copyright  2015 CRS4
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/oauthlib.php');

/**
 * Authentication class to access omero API
 *
 * @package    repository_omero
 * @copyright  2010 Dongsheng Cai
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    function __construct($options)
    {
        parent::__construct($options);
        $this->omero_api = get_config('omero', 'omero_restendpoint');
        $this->omero_content_api = get_config('omero', 'omero_restendpoint');
    }

    /**
     * Get file listing from omero
     *
     * @param string $path
     * @param string $token
     * @param string $secret
     * @return array
     */
    public function get_listing($path = '/', $token = '', $secret = '')
    {
        $url = $this->omero_api . $path;
        $content = $this->get($url, array(), $token, $secret);
        $data = json_decode($content);
        return $data;
    }


    /**
     * Get file listing from omero
     *
     * @param string $path
     * @param string $token
     * @param string $secret
     * @return array
     */
    public function process_request($path = '/', $token = '', $secret = '')
    {
        $url = $this->omero_api . $path;
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
        $url = $this->omero_content_api . '/thumbnails/' . $this->mode . $this->prepare_filepath($filepath);
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


    public function get_thumbnail_url($image_id)
    {
        return $this->omero_api . PathUtils::build_image_thumbnail_url($image_id);
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
        $url = $this->omero_content_api . '/files/' . $this->mode . $this->prepare_filepath($filepath);
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
        return !strcmp($path, "/projects/");
    }

    public static function is_tags_root($path)
    {
        return !strcmp($path, "/tags/");
    }

    public static function is_project($path)
    {
        return preg_match("/proj\/(\d+)\/detail/", $path);
    }

    public static function is_dataset($path)
    {
        return preg_match("/dataset\/(\d+)\/detail/", $path);
    }

    public static function is_image_file($path)
    {
        return preg_match("/imgData\/(\d+)/", $path);
    }

    public static function build_project_list_url()
    {
        return "/proj/list/";
    }

    public static function build_tag_list_url()
    {
        return "/tag/list/";
    }

    public static function build_project_detail_url($project_id)
    {
        return "/proj/$project_id/detail";
    }

    public static function build_dataset_list_url($project_id)
    {
        return "/proj/$project_id/children";
    }

    public static function build_dataset_detail_url($dataset_id)
    {
        return "/dataset/$dataset_id/detail";
    }

    public static function build_image_detail_url($image_id)
    {
        return "/imgData/$image_id";
    }

    public static function build_image_thumbnail_url($image_id)
    {
        return "/render_thumbnail/$image_id";
    }

    public static function build_image_list_url($dataset_id)
    {
        return "/dataset/$dataset_id/children";
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


/**
 * String utility function: check whether a string 'haystack' starts with the string 'needle' or not
 * @param $haystack
 * @param $needle
 * @return bool
 */
function startsWith($haystack, $needle)
{
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

/**
 * String utility function: check whether a string 'haystack' ends with the string 'needle' or not
 * @param $haystack
 * @param $needle
 * @return bool
 */
function endsWith($haystack, $needle)
{
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

/**
 * Return the ID of the object which the '$url' is related to
 * @param $url
 * @return int
 */
function get_omero_item_id_from_url($url)
{
    $result = -1;
    $parts = split("/", $url);
    if (count($parts) > 2) {
        return $parts[2];
    }
    return $result;
}