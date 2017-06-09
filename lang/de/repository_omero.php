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
$string['configplugin'] = 'Omero Speicherkonfiguration';
$string['omero_server'] = 'OmeSeadragon Server';
$string['omero_restendpoint'] = 'OmeSeadragon REST API EndPoint';
$string['omero_webclient'] = 'Omero Web Client URL';


# API authentication and authorization
$string['omero'] = 'omero';
$string['apiversion'] = 'OmeSeadragon API Version';
$string['apikey'] = 'OmeSeadragon API Schlüssel';
$string['apisecret'] = 'OmeSeadragon API Code';
# TODO: to be updated
$string['instruction'] = 'Du bekommst deinen API Schlüssel und Code von: <pre>OMERO API Endpoint>/apps</pre>';

# cache definition
$string['cachedef_repository_info_cache'] = "Sitzungsspeicher bei Rückfragen an OmeSeadraon Rest API";
$string['cachedef_thumbnail_cache'] = "Anwendungsspeicher um die Suche von omeseadragon thumbnails zu optimieren";

# cache settings
$string['cachelimit'] = 'Speicherkapazität';
$string['cachelimit_info'] = 'Für die Speicherung von omero Dateiverknüpfungen/Tastenkombinationen am Server die Maximalgröße (in Bytes) angeben. Dateien werden auch gespeichert wenn die Bezugsquelle nicht mehr verfügbar ist. 
Fehlende Werte oder 0 bedeuten, dass alle Dateien größenunabhängig gespeichert werden.';

# UI strings
$string['current_image'] = "Aktuelles Bild";
$string['choose_image'] = "Wähle ein Bild";
$string['omero:view'] = 'Aufrufen des omero Ordners';
$string['projects'] = 'Projects';
$string['project'] = 'Project';
$string['datasets'] = 'DataSets';
$string['dataset'] = 'DataSet';
$string['tagsets'] = 'TagSets';
$string['tagset'] = 'TagSet';
$string['tags'] = 'Tags';
$string['tag'] = 'Tag';
$string['images'] = 'Bilder';
$string['image'] = 'Bild';