/*Webvote -*- coding: utf-8 -*-
 *Copyright (C) 2014 Mikael Josefsson
 *Modifications copyright 2016 Staffan Ulfberg
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
    $('#sysbar').hide();
    $('.error').hide();
    $('#vote_code').focus();

    var vote_code = null;
    if (supportsHtml5Storage())
    {
        vote_code = localStorage.getItem("vote_code");
        if (vote_code != null) {
            $('#vote_code').val(vote_code);
	}
    }
    votejs.init();
    rereadOrSetMessage(vote_code);

    $('#vote_code').on('keyup', function(e)
    {
        var vote_code = $(this).val();
        rereadOrSetMessage(vote_code);
        return false;
    });

    $('.voteform_trigger').on('submit',function(e)
    {  
        e.preventDefault();
        $('.error').hide();
        votejs.voteFormEval(this);
    });
});

function rereadOrSetMessage(vote_code) {
    var statusDiv = $('#statusdiv');
    if (vote_code == null || vote_code.length != 6) {
        
        statusDiv.html('<div class="infobar infobar-neutral" data-mini="true">Välkommen! Ange din röstkod.</div>');
    }
    else
    {
        var ajax_load = "<img src='img/loading.gif' alt='loading...' />";
        statusDiv.html(ajax_load);
        votejs.reread(vote_code);
    }
}

function supportsHtml5Storage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}

function printInfobar(element, level, message)
{
    var style_class = '';
    switch (level) {
    case "ERROR":
	style_class = 'infobar-error';
	break;
    case "WARNING":
	style_class = 'infobar-warning';
	break;
    case "OK":
	style_class = 'infobar-ok';
	break;
    }
    element.html('<div class="infobar ' + style_class
		 + '" data-mini="true"><p>' + message + '</p></div>');
    element.show();			
}

votejs = function()
{
    var competition_id = null;
    var status_interval = 10000;
    var status_timer = null;

    var VOTES_PER_CAT = 3;
    
    function reread(vote_code)
    {
        $.ajax({  
            type: "POST",
            url: "ajax/vote.php",  
	    contentType: 'application/json',
            dataType: 'json',
            cache : false,
            data: JSON.stringify({
		competition_id: competition_id,
                vote_code: vote_code
            }),

            success: function(response) {
                var disableInput = true;
                if ("vote_code" in response)
		{
                    $('#vote_code').val(response.vote_code); //uppercased etc
                    if (supportsHtml5Storage())
		    {
                        localStorage['vote_code'] = response.vote_code;
		    }
                    disableInput = false;
                }
		
                printInfobar($('#statusdiv'), response.msgtype, response.usrmsg);

		sys_cat = response.sys_cat;
                $.each(sys_cat, function(index, value)
		{
                    for (i = 1; i <= VOTES_PER_CAT; i++)
		    {
                        $('#' + value + '_vote' + i).prop("disabled", disableInput); // prevent input on bad vote code

			var vote = "votes" in response ? response.votes[value][i] : '';
                        $('#' + value + '_vote' + i).val(vote);
                    }
                });

            },
            error: function(xhr, textStatus, errorThrown)
	    {
		alert("error: " + textStatus + ", responseText: " + xhr.responseText); 
            }  
        });
    };

    function sysstatus(args)
    {
        $.ajax({  
            type: "POST",
            url: "ajax/status.php",
	    contentType: 'application/json',
            dataType: 'json',
            cache : false,
	    data : JSON.stringify({
		competition_id: competition_id
            }),
            success: function(response) {
                $("#competition_header").text(response.competition_name);
		
                if (response.interval != status_interval)
                {
                    clearInterval(status_timer);
                    status_interval = response.update_interval;
                    status_timer = window.setInterval(sysstatus, status_interval)
                }

		if (response.competition_seconds_to_close < 60) {
		    var alert_level = 'WARNING'
		} else if (response.competition_seconds_to_close < 600) {
		    var alert_level = 'ERROR'
		} else {
		    var atert_level = 'OK'
		}

                printInfobar($('#sysbar'), alert_level, response.competition_status);
            },
            error: function(xhr, textStatus, errorThrown)
	    {
		alert("error: " + textStatus + ", responseText: " + xhr.responseText); 
            }  
        });	
    };
    
    function voteFormEval(formref){
        var $formId = $(formref);
        
        var vote_code = $("#vote_code").val();
        var category_id = $('.vote_category', $formId).attr('id');
        var statusId = $('.formstatus_trigger', $formId);

        var votes = {};
	for (var i = 1; i <= 3; i++) {
            var voteval = $('#' + category_id + '_vote' + i).val();
	    if (voteval != '')
	    {
		if (voteval.length != 3)
		{
                    var errortag = $('div #' + category_id + "_vote" + i + ' .error').find('.error');
                    if (errortag != null)
                    {
			errortag.html("Tre siffror förväntas")
			errortag.slideDown();
                    }
                    else
		    {
			console.log('check html, missing an error tag for ' + category);
		    }
		    return false;
		}
		for (var j = 1; j < i; j++)
		{
		    if (votes[j] == voteval) {
			statusId.html('Högst en röst per öl');
			statusId.fadeToggle();
			return false;
		    }
		}
	    }
            votes[i] = voteval;
	}
        
        var vdata = {};
	vdata[category_id] = votes;
        $.ajax({  
	    type: "POST",  
            url: "ajax/vote.php",
	    contentType: 'application/json',
            dataType: 'json', 
            cache : false,
            data : JSON.stringify({
		competition_id: competition_id,
		vote_code: vote_code,
		votes: vdata
            }),
	    
            success: function(response) {
		if (response.resetVoteCode) {
                    $('#vote_code').val('');
                    if (supportsHtml5Storage())
		    {
                        localStorage['vote_code'] = '';
		    }

		    sys_cat = response.sys_cat;
                    $.each(sys_cat, function(index, value)
			   {
			       for (i = 1; i <= VOTES_PER_CAT; i++)
			       {
				   $('#' + value + '_vote' + i).val('');
			       }
			   });
		}
                statusId.html(response.vote_result.usrmsg);
                statusId.fadeToggle();
            },
            error: function(xhr, textStatus, errorThrown)
	    {
		statusId.html("error: " + textStatus + ", responseText: " + xhr.responseText); 
                statusId.fadeToggle();
            }  
        });
    };
    
    function init()
    {
	competition_id = $('#votepage').attr('data-competition-id');
        status_timer = window.setInterval(sysstatus, status_interval);
        sysstatus();
    };
    
    return {
        init : init,
        reread : reread,
        sysstatus : sysstatus,
        voteFormEval : voteFormEval
    }    
}();
