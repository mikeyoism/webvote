<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';


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
}else {
    $competition = $dbAccess->getCompetition($competitionId);

}
if (APC_CACHE_ENABLED) {
    
    $categories = apc_fetch('categories-' . $competitionId);
    if ($categories === false) {
    $categories = $dbAccess->getCategories($competitionId);
    apc_store('categories-' . $competitionId, $categories, 120); // Cache for 120 seconds.
    }
}
else
{
    $categories = $dbAccess->getCategories($competitionId);
}



$resetVoteCode = isset($_SESSION['public_vote_terminal']) && $_SESSION['public_vote_terminal'] === true;


$jsonReply = array();
$jsonReply['sys_cat'] = array_keys($categories);

$voteCode = strtoupper($voteArgs->vote_code);
$voteCodeId = $dbAccess->checkVoteCode($competitionId, $voteCode);
if ($voteCodeId == 0) {

    $jsonReply['usrmsg'] = "Ogiltig kod. Försök igen. Koden finns på programmet.";
    $jsonReply['msgtype'] = "WARNING";
} else {
    $jsonReply['vote_code'] = $voteCode;
    $jsonReply['resetVoteCode'] = $resetVoteCode;

    $openTimes = dbAccess::calcCompetitionTimes($competition);
    if (isset($voteArgs->votes)) {
        if ($openTimes['open'] === false) {
            list($status, $msg) = array('WARNING', 'Röstningen är STÄNGD!');
        } else {

            list($status, $msg) = postVotes($dbAccess, $competition, $voteCodeId, $voteArgs->votes, $resetVoteCode, $categories);
        }
        $jsonReply['msgtype'] = $status;
        $jsonReply['usrmsg'] = $msg;
    } else {
        $jsonReply['msgtype'] = "OK";
        $jsonReply['usrmsg'] = "Ok!";
    }

    $voteCountStartTime = $openTimes['voteCountStartTime'];
    $jsonReply['votes'] = $dbAccess->getVotes($competition['id'], $voteCodeId, $voteCountStartTime);
}

header('Content-Type: application/json', true);
echo json_encode($jsonReply);


function postVotes($dbAccess, $competition, $voteCodeId, $votes, $resetVoteCode, $categories)
{

    $countSame = 0;
    foreach ($votes as $categoryId => $categoryVotes) {
        $category = $categories[$categoryId];

        $ivotes = [];

        for ($i = 1; $i <= 3; $i++) {
            if (property_exists($categoryVotes, $i)) {
                // $vote = filter_var($categoryVotes->{$i}, FILTER_SANITIZE_STRING); //deprecated
                $vote = htmlspecialchars($categoryVotes->{$i}, ENT_QUOTES);

                list($ivote, $errorString) = parseVote($category['entries'], $vote);
                if ($ivote == -1) {
                    return array('WARNING', "Röst rad #$i: $errorString");
                } else if ($ivote != 0) {

                    //räkna antal lika röster, exludera null-röster
                    $counts = count(array_filter($ivotes, function ($v) use ($ivote) {
                        return $v == $ivote; }));

                    //lagra antal lika röster
                    if ($counts + 1 > $countSame)
                        $countSame = $counts + 1;

                }
                $ivotes[$i] = $ivote;


            }
        }

        if ($countSame > CONST_SETTING_VOTES_PER_CATEGORY_SAME) {
            if (CONST_SETTING_VOTES_PER_CATEGORY_SAME > 1)
                return array('WARNING', 'Högst ' . CONST_SETTING_VOTES_PER_CATEGORY_SAME . ' röster per öl.');
            else
                return array('WARNING', 'Högst en röst per öl.');


        }
        if ($countSame > 1 && CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL) {
            //count all votes, exclude null entries
            $count = count(array_filter($ivotes));
            if ($count < 3) {
                return array('WARNING', 'Alla röster måste användas om du röstar på samma öl mer än en gång.');
            }
        }

        $dbAccess->insertVote($voteCodeId, $categoryId, $ivotes);
    }

    return array('OK', "Rösterna har registrerats");
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

    $ivote = (int) $vote;


    if (array_search($ivote, $categoryEntries) === false) {
        return array(-1, "ogiltig röst ($vote), otillåtet tävlings-id");
    }

    return array($ivote, '');
}
