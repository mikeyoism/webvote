<?php // -*- coding: utf-8 -*-
/**
 * Bayesian Rating Results Admin Page
 * Displays competition results using Bayesian weighted scoring
 */

session_start();
include '../php/common.inc';
require_once '../php/BayesianRateHelper.php';

$competitionId = getCompetitionId();

list($privilegeLevel, $username) = requireLoggedInOrRedirect($competitionId, 1);

$dbAccess = new DbAccess();
$competition = $dbAccess->getCompetition($_GET['competitionId']);
$openTimes = DbAccess::calcCompetitionTimes($competition);
$voteCountStartTime = $openTimes['voteCountStartTime'];

// Check if Bayesian rating is enabled
$bayesianEnabled = getCompetitionSetting($competitionId, 'ENABLE_BAYESIAN_RATING', false);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="utf-8"/>
    <title>Bayesian Resultat - <?=htmlspecialchars($competition['name'])?></title>
    <link rel="stylesheet" href="../css/themes/shbf.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #D2C199;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            border-left: 4px solid #D2C199;
            padding-left: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 30px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #D2C199;
            color: #333;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .gold {
            background-color: #FFD700 !important;
            font-weight: bold;
        }
        .silver {
            background-color: #C0C0C0 !important;
        }
        .bronze {
            background-color: #CD7F32 !important;
            color: white;
        }
        .best-in-show {
            background-color: #4CAF50 !important;
            color: white;
            font-weight: bold;
        }
        .stats-box {
            background-color: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-box strong {
            color: #D2C199;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
        }
        .back-link:hover {
            color: #333;
        }
        .no-placing {
            color: #999;
            font-style: italic;
        }
        .score-cell {
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <a href="index.php?competitionId=<?=$competitionId?>" class="back-link">&larr; Tillbaka till admin</a>

    <h1>Bayesian Tävlingsresultat - <?=htmlspecialchars($competition['name'])?></h1>

    <?php if (!$bayesianEnabled): ?>
    <div class="warning">
        <strong>Varning:</strong> Bayesian rating är inte aktiverad.
        <br><br>
        <a href="showRateResult.php?competitionId=<?=$competitionId?>">Visa standard rating-resultat istället</a>
    </div>
    <?php else: ?>

    <?php
    $bayesianHelper = new BayesianRateHelper($competitionId);
    $settings = $bayesianHelper->getSettings();
    ?>
    <div class="info">
        <strong>Beräkningsmetod:</strong> Bayesian Average<br>
        <strong>Skala:</strong> 1-10 stjärnor<br>
        <strong>Formel:</strong> WR = (v/(v+m)) × R + (m/(v+m)) × C<br>
        <small>
            Där: v = antal röster, m = pseudo-röster (<?=$settings['pseudoVoteCount']?>),
            R = ölets medelbetyg, C = klassens medelbetyg
        </small><br>
        <strong>Inställningar:</strong>
        Pseudo-röster (m) = <?=$settings['pseudoVoteCount']?>,
        Min. röster för placering = <?=$settings['minimumVotesThreshold']?>,
        Min. öl med tillräckligt antal röster (Per klass) = <?=$settings['minimumBrewsWithEnoughVotes']?><br>
        <strong>Tie-breakers</strong> (vid lika Bayesian Score eller BIS score): 1) Antal röster (fler = bättre),
        2) Medianbetyg (högre = bättre), 3) Standardavvikelse betyg (lägre = bättre). Detta tas med i beräkningen av sorteringsordningen.
    </div>

    <div class="stats-box">
        <p>Senast uppdaterad: <strong><?=(new DateTime())->format('Y-m-d H:i')?></strong></p>
        <p><?=$openTimes['openCloseText']?></p>
        <?php if ($voteCountStartTime === null): ?>
            <p>Alla röster räknas med i röstresultatet.</p>
        <?php else: ?>
            <p>Endast röster avlagda efter <strong><?=$voteCountStartTime->format('Y-m-d H:i')?></strong> räknas med.</p>
        <?php endif; ?>
    </div>

    <?php
    // Best In Show
    $bestInShow = $dbAccess->getBestInShowResults($competitionId, $voteCountStartTime);
    ?>
    <h2>Best In Show</h2>
    <div class="stats-box">
        <p>Vinnaren (#1) från varje tävlingsklass jämförs. BIS Score beräknas med tävlingens samlade medelbetyg
            istället för klassmedelbetyg.</p>
        <p>Klasser med "etikett" eller "label" i namnet exkluderas.</p>
        <p>Endast vinnare med minst <?=$settings['minimumVotesThreshold']?> röster (Min. röster för placering) kan delta i Best In Show.</p>
        <?php if ($bestInShow['winner']): ?>
        <p>Globalt medelbetyg: <strong><?=round($bestInShow['globalMean'], 2)?></strong> |
           Totalt antal röster: <strong><?=$bestInShow['totalVotes']?></strong></p>
        <?php endif; ?>
    </div>
    <?php if ($bestInShow['winner']): ?>
    <table>
        <tr>
            <th>Placering</th>
            <th>Öl-nr</th>
            <th>Namn</th>
            <th>Bryggare</th>
            <th>Klass</th>
            <th>BIS Score</th>
            <th>Antal röster</th>
            <th>Median</th>
            <th>Std Dev</th>
            <th>Medel</th>
        </tr>
        <tr class="best-in-show">
            <td>1</td>
            <td><?=htmlspecialchars($bestInShow['winner']['beerEntryId'])?></td>
            <td><?=htmlspecialchars($bestInShow['winner']['beerName'])?></td>
            <td><?=htmlspecialchars($bestInShow['winner']['brewer'])?></td>
            <td><?=isset($bestInShow['winner']['className']) ? htmlspecialchars($bestInShow['winner']['className']) : $bestInShow['winner']['classId']?></td>
            <td class="score-cell"><?=round($bestInShow['winner']['bisScore'], 4)?></td>
            <td class="score-cell"><?=$bestInShow['winner']['voteCount']?></td>
            <td class="score-cell"><?=round($bestInShow['winner']['medianScore'], 3)?></td>
            <td class="score-cell"><?=round($bestInShow['winner']['standardDeviation'], 3)?></td>
            <td class="score-cell"><?=round($bestInShow['winner']['meanScore'], 3)?></td>
        </tr>
        <?php
        $placing = 2;
        foreach ($bestInShow['runnersUp'] as $runner):
        ?>
        <tr>
            <td><?=$placing++?></td>
            <td><?=htmlspecialchars($runner['beerEntryId'])?></td>
            <td><?=htmlspecialchars($runner['beerName'])?></td>
            <td><?=htmlspecialchars($runner['brewer'])?></td>
            <td><?=isset($runner['className']) ? htmlspecialchars($runner['className']) : $runner['classId']?></td>
            <td class="score-cell"><?=round($runner['bisScore'], 4)?></td>
            <td class="score-cell"><?=$runner['voteCount']?></td>
            <td class="score-cell"><?=round($runner['medianScore'], 3)?></td>
            <td class="score-cell"><?=round($runner['standardDeviation'], 3)?></td>
            <td class="score-cell"><?=round($runner['meanScore'], 3)?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="stats-box">
        <p><em>Inget resultat att visa.</em></p>
    </div>
    <?php endif; ?>

    <?php
    // Per-class results
    $categories = $dbAccess->getCategories($competition['id']);

    foreach ($categories as $category):
        $results = $dbAccess->getBayesianResultsForCategory($category['id'], $voteCountStartTime);

        // Get statistics
        $beerCount = $dbAccess->getBeerCountForCategory($category['id']);
        $voteCodeCount = $dbAccess->getVoteCodeCount($category['id'], $voteCountStartTime);
        $ratingCount = $dbAccess->getRatingCount($category['id'], $voteCountStartTime);
        $drankCheckCount = $dbAccess->getDrankCheckCount($category['id'], $voteCountStartTime);
    ?>

    <h2>Kategori: <?=htmlspecialchars($category['name'])?> - <?=htmlspecialchars($category['description'])?></h2>

    <div class="stats-box">
        <p>
            <strong><?=$beerCount?>st</strong> registrerade tävlingsbidrag |
            <strong><?=$voteCodeCount?>st</strong> besökare har druckit av
            <strong><?=$drankCheckCount?></strong> olika öl,
            varav <strong><?=$ratingCount?></strong> har fått ett betyg.
        </p>
    </div>

    <table>
        <tr>
            <th>Plac.</th>
            <th>Öl-nr</th>
            <th>Namn</th>
            <th>Bryggare</th>
            <th>Bayesian Score</th>
            <th>Antal röster</th>
            <th>Median</th>
            <th>Std Dev</th>
            <th>Medel</th>
        </tr>
        <?php
        foreach ($results as $i => $row):
            $rowClass = '';
            if ($i == 0 && $row['placing'] == 1) $rowClass = 'gold';
            elseif ($i == 1 && $row['placing'] == 2) $rowClass = 'silver';
            elseif ($i == 2 && $row['placing'] == 3) $rowClass = 'bronze';
        ?>
        <tr class="<?=$rowClass?>">
            <td><?=$row['placing'] == BayesianRateHelper::LAST_PLACING ? '<span class="no-placing">-</span>' : $row['placing']?></td>
            <td><?=htmlspecialchars($row['beerEntryId'])?></td>
            <td><?=htmlspecialchars($row['beerName'])?></td>
            <td><?=htmlspecialchars($row['brewer'])?></td>
            <td class="score-cell"><?=$row['bayesianScore'] > 0 ? round($row['bayesianScore'], 3) : '-'?></td>
            <td class="score-cell"><?=$row['voteCount']?></td>
            <td class="score-cell"><?=$row['medianScore'] > 0 ? round($row['medianScore'], 3) : '-'?></td>
            <td class="score-cell"><?=$row['standardDeviation'] > 0 ? round($row['standardDeviation'], 3) : '-'?></td>
            <td class="score-cell"><?=$row['meanScore'] > 0 ? round($row['meanScore'], 3) : '-'?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php endforeach; ?>

    <?php endif; // bayesianEnabled ?>

</body>
</html>
