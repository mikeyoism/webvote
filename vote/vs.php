<?php
/*Webvote - admin competition results
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
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail)  -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">
    <meta http-equiv="content-language" content="sv">
    <title>Röststatus</title>
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/knockout/knockout-3.0.0.js"></script>
    <script src="js/knockout.simplegrid.3.0.js"></script>
    
    <script src="js/vote_common.js" type="text/javascript"></script>
    <script src="js/vote_vs.js" type="text/javascript"></script>
    
    
<script type="text/javascript">
    
</script>    
    
      
    <style>
    #imgscaledmax img { /*fullsize logga */
        width  : 100%;
        height : auto;
        max-width:1300px; /*ovackert om för stor*/
        
    }
    .infobar {
	padding: 0.35em 0.5em;
	margin: 0em;
	border-radius: 7px;
	text-shadow:none;
    }
    .infobar-neutral {background: #D89200; /*orange*/ color: #ffffff /*vita tecken*/ }    
    .infobar-ok { background: #04B404; /*grön*/ color: #000000 /*svarta tecken*/}
    .infobar-error { background: #fff3a5; /*yellow*/ color: #000000 /*svarta tecken*/ }
    .infobar-warning { background: #DF0101; /*red*/ color: #000000 /*svarta tecken*/}
    
    .infobar-drop{
	display: inline-block;
	position: relative;
	margin-top: 0.5em;
	z-index:999;
    }
    
    @media all and (min-width: 450px) {
    .position-rightalign-right-to-label {
        
        margin-left: 22%; /*mosvarar default width av en ui-mobile label*/
    }
}
    </style>
    
<style type="text/css">
    .ko-grid { margin-bottom: 1em; width: 25em auto; max-width: 90%; border: 1px solid silver; background-color:White; font-family: Helvetica,Arial,sans-serif; }
    .ko-grid th { text-align:left; background: #D89200; color:White; text-overflow: ellipsis; text-shadow: rgb(68, 68, 68) 0px 1px 0px; }
    .ko-grid td, th { padding: 0.4em; }
    .ko-grid tr:nth-child(odd) { background-color: #DDD; }
    .ko-grid-pageLinks { margin-bottom: 1em; }
    .ko-grid-pageLinks a { padding: 0.5em; }
    .ko-grid-pageLinks a.selected { background-color: #D89200; color: White; }
    
</style>

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
<h1>Resultatsida</h1>
<h3>OBS ladda om sidan EFTER att tävlingen avslutats för att vara helt säker på att alla resultat har uppdaterats!</h3>
<h6>Viktad tävling: Om flera öl hamnar på samma poäng sorteras de inbördes utifrån antal röster på högsta => lägsta vikt/valör (Ex 1st Guld-röst rankas högre än 2 Silver-röster, vid lika poäng)
<br>Oviktad tävling (1 poäng per röst): Om flera öl hamnar på samma poäng ("antal röster") så är "Antal unika röster" utslagsgivande (antal röstande personer utan hänsyn till dubbelröster)
<br>Om resultat fortfarande är lika mellan medaljörer, får medalj eventuellt delas, ex:
<br>2 lika ettor: 2 Guld, 0 silver, 1 brons
<br>2 lika tåvor: 1 guld, 2 silver, 0 brons
<br>2 lika treor: 1 guld, 1 silver, 2 brons där prispott delas</h6>

<!--systemstatus - default dold -->
<div class="ui-grid-solo" >
    <div class="ui-block-a" data-mini="true">
	<ul data-role="listview" data-inset="true"  >
	    <li data-role="fieldcontain" id="sysbar"> </li> </ul>
    </div>
</div>

<?php
$p = 0;
foreach ($sys_cat as $cc)
    {
	$extra = "";
	if (strlen($user_cat_extra[$p]) > 0)
	    $extra = " (" . $user_cat_extra[$p] . ")";

	echo '<div data-role="collapsible" data-collapsed="false">';
	echo '    <h3>' . $user_cat[$p] . '<small>' . $extra . '</small></h3>';
	echo '	      <div class="liveExample" id="vm_' . $cc . '">';
	echo '	          <div data-bind="simpleGrid: gridViewModel"> </div>';
	echo '		  <button data-inline="true" data-mini="true" data-bind="click: jumpToFirstPage, enable: gridViewModel.currentPageIndex">Sida ett / Topp 5 </button>';
	echo '        </div>';
	echo '</div>';
	$p++;
    }
?>   






<div data-role="footer">
    <h4 >© 2014 Mikael Josefsson, micke_josefsson(at)hotmail.com, utvecklad för SHBF</h4>
</div>

</div> <!-- ui-content container -->



</div> <!-- page -->

</body>
</html>


