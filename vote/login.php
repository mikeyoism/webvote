<?php
/**
 * ------------------------------------------------------------------------
 *
 * @author      Micke Josefsson (micke_josefsson (at) hotmail.com)
 * Notes:
 * lägg in denna nedansåtende kod i varje sida som kräver inloggning:
 * (det är varje sidas ansvar att kolla säkerheten, redirect här i login kan teoretiskt modifieras av klient till annan sida än tänkt)
 * /
//server redirect om ingen användare finns inloggad
//authenticated_level,användar privilegie som krävs, fn 0= ingen användare, 1 = funktionär, 2 = admin
//if (!isset($_SESSION["authenticated_level"]) ||  $_SESSION["authenticated_level"] < 1)
// {
//     $me = explode("?", basename($_SERVER['PHP_SELF'])); //splitta bort ev params
//     $redir = 'login.php?p=' . $me[0];
//     $host  = $_SERVER['HTTP_HOST'];
//     $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
//     header("Location: http://$host$uri/$redir");
//     exit;
// }
 /**/
session_start();
include 'php/_config.php';
//spara undan sidan som vill logga in, om server php behöver den senare
if (isset($_GET["p"])){
    $_SESSION["loginReqPage"] = $_GET["p"];
}
else if (isset($_POST["p"])){
    $_SESSION["loginReqPage"] = $_GET["p"];
}
?>
<!DOCTYPE html>
<head>
    <!--@author      Micke Josefsson (micke_josefsson (at) hotmail) -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Notepad" />
    <meta name="author" content="Micke Josefsson">    
    <title>Login</title>
    
    <link rel="stylesheet" href="css/themes/shbf.min.css" />
    <link rel="stylesheet" href="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile.structure-1.3.2.min.css" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="//ajax.aspnetcdn.com/ajax/jquery.mobile/1.3.2/jquery.mobile-1.3.2.min.js"></script>    

    <script type="text/javascript">
     //inträffar efter pageinit , popup kräver $.mobile.activePage som inte är klar i pageinit.	
    $(document).on('pageshow',"#loginpage", function(){
	$('#popupLogin').popup('open');
    });
    
	
    $(document).on('pageinit',"#loginpage",function(event) {
	$('#loginform').on('submit',function(e) {  
    
	    
             $.ajax({
                url: "./php/admin_ajax.php",
                type: "POST",
		cache : false,
		dataType: 'json',
                data: {
		    operation: 'login',
                    un: $("#un").val(),
                    pw: $("#pw").val(),
		    
                },
                success: function(response)
                {
	    
		    if (response.usrmsg  == "OK")
                    {
                        window.location.replace(response.page);
                    }
                    else
                    {
                        //$("#errorMessage").html(response);
			alert ("Försök igen?");
                    }
                },
		    error: function(xhr, status, thrown) {
			alert("serverfel, sysstatus: " + status + xhr + thrown); 
		    }  		
            });
	    return false;  
        });
	
});
        </script>
</head>
<body>
<!--huve-->
    <div data-role="page" id="loginpage">
	
    <div class="ui-header ui-bar-a" data-swatch="a" data-theme="a" data-form="ui-bar-a" data-role="header" role="banner">
	<a class="ui-btn-left ui-btn ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="none" data-icon="home" title=" Home ">
	    <span class="ui-btn-inner ui-btn-corner-all">
		<span class="ui-btn-text"> Home </span>
		<span data-form="ui-icon" class="ui-icon ui-icon-home ui-icon-shadow"></span>
	    </span>
	</a>
	<h1 class="ui-title" tabindex="0" role="heading" aria-level="1" data-mini="true">SM 2014</h1>
	<a class="ui-btn-right ui-btn ui-btn-icon-notext ui-btn-corner-all ui-shadow ui-btn-up-a" data-iconpos="notext" data-theme="a" data-role="button" data-icon="grid" title=" Navigation ">
	    <span class="ui-btn-inner ui-btn-corner-all">
		<span class="ui-btn-text"> Navigation </span>
		<span data-form="ui-icon" class="ui-icon ui-icon-grid ui-icon-shadow"></span>
	    </span>
	</a>
    </div>  	
	
	
	<!--<a href="#popupLogin" data-rel="popup" data-transition="pop" data-inline="true" data-role="button"  >Basic Popup</a>-->
	<div data-role="popup" id="popupLogin" data-theme="a" class="ui-corner-all">
	    <form id="loginform" data-ajax="false">
		<div style="padding:10px 20px;">
		    <h3>Logga in</h3>
		    <label for="un" class="ui-hidden-accessible">Användare:</label>
		    <input type="text" name="user" id="un" value="" placeholder="username" data-theme="a">
		    <label for="pw" class="ui-hidden-accessible">Lösenord:</label>
		    <input type="password" name="pass" id="pw" value="" placeholder="password" data-theme="a">
		    <button type="submit" data-theme="b" data-icon="check">Logga in</button>
		</div>
	    </form>
	</div>
    </div>
    
</body>

