<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

$competitionId = getCompetitionId();

list($privilegeLevel, $username) = requireLoggedInOrRedirect($competitionId, 1);

$dbAccess = new DbAccess();
$competition = $dbAccess->getCompetition($_GET['competitionId']);
$openTimes = dbAccess::calcCompetitionTimes($competition);
?>

<head>
<meta charset="utf-8"/> 
<title>Tävlingsresultat (rating) för <?=$competition['name']?></title>
<link rel="stylesheet" href="../css/themes/shbf.css" />
<style>
table, th, td {
  border: 1px solid black;
   border-collapse: collapse;
}
tr:nth-child(even) {
  background-color: #D6EEEE;
}
td,th {
  padding: 7px;
  
}
</style>
</head>
<body>
<h1>Tävlingsresultat RATING för <?=$competition['name']?></h1>

<p>Senast uppdaterad <?=(new DateTime())->format('Y-m-d H:i')?>.

<p><?=$openTimes['openCloseText']?>
<?php
$voteCountStartTime = $openTimes['voteCountStartTime'];
if ($voteCountStartTime === null) {
    print '<p>Alla röster räknas med i röstresultatet.';
} else {
    print '<p>Endast röster avlagda efter '.$voteCountStartTime->format('Y-m-d H:i').' räknas med i röstresultatet.';
}
?>

<?php
$categories = $dbAccess->getCategories($competition['id']);

foreach ($categories as $category) {
?>
<h2>Kategori <?=$category['name']?> - <?=$category['description']?></h2>
<p>Klassen har <strong><?=$dbAccess->getBeerCountForCategory($category['id'])?>st </strong> registrerade tävlingsbidrag att rösta på.</p>
<p><strong><?=$dbAccess->getVoteCodeCount($category['id'], $voteCountStartTime)?>st </strong> besökare 
 har druckit av <strong><?=$dbAccess->getDrankCheckCount($category['id'], $voteCountStartTime)?></strong> olika öl,
 varav <strong><?=$dbAccess->getRatingCount($category['id'], $voteCountStartTime)?></strong> har fått ett betyg.
</p>
<table >
 <?php if (CONST_SYS_JS_DEBUG) {?>
<tr><th>Öl-nr# </th><th><strong>Viktad tävlingspoäng </strong></th><th><strong>(Normaliserad)</strong></th></th><th>Antal röstare</th><th>(Oviktad råpoäng)</th><th style="margin-right:10px">(Snittpoäng)</th><th>Ölets Namn</th><th>Bryggare</th>
<?php } else { ?>
  <tr><th>Öl-nr# </th><th><strong>Viktad tävlingspoäng </strong></th></th><th>Antal röstare</th><th>(Oviktad råpoäng)</th><th style="margin-right:10px">(Snittpoäng)</th><th>Ölets Namn</th><th>Bryggare</th>  
<?php
}
    $voteResult = $dbAccess->getRatingResultTot($category['id'], $voteCountStartTime);
    foreach ($voteResult as $row) {
?>
        <tr>
        <td><?=$row['beerEntryId']?></td>
        <td><?=round($row['weightedScore'],5)?></td>
        <?php if (CONST_SYS_JS_DEBUG){?> 
          <td><?=round($row['weightedScoreNorm'],5)?></td>
        <?php }?>
        <td><?=$row['votersCount']?></td>
        <td><?=$row['ratingScore']?></td>
        <td><?=$row['weightedMeanValue']?></td>
        <td><?=$row['beerName']?></td>
        <td><?=$row['brewer']?></td>
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
