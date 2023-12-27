<?php
/**
 sida för  publik live-visning av antal röster, stapeldiagram, i övrigt lika vstat.php
 * ------------------------------------------------------------------------
 *
 * @author      Micke Josefsson (micke_josefsson (at) hotmail.com)
 */

session_start();
//server redirect om ingen användare finns inloggad
//authenticated_level,användar privilegie som krävs, fn 0= ingen användare, 1 = funktionär, 2 = admin
if (!isset($_SESSION["authenticated_level"]) ||  $_SESSION["authenticated_level"] < 0)
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
    <!--@author      Micke Josefson (micke_josefsson (at) hotmail) -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">
    <meta http-equiv="content-language" content="sv">
    <title>Webröstning</title>
    
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css" />
    <link rel="stylesheet" href="css/ui-lightness/jqx.base.css" type="text/css" />    
    
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    
    <script src="jqm/jquery-ui-1.10.4.custom.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/knockout/knockout-3.0.0.js"></script>
    
    <script src="js/knockout.simplegrid.3.0-nolinks.js"></script> <!--custom-->
    <script type="text/javascript" src="js/jqxcore.js"></script>
    <script type="text/javascript" src="js/jqxdata.js"></script>
    <script type="text/javascript" src="js/jqxchart.js"></script>
    <script type="text/javascript" src="js/jqxknockout.js"></script>    
 
    
    <script src="js/vote_common.js" type="text/javascript"></script>
    <script src="js/vote_vstat.js" type="text/javascript"></script>


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
    .jqx-chart-axis-text{
	font-size: 14px;
	font-weight:bold; 
	
    }
    @media all and (min-width: 450px) {
	.position-rightalign-right-to-label {
	    
	    margin-left: 22%; /*mosvarar default width av en ui-mobile label*/
	}
    }
    .center
    {
    margin-left:43%;
    margin-right:40%;
    display: inline-block;
    
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
    
<div data-role="page" id="manregpage">




<!--huve-->
<div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
       

    <h1 class="ui-title" id ="competition_header" tabindex="0" role="heading" aria-level="1" data-mini="true">Folkets Val - Livestatistik</h1> <!--js dynamic-->       

</div>

<!--content-->
<div class="ui-content" data-role="content" data-theme="a">
<div class="nocenter">    


 <!--systemstatus - default dold -->
<div class="ui-grid-solo" >
    <div class="ui-block-a" data-mini="true" id="sysbar">

    </div>
</div> 
      

<br>

 <div id='jqxWidget'>
      <div id='chart' style="width:100%; height:400px" data-bind='jqxChart: {
     title: "Röster i folkets val",
     description: "Antal röstande personer och antal röster per klass",
     enableAnimations: true,
     showLegend: true,
     padding: { left: 5, top: 5, right: 5, bottom: 5 },
     titlePadding: { left: 90, top: 0, right: 0, bottom: 10 },
     source: items,
     categoryAxis:
	 {
	     text: "Category Axis",
	     textRotationAngle: 45,
	     dataField: "category",
	     showTickMarks: true,
	     tickMarksInterval: 1,
	     tickMarksColor: "#888888",
	     unitInterval: 1,
	     showGridLines: false,
	     gridLinesInterval: 1,
	     gridLinesColor: "#888888",
	     axisSize: "auto"
	 },
     colorScheme: "scheme04",
     seriesGroups:
	 [
	     {
		 type: "column",
		 columnsGapPercent: 100,
		 seriesGapPercent: 5,
		 valueAxis:
		 {
		     unitInterval: 100,
		     minValue: 0,
		     maxValue: "auto",
		     displayValueAxis: true,
		     description: "Antal",
		     axisSize: "auto",
		     tickMarksColor: "#866866"
		 },
		 series: [
			 { dataField: "unique_voters", displayText: "Personer" },
			 { dataField: "votes", displayText: "Antal Röster" },
			 
		     ]
	     }
	 ]
  }'>
    </div>
    </div>
 
    

</div>
</div> <!-- ui-content container -->
<!-- framtida footer-->
</div> <!-- page -->

</body>
</html>


