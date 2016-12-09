<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';
$dbAccess = new DbAccess();
$competition = $dbAccess->getCurrentCompetition();

define("SETTING_SYSSTATUS_INTERVAL", 10000);

if (isset($_POST['operation']))
{
    $operation = $_POST['operation'];
    if ($operation == 'sysstatus')
    {
        $source = filter_var($_POST['source'], FILTER_SANITIZE_STRING);
        $clientInterval = (int)$_POST['my_interval'];
        return ax_sysstatus($source, $clientInterval);
    }
    else if ($operation == "reread")
    {
        $postedVoteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
        return ax_reread($postedVoteCode);
    }
    else if($operation == "post_vote" )
    {
        $categoryId = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
        $postedVoteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);

        $category = $dbAccess->getCategories($competition['id'])[$categoryId];
        return ax_post_vote($category, $postedVoteCode);
    }
    else
    {
        die('no match');
    }
}
else
{
    die('no operation');
}

/*
 * SYSSTATUS
 * meddelar klient om tävling är öppen, håller på att stänga etc
 */
function ax_sysstatus($source, $clientInterval)
{
    global $competition;
    
    header('Content-Type: application/json', true);
    $jsonReply = array();
    
    $jsonReply['usrmsg'] = "";
    $jsonReply['msgtype'] = "neutral"; // ok(green), neutral(orange), error(yellow), warning(red)
    $jsonReply['competition_name'] = $competition['name'];
    
    // be klient ändra sin uppdateringsintervall? (om ändrad sen klient startade)
    if ($clientInterval != 0 && $clientInterval != SETTING_SYSSTATUS_INTERVAL) {
        $jsonReply['msgtype'] = 'interval';
        $jsonReply['interval'] = SETTING_SYSSTATUS_INTERVAL;
    }
    /*
     * be klient sluta uppdatera status (vid bandbreddsproblem)
     * $clientInterval == 0, säkerhet om klient löper amok/hackar (ex skickar in en icke int), så får den stop.
     */
    else if ($clientInterval == 0) {
	    $jsonReply['msgtype'] = 'stop';
    } else {
        $jsonReply['usrmsg'] = '<p>'.$competition['openCloseText'];
        $secondsToClose = $competition['closeTime']->getTimeStamp() - (new DateTime())->getTimeStamp();
        if ($secondsToClose < 600) {
            $jsonReply['msgtype'] = 'error';
        } else if ($secondsToClose < 60) {
            $jsonReply['msgtype'] = 'warning';
        }
    }

    echo  json_encode($jsonReply);
    return true;
}


/*
 * REREAD (votes)
 */
function ax_reread($postedVoteCode)
{
    global $competition, $dbAccess;
    
    $jsonReply = array();

    $voteCodeId = $dbAccess->checkVoteCode($postedVoteCode);
    if ($voteCodeId == 0) {
	    $jsonReply['usrmsg'] = "Ogiltig kod. Försök igen. Koden finns på programmet.";
	    $jsonReply['msgtype'] = "warning";
    } else {
        $jsonReply['usrmsg'] = "Ok! nu kan du rösta nedanför";
        $jsonReply['msgtype'] = "ok-cached";
        $jsonReply['vote_code'] = $postedVoteCode;
        
        $votes = $dbAccess->getCurrentVotes($competition['id'], $voteCodeId);

        foreach ($votes as $categoryId => $vote) {
            $jsonReply['vote_1_'.$categoryId] = $vote['vote1'];
            $jsonReply['vote_2_'.$categoryId] = $vote['vote2'];
            $jsonReply['vote_3_'.$categoryId] = $vote['vote3'];
        }

        $jsonReply['sys_cat'] = array_keys($votes);
    }

    header('Content-Type: application/json', true);
    echo  json_encode($jsonReply);
    return true;
}

/*
 * POST_VOTE
 */
function ax_post_vote($category, $voteCode)
{
    global $competition, $dbAccess;
    
    if (! $competition['open']) {
        echo "Röstningen är STÄNGD!";
        return;
    }
    
    $voteCodeId = $dbAccess->checkVoteCode($voteCode);
    if ($voteCodeId == 0) {
        echo "Felaktig röstkod!, ange din röstkod längst upp innan du röstar";
        return;
    }
    
    $ivotes = array(); // integers
    
    $vote_count = 0;
    $filled_votes = 0;
    // client implementation is responsible for sending empty POSTS (ie not null) for all votes not filled in
    while (isset($_POST['vote_' . ($vote_count + 1)])) {
        $vote = filter_var($_POST['vote_' . ($vote_count + 1)], FILTER_SANITIZE_STRING);

        list($ivote, $errorString) = parseVote($category['entries'], $vote);
        if ($ivote == 0) {
            echo "Röst rad #$vote_count,  $ivote: $errorString";
            return;
        } else if ($ivote != -1) {
            $filled_votes++;
            $ivotes[$vote_count] = $ivote;
        }
        $vote_count++;
    }
    
    if ($filled_votes == 0) {
        echo "Ange minst en röst först";
        return false;
    }

    $errorStr = checkVoteRules($ivotes, 3);
    if ($errorStr != '') {
        echo $retStr;
        return;
    }

    $dbAccess->insertVote($voteCodeId, $category['id'], $ivotes);
    
    echo "Rösterna har registrerats";

    foreach ($ivotes as $key => $vote)
    {
        if ($vote > 0) $_SESSION['vote_{$key}_{$category}'] = $vote;
    }
    return true;
}
