<?php // -*- coding: utf-8 -*-

session_start();
include 'php/common.inc';
$dbAccess = new DbAccess();

$competitionId = getCompetitionId();

$categories= $dbAccess->getCategories($competitionId);

?>

<!DOCTYPE html>
<head>
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail) -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">
    <meta http-equiv="content-language" content="sv">
    <title>Webbröstning</title>
    
    <link rel="stylesheet" href="css/themes/shbf.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>
    <link rel="shortcut icon" href="data:image/x-icon;,"type="image/x-icon">

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
    <script src="vote.js" type="text/javascript"></script> 
</head>
<body>
    
<div data-role="page" id="votepage" data-competition-id="<?=$competitionId?>">

<div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
    <h1 class="ui-title" id ="competition_header" tabindex="0" role="heading" aria-level="1" data-mini="true">SM</h1>
</div>

<div class="ui-content" data-role="content" data-theme="a">

<div class="ui-bar ui-bar-b"> <!--ui-bar-b  = bakgrundsfärgstema-->
    <div id="headerbanner" >
	<img src="img/banner.png" alt="Home" </img>
    </div>
</div>    

<div class="ui-grid-a">
    <div class="ui-block-a"  data-mini="true" style="padding-right:0.5em">
	<ul data-role="listview" data-inset="true" >
	    <li data-role="fieldcontain" id="statusdiv">
		<div class="infobar infobar-neutral" data-mini="true">Välkommen!</div>
	    </li> 
	</ul>
    </div>

    <div class="ui-block-b" data-mini="true">
     	    <ul data-role="listview" data-inset="true">
		<li data-role="fieldcontain">
		    
		    <input type="text" id="vote_code" name="vote_code"  placeholder="Din röstkod" value="" maxlength="<?=$code_len;?>" data-mini="true" size="5"  >
		</li>
	    </ul>
 
    </div>
</div>

<div class="ui-grid-solo" >
    <div class="ui-block-a" data-mini="true">
	<ul data-role="listview" data-inset="true"  >
	    <li data-role="fieldcontain" id="sysbar"> </li> </ul>
    </div>
</div> 
    
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
    
foreach ($categories as $c)
{
    $vote_weight_and_labels = $dbAccess->getVoteWeightAndLabels($c['id']);
    $votes_per_cat = count($vote_weight_and_labels);
    $vote_labels = array_keys($vote_weight_and_labels);

    $extra = isset($c['description']) ? ' (' . $c['description'] . ')' : '';
    $cColor = isset($c['color']) ? $c['color'] : '#cccccc';
    $id = $c['id'];
    
    echo '<div data-role="collapsible" id="vote_' . $id . '">';
    echo '	<h3>' . $c['name'] . '<small>' . $extra . '</small>' . '</h3><div id="head_' . $id . '" ></div>';
    echo '	<form name="' . $id . '_vote" id="' . $id . '_vote" class="voteform_trigger" data-ajax="false" >';
    echo '	    <div class="vote_category" id="' . $id . '"></div>';
    echo '	    <ul data-role="listview" data-inset="true">';
    
    for ($voteNr = 1; $voteNr <= $votes_per_cat; $voteNr++){
        echo '		<li data-role="fieldcontain">';
        echo 			htmlVoteRow($id, $voteNr, ($vote_labels[$voteNr-1]));
        echo '		</li>';
    }
    
    echo '		<li data-role="fieldcontain" style="background:' . $cColor . '">';
    echo '			<div class="position-rightalign-right-to-label">';
    echo '			<div id ="form_' . $id . '_status" class="error infobar infobar-error formstatus_trigger" style="margin-bottom: 1em" data-mini="true"></div>';
    echo '			<button id="submit_' . $id . '" type="submit" data-role="button" data-mini="false"data-theme="a" data-icon="check" >Spara</button>';
    echo '			</div>';
    echo '		</li>';
	
    echo '	    </ul>';
    echo '	</form>';    
    echo '</div>';
}
?>
        
</div> 


</div> 
</div>

</body>
</html>
