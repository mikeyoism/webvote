// -*- coding: utf-8 -*-
"use strict";

//föneklad version av rate.js för publikvisning av öppettider och pubik statistik.
//TODO: flera funktioner är dupes från rate.js, bör flyttas till en gemensam fil

$(function (event) {
    user.init();
});

var user = function () {

    var competition_id = null;
    var classes = null;
    var beers = null;
    var styles = null;

    var beersHidden = null; //before competition start


    // This is stored in localStorage, and poulated from backend
    // contins user_data.vote_code, user_data.beers and user_data.ratings etc.
    var user_data = null;

    var vote_status_timer = null;
    var vote_status_timer2 = null;
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
    var cssCompetitionTheme = 'theme_default.css'; //empty file default template
    function init() {

        getRateSettings().done(function () {
            get_competition_data().done(function () {

                if (DEBUGMODE) { console.log('init done, comptetition_id: ' + competition_id  );  }
                //start competition status timer
                vote_status_timer = window.setInterval(get_competition_status, VOTE_STATUS_INTERVAL);
                get_competition_status();
                vote_status_timer2 = window.setInterval(getPublicStat, 4000);
                getPublicStat();
                
                var img_src = $('#eventposter-slide img').css('content');
                if (!(img_src == "normal" || img_src == "none") ) {
                    $('#eventposter-slide').removeClass('d-none');
                    
                }
            });


            
        });


    };
    //ajax query brewer beer info from pub.php
    function getPublicStat() {
        $.ajax({
            url: 'pub.php',
            type: 'POST',
            data: { action: 'getPublicStat' },
            success: function (response) {
                if (DEBUGMODE) {
                    console.log(response);
                }
                var pages = [];
                if (response.usrmsg == "OK") {
                    $('#rated').html('<strong>'+response.ratingCountTotal+'</strong>');
                    $('#tasted').html('<strong>'+response.drankCountTotal+'</strong>');
                    

                } else {
                    $('#beerList').html(response.usrmsg);
                }

            }
            ,
            error: function (xhr, textStatus, errorThrown) {
                console.log("error: " + textStatus + ", responseText: " + xhr.responseText);

            }

        });


    };
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
            open_closed_text = '<h1>' + open_closed_text + '</h1>'; //xl in this file
        }
        //update_rating_allowed();

        $(".competition-status").removeClass(style_class).addClass(style_class).html(open_closed_text);


        //if (DEBUGMODE) { console.log('@update_ui_competition_status: ' + open_closed_text); }
    }

    //get competition status from backend
    function get_competition_status(args) {
        $.ajax({
            type: "POST",
            url: "../../vote/ajax/status.php",
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

    function getRateSettings() {

        return $.ajax({
            type: "POST",
            url: "../../vote/ajax/jssettings.php",
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
                        $('#css-theme').attr('href', '../css/' + cssth);

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
            url: '../php/ajax.php',
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
	//only with a message to display, for unepected severe server/data errers
	//or message=''; to clear the error message & the loading spinner
    
    //atm: not visisible, no error-status in html for pub.php
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
    function secondsToRemainString(seconds) {
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        var str = '';
        if (h > 0) str += h + ' timmar ';
        if (m > 0) str += m + ' minuter ';
        if (s > 0) str += s + ' sekunder ';
        return str;
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
