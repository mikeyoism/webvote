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
    //bekräftar fel
    //bekräftar fel
    $('#error_corr').on('click',function(e) {  

        e.preventDefault();
        
        votejs.postvotes(1);
        return false;  
        
    });	
    $('#error_ack').on('click',function(e) {  

        e.preventDefault();
        
        votejs.postvotes(2);
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
                        //REQUEST_SYSSTATUS = response.REQUEST_SYSSTATUS;
                        //sysstatusInterval = response.SETTING_SYSSTATUS_INTERVAL;
    
                        MAX_SAME_VOTES = response.CONST_SETTING_VOTES_PER_CATEGORY_SAME;
                        DUPE_VOTE_REQUIRE_ALL = response.CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL;
                        VOTES_PER_CAT = response.CONST_SETTING_VOTES_PER_CATEGORY;
                        VOTES_REQUIRE_ALL = response.CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL;
                        
                        if (DEBUGMODE) console.log(response);
                        
                        //sys_cat = response.CONST_SETTING_CATEGORIES_SYS;
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
    

    function preCodeCheck() {

        $.ajax({  
            type: "POST",  
            url: "./php/manreg_ajax.php",  
            dataType: 'json', //note: kräver post
            cache : false,
            data: {
                operation: 		'mr_pre_codecheck', 
                source:         	'manreg_adm.php',
                vote_code:		$('#vote_code').val()
            },

            success: function(response) {
                if (DEBUGMODE) console.log(response);

                if (response.msgtype == "ok"){
                    $('#vote_code').val(response.vote_code); //uppercased etc
                    $('#statusdiv').fadeOut(); //dölj utan message
                }
                else if (response.usrmsg != null){
                    printInfobar('#statusdiv', response.msgtype, response.usrmsg,true);
                }

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE)  alert("serverfel, preCodeCheck: " + status + xhr + thrown); 
            }  
        });
    }
    function clearVoteFields()
    {
        $("#vote_code").val('');
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
                operation: 		'mr_post_votes',
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
                    
                    usrmsg = "<div class=\"infobar infobar-neutral\" style=\" margin-bottom: 1em\"><b>Fel upptäckta. Dubbelkolla att <i>DU</i> som funktionär matat in exakt vad som står på inlämnat röstkort.</b><br>--Tryck 'Bekräfta fel' om felet finns även på inlämnat röstkort. " +
                             "<br>--Eller rätta till och tryck sedan 'Korrigera' om felet berodde på att <i>DU</i> skrev av röstkortet fel.<br>Felen visas nedan:</div>" + usrmsg;

                    $(box).html('<div class="infobar infobar-warning" data-mini="true">' + usrmsg + '</div>');
                    if ($(box).is(':visible')) //flasha vid nytt fel, så nåt märks
                    {
                        $(box).fadeOut();		
                    }
                    $(box).fadeIn();		
                    $("#err_butts").show();
                    $("#submit_votes").hide();
                    $("#error_ack").focus();
                }
                else //OK
                {
                    //flasha kort stund, dölj sen allt
                    $(box).html('<div class="infobar infobar-ok" data-mini="true">' + usrmsg + '</div>');
                    $(box).fadeIn().delay(300).fadeOut(1000);
                    
                    $("#err_butts").hide();
                    $("#submit_votes").show();
                    clearVoteFields();
                    $('#vote_code_label').fadeOut(100).fadeIn(100).fadeOut(100).fadeIn(100); //uppmärksamma user att börja om me nytt röstkort.
                    
                }

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
                statusId.html("serverfel: " + status);
                statusId.fadeToggle();
            }  
        });	    
        
    }

    function init(){
        getVoteSettings();
        clearVoteFields();
    };
    
    return {
        init : init,
        clearVoteFields: clearVoteFields,
        preCodeCheck : preCodeCheck,
        postvotes : postvotes,
    }     
    
}(); //eo votejs


