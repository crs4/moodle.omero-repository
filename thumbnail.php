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
