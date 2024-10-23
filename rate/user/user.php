<?php // -*- coding: utf-8 -*-
$domain = explode('.',$_SERVER['HTTP_HOST']);
$len =count($domain);
//set session across subdomains (to fetch session from event.shbf.se)
if ($len >= 3){
  $some_name = session_name("regrate");
  session_set_cookie_params(0, '/', '.'. $domain[$len-2] . '.' . $domain[$len-1]); //like '.shbf.se'
}
session_start();
//print_r($_SESSION);
//die();
require_once '../../vote/php/common.inc';
$jsonReply = array();
$jsonReply['beers'] = null;
$jsonReply['usrmsg'] = null;


//logged in from event.shbf.se?
if (isset($_SESSION['user_name']) && strlen($_SESSION['user_name']) > 1 && isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0){
    //die("user set");
    $event_username = $_SESSION['user_name'];
    $event_user_id = $_SESSION['user_id'];
    $privilegeLevel = 0; //viewer
    $fv_event_id = $_SESSION['fv_event_id'];
    $et_event_id = $_SESSION['et_event_id'];
    
    //if competition is set in session, use it (might not be current competition)
    if (isset($_SESSION['fv_votesys_competition_id'])){
        $competitionId = $_SESSION['fv_votesys_competition_id'];
    }
    else
    {
        //get current competition
        $dbAccess = new DbAccess();
        $competitionId = getCompetitionId(); //Testing only
        
    }
} else {
    //print("user_name=".$_SESSION['user_name']);
    //print("user_id=".$_SESSION['user_id']);
    //die();
    $event_username = null;
    $event_user_id = null;
    $privilegeLevel = null;

    
    $jsonReply['usrmsg'] = 'Du är inte inloggad, logga in på event.shbf.se ';
    header('Content-Type: application/json', true);
    echo json_encode($jsonReply);
    die();    
}
//todo: local login option against eventreg db?

$dbAccess = new DbAccess();

$competition = $dbAccess->getCompetition($competitionId);
$openTimes = dbAccess::calcCompetitionTimes($competition);

if ($openTimes['brewerLoginOpen'] !== true){
    $jsonReply['usrmsg'] = 'Inloggning ej öppen, den öppnar ' . date_format($openTimes['brewerLoginOpenFrom'],'Y-m-d H:i:s');
    header('Content-Type: application/json', true);
    echo json_encode($jsonReply);    
    die();
}

$voteCountStartTime = $openTimes['voteCountStartTime']; //typically null

//get beers by event_username

$beers = $dbAccess->getBeersByBrewer($competitionId,null/*$fv_event_id*/,$event_user_id);
if ($beers === null){
    $jsonReply['usrmsg'] = 'Inga öl hittades för användare ' . $event_username;
    header('Content-Type: application/json', true);
    echo json_encode($jsonReply);
    die();
} 
//for each beer, get rating average, count and comments

foreach ($beers as $key => $beer){
    $ratingCount = $dbAccess->getRatingCountForEntryCode($beer['votesys_category'],$beer['entry_code'],$voteCountStartTime);
    $ratingComments = $dbAccess->getCommentsForEntryCode($beer['votesys_category'],$beer['entry_code'],null);
    $startCounts = $dbAccess->getRatingScoreCountForEntryCode($beer['votesys_category'],$beer['entry_code'],$voteCountStartTime);
    $drankCount = $dbAccess->getDrankCheckCountForEntryCode($beer['votesys_category'],$beer['entry_code'],null);

    //add to $beer
    $beer['ratingCount'] = $ratingCount;
    $beer['ratingComments'] = $ratingComments;
    $beer['starCounts'] = $startCounts;
    $beer['drankCount'] = $drankCount;
    $beer['competitionName'] = $competition['name'];
    //update $beers
    $beers[$key] = $beer;



    
}


$jsonReply['beers'] = $beers;
$jsonReply['usrmsg'] = 'OK';
header('Content-Type: application/json', true);
echo json_encode($jsonReply);
