<?php session_start();
/*Webvote - helpers & common
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

function isVotingOpen()
{
    $now = date("Y-m-d H:i:s");
    if (( $now > SETTING_OPEN_DATE_OPEN  && $now < SETTING_OPEN_DATE_CLOSE) || SETTING_OPEN_DEBUG_ALWAYS_OPEN === true)
	return true;
    return false;
}

//retunerar röst som integer om ok eller tom röst => -1, annars en sträng med felmeddelande (använd is_int)
function check_vote_format($category, $str_vote)
{
    //om någon anser att "0" = ingen röst, så tillåt det genom att konvertera till tomt (som nedan blir int -1)
    if ($str_vote === "0")
	$str_vote = "";
	$beer_ids_len = CONST_SETTING_BEERID_NUMBERSPAN_LENGTH;
    if(strlen($str_vote) != $beer_ids_len  && strlen($str_vote) > 0)
       return "Fel antal siffror";
    $ivote = -1; //tom röst
    
    if(strlen($str_vote)> 0)
	 $ivote = (int) $str_vote; // = 0 vid icke numerisk int
    
    if ($ivote === 0)
	return "Ogiltig röst (" . $str_vote . "), ej heltal";

    //kontroller röst är i intervall för aktuell kategori (ex mellan 201-299 för grön)
    if ($ivote != -1){
	$nrspan = unserialize(CONST_SETTING_CATEGORIES_NUMBERSPAN);
	$curCatSpan = $nrspan[$category];
	if ($curCatSpan != ""){
	    
	    $minmax = explode("-",$curCatSpan);
	    if (count($minmax) == 2)
	    {
		$min = (int)$minmax[0];
		$max = (int)$minmax[1];
		if ($min != 0 && $max != 0)
		{
		    if ($ivote != -1 && ($ivote < $min || $ivote >$max))
		    {
			return "Ogiltig röst (" . $ivote . "), giltigt område är " . $min . "-" . $max;
			
		    }
			    
		}
		
	    }
	}
    }
    return $ivote; //sucess
}
//db helper, check if $category is blessed, use to eliminate injection when eg used as part of tablename
function check_category($category)
{
	$cats  = unserialize(CONST_SETTING_CATEGORIES_SYS);
	foreach ($cats  as $currCategory)
	    if ($category === strtolower($currCategory) )
		return true;
	
	return false;
}
//$ivotes = integer array of votes to check for one category
//$admin = use strings suitable for competition management (3rd person)
function check_vote_rules($ivotes, $admin = true)
{
    if (CONST_SETTING_VOTES_PER_CATEGORY_SAME != -1 || CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL)
    {
	$maxfound = 1;
	$maxfound_max = 1;
	
	$arrcount = array_count_values($ivotes); //arr med antal av varje värde, ex ([100] => 2, [101] => 1)
	
	$empty_votes_exist = false;
	foreach ($arrcount as $key => $v)
	{
	    if ($key != -1){ //ignorera tomma fält  som är = -1
		
		if ($v > CONST_SETTING_VOTES_PER_CATEGORY_SAME && CONST_SETTING_VOTES_PER_CATEGORY_SAME != -1){
		    
		    return ($admin != true ? ("Du kan rösta max " . CONST_SETTING_VOTES_PER_CATEGORY_SAME . " " .(CONST_SETTING_VOTES_PER_CATEGORY_SAME > 1 ? ("gånger"):("gång")) . " på samma öl!") :
					     ("Fler än " . CONST_SETTING_VOTES_PER_CATEGORY_SAME . " röster på samma öl, ej tillåtet")
		           );
		}
		else if  ($v > $maxfound)
		{
		    if ($v > $maxfound_max)
			$maxfound_max = $v;
		}
	    }
	    else
		$empty_votes_exist = true;
	}
	if ($maxfound_max > 1 && $empty_votes_exist && CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL === true)
	{
	    return ($admin != true ? ('Alla ' . CONST_SETTING_VOTES_PER_CATEGORY . ' röster måste fyllas i om du röstar +1 gång på samma öl!') :
				     ('Alla ' . CONST_SETTING_VOTES_PER_CATEGORY . ' röster måste fyllas i vid dubbelröst på samma öl.')
		   );	    

	}
	else if (CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL === true && $empty_votes_exist) //Typically when weighted Gold, Silver...
	{
	    return ($admin != true ? ('Alla röster måste fyllas i, försök igen!') :
				     ('Alla röster måste fyllas i')
		   );	   		    
	}
    }
    return ""; //empty = OK voting
    
}
//ret -1 ogiltigt format, 0 = ogiltig kod, 1 = giltig kod
function check_vote_code_sql($vote_code,$manreg_paper_vote = null,$vote_code_old = "")
{
    $count = 0;
    $code_len = CONST_SETTING_VOTE_CODE_LENGTH;
    if(strlen($vote_code) != $code_len){
	return  -1;
    }
    else{
	    include 'sqlopen_pdo.php';
	    $stmt = $db->prepare("SELECT vote_code from vote_codes WHERE vote_code = :vote_code LIMIT 1" );
	    $stmt->bindValue(':vote_code', $vote_code, PDO::PARAM_STR);
	    if ($stmt->execute()){
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		//$count = count($row);
		if (!empty($row) > 0 && SETTING_LOG_ALLOCATED_VOTE_CODES)
		{
		    $count = 1;
		    //manreg_paper_vote=2 = röstat via publik dator, 1= röst reggad av funktionär (manreg_adm)
		    if (strlen($vote_code_old) == $code_len && $vote_code_old != $vote_code) //indikera att koden ändrats = statistik för om flera användare delar enhet
		    {
			$stmt = $db->prepare("INSERT INTO vote_codes_allocation_log  (vote_code,allocation_DT,old_code,manreg_paper_vote) VALUES(:vote_code, :date ,:vote_code_old, :manreg_paper_vote)");
			$stmt->execute(array(':vote_code' => $vote_code, ':date' => date("Y-m-d H:i:s"), ':vote_code_old' => $vote_code_old, ':manreg_paper_vote' => $manreg_paper_vote));
		    }
		    else
		    {
			$stmt = $db->prepare("INSERT INTO vote_codes_allocation_log  (vote_code,allocation_DT,manreg_paper_vote) VALUES(:vote_code, :date,:manreg_paper_vote )");
			$stmt->execute(array(':vote_code' => $vote_code, ':date' => date("Y-m-d H:i:s"), ':manreg_paper_vote' => $manreg_paper_vote));
		    }
		}
	    }
	    else
		$count = -1; //error
	    $stmt = null;
	    $db = null;
	}
    return $count;
    
}

function generateRandomString($length = 3) {
    #$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
	$randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}
