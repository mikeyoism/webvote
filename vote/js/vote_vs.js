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
$(document).on('pageinit', function(event) {

    var sysbar = $('#sysbar');
    sysbar.hide();
    votejs.init();

});


//model for client vote logic
votejs = function() {
    //we check some rules client-side, but they're also checked server-side (so don't bother)
    //and we retrieve sys status (is competition open etc) using a timer
    var DEBUGMODE = false;

    var REQUEST_SYSSTATUS = false;
    var sysstatusInterval = 9999; //todo uppercase
    var sysstatustmr = null;
    var VOTES_PER_CAT = 0;
    var VOTE_WEIGHT_AND_LABELS = null;


    //to store & retrieve the voting categories for this competition.
    var sys_cat = [];

    function getVoteSettings() {

	$.ajax({
	    type: "POST",
	    url: "./php/jssettings.php",
	    dataType: 'json',
	    cache: false,
	    async: false,
	    data: {
		operation: 'getjssettings',
		source: 'index.php',
		subset: ''
	    },

	    success: function(response) {
		if (response.msgtype == "ok") {
		    DEBUGMODE = response.CONST_SYS_JS_DEBUG;
		    REQUEST_SYSSTATUS = response.REQUEST_SYSSTATUS;
		    sysstatusInterval = response.SETTING_SYSSTATUS_INTERVAL;
		    VOTES_PER_CAT = response.CONST_SETTING_VOTES_PER_CATEGORY;
		    VOTE_WEIGHT_AND_LABELS = response.CONST_SETTING_VOTE_WEIGHT;
		    if (DEBUGMODE) console.log(response);

		    sys_cat = response.CONST_SETTING_CATEGORIES_SYS;
		    var competition_name = response.SETTING_COMPETITION_NAME;
		    $("#competition_header").text(competition_name);
		} else {

		    printInfobar('#statusdiv', 'warning', 'serverfel 1-1');
		}

	    },
	    error: function(xhr, status, thrown) {
		printInfobar('#statusdiv', 'warning', 'serverfel 1-2');
	    }
	});
    };



    function sysstatus(args) {
	if (REQUEST_SYSSTATUS == true) {
	    $.ajax({
		type: "POST",
		url: "./php/vote_ajax.php",
		dataType: 'json',
		cache: false,
		data: {
		    operation: 'sysstatus',
		    source: 'vs.php',
		    my_interval: sysstatusInterval
		},

		success: function(response) {
		    if (DEBUGMODE) console.log(response);
		    if (response.msgtype == "interval" && sysstatustmr != null) {

			clearInterval(sysstatustmr);
			sysstatusInterval = response.interval;
			sysstatustmr = window.setInterval(sysstatus, response.interval)

		    } else if (response.msgtype == "stop") {
			$('#sysbar').hide();
			REQUEST_SYSSTATUS = false;
		    } else if (response.usrmsg.length > 0) {
			printInfobar('#sysbar', response.msgtype, response.usrmsg);

		    } else
			$('#sysbar').hide();
		},
		error: function(xhr, status, thrown) {
		    //det uppstår fel här ibland, runtomkring när serverparam uppdateras...så unvik bry dig
		    //if (DEBUGMODE)  alert("serverfel, sysstatus: " + status + xhr + thrown); 
		}
	    });
	}
    }

    //ex = [
    //        { name: "Well-Travelled Kitten", sales: 352, price: 75.95 },
    //        { name: "Speedy Coyote", sales: 89, price: 190.00 },
    //        { name: "Furious Lizard", sales: 152, price: 25.00 },
    //        { name: "Indifferent Monkey", sales: 1, price: 99.95 },
    //        { name: "Brooding Dragon", sales: 0, price: 6350 },
    //        { name: "Ingenious Tadpole", sales: 39450, price: 0.35 },
    //        { name: "Optimistic Snail", sales: 420, price: 1.50 }
    //    ];

    var PagedGridModel = function(category) {
	var self = this;
	self.items = ko.observableArray([]);
	self.weighted = false;
	self.update = function() {
	    $.ajax({
		type: "POST",
		url: "./php/admin_ajax.php",
		dataType: 'json',
		cache: false,
		async: false, // forces synchronous call
		data: {
		    operation: 'getvotes', //getvotes etc
		    category: category,
		    source: 'vs.php',
		},

		success: function(response) {
		    if (DEBUGMODE) console.log(response);
		    self.items(response); //obs, tänk på ej tilldela(=), skriver över observable
		    if (self.weighted)
			self.sortByScore();
		}
	    });

	}
	var gridLayout = {
	    data: self.items,
	    columns: [{
		    headerText: "Öl:#",
		    rowText: "beer"
		}, {
		    headerText: "Antal röster",
		    rowText: "votes"
		}, {
		    headerText: "Antal unika röstare",
		    rowText: "unique_voters"
		},

	    ],
	    pageSize: 50
	};
	self.init = function() {
	    if (getObjectSize(VOTE_WEIGHT_AND_LABELS) > 0) {
		self.weighted = true;
		var weightColumns = [{
			headerText: "Öl:#",
			rowText: "beer"
		    }, {
			headerText: "Poäng",
			rowText: "score"
		    }
		];
		var cnt = 1;
		$.each(VOTE_WEIGHT_AND_LABELS, function(key, value) {
		    weightColumns.push({
			headerText: key,
			rowText: "votes_" + cnt
		    }) //voteS
		    cnt++;
		});
		gridLayout["columns"] = weightColumns; //replace default (non-weighted voting)

	    }


	    self.gridViewModel = new ko.simpleGrid.viewModel(gridLayout);
	}
	self.sortByName = function() {
	    self.items.sort(function(a, b) {
		return a.beer < b.beer ? -1 : 1;
	    });
	};
	function srt(a, b) {
	    if (a == b) 
		return 0;
	    return a < b ? 1 : -1;
	}
	self.sortByScore = function() {
	    self.items.sort(function (a, b) {
		if (a.score < b.score) {
		    return 1;
		} else if (a.score == b.score) {
		    //then sort by weighted votes (votes_1 to votes_xx), asmmuming votes_1 are the most noble.
		    var pos = 1;
		    var ret = 0;
		    do{
			ret = srt(a['votes_'+pos], b['votes_'+pos]);
			pos++;
		    } while (pos <= VOTES_PER_CAT && ret == 0)
		    return ret;
			
		} else //>
		{
		   return -1;
		}
	    });
	};


	self.jumpToFirstPage = function() {
	    self.gridViewModel.currentPageIndex(0);
	};


    };

    var grids = [];

    function initGridModel() {

	//skapa en PagedGridModel för varje category
	for (i = 0; i < sys_cat.length; i++) {
	    var gridVM = new PagedGridModel(sys_cat[i]);
	    gridVM.init();
	    window.setInterval(gridVM.update, sysstatusInterval * 2);
	    gridVM.update();
	    var elem = $("vm_" + sys_cat[i]);
	    if (elem != null) {
		ko.applyBindings(gridVM, document.getElementById("vm_" + sys_cat[i]));
		grids.push(gridVM);
	    }
	}
    };


    function init() {
	getVoteSettings();
	//uppdatera systemstatus kontinuerligt
	sysstatustmr = window.setInterval(sysstatus, sysstatusInterval);
	sysstatus();
	initGridModel();
    };

    return {
	init: init


    }

}(); //eo votejs
