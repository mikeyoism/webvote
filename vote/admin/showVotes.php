<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

list($privilegeLevel, $username) = requireLoggedIn(1, true);

$dbAccess = new DbAccess();
$competition = $dbAccess->getCompetition($_GET['competitionId']);
$openTimes = dbAccess::calcCompetitionTimes($competition);
?>

<head>
<meta charset="utf-8"/>
<title>Röstningsresultat för <?=$competition['name']?></title>
<link rel="stylesheet" href="css/themes/shbf.css" />
</head>
<body>
<h1>Röstningsresultat för <?=$competition['name']?></h1>

<p>Senast uppdaterad <?=(new DateTime())->format('Y-m-d H:i')?>.

<p><?=$openTimes['openCloseText']?>
<?php
$openTime = $competition['openTime'];
$now = new DateTime();
$timeBeforeOpen = date_diff($now, $openTime);
if ($timeBeforeOpen->invert) {
    $voteCountStartTime = $openTime;
    print '<p>Endast röster avlagda efter '.$voteCountStartTime->format('Y-m-d H:i').' räknas med i röstresultatet.';
} else {
    $voteCountStartTime = null;
    print '<p>Alla röster räknas med i röstresultatet.';
}
?>

<?php
$categories = $dbAccess->getCategories($competition['id']);

foreach ($categories as $category) {
?>
<h2>Kategori <?=$category['name']?>
<table>
<tr><th>Öl-nr</th><th>Antal guld</th><th>Antal silver</th><th>Antal brons</th><th>Poäng</th></tr>
<?php
    $voteResult = $dbAccess->getVoteResult($category['id'], $voteCountStartTime);
    foreach ($voteResult as $row) {
?>
        <tr>
        <td><?=$row['entryId']?></td>
        <td><?=$row['vote1']?></td>
        <td><?=$row['vote2']?></td>
        <td><?=$row['vote3']?></td>
        <td><?=$row['points']?></td>
        </tr>
<?php
    }

?>
</table>
<?php
}
?>
             
</body>
</html>
