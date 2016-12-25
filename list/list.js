// -*- coding: utf-8 -*-
"use strict";

$(document).ready(function(event) {
    beer_db.init();
});

var beer_db = function()
{
    // These are read from the server using ajax
    var competition_id = null;
    var classes = null;
    var beers = null;

    // This is stored in localStorage. user_beers and user_votes are
    // just references to user_data[competition_id].beers and
    // user_data[competition_id].votes, respectively.
    var user_data = null;
    var user_beers = null;
    var user_votes = null;

    var current_popup_item_id = null;

    var vote_code = null;
    
    function init()
    {
	var user_data_string = localStorage.getItem("user_data");
	if (user_data_string != null)
	{
	    user_data = JSON.parse(user_data_string);
	}
	else
	{
	    user_data = {};
	}

	$.ajax({
	    type: 'GET',
	    cache: 'false',
	    url: 'ajax.php',
	    dataType: 'json',
	    data: {},
	    success: function(response) {
		competition_id = response.competition_id;
		classes = response.classes;
		beers = response.beers;

		if (!(competition_id in user_data)) {
		    user_data[competition_id] = { beers: {}, votes: {} };
		    $.each(classes, function(i, vote_class) {
			user_data[competition_id].votes[vote_class.id] = {};
		    });
		}
		user_beers = user_data[competition_id].beers;
		user_votes = user_data[competition_id].votes;

		initialize_html();

		// Now that the competition information is known we can handle
		// vote code changes.
		$('#vote-code').on('keyup', function(e) {
		    var code = $(this).val();
		    if (code.length > 6)
		    {
			code = code.substr(0, 6);
			$(this).val(code);
		    }
		    var code = $(this).val();
		    update_vote_code(code);
		    return false;
		});
	    },
	    error: function(xhr, status, thrown) {
		alert("error: " + status + xhr + thrown);
	    }
	});
    }
    
    function initialize_html()
    {
	// Add items to the nav bar class selection dropdown.
	var class_dropdown = $('ul #class-dropdown');
	$.each(classes, function(i, vote_class) {
	    class_dropdown.append('<li class="dropdown-item"><a data-toggle="tab" href="#page-' + vote_class.id
				  + '" id="menu-item-' + vote_class.id + '">' + vote_class.name + '</a></li>');
	});

	/*
	// Register events on the class selections to update the button text.
	$("#class-dropdown li a").click(function() {
	    $('#class-dropdown-button').html($(this).text());
	});

	// Clear current class name on class selection button when moving to the voting page
	$("#vote-menu-button").click(function() {
	    $('#class-dropdown-button').html("Ölklasser");
	});
	*/

	// Default to the first class (this only sets the pill text -- by default the first tab is selected.
	$('#menu-item-' + classes[0].id).trigger('click');

	$('#menu-item-sort-by-entry-id').click(function() {
	    var current_tab = get_current_tab_hash();
	    fill_beer_lists(compare_beers_by_entry_id, current_tab);
	});

	$('#menu-item-sort-by-rating').click(function() {
	    var current_tab = get_current_tab_hash();
	    fill_beer_lists(compare_beers_by_rating, current_tab);
	});

	$("#popup").on('hidden.bs.modal', function(event) {
	    var comment = $("#popup-comment").val();
	    var rating = $("input[type='radio'][name='popup-rating']:checked").val()
	    var medal = $("input[type='radio'][name='popup-medal']:checked").val();

	    set_medal(current_popup_item_id, medal);
	    
	    user_beers[current_popup_item_id] = {
		rating: rating,
		comment: comment
	    };
	    localStorage.setItem('user_data', JSON.stringify(user_data));

	    update_rating_in_beer_list(current_popup_item_id, rating);
	});
	
	fill_beer_lists(compare_beers_by_entry_id, '#page-' + classes[0].id);

	// Workaround for tabs not being inactivated coorectly.
	// Remove when bootstrap fixes this (hopefully in 4alpha6).
	$('.nav-item').on('shown.bs.tab', 'a', function (e) {
	    if (e.relatedTarget) {
		$(e.relatedTarget).removeClass('active');
	    }
	})
	
	fill_vote_form();
    }

    function compare_beers_by_entry_id(a, b)
    {
	return a > b ? 1 : a == b ? 0 : -1;
    }

    function compare_beers_by_rating(a, b)
    {
	var a_rating = 0;
	if (a in user_beers)
	{
	    a_rating = user_beers[a].rating;
	}
	var b_rating = 0;
	if (b in user_beers)
	{
	    b_rating = user_beers[b].rating;
	}
	if (a_rating != b_rating)
	{
	    return b_rating - a_rating;
	}
	return a > b ? 1 : a == b ? 0 : -1;
    }
    
    function fill_beer_lists(compare_function, active_tab_hash)
    {
	// Order beers by sort order
	var sorted_beers = [];
	$.each(beers, function(i, beer) {
	    sorted_beers.push(i);
	});

	sorted_beers.sort(compare_function);
	
	// Sort beers into classes.
	var items = {};
	$.each(sorted_beers, function(i, entry_id) {
	    var beer = beers[entry_id];
	    var class_id = beer['class'];

	    var rating = 0;
	    if (entry_id in user_beers) {
		rating = user_beers[entry_id].rating;
	    }

	    items[class_id] = items[class_id] || [];
	    items[class_id].push(
		'<a class="list-group-item list-group-item-action" id="' + entry_id + '" href="#" data-toggle="modal" data-target="#popup">'
		    + '<span class="float-xs-right" id="rating-display-' + entry_id + '">'
		    + get_rating_string(rating)
		    + '</span>'
		    + '<span class="float-xs-right" id="medal-display-' + entry_id + '" style="padding-right: 10px;"></span>'
		    + '<span class="beer-number">' + beer.beerCounter + '</span>. '
		    + '<span class="beer-name">' + beer.BeerName + '</span><br>'
		    + '<span class="beer-style">' + beer.styleName + ' (' + beer.styleId + ')</span>'
		    + '</a>');
	});

	// For each class, remove any previous tab-page, create it, and fill it with its list of beers.
	var pages = [];
	$.each(classes, function(i, vote_class) {
	    $('#page-' + vote_class.id).remove();
	    
	    pages.push('<div id="page-' + vote_class.id + '" class="tab-pane ' 
		       + ('#page-' + vote_class.id == active_tab_hash ? ' active' : '') + '">');

	    pages.push('<h1 class="display-4">' + vote_class.name + '</h1>');
	    
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
	$.each(classes, function(i, vote_class) {
	    $("#beerlist-" + vote_class.id + " a").on("click", function (e) {
		var entry_id = $(this).attr("id");
		popup_beer(entry_id, e);
	    });
	});

	// Maybe display a medal for some items (do not call if 0 -- we just created the
	// list so there are no medals shown by default.
	$.each(sorted_beers, function(i, entry_id) {
	    var medal = 0;
	    if (entry_id in user_beers) {
		medal = get_medal(entry_id);
		update_medal_in_beer_list(entry_id, medal);
	    }
	});
    }

    function fill_vote_form()
    {
	var items = [];
	
	$.each(classes, function(i, vote_class) {
	    items.push('<div class="form-group row">');
	    items.push('  <div class="col-xs-3"><label>' + vote_class.name + '</label></div>');
	    for (var j = 1; j <= 3; j++)
	    {
		items.push('<div class="col-xs-3"><input type="text" class="form-control" id="vote-form-'
			   + vote_class.id + "-" + j + '" value="');
		if (user_votes[vote_class.id][j])
		{
		    items.push(beers[user_votes[vote_class.id][j]].beerCounter);
		}
		items.push('"></div>');
	    }
	    items.push('</div>');
	});

	$('#vote-form-rows').html(items.join(''));
    }

    function popup_beer(item_id, e)
    {
	var beer = beers[item_id];
	$("#popup-header").html(beer.beerCounter + ". " + beer.BeerName);
	var brewers = beer.brewer;
	if (beer.coBrewers != '') {
	    brewers += ', ' + beer.coBrewers.replace(/&lt;[^&]*&gt;/g, ''); 
	}
	$("#popup-brewer").html(brewers);
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

	$('input[name="popup-rating"][value="' + rating + '"').prop('checked', true);
	
	current_popup_item_id = item_id;
    }
    
    function update_rating_in_beer_list(entry_id, rating)
    {
	var rating_span = $('#rating-display-' + entry_id);
	rating_span.html(get_rating_string(rating));
    }

    function get_rating_string(rating)
    {
	return '<span class="gold">' + '&#9733;'.repeat(rating) + '</span>'
	    + '<span class="grey">' + '&#9734;'.repeat(5 - rating) + '</span>';
	// return '<span class="fa fa-star fa-fw gold"></span>'.repeat(rating)
	//    + '<span class="fa fa-star fa-fw grey"></span>'.repeat(5 - rating);
    }

    function update_medal_in_beer_list(entry_id, medal)
    {
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

    function get_medal(entry_id)
    {
	var class_id = beers[entry_id].class;

	var votes = user_votes[class_id];
	for (var i = 1; i <= 3; i++) {
	    if (i in votes && votes[i] == entry_id) {
		return i;
	    }
	}
	return 0;
    }

    function set_medal(entry_id, medal)
    {
	var class_id = beers[entry_id].class;

	$.each(user_votes[class_id], function(i, beer) {
	    if (i == medal && beer != entry_id
		|| i != medal && beer == entry_id) {
		delete user_votes[class_id][i];
		update_medal_in_beer_list(beer, 0);
		$('#vote-form-' + class_id + '-' + i).val('');
	    }
	});
	
	if (medal == 1 || medal == 2 || medal == 3) {
	    user_votes[class_id][medal] = entry_id;
	    update_medal_in_beer_list(entry_id, medal);
	    $('#vote-form-' + class_id + '-' + medal).val(beers[entry_id].beerCounter);
	}
    }

    // Get the current tab hash, if it is one of the class tabs.
    // Otherwise, return the empty string.
    function get_current_tab_hash()
    {
	var as = $("ul#class-dropdown a.active");
	if (as.length == 1)
	{
	    return as[0].hash;
	}
	else
	{
	    return '';
	}
    }

    function update_vote_code(code)
    {
	vote_code = code;
	
	var input_field = $('#vote-code');
	var form_group = input_field.closest('.form-group');
	input_field.removeClass('form-control-success');
	input_field.removeClass('form-control-warning');
	input_field.removeClass('form-control-danger');
	form_group.removeClass('has-success');
	form_group.removeClass('has-warning');
	form_group.removeClass('has-danger');
	if (code.length == 6)
	{
            read_votes();
	}
	else if (code.length != 0)
	{
	    form_group.addClass('has-warning');
	    input_field.addClass('form-control-warning');
	}
    }

    function read_votes()
    {
        $.ajax({  
            type: "POST",
            url: "/vote/ajax/vote.php",  
	    contentType: 'application/json',
            dataType: 'json',
            cache : false,
            data: JSON.stringify({
                vote_code: vote_code
            }),

            success: function(response) {
		var input_field = $('#vote-code');
		var form_group = input_field.closest('.form-group');
                if ("vote_code" in response)
		{
		    vote_code = response.vote_code; // uppercased etc
                    input_field.val(vote_code);
		    form_group.addClass('has-success');
		    input_field.addClass('form-control-success');
                    //if (supportsHtml5Storage())
		    //{
                    //    localStorage['vote_code'] = response.vote_code;
		    //}
                }
		else
		{
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
            error: function(xhr, textStatus, errorThrown)
	    {
		alert("error: " + textStatus + ", responseText: " + xhr.responseText); 
            }  
        });
    }
    
    return {
	init: init
    }
}();
