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

//dev note: we stick to one file per page, for speed, even though some helpers will be redundant
//(you might even want to paste the minified version string into the html file to further improve speed on mobile devices)

//jquery mobile 1.3+/jquery 1.9+ 
$(document).on('pageinit',"#votepage",function(event) { 
    var statusDiv = $('#statusdiv');
    var sysbar = $('#sysbar');
    sysbar.hide();
    $('.error').hide();
    $('#vote_code').focus();

    
    if (supports_html5_storage())
    {
        var ls_vote_code = localStorage["vote_code"];
        if (ls_vote_code != "")
            $('#vote_code').val(ls_vote_code);
     
    }
    //onload get any saved votes + get sys status (if competition is open etc)
    votejs.init();
   
    $('#vote_code').on('keyup',function(e){
        
        var vote_code = $(this).val();
        
        if (vote_code.length < votejs.VOTE_CODE_LEN) {
            
            statusDiv.html('<div class="infobar infobar-neutral" data-mini="true">Välkommen! ange först din röstkod.</div>');
            return false;
        }
        else{
            var ajax_load = "<img src='js/loading.gif'  alt='loading...' />";
            statusDiv.html(ajax_load);
            votejs.reread();

            
        }
        return false;
    
    
    });
    $('#statform_trigger').on('submit',function(e) {
        e.preventDefault();
        $('.error').hide();
        votejs.statFormEval(this);
    });	    
    //submittar något av våra voteforms
    $('.voteform_trigger').on('submit',function(e){  

        e.preventDefault();
        $('.error').hide();
        votejs.voteFormEval(this);
    });
});



//helpers & commmons
//(for speed we stick to one js file per page, although redundant)
function supports_html5_storage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}

function printInfobar(elemId, msgtype, usrmsg)
{
    if (usrmsg.length > 0) {
        if (msgtype == "ok" || msgtype == "ok-cached")
            $(elemId).html('<div class="infobar infobar-ok" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "neutral")
            $(elemId).html('<div class="infobar infobar-neutral" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "warning")
            $(elemId).html('<div class="infobar infobar-warning" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "error")
            $(elemId).html('<div class="infobar infobar-error" data-mini="true">' + usrmsg + '</div>');
        $(elemId).show();			
    }	    
    
}


//model for client vote logic
votejs = function(){
    //we check some rules client-side, but they're also checked server-side (so don't bother)
    //and we retrieve sys status (is competition open etc) using a timer
    var DEBUGMODE = false;
    var DISABLE_CLIENT_CHECKS = true;
    
    var MAX_SAME_VOTES = 0;
    var DUPE_VOTE_REQUIRE_ALL = true;
    var VOTES_PER_CAT = 0;
    var VOTES_REQUIRE_ALL = false;
    var VOTE_CODE_LEN = 3;
    var REQUEST_SYSSTATUS = false;
    var sysstatusInterval = 9999; //todo uppercase
    var sysstatustmr = null;
    
    //to store & retrieve the voting categories for this competition.
    var sys_cat = [];
    
    

    
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
                        DISABLE_CLIENT_CHECKS =  response.DISABLE_CLIENT_CHECKS;                       
                        REQUEST_SYSSTATUS = response.REQUEST_SYSSTATUS;
                        sysstatusInterval = response.SETTING_SYSSTATUS_INTERVAL;
                        
                        VOTE_CODE_LEN = response.CONST_SETTING_VOTE_CODE_LENGTH;
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
                    
                    printInfobar('#statusdiv', 'warning', 'serverfel 1-1');
                }
    
            },
            error: function(xhr, status, thrown) {
                printInfobar('#statusdiv', 'warning', 'serverfel 1-2');
            }  
        });
    };

    
    function reread() {

        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'json', //note: kräver post
            cache : false,
            data: {
                operation: 		'reread', 
                source:         	'index.php',
                vote_code:		$('#vote_code').val()
            },

            success: function(response) {
                if (DEBUGMODE) console.log(response);
                var disinput = true;
                if (response.msgtype == "ok"){
                    $('#vote_code').val(response.vote_code); //uppercased etc
                    if (supports_html5_storage()) 
                        localStorage['vote_code'] = response.vote_code;
                    disinput = false;
                }
                else if (response.msgtype == "ok-cached"){
                    if (supports_html5_storage()) 
                        localStorage['vote_code'] = response.vote_code;
                    disinput = false;
                }
                if (response.usrmsg != null) {
                    printInfobar('#statusdiv', response.msgtype, response.usrmsg);
                }
                $.each(sys_cat,function(index,value){
                    for (i = 1; i <= VOTES_PER_CAT; i++){
                        $('#' + value + '_vote' + i).prop( "disabled", disinput ); //prevent input on bad code
                        $('#' + value + '_vote' + i).val(response['vote_' + i + '_' + value]); //set previusly saved votes, ie  $('#lager_vote1').val(response.vote_1_lager); etc
                        
                    }
                });
                $('#submit_stat').prop("disabled", disinput);
                
                var errortags = $('.error');
                errortags.html("");
                errortags.hide();

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE)  alert("serverfel, reread: " + status + xhr + thrown); 
            }  
        });
    };
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
                    //an error does occur while (if) the server-side params are being updated by competition management, so we must ignore.
                    //if (DEBUGMODE)  alert("serverfel, sysstatus: " + status + xhr + thrown); 
                }  
            });	
        }
    };
    
    function voteFormEval(formref){
        var $formId = $(formref);
        
        
        var vote_code = $("#vote_code").val(); //inte i detta form
        var category = $('.vote_category',$formId).attr('id').toLowerCase(); ///lager,ale etc
        var statusId = $('.formstatus_trigger',$formId);
        var votes = [];
        var checkfail = false;
        var votes_filled = 0;
         
        //kontrollera inputs längder i varje li (inputs är numeric så "om siffra" behöver inte kollas)
        //sätt dold .error div med text vid fel
        //annars lägg in värde i votes
        $('li',$formId).each(function(){
            //obs: $this refererar till li, går ej backa längre upp i DOM än $this
            var voteInput = $(this).find('input');
            if (voteInput != null){ //exkl li's utan input (submit li)
                var voteval = voteInput.val();
                if (typeof voteval != 'undefined')
                {    
                    if (voteval != null && voteval.length != 3 && voteval.length > 0)
                    {
                        checkfail = true;
                        var errortag = $(this).find('.error');
                        if (errortag != null)
                        {
                            errortag.html("Tre siffror förväntas")
                            errortag.slideDown();
                        }
                        else if (DEBUGMODE)
                           console.log('check html, missing an error tag for ' + category);
                         
                    }
                    else if (voteval != null && voteval.length != 0) {
                        votes_filled++;
                    }
                    votes.push(voteval);
                }

           }
        });
        
        if (!DISABLE_CLIENT_CHECKS){
            if (MAX_SAME_VOTES != -1 || VOTES_REQUIRE_ALL) {
            
                var arrcheck = votes.slice(0); //clone
                var identicalmaxcount = 1;
                var identicalmaxcount_max = 1;
                var missrequired = false;
                //check that identical votes doesn't exceed max allowed.
                 while (arrcheck.length > 0) {
                    var v = arrcheck.pop();
                    var cc;
                    if (v != ""){ //ignore empty votes
                        //count & remove all identical
                        while (arrcheck.length > 0 && (cc = arrcheck.indexOf(v)) != -1) {
                            identicalmaxcount++;
                            arrcheck.splice(cc,1); 
                        }
                        if (identicalmaxcount > identicalmaxcount_max)
                            identicalmaxcount_max = identicalmaxcount;
                        identicalmaxcount = 1; //ready for next vote-number
                    }
                    else if (VOTES_REQUIRE_ALL === true) {
                        missrequired = true;
                    }
                }
                if (missrequired) {
                    checkfail = true;
                    statusId.html('Alla röster måste fyllas i, försök igen');
                    statusId.fadeToggle();
            
                }
                if (identicalmaxcount_max > MAX_SAME_VOTES && MAX_SAME_VOTES != -1){
                    checkfail = true;
                    if (MAX_SAME_VOTES == 1) { 
                        statusId.html('Du kan bara rösta ' + MAX_SAME_VOTES + ' gång på samma öl');
                    }
                    else
                        statusId.html('Du kan rösta max ' + MAX_SAME_VOTES + ' gånger på samma öl');
                        
                    statusId.fadeToggle();
                    
                    
                }
                else if (identicalmaxcount_max > 1 && votes_filled != VOTES_PER_CAT && DUPE_VOTE_REQUIRE_ALL == true) {
                    checkfail = true;
                    statusId.html('Alla ' + VOTES_PER_CAT + ' röster måste fyllas om du röstar +1 gång på samma öl');
                    statusId.fadeToggle();		    
            
                }
            }
        }
        
        
        if (checkfail) 
            return false;
        var vdata = {
                vote_code: 		vote_code,
                category: 		category,
                operation: 		'post_vote',
                source:         	'index.php'
        };
        $.each(votes, function (index, vote) { //append votes
                 vdata['vote_' + (index+1)] = vote;
        });
        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'html',
            cache : false,
            data : vdata,
            //data: {
            //    vote_code: 		vote_code,
            //    vote_1:		votes[0], //sup3
            //    vote_2:		votes[1],
            //    vote_3:		votes[2],
            //    category: 		category, 
            //    operation: 		'post_vote',
            //    source:         	'index.php'
            //},

            success: function(response) {
                if (DEBUGMODE) console.log(response);
                statusId.html(response);
                statusId.fadeToggle();

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
                statusId.html("serverfel: " + status);
                statusId.fadeToggle();
            }  
        });
        return false;  
        
    };
    function statFormEval(formref){
        var $formId = $(formref);
        var statusId = $('.formstatus_trigger',$formId);
        var gender = $('input[name="stat1"]:checked',$formId).val();
        var age = $('#stat2',$formId).val();
        var location = $('#stat3',$formId).val();
        var firstSM = $('input[name="stat4"]:checked',$formId).val();
        var vote_code = $("#vote_code").val(); //inte i detta form
        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'html',
            cache : false,
            data: {
                vote_code: 		vote_code,
                gender:		gender,
                age:		age,
                location:		location,
                firstSM: 		firstSM, 
                operation: 		'post_stat',
                source:         	'index.php'
            },

            success: function(response) {
                if (DEBUGMODE) console.log(response);
                statusId.html(response);
                statusId.fadeToggle();

            },
            error: function(xhr, status, thrown) {
                if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
                statusId.html("serverfel: " + status);
                statusId.fadeToggle();
            }  
        });
        return false; 	    

    }
    
    function init(){
        getVoteSettings();
        reread();
        //uppdatera systemstatus kontinuerligt
        sysstatustmr = window.setInterval(sysstatus, sysstatusInterval);
        sysstatus();
    };
    
    return {
        init : init,
        reread: reread,
        sysstatus : sysstatus,
        voteFormEval : voteFormEval,
        statFormEval : statFormEval
    }    

}(); //eo votejs

