<?php
/**
 * Created by PhpStorm.
 * User: kikkomep
 * Date: 02/02/16
 * Time: 22:53
 */


// moodle/mod/myplugin/db/caches.php
$definitions = array(
    'thumbnail_cache' => array(
        'mode' => cache_store::MODE_APPLICATION
    ),

    'repository_info_cache' => array(
        'mode' => cache_store::MODE_SESSION
    )
);