<?php // -*- coding: utf-8 -*-
require_once '../../vote/php/common.inc';
$jsonReply = array();
$jsonReply['beers'] = null;
$jsonReply['usrmsg'] = null;


$dbAccess = new DbAccess();
$competitionId = getCompetitionId(); 
$competition = $dbAccess->getCompetition($competitionId);
$openTimes = dbAccess::calcCompetitionTimes($competition);

$voteCountStartTime = $openTimes['voteCountStartTime']; 

//get categories
$categories = $dbAccess->getCategories($competitionId);
$beerCounts = array();
$ratingCount = array();
$ratingCountTotal = 0;
$drankCount = array();
$drankcountTotal = 0;
foreach ($categories as $category){
    $beerCounts[$category['id']] = $dbAccess->getBeerCountForCategory($category['id']);
    //get number of beers rated by all users
    $ratingCount[$category['id']] = $dbAccess->getRatingCount($category['id'],$voteCountStartTime);
    $ratingCountTotal += $ratingCount[$category['id']];
    $drankCount[$category['id']] = $dbAccess->getDrankCheckCount($category['id'],null);
    $drankcountTotal += $drankCount[$category['id']];
}


$ratingCount = $dbAccess->getRatingCount($competitionId,$voteCountStartTime);


$jsonReply['usrmsg'] = 'OK';
$jsonReply['openCloseText'] = $openTimes['openCloseText'];
$jsonReply['ratingCount'] = $ratingCount;
$jsonReply['ratingCountTotal'] = $ratingCountTotal;
$jsonReply['drankCount'] = $drankCount;
$jsonReply['drankCountTotal'] = $drankcountTotal;

header('Content-Type: application/json', true);
echo json_encode($jsonReply);
