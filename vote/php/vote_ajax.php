<?php // -*- coding: utf-8 -*-
/*
 *Copyright (C) 2014 Mikael Josefsson
 *Modifications copyright 2016 Staffan Ulfberg
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *@author      Mikael Josefsson (micke_josefsson (at) hotmail.com)
 *
 *@Part of a voting system developed for use at (but not limited to) 
 *home brewing events arranged by the swedish home brewing association (www.SHBF.se)
*/

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

$categories = apc_fetch('categories');
if ($categories === false) {
    $categories = $dbAccess->getCategories($competition['id']);
    apc_store('categories', $categories, 120); // Cache for 120 seconds.
}


if (isset($_POST['operation']))
{
    $operation = $_POST['operation'];
    if ($operation == 'sysstatus')
    {
        $clientInterval = (int)$_POST['my_interval'];
        $jsonReply = ax_sysstatus($competition, $clientInterval);
    }
    else if ($operation == "reread")
    {
        $voteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);

        $jsonReply = ax_reread($dbAccess, $competition, $categories, $voteCode);
    }
    else if($operation == "post_vote" )
    {
        $categoryId = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
        $voteCode = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);

        $resetVoteCode = isset($_SESSION['public_vote_terminal']) && $_SESSION['public_vote_terminal'] === true;
        
        $category = $categories[$categoryId];
        
        $jsonReply = ax_post_vote($dbAccess, $competition, $category, $voteCode, $resetVoteCode, $categories);
    }
    else
    {
        die('no match');
    }

    header('Content-Type: application/json', true);
    echo  json_encode($jsonReply);
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
    $jsonReply = array();
    $jsonReply['usrmsg'] = "";
    $jsonReply['msgtype'] = "neutral"; // ok(green), neutral(orange), error(yellow), warning(red)
    $jsonReply['competition_name'] = $competition['name'];
    
    // be klient ändra sin uppdateringsintervall? (om ändrad sen klient startade)
    if ($clientInterval != SETTING_SYSSTATUS_INTERVAL) {
        $jsonReply['msgtype'] = 'interval';
        $jsonReply['interval'] = SETTING_SYSSTATUS_INTERVAL;
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

    return $jsonReply;
}


/*
 * REREAD (votes)
 */
function ax_reread($dbAccess, $competition, $categories, $voteCode)
{
    $voteCode = strtoupper($voteCode);
    $voteCodeId = $dbAccess->checkVoteCode($voteCode);

    $jsonReply = array();
    $jsonReply['sys_cat'] = array_keys($categories);
    
    if ($voteCodeId == 0) {
	    $jsonReply['usrmsg'] = "Ogiltig kod. Försök igen. Koden finns på programmet.";
	    $jsonReply['msgtype'] = "warning";
    } else {
        $jsonReply['usrmsg'] = "Ok! Nu kan du rösta nedanför.";
        $jsonReply['msgtype'] = "ok";
        $jsonReply['vote_code'] = $voteCode;

        $openTimes = dbAccess::calcCompetitionTimes($competition);
        $voteCountStartTime = $openTimes['voteCountStartTime'];
        $votes = $dbAccess->getCurrentVotes($competition['id'], $voteCodeId, $voteCountStartTime);

        foreach ($votes as $categoryId => $vote) {
            $jsonReply['vote_1_'.$categoryId] = $vote['vote1'];
            $jsonReply['vote_2_'.$categoryId] = $vote['vote2'];
            $jsonReply['vote_3_'.$categoryId] = $vote['vote3'];
        }
    }

    return $jsonReply;
}

/*
 * POST_VOTE
 */
function ax_post_vote($dbAccess, $competition, $category, $voteCode, $resetVoteCode, $categories)
{
    $jsonReply = array();

    $openTimes = dbAccess::calcCompetitionTimes($competition);
    if ($openTimes['open'] === false) {
        $jsonReply['usrmsg'] = 'Röstningen är STÄNGD!';
        return $jsonReply;
    }
    
    $voteCodeId = $dbAccess->checkVoteCode($voteCode);
    if ($voteCodeId == 0) {
        $jsonReply['usrmsg'] = 'Felaktig röstkod!, ange din röstkod längst upp innan du röstar';
        return $jsonReply;
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
            $jsonReply['usrmsg'] = "Röst rad #$i: $errorString";
            return $jsonReply;
        } else if ($ivote != 0) {
            if (array_search($ivote, $ivotes)) {
                $jsonReply['usrmsg'] = 'Högst en röst per öl.';
                return $jsonReply;
            }
            array_push($ivotes, $ivote);
            $filled_votes++;
        } else {
            array_push($ivotes, null);
        }
    }
    
    if ($filled_votes == 0) {
        $jsonReply['usrmsg'] = 'Ange minst en röst först';
        return $jsonReply;
    }

    $nonNullVotes = array_filter($ivotes, function($v) { return $v !== null; });
    if (count($nonNullVotes) != count(array_unique($nonNullVotes))) {
        $jsonReply['usrmsg'] = 'Högst en röst per öl';
        return $jsonReply;
    }

    $dbAccess->insertVote($voteCodeId, $category['id'], $ivotes);
    
    $jsonReply['usrmsg'] = "Rösterna har registrerats";
    $jsonReply['resetVoteCode'] = $resetVoteCode;
    $jsonReply['sys_cat'] = array_keys($categories);

    return $jsonReply;
}
