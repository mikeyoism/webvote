<?php session_start();
/*Webvote - functions for manual registration of paper votes, and public voting stations (ie computer screens, not mobile devices)
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
date_default_timezone_set("Europe/Stockholm"); //default är annars greenwitch
require_once "_config.php";
require_once "vote_common.php";



//hantera klientanrop
if(isset($_POST['operation']))
{


    if ($_POST['operation'] == "mr_pre_codecheck")
    {
	 $vote_code = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
	 return ax_mr_pre_codecheck($vote_code);
    }
    else if($_POST['operation'] == "mr_post_votes" )
    {
	 return ax_mr_post_votes();
    }
    else if($_POST['operation'] == "mr_post_votes_public" )
    {
	 return ax_mr_post_votes_public();
    }
    else if ($_POST['operation'] == "mr_reread")
    {
	 $userStored_vote_code = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
	 return ax_mr_reread($userStored_vote_code);
    }
    else
    {
	echo "no match";
	exit;
    }
}
else{ echo "no operation"; FB::warn("no operation"); exit;}

function mr_CodeReply($vote_code, $status) //$status = status från check_vote_code_sql
{
	header('Content-Type: application/json', true);
	$jsonReply = array();
	if ($status == -1)
	{
	    $jsonReply["usrmsg"] = "fel antal tecken, kontrollera att DU skrev rätt!";
	    $jsonReply["msgtype"] = "warning";
	}
	else if ($status == 0)
	{
	    $jsonReply["usrmsg"] = "Ogiltig röstkod, kontrollera att DU skrev rätt";
	    $jsonReply["msgtype"] = "warning";
	}
	else if ($status == 1)
	{
	    $jsonReply["usrmsg"] = "Kod OK!";
	    $jsonReply["msgtype"] = "ok";
	}
	else if ($status == 2)
	{
	    $jsonReply["usrmsg"] = "databasfel, kontakta admin";
	    $jsonReply["msgtype"] = "error";
	}

	$jsonReply["vote_code"] = $vote_code; //ret till client formatterad
	echo json_encode($jsonReply);

}
/*
*pre_codecheck (votes) (anropas direkt av klient direkt efter inmatning av kod för att förvarna funktionär direkt)
*/    
function ax_mr_pre_codecheck($vote_code){
	
   $vote_code = strtoupper($vote_code);
   $vote_code = trim($vote_code);
   $source = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
   $manreg_papervote = 1; //manreg_adm
   if ($source == "manreg.php")
    $manreg_papervote = 2;

   $status = check_vote_code_sql($vote_code,$manreg_papervote);//mr_AuthenticateCode($vote_code);
   mr_CodeReply($vote_code,$status);
}

/*
 *POST_VOTE (klient mangreg_adm.php) - for paper vote registration by competition management, ie, no checks if competition is still open etc.
 */
function ax_mr_post_votes(){
   header('Content-Type: application/json', true);
   $jsonReply = array();
   $jsonReply["crc"] = 1;
   $replystrOnError = "";
   $replystrOnIllegal = "";
   $vote_code = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
   $vote_code = strtoupper($vote_code);
   $vote_code = trim($vote_code);
   //kolla kod, och retunera direkt vid fel...
   $status = check_vote_code_sql($vote_code,1);//mr_AuthenticateCode($vote_code);

   if ($status != 1)
       return mr_CodeReply($vote_code,$stats);
   //0 = nej, 1=korrigerat, 2=bekräftar fel
   //skickas av klient, för att funk ska varnas och kunna korrigera ev fel han själv gjort
   //1=korrigerat har samma funktion nedan som 0 = nej serverside. , rösterna gås igenom på nytt och är det fortfarande fel skickas medelande på nytt om fel
   $confirmed_error = filter_var($_POST['confirmed_error'], FILTER_SANITIZE_STRING);
   $p = 0;
   $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
   $catspub  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC);
   $formaterror = false;
   $illegalVote = false;

   $voteStoreArr = array();
   $voteStoreLegal = array();
   $voteStoreError = array();

   //kontrollera format på röster, och att regler för dubbelröster följs
   foreach ($cats  as $category)
   {
       $formaterrorLocal = false;
       $illegalVoteLocal = false;

       $votes = array();
       $ivotes = array();
       $vote_count = 0;
       while (isset($_POST["vote_" . ($vote_count + 1) . "_{$category}"])){
	    $vote_count++;
	    $votes[$vote_count] = trim(filter_var($_POST["vote_{$vote_count}_{$category}"], FILTER_SANITIZE_STRING));
	    //tomma röster ok, annars ska det vara 3 siffror, och inom rätt område, kolla...
	    $ivotes[$vote_count] = check_vote_format($category,$votes[$vote_count]);
	     if (!is_int($ivotes[$vote_count])){
		 $replystrOnError .= $catspub[$p] . ", röst {$vote_count}: " . $ivotes[$vote_count] . "\n";
		 $formaterrorLocal = true;
	     }

       }
       FB::info($vote_count, "vote count");

       if (($retStr = check_vote_rules($ivotes,true)) != ""){
	  $illegalVoteLocal = true;
	  $replystrOnIllegal .= $catspub[$p] . ": Diskvalificera? " . $retStr;
	  $jsonReply["crc"] = 3;

       }

       $voteStoreArr[$category] = $ivotes;//spara undan, för sparandet till sql nedan
       $voteStoreLegal[$category] = !$illegalVoteLocal;
       $voteStoreError[$category] = !$formaterrorLocal;
       if ($formaterrorLocal)
	   $formaterror = true;
       if ($illegalVoteLocal)
	   $illegalVote = true;

       $p++;
   }
   $jsonReply["cc"] = $confirmed_error;
   //om fel-format eller regelbrott och inte confirmed skickats så ska vi sluta här, och retunera felen (för konfirmering)
   if ($formaterror && $confirmed_error != "2")
   {
       $jsonReply["msgtype"] = "warning";
       $jsonReply["usrmsg"] = $replystrOnError;
   }
   if ($illegalVote && $confirmed_error  != "2")
   {
       $jsonReply["msgtype"] = "warning";
       $jsonReply["usrmsg"] = $replystrOnIllegal;
   }
   if (($formaterror || $illegalVote )&& $confirmed_error != "2")
   {
       echo json_encode($jsonReply);
       return;
   }
   $p = 0;
   $replyStr = ""; // om inget sätts i denna, ok:ar vi den längst ner sen
   //regga röster
   foreach ($cats  as $category)
   {
      $ivotes = $voteStoreArr[$category];
      if ($ivotes == NULL) //vid error ovan
	   continue;
       //vi är snälla och diskar bara per kategori
      if ($voteStoreLegal[$category] != true)
      {
	   $replyStr .= $catspub[$p] . ": diskvalificerad!\n";
	   continue;
      }
       if ($voteStoreError[$category] != true){
	   $replyStr .= $catspub[$p] . ": sparas ej, fel format!\n";
	   continue;
       }
       $sqls = "SELECT * from vote_cat_{$category} WHERE vote_code = :vote_code LIMIT 1";
       include 'sqlopen_pdo.php'; //global conn
       $stmt = $db->prepare($sqls);
       //if($result = mysql_query($sqls) )
       if($stmt->execute(array(':vote_code' => $vote_code)) )
       {

	  $now = date("Y-m-d H:i:s");
	  //pre-store
	  $sqlparams = $sqlvalues = "";
	  foreach ($ivotes as $key => $vote)
	  {
	      $sqlparams .=  ", vote_" . $key . ", vote_" . $key . "_dt";
	      $sqlvalues .=  ($vote > 0 ? (",". $vote . ",'" . $now . "'" ) : (", NULL, NULL") );
	      $sqlvalues_update .=  ", vote_{$key}=" . ($ivotes[$key] == -1 ? ("NULL") : ($ivotes[$key])) . ", vote_{$key}_DT='" . $now . "' ";
	  }
	  $row = $stmt->fetch(PDO::FETCH_ASSOC);
	  //$rowCount = count($row);   	  
	   //if (mysql_num_rows($result) == 0) //INSERT (inga röster lagrade sen tidigare för denna kod)
	   if (empty($row))
	   {
	       //detta kollas inte vid manreg, det skulle ge en massa kontrollfrågor till funktionär för alla som inte röstat i alla klasser...vilket kan antas vara rätt många
	       //vi reggar tomma manreg-röster med nullvärden så det syns att de "kontrollräknats"
	       //    if ($ivote_1 < 1 && $ivote_2 < 1 && $ivote_3 < 1){
	       //   //jsonreply(array('error' => 'ange minst en röst först'));
	       //   echo "Ange minst en röst först";
	       //   return false;
	       //    }
	       //manreg_paper_vote=2 = röstat via publik dator, 1= röst reggad av funktionär (manreg_adm)

		
	       $sqls = "INSERT INTO vote_cat_{$category} (vote_code" . $sqlparams . ", manreg_paper_vote) VALUES('" . $vote_code . "'" . $sqlvalues . ",1)";
	       $affected_rows = $db->exec($sqls);
		
	       	              
	        //spara röster
	       //if (!$resulti = mysql_query($sqls))
	       if ($affected_rows === FALSE || $affected_rows === 0)
		{
		       $replyStr .= "Oväntat fel vid registrering av klass [" . $catspub[$p] . "] dubbelkolla värden!\n";
		       FB::warn($sqls, "insert (band new ) votes failed");
		}
	       else{
		   //echo "Rösterna har registrerats";
		   FB::info($affected_rows,"inserted (brand new) votes");
	       }
	   }
	   else  //UPDATE
	   //else if (mysql_num_rows($result) > 0 ) //UPDATE
	   {
	       //$row = mysql_fetch_assoc($result);
	       //tillåt enligt inställning, eller även om funktionär redan reggat en gång innan (och skrev fel kan man anta)
	       if (CONST_SETTING_PAPERVOTE_CAN_OVERWRITE_WEBVOTE == true || $row['manreg_paper_vote'] == 1)
	       {
		   //om röster finns innan för kategorin, så skrivs de över helt här, vi tar inte (och ska inte) hänsyn till om individuella röst 1/2/3 har ändrats eller ej
		   //som vi gör vid uppdatering i webbröstningssidan. Har man lämnat in papperslapp är det den som gäller helt enkelt.

		   $now = date("Y-m-d H:i:s");
		   $sqls = "UPDATE vote_cat_{$category} SET manreg_paper_vote=1 {$sqlvalues_update}";
		   $sqls .= " WHERE vote_code = '" . $vote_code . "'";

		   $affected_rows = $db->exec($sqls);
		   //if ($resultu = mysql_query($sqls))
		   if ($affected_rows > 0 )
		   {
		      FB::info($affected_rows,"sql update:");
		       //echo "Rösterna har uppdaterats";
		       //uppdatera loggen, dock inte vid manreg +1 gång, tämligen ointressant om funktionär skrivit fel och reggar en gång till...då vill vi bara veta
		       //orginalrösten user gjorde i webbröstsystemet
		       if (SETTING_LOG_CHANGED_VOTES && $row['manreg_paper_vote'] != 1)
		       {
			    foreach ($ivotes as $key => $vote)
			    {
				$sqlHvalues .=  ($row["vote_{$key}"] == "" ? (", NULL") : (", " .$row["vote_{$key}"])) . "," . ($row["vote_{$key}_dt"] == "" ? ("NULL") : ("'" . $row["vote_{$key}_dt"]) . "'");
			    }
			   $sqls = "INSERT INTO vote_cat_{$category}_history (vote_code {$sqlparams},dt,manreg_paper_vote) "
								  . "VALUES ('" . $vote_code . "'" . $sqlHvalues . ", '{$now}'," . ($row['manreg_paper_vote'] == "" ? ("NULL") : ($row['manreg_paper_vote'])) . ")";
                                                                     

			   //$replyStr .= $sqls;
			   //$resulti = mysql_query($sqls);
			   $affected_rows = $db->exec($sqls);
			   FB::info($affected_rows,"log history votes:");
			   //inget reponse för detta, ej viktigt för user
			   if (!$affected_rows)
			       FB::warn($sqls, "log history votes:");

		       }
		   }
		   else
		   {
		       echo json_encode(array('test' => $formaterror, 'test2' => 'h' ));
		       //jsonreply(array('error' => 'ett fel uppstod vid uppdatering'));
		       $replyStr .= "Oväntat fel vid registrering av klass [" . $catspub[$p] . "] dubbelkolla värden!\n";
		   }
		   //mysql_free_result($result);
	       }
	       else{
		   $replyStr .= $catspub[$p] . ": Diskas, redan reggad som webbröst.\n";
		   FB::warn("diskvlificerad, redan webbröst");
	       }
	   }

       }
       else
	    $replyStr .= $catspub[$p] .  ": severe error, kontakta Micke\n";
       //include 'sqlclose.php';
       $db = $stmt  = null;

       $p++;
   }

   if ($replyStr == "")
   {
       $jsonReply["msgtype"] = "ok";
       $jsonReply["usrmsg"] = "OK!";
   }
   else
   {
       if ($confirmed_error == "2") //funk bekräftar fel
	   $jsonReply["msgtype"] = "ok"; //vi gjorde vad vi kunde ovan, det som sparades sparades, nu går vi vidare....
       else
	   $jsonReply["msgtype"] = "warning"; //red

       $jsonReply["usrmsg"] = $replyStr;

   }
   echo json_encode($jsonReply);
   return;

} //EO mr_post_votes

/*
*POST_VOTE_PUBLIC (publika röstdatorer, ej för funktionärer, klient: manregp.php)
*/
function ax_mr_post_votes_public(){
   //public så här svarar vi bara med fel om nåt inte stämmer, så får user försöka på nytt.

   header('Content-Type: application/json', true);
   $jsonReply = array();
   $jsonReply["crc"] = 1;

   if(!isVotingOpen()){
       $jsonReply["msgtype"] = "warning"; //red
       $jsonReply["usrmsg"] = "Röstningen är stängd, du kan inte rösta";
       echo json_encode($jsonReply);
       return;
   }
   $replystrOnError = "";
   $replystrOnIllegal = "";
   $vote_code = filter_var($_POST['vote_code'], FILTER_SANITIZE_STRING);
   $vote_code = strtoupper($vote_code);
   $vote_code = trim($vote_code);
   //kolla kod, och retunera direkt vid fel...
   $status = check_vote_code_sql($vote_code,2);// mr_AuthenticateCode($vote_code,2);

   if ($status != 1)
       return mr_CodeReply($vote_code,$stats);

   $p = 0;
   $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
   $catspub  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC);
   $formaterror = false;
   $illegalVote = false;

   $voteStoreArr = array();
   $voteStoreLegal = array();
   $voteStoreError = array();

   //kontrollera format på röster, och att regler för dubbelröster följs
   foreach ($cats  as $category)
   {
       $formaterrorLocal = false;
       $illegalVoteLocal = false;



       $votes = array();
       $ivotes = array();
       $vote_count = 0;
       while (isset($_POST["vote_" . ($vote_count + 1) . "_{$category}"])){
	    $vote_count++;
	    $votes[$vote_count] = trim(filter_var($_POST["vote_{$vote_count}_{$category}"], FILTER_SANITIZE_STRING));
	    //tomma röster ok, annars ska det vara 3 siffror, och inom rätt område, kolla...
	    $ivotes[$vote_count] = check_vote_format($category,$votes[$vote_count]);
	     if (!is_int($ivotes[$vote_count])){
		 $replystrOnError .= $catspub[$p] . ", röst {$vote_count}: " . $ivotes[$vote_count] . "\n";
		 $formaterrorLocal = true;
	     }

       }
       FB::info($vote_count, "vote count");




       //$arrcheck = array($ivote_1,$ivote_2,$ivote_3);
       //detta kollas INTE i klient vid manreg...så
       if (($retStr = check_vote_rules($ivotes,false)) != ""){
	  $illegalVoteLocal = true;
	  $replystrOnIllegal .= $catspub[$p] . ": " . $retStr;
	  $jsonReply["crc"] = 3;

       }


       $voteStoreArr[$category] = $ivotes;//array($ivote_1,$ivote_2, $ivote_3); //spara undan, för sparandet till sql nedan

       $voteStoreLegal[$category] = !$illegalVoteLocal;
       $voteStoreError[$category] = !$formaterrorLocal;
       if ($formaterrorLocal)
	   $formaterror = true;
       if ($illegalVoteLocal)
	   $illegalVote = true;

       $p++;
   }

   //returnera vid fel ovan
   if ($formaterror)
   {
       $jsonReply["msgtype"] = "warning";
       $jsonReply["usrmsg"] = $replystrOnError;
   }
   if ($illegalVote)
   {
       $jsonReply["msgtype"] = "warning";
       $jsonReply["usrmsg"] = $replystrOnIllegal;
   }
   if (($formaterror || $illegalVote ))
   {
       echo json_encode($jsonReply);
       return;
   }
   $p = 0;
   $replyStr = ""; // om inget sätts i denna, ok:ar vi den längst ner sen

   //regga röster
   foreach ($cats  as $category)
   {


      $ivotes = $voteStoreArr[$category];

      if ($ivotes == NULL) //vid error ovan
	   continue;

       //$sqls = "SELECT * from vote_cat_{$category} WHERE vote_code = '" . $vote_code . "' LIMIT 1";
       include 'sqlopen_pdo.php'; //global conn
	$sqls = "SELECT * from vote_cat_{$category} WHERE vote_code = :vote_code LIMIT 1";
	$stmt = $db->prepare($sqls);
	if ($stmt->execute(array(':vote_code' => $vote_code)))	       
       //if($result = mysql_query($sqls) )
       {

	  $now = date("Y-m-d H:i:s");
	  //pre-store
	  $sqlparams = $sqlvalues = "";
	  foreach ($ivotes as $key => $vote)
	  {
	      $sqlparams .=  ", vote_" . $key . ", vote_" . $key . "_dt";
	      $sqlvalues .=  ($vote > 0 ? (",". $vote . ",'" . $now . "'" ) : (", NULL, NULL") );
	      $sqlvalues_update .=  ", vote_{$key}=" . ($ivotes[$key] == -1 ? ("NULL") : ($ivotes[$key])) . ", vote_{$key}_DT='" . $now . "' ";
	  }
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
	    //$rowCount = count($row);   	  
	   //if (mysql_num_rows($result) == 0) //INSERT (inga röster lagrade sen tidigare för denna kod)
	   if (empty($row)) //INSERT (inga röster lagrade sen tidigare för denna kod)
	   {
	       //detta kollas inte vid manreg, det skulle ge en massa kontrollfrågor till funktionär för alla som inte röstat i alla klasser...vilket kan antas vara rätt många
	       //vi reggar tomma manreg-röster med nullvärden så det syns att de "kontrollräknats"
	       //    if ($ivote_1 < 1 && $ivote_2 < 1 && $ivote_3 < 1){
	       //   //jsonreply(array('error' => 'ange minst en röst först'));
	       //   echo "Ange minst en röst först";
	       //   return false;
	       //    }
	       //manreg_paper_vote=2 = röstat via publik dator, 1= röst reggad av funktionär (manreg_adm)

	       //spara röster
	       $sqls = "INSERT INTO vote_cat_{$category} (vote_code" . $sqlparams . ", manreg_paper_vote) VALUES('" . $vote_code . "'" . $sqlvalues . ",2)";
		$affected_rows = $db->exec($sqls);

		//if (!$resulti = mysql_query($sqls))
		if ($affected_rows === FALSE || $affected_rows === 0)
		{
		       $replyStr .= "Oväntat fel vid registrering av klass [" . $catspub[$p] . "] dubbelkolla värden!\n";
		       FB::warn($sqls, "insert (band new ) votes failed");
		}
	       else{
		   //echo "Rösterna har registrerats";
		   FB::info($affected_rows,"inserted (brand new) votes");
	       }
	   }
	   //else if (mysql_num_rows($result) > 0 ) //UPDATE
	   else//UPDATE
	   {
	       //$row = mysql_fetch_assoc($result);
	       //tillåt enligt inställning, eller även om funktionär redan reggat en gång innan (och skrev fel kan man anta)
	       //obs, detta sker från publik dator, helt ok att skriva över ev tidigare röster man lagt via mobil....
	       //if (CONST_SETTING_PAPERVOTE_CAN_OVERWRITE_WEBVOTE == true || $row['manreg_paper_vote'] == 1)
	       //{
		   //om röster finns innan för kategorin, så skrivs de över helt här, vi tar inte (och ska inte) hänsyn till om individuella röst 1/2/3 har ändrats eller ej
		   //som vi gör vid uppdatering i webbröstningssidan.

		   $now = date("Y-m-d H:i:s");

		   $sqls = "UPDATE vote_cat_{$category} SET manreg_paper_vote=2 {$sqlvalues_update}";

		   $sqls .= " WHERE vote_code = '" . $vote_code . "'";

		  $affected_rows = $db->exec($sqls);
		  //if ($resultu = mysql_query($sqls)){
		  if ($affected_rows > 0 ){
		      FB::info($affected_rows,"sql update:");
		       //echo "Rösterna har uppdaterats";
		       //uppdatera loggen, dock inte vid manreg +1 gång, tämligen ointressant om funktionär skrivit fel och reggar en gång till...då vill vi bara veta
		       //orginalrösten user gjorde i webbröstsystemet
		       if (SETTING_LOG_CHANGED_VOTES)
		       {
			    foreach ($ivotes as $key => $vote)
			    {
				$sqlHvalues .=  ($row["vote_{$key}"] == "" ? (", NULL") : (", " .$row["vote_{$key}"])) . "," . ($row["vote_{$key}_dt"] == "" ? ("NULL") : ("'" . $row["vote_{$key}_dt"]) . "'");
			    }
			   $sqls = "INSERT INTO vote_cat_{$category}_history (vote_code {$sqlparams},dt,manreg_paper_vote) "
								  . "VALUES ('" . $vote_code . "'" . $sqlHvalues . ", '{$now}'," . ($row['manreg_paper_vote'] == "" ? ("NULL") : ( $row['manreg_paper_vote'])) . ")";


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
		       echo json_encode(array('test' => $formaterror, 'test2' => 'h' ));
		       //jsonreply(array('error' => 'ett fel uppstod vid uppdatering'));
		       $replyStr .= "Oväntat fel vid registrering av klass [" . $catspub[$p] . "] dubbelkolla värden!\n";
		       FB::warn($sqls,"update:");
		   }
		   //mysql_free_result($result);

	   }


       }
       else
	    $replyStr .= $catspub[$p] .  ": severe error, kontakta Micke\n";
       //include 'sqlclose.php';
       $db = $stmt = null;
       $p++;
   }

   if ($replyStr == "")
   {
       $jsonReply["msgtype"] = "ok";
       $jsonReply["usrmsg"] = "OK!, tack för dina röster.";
   }
   else
   {
     $jsonReply["msgtype"] = "warning"; //red

       $jsonReply["usrmsg"] = $replyStr;

   }
   echo json_encode($jsonReply);
   return;

} //EO mr_post_votes_public



/*
 *REREAD (votes)
 */
function ax_mr_reread($userStored_vote_code){

   header('Content-Type: application/json', true);
   $jsonReply = array();

   $jsonReply["msgtype"] ="ok"; //always, mer enhetligt för klient att skriva ut med färg, även om meningslös här
   $jsonReply["vote_code"] = $userStored_vote_code;
   if (strlen($userStored_vote_code) != 3)
   {
       $jsonReply["novotes"] = "1";
       echo  json_encode($jsonReply);
       return;
   }
   $connOpen = false; //öppna en gång, i foreach.
   $hasvotes = false;
   $cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
   foreach ($cats  as $currCategory)
   {
	  $sqlvotes = "";
	  for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
	      $sqlvotes .= "vote_{$voteNr},";
	  $sqlvotes = substr($sqlvotes,0,-1);
	  
	   if (!$connOpen){
	       include 'sqlopen_pdo.php';
	       $connOpen = true;
	   }
	  //$sqlsearch = "SELECT {$sqlvotes} from vote_cat_{$currCategory} WHERE vote_code = '" . $userStored_vote_code . "' LIMIT 1";
	  $sqlsearch = "SELECT {$sqlvotes} from vote_cat_{$currCategory} WHERE vote_code = :userStored_vote_code LIMIT 1";	   
	 $stmt = $db->prepare($sqlsearch);
	 
	 //if($result = mysql_query($sqlsearch) )
	 if($stmt->execute(array(':userStored_vote_code' => $userStored_vote_code)) )
	 {
	     $row = $stmt->fetch(PDO::FETCH_ASSOC);
	     //if ($row = mysql_fetch_assoc($result))
	     if (!empty($row))
	       {
		   for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
		   {
		       $jsonReply["vote_{$voteNr}_{$currCategory}"] = $row["vote_{$voteNr}"];
		   }
		   $hasvotes = true;

	       }
	       //mysql_free_result($result);
	       $stmt = null;
	   }
	   else{
	       //om inte annat resettar detta sessionvariabler vid felsökning
		for ($voteNr = 1; $voteNr <= CONST_SETTING_VOTES_PER_CATEGORY; $voteNr++)
		    $jsonReply["vote_{$voteNr}_{$currCategory}"] = "";
	   }
   }
   if ($connOpen){
       //include 'sqlclose.php';
       $db = $stmt = null;
   }
   if (!$hasvotes)
       $jsonReply["novotes"] = "1";
   echo  json_encode($jsonReply);
   return true;

  }
