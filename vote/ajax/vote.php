<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

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

$resetVoteCode = isset($_SESSION['public_vote_terminal']) && $_SESSION['public_vote_terminal'] === true;

$voteArgs = json_decode(file_get_contents('php://input'));

$jsonReply = array();
$jsonReply['sys_cat'] = array_keys($categories);

$voteCode = strtoupper($voteArgs->vote_code);
$voteCodeId = $dbAccess->checkVoteCode($voteCode);
if ($voteCodeId == 0) {
    $jsonReply['usrmsg'] = "Ogiltig kod. Försök igen. Koden finns på programmet.";
    $jsonReply['msgtype'] = "WARNING";
} else {
    $jsonReply['usrmsg'] = "Ok!";
    $jsonReply['msgtype'] = "OK";

    $jsonReply['vote_code'] = $voteCode;

    $openTimes = dbAccess::calcCompetitionTimes($competition);
    if (isset($voteArgs->votes)) {
        if ($openTimes['open'] === false) {
            $voteResult = array('usrmsg' => 'Röstningen är STÄNGD!');
        } else {
            $voteResult = postVotes($dbAccess, $competition, $voteCodeId, $voteArgs->votes, $resetVoteCode, $categories);
        }
        $jsonReply['vote_result'] = $voteResult;
    }

    $voteCountStartTime = $openTimes['voteCountStartTime'];
    $jsonReply['votes'] = $dbAccess->getVotes($competition['id'], $voteCodeId, $voteCountStartTime);
}

header('Content-Type: application/json', true);
echo  json_encode($jsonReply);


function postVotes($dbAccess, $competition, $voteCodeId, $votes, $resetVoteCode, $categories)
{
    $jsonReply = array();

    foreach ($votes as $categoryId => $categoryVotes) {
        $category = $categories[$categoryId];

        $ivotes = [];
    
        for ($i = 1; $i <= 3; $i++) {
            $vote = filter_var($categoryVotes->{$i}, FILTER_SANITIZE_STRING);
            list($ivote, $errorString) = parseVote($category['entries'], $vote);
            if ($ivote == -1) {
                $jsonReply['usrmsg'] = "Röst rad #$i: $errorString";
                return $jsonReply;
            } else if ($ivote != 0) {
                if (array_search($ivote, $ivotes)) {
                    $jsonReply['usrmsg'] = 'Högst en röst per öl.';
                    return $jsonReply;
                }
            }
            $ivotes[$i] = $ivote;
        }
    
        $dbAccess->insertVote($voteCodeId, $categoryId, $ivotes);
    }
    
    $jsonReply['usrmsg'] = "Rösterna har registrerats";
    $jsonReply['resetVoteCode'] = $resetVoteCode;

    return $jsonReply;
}

/*
 * Returns two values (vote, errorDescription):
 * vote is an integer: positive means a vote, null a missing vote, and -1 that an error occurred.
 */
function parseVote($categoryEntries, $vote)
{
    if ($vote == '') {
        return array(null, '');
    }

    if (($vote = filter_var($vote, FILTER_VALIDATE_INT)) === false) {
        return array(-1, "ogiltig röst, ej heltal");
    }
    
    $ivote = (int)$vote;

    if (array_search($ivote, $categoryEntries) === false) {
        return array(-1, "ogiltig röst ($vote), otillåtet tävlings-id");
    }

    return array($ivote, '');
}
