<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

define("SETTING_SYSSTATUS_INTERVAL", 10000);

$dbAccess = new DbAccess();

$competition = apc_fetch('competition');
if ($competition === false) {
    $competition = $dbAccess->getCurrentCompetition();
    apc_store('competition', $competition, 30); // Cache for 30 seconds.
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

header('Content-Type: application/json', true);
echo  json_encode($jsonReply);
