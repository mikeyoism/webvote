<?php // -*- coding: utf-8 -*-
require_once '../../vote/php/_config.php';
$competitionId = COMPETITION_ID;

require_once '../../vote/php/common.inc';
//TODO: cashed file option & apc memcache for db

if (!CONST_SETTING_CONNECT_EVENTREG_DB) {
    //Read from files
    
    $files = [
        array('class_id' => '92', 'name' => 'Klass 1', 'description' => 'Lager och Underjäst öl', 'filename' => 'data/list_fv1.txt'),
        array('class_id' => '93', 'name' => 'Klass 2', 'description' => 'Maltdominerad öl', 'filename' => 'data/list_fv2.txt'),
        array('class_id' => '94', 'name' => 'Klass 3', 'description' => 'Humledominerad öl', 'filename' => 'data/list_fv3.txt'),
        array('class_id' => '95', 'name' => 'Klass 4', 'description' => 'Jästdominerad öl', 'filename' => 'data/list_fv4.txt'),
        array('class_id' => '96', 'name' => 'Klass 5', 'description' => 'Syrligt och Spontanjäst öl', 'filename' => 'data/list_fv5.txt'),
        array('class_id' => '97', 'name' => 'Klass 6', 'description' => 'Övriga öl', 'filename' => 'data/list_fv6.txt')
        //98 Cider och Mjöd
        //99 Folköl
        //100 Bästa etikett
    ];

    $classes = array();
    $beers = array();
    $nextEntryId = 1;
    foreach ($files as $class) {
        array_push($classes, array('id' => $class['class_id'], 'name' => $class['name'], 'description' => $class['description']));

        $str = file_get_contents($class['filename']);
        $lines = explode("\n", trim($str, "\n"));
        foreach ($lines as $line) {
            $cols = explode("\t", $line);

            $styleParts = explode(" - ", $cols[1]);

            $entryId = $nextEntryId++;
            $beer = array(
                'class' => $class['class_id'],
                'entry_id' => $entryId,
                'entry_code' => $cols[0],
                'styleId' => $styleParts[0],
                'styleName' => $styleParts[1],
                'name' => $cols[2],
                'brewer' => $cols[3],
                'alk' => $cols[4],
                'OG' => $cols[5],
                'FG' => $cols[6],
                'IBU' => $cols[7]
            );
            $beers[$entryId] = $beer;
        }
    }
}
else
{
    //read from database (eventreg)
    $beers = array();
    $dbAccess = new DbAccess();
    $beers = $dbAccess->getBeers($competitionId,false);
    
    $classes = $dbAccess->getCategories($competitionId,true);

}
header('Content-Type: application/json', true);
echo json_encode(array(
    'competition_id' => $competitionId,
    'classes' => $classes,
    'beers' => $beers
));
