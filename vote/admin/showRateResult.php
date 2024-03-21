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
<link rel="stylesheet" href="css/themes/shbf.css" />
</head>
<body>
<h1>Tävlingsresultat (av betyg/rating) för <?=$competition['name']?></h1>

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
<h2>Kategori <?=$category['name']?>
<table>
<tr><th>Öl-nr# </th><th><strong>Viktad tävlingspoäng </strong></th><th>Antal röstare</th><th>(Oviktad råpoäng)</th><th>(Snittpoäng)</th><th>Ölets Namn</th><th>Bryggare</th>
<?php
    $voteResult = $dbAccess->getRatingResultTot($category['id'], $voteCountStartTime);
    foreach ($voteResult as $row) {
?>
        <tr>
        <td><?=$row['beerEntryId']?></td>
        <td><?=$row['weightedScore']?></td>
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
