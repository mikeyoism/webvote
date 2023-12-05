<?php session_start();
/*Webvote - main voting functions (default, for mobile devices, index.html)
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

    **/
/*
    *
    *dev notes: Det finns en maxbegränsinsning på 20 samtidigt aktiva SQL-connections hos one.com
    *De flesta anrop är några hundra millisekunder långa, och begränsingen skulle träda in om mer än 20 användare orsakar det exakt samtidigt
    *Det är kanske inte så troligt, men koden är husomhelt designad för att minimera risken:
    *-open och close görs med så lite kod emellan som möjligt, dvs så fort vi fått svar stänger vi (och open/close tar tid, så bara 1ggr per funktion om möjligt)
    *-vi använder session_variabler föra att minimera antal anrop mot sql
    *-vi undviker komplexa sql frågor
    *-det finns ett antal prestanda inställningar, om behov uppstår ex stänga av loggningstabeller
    *-SQL tabelldesignen kan tyckas märklig och elementär, men även den designad för att minimera påverkan av locks vid insert/update/select
    * på one.com gäller MyISAM storage engine som låser hela tabeller, men klarar samtidiga insert-selects
    * se bl.a https://dev.mysql.com/doc/refman/5.0/en/internal-locking.html för mer info.
    * så bland annat har jag valt att dela upp i separata tabeller per kategori (användaren trycker "spara" i klienten under respektive kategori, och då
    * uppstår ett lock bara på den kategorin - andra användare kan samtidigt manipulera en annan kategori) samt att varje vote är egen kolumn för eliminera
    * för att unvika tidskrävande selects - utan de ska vara så enkla/snabba som möjligt vid kontroll när röster uppdateras.
    *-inga inställningar sparas i SQL utan i _config.php för att hålla nera antal sql-operationer
    *
    *dev notes2: det finns en hel del att göra med att strukturera upp  kod som småbarnspappan inte hann med innan sm.
    *-gör rena html/js sidor utan ful-php
    *-gemensamma funtioner i sparat php fil
    *-etc etc
    **/

date_default_timezone_set("Europe/Stockholm"); //default är annars greenwitch

require_once "_config.php";
require_once "vote_common.php";



//hantera klientanrop
if(isset($_POST['operation']))
{

    if ($_POST['operation'] == "sysstatus")
    {
	$source = filter_var($_POST['source'], FILTER_SANITIZE_STRING);
	$clientInterval = (int) $_POST['my_interval'];
	return ax_sysstatus($source,$clientInterval);
    }
    else if ($_POST['operation'] == "reread")
    {
	$userStored_vote_code = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
	return ax_reread($userStored_vote_code);
	
    }
    else if($_POST['operation'] == "post_stat" )
    {
	$vote_code = filter_var($_POST["vote_code"], FILTER_SANITIZE_STRING);
	$gender = filter_var($_POST["gender"], FILTER_SANITIZE_STRING);
	$age = filter_var($_POST["age"], FILTER_SANITIZE_STRING);
	$location = filter_var($_POST["location"], FILTER_SANITIZE_STRING);
	$firstSM = filter_var($_POST["firstSM"], FILTER_SANITIZE_STRING);	
	return ax_post_stat($vote_code,$gender,$age,$location,$firstSM);
    }
    else if($_POST['operation'] == "post_vote" )
    {
	$category = filter_var($_POST['category'], FILTER_SANITIZE_STRING); //anv för att välja tabell nedan
	$vote_code = filter_var($_POST["vote_code"], FILTER_SANITIZE_STRING);
	return ax_post_vote($category,$vote_code);
    }
    else
    {
	echo "no match";
	exit;
    }
}
else{ echo "no operation"; FB::warn("no operation"); exit;}


//anropa när vote_code ändras
function resetSessionVotes()
{
   $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
    foreach ($cats as $currCategory)
    {
	for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
	    unset($_SESSION["vote_{$voteNr}_{$currCategory}"]);
    }
    FB::info("session reset");

}
//kollar om röster sparats i session för angiven category (lager,ale etc)
function needReReadSql($category)
{
    $reread_sql = true;
    for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
    {
	if (isset($_SESSION["vote_{$voteNr}_{$category}"]) && $_SESSION["vote_{$voteNr}_{$category}"] != "" )
	    $reread_sql = false;
    }
    if (!$reread_sql)
	FB::info("votes cached");
    return $reread_sql;
}


/*
 *SYSSTATUS
 *meddelar klient om tävling är öppen, håller på att stänga etc
 */
function ax_sysstatus($source,$clientInterval){
    header('Content-Type: application/json', true);
    $jsonReply = array();
    
    $jsonReply["usrmsg"] = "";
    $jsonReply["msgtype"] = "neutral"; //ok(green) neutral(orange), error(yellow), warning(red)

    
    //be klient ändra sin uppdateringsintervall? (om ändrad sen klient startade)
    if ($clientInterval != 0 && $clientInterval != SETTING_SYSSTATUS_INTERVAL)
    {
	$jsonReply["msgtype"] = "interval";
	$jsonReply["interval"] = SETTING_SYSSTATUS_INTERVAL;
    }
    //be klient sluta uppdatera status (vid bandbreddsproblem)
    //$clientInterval == 0, säkerhet om klient löper amok/hackar (ex skickar in en icke int), så får den stoppa.
    else if (SETTING_SYSSTATUS_DISABLE === true || $clientInterval == 0)
    {
	    $jsonReply["msgtype"] = "stop";
    }
    else{ //normalläge
	    $now = new DateTime(date("Y-m-d H:i:s"));
	    $open = new DateTime(SETTING_OPEN_DATE_OPEN);
	    $close = new DateTime(SETTING_OPEN_DATE_CLOSE);
	    if ($now < $open && SETTING_OPEN_DEBUG_ALWAYS_OPEN === false){
		$dtd = $now->diff($open);
		if ($dtd->d > 0)
		    $jsonReply["usrmsg"] = "Röstningen öppnar om " . $dtd->format("%d dagar och %hh");
		else if ($dtd->h > 0)
		    $jsonReply["usrmsg"] = "Röstningen öppnar om " . $dtd->format("%hh, %i minuter");
		else
		    $jsonReply["usrmsg"] = "Röstningen öppnar om " . $dtd->format("%i minuter");
	    }
	    if ($now > $open && $now < $close || SETTING_OPEN_DEBUG_ALWAYS_OPEN === true) //aktiv tävling
	    {
		//note SETTING_OPEN_DEBUG_ALWAYS_OPEN will output wierd numbers, but doen't matter...
		$dtdc = $now->diff($close);
		if ($dtdc->h == 0 && $dtdc->i < 10 && $dtdc->i > 0){
		    $jsonReply["usrmsg"] = "Röstningen stänger om " . ($dtdc->i + 1) . " minuter";
		    $jsonReply["msgtype"] = "error";
		}
		else if ($dtdc->h == 0 && $dtdc->i == 0){
		    $jsonReply["usrmsg"] = "Röstningen stänger om " . $dtdc->s . " sekunder!";
		    $jsonReply["msgtype"] = "warning";
		}
		else{
		    if ($source ="vstat.php"){

			$jsonReply["msgtype"] = "ok";
			if ($dtdc->d > 0)
			    $jsonReply["usrmsg"] = "Röstningen för folkets val stänger om " . $dtdc->format("%d dagar och %hh");
			else if ($dtdc->h > 0)
			    $jsonReply["usrmsg"] = "Röstningen för folkets val stänger om " . $dtdc->format("%hh och %i minuter");
			else if ($dtdc->i > 0)
			    $jsonReply["usrmsg"] = "Röstningen för folkets val stänger om " . $dtdc->format("%i minuter");
			else{
			    $jsonReply["usrmsg"] = "Röstningen för folkets val stänger om " . $dtdc->format("%s sekunder!");
			    $jsonReply["msgtype"] = "interval";
			    $jsonReply["interval"] = 1000; //special för statsida, öka takten
			}
		    }
		    else
			$jsonReply["msgtype"] = "ok";
		}
	    }
	    else if ($now > $close){
		$jsonReply["usrmsg"] = "Tävlingen är nu avslutad";
		$jsonReply["msgtype"] = "warning";
	    }


    }
    echo  json_encode($jsonReply);
    return true;
}


/*
 *REREAD (votes)
 */
function ax_reread($userStored_vote_code)
{
    header('Content-Type: application/json', true);
    $jsonReply = array();
    //mj php8
    if (!isset($_SESSION["vote_code_approved"]))
      $_SESSION["vote_code_approved"]  = false;
    if (!isset($_SESSION['vote_code']))
      $_SESSION['vote_code']  = "";
    $code_len = CONST_SETTING_VOTE_CODE_LENGTH;
    //mj fix, kod som varit ok i session tidigare ska direkt bli ogiltig vid radering av tecken / fel antal tecken
    if (strlen($userStored_vote_code) != $code_len){
         $_SESSION["vote_code_approved"]  = false;
         $_SESSION['vote_code'] = "";
    }
      
    //dubblekolla kod mod sql, returnera meddelande till user, utför bara om kod-in ändrats mot vad vi har serverside.
    if (($_SESSION["vote_code_approved"] !== true || $_SESSION['vote_code'] != $userStored_vote_code)  && strlen($userStored_vote_code) == $code_len){
	$jsonReply = codeCheck2($userStored_vote_code);
    }
    else if ($_SESSION["vote_code_approved"] === true){
	$jsonReply["msgtype"] = "ok-cached";
	$jsonReply["usrmsg"] = "Ok! nu kan du rösta nedanför";
    }
    //läs in sparade röster, om kod är ok.
    if ($_SESSION["vote_code_approved"] === true)
    {

	FB::info("approved");
	$approved_code = $jsonReply["vote_code"] = $_SESSION["vote_code"];
	$connOpen = false; //öppna en gång, i foreach.

	$cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);

	foreach ($cats  as $currCategory)
	{

	    if (needReReadSql($currCategory))
	    {
		FB::info("need reread");
		$sqlvotes = "";
		for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
		    $sqlvotes .= "vote_{$voteNr},";
		$sqlvotes = substr($sqlvotes,0,-1);

		if (!$connOpen){
		    include 'sqlopen_pdo.php';
		    $connOpen = true;
		}
		//$sqlsearch = "SELECT {$sqlvotes} from vote_cat_{$currCategory} WHERE vote_code = '" . $approved_code . "' LIMIT 1";
		$sqlsearch = "SELECT {$sqlvotes} from vote_cat_{$currCategory} WHERE vote_code = :approved_code LIMIT 1";
		$stmt = $db->prepare($sqlsearch);
		
		//if($result = mysql_query($sqlsearch) )
		if($stmt->execute(array(':approved_code' => $approved_code)) )
		{
		    
		    $row = $stmt->fetch(PDO::FETCH_ASSOC);
		    //if ($row = mysql_fetch_assoc($result))
		    if (!empty($row))
		    {
			FB::info($row,"rr");
			for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
			{
			    
			    $_SESSION["vote_{$voteNr}_{$currCategory}"] = $jsonReply["vote_{$voteNr}_{$currCategory}"] = $row["vote_{$voteNr}"];
			}


		    }
		    //mysql_free_result($result);
		    $stmt = null;
		}
		else{
		    //om inte annat resettar detta sessionvariabler vid felsökning
		    for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
			$jsonReply["vote_{$voteNr}_{$currCategory}"] = $_SESSION["vote_{$voteNr}_{$currCategory}"] = "";
		    FB::info("no votes");

		}


	    }
	    else
	    {
		for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
                   $jsonReply["vote_{$voteNr}_{$currCategory}"] = $_SESSION["vote_{$voteNr}_{$currCategory}"] ?? ""; //mj php8

	    }
	}
	if ($connOpen){
	    //include 'sqlclose.php';
	    $db = $stmt = null;
	}

	echo  json_encode($jsonReply);
	return true;

    }
    if ($jsonReply["usrmsg"] ?? "" != "")
    {
	$jsonReply["no votes"] = "";
	echo json_encode($jsonReply);
    }
    else //empty
	echo json_encode(array('no votes,no code' => '')); //nästan empty

    return true;
}


/*
 *POST_STAT
 */
function ax_post_stat($vote_code,$gender,$age,$location,$firstSM)
{
   $code_len = CONST_SETTING_VOTE_CODE_LENGTH;
   if(strlen($vote_code) < $code_len){
	echo "Felaktig röstkod!, ange din röstkod längst upp innan du registrerar";
	return;
    }
    if (strlen($location) > 49)
	$location = substr($location,0,49);
    if ($gender != "M" && $gender != "K")
	$gender = "M";

    $iage = (int)$age;
    if ($iage > 100)
	$iage = 100;
    if ($iage < 0)
	$iage = 0;

	    include 'sqlopen_pdo.php'; //global conn
    $stmt = $db->prepare("REPLACE INTO vote_visitor_stat (vote_code,gender,age,location,firstSM) VALUES (:vote_code,:gender,:age,:location,:firstSM)");
    if ($stmt->execute(array(':vote_code' => $vote_code,':gender' => $gender,':age' => $age,':location' => $location,':firstSM' => $firstSM,)))
      echo "Tack!";
    $stmt = null;
    $db = null;

    return false;
}


    /*
     *POST_VOTE
     */

function ax_post_vote($category,$vote_code){
    if(!isVotingOpen()){
	 echo "Röstningen är STÄNGD!";
	 return;
    }
    $code_len = CONST_SETTING_VOTE_CODE_LENGTH;
    if(strlen($vote_code) != $code_len ){
	echo "Felaktig röstkod!, ange din röstkod längst upp innan du röstar";
	return;
    }


    //$votes = array();
    $ivotes = array(); //integers

    $vote_count = 0;
    $filled_votes = 0;
    //collect all votes
    //client implementation is responsible for sending empty POSTS (ie not null) for all votes not filled in between 1 and CONST_SETTING_VOTES_PER_CATEGORY
    while (isset($_POST["vote_" . ($vote_count+1)])){
	$vote_count++;
	$vote = filter_var($_POST["vote_" . $vote_count], FILTER_SANITIZE_STRING);
	//tomma röster ok, annars ska det vara 3 siffror
	//röst som int retuneras, alt -1 för tom röst, eller sträng vid fel format
	$ivotes[$vote_count] = check_vote_format($category,$vote);
	if (!is_int($ivotes[$vote_count])){
	    echo "Röst rad #" . $vote_count . ", " . $ivotes[$vote_count];
	    return;
	}
	else if ($ivotes[$vote_count] != -1)
	    $filled_votes++;
    }
    if ($filled_votes = 0){
	 echo "Ange minst en röst först";
	 return false;
    }

    //detta kollas även i klient, och ska bara stängas av vid test! så...
    if (CONST_SETTING_VOTES_SERVER_SIDE_CHECKS === true)
    {
	if (($retStr = check_vote_rules($ivotes,false)) != ""){
	    echo $retStr; //user feedback on rule violation
	    return;
	}
    }
    //injection (or bug) check, as we use $category as part of sql-tablenames below
    if (!check_category($category)){
	echo "upsy-daisy!";
	return;
    }
    
    $vote_code = strtoupper($vote_code);
    $vote_code = trim($vote_code);
    
    //$sqls = "SELECT * from vote_cat_{$category} WHERE vote_code = '" . $vote_code . "' LIMIT 1";
    //if($result = mysql_query($sqls) )
    include 'sqlopen_pdo.php'; //global conn
    try{
	$sqls = "SELECT * from vote_cat_{$category} WHERE vote_code = :vote_code LIMIT 1";
	$stmt = $db->prepare($sqls);
	if ($stmt->execute(array(':vote_code' => $vote_code)))	
	{
	    $now = date("Y-m-d H:i:s");
	    //pre-store
	    $sqlparams = $sqlvalues = "";
	    foreach ($ivotes as $key => $vote)
	    {
		$sqlparams .=  ", vote_" . $key . ", vote_" . $key . "_dt";
		$sqlvalues .=  ($vote > 0 ? (",". $vote . ",'" . $now . "'" ) : (", NULL, NULL") );
	    }
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
	    if (empty($row)) //INSERT (inga röster lagrade sen tidigare för denna kod)
	    {
		//först en säkerhetskoll, dubbelkolla vote_code för att förhindra att ogiltiga koder injerceras förbi JS-kod, på nåt fult sätt
		//(detta gör vi bara första gången, och inte vid senare updates nedan, då har det ju redan kollats att koden är ok mot databas en gång här, om den   finns lagrad i tabeller)
		//$sqlcheck = "SELECT vote_code from vote_codes WHERE vote_code = '" . $checkvote_code . "' LIMIT 1";
		//if($checkresult = mysql_query($sqlcheck) )
		$stmt = null;
		$stmt = $db->prepare("SELECT vote_code from vote_codes WHERE vote_code = :vote_code LIMIT 1");
		if ($stmt->execute(array(':vote_code' => $vote_code)))	
		{
		     if (count($stmt->fetch(PDO::FETCH_ASSOC)) == 0){
			echo "Felaktig röstkod, ange din röstkod längst upp innan du röstar";
			//logga med flagga illegal_Attempt = 1
			if (SETTING_LOG_ALLOCATED_VOTE_CODES)
			{
			    //$rescheckupd = mysql_query("INSERT INTO vote_codes_allocation_log  (vote_code,allocation_DT,illegal_attempt) VALUES('" . $checkvote_code . "', '" . $now . "',1)");
			    $stmt = $db->prepare("INSERT INTO vote_codes_allocation_log  (vote_code,allocation_DT,illegal_attempt) VALUES(?, ?,1)");
			    $affected_rows = $stmt->execute(array($vote_code,$now));	
			}
			$stmt = null; $db = null;
			return false;
			
		     }
		    $stmt = null;
		}
		//spara röster
		//$sqls = "INSERT INTO vote_cat_{$category} (vote_code" . $sqlparams . ") VALUES('" . $vote_code . "'" . $sqlvalues . ")";
		$affected_rows = $db->exec("INSERT INTO vote_cat_{$category} (vote_code" . $sqlparams . ") VALUES('" . $vote_code . "'" . $sqlvalues . ")");

		//if (!$resulti = mysql_query($sqls))
		if ($affected_rows === FALSE || $affected_rows === 0)
		{
			echo "Ett fel uppstod vid registrering";
		}
		else{
		    echo "Rösterna har registrerats";
		    foreach ($ivotes as $key => $vote)
		    {
			if ($vote > 0) $_SESSION["vote_{$key}_{$category}"] = $vote;

		    }
		}
	    }
	    //else if (mysql_num_rows($result) > 0 ) //UPDATE
	    else //UPDATE
	    {
		$achanges = array();
		$changes = false;
		//check wich votes has changed since last submittal

		foreach ($ivotes as $key => $vote)
		{
		    if (($vote > 0 && $vote != $row['vote_' . $key]) || ($vote == -1 && $row['vote_' . $key] != NULL) ){
			$achanges[$key] = true;
			$changes = true;
		    }
		    else
			$achanges[$key] = false;
		}

		if (!$changes){

		    echo "Rösterna redan registrerade";
		    //mysql_free_result($result);
		    $stmt = null; $db = null;
		    //include 'sqlclose.php';
		    return true;
		}


		$now = date("Y-m-d H:i:s");


		$sqls = "UPDATE vote_cat_{$category} SET manreg_paper_vote=NULL ";
		foreach ($achanges as $key => $change)
		{
		    if ($change)  $sqls .= ", vote_{$key}=" . ($ivotes[$key] == -1 ? ("NULL") : ($ivotes[$key])) . ", vote_{$key}_DT='" . $now . "' ";
		}

		//$sqls = substr($sqls,0,-2); //ta bort mellanslag-komma för sista
		$sqls .= " WHERE vote_code = '" . $vote_code . "'";
		$affected_rows = $db->exec($sqls);
		//if ($resultu = mysql_query($sqls)){
		if ($affected_rows > 0 ){
		    echo "Rösterna har uppdaterats";
		    FB::info($affected_rows,"sql update:");
		    foreach ($achanges as $key => $change)
		    {
			if ($change) $_SESSION["vote_{$key}_{$category}"] = ($ivotes[$key] == -1 ? ("") : ($ivotes[$key]) );

		    }
		    if (SETTING_LOG_CHANGED_VOTES && $changes)
		    {
         $sqlHvalues = ""; //mj php8
			foreach ($ivotes as $key => $vote)
			{
			    //($vote > 0 ? (",". $vote . ",'" . $now . "'" ) : (", NULL, NULL") )
			    $sqlHvalues .=  ($row["vote_{$key}"] == "" ? (", NULL") : (", " .$row["vote_{$key}"])) . "," . ($row["vote_{$key}_dt"] == "" ? ("NULL") : ("'" . $row["vote_{$key}_dt"]) . "'");
			}
			$sqls = "INSERT INTO vote_cat_{$category}_history (vote_code" . $sqlparams . ",dt) VALUES('" . $vote_code . "'" . $sqlHvalues . ", '{$now}')";


			//$resulti = mysql_query($sqls);
			$affected_rows = $db->exec($sqls);
			FB::info($affected_rows,"log changed votes:");
			if (!$affected_rows)
			    FB::warn($sqls, "log changed votes:");
			//inget reponse för detta, ej viktigt för user
		    }
		}
		else
		{
		    FB::error($sqls,"update fel:");
		    FB::info($affected_rows,"sql update:");
		    echo "Ett fel uppstod vid uppdatering";
		}

	    }


	}
	else
	    echo "Severe error";
	}
    catch (PDOException $ex)
    {
	echo "Severe outer exception";
	FB::error($ex,"sql exception: ");
    }
    $stmt = null;
    $db = null;
    //include 'sqlclose.php';
    return true;

} //EO post_vote

//kontrollerar röstkod mot SQL vote_codes-tabell
//lagrar OK kod i SESSION, samt retunerar svar till anropare.
function codeCheck2($stored_client_code = "")
{

	$count = 0;
	$vote_code_old = $_SESSION['vote_code'] ?? ""; //mj php8
   $code_len = CONST_SETTING_VOTE_CODE_LENGTH;
	if (strlen($stored_client_code) == $code_len )
	    $vote_code = $stored_client_code;
	else
	    $vote_code =$_GET["vote_code"] ?? "";  //mj php8
	$vote_code = strtoupper($vote_code);
	$vote_code = trim($vote_code);	    
	$count = check_vote_code_sql($vote_code,null,$vote_code);

	$jsonReply = array();
	if ($count > 0)
	{
	    if ($vote_code_old != $vote_code)
		resetSessionVotes(); //nollställ, annars används föregående kods sessionröster vid nästa reread()
	    $_SESSION["vote_code"] = $vote_code;
	    $_SESSION["vote_code_approved"] = true;
	    $jsonReply["usrmsg"] = "Ok! nu kan du rösta nedanför";
	    $jsonReply["msgtype"] = "ok";
	    $jsonReply["vote_code"] = $vote_code; //retunera formatterad/uppercasad
	}
	else if ($count == 0) //mj php8
	{
	    unset($_SESSION["vote_code"]);
	    $_SESSION["vote_code_approved"] = false;
	    $jsonReply["usrmsg"] = "Ogiltig kod. Försök igen. Koden finns på programmet.";
	    $jsonReply["msgtype"] = "warning";

	}
	else
	{   //-1
	    unset($_SESSION["vote_code"]);
	    $_SESSION["vote_code_approved"] = false;
	    $jsonReply["usrmsg"] = mysql_error();
	    $jsonReply["msgtype"] = "error";
	}
	return $jsonReply;
}
