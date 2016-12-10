<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

define("SETTING_SYSSTATUS_INTERVAL", 10000);
define("SETTING_VOTES_PER_CATEGORY", 3);

$dbAccess = new DbAccess();

$competition = apc_fetch('competition');
if ($competition === false) {
    $competition = $dbAccess->getCurrentCompetition();
    apc_store('competition', $competition, 30); // Cache for 30 seconds.
}

if (isset($_POST['operation']))
{
    $operation = $_POST['operation'];
    if ($operation == 'sysstatus')
    {
        $clientInterval = (int)$_POST['my_interval'];
        ax_sysstatus($competition, $clientInterval);
    }
    else if ($operation == "reread")
    {
        $postedVoteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
        ax_reread($dbAccess, $competition, $postedVoteCode);
    }
    else if($operation == "post_vote" )
    {
        $categoryId = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
        $postedVoteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);

        $category = $dbAccess->getCategories($competition['id'])[$categoryId];
        ax_post_vote($dbAccess, $competition, $category, $postedVoteCode);
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
function ax_sysstatus($competition, $clientInterval)
{
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
        $openTimes = dbAccess::calcCompetitionTimes($competition);
        $jsonReply['usrmsg'] = '<p>'.$openTimes['openCloseText'];
        $secondsToClose = $competition['closeTime']->getTimeStamp() - (new DateTime())->getTimeStamp();
        if ($secondsToClose < 600) {
            $jsonReply['msgtype'] = 'error';
        } else if ($secondsToClose < 60) {
            $jsonReply['msgtype'] = 'warning';
        }
    }

    echo  json_encode($jsonReply);
}


/*
 * REREAD (votes)
 */
function ax_reread($dbAccess, $competition, $postedVoteCode)
{
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
}

/*
 * POST_VOTE
 */
function ax_post_vote($dbAccess, $competition, $category, $voteCode)
{
    $openTimes = dbAccess::calcCompetitionTimes($competition);
    if ($openTimes['open'] === false) {
        echo "Röstningen är STÄNGD!";
        return;
    }
    
    $voteCodeId = $dbAccess->checkVoteCode($voteCode);
    if ($voteCodeId == 0) {
        echo "Felaktig röstkod!, ange din röstkod längst upp innan du röstar";
        return;
    }
    
    $ivotes = array(); // integers
    $filled_votes = 0;
    $duplicate = false;
    
    for ($i = 1; $i <= SETTING_SYSSTATUS_INTERVAL; $i++) {
        if (isset($_POST["vote_$i"])) {
            $vote = filter_var($_POST["vote_$i"], FILTER_SANITIZE_STRING);
        } else {
            $vote = '';
        }
            
        list($ivote, $errorString) = parseVote($category['entries'], $vote);
        if ($ivote == -1) {
            echo "Röst rad #$i: $errorString";
            return;
        } else if ($ivote != 0) {
            if (array_search($ivote, $ivotes)) {
                echo "Högst en röst per öl.";
                return;
            }
            array_push($ivotes, $ivote);
            $filled_votes++;
        } else {
            array_push($ivotes, null);
        }
    }
    
    if ($filled_votes == 0) {
        echo "Ange minst en röst först";
        return false;
    }

    $dbAccess->insertVote($voteCodeId, $category['id'], $ivotes);
    
    echo "Rösterna har registrerats";
}
