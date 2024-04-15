<?php


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$uri = explode( '/', $uri );
//find index.php position
$index = array_search('index.php', $uri);
if ((isset($uri[$index+1]) && $uri[$index+1] != 'statistics') || !isset($uri[$index+2])) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

require "../api/StatisticsController.php";
$objFeedController = new StatisticsController();
$strMethodName = $uri[$index+2] . 'Action';
$objFeedController->{$strMethodName}();
