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

	// This is stored in localStorage. user_beers and user_votes are
	// just references to user_data.beers and user_data.votes, respectively.
	var user_data = null;
	var user_beers = null;
	var user_votes = null;

	var votes_dirty = false;
	var vote_code_ok = false;

	var current_popup_item_id = null;

	var vote_status_interval = 10000;
	var vote_status_timer = null;

	function init() {
		$.ajax({
			type: 'GET',
			cache: 'false',
			url: 'ajax.php',
			dataType: 'json',
			data: {},
			success: function (response) {
				competition_id = response.competition_id;
				enable_voting = response.enable_voting;
				classes = response.classes;
				beers = response.beers;

				var user_data_string = localStorage.getItem("user_data_" + competition_id);
				if (user_data_string != null) {
					user_data = JSON.parse(user_data_string);
				}
				else {
					user_data = { beers: {}, vote_code: '', votes: {} };
					$.each(classes, function (i, vote_class) {
						user_data.votes[vote_class.id] = {};
					});
				}

				user_beers = user_data.beers;
				user_votes = user_data.votes;

				initialize_html();
				update_vote_code(user_data.vote_code);

				// Now that the competition information is known we can handle
				// vote code changes.
				$('#vote-code').on('keyup', function (e) {
					var code = $(this).val();
					if (code.length > 6) {
						code = code.substr(0, 6);
						$(this).val(code);
					}
					var code = $(this).val();
					update_vote_code(code);
					return false;
				});

				if (enable_voting) {
					// Wait before we have the competition id here too
					vote_status_timer = window.setInterval(get_competition_status,
						vote_status_interval);
					get_competition_status();
				}
			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
			}
		});
	}

	function initialize_html() {
		// Add items to the nav bar class selection dropdown.
		var class_dropdown = $('ul #class-dropdown');
		$.each(classes, function (i, vote_class) {
			class_dropdown.append('<li class="dropdown-item"><a data-toggle="tab" href="#page-' + vote_class.id
				+ '" id="menu-item-' + vote_class.id + '">' + vote_class.name + '</a></li>');
		});

		// Default to the first class (this only sets the pill text -- by default the first tab is selected.
		$('#menu-item-' + classes[0].id).trigger('click');

		$('#menu-item-sort-by-entry-code').click(function () {
			var current_tab = get_current_tab_hash();
			fill_beer_lists(compare_beers_by_entry_code, current_tab);
		});

		$('#menu-item-sort-by-style').click(function () {
			var current_tab = get_current_tab_hash();
			fill_beer_lists(compare_beers_by_style, current_tab);
		});

		$('#menu-item-sort-by-rating').click(function () {
			var current_tab = get_current_tab_hash();
			fill_beer_lists(compare_beers_by_rating, current_tab);
		});

		$("#beer-popup").on('hidden.bs.modal', function (event) {
			var comment = $("#popup-comment").val();
			var rating = $("input[type='radio'][name='popup-rating']:checked").val()
			var medal = $("input[type='radio'][name='popup-medal']:checked").val();

			set_medal(current_popup_item_id, medal);

			user_beers[current_popup_item_id] = {
				rating: rating,
				comment: comment
			};

			saveToLocalStorage();

			update_rating_in_beer_list(current_popup_item_id, rating);
		});

		$("#popup-medal-group").click(function (event) {
			update_no_vote_code_alert();
		});

		fill_beer_lists(compare_beers_by_entry_code, '#page-' + classes[0].id);

		// Workaround for tabs not being inactivated coorectly.
		// Remove when bootstrap fixes this (hopefully in 4alpha6).
		$('.nav-item').on('shown.bs.tab', 'a', function (e) {
			if (e.relatedTarget) {
				$(e.relatedTarget).removeClass('active');
			}
		})

		$(window).on('hashchange', function (e) {
			if (window.location.hash != '#modal-beer-popup') {
				$('#beer-popup').modal('hide');
			}
		});

		if (enable_voting) {
			fill_vote_form();
		} else {
			$('.voting').addClass('hidden-xs-up');
		}
	}

	function update_no_vote_code_alert() {
		if (vote_code_ok) {
			$('#popup-alert-no-vote-code').addClass('hidden-xs-up');
		} else {
			$('#popup-alert-no-vote-code').removeClass('hidden-xs-up');
		}
	}

	function saveToLocalStorage() {
		localStorage.setItem('user_data_' + competition_id, JSON.stringify(user_data));
	}

	function compare_beers_by_entry_code(a, b) {
		var a_code = beers[a].entry_code;
		var b_code = beers[b].entry_code;

		return a_code > b_code ? 1 : a_code == b_code ? 0 : -1;
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

	function compare_beers_by_rating(a, b) {
		var a_rating = 0;
		if (a in user_beers) {
			a_rating = user_beers[a].rating;
		}
		var b_rating = 0;
		if (b in user_beers) {
			b_rating = user_beers[b].rating;
		}
		if (a_rating != b_rating) {
			return b_rating - a_rating;
		}
		return compare_beers_by_entry_code(a, b);
	}

	function fill_beer_lists(compare_function, active_tab_hash) {
		// Order beers by sort order
		var sorted_beers = [];
		$.each(beers, function (i, beer) {
			sorted_beers.push(i);
		});

		sorted_beers.sort(compare_function);

		// Sort beers into classes.
		var items = {};
		$.each(sorted_beers, function (i, entry_id) {
			var beer = beers[entry_id];
			var class_id = beer['class'];

			var rating = 0;
			if (entry_id in user_beers) {
				rating = user_beers[entry_id].rating;
			}

			items[class_id] = items[class_id] || [];
			items[class_id].push(
				'<a class="list-group-item list-group-item-action" id="' + entry_id + '" href="#" data-toggle="modal" data-target="#beer-popup">'
				+ '<span class="float-xs-right" id="rating-display-' + entry_id + '">'
				+ get_rating_string(rating)
				+ '</span>'
				+ '<span class="float-xs-right" id="medal-display-' + entry_id + '" style="padding-right: 10px;"></span>'
				+ '<span class="beer-number">' + beer.entry_code + '</span>. '
				+ '<span class="beer-name">' + beer.name + '</span><br>'
				+ '<span class="beer-style">' + beer.styleName + ' (' + beer.styleId + ')</span>'
				+ '</a>');
		});

		// For each class, remove any previous tab-page, create it, and fill it with its list of beers.
		var pages = [];
		$.each(classes, function (i, vote_class) {
			$('#page-' + vote_class.id).remove();

			pages.push('<div id="page-' + vote_class.id + '" class="tab-pane '
				+ ('#page-' + vote_class.id == active_tab_hash ? ' active' : '') + '">');

			pages.push('<h1 class="display-4">' + vote_class.name + '</h1>');

			pages.push('<div class="votes-dirty-field d-inline-block alert alert-danger hidden-xs-up">Det finns osparade röster.</div>');

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

		// Maybe display a medal for some items (do not call if 0 -- we just created the
		// list so there are no medals shown by default.
		$.each(sorted_beers, function (i, entry_id) {
			var medal = 0;
			if (entry_id in user_beers) {
				medal = get_medal(entry_id);
				update_medal_in_beer_list(entry_id, medal);
			}
		});
	}

	function fill_vote_form() {
		var items = [];

		$.each(classes, function (i, vote_class) {
			items.push('<div class="form-group row">');
			items.push('  <div class="col-xs-3"><label>' + vote_class.name + '</label></div>');
			for (var j = 1; j <= 3; j++) {
				items.push('<div class="col-xs-3"><input type="text" class="form-control" id="vote-form-'
					+ vote_class.id + "-" + j + '" value="');
				if (user_votes[vote_class.id][j]) {
					items.push(beers[user_votes[vote_class.id][j]].entry_code);
				}
				items.push('"></div>');
			}
			items.push('</div>');
		});

		$('#vote-form-rows').html(items.join(''));
	}

	function popup_beer(item_id, e) {
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
		if (item_id in user_beers) {
			comment = user_beers[item_id].comment;
			rating = user_beers[item_id].rating;
		}
		var medal = get_medal(item_id);

		$("#popup-comment").val(comment);
		$("#popup-medal-" + medal + "-button").button('toggle');

		$('input[name="popup-rating"][value="' + rating + '"]').prop('checked', true);

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
			medal_span.html('<span class="tag" style="background-color: ' + color + ';">'
				+ value_str + "</label>");
		}
	}

	function get_medal(entry_id) {
		var class_id = beers[entry_id].class;

		var votes = user_votes[class_id];
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
			votes_dirty_field.removeClass('hidden-xs-up');
			votes_registered_field.addClass('hidden-xs-up');
		}
		else {
			votes_dirty_field.addClass('hidden-xs-up');
			votes_registered_field.removeClass('hidden-xs-up');
		}
	}

	// Get the current tab hash, if it is one of the class tabs.
	// Otherwise, return the empty string.
	function get_current_tab_hash() {
		var as = $("ul#class-dropdown a.active");
		if (as.length == 1) {
			return as[0].hash;
		}
		else {
			return '';
		}
	}

	function update_vote_code(code) {
		user_data.vote_code = code;

		var input_field = $('#vote-code');
		var form_group = input_field.closest('.form-group');
		input_field.removeClass('form-control-success');
		input_field.removeClass('form-control-warning');
		input_field.removeClass('form-control-danger');
		form_group.removeClass('has-success');
		form_group.removeClass('has-warning');
		form_group.removeClass('has-danger');
		if (code.length == 6) {
			read_votes();
		}
		else {
			vote_code_ok = false;
			if (code.length != 0) {
				form_group.addClass('has-warning');
				input_field.addClass('form-control-warning');
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
				var input_field = $('#vote-code');
				var form_group = input_field.closest('.form-group');
				if ("vote_code" in response) {
					vote_code_ok = true;
					var vote_code = response.vote_code; // uppercased etc
					input_field.val(vote_code);
					form_group.addClass('has-success');
					input_field.addClass('form-control-success');
					user_data.vote_code = vote_code;
					saveToLocalStorage();
				}
				else {
					vote_code_ok = false;
					form_group.addClass('has-danger');
					input_field.addClass('form-control-danger');
				}

				/*
						$.each(sys_cat, function(index, value)
				{
							for (i = 1; i <= VOTES_PER_CAT; i++)
					{
								$('#' + value + '_vote' + i).prop("disabled", disableInput); // prevent input on bad vote code
		
					var vote = "votes" in response ? response.votes[value][i] : '';
								$('#' + value + '_vote' + i).val(vote);
							}
						});
				*/
			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				//alert("error: " + textStatus + ", responseText: " + xhr.responseText);
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
				if (response.competition_id != competition_id) {
					alert('competition id mismatch');
					return;
				}

				if (response.interval != vote_status_interval) {
					clearInterval(vote_status_timer);
					vote_status_interval = response.update_interval;
					vote_status_timer = window.setInterval(get_competition_status,
						vote_status_interval)
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
