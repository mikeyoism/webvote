<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

//define("SETTING_SYSSTATUS_INTERVAL", 10000);
//post
$voteArgs = json_decode(file_get_contents('php://input'));
if (!isset($voteArgs->competition_id)) {
    die('competition_id missing');
}
$competitionId = $voteArgs->competition_id;
if (!preg_match('/^[1-9][0-9]{0,4}$/', $competitionId)) {
    die('competition_id non-numeric');
}

$dbAccess = new DbAccess();

if (APC_CACHE_ENABLED) {
    $competition = apc_fetch('competition-' . $competitionId);
    if ($competition === false) {
        $competition = $dbAccess->getCompetition($competitionId);
     apc_store('competition-' . $competitionId, $competition, 30); // Cache for 30 seconds.
    }
} else {
    $competition = $dbAccess->getCompetition($competitionId);
}

$openTimes = dbAccess::calcCompetitionTimes($competition);

$jsonReply = array();
$jsonReply['update_interval'] = SETTING_SYSSTATUS_INTERVAL;

$jsonReply['competition_id'] = $competition['id'];
$jsonReply['competition_name'] = $competition['name'];
$jsonReply['competition_open'] = $openTimes['open'];
$jsonReply['competition_status'] = $openTimes['openCloseText'];
$jsonReply['competition_seconds_to_open'] = $competition['openTime']->getTimeStamp() - (new DateTime())->getTimeStamp();
$jsonReply['competition_seconds_to_close'] = $competition['closeTime']->getTimeStamp() - (new DateTime())->getTimeStamp();
$jsonReply['competition_closes_hhmm'] = $competition['closeTime']->format('H:i');
$jsonReply['ENABLE_RATING'] = ENABLE_RATING;

header('Content-Type: application/json', true);
echo  json_encode($jsonReply);
