<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';
$dbAccess = new DbAccess();

if (isset($_POST['login'])) {
    $dbAccess->login($_POST['username'], $_POST['password']);
    redirectToSelf();
} else if (isset($_POST['logout'])) {
    logout();
    redirectToSelf();
}

$competitionId = getCompetitionId();

if (isLoggedIn()) {
    list($privilegeLevel, $username) = requireLoggedIn($competitionId, 1);
} else {
    $privilegeLevel = 0;
}

$competition = $dbAccess->getCompetition($competitionId);
$openTimes = dbAccess::calcCompetitionTimes($competition);

if (isset($_POST['generateVoteCodes'])) {
    if ($privilegeLevel < 2) {
        die('Not authorized.');
    }
    generateVoteCodes($competitionId, $_POST['voteCodesToGenerate']);
    redirectToSelf();
} else if (isset($_POST['addEntryCodes'])) {
    $dbAccess->addCategoryEntries($_POST['categoryId'], expandRangesToArray($_POST['entryCodes']));
    redirectToSelf();
} else if (isset($_POST['removeEntryCodes'])) {
    $dbAccess->removeCategoryEntries($_POST['categoryId'], expandRangesToArray($_POST['entryCodes']));
    redirectToSelf();
} else if (isset($_POST['openForTest'])) {
    $closeTime = (new DateTime())->add(new DateInterval('PT1H'));
    $dbAccess->setCompetitionOpenForTestUntil($competitionId, $closeTime);
    redirectToSelf();
}


?>

<head>
<meta charset="utf-8"/>
<title>Administration</title>
<link rel="stylesheet" href="css/themes/shbf.css" />
</head>
<body>
<h1>Administration</h1>

<?php
if ($privilegeLevel < 1) {
?>
    <h2>Logga in</h2>
    <form method='post'>
    <label>Användare:</label>
    <input type="text" name="username" value=""/>
    <label>Lösenord:</label>
    <input type="password" name="password"/>
    <button type="submit" name='login'>Logga in</button>
    </form>
<?php
    exit;
} else {
?>
    <form method='post'>
    <label>Användare:</label><?= $username ?>
    <button type="submit" name='logout'>Logga ut</button>
    </form>
    <hr>
<?php
}
?>

<p><?= $competition['name'] ?>: öppnar för röstning <?= $openTimes['openTime']->format('Y-m-d H:i') ?> och stänger <?= $openTimes['closeTime']->format('Y-m-d H:i') ?>.

<p><?=$openTimes['openCloseText']?>
<?php
if (!$openTimes['open'] && !$openTimes['timeBeforeOpen']->invert) {
    print "<form method='post'><button type='submit' name='openForTest'>".
        "Öppna 1 timme för test</button></form>";
}
?>
    
<hr>

<p>Det finns <?=$competition['voteCodeCount']?> <a
href="listVoteCodes.php?competitionId=<?=$competitionId?>">röstkoder</a> för denna tävling.
             
<?php
if ($privilegeLevel == 2) {
?>
    <form method='post'>
    För att generera fler röstkoder, ange antal: <input type='text' name='voteCodesToGenerate' value='' size='3'/>
    <button type="submit" name='generateVoteCodes'>Generera</button>
    </form>
<?php
}
?>

<hr>

<?php
$categories = $dbAccess->getCategories($competitionId);

foreach ($categories as $category) {
?>
<p><b>Deltagande nummer i kategori <?=$category['name']?> (<?=$category['description']?>)</b>:
    <?= compressArrayToRanges($category['entries'])?>
    
    <form method='post'>
    Förändra listan över deltagande nummer: <input type='text' name='entryCodes' value='' size='40' maxlength='3000' />
    <input type='hidden' name='categoryId' value='<?=$category['id']?>'/>
    <button type='submit' name='addEntryCodes'>Lägg till</button>
    <button type='submit' name='removeEntryCodes'>Ta bort</button>
    </form>

<?php
}
?>


<hr>

<p>Se <a href="showVotes.php?competitionId=<?=$competitionId?>">röstningsresultatet</a>.

</body>
</html>

<?php

function generatevoteCodes($competitionId, $count)
{
    global $dbAccess;

    $codes = array();
    for ($i = 0; $i  < $count; $i++)
    {
        do {
            $code = generateRandomString(6);
        } while (in_array($code, $codes));
	    array_push($codes, $code);
	}

    $dbAccess->insertVoteCodes($competitionId, $codes);
}

function generateRandomString($length)
{
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function compressArrayToRanges($expanded) {
    $low = -1;
    $prevNum = -1;
    $expanded = array_unique($expanded);
    sort($expanded, SORT_NUMERIC);
    foreach ($expanded as $num) {
        if ($low == -1) {
            $low = $num;
        } else if($num - $prevNum > 1) {
            $compact[] = ($prevNum - $low >= 1) ? sprintf("%d-%d", $low, $prevNum) : $prevNum;
            $low = $num;
        }
        $prevNum = $num;
    }
    if ($low != -1 ) {
        $compact[] = ($num - $low >= 1) ? sprintf("%d-%d", $low, $num) : $num;
        return implode(",", $compact);
    } else {
        return '';
    }
}

function expandRangesToArray($compact) {
    $expanded = Array();
    $compact = explode(",", $compact);
    foreach($compact as $num) {
        if( is_numeric($num) ) {
            $expanded[] = $num;
        } else {
            list($low, $high) = explode("-", $num);
            if( is_numeric($low) && is_numeric($high) && $low < $high) {
                for($i = $low;$i <= $high;$i++) {
                    $expanded[] = $i;
                }
            }
        }
    }
    return $expanded;
}
?>