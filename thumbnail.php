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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$repo_id   = optional_param('repo_id', 0, PARAM_INT);           // Repository ID
$contextid = optional_param('ctx_id', SYSCONTEXTID, PARAM_INT); // Context ID
$source    = optional_param('source', '', PARAM_TEXT);          // File path in current user's omero

if (isloggedin() && $repo_id && $source
        && ($repo = repository::get_repository_by_id($repo_id, $contextid))
        && method_exists($repo, 'send_thumbnail')) {
    // try requesting thumbnail and outputting it. This function exits if thumbnail was retrieved
    $repo->send_thumbnail($source);
}

// send default icon for the file type
$fileicon = file_extension_icon($source, 64);
send_file($CFG->dirroot.'/pix/'.$fileicon.'.png', basename($fileicon).'.png');
