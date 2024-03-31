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
} else {
    $competition = $dbAccess->getCompetition($competitionId);
}

if (APC_CACHE_ENABLED) {

    $categories = apc_fetch('categories-' . $competitionId);
    if ($categories === false) {
        $categories = $dbAccess->getCategories($competitionId);
        apc_store('categories-' . $competitionId, $categories, 120); // Cache for 120 seconds.
    }
} else {
    $categories = $dbAccess->getCategories($competitionId);
}

$resetVoteCode = isset($_SESSION['public_vote_terminal']) && $_SESSION['public_vote_terminal'] === true;


$jsonReply = array();

$operation = strtolower($voteArgs->operation);
if ($operation == "getratings")

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
    if (isset($voteArgs->ratings)) {
        if ($openTimes['open'] === false) {
            list($status, $msg) = array('WARNING', 'Röstningen är STÄNGD!');
        } else {

            list($status, $msg) = storeRatings($dbAccess, $competition, $voteCodeId, $voteArgs->ratings);
        }
        $jsonReply['msgtype'] = $status;
        $jsonReply['usrmsg'] = $msg;
    } else {
        $jsonReply['msgtype'] = "OK";
        $jsonReply['usrmsg'] = "Ok!";
    }

    $voteCountStartTime = $openTimes['voteCountStartTime'];
    $jsonReply['ratings'] = $dbAccess->getRatings($competition['id'], $voteCodeId, $voteCountStartTime);
}

header('Content-Type: application/json', true);
$jsonOut = json_encode($jsonReply);
echo $jsonOut;

function storeRatings($dbAccess, $competition, $voteCodeId,  $ratings)
{

    //rating by categoryId
    // one $rating  like {
    //     "categoryId": "1",
    //     "beerEntryId": "114",
    //     "drankCheck": "1",
    //     "ratingScore": "2",
    //     "ratingComment": "nja"
    // }
    $successes = 0;
    $failures = 0;
    foreach ($ratings as $categoryId => $catOfRatings) {
        
        //foreach ratings array, call storeRating
        foreach ($catOfRatings as $clientKey => $rating) {
            if ($rating->beerEntryId > 0 && $categoryId == $rating->categoryId) { //exta check
                
                //ratingScore, garantera att den finns, +  att det är en int eller null
                if (!isset($rating->ratingScore))
                    $rating->ratingScore = "";
                $ratingScore = $rating->ratingScore  == "" ?  null : $rating->ratingScore;
                if ($ratingScore !== null && ($ratingScore = filter_var($ratingScore, FILTER_VALIDATE_INT)) === false) {
                    return array("WARNING", "ogiltig röst, ej heltal @ " . $rating->beerEntryId); //ska aldrig hända, men...
                }
                //drankCheck, garantera att det är en int eller null
                $drankCheck = $rating->drankCheck  == "" ?  null : $rating->drankCheck;
                if ($drankCheck !== null && ($drankCheck = filter_var($drankCheck, FILTER_VALIDATE_INT)) === false) {
                    return array("WARNING", "ogiltig drankCheck, ej heltal @ " . $rating->beerEntryId); //ska aldrig händ
                }
                //ratingComment
                $ratingComment = null;
                if (isset($rating->ratingComment) && $rating->ratingComment !== ""
                    && strlen($rating->ratingComment) > 0 && strlen($rating->ratingComment) <= 500) {
                    $ratingComment = $rating->ratingComment;
                }
                
                if ($ratingComment !== null ) {
                    $ratingComment = htmlspecialchars($ratingComment,ENT_QUOTES); //sanitera
                }

                list($ivoteR, $errorString) = $dbAccess->storeRating($voteCodeId, $rating->categoryId, $rating->beerEntryId, $ratingScore, $ratingComment, $drankCheck);
                
                if ($ivoteR == -1) {
                    $failures++;
                }
                else {
                    $successes++;
                }
            }
        }
    }
    if ($failures > 0) {
        return array('WARNING', "Något gick fel, ". $failures ." av " . ($failures + $successes) ." betyg har inte registrerats");
    } else {
        return array('OK', $successes ."st betyg registrerades");
    }
    
}
