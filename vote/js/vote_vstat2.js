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
    
    votejs.init();
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
                        REQUEST_SYSSTATUS = response.REQUEST_SYSSTATUS;
                        sysstatusInterval = response.SETTING_SYSSTATUS_INTERVAL;
                       
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

    
    
    function sysstatus(args) {
	if (REQUEST_SYSSTATUS == true){
	    $.ajax({  
		type: "POST",  
		url: "./php/vote_ajax.php",  
		dataType: 'json', 
		cache : false,
		data: {
			operation: 	'sysstatus', 
			source:    	'vstat2.php',
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
			printInfobar('#sysbar', response.msgtype, response.usrmsg,true);
		       
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
    

    // prepare chart data as an array
    var sampleData = [
	    { category: 'Gul', unique_voters: 30, votes: 0},
	    { category: 'Grön', unique_voters: 25, votes: 25 },
	    { category: 'Blå', unique_voters: 30, votes: 0},
	    { category: 'Röd', unique_voters: 35, votes: 25 },
	    { category: 'Etikett', unique_voters: 0, votes: 20 }
    
	];

	// setup the chart
	//$('#jqxChart').jqxChart(settings);
	
    var ChartModel = function (items) {
	var self = this;
	//self.items = ko.observableArray(items);
	self.items = ko.observableArray([]);
	this.updateItem = function () {
	    //var newItems = new Array();
	    //for (var i = 0; i < 7; i++) {
	    //    var item = $.extend({}, sampleData[i]);
	    //    item.Keith = Math.floor(Math.random() * 50);
	    //    item.Erica = Math.floor(Math.random() * 30);
	    //    item.George = Math.floor(Math.random() * 90);
	    //    newItems[i] = item;
	    //}
	    //this.items(newItems);
	    $.ajax({  
		    type: "POST",  
		    url: "./php/admin_ajax.php",  
		    dataType: 'json',
		    cache : false,
		    async: false,   // forces synchronous call
		    data: {
			operation: 		'getvotestat_public', //getvotes etc
			category: 		 'category',
			source:         	'vstat.php',
		    },
	
		    success: function(response) {
			if (DEBUGMODE) console.log(response);
			self.items(response); //obs, tänk på ej tilldela(=), skriver över observable
		    }
	    });		    
	};
    };
    var grids = [];
    function initGridModel(){
	var cm = new ChartModel(sampleData);
	ko.applyBindings(cm);	    
	window.setInterval(cm.updateItem, sysstatusInterval*2);
	cm.updateItem();
    };
    
    

    
    function init(){
	getVoteSettings();
        //uppdatera systemstatus kontinuerligt
        sysstatustmr = window.setInterval(sysstatus, sysstatusInterval);
        sysstatus();
	initGridModel();
    };
    
    return {
        init : init
 

    }     
    
}(); //eo votejs




