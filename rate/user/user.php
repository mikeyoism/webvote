<?php // -*- coding: utf-8 -*-
$domain = explode('.',$_SERVER['HTTP_HOST']);
$len =count($domain);
//set session across subdomains (to fetch session from event.shbf.se)
if ($len >= 3){
  $some_name = session_name("regrate");
  session_set_cookie_params(0, '/', '.'. $domain[$len-2] . '.' . $domain[$len-1]); //like '.shbf.se'
}
session_start();
require_once '../../vote/php/common.inc';

$competitionIdFV = getCompetitionId(); //Testing only
//logged in from event.shbf.se?
if (isset($_SESSION['user_name']) && strlen($_SESSION['user_name'] > 1 && isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0)){
    $event_username = $_SESSION['user_name'];
    $event_user_id = $_SESSION['user_id'];
    $privilegeLevel = 0; //viewer
    $fv_event_id = $_SESSION['fv_event_id'];
    $et_event_id = $_SESSION['et_event_id'];
    if (isset($_SESSION['fv_votesys_competition_id'])){
        $competitionIdFV = $_SESSION['fv_votesys_competition_id'];
    }
    //etikett
    if (isset($_SESSION['et_votesys_competition_id'])){
        $competitionIdET = $_SESSION['et_votesys_competition_id'];
    }
} else {
    $event_username = null;
    $event_user_id = null;
    $privilegeLevel = null;

    //$competitionIdFV = getCompetitionId();
    die('Not logged in');
}
//todo: local login option against eventreg db?



//post
$voteArgs = json_decode(file_get_contents('php://input'));

$dbAccess = new DbAccess();

$competition = $dbAccess->getCompetition($competitionIdFV);
$openTimes = dbAccess::calcCompetitionTimes($competition);
$voteCountStartTime = $openTimes['voteCountStartTime']; //typically null

//get beers by event_username

$beers = $dbAccess->getBeersByBrewer($competitionIdFV,$fv_event_id,$event_user_id);

//for each beer, get rating average, count and comments

foreach ($beers as $key => $beer){
    $ratingCount = $dbAccess->getRatingCountForEntryCode($beer['entry_code'],$voteCountStartTime);
    $ratingComments = $dbAccess->getCommentsForEntryCode($beer['entry_code'],null);
    $startCounts = $dbAccess->getRatingScoreCountForEntryCode($beer['entry_code'],$voteCountStartTime);
    $drankCount = $dbAccess->getDrankCheckCountForEntryCode($beer['entry_code'],null);
    //add to $beer
    $beer['ratingCount'] = $ratingCount;
    $beer['ratingComments'] = $ratingComments;
    $beer['starCounts'] = $startCounts;
    $beer['drankCount'] = $drankCount;
    $beer['competitionName'] = $competition['name'];
    //update $beers
    $beers[$key] = $beer;



    
}


header('Content-Type: application/json', true);
echo json_encode($beers);
