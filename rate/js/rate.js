// -*- coding: utf-8 -*-
"use strict";

$(function (event) {
	beer_db.init();
});

var beer_db = function () {
	// These are read from the server using ajax
	var competition_id = null;
	var enable_voting;
	var classes = null;
	var beers = null;

	// This is stored in localStorage, and poulated from backend
	// just references to user_data.beers and user_data.votes, respectively.
	var user_data = null;
	//var user_beers = null; //todo remove, replaced with user_data.ratings
	var user_votes = null; //just reference to user_data.votes


	var votes_dirty = false;
	var vote_code_ok = false;

	var current_popup_item_id = null;


	var vote_status_timer = null;
	var DEBUGMODE = false;
	var VOTE_STATUS_INTERVAL = 10000;
	var VOTE_CODE_LEN = 6;
	//url parameters competition id and beer id
	var cid = null;
	var bid = null;
	var activeTab = null; //current active tab of the class dropdown
	var last_compare_function = null;

	function getVoteSettings() {

		return $.ajax({
			type: "POST",
			url: "../vote/ajax/jssettings.php",
			dataType: 'json',
			cache: false,
			async: true,
			data: {
				operation: 'getjssettings',
				source: 'index.php',
				subset: ''
			},
			success: function (response) {
				if (response.msgtype == "ok") {
					DEBUGMODE = response.CONST_SYS_JS_DEBUG;
					VOTE_STATUS_INTERVAL = response.SETTING_SYSSTATUS_INTERVAL;
					VOTE_CODE_LEN = response.CONST_SETTING_VOTE_CODE_LENGTH;

					if (DEBUGMODE) console.log(response);
				}
				else {

					printInfobar('#statusdiv', 'warning', 'serverfel 1-1');
				}

			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
			}
		});
	};

	function get_competition_data() {
		return $.ajax({
			type: 'GET',
			cache: 'false',
			url: 'php/ajax.php',
			dataType: 'json',
			data: {},
			success: function (response) {
				competition_id = response.competition_id;
				enable_voting = response.enable_voting;
				classes = response.classes;
				beers = response.beers;
				if (DEBUGMODE) { console.log("@competition_data"); console.log(response); }

			},
			error: function (xhr, textStatus, errorThrown) {
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
			}
		});
	}

	function init() {
		getVoteSettings().done(function () {
			get_competition_data().done(function () {

				if (DEBUGMODE) { console.log('init done, comptetition_id: ' + competition_id + ', classes: '); console.log(classes); }



				//cached data, or new session
				var user_data_string = localStorage.getItem("user_data_" + competition_id);
				if (user_data_string != null) {
					user_data = JSON.parse(user_data_string);
				}
				else {
					user_data = { beers: {}, vote_code: '', votes: {}, ratings: {} };
					$.each(classes, function (i, vote_class) {
						user_data.votes[vote_class.id] = {};
					});
					$.each(classes, function (i, vote_class) {
						user_data.ratings[vote_class.id] = [];
					});

				}
				if (user_data.last_compare_function_name != null) {
					//name of function is stored, set last_compare_function
					switch (user_data.last_compare_function_name) {
						case 'compare_beers_by_entry_code':
							last_compare_function = compare_beers_by_entry_code; 
							break;
						case 'compare_beers_by_rating':
							last_compare_function = compare_beers_by_rating;
							break;
						case 'compare_beers_by_style':
							last_compare_function = compare_beers_by_style;
							break;
					}

				}
				//user_beers = user_data.beers;
				user_votes = user_data.votes;

				var startupClass = 0;
				//if url-parmeters for bid and cid are set (qr-code url's), we should open the popup for that beer
				bid = UrlParameters('bid'); //beer id aka entry_id
				var comp_id = UrlParameters('cid'); //competition id

				//if comp_id from url is correct, set cid for later use (otherwise ignore it)
				if (bid && comp_id && comp_id == competition_id) {
					cid = comp_id;
					//find class_id for the bid (entry_id)
					$.each(beers, function (i, beer) {
						if (beer.entry_code == bid) {
							startupClass = beer.class;
							return false;
						}
					});

				}
				else
					cid = bid = null;


				if (DEBUGMODE) { console.log('@user_ratings for votecode=' + user_data.vote_code); console.log(user_data.ratings); }
				update_vote_code(user_data.vote_code);
				initialize_html(startupClass);


				// Now that the competition information is known we can handle
				// vote code changes.
				$('#vote-code').on('keyup', function (e) {
					var code = $(this).val();
					if (code.length > VOTE_CODE_LEN) {
						code = code.substr(0, VOTE_CODE_LEN);
						$(this).val(code);
					}
					var code = $(this).val();
					update_vote_code(code);
					return false;
				});

				if (enable_voting) {
					// Wait before we have the competition id here too
					vote_status_timer = window.setInterval(get_competition_status,
						VOTE_STATUS_INTERVAL);
					get_competition_status();
				}


				//open welcome-popup if no vote code is set
				if (user_data.vote_code.length != VOTE_CODE_LEN) {
					$('#welcome-popup').modal('show');
				}
				else if (bid != null) { //open beer-popup if url parameters are set
					popup_beer_by_id(bid);
					bid = null; //once opened, clear the bid
				}


			});
		});

	}


	function initialize_html(startupClass = 0) {

		var startupClassIndex = 0;
		// Add items to the nav bar class selection dropdown.
		var class_dropdown = $('ul #class-dropdown');
		$.each(classes, function (i, vote_class) {
			class_dropdown.append('<li class="dropdown-item"><a data-toggle="tab" href="#page-' + vote_class.id
				+ '" id="menu-item-' + vote_class.id + '">' + vote_class.name + '</a></li>');

			if (vote_class.id == startupClass) {
				startupClassIndex = i;
			}
		});


		activeTab = "#page-" + classes[startupClassIndex].id; //default to the first class


		// Add click handlers for the sort menu items.
		$('#menu-item-sort-by-entry-code').on("click", function () {

			if (DEBUGMODE) console.log('current_tab=' + activeTab);
			if (activeTab > "")
				last_compare_function = compare_beers_by_entry_code;
			fill_beer_lists(last_compare_function, activeTab);
		});

		$('#menu-item-sort-by-style').on("click", function () {

			if (DEBUGMODE) console.log('current_tab=' + activeTab);
			if (activeTab > "")
				last_compare_function = compare_beers_by_style;
			fill_beer_lists(last_compare_function, activeTab);
		});

		$('#menu-item-sort-by-rating').on("click", function () {

			if (DEBUGMODE) console.log('current_tab=' + activeTab);
			if (activeTab > "")
				last_compare_function = compare_beers_by_rating;
			fill_beer_lists(last_compare_function, activeTab);
		});



		// on tab shown, set the activeTab variable to the new active tab
		$('.nav-item').on('shown.bs.tab', 'a', function (e) {
			if (e.target) {
				activeTab = e.target.hash; //$(e.target).attr('href');
				$(e.target).removeClass('active'); //remove active class from the tab, so that it can be set again
			}
		})

		// Default to the first class this triggers shown.bs.tab
		$('#menu-item-' + classes[startupClassIndex].id).trigger('click');

		//welcome-popup close event
		$("#welcome-popup").on('hidden.bs.modal', function (event) {
			// if (user_data.vote_code.length != VOTE_CODE_LEN) {
			// 	$('#welcome-popup').modal('show');
			// }
			if (bid != null) { //open beer-popup if url parameters are set
				popup_beer_by_id(bid);
				bid = null; //once opened, clear the bid
			}
		});
		//on popup-rating click on a star, also set drankcheck if rating >= 1
		$("input[type='radio'][name='popup-rating']").on("click", function (event) {
			if ($("input[type='radio'][name='popup-rating']:checked").val() >= 1) {
				//if not already drank
				if ($("#popup-drank-rot").hasClass('down') === true) {
					$("#popup-drank-rot").removeClass('down');
					$("input[type='checkbox'][name='popup-drankcheck']").prop("checked", true);

				}
			}
		});


		//popup close event
		$("#beer-popup").on('hidden.bs.modal', function (event) {
			if (user_data.vote_code.length == VOTE_CODE_LEN) {
				var comment = $("#popup-comment").val();
				var ratingVal = $("input[type='radio'][name='popup-rating']:checked").val()
				var medal = $("input[type='radio'][name='popup-medal']:checked").val();
				var drank = $("input[type='checkbox'][name='popup-drankcheck']").is(":checked");

				set_medal(current_popup_item_id, medal);

				var class_id = beers[current_popup_item_id].class;
				var beer_entry_id = beers[current_popup_item_id].entry_code;
				//one array for each class
				var user_rating_class = user_data.ratings[class_id];
				if (user_rating_class == undefined) {
					user_rating_class = [];
				}



				var rating = {
					categoryId: class_id,
					beerEntryId: beer_entry_id,
					drankCheck: drank === true ? '1' : '0',
					ratingScore: ratingVal == "" ? null : ratingVal,
					ratingComment: comment === "" ? null : comment,

				};
				var ratingPos = -1;
				//if the beer is already rated, update the rating
				$.each(user_rating_class, function (i, obj) {
					if (obj.beerEntryId == beer_entry_id) {
						ratingPos = i;
						user_rating_class[ratingPos] = rating;
						return false;
					}
				});

				if (ratingPos == -1) {
					user_rating_class.push(rating);
				}

				saveToLocalStorage();
				store_ratings();

				update_rating_in_beer_list(current_popup_item_id, rating.ratingScore);
				update_drank_in_beer_list(current_popup_item_id, rating.drankCheck);
			}
		});

		$("#popup-medal-group").click(function (event) {
			update_no_vote_code_alert();
		});

		$("#popup-drank-rot").on("click", function (event) {
			//rotate the icon 180 degrees and toggle the checkbox

			$("#popup-drank-rot").toggleClass('down');
			$("input[type='checkbox'][name='popup-drankcheck']").is(":checked") ? $("input[type='checkbox'][name='popup-drankcheck']").prop("checked", false) : $("input[type='checkbox'][name='popup-drankcheck']").prop("checked", true);

			if (DEBUGMODE) console.log('drank click, ' + ($("#popup-drank-rot").hasClass('down') == true ? "down" : "up") + " checked=" + $("input[type='checkbox'][name='popup-drankcheck']").is(":checked"));

			event.preventDefault();  //block the regular checkbox event

		});


		$(window).on('hashchange', function (e) {
			if (window.location.hash != '#modal-beer-popup') {
				$('#beer-popup').modal('hide');
			}
		});

		if (enable_voting) {
			fill_vote_form();
		} else {
			$('.voting').addClass('d-none');
		}



		$('#search-beer').on('keyup', function (e) {
			var searchstr = $(this).val();
			if (searchstr.length >= 3) {
				//find the beer(s) with the searchstr
				var searchstr = searchstr.toLowerCase();
				var searchstr = searchstr.replace(/[^a-z0-9]/g, ''); //remove non-alphanumeric characters
				//look in the beers array for the searchstr
				var found = false;
				$.each(beers, function (i, beer) {
					//sökning på namn funger men blir rörigt vid multipla träffar på strängen (första träff öppnas)
					//så stängar av det...
					//var beername = beer.name.toLowerCase();
					//var beername = beername.replace(/[^a-z0-9]/g, ''); //remove non-alphanumeric characters
					if (/*beername.indexOf(searchstr) > -1 ||*/ beer.entry_code.indexOf(searchstr) > -1) {
						popup_beer_by_id(beer.entry_code);
						//change the active tab to the class of the found beer
						$.each(classes, function (i, vote_class) {
							if (vote_class.id == beer.class) {
								$('#menu-item-' + vote_class.id).trigger('click');
								return false;
							}
						});
						found = true;
						return false;
					}
				});


			}
			return false;
		});

	}
	function popup_beer_by_id(beer_id) {

		if (beer_id != null) {
			popup_beer(beer_id, null, true);
			$('#beer-popup').modal('show');
		}
	}


	function update_no_vote_code_alert() {
		if (vote_code_ok) {
			$('#popup-alert-no-vote-code').addClass('d-none');
		} else {
			$('#popup-alert-no-vote-code').removeClass('d-none');
		}
	}

	function saveToLocalStorage() {
		//set user_data.last_compare_function_name
		if (last_compare_function !== null)
			user_data.last_compare_function_name = last_compare_function.name;

		localStorage.setItem('user_data_' + competition_id, JSON.stringify(user_data));

	}

	function compare_beers_by_entry_code(a, b) {
		var a_code = beers[a].entry_code;
		var b_code = beers[b].entry_code;

		return a_code > b_code ? 1 : a_code == b_code ? 0 : -1;
	}
	function compare_beers_by_rating(a, b) {
		/* Function used to determine the order of the elements. 
		It is expected to return a negative value if the first argument is less than the second argument, 
		zero if they're equal, and a positive value otherwise. 
		*/
		var a_rating = 0;
		var a_drank = 0;
		//find a in beers, to get the entry_code
		var a_entry_code = beers[a].entry_code;
		var a_classId = beers[a].class;


		//find a_entry_code in user_data.ratings, if found, set a_rating to the rating
		$.each(user_data.ratings[a_classId], function (i, obj) {
			if (obj.beerEntryId == a_entry_code) {
				if (obj.ratingScore != null)
					a_rating = parseInt(obj.ratingScore);
				a_drank = (obj.drankCheck === "1" || obj.drankCheck === 1 || obj.drankCheck === true) ? 1 : 0;
				return false;
			}
		});
		//find b_entry_code in user_data.ratings, if found, set b_rating to the rating
		var b_rating = 0;
		var b_drank = 0;
		var b_entry_code = beers[b].entry_code;
		var b_classId = beers[b].class;
		$.each(user_data.ratings[b_classId], function (i, obj) {
			if (obj.beerEntryId == b_entry_code) {

				if (obj.ratingScore != null)
					b_rating = parseInt(obj.ratingScore);
				b_drank = (obj.drankCheck === "1" || obj.drankCheck === 1 || obj.drankCheck === true) ? 1 : 0;
				return false;
			}
		});

		if (a_rating != b_rating) {

			return b_rating - a_rating;
		}
		if (a_drank != b_drank) {

			return a_drank > b_drank ? -1 : a_drank == b_drank ? 0 : 1;
		}
		//for unrated beers...
		var ret = compare_beers_by_entry_code(a, b);
		return ret;
	}
	function compare_beers_by_style(a, b) {
		var a_style_parts = beers[a].styleId.match(/([0-9]+)([A-Z]+)/);
		var b_style_parts = beers[b].styleId.match(/([0-9]+)([A-Z]+)/);

		if (a_style_parts[1] != b_style_parts[1]) {
			return a_style_parts[1] - b_style_parts[1];
		}
		if (a_style_parts[2] != b_style_parts[2]) {
			return a_style_parts[2].localeCompare(b_style_parts[2]);
		}
		return compare_beers_by_entry_code(a, b);
	}


	function get_ratings_in_class(class_id) {
		var ratings = user_data.ratings[class_id];
		if (ratings == undefined) {
			ratings = [];
		}
		return ratings;
	}
	function get_rating(class_id, beer_entry_id) {
		var rating = {
			categoryId: class_id,
			beerEntryId: beer_entry_id,
			drankCheck: '0',
			ratingScore: null,
			ratingComment: null,

		};
		$.each(user_data.ratings[class_id], function (i, obj) {
			if (obj.beerEntryId == beer_entry_id) {
				rating = obj;
				return false;
			}
		});
		return rating;
	}


	function fill_beer_lists(compare_function, active_tab_hash) {


		// Order beers by sort order

		var sorted_beers_by_class = [];

		$.each(beers, function (i, beer) {

			var class_id = beer['class'];
			if (sorted_beers_by_class[class_id] == undefined)
				sorted_beers_by_class[class_id] = [];

			sorted_beers_by_class[class_id].push(i);
		});

		//if (DEBUGMODE) {console.log("beers"); console.log(beers)};


		//loop through the classes and sort the beers in each class (note: classes might be in random order, use .id )
		$.each(classes, function (no_use, vote_class) {
			//sort the beers in each class with current compare_function

			sorted_beers_by_class[vote_class.id].sort(compare_function);
			//if (DEBUGMODE) console.log("sorted_beers_by_class[" + vote_class.id + "]"); console.log(sorted_beers_by_class[vote_class.id]);

		});


		// Sort beers into classes.
		var items = {};
		$.each(classes, function (no_use, vote_class) {
			$.each(sorted_beers_by_class[vote_class.id], function (j, entry_id) {


				var beer = beers[entry_id];
				var class_id = beer['class'];

				//var rating = 0;
				// if (entry_id in user_data.ratings) {
				// 	rating = user_data.ratings[entry_id].rating;
				// }
				var rating = get_rating(class_id, beer.entry_code);

				items[class_id] = items[class_id] || [];
				items[class_id].push(
					'<a class="list-group-item list-group-item-action" id="' + entry_id + '" href="#" data-toggle="modal" data-target="#beer-popup">'
					+ '<span class="float-right" id="rating-display-' + entry_id + '">'
					+ get_rating_string(rating.ratingScore == null ? 0 : rating.ratingScore)
					+ '</span>'
					+ '<span class="float-right" id="drank-display-' + entry_id + '" style="padding-right: 10px;">'
					+ get_drank_string(rating.drankCheck)
					+ '</span>'
					+ '<span class="float-right" id="medal-display-' + entry_id + '" style="padding-right: 10px;"></span>'
					+ '<span class="beer-number">' + beer.entry_code + '</span>. '
					+ '<span class="beer-name">' + beer.name + '</span><br>'
					+ '<span class="beer-style">' + beer.styleName + ' (' + beer.styleId + ')</span>'
					+ '</a>');

			});
		});

		// For each class, remove any previous tab-page, create it, and fill it with its list of beers.
		var pages = [];
		$.each(classes, function (no_use, vote_class) {
			$('#page-' + vote_class.id).remove();

			pages.push('<div id="page-' + vote_class.id + '" class="tab-pane '
				+ ('#page-' + vote_class.id == active_tab_hash ? ' active' : '') + '">');

			pages.push('<h1 class="display-4">' + vote_class.name + '</h1>');

			pages.push('<div class="votes-dirty-field d-inline-block alert alert-danger d-none">Det finns osparade rösters.</div>');

			pages.push('<div id="beerlist-' + vote_class.id + '" class="list-group">');
			pages.push(items[vote_class.id].join(''));
			pages.push('</div>');
			pages.push('</div>');
		});

		var html = pages.join('');
		var list = $(".tab-content");
		list.append(html);

		// Also, register a click handler for the links to populate the pop up dialog.
		// Need to do this after the lists are created.
		$.each(classes, function (i, vote_class) {
			$("#beerlist-" + vote_class.id + " a").on("click", function (e) {
				var entry_id = $(this).attr("id");
				popup_beer(entry_id, e);
			});
		});

		// // Maybe display a medal for some items (do not call if 0 -- we just created the
		// // list so there are no medals shown by default.
		// $.each(sorted_beers, function (i, entry_id) {
		// 	var medal = 0;
		// 	if (entry_id in user_data.ratings) {
		// 		medal = get_medal(entry_id);
		// 		update_medal_in_beer_list(entry_id, medal);
		// 	}
		// });
	}

	function fill_vote_form() {
		var items = [];

		$.each(classes, function (i, vote_class) {
			items.push('<div class="form-group row">');
			items.push('  <div class="col-xs-3"><label>' + vote_class.name + '</label></div>');
			for (var j = 1; j <= 3; j++) {
				items.push('<div class="col-xs-3"><input type="text" class="form-control" id="vote-form-'
					+ vote_class.id + "-" + j + '" value="');
				if (user_votes[vote_class.id] != undefined && user_votes[vote_class.id][j]) {
					items.push(beers[user_votes[vote_class.id][j]].entry_code);
				}
				items.push('"></div>');
			}
			items.push('</div>');
		});

		$('#vote-form-rows').html(items.join(''));
	}

	function popup_beer(item_id, e, isentry_code = false) {
		if (isentry_code) {
			var found = false;
			$.each(beers, function (i, beer) {
				if (beer.entry_code == item_id) {
					item_id = i;
					found = true;
					return false;
				}
			});
			//exit if we did not find the beer (by url)
			if (!found) return;


		};
		var beer = beers[item_id];
		$("#popup-header").html(beer.entry_code + ". " + beer.name);
		$("#popup-brewer").html(beer.brewer);
		$("#popup-style").html(beer.styleName + " (" + beer.styleId + ")");
		$("#popup-og").html(beer.OG);
		$("#popup-fg").html(beer.FG);
		$("#popup-alcohol").html(beer.alk);
		$("#popup-ibu").html(beer.IBU);

		var comment = '';
		var rating = '';
		var drank = false;
		//find item_id (entry_code) in user_data.ratings (array of objects with rating, comment, drankcheck, beerEntryId, categoryId
		//if found, set rating, comment and drankcheck
		var class_id = beer.class;
		var beer_entry_id = beer.entry_code;
		var user_rating_class = user_data.ratings[class_id];
		if (user_rating_class != undefined) {
			$.each(user_rating_class, function (i, obj) {
				if (obj.beerEntryId == beer_entry_id) {
					rating = obj.ratingScore;
					comment = obj.ratingComment;
					drank = (obj.drankCheck === "1" || obj.drankCheck === 1 || obj.drankCheck === true) ? true : false;
					return false;
				}
			});
		}




		var medal = get_medal(item_id);

		$("#popup-comment").val(comment);
		$("#popup-medal-" + medal + "-button").button('toggle');

		$('input[name="popup-rating"][value="' + rating + '"]').prop('checked', true);
		//drank check
		$("input[type='checkbox'][name='popup-drankcheck']").prop("checked", drank);
		if (drank === true) {
			//raise glass
			$("#popup-drank-rot").removeClass('down');
		} //reset previous rotation, from previous popup of other beer (if any)
		else if (!$("#popup-drank-rot").hasClass('down')) {

			$("#popup-drank-rot").addClass('down');
		}

		update_no_vote_code_alert();

		current_popup_item_id = item_id;
		window.location.hash = 'modal-beer-popup'; // Used to trap back button
	}

	function update_rating_in_beer_list(entry_id, rating) {
		var rating_span = $('#rating-display-' + entry_id);
		rating_span.html(get_rating_string(rating));
	}

	function get_rating_string(rating) {
		return '<span class="gold">' + '&#9733;'.repeat(rating) + '</span>'
			+ '<span class="grey">' + '&#9734;'.repeat(5 - rating) + '</span>';
		// return '<span class="fa fa-star fa-fw gold"></span>'.repeat(rating)
		//    + '<span class="fa fa-star fa-fw grey"></span>'.repeat(5 - rating);
	}
	function update_drank_in_beer_list(entry_id, drank) {
		var drank_span = $('#drank-display-' + entry_id);
		drank_span.html(get_drank_string(drank));
	}
	function get_drank_string(drank) {
		return (drank === 1 || drank === "1" || drank === "true") ? '<span class="fas fa-wine-glass" style="color: #FFD43B;"></span>' : '';
	}


	function update_medal_in_beer_list(entry_id, medal) {
		var medal_span = $('#medal-display-' + entry_id);
		if (medal == 0) {
			medal_span.html("");
		} else {
			if (medal == 1) {
				var value_str = 'Guld';
				var color = 'gold';
			} else if (medal == 2) {
				var value_str = 'Silver';
				var color = 'silver';
			} else if (medal == 3) {
				var value_str = 'Brons';
				var color = '#cd7f32';
			} else {
				alert('error medal value=' + medal);
			}
			medal_span.html('<span class="badge" style="background-color: ' + color + ';">'
				+ value_str + "</label>");
		}
	}

	function get_medal(entry_id) {
		var class_id = beers[entry_id].class;

		var votes = user_votes[class_id];
		if (votes != undefined)
			for (var i = 1; i <= 3; i++) {
				if (i in votes && votes[i] == entry_id) {
					return i;
				}
			}
		return 0;
	}

	function set_medal(entry_id, medal) {
		var class_id = beers[entry_id].class;

		$.each(user_votes[class_id], function (i, beer) {
			if (i == medal && beer != entry_id
				|| i != medal && beer == entry_id) {
				delete user_votes[class_id][i];
				votes_dirty = true;
				update_medal_in_beer_list(beer, 0);
				$('#vote-form-' + class_id + '-' + i).val('');
			}
		});

		if (medal == 1 || medal == 2 || medal == 3) {
			if (!(medal in user_votes[class_id])
				|| user_votes[class_id][medal] != entry_id) {
				user_votes[class_id][medal] = entry_id;
				votes_dirty = true;
				update_medal_in_beer_list(entry_id, medal);
				$('#vote-form-' + class_id + '-' + medal).val(beers[entry_id].entry_code);
			}
		}

		show_votes_dirty();
	}

	function show_votes_dirty() {
		var votes_dirty_field = $('.votes-dirty-field');
		var votes_registered_field = $('.votes-registered-field');
		if (votes_dirty) {
			votes_dirty_field.removeClass('d-none');
			votes_registered_field.addClass('d-none');
		}
		else {
			votes_dirty_field.addClass('d-none');
			votes_registered_field.removeClass('d-none');
		}
	}

	function update_vote_code(code) {
		user_data.vote_code = code;

		var input_field = $('#vote-code');
		//proceed button
		var proceed_button = $('#vote-proceed-button');
		var form_group = input_field.closest('.form-group');
		input_field.removeClass('is-valid');
		input_field.removeClass('is-invalid');
		form_group.removeClass('text-danger');
		form_group.removeClass('text-success');
		proceed_button.prop('disabled', true);

		if (code.length == VOTE_CODE_LEN) {
			//read_votes();
			read_ratings();
		}
		else {
			vote_code_ok = false;
			if (code.length != 0) {
				form_group.addClass('text-danger');
				input_field.addClass('is-invalid');
				proceed_button.prop('disabled', true);
			}
		}
	}

	function read_votes() {
		$.ajax({
			type: "POST",
			url: "../vote/ajax/vote.php",
			contentType: 'application/json',
			dataType: 'json',
			cache: false,
			data: JSON.stringify({
				vote_code: user_data.vote_code,
				competition_id: competition_id
			}),

			success: function (response) {
				if (DEBUGMODE) { console.log("@read_votes"); console.log(response) };
				var input_field = $('#vote-code');
				var form_group = input_field.closest('.form-group');
				if ("vote_code" in response) {
					vote_code_ok = true;
					var vote_code = response.vote_code; // uppercased etc
					input_field.val(vote_code);
					form_group.addClass('text-success');
					input_field.addClass('is-valid');
					user_data.vote_code = vote_code;
					saveToLocalStorage();
				}
				else {
					vote_code_ok = false;
					form_group.addClass('text-danger');
					input_field.addClass('is-invalid');
				}


			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
			}
		});
	}
	//read ratings
	function read_ratings() {
		$.ajax({
			type: "POST",
			url: "../vote/ajax/rate.php",
			contentType: 'application/json',
			dataType: 'json',
			cache: false,

			data: JSON.stringify({
				operation: 'getratings',
				vote_code: user_data.vote_code,
				competition_id: competition_id
			}),
			success: function (response) {
				if (DEBUGMODE) { console.log("@read_ratings"); console.log(response) };
				var input_field = $('#vote-code');
				var form_group = input_field.closest('.form-group');
				var proceed_button = $('#vote-proceed-button');
				if ("vote_code" in response) {
					vote_code_ok = true;
					var vote_code = response.vote_code; // uppercased etc
					input_field.val(vote_code);
					form_group.addClass('text-success');
					input_field.addClass('is-valid');
					proceed_button.prop('disabled', false);
					user_data.vote_code = vote_code;
					user_data.ratings = response.ratings;

					saveToLocalStorage();
					//default
					if (last_compare_function == null)
						last_compare_function = compare_beers_by_entry_code;

					fill_beer_lists(last_compare_function, activeTab);
				}
				else {
					vote_code_ok = false;
					form_group.addClass('text-danger');
					input_field.addClass('is-invalid');
					proceed_button.prop('disabled', true);
				}

			},
			error: function (xhr, textStatus, errorThrown) {
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
			}

		});
	}
	//store ratings
	function store_ratings() {
		$.ajax({
			type: "POST",
			url: "../vote/ajax/rate.php",
			contentType: 'application/json',
			dataType: 'json',
			cache: false,
			data: JSON.stringify({
				operation: 'setratings',
				vote_code: user_data.vote_code,
				competition_id: competition_id,
				ratings: user_data.ratings
			}),
			success: function (response) {
				//replicate the ratings to localstorage
				saveToLocalStorage();

				if (DEBUGMODE) { console.log("@pre_store_ratings"); console.log(user_data.ratings) };
				if (DEBUGMODE) { console.log("@store_ratings"); console.log(response) };
			},
			error: function (xhr, textStatus, errorThrown) {
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				if (DEBUGMODE) { console.log("@pre_store_ratings"); console.log(user_data.ratings) };
			}
		});
	}


	function get_competition_status(args) {
		$.ajax({
			type: "POST",
			url: "../vote/ajax/status.php",
			contentType: 'application/json',
			dataType: 'json',
			cache: false,
			data: JSON.stringify({
				competition_id: competition_id
			}),
			success: function (response) {
				//if (DEBUGMODE) console.log(response);
				if (response.competition_id != competition_id) {
					alert('competition id mismatch');
					if (DEBUGMODE) console.log('competition id mismatch');
					return;
				}

				if (response.update_interval != VOTE_STATUS_INTERVAL) {
					clearInterval(vote_status_timer);
					VOTE_STATUS_INTERVAL = response.update_interval;
					vote_status_timer = window.setInterval(get_competition_status,
						VOTE_STATUS_INTERVAL);
					if (DEBUGMODE) console.log('vote status interval updated to ' + VOTE_STATUS_INTERVAL);
				}

				var style_class = 'rounded d-inline-block p-1 mb-1';
				if (response.competition_open) {
					if (response.competition_seconds_to_close < 60) {
						style_class += ' bg-danger text-white'
					} else if (response.competition_seconds_to_close < 600) {
						style_class += ' bg-warning text-white'
					} else {
						style_class += ' bg-faded'
					}
					var open_closed_text = 'Röstningen stänger om '
						+ secondsToString(response.competition_seconds_to_close) + '.';
				} else {
					if (response.competition_seconds_to_open < 0) {
						style_class += ' bg-danger text-white';
						var open_closed_text = 'Röstningen har stängt.';
					} else {
						style_class += ' bg-faded';
						var open_closed_text = 'Röstningen öppnar om '
							+ secondsToString(response.competition_seconds_to_open) + '.';
					}
				}
				$('#vote-competition-status').html(
					'<div class="' + style_class + '">'
					+ open_closed_text + '</div>');
			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
			}
		});
	}

	var UrlParameters = function getUrlParameter(sParam) {
		var sPageURL = window.location.search.substring(1),
			sURLVariables = sPageURL.split('&'),
			sParameterName,
			i;

		for (i = 0; i < sURLVariables.length; i++) {
			sParameterName = sURLVariables[i].split('=');

			if (sParameterName[0] === sParam) {
				return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
			}
		}
		return false;
	};

	function secondsToString(s) {
		var hours = Math.floor(s / 3600);
		var minutes = Math.floor(s / 60) % 60;
		var seconds = s % 60;
		return [hours, minutes, seconds].map(v => v < 10 ? "0" + v : v).join(":")
	}


	return {
		init: init
	}
}();
