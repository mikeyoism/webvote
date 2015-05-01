<?php
/**
 
 * ------------------------------------------------------------------------
 *
 * @author      Micke Josefsson (micke_josefsson (at) hotmail.com)
 */

session_start();
//server redirect om ingen användare finns inloggad
//authenticated_level,användar privilegie som krävs, fn 0= ingen användare, 1 = funktionär, 2 = admin
if (!isset($_SESSION["authenticated_level"]) ||  $_SESSION["authenticated_level"] < 2)
{
    $me = explode("?", basename($_SERVER['PHP_SELF'])); //splitta bort ev params
    $redir = 'login.php?p=' . $me[0];
    $host  = $_SERVER['HTTP_HOST'];
    $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    
    header("Location: http://$host$uri/$redir"); 
    exit;
}
include 'php/_config.php';

$sys_cat  = unserialize(CONST_SETTING_CATEGORIES_SYS);
$user_cat  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC);
$user_cat_extra  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC_SUB);
$cat_col = unserialize(CONST_SETTING_CATEGORIES_COLORS);

?>

<!DOCTYPE html>
<head>
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail) -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">    
    <title>Röststatus</title>
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/knockout/knockout-3.0.0.js"></script>

    <script src="js/vote_adm.js" type="text/javascript"></script> 
    

</head>
<body>
    
<div data-role="page" id="votestat">

<!--huve-->
<div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
    <a class="ui-btn-left ui-btn ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="none" data-icon="home" title=" Home ">
        <span class="ui-btn-inner ui-btn-corner-all">
            <span class="ui-btn-text"> Home </span>
            <span data-form="ui-icon" class="ui-icon ui-icon-home ui-icon-shadow"></span>
        </span>
    </a>
    <h1 class="ui-title" id ="competition_header" tabindex="0" role="heading" aria-level="1" data-mini="true">SM 2014</h1> <!--js dynamic-->       
    <a class="ui-btn-right ui-btn ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="button" data-icon="grid" title=" Navigation ">
	<span class="ui-btn-inner ui-btn-corner-all">
	    <span class="ui-btn-text"> Navigation </span>
	    <span data-form="ui-icon" class="ui-icon ui-icon-grid ui-icon-shadow"></span>
	</span>
    </a>
</div>


<!--content-->
<div class="ui-content" data-role="content" data-theme="a">
<h1>Adminsida</h1>
<h3>Börja med att sätta klass-inställningar mm via _config.php, denna sida förutsätter även att databas är färdigkonfad.</h3>
<h4>Antalet röstkoder bör inte markant överstiga antalet uppskattade besökare. Ju fler koder som finns lagrade ju lättare är det att "gissa koder" och träffa rätt.
Maximalt antal möjliga kombinationer med 3 teckens röstkod är ca 39.000 unika koder, så om vi räknar med 1500 besökare så skapa inte mer än ca 2000 koder.</h4>

<p id="countshow"></p>
<p id="err"></p>
<button data-inline="true" data-mini="false" id="votecode_gen500">Generera upp till 100 nya röstkoder</button>
<br>
<br>

<h4>Nedanstående ska bara användas för att förbereda innan ny tävling. De får absolut INTE användas under pågående tävling, och INTE förrän resultatet är klart och undansparat/redovisat
EJ ÄNNU IMPLEMENTEREADE KNAPPAR!</h4>    
<button data-inline="true" data-mini="false" id="votecode_erase">Radera alla befintliga röstkoder</button>
<button data-inline="true" data-mini="false" id="votecode_erase">Radera alla tävlingsresultat</button>





<div data-role="footer">
    <h4 >© 2014 Mikael Josefsson, micke_josefsson(at)hotmail.com, utvecklad för SHBF</h4>
</div>

</div> <!-- ui-content container -->



</div> <!-- page -->

</body>
</html>


