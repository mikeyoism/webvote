<?php // -*- coding: utf-8 -*-
require_once '../../vote/php/_config.php';
require_once '../../vote/php/common.inc';

$competitionId = getCompetitionId();
$dbAccess = new DbAccess();

if (!CONNECT_EVENTREG_DB) {
    //Read from cached files (updated/created in admin-page)
    
    $catsAndBeers =  $dbAccess->readEventBeerDataFromCacheFiles($competitionId);
    $categories = $catsAndBeers[0];
    $beers = $catsAndBeers[1];
  
}
else
{
    //read live from database (eventreg)
    $beers = array();
    
    
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

$styles = $dbAccess->getBeerStyleGuides($competitionId);
if (!is_array($styles)) {
    $styles = array();
}


header('Content-Type: application/json', true);
echo json_encode(array(
    'competition_id' => $competitionId,
    'classes' => $categories,
    'beers' => $beers,
    'styles' => $styles
));
