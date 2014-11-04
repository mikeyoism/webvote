<?php
/**
 
 * ------------------------------------------------------------------------
 *
 * @author      Micke Josefsson (micke_josefsson (at) hotmail.com)
 */

session_start();
include 'php/_config.php';
//for server-side build-up of page...
//you may produce a static version if you wish
$sys_cat  = unserialize(CONST_SETTING_CATEGORIES_SYS);
$user_cat  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC);
$user_cat_extra  = unserialize(CONST_SETTING_CATEGORIES_PUBLIC_SUB);
$cat_col = unserialize(CONST_SETTING_CATEGORIES_COLORS);
$votes_per_cat = CONST_SETTING_VOTES_PER_CATEGORY;
$vote_weight_and_labels = unserialize(CONST_SETTING_VOTE_WEIGHT);
?>

<!DOCTYPE html>
<head>
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail) -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">
    <meta http-equiv="content-language" content="sv">
    <title>Webröstning</title>
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>

    <style>
    #headerbanner img { /*fullsize logga */
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
	    
	    margin-left: 22%; /*motsvarar default width av en ui-mobile label*/
	}
	.ui-popup {
	    max-height: 90% !important;
	}
    }
    </style>    
    <script src="js/vote.min.js" type="text/javascript"></script> 
</head>
<body>
    
<div data-role="page" id="votepage">

<!--huvée-->
<div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
    <a class="ui-btn-left ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="none" data-icon="none" title=" Home ">
        <span class="ui-btn-inner ui-btn-corner-all">
            <span class="ui-btn-text"> Home </span>
            <span data-form="ui-icon" class="ui-icon ui-icon-home ui-icon-shadow"></span>
        </span>
    </a>
   
    <h1 class="ui-title" id ="competition_header" tabindex="0" role="heading" aria-level="1" data-mini="true">SM</h1> <!--js dynamic-->
    <a href="#popupHelp" id="helpButton" data-rel="popup" data-transition="slide" data-inline="true" data-position-to="window"
    class="ui-btn-right  ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="button" data-icon="info" title=" Hjälp ">
	<span class="ui-btn-inner ui-btn-corner-all">
	    <span class="ui-btn-text"> Hjälp </span>
	    <span data-form="ui-icon" class="ui-icon ui-icon-grid ui-icon-shadow"></span>
	</span>
    </a>
</div>

<!--content-->
<div class="ui-content" data-role="content" data-theme="a">

<div data-role="popup" id="popupHelp" data-theme="a" class="ui-corner-all">
<a href="#" data-rel="back" data-role="button" data-theme="a" data-icon="delete" data-iconpos="notext" class="ui-btn-left">Close</a>    

<div data-role="header" data-theme="a" class="ui-corner-top ui-header ui-bar-a" role="banner">
    <h1 class="ui-title" role="heading" aria-level="1">Hjälp</h1>
</div>
	
	<div style="padding:0.5em 1em;" id="helpform">
	    <p>Börja med att fylla i din personliga röstkod (se programbladet), den måste <b>alltid</b> vara ifylld när du röstar. </p>
	    <p>Tävlande öl är indelade i olika tävlingsklasser efter alkoholhalt.</p>
	    <p>Rösta på dina favoritöl i en klass genom att först ange ölens tävlingsnummer, <b>rösterna registreras när du trycker på SPARA-knappen</b>.
	    Du får alltid en bekräftelse tillbaka. Röster kan uppdateras/ändras obegränsat fram till tävlingen stänger med spara-knapparna.</p>
	    <p><b>Observera att varje tävlingsklass har sin egen spara-knapp</b>, du sparar alltså röster inividuellt per klass.</p>
	    <p>Du väljer själv i vilka klasser du vill rösta och hur många röster du vill avge. Varje röst ger en (1) poäng och du kan rösta på upp till tre olika öl per klass.
	    Max två röster kan ges på samma öl och då måste du även ge en 3:e röst på en annan öl i den klassen</p>
	</div>


</div>  

<!--logo-->
<div class="ui-bar ui-bar-b"> <!--ui-bar-b  = bakgrundsfärgstema-->
    <div id="headerbanner" >
	<img src="img/banner.png" alt="Home" </img>
    </div>
</div>    
      
      
<!--röstkod/status-->      
<div class="ui-grid-a">
    <div class="ui-block-a"  data-mini="true" style="padding-right:0.5em">
	<ul data-role="listview" data-inset="true" >
	    <li data-role="fieldcontain" id="statusdiv">
		<div class="infobar infobar-neutral" data-mini="true">Välkommen! ange först din röstkod.</div>
	    </li> 
	</ul>
    </div>

    <div class="ui-block-b" data-mini="true">
     	    <ul data-role="listview" data-inset="true">
		<li data-role="fieldcontain">
		    
		    <input type="text" id="vote_code" name="vote_code"  placeholder="Din röstkod" value="" maxlength="3" data-mini="true" size="5"  >
		</li>
	    </ul>
 
    </div>
</div>

<!--systemstatus - default dold -->
<div class="ui-grid-solo" >
    <div class="ui-block-a" data-mini="true">
	<ul data-role="listview" data-inset="true"  >
	    <li data-role="fieldcontain" id="sysbar"> </li> </ul>
    </div>
</div> 
    
  
    
<!--röster-->
<div data-role="collapsible-set" data-theme="a" data-content-theme="a">

<?php
    function htmlVoteRow($cat, $voteNr, $voteLabel)
    {
	$ret;
	$label = "Röst " . $voteNr; //default, röst 1, röst 2 etc
	if ($voteLabel != "")
	    $label = $voteLabel; //user defined, guld, silver etc...
	    
	$ret = '		    <label for="' . $cat . '_vote' . $voteNr . '" id="' . $cat . '_vote' . $voteNr . '_label">' . $label . ':</label>' .
	       '		    <input class="voteval' . $voteNr . '" type="number" name="' . $cat . '_vote' . $voteNr . '" id="' . $cat . '_vote' . $voteNr . '" value="" data-clear-btn="true" maxlength="3">' .
	       '		    <div class="position-rightalign-right-to-label">' .
	       '			<div id ="' . $cat . '_vote' . $voteNr . '_status" class="error infobar infobar-error infobar-drop" data-mini="true"></div>' .
	       '		    </div>';	
	
	return $ret;
    }

    $p = 0;
    $labelc = count($vote_weight_and_labels);
    if ($labelc > 0)
	$vote_labels = array_keys($vote_weight_and_labels);
    foreach ($sys_cat as $cc)
    {
	$extra = "";
	if (strlen($user_cat_extra[$p]) > 0)
	    $extra = " (" . $user_cat_extra[$p] . ")";
	$cColor = $cat_col[$cc];
	if ($cColor == "")
	    $cColor  = "#cccccc";
	
	echo '<div data-role="collapsible" id="vote_' . $cc . '">';
	echo '	<h3>' . $user_cat[$p] . '<small>' . $extra . '</small>' . '</h3><div id="head_' . $cc . '" ></div>';
	echo '	<form name="' . $cc . '_vote" id="' . $cc . '_vote" class="voteform_trigger" data-ajax="false" >';
	echo '	    <div class="vote_category" id="' . $cc . '"></div>';
	echo '	    <ul data-role="listview" data-inset="true">';
        //sup3
	
	for ($voteNr = 1; $voteNr <= $votes_per_cat; $voteNr++){
	    echo '		<li data-role="fieldcontain">';
	    echo 			htmlVoteRow($cc,$voteNr, ($labelc > 0 ? $vote_labels[$voteNr-1] : ""));
	    echo '		</li>';
	}

			
	echo '		<li data-role="fieldcontain" style="background:' . $cColor . '">';
	echo '			<div class="position-rightalign-right-to-label">';
	echo '			<div id ="form_' . $cc . '_status" class="error infobar infobar-error formstatus_trigger" style="margin-bottom: 1em" data-mini="true"></div>';
	echo '			<button id="submit_' . $cc . '" type="submit" data-role="button" data-mini="false"data-theme="a" data-icon="check" >Spara</button>';
	echo '			</div>';
	echo '		</li>';
			
	echo '	    </ul>';
	echo '	</form>';    
	echo '</div>';
	
	$p++;
    }
    
    $p = 0;
    
    if (CONST_SETTING_ENABLE_VISITOR_STATISTICS)
    {
?>  
	    
    <div data-role="collapsible" id="div_stat">
	<h3> Statistik <small> (frivillig & anonym)</small></h3>
	<form name="stat_stat" id="statform_trigger" class="statform_trigger" data-ajax="false">
	    <div class="stat_category" id="stat_category"></div>
	    <ul data-role="listview" data-inset="true">

		<li data-role="fieldcontain">
		    <fieldset data-role="controlgroup" data-type="horizontal">
		    <legend>Kön:</legend>
		    <input type="radio" name="stat1" id="stat1man" value="M" checked="checked" />
		    <label for="stat1man">Man</label>
		    <input type="radio" name="stat1" id="stat1woman" value="K"/>
		    <label for="stat1woman">Kvinna</label>
		    </fieldset>
		</li>
    
		<li data-role="fieldcontain">
		    <label for="stat2">Ålder:</label>
		    <input type="range" name="slider-mini" id="stat2" value="35" min="20" max="100" data-highlight="true" data-mini="true" />
		</li>
		<li data-role="fieldcontain">
		    
			<label for="stat3" id="stat3_label">Hemort:</label>
			<input class="statval3" type="text" name="stat3" id="stat3" maxlength="49"  value="">
		    
		<li>
	
		<li data-role="fieldcontain">
		    <fieldset data-role="controlgroup" data-type="horizontal">
		    <legend>Är du på gång?:</legend>
		    <input type="radio" name="stat4" id="stat4yes" value="1" />
		    <label for="stat4yes">Ja</label>
		    <input type="radio" name="stat4" id="stat4no" value="0"/>
		    <label for="stat4no">Nej</label>
		    <input type="radio" name="stat4" id="stat4dunno" value="2" checked="checked"/>
		    <label for="stat4dunno">Vet ej</label>
		    </fieldset>
		</li>    
		<li data-role="fieldcontain" style="background: #cccccc">
		    <div class="position-rightalign-right-to-label">
		    <div id ="form_stat_status" class="error infobar infobar-error formstatus_trigger" style="margin-bottom: 1em" data-mini="true"></div>
		    <button id="submit_stat" type="submit" data-role="button" data-mini="false"data-theme="a" data-icon="check" >Spara</button>
		    </div>
		</li>
			
	    </ul>
	</form>    
    </div>
<?php
    }
?>

</div> <!-- collapsible-set-->


</div> <!-- ui-content container -->
<!-- framtida footer-->
</div> <!-- page -->

</body>
</html>


