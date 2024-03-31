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
//distinction between rating and legacy voting
$isRateSystem = true;
if (!isset($voteArgs->isRateSystem)) {
    $isRateSystem = false;
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
$VotingAsRating = ENABLE_VOTING_AS_RATING;
if (!$isRateSystem &&  $openTimes['open'] == true) {
    //legacy voting, överskrid om det är tillåtet eller ej
    if (!ENABLE_VOTING && !ENABLE_VOTING_AS_RATING) {
        //informera om nya betygssystemet
        if (ENABLE_RATING)
            $openTimes['openCloseText'] = "Röstningen är inaktiverad. Vi har bytt till ett nytt röstsystem. Surfa istället till <a href='https://rate.shbf.se'>rate.shbf.se</a> för att rösta.";

    }
    else if (ENABLE_VOTING_AS_RATING == true) {
        $resetVoteCode = isset($_SESSION['public_vote_terminal']) && $_SESSION['public_vote_terminal'] === true;
        if ($resetVoteCode)
            $openTimes['openCloseText'] = "Röstningen är Öppen. Här kan du ge poäng till dina 3 favoriter per klass. OBS: Betygsättning är endast möjlig i nya betygsystemet, betyg i nya systmet nollställs om du röstar med denna sida!.";
        else if (ENABLE_VOTING_AS_RATING_TERMINAL_ONLY == true){
            $openTimes['openCloseText'] = "Röstningen är inkativerad. Endast terminaler kan rösta i detta system. Vi har bytt till ett nytt röstsystem. Surfa istället till <a href='https://rate.shbf.se'>rate.shbf.se</a> för att rösta.";
            $VotingAsRating = false;
        }else
            $openTimes['openCloseText'] = "Röstningen är öppen. Här kan du ge poäng till dina 3 favoriter per klass. OBS: Betygsättning är endast möjlig i nya betygsystemet <a href='https://rate.shbf.se'>rate.shbf.se</a>, betyg i nya systmet nollställs om du röstar med denna sida!";   
    }

}

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
$jsonReply['ENABLE_VOTING'] = ENABLE_VOTING;
$jsonReply['ENABLE_VOTING_AS_RATING'] = $VotingAsRating;

header('Content-Type: application/json', true);
echo  json_encode($jsonReply);
