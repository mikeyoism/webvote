/*Webvote - commons  & helpers
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
//helpers & commmons
//(for aux pages)
function supports_html5_storage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}

//helper
function printInfobar(elemId, msgtype, usrmsg,fadein)
{
    usrmsg = usrmsg.replace("\n","<br>");

    if (usrmsg.length > 0) {
        if (msgtype == "ok" || msgtype == "ok-cached")
            $(elemId).html('<div class="infobar infobar-ok" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "neutral")
            $(elemId).html('<div class="infobar infobar-neutral" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "warning")
            $(elemId).html('<div class="infobar infobar-warning" data-mini="true">' + usrmsg + '</div>');
        else if (msgtype == "error")
            $(elemId).html('<div class="infobar infobar-error" data-mini="true">' + usrmsg + '</div>');
        if (fadein === true) {
            $(elemId).fadeIn();			    
        }
        else
            $(elemId).show();
    }	    
}

var getObjectSize = function(obj) {
    var len = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) len++;
    }
    return len;
};