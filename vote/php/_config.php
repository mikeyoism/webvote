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

//-COMPETITION SETUP START

//tvälingsnamn, visas som header på röstsida (och övriga sidor)
//håll namnet KORT, annars trunkeras namnet (framförallt i mobila enheter, testa)
define("SETTING_COMPETITION_NAME","SM 2015");  

//tävlingens öppettider format ex : 2014-01-22 20:00:00"
//SETTING_OPEN_DEBUG_ALWAYS_OPEN = bara true vid test, överskrider inställa öppettider
define("SETTING_OPEN_DEBUG_ALWAYS_OPEN",true);
define("SETTING_OPEN_DATE_OPEN", "2014-06-27 18:00:00");
define("SETTING_OPEN_DATE_CLOSE","2014-06-27 21:00:00");

//innan categories ändras måste tabeller i databas skapas som stämmer överens:
//tabellerna ska heta "vote_cat_xxx" och "vote_cat_xxx_history" där xxx motsvarar CONST_SETTING_CATEGORIES_SYS
//om rena nummerserier inte ska användas per kategori så måste även tabeller "vote_cat_xxx_list" skapas för varje kategori där xxx motsvarar CONST_SETTING_CATEGORIES_SYS
//MER INFO om hur systemet sätts upp i sin helhet finns i readme.md

//publika namn på kategorier  - svenska tecken och dyl är OK här
//ex define ("CONST_SETTING_CATEGORIES_PUBLIC", serialize (array ('Gul','Grön','Blå','Röd','Etikett')));
define ("CONST_SETTING_CATEGORIES_PUBLIC", serialize (array ('Den enda tävlingsklassen')));

//tekniska namn på kategorier (databasnamn etc) - svenska, mellanslag etc är förbjudna här! måste stämma i antal och i sekvens med publika
//(en databastabell skall finns för varje kategori med samman man som här och prefix vote_cat_)
//ex define ("CONST_SETTING_CATEGORIES_SYS", serialize (array ('gul','gron','bla','rod','etikett')));
define ("CONST_SETTING_CATEGORIES_SYS", serialize (array ('example')));

//publik extra info för respektive kategori,måste stämma i antal och i sekvens med tekniska
//define ("CONST_SETTING_CATEGORIES_PUBLIC_SUB", serialize (array ('lågalkoholöl 0-3,5%','sessionöl 3,6-5,5%','medelkraftig öl 5,6-7%','kraftfull öl 7-∞%','')));
define ("CONST_SETTING_CATEGORIES_PUBLIC_SUB", serialize (array ('0-100% ABV')));

//Ska vi ha nummerserier för röster för respektive kategori, ex klass 1: 101-199 (true), eller har vi löpande numrering oavsett klass (false)
define("CONST_SETTING_SERIALIZED_NUMBERS", true);

//Nummerserier för röster för respektive kategori, krav: positiva heltal (ej noll) min-max separarade med bindestreck, nycklar = lika CATEGORIES_SYS
//define ("CONST_SETTING_CATEGORIES_NUMBERSPAN", serialize (array ('gul'=>'101-199','gron'=>'201-299','bla'=>'301-399','rod'=>'401-499','etikett'=>'501-599')));
define ("CONST_SETTING_CATEGORIES_NUMBERSPAN", serialize (array ('example'=>'101-199')));

//Sparaknapparnas bakgrundsfärger, för ökat visuell känsla av vad man håller på att rösta på. CCCCCC är defaultfärgen grå som matchar övrig bakgrund
//define ("CONST_SETTING_CATEGORIES_COLORS", serialize (array ('gul'=>'#D8C704','gron'=>'#75AF6A','bla'=>'#6A8EAF','rod'=>'#D83737','etikett'=>'#CCCCCC')));
define ("CONST_SETTING_CATEGORIES_COLORS", serialize (array ('example'=>'#75AF6A','example2'=>'#6A8EAF')));

//Antal röster per kategori
//Om fler än 7st måste DB-tabeller utökas, se readme.md
define("CONST_SETTING_VOTES_PER_CATEGORY",3);


//Om röster ska viktas, ex Guld, silver, brons
//om denna inställning används ska formatet vara 'röstnamn'=>poäng (poäng rösten ger) exempel 'Guld'=>3,'Silver'=>'2','Brons'=>'1'
//röstnamn är den label som presenteras för användarna på röstsidan
//OBS ordningen motsvarar vote_1, vote_2, Vote_3 i respektive kategori-tabell i SQL
//OBS att antal labels och poäng MÅSTE stämma överens med inställningen CONST_SETTING_VOTES_PER_CATEGORY
//Om denna inställning inte används lämna tom (serialize (array ()));) då kommer lablar "Röst 1, Röst 2 etc"
//automatiskt presenteras för användarna på röstsidan och varje röst ge en poäng (som SM2014)
//(du KAN INTE fylla i poäng '1' rakt igenom för att få andra lablar (ej testat))
//exempel define ("CONST_SETTING_VOTE_WEIGHT", serialize (array ('Guld'=>5,'Silver'=>4,'Brons'=>3,'Sten'=>2,'Lera'=>1,'Mögel'=>0,'Avföring'=>-1)));
define ("CONST_SETTING_VOTE_WEIGHT", serialize (array ('Guld'=>3,'Silver'=>2,'Brons'=>1)));

//Max antal röster på samma öl - i samma kategori, sätt -1 för att inaktivera begränsing.
define("CONST_SETTING_VOTES_PER_CATEGORY_SAME",1);
//Kräv att alla tre röster utnyttjas, om man röstat på samma öl +1 gång
define("CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL",true);

//Kräv att alla röster alltid utnyttjas (överskrider CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL)
//rekommenders om röster är viktade , ex Guld, Silver röster via CONST_SETTING_VOTE_WEIGHT
define("CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL",true);

//true = om en papperröst registreras så ska denna skriva över ev webbröster
//vid false ignoreras pappersröst där webbröst redan finns.
//överskrivning gäller individuellt per kategori.
//Sätts denna till false bör konsekvenserna beaktas, ex vid överbelastat nät / haveri (folk tvingas pappersrösta men har kanske hunnit webbrösta delvis etc)
define("CONST_SETTING_PAPERVOTE_CAN_OVERWRITE_WEBVOTE",true);


/*Nedan är Prestandainställningar, ändras INTE om inte problem föreligger*/

//intervall klienter ska fråga efter systatus, i millisekunder
//(sysstatus = fönsterat som visar hur länge tävlig är öppen/stängd mm i klienten)
define("SETTING_SYSSTATUS_INTERVAL",10000);

//Normalt false. True meddelar klienter att sluta fråga efter status, använd för att minska bandbreddsproblem
//aktiva klienter kommer sedan inte prova igen, förrän klientsida laddas om!
define("SETTING_SYSSTATUS_DISABLE",false);

//Normalt true. ska klienten få prova uppdatera systatus när sida laddas? sätt ev "false" ihop med SETTING_SYSSTATUS_DISABLE för att definitivt stoppa trafiken.
define("SETTING_SYSSTATUS_DO_NOT_DISABLE_ON_CLIENT_LOAD",true); 

//spara allokeringar i loggtabell, eller ej
define ("SETTING_LOG_ALLOCATED_VOTE_CODES", true);

//spara ändrade röster i historytabeller, eller ej
define ("SETTING_LOG_CHANGED_VOTES", true);
//visa användararstatistik som möjliggör ange användare ange kön, ort etc
define("CONST_SETTING_ENABLE_VISITOR_STATISTICS",false);

//----END OF COMPETITION SETTINGS-----


//output js & php script debug i klient? Viktigt att sätta false i produktion
//ger extra fel och info rutor för testning 
define("CONST_SYS_JS_DEBUG",false); 

//denna inställning bör enbart stängas av vid test/utveckling, eller möjligtvis vid allvarliga problem under pågående tävling för att spara prestanda.
//(true = inskickad röstkod kontrolleras server-side första gången röster kommer in för en ny röstkod i en kategori.
//Röstkoder kollas även när man matar in dem i klientsidan och
//innan kod är kollad ser kilentscript till att spara-knapp och inputfält vara disablade,
//så denna inställningen är främst till för att omöjliggöra "hackning", JS kod i klienten kan alltid hackas i teorin.)
define("CONST_SETTING_VOTES_SERVER_SIDE_CHECKS",true);

//denna inställning ska ALLTID vara false
//ändra bara till true för tillfälliga utvecklingstester av server-checks
//denna inställning får ABSOLUT INTE vara true tillsammans med CONST_SETTING_VOTES_SERVER_SIDE_CHECKS, under tävling!
//(true stänger av kontrollerna i klient som beskrivs vid CONST_SETTING_VOTES_SERVER_SIDE_CHECKS)
define("DISABLE_CLIENT_CHECKS",false); 




//php debugging to JS console, enabled if CONST_SYS_JS_DEBUG
//(you need a firefox/chrome firephp-addon to see these messages in your console)
ob_start();
require_once /*$_SERVER['DOCUMENT_ROOT'] */ ('FirePHPCore/FirePHP.class.php');
require_once /*$_SERVER['DOCUMENT_ROOT'] */ ('FirePHPCore/fb.php');
$firephp = FirePHP::getInstance(true);
// best practice - disable first
$firephp->setEnabled(false);

if (CONST_SYS_JS_DEBUG){
    // Log all errors, exceptions, and assertion errors to Firebug 
    $firephp->setEnabled(true);
    $firephp->registerErrorHandler($throwErrorExceptions=true);
    $firephp->registerExceptionHandler();
    $firephp->registerAssertionHandler($convertAssertionErrorsToExceptions=true, $throwAssertionExceptions=false);    
}
?>
