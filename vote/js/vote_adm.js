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
$(document).on('pageinit',function(event) {
    
    votejs.getVote_count();
    $('#votecode_gen500').on('click',function(e) {
    
	$.ajax({  
		type: "POST",  
		url: "./php/admin_ajax.php",  
		dataType: 'html',
		cache : false,
		data: {
		    nrofcodes: 		100, 
		    operation: 		'votecode_generate',
		    source:         	'adm.php'
		},

		success: function(response) {
		    if (DEBUGMODE) console.log(response);
		    alert(response);
		    $("#err").html(response);
		    votejs.getVote_count();

		},
		error: function(xhr, status, thrown) {
		    if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
		    alert("misslyckades");
		    
		}  
	
	});

    });
});

//model for client vote logic
votejs = function(){
    var DEBUGMODE = true;
    
    function getVote_count()
    {
	
	$.ajax({  
		type: "POST",  
		url: "./php/admin_ajax.php",  
		dataType: 'html',
		cache : false,
		data: {
		    operation: 		'votecode_count',
		    source:         	'adm.php'
		},

		success: function(response) {
		    if (DEBUGMODE) console.log(response);
		    $("#countshow").html("databasen innehåller " + response + " röstkoder");
		    

		},
		error: function(xhr, status, thrown) {
		    if (DEBUGMODE) console.log("error, post_vote:" + xhr + "," + status + "," + thrown );
		    $("#countshow").html("serverfel: " + status);
		}  
	
	});
    }
    function init(){
       
    };
    
    return {
        init : init,
        getVote_count : getVote_count
        
    }         
    
}();
