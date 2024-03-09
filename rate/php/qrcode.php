<?php // -*- coding: utf-8 -*-
session_start();
include '../vote/php/common.inc';

//qr code params
$cid = "";
$bid = "";
if (isset($_GET['cid'])) {
    $cid = preg_replace('/[^-a-zA-Z0-9_]/', '', $_GET['cid']);
}
if (isset($_GET['bid'])) {
    $bid = preg_replace('/[^-a-zA-Z0-9_]/', '', $_GET['bid']);
}
//echo "cid: " . $cid . " bid: " . $bid;
