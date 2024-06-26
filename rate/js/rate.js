// -*- coding: utf-8 -*-
"use strict";




$(function (event) {
	beer_db.init();
});

var beer_db = function () {
	// These are read from the server using ajax
	var competition_id = null;
	var classes = null;
	var beers = null;
	var styles = null; //style guides
	var beersHidden = null; //before competition start

	// This is stored in localStorage, and poulated from backend
	// contins user_data.vote_code, user_data.beers and user_data.ratings etc.
	var user_data = null;

	var vote_code_ok = false;
	var current_popup_item_id = null;


	var vote_status_timer = null;
	var ENABLE_RATING = true;
	var DEBUGMODE = false;
	var VOTE_STATUS_INTERVAL = 10000;
	var VOTE_CODE_LEN = 6;
	var HIDE_BEERS_BEFORE_START = true;
	var competition_name = "";
	var competition_open = false;
	var competition_closes_hhmm = "";
	var competition_seconds_to_close = 0;
	var competition_seconds_to_open = 0;
	var competition_has_closed = false; //only true if competition has closed after first being open
	var competition_allow_comments_and_checkins = false;

	//url parameters competition id and beer id
	var cid = null;
	var bid = null;
	var activeTab = null; //current active tab of the class dropdown
	var last_compare_function = null; //stored to localStorage

	//driver.js offers richer funtionality than bootstrap popovers
	const driver = window.driver.js.driver;
	//var beerPopupDriverObj = null;
	var skipCarouselFront = false; //skip the front-pic carousel once, when the user has seen it before
	var wentTotheWelcomePopup = false; //if the user went to the welcome-popup
	var cssCompetitionTheme = 'theme_default.css'; //empty file default template
	var isTouchScreen = (('ontouchstart' in window) || (navigator.msMaxTouchPoints > 0));


	function getRateSettings() {

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
					ENABLE_RATING = response.ENABLE_RATING;
					HIDE_BEERS_BEFORE_START = response.HIDE_BEERS_BEFORE_START;

					//set cssCompetitionTheme (optional)
					if (response.CSS_COMPETITION_THEME != null && response.CSS_COMPETITION_THEME != "" &&
						response.CSS_COMPETITION_THEME !== cssCompetitionTheme) {
						cssCompetitionTheme = response.CSS_COMPETITION_THEME;
						var cssth = cssCompetitionTheme.toLowerCase();
						if (cssth.slice(-4) != '.css') {
							cssth += '.css';
						}
						$('#css-theme').attr('href', 'css/' + cssth);

					}

					if (DEBUGMODE) console.log(response);
				}
				else {
					setErrorAndSpinner("serverfel 1-1, Kontakta tävlingsledningen.");
					if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				}

			},
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);

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
				classes = response.classes;
				beers = response.beers;
				styles = response.styles;
				beersHidden = response.beersHidden;
				if (DEBUGMODE) { console.log("@competition_data"); console.log(response); }

			},
			error: function (xhr, textStatus, errorThrown) {
				setErrorAndSpinner("serverfel 1-2, Kontakta tävlingsledningen.");
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);

			}
		});
	}
	function init() {
		getRateSettings().done(function () {
			get_competition_data().done(function () {

				if (DEBUGMODE) { console.log('init done, comptetition_id: ' + competition_id + ', classes: '); console.log(classes); }


				init_user_data();



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



				update_vote_code(user_data.vote_code);
				initialize_html(startupClass);


				//start competition status timer
				vote_status_timer = window.setInterval(get_competition_status, VOTE_STATUS_INTERVAL);
				get_competition_status();


				//open welcome-popup if no vote code is set
				if (user_data.vote_code.length != VOTE_CODE_LEN) {
					$('#welcome-popup').modal('show');
				}
				else if (bid != null) { //open beer-popup if url parameters are set
					popup_beer_by_id(bid);
					bid = null; //once opened, clear the bid
				}
				setErrorAndSpinner(""); //clear any previous error message and hide loading spinner


			});
		});

	}
	function saveToLocalStorage() {
		//set user_data.last_compare_function_name
		if (last_compare_function !== null)
			user_data.last_compare_function_name = last_compare_function.name;

		localStorage.setItem('user_data_' + competition_id, JSON.stringify(user_data));

	}
	function init_user_data(reset = false) {
		//cached data, or new session
		var user_data_string = null;
		if (DEBUGMODE) console.log('@init_user_data, reset=' + reset);

		if (reset !== true) {
			user_data_string = localStorage.getItem("user_data_" + competition_id);
		}
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
				default:
					last_compare_function = compare_beers_by_entry_code;
			}

		}
		else {
			last_compare_function = compare_beers_by_entry_code; //default
		}
		//store empty user_data to localstorage, for next reload etc
		if (reset === true) {
			saveToLocalStorage();
		}
		if (DEBUGMODE) { console.log('@user_ratings for votecode=' + user_data.vote_code); console.log(user_data.ratings); }
	}



	function initialize_html(startupClass = 0) {


		//set #vote-code max length
		$('#vote-code').attr('maxlength', VOTE_CODE_LEN);


		// handle vote code changes.
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
		//fortsätt utan kod
		$('#vote-proceed-no-code-button').on('click', function (e) {
			$('#welcome-popup').modal('hide');
			update_vote_code('', true); //reset vote code
			return false;
		});


		//welcome-popup open event
		$("#welcome-popup").on('shown.bs.modal', function (event) {
			//set focus on the vote-code input field
			$('#vote-code').focus();
			//trigger validation of the vote-code input field
			$('#vote-code').trigger('keyup');

			//get image src from the first slide, and set it as background-image for the modal
			var img_src = $('#eventposter-slide img').css('content');

			//ignore first slide with event poster, if not defined in theme css
			if (img_src == "normal" || img_src == "none") {

				$('#carousel-welcome').carousel(1);
				$('#carousel-welcome').carousel('pause');
				//show modal header with competition name instead of the event poster
				$('#welcome-popup').find('.modal-header').removeClass('d-none');
			}

			else {
				$('#eventposter-slide').removeClass('d-none');
				if (!skipCarouselFront) {
					skipCarouselFront = false;

					$('#carousel-welcome').carousel(0);
					$('#carousel-welcome').carousel('cycle')

					//hide the footer
					//if no vote code is set, hide the footer for first slide  (shown on the last slide initially)
					if (user_data.vote_code.length != VOTE_CODE_LEN)
						$('#modal-footer-welcome').hide();
				}
			}
		});

		//welcome-popup close event
		$("#welcome-popup").on('hidden.bs.modal', function (event) {

			const introDriverObj = driver({
				animate: true,
				showProgress: true,
				progressText: 'Intro {{current}} av {{total}}',
				showButtons: true,
				closeBtnText: 'Stäng',
				nextBtnText: 'Nästa',
				prevBtnText: 'Föregående',
				doneBtnText: 'Stäng!',
				steps: [
					{ element: '#beer-class-dropdown-button', popover: { title: 'Välj tävlingsklass', description: 'Välj tävlingsklass i menyn.' } },
					{
						element: 'tab-content', popover: {
							title: 'Aktuell tävlingsklass', description: 'Alla tävlande öl i den valda klassen visas här. Tryck på ett öl för att rösta!',
							side: "right",
							align: 'start'
						}
					},
					{ element: '#sort-dropdown-button', popover: { title: 'Sortera', description: 'Sortera ölen efter tävlingsnummer, stil eller betyg.' } },

					{ element: '#menu-rate-start', popover: { title: 'Aktivera röstkod', description: 'Tryck här för att aktivera eller byta röstkod samt få mer information om tävlingen.' } },
					{ element: '#menu-search-beer', popover: { title: 'Snabbsökning', description: 'Sök på tävlingsnummer för direktvisning av ölet. Exempel: "123" <p>Alternativt går det även att använda mobilens kamera (utanför appen) för att scanna ölets QR-kod.</p>' } }


				]
				,
				onDestroyed: function () {
					//if bid is set (qr-code url), open the beer-popup
					if (bid != null) {
						popup_beer_by_id(bid);
						bid = null; //once opened, clear the bid
					}
				}
			});

			introDriverObj.drive();
			wentTotheWelcomePopup = true;

		});
		//fortsätt med kod (needed with carousel, as the button is outside the form)
		$('#vote-proceed-button').on('click', function (e) {
			$('#welcome-popup').modal('hide');
			return false;
		});
		// $('#carousel-welcome').on('slide.bs.carousel', function (e) {
		// 	$('#modal-footer-welcome').hide();
		// });
		$('#carousel-welcome').on('slid.bs.carousel', function (e) {
			if (e.to == 1) { //last slide
				$('#modal-footer-welcome').show();
				//remove welcomecarousel_img from child (optional)
				$('#welcome-popup').find('.modal-content').removeClass('modal-content-welcomecarousel_img');
				$('#welcome-popup').find('.modal-body').removeClass('modal-body-welcomecarousel_img');
				$('.carousel-control-next').hide();
				$('#carousel-welcome').carousel('pause');
			}
			else //eventposter slide (rpgoramblad)
			{
				//start the carousel, if it's paused
				$('#welcome-popup').find('.modal-content').addClass('modal-content-welcomecarousel_img');
				$('#welcome-popup').find('.modal-body').addClass('modal-body-welcomecarousel_img');
				//hide next/prev buttons
				$('.carousel-control-next').show();

				$('#carousel-welcome').carousel('cycle');

			}

		});
		$('#welcome-popup-showmore').hide();
		//welcome-popup-morehelp arrow down click
		$('#welcome-popup-morehelp').on('click', function (e) {
			$('#welcome-popup-showmore').toggle();


		});





		//on beer-popup guide click
		$('#beer-popup-guide').on('click', function (e) {
			//Driver for beer-popup
			const beerPopupDriverObj = driver({
				animate: true,
				showProgress: true,
				progressText: 'Intro {{current}} av {{total}}',
				showButtons: true,
				closeBtnText: 'Stäng',
				nextBtnText: 'Nästa',
				prevBtnText: 'Föregående',
				doneBtnText: 'Stäng!',
				steps: [
					{ element: '#popup-brewer-data', popover: { title: 'Öldata', description: 'Nyckelvärden bryggaren angivit för ölet. OG (Original Gravity, sv. densitet) är uppmätt sockermängd/vörtstyrka innan jäsning. Densiteten efter jäsning anges som FG (Final Gravity)', side: "bottom", align: 'center' } },
					{ element: '#popup-brewer-data', popover: { title: 'Öldata del 2', description: 'Alkoholhalten mäts eller beräknas utifrån OG/FG och anges i volymprocent (ABV). Ölets beska kommer oftast från alfasyran i humlen och anges i måttenheten IBU (International Bitterness Units) ', side: "bottom", align: 'center' } },
					{ element: '#popup-style', popover: { title: 'Tävlingsklass', description: 'Ölets tävlingsklass. Tryck på texten för att visa stilguide / öltypsdefinition' } },
					{ element: '#popup-drank legend', popover: { title: 'Provsmakat', description: 'Tryck på glaset om du druckit av ölet, för att hålla kolla på provsmakde öl.' } },
					{ element: '#rating-legend', popover: { title: 'Betyg', description: 'Betygsätt ölet med 1 till 5 sjtärnor. Betyget bidrar med poäng i Folkets val.' } },
					{ element: '#popup-comment', popover: { title: 'Kommentar', description: 'Skriv en valfri kommentar, tex positiv feedback om ölet till bryggaren.' } },


				]
			});
			beerPopupDriverObj.drive();

		});


		//welcome-popup-lesshelp click
		$('#welcome-popup-infoheader').on('click', function (e) {

			//rotate the icon 180 degrees
			$("#welcome-popup-lesshelp").toggleClass('down');

			$('#welcome-help-card').toggle();
			//scroll to bottom of the modal, to show "Fortsätt" (for small mopbile screens, like iphone SE)
			$('#welcome-popup').scrollTop($('#welcome-popup')[0].scrollHeight);
		});
		//hide fortätt utan kod button if bid is set (qr-code url), or confusing to user
		if (bid != null) {
			$('#vote-proceed-no-code-button').hide();
		}

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
			if (activeTab > "") {
				last_compare_function = compare_beers_by_entry_code;
				fill_beer_lists(last_compare_function, activeTab);
				saveToLocalStorage();
			}
		});

		$('#menu-item-sort-by-style').on("click", function () {

			if (DEBUGMODE) console.log('current_tab=' + activeTab);
			if (activeTab > "") {
				last_compare_function = compare_beers_by_style;
				fill_beer_lists(last_compare_function, activeTab);
				saveToLocalStorage();
			}
		});

		$('#menu-item-sort-by-rating').on("click", function () {

			if (DEBUGMODE) console.log('current_tab=' + activeTab);
			if (activeTab > "") {
				last_compare_function = compare_beers_by_rating;
				fill_beer_lists(last_compare_function, activeTab);
				saveToLocalStorage();
			}
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

		//close ths popup on enter key
		$('#beer-popup').on('keypress', function (e) {
			if (e.which == 13) {
				$('#beer-popup').modal('hide');
			}
		});
		//popup beer close event
		$("#beer-popup").on('hidden.bs.modal', function (event) {
			$("#popup-style").popover('dispose');
			if (user_data.vote_code.length == VOTE_CODE_LEN) {
				var comment = $("#popup-comment").val();
				var ratingVal = $("input[type='radio'][name='popup-rating']:checked").val()

				var drank = $("input[type='checkbox'][name='popup-drankcheck']").is(":checked");



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

				
				store_ratings().done(function () {
					//find the validated rating from the server, now in user_data.ratings
					//(not updated if competition is closed etc)
					var rating = get_rating(class_id, beer_entry_id);


					update_rating_in_beer_list(current_popup_item_id, rating.ratingScore);
					update_drank_in_beer_list(current_popup_item_id, rating.drankCheck);
				});


			}
		});

		//popup-drank click
		$("#popup-drank-rot").on("click", function (event) {
			//workaround variable as $("input[type='checkbox'][name='popup-drankcheck']").prop("disabled", true) has no effefct
			if (!drank_checking_allowed) {
				event.preventDefault();
				event.stopPropagation();
				return false;
			}
			//rotate the icon 180 degrees and toggle the checkbox
			$("#popup-drank-rot").toggleClass('down');
			$("input[type='checkbox'][name='popup-drankcheck']").is(":checked") ? $("input[type='checkbox'][name='popup-drankcheck']").prop("checked", false) : $("input[type='checkbox'][name='popup-drankcheck']").prop("checked", true);

			if (DEBUGMODE) console.log('drank click, ' + ($("#popup-drank-rot").hasClass('down') == true ? "down" : "up") + " checked=" + $("input[type='checkbox'][name='popup-drankcheck']").is(":checked"));

			event.preventDefault();  //block the regular checkbox event

		});
		//click on no-vote-code alert
		$('#popup-alert-no-vote-code').on('click', function (e) {
			skipCarouselFront = true;
			$('#welcome-popup').modal('show');
			$('#beer-popup').modal('hide');
		});


		$(window).on('hashchange', function (e) {
			if (window.location.hash != '#modal-beer-popup') {
				$('#beer-popup').modal('hide');
			}
		});

		$('#menu-search-beer').on('keyup', function (e) {
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

	} //end of initialize_html


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

	//get rating from user_data.ratings
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
		var beerCount = 0;
		$.each(beers, function (i, beer) {

			var class_id = beer['class'];
			if (sorted_beers_by_class[class_id] == undefined)
				sorted_beers_by_class[class_id] = [];

			sorted_beers_by_class[class_id].push(i);
			beerCount++;
		});
		if (beerCount > 0) {
			//if (DEBUGMODE) {console.log("beers"); console.log(beers)};


			//loop through the classes and sort the beers in each class (note: classes might be in random order, use .id )
			$.each(classes, function (no_use, vote_class) {
				//sort the beers in each class with current compare_function
				
				//sort, provided that the class has any beers (not undefined or null)
				if (sorted_beers_by_class[vote_class.id] != undefined && sorted_beers_by_class[vote_class.id] != null)
					sorted_beers_by_class[vote_class.id].sort(compare_function);
				
				//if (DEBUGMODE) console.log("sorted_beers_by_class[" + vote_class.id + "]"); console.log(sorted_beers_by_class[vote_class.id]);

			});
		}

		// Sort beers into classes.
		var items = {};
		$.each(classes, function (no_use, vote_class) {
			// For each class, create a list of beers.
			//provided that the class has any beers (not undefined or null)
			if (sorted_beers_by_class[vote_class.id] != undefined && sorted_beers_by_class[vote_class.id] != null)
				$.each(sorted_beers_by_class[vote_class.id], function (j, entry_id) {


					var beer = beers[entry_id];
					var class_id = beer['class'];


					var rating = get_rating(class_id, beer.entry_code);

					items[class_id] = items[class_id] || [];
					items[class_id].push(
						'<a class="list-group-item list-group-item-action" id="' + entry_id + '" href="#" data-toggle="modal" data-target="#beer-popup">'
						+ '<span class="float-right" id="rating-display-' + entry_id + '">'
						+ get_rating_string(rating.ratingScore == null ? 0 : rating.ratingScore)
						+ '</span>'
						+ '<span class="float-right" id="drank-display-' + entry_id + '" style="padding-right: 10px;margin-top:5px;">'
						+ get_drank_string(rating.drankCheck)
						+ '</span>'

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

			pages.push('<div class="container-fluid">');
			pages.push('<div class="row">');
			pages.push('<div class="col-8">');
			pages.push('<h1 class="ml-1 mb-0"><span class="beer-class-header" id="beer-class-header">' + vote_class.name + '</span></h1>');
			pages.push('<h6 class="beer-class-description ml-2 mt-0">' + vote_class.description + '</h6>');
			pages.push('</div>');
			pages.push('<div class="col">');
			pages.push('<div id="ornament1"><img src="" alt=""></div>');
			pages.push('</div>');
			pages.push('</div>');


			pages.push('<div class="competition-status alert competition-status-main"></div>');
			pages.push('</div>');
			//add img floating to the right


			pages.push('<div id="beerlist-' + vote_class.id + '" class="list-group ">');
			if (beerCount > 0 && items[vote_class.id] !== undefined && items[vote_class.id] !== null) pages.push(items[vote_class.id].join(''));
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

		get_competition_status();

	}

	//popup full style guide
	function popup_style_guide(main_style, style_substyle) {
		//assign the styles array to the modal
		
		$('#style-guide-popup').find('#style-guide-popup-mainstyle').html(main_style.name + " (" + main_style.number + ")");
		$('#style-guide-popup').find('#style-guide-popup-substyle').html(style_substyle.name + " (" + style_substyle.letter + ")");
		$('#style-guide-popup').find('#style-guide-popup-og-range').html('OG ' + (style_substyle.ogMin || ' ') + ' - ' + (style_substyle.ogMax || ' '));
		$('#style-guide-popup').find('#style-guide-popup-fg-range').html("FG " + (style_substyle.fgMin || ' ') + " - " + (style_substyle.fgMax || ' '));
		$('#style-guide-popup').find('#style-guide-popup-ibu-range').html("Beska " + (style_substyle.ibuMin || ' ') + " - " + (style_substyle.ibuMax || ' ' + " IBU"));
		$('#style-guide-popup').find('#style-guide-popup-abv-range').html("ABV " + (style_substyle.abvMin || ' ') + " - " + (style_substyle.abvMax || ' ') + " %");
		$('#style-guide-popup').find('#style-guide-popup-ebc-range').html("Färg " + (style_substyle.ebcMin || ' ') + " - " + (style_substyle.ebcMax || ' ') + " EBC");
		$('#style-guide-popup').find('#style-guide-popup-summary').html(style_substyle.summary || " ");

		$('#style-guide-popup').find('#style-guide-popup-aroma').html('<strong>Bouquet/arom: </strong>' + (style_substyle.aroma || " "));
		$('#style-guide-popup').find('#style-guide-popup-appearance').html('<strong>Utseende: </strong>' + (style_substyle.appearance || " "));
		$('#style-guide-popup').find('#style-guide-popup-flavor').html('<strong>Smak: </strong>' + (style_substyle.flavor || " "));
		$('#style-guide-popup').find('#style-guide-popup-texture').html('<strong>Munkänsla: </strong>' + (style_substyle.texture || " "));
		$('#style-guide-popup').modal('show');
	}

	function popup_beer_by_id(beer_id) {

		if (beer_id != null) {
			popup_beer(beer_id, null, true);
			$('#beer-popup').modal('show');
		}
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
		$("#popup-style").html(beer.styleName + " (" + beer.styleId + ") <i class=\"far fa-question-circle secondary-color\" style=\"vertical-align: middle;\"></i>");
		$("#popup-og").html(parseInt(beer.OG) / 1000 + 1);
		$("#popup-fg").html(parseInt(beer.FG) / 1000 + 1);
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
					rating = obj.ratingScore === null ? '' : obj.ratingScore;
					comment = obj.ratingComment === null ? '' : obj.ratingComment;
					drank = (obj.drankCheck === "1" || obj.drankCheck === 1 || obj.drankCheck === true) ? true : false;
					return false;
				}
			});
		}




		$("#popup-comment").val(comment);


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


		//find the style info in the styles guide array
		var style_main = null;
		var style_substyle = null;
		var style_subdesc = "";
		//parse StyleId as number and letter
		var style_parts = beer.styleId.match(/([0-9]+)([A-Z]+)/);
		if (style_parts != null) {

			//find the style info in the styles guide array
			$.each(styles, function (i, style) {
				if (style.number == style_parts[1]) {
					style_main = style;
					//substyles
					$.each(style.styles, function (j, substyle) {
						if (substyle.letter == style_parts[2]) {
							style_subdesc = substyle.description;
							style_substyle = substyle;
							return false;
						}
					});
					return false;
				}
			});


		}
		var trigger = 'hover focus' //opitmized for mobile touchscreen!
		if (!isTouchScreen)
			trigger = 'click focus'; //click interferes with closing the popup 

		//popover for style description when hovering over the style
		//note: must be disposed before creating a new one, during close event
		$("#popup-style").popover({
			trigger: trigger,
			placement: 'auto',
			container: 'body',
			boundary: 'viewport', //important for small screens
			html: true,
			title: '<i class=\"far fa-question-circle\"></i> Ölstilsbeskrivning',
			content: function () {
				var txt = "<h6>" + beer.styleName + " (" + beer.styleId + ") ";
				//popup-link to  style-guide-popup
				if (style_main !== null && style_substyle !== null)
					txt += "<a href=\"#\" id=\"popup-style-guide-link\" class=\"btn btn-outline-secondary btn-sm  ml-2\">Full Typdef</a>"; 
				txt += "</h6>";
				if (style_subdesc !== null)
					txt += "<p>" + style_subdesc + "</p>";
				return txt;
			}
		});

		//style guide link click inside popover
		$(document).on('click', '#popup-style-guide-link', function (e) {
			$(this).parents(".popover").popover('hide');
			if (style_main !== null && style_substyle !== null)
				popup_style_guide(style_main, style_substyle);
			e.preventDefault();
		});

		if (wentTotheWelcomePopup) {

			wentTotheWelcomePopup = false;
		}

		window.location.hash = 'modal-beer-popup'; // Used to trap back button

	}


	function update_rating_in_beer_list(entry_id, rating) {
		var rating_span = $('#rating-display-' + entry_id);
		rating_span.html(get_rating_string(rating));
	}

	function get_rating_string(rating) {
		return '<span class="gold">' + '&#9733;'.repeat(rating) + '</span>'
			+ '<span class="grey">' + '&#9734;'.repeat(5 - rating) + '</span>';

	}
	function update_drank_in_beer_list(entry_id, drank) {
		var drank_span = $('#drank-display-' + entry_id);
		drank_span.html(get_drank_string(drank));
	}
	function get_drank_string(drank) {
		return (drank === 1 || drank === "1" || drank === "true") ? '<span class="fas fa-wine-glass" style="color: #FFD43B;"></span>' : '';
	}




	function update_no_vote_code_alert() {
		if (vote_code_ok) {
			$('#popup-alert-no-vote-code').hide();


		} else {
			$('#popup-alert-no-vote-code').show();
		}
		update_rating_allowed();
	}

	//workaround variable for click event on the drank icon
	var drank_checking_allowed = true;
	//enable/disable fieldsets
	function update_rating_allowed() {



		if (!ENABLE_RATING || !competition_open) {
			$('.rating').prop('disabled', true);
		} else {
			$('.rating').prop('disabled', !vote_code_ok);
		}
		//disable all changes if rating is disabled by competition sys-setting
		if (!ENABLE_RATING) {
			//popup-drank child checkbox

			drank_checking_allowed = false;

			$('.rating-comment').prop('disabled', true);
		}
		else {
			//disable drank and comment if competition has not yet opened
			//it's ok to leave comments and drank-checks, after competition/rating has closed (Server-side check)
			if (competition_allow_comments_and_checkins){

				drank_checking_allowed = vote_code_ok;
				$('.rating-comment').prop('disabled', !vote_code_ok);
			}
			else  {

				drank_checking_allowed = false;
				$('.rating-comment').prop('disabled', true);
			}
			
		}
		//if (DEBUGMODE) { console.log('@update_rating_allowed, vote_code_ok=' + vote_code_ok + ', competition_open=' + competition_open); }		
	}


	function update_vote_code(code) {

		if (DEBUGMODE) { console.log('@update_vote_code: ' + code); }

		if (code !== user_data.vote_code) {
			//init new user_data
			init_user_data(true);
		}
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
			fill_beer_lists(last_compare_function, activeTab); //show all beers, no ratings
		}
	}
	function update_ui_competition_status() {
		var style_class = 'd-inline-block pl-3 pr-3 mb-3';
		if (ENABLE_RATING === false) {
			style_class += ' bg-danger text-white';
			var open_closed_text = 'Betygsättningen är avstängd av tävlingsledningen';
			$('.rating').addClass('d-none'); //hide rating stars
		} else {
			$('.rating').removeClass('d-none');
			if (competition_open) {

				if (competition_seconds_to_close < 60) {
					style_class += ' bg-danger text-white'
				} else if (competition_seconds_to_close < 600) {
					style_class += ' bg-warning text-white'
				} else {
					style_class += ' rateinfo'
				}
				var open_closed_text = 'Betygsätt fram till kl. ' + competition_closes_hhmm + ', det är  '
					+ secondsToRemainString(competition_seconds_to_close) + ' kvar.';
			} else {
				if (competition_seconds_to_open < 0) {
					style_class += ' bg-danger text-white';
					var open_closed_text = 'Betygsättningen har stängt.';
					competition_has_closed = true;
				} else {
					style_class += ' bg-warning';
					var open_closed_text = 'Betygsättningen öppnar om '
						+ secondsToString(competition_seconds_to_open) + '.';
				}
			}
			if (beersHidden) {
				open_closed_text += ' <strong>Tävlingsbidrag är dolda innan tävling.</strong>';
			}
		}
		update_rating_allowed();

		$(".competition-status").removeClass(style_class).addClass(style_class).html(open_closed_text);
		//if (DEBUGMODE) { console.log('@update_ui_competition_status: ' + open_closed_text); }
	}
	//read ratings from backend  and update local storage etc
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
					update_no_vote_code_alert();
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
					update_no_vote_code_alert();
				}

			},
			error: function (xhr, textStatus, errorThrown) {
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
			}

		});
	}
	//store ratings to backend
	function store_ratings() {
		return $.ajax({
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
				//if (DEBUGMODE) { console.log("@pre_store_ratings"); console.log(user_data.ratings) };
				//if the server accepted the ratings, save them to localstorage
				if (response.msgtype == 'OK') {
					saveToLocalStorage();
				} else {
					user_data.ratings = response.ratings; //reset to what was stored and accepted by server
				}



				if (DEBUGMODE) { console.log("@store_ratings result"); console.log(response) };
			},
			error: function (xhr, textStatus, errorThrown) {
				if (DEBUGMODE) console.log("error: " + textStatus + ", responseText: " + xhr.responseText);
				if (DEBUGMODE) { console.log("@pre_store_ratings"); console.log(user_data.ratings) };
			}
		});
	}

	//get competition status from backend
	function get_competition_status(args) {
		$.ajax({
			type: "POST",
			url: "../vote/ajax/status.php",
			contentType: 'application/json',
			dataType: 'json',
			cache: false,
			data: JSON.stringify({
				competition_id: competition_id,
				isRateSystem: true
			}),
			success: function (response) {
				//if (DEBUGMODE) console.log(response);


				if (response.competition_id != competition_id) {
					setErrorAndSpinner("Tävlings-id stämmer inte", false);
					competition_open = false;
					if (DEBUGMODE) console.log('competition id mismatch');
					return;
				}
				else
					setErrorAndSpinner(""); //clear any previous error message and hide loading spinner

				if (response.ENABLE_RATING != null && response.ENABLE_RATING !== ENABLE_RATING) {
					ENABLE_RATING = response.ENABLE_RATING;

				}


				if (response.update_interval != VOTE_STATUS_INTERVAL) {
					clearInterval(vote_status_timer);
					VOTE_STATUS_INTERVAL = response.update_interval;
					vote_status_timer = window.setInterval(get_competition_status,
						VOTE_STATUS_INTERVAL);
					if (DEBUGMODE) console.log('vote status interval updated to ' + VOTE_STATUS_INTERVAL);
				}
				if (response.competition_name != null && response.competition_name !== competition_name) {
					competition_name = response.competition_name;
					$('#competition-name').html(competition_name);
					$(document).attr("title", competition_name);
				}
				if (response.competition_open != null) {
					competition_open = response.competition_open;
				}
				if (response.competition_closes_hhmm != null) {
					competition_closes_hhmm = response.competition_closes_hhmm;
				}
				if (response.competition_seconds_to_close != null) {
					competition_seconds_to_close = response.competition_seconds_to_close;
				}
				if (response.competition_seconds_to_open != null) {
					competition_seconds_to_open = response.competition_seconds_to_open;
				}
				if (response.competition_allow_comments_and_checkins != null) {
					competition_allow_comments_and_checkins = response.competition_allow_comments_and_checkins;
				}

				if (response.refresh_page != null && response.refresh_page === true) {
					if (DEBUGMODE) console.log('refresh_page backend request');
					get_competition_data().done(function () {
						update_vote_code(user_data.vote_code); // to refresh everything
						update_ui_competition_status();
					});

				}
				else {
					update_ui_competition_status();
				}




			},
			error: function (xhr, textStatus, errorThrown) {
				setErrorAndSpinner("Kunde inte hämta tävlingsstatus", false);
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);

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
	//only with a message to display, for unepected severe server/data errers
	//or message=''; to clear the error message & the loading spinner
	function setErrorAndSpinner(message, spinner = false) {
		var status = $('#error-status');
		if (message === '') {

			status.hide();

		}
		else {
			status.html("<strong>" + message + "</strong>");
			status.removeClass('d-none');
			status.show();
		}
		if (spinner) {
			//$('#loading-spinner').show();
			$('#loading-spinner').html('<div class="cssload-container"><div class="cssload-speeding-wheel"></div>');
		}
		else {
			//$('#loading-spinner').hide();
			$('#loading-spinner').html('');
		}
	}

	function secondsToString(s) {
		var hours = Math.floor(s / 3600);
		var minutes = Math.floor(s / 60) % 60;
		var seconds = s % 60;
		return [hours, minutes, seconds].map(v => v < 10 ? "0" + v : v).join(":")
	}
	//seconds to string hh timmar och mm minuter
	function secondsToRemainString(s) {
		var hours = Math.floor(s / 3600);
		var minutes = Math.floor(s / 60) % 60;
		var seconds = s % 120;

		var ret = '';
		if (hours < 1) {
			if (minutes >= 2)
				ret = minutes + ' minuter';
			else
				ret = seconds + ' sekunder';
		}
		else if (hours == 1) {
			if (minutes > 1)
				ret = hours + ' timma och ' + minutes + ' minuter';
			else if (minutes == 1)
				ret = hours + ' timma och ' + minutes + ' minut';
			else
				ret = hours + ' timma';
		}
		else {
			if (minutes > 1)
				ret = hours + ' timmar och ' + minutes + ' minuter';
			else if (minutes == 1)
				ret = hours + ' timmar och ' + minutes + ' minut';
			else
				ret = hours + ' timmar';
		}
		return ret;
	}


	return {
		init: init
	}
}();
