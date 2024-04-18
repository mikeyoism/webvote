// -*- coding: utf-8 -*-
"use strict";


$(function (event) {
	user.init();
});

var user = function () {
    
    var DEBUGMODE = true;

    function init() {
        
        getBrewerBeer();
        
        $('#loginForm').on('submit', function (event) {
            event.preventDefault();
            user.login();
        });
    };
    //ajax query brewer beer info from user.php
    function getBrewerBeer() {
        $.ajax({
            url: 'user.php',
            type: 'POST',
            data: {action: 'getBrewerBeerStat'},
            success: function (response) {
                if (DEBUGMODE) {
                    console.log(response);
                }
                var pages = [];
                $.each(response, function (index, beer) {
                    if (index == 1){
                        $('#brewerName').html("Bryggarstatistik " + beer.competitionName + " för " + beer.brewer);
                    }
                    var page = '<div class="col-xs-12 col-md-6">' +
                        '<div class="card mb-4 box-shadow">' +
                        '<div class="card-header ">' +
                        '<h4>' + beer.entry_code + ". " + beer.name + '</h4>' +
                        '</div>' +
                        '<div class="card-body">' +
                        '<strong>Öltyp:</strong> ' + beer.styleName + '<br>' +
                        '<p class="card-text">' ;
                        //drankCount & ratingCount
                        page += '<strong>Antal provsmakade:</strong> ' + beer.drankCount + '<br>' +
                        '<strong>Antal betyg:</strong> ' + beer.ratingCount + '<br>';
                        $.each(beer.starCounts, function (index, starCount) {
                            page += '<strong>Betyg ' + index + ' stjärnor:</strong> ' + starCount + 'st<br>';
                        });

                        page += '<strong>Kommentarer om ölet:</strong> <br>';
                        
                        $.each(beer.ratingComments, function (index, ratingComment) {
                            page += '<p> <span class="badge  badge-secondary">' + ratingComment + '</span></p>';
                            
                        });
                        page += '</p>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                    pages.push(page);
                });
                $('#beerList').html(pages.join(''));



            }
            ,
			error: function (xhr, textStatus, errorThrown) {
				console.log("error: " + textStatus + ", responseText: " + xhr.responseText);

			}

        });
    
    
    };

    return {
		init: init
	}
}();
