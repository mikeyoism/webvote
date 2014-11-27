<?php session_start();
/*Webvote - functions for comeptition management and admin.
 *Copyright (C) 2014 Mikael Josefsson
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *@author      Mikael Josefsson (micke_josefsson (at) hotmail.com)
 *
 *@Part of a voting system developed for use at (but not limited to) 
 *home brewing events arranged by the swedish home brewing association (www.SHBF.se)
*/

require_once "_config.php";
require_once "vote_common.php";

if(isset($_POST['operation']))
{
    if ($_POST['operation'] == "getvotes"){
	$inCat = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
	return ax_getvotes($inCat);
    }
    else if ($_POST['operation'] == "getvotestat_public"){
	return ax_getvotestat_public();
    }
    else if ($_POST['operation'] == "login"){    
	$username = filter_var($_POST['un'], FILTER_SANITIZE_STRING);
	//todo? $password = md5(($_POST['pw']));
	$password = filter_var($_POST['pw'], FILTER_SANITIZE_STRING);
	return ax_login($username,$password);
    }
    else if ($_POST['operation'] == "votecode_generate")
    {
	$NrofCodes = filter_var($_POST['nrofcodes'], FILTER_SANITIZE_STRING);
	return ax_votecode_generate($NrofCodes);
    }
    else if ($_POST['operation'] == "votecode_count")
    {
	echo ax_votecode_count();
	return;
    }
    else
    {
	echo "no match";
	exit;
    }
}
else{ echo "no operation"; FB::warn("no operation"); exit;}

function ax_votecode_count()
{
	include 'sqlopen_pdo.php'; 
	$select = "SELECT count(vote_code) as codecount from vote_codes";
	$stmt = $db->prepare($select);
	if ($stmt->execute())
	{
	    if (count( $row = $stmt->fetch(PDO::FETCH_ASSOC)) > 0 ) //if ($row = mysql_fetch_assoc($result))
	    {
		return $row['codecount'];
	    }else
		return "0";
	}
	else
	    return "0";
	$db = $stmt = null;

}


function ax_getvotes($inCat){
    $inCat = strtolower($inCat);
    $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
    
    $cfound = false;
    //dubbelkolla att cat är giltig
    foreach ($cats  as $currCategory)
	if ($inCat === strtolower($currCategory) )
	    $cfound = true;
    
    if ($cfound){
	header('Content-Type: application/json', true);
	$jsonReply = array();
	FB::info($WeightCount,"weight count");
	$vote_weight_and_labels = unserialize(CONST_SETTING_VOTE_WEIGHT);
	$WeightCount = count($vote_weight_and_labels);
	FB::info($WeightCount,"weight count");
	$sqlvotes_1 = "";
	$sqlunions = "";
	$votes_found = false;
	if ($WeightCount == 0) //competition with NON-weighted votes
	{    
	    for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
	    {
			$sqlvotes_1 .= "vote_{$voteNr}=votetable.beer or ";
			$sqlunions .= "SELECT vote_{$voteNr} as beer  FROM  `vote_cat_{$inCat}`
				       UNION ALL ";
	    }		    
	    $sqlvotes_1 = substr($sqlvotes_1,0,-3); //rem last or
	    $sqlunions = substr($sqlunions,0,-10); //rem last union
		    
		   
	    //ta fram topplista sorterad på antal röster per öl, och antal unika röstare som andrahand-kriterium (utslagsgivande vid exakt lika många röster)
	    //not: votetable.beer ASC behövs, utan så kan ordningen pendla inblördes mellan öl med samma antal röster vid olika querytillfällen
	    $select = "SELECT votetable.beer, count(votetable.beer) as votes, (SELECT count(vote_code) FROM vote_cat_{$inCat}
		       WHERE {$sqlvotes_1}) as unique_voters  FROM 
		       ({$sqlunions}) votetable WHERE votetable.beer is not NULL GROUP BY votetable.beer
		       ORDER BY votes DESC, unique_voters DESC, votetable.beer ASC";
	    include 'sqlopen_pdo.php';
	    $stmt = $db->prepare($select);
	    FB::info($select,"sql:");
	    //if ($result = mysql_query($select)){
	    if ($stmt->execute())
	    {
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
		//if (mysql_num_rows($result) > 0 ){
		if (count($rows) > 0){
		    $votes_found = true;
		    
		}
		//while ($row = mysql_fetch_assoc($result) )
		foreach ($rows as $row)
		{
		    //spara array på varje postion, utan unika nycklar, blir lättare för klient att hantera i grid
		    array_push($jsonReply,array('beer' => $row['beer'], 'votes' => $row['votes'], 'unique_voters' => $row['unique_voters']));
		}
		//mysql_free_result($result);
		
	    
	    }
	    else
		FB::warn($select,"sql getvotes");
		
	    //include 'sqlclose.php';
	    $db = $stmt = null;
	
	}
	else
	{ //competition set up with weigted votes
	    //$vote_labels = array_keys($vote_weight_and_labels);
	    $vote_totals = array();

	    include 'sqlopen_pdo.php';
	    $votePos = 1;
	    foreach ($vote_weight_and_labels as $label => $weightScore)
	    {
		
		if (((int) $weightScore) == 0){
		    FB::warn("Weight is Zero, is this intended? otherwise ensure weight is defined as int");
		}
		
	    
		//FB::warn("no","sql getvotes");
		//select each vote_column, and sum up votes per beer
		$select = "SELECT vote_{$votePos} AS beer, COUNT( vote_{$votePos} ) AS votes
			   FROM  `vote_cat_{$inCat}`
			   WHERE vote_{$votePos} IS NOT NULL 
			   GROUP BY vote_{$votePos}
			   ORDER BY votes DESC , beer ASC";
		 
		 
		$stmt = $db->prepare($select);
		
		 //if ($result = mysql_query($select)){
		if ($stmt->execute())
		{
		    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		    
		     //FB::info(mysql_num_rows($result),"weight num rows");
		     if (!$votes_found && count($rows) > 0 )
			 $votes_found = true;
	    
		     //while ($row = mysql_fetch_assoc($result) )
		    foreach ($rows as $row)		     
		     {
			 //array of arrays, beer as key
			 if (!isset($vote_totals[$row['beer']])){
			     $vote_totals[$row['beer']] = array();
			 }
			 if (!isset($vote_totals[$row['beer']]['score']))
			     $vote_totals[$row['beer']]['score'] = $row['votes'] * $weightScore;
			 else
			    $vote_totals[$row['beer']]['score'] += $row['votes'] * $weightScore;
			    
			$vote_totals[$row['beer']]["votes_{$votePos}"] = $row['votes'];   //char-safe name, vote_1 etc
		     }
		     //mysql_free_result($result);
		     
		 
		}
		else {
		     FB::warn($select,"weight getvotes");
		}
		$votePos++;	
	    }
	    //include 'sqlclose.php';
	    $db = $stmt = null;
	    //spara array på varje postion, utan unika nycklar, blir lättare för klient att hantera i grid
	    foreach ($vote_totals as $beer => $keys){
	       //transform to plain one-dimensional w/o key array('beer'=>beer_id, 'score' =>xx, 'votes_x' =>xx)
	       $trans = array('beer' =>$beer/*,'k'=>count($keys)*/);
	       foreach ($keys as $k => $v)
		   $trans[$k] = $v;
	       array_push($jsonReply,$trans);
	    }


	}
				     
	
	if (!$votes_found)    
	    echo json_encode(array(array('beer' => 'inga röster än', 'votes' => '0', 'unique_voters' => '0'))); //nästan empty
	else
	    echo json_encode($jsonReply);
	
	return true;
    }
    else
	return false;
}

function ax_getvotestat_public()
{
    $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
    $catsPub  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC);
    $p = 0;
    header('Content-Type: application/json', true);
    $jsonReply = array();	

    include 'sqlopen_pdo.php';
    foreach ($cats  as $currCategory)
    {
	$inCat = $currCategory;
	$catPub = $catsPub[$p]; //pulikt namn

	$sqlvotes_1 = "";
	$sqlunions = "";
	for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
	{
		    $sqlvotes_1 .= "vote_{$voteNr} is not null or ";
		    $sqlunions .= "SELECT vote_{$voteNr} as beer  FROM  `vote_cat_{$inCat}`
				   UNION ALL ";
	}		    
	$sqlvotes_1 = substr($sqlvotes_1,0,-3); //rem or
	$sqlunions = substr($sqlunions,0,-10); //rem last union	    
	
	//ta fram topplista sorterad på antal röster per öl, och antal unika röstare som andrahandkriterium (utslagsgivande vid exakt lika många röster)
	//not: votetable.beer ASC behövs, utan så kan ordningen pendla inblördes mellan öl med samma antal röster vid olika querytillfällen
	$select = "SELECT count(votetable.beer) as votes, (SELECT count(vote_code) FROM vote_cat_{$inCat}
		   WHERE {$sqlvotes_1}) as unique_voters  FROM
		   ({$sqlunions}) votetable WHERE votetable.beer is not NULL
		   ORDER BY votes DESC, unique_voters DESC";
	
	$votes_found = false;
 	$stmt = $db->prepare($select);
	
	//if ($result = mysql_query($select)){
	if ($stmt->execute())
	{
	    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	    
	    //while ($row = mysql_fetch_assoc($result) ) //suppar flera rader, dock ej aktuellt här
	    foreach ($rows as $row)
	    {
		//spara array på varje postion, utan unika nycklar, blir lättare för klient att hantera i grid
		array_push($jsonReply,array('category' => $catPub, 'votes' => $row['votes'], 'unique_voters' => $row['unique_voters']));
	    }

	
	}
	else
	    FB::warn($select,"sql getvotestat_public");
	
	$p++;
    }
    echo json_encode($jsonReply);
    //include 'sqlclose.php';
    $db = $stmt = null;
    return true;
}        

function ax_login($username,$password){
    header('Content-Type: application/json', true);
    $jsonReply = array();
    $jsonReply["usrmsg"] = "";	
    $valid = false;
    $priv = 0;
    if(!empty($username) && !empty($password))
    {
	include 'sqlopen_pdo.php';
	$stmt = $db->prepare("SELECT username, priviledge from vote_users WHERE LOWER(username) = LOWER(?) AND password=? LIMIT 1" );
	$stmt->execute(array($username,$password));
	$rows = $stmt->fetch(PDO::FETCH_ASSOC);
	if(!empty($rows))
	{
	    $valid = true;
	    $priv = (int) $rows["priviledge"];
	}
	$stmt = null;
	$db = null; //include 'sqlclose.php';

    }
    
    if ($valid){
	$jsonReply["usrmsg"] = "OK";
	$jsonReply["page"]  = $_SESSION["loginReqPage"]; //mest för att det ser bra ut, eller om vi vill bygga ut funktionalitet framöver...
	$_SESSION["authenticated_level"] = $priv;
    }
    else{
	
	$jsonReply["usrmsg"] = "DENIED";
	//$jsonReply["u"] = $username;
	//$jsonReply["p"] = $password;
	$_SESSION["authenticated_level"] = 0;
    }
    echo json_encode($jsonReply);
    return true;
}

function ax_votecode_generate($NrofCodes){
    if ($NrofCodes > 0)
    {
	$codes = array();
	for ($i = 0; $i  < $NrofCodes; $i++)
	{
	    $new = generateRandomString(3);
	    while (in_array($new,$codes))
		   $new = generateRandomString(3);
	    array_push($codes,$new);
	    
	    
	}
	$now = date("Y-m-d H:i:s");
	$insert = "INSERT IGNORE INTO vote_codes (vote_code,generated_DT) VALUES";
	foreach ($codes as $code){
	    if ($code != "")
		$insert .= " ('" . $code . "','" . $now . "'),";
	}
	$insert = rtrim($insert,",");
	
	include 'sqlopen_pdo.php'; //global conn
	//if (mysql_query($insert))
	if (($affected_rows = $db->exec($insert)))	
	    echo "OK -" . $affected_rows . " koder tillagda";
	else
	    echo "Failure - " ; //. mysql_error();
	    
	//include 'sqlclose.php'; //global conn
	$db = null;
	
    }
    return;
}
