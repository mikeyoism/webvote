/*Webvote -
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

    jQuery(document).ready(function($) {
    //$(document).on('pageinit',function(event) { 
	var statusDiv = $('#statusdiv');
	$('.error').hide();
        
	votejs.init();
	
	statusDiv.hide();

	
	$('#vote_code').on('keyup focusout',function(e){
	    
	    var vote_code = $(this).val();
	    
	    if (e.type == "keyup" && vote_code.length < 3) {
		return false;
	    }
	    else if (e.type == "focusout" && vote_code.length < 3) {
		statusDiv.html('<div class="infobar infobar-warning" data-mini="true">Kod ska vara 3 tecken!</div>');
		statusDiv.fadeIn();
	    }
	    else{
		//var ajax_load = "<img src='js/loading.gif'  alt='loading...' />";
		//statusDiv.html(ajax_load);
		
		votejs.preCodeCheck();
	    }
	    return false;
	
	
	});

	//submittar votes
	$('.voteform_trigger').on('submit',function(e) {  
    
	    e.preventDefault();
	    
	    votejs.postvotes(0);
	    return false;  
	    
	});


	//jquery UI - applicera thema
	$(function() {
	$( "input[type=submit], a, button, input" )
	    .button()
	.css({

	    'text-align' : 'left',
	       'outline' : 'none',
		'cursor' : 'auto'
	  })	    
	});
	

});



//model for client vote logic
votejs = function(){
    //we check some rules client-side, but they're also checked server-side (so don't bother)
    //and we retrieve sys status (is competition open etc) using a timer
    var DEBUGMODE = false;
    var MAX_SAME_VOTES = 0;
    var DUPE_VOTE_REQUIRE_ALL = true;
    var VOTES_PER_CAT = 0;
    var VOTES_REQUIRE_ALL = false;
    var REQUEST_SYSSTATUS = false;
    var sysstatusInterval = 9999; //todo uppercase
    var sysstatustmr = null;

    //to store & retrieve the voting categories for this competition.
    var sys_cat = [];
    
    var prev_code = "";

    
    function getVoteSettings() {
    
        $.ajax({  
            type: "POST",  
            url: "./php/jssettings.php",  
            dataType: 'json', 
            cache : false,
            async : false,
            data: {
                operation: 		'getjssettings', 
                source:         	'index.php',
                subset:         	''
            },
    
            success: function(response) {
                if (response.msgtype == "ok"){
                        DEBUGMODE = response.CONST_SYS_JS_DEBUG;
                        REQUEST_SYSSTATUS = response.REQUEST_SYSSTATUS;
                        sysstatusInterval = response.SETTING_SYSSTATUS_INTERVAL;
    
                        MAX_SAME_VOTES = response.CONST_SETTING_VOTES_PER_CATEGORY_SAME;
                        DUPE_VOTE_REQUIRE_ALL = response.CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL;
			VOTES_PER_CAT = response.CONST_SETTING_VOTES_PER_CATEGORY;
			VOTES_REQUIRE_ALL = response.CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL;
                        
                        if (DEBUGMODE) console.log(response);
                        
                        sys_cat = response.CONST_SETTING_CATEGORIES_SYS;
                        var competition_name = response.SETTING_COMPETITION_NAME;
                        $("#competition_header").text(competition_name);
                }
                else {
                    
                    printInfobar('#statusdiv', 'warning', 'serverfel 1-1',true);
                }
    
            },
            error: function(xhr, status, thrown) {
                printInfobar('#statusdiv', 'warning', 'serverfel 1-2',true);
            }  
        });
    };
    

    function reread() {

        var novotes = false;
        $.ajax({  
            type: "POST",  
            url: "./php/manreg_ajax.php",  
            dataType: 'json', //note: kräver post
            cache : false,
            data: {
                operation: 		'mr_reread', 
                source:         	'manregp.php',
                vote_code:		$('#vote_code').val()
            },

            success: function(response) {
                if (DEBUGMODE) console.log(response);

                if (response.novotes == null) {
                  
                      printInfobar('#statusdiv', response.msgtype, "Dina befintliga röster läses in.");
                      $('#statusdiv').fadeOut(3500); //dölj utan message
    
                }

                $.each(sys_cat,function(index,value){
                    for (i = 1; i <= VOTES_PER_CAT; i++){
                        $('#' + value + '_vote' + i).val(response['vote_' + i + '_' + value]); //set previusly saved votes, ie  $('#lager_vote1').val(response.vote_1_lager); etc
                    }
                });	
            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE)  alert("serverfel 1-3, reread: " + status + xhr + thrown); 
            }  
        });
        return novotes;
    }	
    
    function sysstatus(args) {
        if (REQUEST_SYSSTATUS == true){
            $.ajax({  
                type: "POST",  
                url: "./php/vote_ajax.php",  
                dataType: 'json', 
                cache : false,
                data: {
                        operation: 	'sysstatus', 
                        source:    	'index.php',
                        my_interval: sysstatusInterval
                },

                success: function(response) {
                    if (DEBUGMODE) console.log(response);
                    if (response.msgtype == "interval" && sysstatustmr != null)
                    {
                        
                        clearInterval(sysstatustmr);
                        sysstatusInterval = response.interval;
                        sysstatustmr = window.setInterval(sysstatus,response.interval)
                        
                    }
                    else if (response.msgtype == "stop"){
                        $('#sysbar').hide();
                        REQUEST_SYSSTATUS = false;
                    }
                    else if (response.usrmsg.length > 0) {
                        printInfobar('#sysbar', response.msgtype, response.usrmsg);
                       
                    }
                    else
                        $('#sysbar').hide();
                },
                error: function(xhr, status, thrown) {
                    //det uppstår fel här ibland, runtomkring när serverparam uppdateras...så unvik bry dig
                    //if (DEBUGMODE)  alert("serverfel, sysstatus: " + status + xhr + thrown); 
                }  
            });	
        }
    }
    
    function preCodeCheck() {

        $.ajax({  
            type: "POST",  
            url: "./php/manreg_ajax.php",  
            dataType: 'json', //note: kräver post
            cache : false,
            data: {
                operation: 		'mr_pre_codecheck', 
                source:         	'manreg.php',
                vote_code:		$('#vote_code').val()
            },

            success: function(response) {
                if (DEBUGMODE) console.log(response);

                if (response.msgtype == "ok"){
                    if (prev_code != $('#vote_code').val()) { //en gång med samma kod
                        $('#vote_code').val(response.vote_code); //uppercased etc
                        prev_code = $('#vote_code').val();
                        clearVoteFields(true);
                        $('#statusdiv').hide(); //resetta ev gammla fel
                        reread();
                    }
                }
                else if (response.usrmsg != null){
                    printInfobar('#statusdiv', response.msgtype, response.usrmsg);
                    prev_code = "";
                }

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE)  alert("serverfel 1-4, preCodeCheck: " + status + xhr + thrown); 
            }  
        });
    }
    function clearVoteFields(excludeCode)
    {
        if (!excludeCode) {
            $("#vote_code").val('');
            prev_code = "";
        }
        var $formId = $('#voteregform');
        
        
        $('div.vote_category',$formId).each(function(){
            var catName = $(this).attr('id');
            var c = 1;
            $('li',$(this)).each(function(){
                var voteInput = $(this).find('input');
                if (voteInput != null){ //exkl li's utan input (submit li)
                    voteInput.val('');
                }
            });
        });
        $('#vote_code').focus();
    }
    function getVoteData()
    {
        var vote_code = $("#vote_code").val(); //inte i detta form
        var votes = [];
        var votes_filled = 0;
        var $formId = $('#voteregform');
        
        $('div.vote_category',$formId).each(function(){
            var catName = $(this).attr('id');
            var c = 1;
            $('li',$(this)).each(function(){
                var voteInput = $(this).find('input');
                if (voteInput != null){ //exkl li's utan input (submit li)
                    var voteval = voteInput.val();
                    votes_filled++;
                    var id = "vote_" + c++ + "_" + catName;
                    votes.push({voteid: id, vote: voteval});
               }
            });
        });
        

         var vdata = {
                vote_code: 		vote_code,
                operation: 		'mr_post_votes_public',
                source:         	'manreg.php'
            } ;
            $.each(votes, function (index, vote) { //appenda röster, nycklar "vote_1_gul etc
                     vdata[vote.voteid] = vote.vote;
                })	    
        return vdata;
    }
    //errstate 0 = new vote, 1=korrigerat fel, 2=bekräfta fel
    function postvotes(errstate){
        
        var $formId = $(this);
        var vote_code = $("#vote_code").val(); //inte i detta form
        var statusId = $('.formstatus_trigger',$formId);
        var vdata = getVoteData();
        vdata['confirmed_error'] = errstate;
        $.ajax({  
            type: "POST",  
            url: "./php/manreg_ajax.php",  
            dataType: 'json',
            cache : false,
            data: vdata ,

            success: function(response) {

                if (DEBUGMODE) console.log(response);
                var box = $('#form_submit_status');
                var usrmsg = response.usrmsg.replace("\n","<br>");
                var msgtype = response.msgtype;
                if (usrmsg.length > 0 && (msgtype == "warning" || msgtype == "error"))
                {
                    
                    usrmsg = "<div class=\"infobar infobar-neutral\" style=\" margin-bottom: 1em\"><b>Fel upptäckta. Försök igen. " +
                             "<br>Felen visas nedan:</div>" + usrmsg;

                    $(box).html('<div class="infobar infobar-warning" data-mini="true">' + usrmsg + '</div>');
                    if ($(box).is(':visible')) //flasha vid nytt fel, så nåt märks
                    {
                        $(box).fadeOut();		
                    }
                    $(box).fadeIn();		
                }
                else //OK
                {
                    //flasha kort stund, dölj sen allt
                    $(box).html('<div class="infobar infobar-ok" data-mini="true">' + usrmsg + '</div>');
                    $(box).fadeIn().delay(300).fadeOut(3000);
                    
                    clearVoteFields();
                    //$('#vote_code_label').fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100); //uppmärksamma user att börja om me nytt röstkort.
                    
                }

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
                statusId.html("serverfel 1-5: " + status);
                statusId.fadeToggle();
            }  
        });	    
        
    }
    function init(){
        getVoteSettings();
        clearVoteFields();
        //reread(); - no point before preCodeCheck @ public page
        //uppdatera systemstatus kontinuerligt
        sysstatustmr = window.setInterval(sysstatus, sysstatusInterval);
        sysstatus();        
    };
    
    return {
        init : init,
        preCodeCheck : preCodeCheck,
        postvotes : postvotes,
    }     
    
}(); //eo votejs

