<?php // -*- coding: utf-8 -*-
session_start();
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
require_once "../php/_config.php";
//hantera klientanrop    
if(isset($_POST['operation']))
{
    $operation = preg_replace('/[^-a-zA-Z0-9_]/', '', $_POST['operation']);
    /*
     *getjssettings - returns _config settings used in js
     */
    if ($operation == "getjssettings")
    {
        header('Content-Type: application/json', true);
        $jsonReply = array();
        $jsonReply["msgtype"] = "ok"; //dummy

        
        $jsonReply["SETTING_SYSSTATUS_INTERVAL"] = SETTING_SYSSTATUS_INTERVAL;
        $jsonReply["CONST_SYS_JS_DEBUG"] = CONST_SYS_JS_DEBUG;
        $jsonReply["CONST_SETTING_SHOW_HELP_POPUP"] =CONST_SETTING_SHOW_HELP_POPUP;
               

        $jsonReply["CONST_SETTING_VOTE_CODE_LENGTH"] = CONST_SETTING_VOTE_CODE_LENGTH;
        $jsonReply["CONST_SETTING_BEERID_NUMBERSPAN_LENGTH"] = CONST_SETTING_BEERID_NUMBERSPAN_LENGTH;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY"] = CONST_SETTING_VOTES_PER_CATEGORY;

        //används nu i backend only
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY_SAME"] = CONST_SETTING_VOTES_PER_CATEGORY_SAME;
        $jsonReply["CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL"] = CONST_SETTING_VOTES_PER_CATEGORY_SAME_REQUIRE_ALL;
         
        echo  json_encode($jsonReply);
        return true;


    }
}
?>