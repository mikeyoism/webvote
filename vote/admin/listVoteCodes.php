<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

$competitionId = getCompetitionId();
list($privilegeLevel, $username) = requireLoggedInOrRedirect($competitionId, 1);

$dbAccess = new DbAccess();
$competition = $dbAccess->getCompetition($competitionId);

?>

<head>
<meta charset="utf-8"/>
<title>Röstningskoder för <?=$competition['name']?></title>
<link rel="stylesheet" href="css/themes/shbf.css" />
</head>
<body>
<h1>Röstningskoder för <?=$competition['name']?></h1>
<pre>
<?php
$voteCodes = $dbAccess->getVoteCodes($competition['id']);

foreach ($voteCodes as $code) {
    print "$code\n";
}
?>
</pre>
</body>
</html>
