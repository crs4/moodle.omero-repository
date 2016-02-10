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
 * Strings for the plugin 'repository_omero', language 'en'
 *
 * @package    repository_omero
 * @copyright  2015-2016 CRS4
 * @license    https://opensource.org/licenses/mit-license.php MIT license
 */

# general
$string['pluginname'] = 'Omero';
$string['configplugin'] = 'Configurazione Repository Omero';
$string['omero_restendpoint'] = 'Ome-Seadragon REST API EndPoint';

# API authentication and authorization
$string['apikey'] = 'omero API key';
$string['omero'] = 'omero';
$string['secret'] = 'omero secret';
# TODO: to be updated
$string['instruction'] = 'You can get your API Key and secret from <pre>OMERO API Endpoint>/apps</pre>.';

# cache definition
$string['cachedef_repository_info_cache'] = "Session cache for queries to the OmeSeadraon Rest API";
$string['cachedef_thumbnail_cache'] = "Application cache to optimize retrieval of omeseadragon thumbnails";

# cache settings
$string['cachelimit'] = 'Cache limit';
$string['cachelimit_info'] = 'Enter the maximum size of files (in bytes) to be cached on server for omero aliases/shortcuts. Cached files will be served when the source is no longer available. Empty value or zero mean caching of all files regardless of size.';

# UI strings
$string['current_image'] = "Current image";
$string['choose_image'] = "Choose an image";
$string['omero:view'] = 'View a omero folder';
$string['projects'] = 'Projects';
$string['project'] = 'Project';
$string['datasets'] = 'DataSets';
$string['dataset'] = 'DataSet';
$string['tagsets'] = 'TagSets';
$string['tagset'] = 'TagSet';
$string['tags'] = 'Tags';
$string['tag'] = 'Tag';
$string['images'] = 'Images';
$string['image'] = 'Image';
