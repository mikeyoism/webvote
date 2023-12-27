<?php
require "sqlopen_pdo.MY.gitignore.php"; //either create the MY.gitignore.php file with values for above variables set, or fill in above variables and remove this line.

//our php/html-pages are stored as utf8
//avoid PDO::ATTR_PERSISTENT => true ? to avoid reaching max-user limit
//see http://stackoverflow.com/questions/23432948/fully-understanding-pdo-attr-persistent
//note, caller should catch conn errors himself, do not leave exception active here unless testing.
//try {
    $db = new PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8", $dbuser, $dbpass,array(PDO::ATTR_EMULATE_PREPARES => false, 
                                                                                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
//} catch (PDOException $e) {
//    print "Error!: " . $e->getMessage() . "<br/>";
//    die();
//}
?>
