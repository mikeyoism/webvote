<?php // -*- coding: utf-8 -*-
require_once '../../vote/php/_config.php';
$competitionId = COMPETITION_ID;

require_once '../../vote/php/common.inc';


if (!CONST_SETTING_CONNECT_EVENTREG_DB) {
    //Read from cached files (updted/created in admin-page)
    $dbAccess = new DbAccess();
    $catsAndBeers =  $dbAccess->readEventBeerDataFromCacheFiles($competitionId);
    $categories = $catsAndBeers[0];
    $beers = $catsAndBeers[1];
  
}
else
{
    //read live from database (eventreg)
    $beers = array();
    $dbAccess = new DbAccess();
    
    if (APC_CACHE_ENABLED) {

        $beers = apc_fetch('eventbeers-' . $competitionId);
        if ($beers === false) {
            $beers = $dbAccess->getBeers($competitionId,false);
            apc_store('eventbeers-' . $competitionId, $beers, 120); // Cache for 120 seconds.
        }


        $categories = apc_fetch('categories-' . $competitionId);
        if ($categories === false) {
            $categories = $dbAccess->getCategories($competitionId);
            apc_store('categories-' . $competitionId, $categories, 120); // Cache for 120 seconds.
        }
    } else {
        $beers = $dbAccess->getBeers($competitionId,false);
        $categories = $dbAccess->getCategories($competitionId,true);
    }

}



header('Content-Type: application/json', true);
echo json_encode(array(
    'competition_id' => $competitionId,
    'classes' => $categories,
    'beers' => $beers
));
