<?php
/**
 sida för snabbinmatning av pappersröster för funktionärer.
 * ------------------------------------------------------------------------
 *
 * @author      Micke Josefsson (micke_josefsson (at) hotmail.com)
 */

session_start();
//server redirect om ingen användare finns inloggad
//authenticated_level,användar privilegie som krävs, fn 0= ingen användare, 1 = funktionär, 2 = admin
if (!isset($_SESSION["authenticated_level"]) ||  $_SESSION["authenticated_level"] < 1)
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
$votes_per_cat = CONST_SETTING_VOTES_PER_CATEGORY;
$vote_weight_and_labels = unserialize(CONST_SETTING_VOTE_WEIGHT);
$code_len = CONST_SETTING_VOTE_CODE_LENGTH;
?>

<!DOCTYPE html>
<head>
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail)  -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">
    <meta http-equiv="content-language" content="sv">
    <title>Webröstning</title>
    
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css" />
    <!--this page is NOT designed for mobile devices-->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="jqm/jquery-ui-1.10.4.custom.min.js"></script>
    

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
    .clear{

	clear: both;
	
    }
    .vote_code {
	color: rgb(255,100,0);
	;
	
    }
    </style>    
    
    <script>

   </script>
      

    <script src="js/vote_common.min.js" type="text/javascript"></script>
    <script src="js/vote_manreg_adm.js" type="text/javascript"></script>

	


</head>
<body>
    
<div data-role="page" id="manregpage">




<!--huve-->
<div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
       
    <h1 class="ui-title" tabindex="0" role="heading" aria-level="1" data-mini="true">Röstregistrering</h1>
    
</div>

<!--content-->
<div class="ui-content" data-role="content" data-theme="a">

 
      
<!--röstkod/status-->      
<div class="ui-grid-a">
    <p data-mini="true">TABB-tangent + ENTER (spara) kan användas. Detta är FUNKTIONÄRSSIDA för pappersröster, EJ publik!</p>
    <div class="ui-block-a"  data-mini="true" style="padding-right:0.5em">
	<ul data-role="listview" data-inset="true" >
	<li data-role="fieldcontain">
	    <label for=vote_code" id="vote_code_label" class="vote_code">Röstkod:</label>
	    <input type="text" id="vote_code" name="vote_code"  tabindex="0"  value="" maxlength="<?=$code_len;?>" data-mini="true" size="5"  >
	</li>

	</ul>
    </div>

    <div class="ui-block-b" data-mini="true">
     	    <ul data-role="listview" data-inset="true">
	    <li data-role="fieldcontain" id="statusdiv">
		<div class="infobar infobar-neutral" data-mini="true">Välkommen! ange först din röstkod.</div>
	    </li> 		
	    </ul>
 
    </div>
</div>

    
  
    
<!--röster-->
<div  data-theme="a" data-content-theme="a">
<form name="voteregform" id="voteregform" class="voteform_trigger" data-ajax="false" >
<?php

    function htmlVoteRow($cat, $voteNr, $voteLabel)
    {
	$ret;
	$label = "Röst " . $voteNr; //default, röst 1, röst 2 etc
	if ($voteLabel != "")
	    $label = $voteLabel; //user defined, guld, silver etc...
	    
	$ret = '		    <label for="' . $cat . '_vote' . $voteNr . '" id="' . $cat . '_vote' . $voteNr . '_label">' . $label . ':</label>' .
	       '		    <input class="voteval' . $voteNr . '" type="number" name="' . $cat . '_vote' . $voteNr . '" id="' . $cat . '_vote' . $voteNr . '" value="">' .
	       '		    <div class="position-rightalign-right-to-label">' .
	       '			<div id ="' . $cat . '_vote' . $voteNr . '_status" class="error infobar infobar-error infobar-drop" data-mini="true"></div>' .
	       '		    </div>';	

	return $ret;
    }
    $labelc = count($vote_weight_and_labels);
    if ($labelc > 0)
	$vote_labels = array_keys($vote_weight_and_labels);
	
    $p = 0;
    
    foreach ($sys_cat as $cc)
    {
	$extra = "";
	if (strlen($user_cat_extra[$p]) > 0)
	    $extra = " (" . $user_cat_extra[$p] . ")";
	$cColor = $cat_col[$cc];
	if ($cColor == "")
	    $cColor  = "#cccccc";
	    
	echo '<div id="vote_' . $cc . '" style="float:left;">';
	echo '	<div style="background:' . $cColor . '"><h3>' . $user_cat[$p] . '<small>' . $extra . '</small>' . '</h3><div id="head_' . $cc . '" ></div></div>';
//	echo '	<form name="' . $cc . '_vote" id="' . $cc . '_vote" class="voteform_trigger" data-ajax="false" >';
	echo '	    <div class="vote_category" id="' . $cc . '">'; //OBS se längst ner
	echo '	    <ul data-role="listview" data-inset="true" >';
	for ($voteNr = 1; $voteNr <= $votes_per_cat; $voteNr++){
	    echo '		<li data-role="fieldcontain" style="float:left;">';
	    echo 			htmlVoteRow($cc,$voteNr, ($labelc > 0 ? $vote_labels[$voteNr-1] : ""));
	    echo '		</li>';
	}
			
	echo '	    </ul></div>'; //NOTE: div-slutet skiljer sig här mot standardsidan index.php där diven inte omfamnar ul/li - för att vi ska kunna iteratera den i js
//	echo '	</form>';    
	echo '</div>';
	
	$p++;
    }
    
    $p = 0;

?>
	<br>
	<div id="save" class="clear">

				<div class="position-rightalign-right-to-label">
				<div id ="form_submit_status" style="margin-bottom: 1em; margin-top: 2em;" data-mini="true"></div>
				<button id="submit_votes" type="submit" data-role="button" style="margin-top: 1em">Spara röster</button>
				<div id="err_butts" class="infobar infobar-neutral" style=" margin-top: 1em" hidden="true">
				<button id="error_ack" type="button" data-role="button"  >Bekräfta fel</button>
				<button id="error_corr" type="button" data-role="button"  >Korrigera</button>
				</div>
				</div>

	 </div>
</form>
    
</div> <!-- collapsible-set-->


</div> <!-- ui-content container -->
<!-- framtida footer-->
</div> <!-- page -->

</body>
</html>


