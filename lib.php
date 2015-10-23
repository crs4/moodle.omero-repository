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
 * This plugin is used to access user's omero files
 *
 * @since Moodle 2.0
 * @package    repository_omero
 * @copyright  2015 CRS4
 * @author
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/logger.php');

/**
 * Repository to access omero files
 *
 * @package    repository_omero
 * @copyright  2010 Dongsheng Cai
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_omero extends repository
{
    /** @var omero the instance of omero client */
    private $omero;
    /** @var array files */
    public $files;
    /** @var bool flag of login status */
    public $logged = false;
    /** @var int maximum size of file to cache in moodle filepool */
    public $cachelimit = null;

    /** @var int cached file ttl */
    private $cachedfilettl = null;

    /** @var Logger */
    private $logger = null;

    /** item blacklist */
    private $item_black_list = array(
        "Atlante", "Melanomi e nevi", "slide_seminar_CAAP2015",
        "2015-08-11", "TEST"
    );

    private $PROJECTS_ROOT_ITEM = array(
        "name" => "projects",
        "type" => "projects",
        "path" => "/projects"
    );

    private $TAGS_ROOT_ITEM = array(
        "name" => "tags",
        "type" => "tags",
        "path" => "/tags"
    );

    /**
     * Constructor of omero plugin
     *
     * @param int $repositoryid
     * @param stdClass $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array())
    {
        global $CFG;
        $options['page'] = optional_param('p', 1, PARAM_INT);
        parent::__construct($repositoryid, $context, $options);

        $this->setting = 'omero_';

        $this->omero_restendpoint = $this->get_option('omero_restendpoint');
        $this->omero_key = "omero_key"; //FIXME: to restore ---> $this->get_option('omero_key');
        $this->omero_secret = "omero_secret"; // FIXME: to restore --> $this->get_option('omero_secret');

        // one day
        $this->cachedfilettl = 60 * 60 * 24;

        if (isset($options['omero_restendpoint'])) {
            $this->omero_restendpoint = $options['omero_restendpoint'];
        } else {
            $this->omero_restendpoint = get_user_preferences($this->setting . '_omero_restendpoint', '');
        }
        if (isset($options['access_key'])) {
            $this->access_key = $options['access_key'];
        } else {
            $this->access_key = get_user_preferences($this->setting . '_access_key', '');
        }
        if (isset($options['access_secret'])) {
            $this->access_secret = $options['access_secret'];
        } else {
            $this->access_secret = get_user_preferences($this->setting . '_access_secret', '');
        }

        if (!empty($this->access_key) && !empty($this->access_secret)) {
            $this->logged = true;
        }

        $callbackurl = new moodle_url($CFG->wwwroot . '/repository/repository_callback.php', array(
            'callback' => 'yes',
            'repo_id' => $repositoryid
        ));

        $toprint = "";
        foreach ($options as $k => $v) {
            $toprint .= $k;
        }

        error_log("REST ENDPOINT: " . $this->omero_restendpoint . $toprint . "---" . implode(",", $options));

        $args = array(
            'omero_restendpoint' => $this->omero_restendpoint,
            'oauth_consumer_key' => $this->omero_key,
            'oauth_consumer_secret' => $this->omero_secret,
            'oauth_callback' => $callbackurl->out(false),
            'api_root' => $this->omero_restendpoint,
        );

        $this->logger = new Logger("omero-lib");
        $this->omero = new omero($args);
    }

    /**
     * Set access key
     *
     * @param string $access_key
     */
    public function set_access_key($access_key)
    {
        $this->access_key = $access_key;
    }

    /**
     * Set access secret
     *
     * @param string $access_secret
     */
    public function set_access_secret($access_secret)
    {
        $this->access_secret = $access_secret;
    }


    /**
     * Check if moodle has got access token and secret
     *
     * @return bool
     */
    public function check_login()
    {
        //return !empty($this->logged);
        return true; // disabled plugin loigin
    }

    /**
     * Generate omero login url
     *
     * @return array
     */
    public function print_login()
    {
        $result = $this->omero->request_token();
        set_user_preference($this->setting . '_request_secret', $result['oauth_token_secret']);
        $url = $result['authorize_url'];
        if ($this->options['ajax']) {
            $ret = array();
            $popup_btn = new stdClass();
            $popup_btn->type = 'popup';
            $popup_btn->url = $url;
            $ret['login'] = array($popup_btn);
            return $ret;
        } else {
            echo '<a target="_blank" href="' . $url . '">' . get_string('login', 'repository') . '</a>';
        }
    }

    /**
     * Request access token
     *
     * @return array
     */
    public function callback()
    {
        $token = optional_param('oauth_token', '', PARAM_TEXT);
        $secret = get_user_preferences($this->setting . '_request_secret', '');
        $access_token = $this->omero->get_access_token($token, $secret);
        set_user_preference($this->setting . '_access_key', $access_token['oauth_token']);
        set_user_preference($this->setting . '_access_secret', $access_token['oauth_token_secret']);
    }

    /**
     * Get omero files
     *
     * @param string $path
     * @param int $page
     * @return array
     */
    public function get_listing($path = '', $page = '1', $search_text = null)
    {
        global $CFG, $OUTPUT;

        // format the current selected URL
        if (empty($path) || $path == '/') {
            $path = '/';
        } else {
            $path = file_correct_filepath($path);
        }
        $encoded_path = str_replace("%2F", "/", rawurlencode($path));

        $this->logger->debug("Current path: " . $encoded_path . " --- " . $path);

        // Initializes the data structures needed to build the response
        $list = array();
        $list['list'] = array();
        $list['manage'] = 'https://www.omero.com/home';
        $list['dynload'] = true;
        $list['nologin'] = true;
        $list['search_query'] = $search_text;
        $list['tags-set'] = json_decode(file_get_contents("http://10.211.55.7:4789/moodle/repository/omero/tests/tags.json"));
        #$list['logouturl'] = 'https://www.omero.com/logout';
        #$list['message'] = get_string('logoutdesc', 'repository_omero');


        // Host the navigation links
        $navigation_list = array();


        // Enable/Disable the search field
        $list['nosearch'] = false;

        // process search request
        if (isset($search_text)) {
            $this->logger->debug("Searching by TAG !!!");

            $response = $this->omero->process_search($search_text,
                $this->access_key, $this->access_secret);

            foreach ($response as $item) {
                $obj = $this->process_list_item("Tag", $item);
                if ($obj != null)
                    $list['list'][] = $obj;
            }

            // Set this result as a search result
            $list['issearchresult'] = true;

            // Build the navigation bar
            $list['path'] = $this->build_navigation_from_url($navigation_list, "/tags", $search_text);

        } else {

            // true if the list is a search result
            $list['issearchresult'] = false;

            // Build the navigation bar
            $list['path'] = $this->build_navigation_from_url($navigation_list, $path);

            if (PathUtils::is_root_path($path)) {
                $this->logger->debug("The root path has been selected !!!");

                $list['list'][] = $this->process_list_item("ProjectRoot", (object)$this->PROJECTS_ROOT_ITEM);
                $list['list'][] = $this->process_list_item("TagRoot", (object)$this->TAGS_ROOT_ITEM);

            } else if (PathUtils::is_projects_root($path)) {
                $this->logger->debug("The root project path has been selected !!!");

                $response = $this->omero->process_request(PathUtils::build_project_list_url(),
                    $this->access_key, $this->access_secret);

                foreach ($response as $item) {
                    $obj = $this->process_list_item("Project", $item);
                    if ($obj != null)
                        $list['list'][] = $obj;
                }

            } else if (PathUtils::is_tags_root($path)) {
                $this->logger->debug("The root tag path has been selected !!!");

                // TODO: replace the real call
//                $response = $this->omero->process_request(PathUtils::build_tag_list_url(),
//                    $this->access_key, $this->access_secret);
                // TODO: remove mockup call
                $response = json_decode(file_get_contents("http://10.211.55.7:4789/moodle/repository/omero/tests/tags.json"));
                foreach ($response->tags as $item) {
                    $obj = $this->process_list_item("Tag", $item);
                    if ($obj != null) {
                        $list['list'][] = $obj;
                    }
                }

            } else {

                $selected_obj_info = $this->omero->process_request($path, $this->access_key, $this->access_secret);

                if (PathUtils::is_tag($path)) {
                    $this->logger->debug("Tag selected!!!");
                    $response = $selected_obj_info;
                    foreach ($response as $item) {
                        $obj = $this->process_list_item("Image", $item);
                        if ($obj != null)
                            $list['list'][] = $obj;
                    }

                }else if ($this->is_project($selected_obj_info)) {

                    $this->logger->debug("Project selected!!!");
                    $response = $this->omero->process_request(
                        PathUtils::build_dataset_list_url($selected_obj_info->id),
                        $this->access_key, $this->access_secret);
                    foreach ($response as $item) {
                        $obj = $this->process_list_item("Dataset", $item);
                        if ($obj != null)
                            $list['list'][] = $obj;
                    }

                } else if ($this->is_dataset($selected_obj_info)) {

                    $this->logger->debug("Dataset selected!!!");
                    $response = $this->omero->process_request(
                        PathUtils::build_image_list_url($selected_obj_info->id),
                        $this->access_key, $this->access_secret);
                    foreach ($response as $item) {
                        $processed_item = $this->process_list_item("Image", $item, "Series 1");
                        if ($processed_item != null)
                            $list['list'][] = $processed_item;
                    }

                } else if ($this->is_image($selected_obj_info)) {

                    $this->logger->debug("Image selected!!!");
                    $response = $this->omero->process_request(
                        PathUtils::build_image_detail($selected_obj_info->id),
                        $this->access_key, $this->access_secret);

                } else {
                    $this->logger->debug("Unknown resource selected: $path !!!: ". PathUtils::is_tag($path));
                }
            }
        }

        return $list;
    }


    /**
     *
     */
    public function build_navigation_from_url($result, $path, $search_text=false)
    {
        $items = split("/", $path);

        $omero_tag = $_SESSION['omero_tag'];
        $omero_search_text = $_SESSION['$omero_search_text'];
        $omero_project = $_SESSION['omero_project'];
        $omero_dataset = $_SESSION['omero_dataset'];

        if (count($items) == 0 || empty($items[1])) {
            array_push($result, array('name' => "/", 'path' => "/"));
            $_SESSION['omero_tag'] = "";
            $_SESSION['omero_project'] = "";
            $_SESSION['omero_dataset'] = "";
            $_SESSION['$omero_search_text'] = "";

        } else if ($items[1] == "projects") {
            array_push($result, array('name' => "/", 'path' => "/"));
            array_push($result, array('name' => "Projects", 'path' => "/projects"));
            $_SESSION['omero_tag'] = "";
            $_SESSION['omero_project'] = "";
            $_SESSION['omero_dataset'] = "";
            $_SESSION['$omero_search_text'] = "";

        } else if ($items[1] == "tags") {
            array_push($result, array('name' => "/", 'path' => "/"));
            array_push($result, array('name' => "Tags", 'path' => "/tags"));
            if($search_text) {
                array_push($result, array('name' => $search_text, 'path' => "/tag/$search_text"));
                $_SESSION['$omero_search_text'] = $search_text;
            }
            $_SESSION['omero_tag'] = "";
            $_SESSION['omero_project'] = "";
            $_SESSION['omero_dataset'] = "";
            $_SESSION['$omero_search_text'] = "";

        } else if ($items[1] == "tag") {
            array_push($result, array('name' => "/", 'path' => "/"));
            array_push($result, array('name' => "Tags", 'path' => "/tags"));
            //FIXME: $omero_search_text seems to be always empty!!!
            if(isset($omero_search_text) && ! empty($omero_search_text)){
                array_push($result, array('name' => $omero_search_text, 'path' => "/tag/$omero_search_text"));
            }
            array_push($result, array('name' => $items[2], 'path' => $path));
            $_SESSION['omero_project'] = "";
            $_SESSION['omero_dataset'] = "";
            $_SESSION['omero_tag'] = $path;

        } else if ($items[1] == "proj") {
            array_push($result, array('name' => "/", 'path' => "/"));
            array_push($result, array('name' => "Projects", 'path' => "/projects"));
            array_push($result, array(
                    'name' => "Project [" . get_omero_item_id_from_url($path) . "]",
                    'path' => $path)
            );
            $_SESSION['omero_project'] = $path;

        } else if ($items[1] == "dataset") {
            array_push($result, array('name' => "/", 'path' => "/"));
            array_push($result, array('name' => "Projects", 'path' => "/projects"));
            array_push($result, array(
                    'name' => "Project [" . get_omero_item_id_from_url($omero_project) . "]",
                    'path' => $omero_project)
            );
            array_push($result, array(
                    'name' => "DataSet [" . get_omero_item_id_from_url($path) . "]",
                    'path' => $path)
            );
            $_SESSION['omero_dataset'] = $path;
        }

        return $result;
    }


    /**
     * Fill data for a list item
     *
     * @param $type
     * @param $item
     * @param null $filter
     * @return array|null
     */
    public function process_list_item($type, $item, $filter = null)
    {
        global $OUTPUT;

        $path = "/";
        $children = null;
        $thumbnail = null;
        $itemObj = null;
        $image_date = null;
        $image_author = null;

        // Hardwired filter to force only a subset ot datasets
        foreach ($this->item_black_list as $pattern) {
            if (preg_match("/^$pattern$/", $item->name)) {
                return null;
            }
        }

        if ($filter == null || preg_match("/(Series\s1)/", $item->name)) {

            $title = $item->name . " [id:" . $item->id . "]";

            if (strcmp($type, "ProjectRoot") == 0) {
                $title = "Projects";
                $path = "/projects";
                $children = array();
                $thumbnail = $OUTPUT->pix_url(file_folder_icon(64))->out(true);

            } else if (strcmp($type, "TagRoot") == 0) {
                $title = "Tags";
                $path = "/tags";
                $children = array();
                $thumbnail = ($this->file_tag_icon(64));

            } else if (strcmp($type, "Tag") == 0) {
                $path = PathUtils::build_tag_detail_url($item->id);
                $children = array();
                $thumbnail = ($this->file_tag_icon(64));
                $title = $item->value . ": " . $item->description . " [id:" . $item->id . "]";

            } else if (strcmp($type, "Project") == 0) {
                $path = PathUtils::build_project_detail_url($item->id);
                $children = array();
                $thumbnail = $OUTPUT->pix_url(file_folder_icon(64))->out(true);

            } else if (strcmp($type, "Dataset") == 0) {
                $path = PathUtils::build_dataset_detail_url($item->id);
                $children = array();
                $thumbnail = $OUTPUT->pix_url(file_folder_icon(64))->out(true);

            } else if (strcmp($type, "Image") == 0) {
                $path = PathUtils::build_image_detail_url($item->id);
                $thumbnail = $this->omero->get_thumbnail_url($item->id);
                $image_info = $this->omero->process_request(
                    PathUtils::build_image_detail_url($item->id));
                $image_date = $image_info->meta->imageTimestamp;
                $image_author = $image_info->meta->imageAuthor;
            } else
                throw new RuntimeException("Unknown data type");

            $itemObj = array(
                'image_id' => $item->id,
                'title' => $title,
                'author' => $image_author,
                'path' => $path,
                'source' => $item->id,
                'date' => $image_date,
                'thumbnail' => $thumbnail,
                'license' => "",
                'thumbnail_height' => 128,
                'thumbnail_width' => 128,
                'children' => $children
            );

            $this->logger->debug("***");
            $this->logger->debug("fields created ....");
            foreach ($itemObj as $k => $v) {
                if (!is_array($v))
                    $this->logger->debug("$k = $v");
            }
            $this->logger->debug("***");
        }

        return $itemObj;
    }


    public function print_search()
    {
        // The default implementation in class 'repository'
        global $PAGE;
        $renderer = $PAGE->get_renderer('core', 'files');
        return $renderer->repository_default_searchform();
    }

    public function search($search_text, $page = 0)
    {
        return $this->get_listing('', 1, $search_text);
    }

    function get_type($item)
    {
        return $item->type;
    }


    function is_project($item)
    {
        return (strcmp($this->get_type($item), "Project") == 0);
    }


    function is_dataset($item)
    {
        return (strcmp($this->get_type($item), "Dataset") == 0);
    }


    function is_image($item)
    {
        return (strcmp($this->get_type($item), "Image") == 0);
    }


    /**
     * Displays a thumbnail for current user's omero file
     *
     * @param string $string
     */
    public function send_thumbnail($source)
    {
        $this->logger->debug("#### send_thumbnail");

        global $CFG;
        $saveas = $this->prepare_file('');
        try {
            $access_key = get_user_preferences($this->setting . '_access_key', '');
            $access_secret = get_user_preferences($this->setting . '_access_secret', '');
            $this->omero->set_access_token($access_key, $access_secret);
            $this->omero->get_thumbnail($source, $saveas, $CFG->repositorysyncimagetimeout);
            $content = file_get_contents($saveas);
            unlink($saveas);
            // set 30 days lifetime for the image. If the image is changed in omero it will have
            // different revision number and URL will be different. It is completely safe
            // to cache thumbnail in the browser for a long time
            send_file($content, basename($source), 30 * 24 * 60 * 60, 0, true);
        } catch (Exception $e) {
        }
    }

    /**
     * Logout from omero
     * @return array
     */
    public function logout()
    {
        set_user_preference($this->setting . '_access_key', '');
        set_user_preference($this->setting . '_access_secret', '');
        $this->access_key = '';
        $this->access_secret = '';
        return $this->print_login();
    }

    /**
     * Set omero option
     * @param array $options
     * @return mixed
     */
    public function set_option($options = array())
    {
        if (!empty($options['omero_restendpoint'])) {
            set_config('omero_restendpoint', trim($options['omero_restendpoint']), 'omero');
        }
        if (!empty($options['omero_key'])) {
            set_config('omero_key', trim($options['omero_key']), 'omero');
        }
        if (!empty($options['omero_secret'])) {
            set_config('omero_secret', trim($options['omero_secret']), 'omero');
        }
        if (!empty($options['omero_cachelimit'])) {
            $this->cachelimit = (int)trim($options['omero_cachelimit']);
            set_config('omero_cachelimit', $this->cachelimit, 'omero');
        }

        //unset($options['omero_restendpoint']);
        unset($options['omero_key']);
        unset($options['omero_secret']);
        unset($options['omero_cachelimit']);
        $ret = parent::set_option($options);
        return $ret;
    }

    /**
     * Get omero options
     * @param string $config
     * @return mixed
     */
    public function get_option($config = '')
    {
        if ($config === 'omero_key') {
            return trim(get_config('omero', 'omero_key'));
        } elseif ($config === 'omero_secret') {
            return trim(get_config('omero', 'omero_secret'));
        } elseif ($config === 'omero_cachelimit') {
            return $this->max_cache_bytes();
        } else {
            $options = parent::get_option();
            $options['omero_key'] = trim(get_config('omero', 'omero_key'));
            $options['omero_secret'] = trim(get_config('omero', 'omero_secret'));
            $options['omero_cachelimit'] = $this->max_cache_bytes();
        }
        return $options;
    }

    /**
     * Fixes references in DB that contains user credentials
     *
     * @param string $reference contents of DB field files_reference.reference
     */
    public function fix_old_style_reference($reference)
    {
        global $CFG;
        $ref = unserialize($reference);
        if (!isset($ref->url)) {
            $this->omero->set_access_token($ref->access_key, $ref->access_secret);
            $ref->url = $this->omero->get_file_share_link($ref->path, $CFG->repositorygetfiletimeout);
            if (!$ref->url) {
                // some error occurred, do not fix reference for now
                return $reference;
            }
        }
        unset($ref->access_key);
        unset($ref->access_secret);
        $newreference = serialize($ref);
        if ($newreference !== $reference) {
            // we need to update references in the database
            global $DB;
            $params = array(
                'newreference' => $newreference,
                'newhash' => sha1($newreference),
                'reference' => $reference,
                'hash' => sha1($reference),
                'repoid' => $this->id
            );
            $refid = $DB->get_field_sql('SELECT id FROM {files_reference}
                WHERE reference = :reference AND referencehash = :hash
                AND repositoryid = :repoid', $params);
            if (!$refid) {
                return $newreference;
            }
            $existingrefid = $DB->get_field_sql('SELECT id FROM {files_reference}
                    WHERE reference = :newreference AND referencehash = :newhash
                    AND repositoryid = :repoid', $params);
            if ($existingrefid) {
                // the same reference already exists, we unlink all files from it,
                // link them to the current reference and remove the old one
                $DB->execute('UPDATE {files} SET referencefileid = :refid
                    WHERE referencefileid = :existingrefid',
                    array('refid' => $refid, 'existingrefid' => $existingrefid));
                $DB->delete_records('files_reference', array('id' => $existingrefid));
            }
            // update the reference
            $params['refid'] = $refid;
            $DB->execute('UPDATE {files_reference}
                SET reference = :newreference, referencehash = :newhash
                WHERE id = :refid', $params);
        }
        return $newreference;
    }

    /**
     * Converts a URL received from omero API function 'shares' into URL that
     * can be used to download/access file directly
     *
     * @param string $sharedurl
     * @return string
     */
    private function get_file_download_link($sharedurl)
    {
        return preg_replace('|^(\w*://)www(.omero.com)|', '\1dl\2', $sharedurl);
    }

    /**
     * Downloads a file from external repository and saves it in temp dir
     *
     * @throws moodle_exception when file could not be downloaded
     *
     * @param string $reference the content of files.reference field or result of
     * function {@link repository_omero::get_file_reference()}
     * @param string $saveas filename (without path) to save the downloaded file in the
     * temporary directory, if omitted or file already exists the new filename will be generated
     * @return array with elements:
     *   path: internal location of the file
     *   url: URL to the source (from parameters)
     */
    public function get_file($reference, $saveas = '')
    {

        $this->logger->debug("### get_file ###");

        global $CFG;
        $ref = unserialize($reference);
        $saveas = $this->prepare_file($saveas);
        if (isset($ref->access_key) && isset($ref->access_secret) && isset($ref->path)) {
            $this->omero->set_access_token($ref->access_key, $ref->access_secret);
            return $this->omero->get_file($ref->path, $saveas, $CFG->repositorygetfiletimeout);
        } else if (isset($ref->url)) {
            $c = new curl;
            $url = $this->get_file_download_link($ref->url);
            $result = $c->download_one($url, null, array('filepath' => $saveas, 'timeout' => $CFG->repositorygetfiletimeout, 'followlocation' => true));
            $info = $c->get_info();
            if ($result !== true || !isset($info['http_code']) || $info['http_code'] != 200) {
                throw new moodle_exception('errorwhiledownload', 'repository', '', $result);
            }
            return array('path' => $saveas, 'url' => $url);
        }
        throw new moodle_exception('cannotdownload', 'repository');
    }

    /**
     * Add Plugin settings input to Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository')
    {
        global $CFG;
        parent::type_config_form($mform);
        $endpoint = get_config('omero', 'omero_restendpoint');
        $key = get_config('omero', 'omero_key');
        $secret = get_config('omero', 'omero_secret');

        if (empty($endpoint)) {
            $endpoint = 'http://omero.crs4.it:8080/webgateway';
        }
        if (empty($key)) {
            $key = '';
        }
        if (empty($secret)) {
            $secret = '';
        }

        $strrequired = get_string('required');

        $mform->addElement('text', 'omero_restendpoint', get_string('omero_restendpoint', 'repository_omero'), array('value' => $endpoint, 'size' => '80'));
        $mform->setType('omero_restendpoint', PARAM_RAW_TRIMMED);

        $mform->addElement('text', 'omero_key', get_string('apikey', 'repository_omero'), array('value' => $key, 'size' => '40'));
        $mform->setType('omero_key', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'omero_secret', get_string('secret', 'repository_omero'), array('value' => $secret, 'size' => '40'));

        $mform->addRule('omero_key', $strrequired, 'required', null, 'client');
        $mform->addRule('omero_secret', $strrequired, 'required', null, 'client');
        $mform->setType('omero_secret', PARAM_RAW_TRIMMED);
        $str_getkey = get_string('instruction', 'repository_omero');
        $mform->addElement('static', null, '', $str_getkey);

        $mform->addElement('text', 'omero_cachelimit', get_string('cachelimit', 'repository_omero'), array('size' => '40'));
        $mform->addRule('omero_cachelimit', null, 'numeric', null, 'client');
        $mform->setType('omero_cachelimit', PARAM_INT);
        $mform->addElement('static', 'omero_cachelimit_info', '', get_string('cachelimit_info', 'repository_omero'));
    }

    /**
     * Option names of omero plugin
     *
     * @return array
     */
    public static function get_type_option_names()
    {
        return array('omero_restendpoint', 'omero_key', 'omero_secret', 'pluginname', 'omero_cachelimit');
    }

    /**
     * omero plugin supports all kinds of files
     *
     * @return array
     */
    public function supported_filetypes()
    {
        return array('image/png');
    }

    /**
     * User cannot use the external link to omero
     *
     * @return int
     */
    public function supported_returntypes()
    {
        return /*FILE_INTERNAL |*/
            //FILE_REFERENCE |
            FILE_EXTERNAL;
    }

    /**
     * Return file URL for external link
     *
     * @param string $reference the result of get_file_reference()
     * @return string
     */
    public function get_link($reference)
    {
        global $CFG;

        $this->logger->debug("get_link called: : $reference !!!");

        $ref = unserialize($reference);
        foreach ($ref as $k => $v)
            $this->logger->debug("$k ---> $v");

        if (!isset($ref->url)) {
            $this->omero->set_access_token($ref->access_key, $ref->access_secret);
            $ref->url = $this->omero->get_file_share_link($ref->path, $CFG->repositorygetfiletimeout);
        }

        $image_id = preg_replace("/\/render_thumbnail\/(\d+)/", "$1", $ref->path);
        $res = $this->omero->get_thumbnail_url($image_id);
        $this->logger->debug("RES: " . $res);
        return $res;
    }

    /**
     * Prepare file reference information
     *
     * @param string $source
     * @return string file referece
     */
    public function get_file_reference($source)
    {
        $this->logger->debug("---> Calling 'get_file_reference' <---");

        $this->logger->debug("SOURCE: $source");

        global $USER, $CFG;
        $reference = new stdClass;
        $reference->path = "$this->omero_restendpoint/render_thumbnail/$source"; // FIXME: static URL
        $reference->userid = $USER->id;
        $reference->username = fullname($USER);
        $reference->access_key = get_user_preferences($this->setting . '_access_key', '');
        $reference->access_secret = get_user_preferences($this->setting . '_access_secret', '');

        // by API we don't know if we need this reference to just download a file from omero
        // into moodle filepool or create a reference. Since we need to create a shared link
        // only in case of reference we analyze the script parameter
        $usefilereference = optional_param('usefilereference', false, PARAM_BOOL);
        if ($usefilereference) {
            $this->logger->debug("Computing reference: $usefilereference");
            $this->omero->set_access_token($reference->access_key, $reference->access_secret);
            $url = $this->omero->get_file_share_link($source, $CFG->repositorygetfiletimeout);
            if ($url) {
                unset($reference->access_key);
                unset($reference->access_secret);
                $reference->url = "$this->omero_restendpoint/webclient/img_detail/$source"; // FIXME: static URL
                $this->logger->debug("Computed reference: " . $reference->url);
            }
        }
        return serialize($reference);
    }

    public function sync_reference(stored_file $file)
    {
        $this->logger->debug("---> Calling 'sync_reference' <---");

        global $CFG;

        if ($file->get_referencelastsync() + DAYSECS > time()) {
            // Synchronise not more often than once a day.
            return false;
        }
        $ref = unserialize($file->get_reference());
        if (!isset($ref->url)) {
            // this is an old-style reference in DB. We need to fix it
            $ref = unserialize($this->fix_old_style_reference($file->get_reference()));
        }
        if (!isset($ref->url)) {
            return false;
        }
        $c = new curl;
        $url = $this->get_file_download_link($ref->url);
        if (file_extension_in_typegroup($ref->path, 'web_image')) {
            $saveas = $this->prepare_file('');
            try {
                $result = $c->download_one($url, array(),
                    array('filepath' => $saveas,
                        'timeout' => $CFG->repositorysyncimagetimeout,
                        'followlocation' => true));
                $info = $c->get_info();
                if ($result === true && isset($info['http_code']) && $info['http_code'] == 200) {
                    $fs = get_file_storage();
                    list($contenthash, $filesize, $newfile) = $fs->add_file_to_pool($saveas);
                    $file->set_synchronized($contenthash, $filesize);
                    return true;
                }
            } catch (Exception $e) {
            }
        }
        $c->get($url, null, array('timeout' => $CFG->repositorysyncimagetimeout, 'followlocation' => true, 'nobody' => true));
        $info = $c->get_info();
        if (isset($info['http_code']) && $info['http_code'] == 200 &&
            array_key_exists('download_content_length', $info) &&
            $info['download_content_length'] >= 0
        ) {
            $filesize = (int)$info['download_content_length'];
            $file->set_synchronized(null, $filesize);
            return true;
        }
        $file->set_missingsource();
        return true;
    }

    /**
     * Cache file from external repository by reference
     *
     * omero repository regularly caches all external files that are smaller than
     * {@link repository_omero::max_cache_bytes()}
     *
     * @param string $reference this reference is generated by
     *                          repository::get_file_reference()
     * @param stored_file $storedfile created file reference
     */
    public function cache_file_by_reference($reference, $storedfile)
    {
        $this->logger->debug("---> Calling 'cache_file_by_reference' <---");

        try {
            $this->import_external_file_contents($storedfile, $this->max_cache_bytes());
        } catch (Exception $e) {
        }
    }

    /**
     * Return human readable reference information
     * {@link stored_file::get_reference()}
     *
     * @param string $reference
     * @param int $filestatus status of the file, 0 - ok, 666 - source missing
     * @return string
     */
    public function get_reference_details($reference, $filestatus = 0)
    {
        $this->logger->debug("---> Calling 'get_reference_details' <---");
        global $USER;
        $ref = unserialize($reference);
        $detailsprefix = $this->get_name();
        if (isset($ref->userid) && $ref->userid != $USER->id && isset($ref->username)) {
            $detailsprefix .= ' (' . $ref->username . ')';
        }
        $details = $detailsprefix;
        if (isset($ref->path)) {
            $details .= ': ' . $ref->path;
        }
        if (isset($ref->path) && !$filestatus) {
            // Indicate this is from omero with path
            return $details;
        } else {
            if (isset($ref->url)) {
                $details = $detailsprefix . ': ' . $ref->url;
            }
            return get_string('lostsource', 'repository', $details);
        }
    }

    /**
     * Return the source information
     *
     * @param string $source
     * @return string
     */
    public function get_file_source_info($source)
    {
        global $USER;
        return 'omero (' . fullname($USER) . '): ' . $source;
    }

    /**
     * Returns the maximum size of the omero files to cache in moodle
     *
     * Note that {@link repository_omero::sync_reference()} will try to cache images even
     * when they are bigger in order to generate thumbnails. However there is
     * a small timeout for downloading images for synchronisation and it will
     * probably fail if the image is too big.
     *
     * @return int
     */
    public function max_cache_bytes()
    {
        if ($this->cachelimit === null) {
            $this->cachelimit = (int)get_config('omero', 'omero_cachelimit');
        }
        return $this->cachelimit;
    }

    /**
     * Repository method to serve the referenced file
     *
     * This method is ivoked from {@link send_stored_file()}.
     * omero repository first caches the file by reading it into temporary folder and then
     * serves from there.
     *
     * @param stored_file $storedfile the file that contains the reference
     * @param int $lifetime Number of seconds before the file should expire from caches (null means $CFG->filelifetime)
     * @param int $filter 0 (default)=no filtering, 1=all files, 2=html files only
     * @param bool $forcedownload If true (default false), forces download of file rather than view in browser/plugin
     * @param array $options additional options affecting the file serving
     */
    public function send_file($storedfile, $lifetime = null, $filter = 0, $forcedownload = false, array $options = null)
    {
        $this->logger->debug("---> Calling 'send_file' <---");

        $ref = unserialize($storedfile->get_reference());
        if ($storedfile->get_filesize() > $this->max_cache_bytes()) {
            header('Location: ' . $this->get_file_download_link($ref->url));
            die;
        }
        try {
            $this->import_external_file_contents($storedfile, $this->max_cache_bytes());
            if (!is_array($options)) {
                $options = array();
            }
            $options['sendcachedexternalfile'] = true;
            send_stored_file($storedfile, $lifetime, $filter, $forcedownload, $options);
        } catch (moodle_exception $e) {
            // redirect to omero, it will show the error.
            // We redirect to omero shared link, not to download link here!
            header('Location: ' . $ref->url);
            die;
        }
    }

    /**
     * Caches all references to omero files in moodle filepool
     *
     * Invoked by {@link repository_omero_cron()}. Only files smaller than
     * {@link repository_omero::max_cache_bytes()} and only files which
     * synchronisation timeout have not expired are cached.
     */
    public function cron()
    {
        $fs = get_file_storage();
        $files = $fs->get_external_files($this->id);
        foreach ($files as $file) {
            try {
                // This call will cache all files that are smaller than max_cache_bytes()
                // and synchronise file size of all others
                $this->import_external_file_contents($file, $this->max_cache_bytes());
            } catch (moodle_exception $e) {
            }
        }
    }


    /**
     * Return the relative icon path for a folder image
     *
     * Usage:
     * <code>
     * $icon = $OUTPUT->pix_url(file_folder_icon())->out();
     * echo html_writer::empty_tag('img', array('src' => $icon));
     * </code>
     * or
     * <code>
     * echo $OUTPUT->pix_icon(file_folder_icon(32));
     * </code>
     *
     * @param int $iconsize The size of the icon. Defaults to 16 can also be 24, 32, 48, 64, 72, 80, 96, 128, 256
     * @return string
     */
    function file_tag_icon($iconsize = null)
    {
        global $CFG;

        static $iconpostfixes = array(256 => '-256', 128 => '-128', 96 => '-96', 80 => '-80', 72 => '-72', 64 => '-64', 48 => '-48', 32 => '-32', 24 => '-24', 16 => '');
        static $cached = array();
        $iconsize = max(array(16, (int)$iconsize));
        if (!array_key_exists($iconsize, $cached)) {
            foreach ($iconpostfixes as $size => $postfix) {
                $fullname = $CFG->wwwroot . "/repository/omero/pix/tag/$iconsize.png";
                return $fullname;
                if ($iconsize >= $size && (file_exists($fullname)))
                    return $fullname;
//                if ($iconsize >= $size && (file_exists($fullname.'.png') || file_exists($fullname.'.gif'))) {
//                    $cached[$iconsize] = 'f/tag'.$postfix;
//                    break;
//                }
            }
        }
        return $cached[$iconsize];
    }
}

