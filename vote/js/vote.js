/*Webvote -*- coding: utf-8 -*-
 *Copyright (C) 2014 Mikael Josefsson
 *Modified 2016 Staffan Ulfberg
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

//jquery mobile 1.3+/jquery 1.9+ 

$(document).on('pageinit',"#votepage",function(event)
{
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
   
    $('#vote_code').on('keyup', function(e)
    {
        var vote_code = $(this).val();
        
        if (vote_code.length < 6) {
            
            statusDiv.html('<div class="infobar infobar-neutral" data-mini="true">Välkommen! ange först din röstkod.</div>');
            return false;
        }
        else
	{
            var ajax_load = "<img src='js/loading.gif' alt='loading...' />";
            statusDiv.html(ajax_load);
            votejs.reread();
            
        }
        return false;
    });
    //submittar något av våra voteforms
    $('.voteform_trigger').on('submit',function(e)
    {  
        e.preventDefault();
        $('.error').hide();
        votejs.voteFormEval(this);
    });
});


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

votejs = function()
{
    var MAX_SAME_VOTES = 1;
    var VOTES_PER_CAT = 3;
    var VOTES_REQUIRE_ALL = false;
    var sysstatusInterval = 10000; //todo uppercase
    var sysstatustmr = null;
    
    function reread()
    {
        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'json', //note: kräver post
            cache : false,
            data: {
                operation: 		'reread', 
                vote_code:		$('#vote_code').val()
            },

            success: function(response) {
                var disinput = true;
                if (response.msgtype == "ok")
		{
                    $('#vote_code').val(response.vote_code); //uppercased etc
                    if (supports_html5_storage()) 
                        localStorage['vote_code'] = response.vote_code;
                    disinput = false;
                }
                else if (response.msgtype == "ok-cached")
		{
                    if (supports_html5_storage()) 
                        localStorage['vote_code'] = response.vote_code;
                    disinput = false;
                }
                if (response.usrmsg != null)
		{
                    printInfobar('#statusdiv', response.msgtype, response.usrmsg);
                }
		sys_cat = response.sys_cat;
                $.each(sys_cat,function(index,value)
		{
                    for (i = 1; i <= VOTES_PER_CAT; i++)
		    {
                        $('#' + value + '_vote' + i).prop( "disabled", disinput); //prevent input on bad code
                        $('#' + value + '_vote' + i).val(response['vote_' + i + '_' + value]); //set previusly saved votes, ie  $('#lager_vote1').val(response.vote_1_lager); etc
                    }
                });
                $('#submit_stat').prop("disabled", disinput);
                
                var errortags = $('.error');
                errortags.html("");
                errortags.hide();

            },
            error: function(xhr, status, thrown)
	    {
		alert("serverfel, reread: " + status + xhr + thrown); 
            }  
        });
    };

    function sysstatus(args) {
        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'json', 
            cache : false,
            data: {
                operation: 	'sysstatus', 
                my_interval: sysstatusInterval
            },
	    
            success: function(response) {
		if (response.competition_name.length > 0)
		{
                    $("#competition_header").text(response.competition_name);
		}
		
                if (response.msgtype == "interval" && sysstatustmr != null)
                {
                    clearInterval(sysstatustmr);
                    sysstatusInterval = response.interval;
                    sysstatustmr = window.setInterval(sysstatus,response.interval)
                }
                else if (response.msgtype == "stop")
		{
                    $('#sysbar').hide();
                    REQUEST_SYSSTATUS = false;
                }
                else if (response.usrmsg.length > 0)
		{
                    printInfobar('#sysbar', response.msgtype, response.usrmsg);
                }

                else
                    $('#sysbar').hide();
            },
            error: function(xhr, status, thrown) {
                alert("serverfel, sysstatus: " + status + xhr + thrown); 
            }  
        });	
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
                    if (false && voteval != null && voteval.length != 3 && voteval.length > 0)
                    {
                        checkfail = true;
                        var errortag = $(this).find('.error');
                        if (errortag != null)
                        {
                            errortag.html("Tre siffror förväntas")
                            errortag.slideDown();
                        }
                        else
			{
                            console.log('check html, missing an error tag for ' + category);
			}
                    }
                    else if (voteval != null && voteval.length != 0) {
                        votes_filled++;
                    }
                    votes.push(voteval);
                }
		
            }
        });
        
        var arrcheck = votes.slice(0); // clone
        var identicalCount = 1;
        var identicalCount_max = 1;
        var missrequired = false;
	
        //check that identical votes doesn't exceed max allowed.
        while (arrcheck.length > 0) {
            var v = arrcheck.pop();
            var cc;
            if (v != "") // ignore empty votes
	    { 
                //count & remove all identical
                while (arrcheck.length > 0 && (cc = arrcheck.indexOf(v)) != -1)
		{
                    identicalCount++;
                    arrcheck.splice(cc, 1); 
                }
                if (identicalCount > identicalCount_max)
                    identicalCount_max = identicalCount;
                identicalCount = 1; // ready for next vote-number
            }
            else if (VOTES_REQUIRE_ALL === true)
	    {
                missrequired = true;
            }
        }
        if (missrequired)
	{
            checkfail = true;
            statusId.html('Alla röster måste fyllas i, försök igen');
            statusId.fadeToggle();
        }
        if (identicalCount_max > MAX_SAME_VOTES)
	{
            checkfail = true;
            statusId.html('Högst ' + MAX_SAME_VOTES + ' röst(er) per öl');
            statusId.fadeToggle();
        }
	
        if (checkfail)
	{
            return false;
	}
	
        var vdata = {
            vote_code: 		vote_code,
            category: 		category,
            operation: 		'post_vote'
        };
        $.each(votes, function (index, vote) { // append votes
            vdata['vote_' + (index+1)] = vote;
        });
        $.ajax({  
            type: "POST",  
            url: "./php/vote_ajax.php",  
            dataType: 'html',
            cache : false,
            data : vdata,
	    
            success: function(response) {
                statusId.html(response);
                statusId.fadeToggle();
		
            },
            error: function(xhr, status, thrown) {
                statusId.html("serverfel: " + status);
                statusId.fadeToggle();
            }  
        });
        return false;  
    };
    
    function init()
    {
        reread();
        //uppdatera systemstatus kontinuerligt
        sysstatustmr = window.setInterval(sysstatus, sysstatusInterval);
        sysstatus();
    };
    
    return {
        init : init,
        reread : reread,
        sysstatus : sysstatus,
        voteFormEval : voteFormEval
    }    
}();
