<?php // -*- coding: utf-8 -*-

session_start();
include '../php/common.inc';

list($privilegeLevel, $username) = requireLoggedIn(1, true);

$dbAccess = new DbAccess();
$competition = $dbAccess->getCompetition($_GET['competitionId']);

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
