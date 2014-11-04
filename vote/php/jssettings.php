<?php session_start();
/*Webvote - function for retrieving JS-settings 
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
require_once "_config.php";
//hantera klientanrop    
if(isset($_POST['operation']))
{
    $operation = filter_var($_POST['operation'], FILTER_SANITIZE_STRING);
    /*
     *getjssettings - returns _config settings used in js
     */
    if ($operation == "getjssettings")
    {
        header('Content-Type: application/json', true);
        $jsonReply = array();
        $jsonReply["msgtype"] = "ok"; //dummy
        
        $jsonReply["CONST_SYS_JS_DEBUG"] = CONST_SYS_JS_DEBUG;
        $jsonReply["DISABLE_CLIENT_CHECKS"] = DISABLE_CLIENT_CHECKS;
        $jsonReply["SETTING_SYSSTATUS_INTERVAL"] = SETTING_SYSSTATUS_INTERVAL;
        $jsonReply["REQUEST_SYSSTATUS"] = SETTING_SYSSTATUS_DO_NOT_DISABLE_ON_CLIENT_LOAD; //to client naming convention
        
        $jsonReply["SETTING_COMPETITION_NAME"] = SETTING_COMPETITION_NAME;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY_SAME"] = CONST_SETTING_VOTES_PER_CATEGORY_SAME;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL"] = CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY"] = CONST_SETTING_VOTES_PER_CATEGORY;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL"] = CONST_SETTING_VOTES_PER_CATEGORY_REQUIRE_ALL;
        $jsonReply["CONST_SETTING_CATEGORIES_SYS"] = unserialize(CONST_SETTING_CATEGORIES_SYS);
        $jsonReply["CONST_SETTING_VOTE_WEIGHT"] = unserialize(CONST_SETTING_VOTE_WEIGHT);
        echo  json_encode($jsonReply);
        return true;


    }
}
?>