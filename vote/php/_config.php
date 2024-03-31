<?php
/*Webvote - global configuration file
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

//För korrekt beräkning av tävligstider
date_default_timezone_set("Europe/Stockholm");
//-COMPETITION SETUP START

//for testing/used if not set by param
define("COMPETITION_ID", 42);

define("ENABLE_RATING", true);
//Längd på röstkoder - överskrid inte satt maxlängd i db.
define("CONST_SETTING_VOTE_CODE_LENGTH",6);

/*Nedan är Prestandainställningar, ändras INTE om inte problem föreligger
 Kan ändras under pågående tävling*/

//LIVE-läs ölinfo från eventreg-database (annars från cache-filer som sätts upp på admin-sidan)
define("CONNECT_EVENTREG_DB",false);
///1=No Offset in Category-table, TODO; fix this
define("CONST_SETTING_CATEGORY_OFFSET",92); 
//intervall klienter ska fråga efter systatus, i millisekunder
//(sysstatus = fönsterat som visar hur länge tävlig är öppen/stängd mm i klienten)
define("SETTING_SYSSTATUS_INTERVAL",10000);

//----END OF COMPETITION SETTINGS-----

//LEGACY VOTE SETTINGS - NOT USED FOR RATING

//för legacy VOTE, läs ölinfo från eventreg-database (cache-filer används inte för detta)
//detta gör entries-tabellen irrelevant
define("CONNECT_EVENTREG_DB_VOTING",true);

//tillåt ALDRIG röstning (till vote-tabell) samtidigt som betygssättning (till ratings-tabell)
define("ENABLE_VOTING",!ENABLE_RATING); // alltså normalt = !ENABLE_RATING

//vote lagras till ratings-table, not vote-table 
//överskrider ENABLE_VOTING
define("ENABLE_VOTING_AS_RATING",true); 
//enbart terminaler kan rösta AS_RATING
define("ENABLE_VOTING_AS_RATING_TERMINAL_ONLY",true);  

//visar hjälppopup med mer info för användaren
define("CONST_SETTING_SHOW_HELP_POPUP", true);
define("SETTING_OPEN_DEBUG_ALWAYS_OPEN",true);
//Antal siffror i serierna för NUMBERSPAN ovan (måste vara samma antal siffror från min - max i alla kategoerier (ex 100-999 = 3 siffror)
define("CONST_SETTING_BEERID_NUMBERSPAN_LENGTH",3);
//Antal röster per kategori
//Om fler än 7st måste DB-tabeller utökas, se readme.md
//inget annat än 3 är supportat i db just nu
define("CONST_SETTING_VOTES_PER_CATEGORY",3);

//Max antal röster på samma öl - i samma kategori, sätt -1 för att inaktivera begränsing.
define("CONST_SETTING_VOTES_PER_CATEGORY_SAME",2);
//Kräv att alla tre röster utnyttjas, om man röstat på samma öl +1 gång
define("CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL",true);
//----END OF LEGACY VOTE SETTINGS -----

//----SYSTEM SETTINGS -----

//output js & php script debug i klient? Viktigt att sätta false i produktion
//ger extra fel och info rutor för testning 
define("CONST_SYS_JS_DEBUG", true); 
//php extension som inte alltid finns installerad, och är depricerad (one.com)
define("APC_CACHE_ENABLED", false);




//php debugging to JS console, enabled if CONST_SYS_JS_DEBUG
//(you need a firefox/chrome firephp-addon to see these messages in your console)
// ob_start();
// require_once /*$_SERVER['DOCUMENT_ROOT'] */ ('FirePHPCore/FirePHP.class.php');
// require_once /*$_SERVER['DOCUMENT_ROOT'] */ ('FirePHPCore/fb.php');
// $firephp = FirePHP::getInstance(true);
// // best practice - disable first
// $firephp->setEnabled(false);

// if (CONST_SYS_JS_DEBUG){
//     // Log all errors, exceptions, and assertion errors to Firebug 
//     $firephp->setEnabled(true);
//     $firephp->registerErrorHandler($throwErrorExceptions=true);
//     $firephp->registerExceptionHandler();
//     $firephp->registerAssertionHandler($convertAssertionErrorsToExceptions=true, $throwAssertionExceptions=false);    
// }

