<?php
session_start();
$_SESSION['public_vote_terminal'] = true;
$host  = $_SERVER['HTTP_HOST'];
$uri   = $_SERVER['PHP_SELF'];

$standardUri = preg_replace('/\/votepub\//', '/vote/', $uri);
header("Location: http://$host$standardUri"); 
?>
