<?php // -*- coding: utf-8 -*-

$competitionId = 1;
$enableVoting = 1;


$files = [
    array( 'class_id' => '5', 'name' => 'Klass 1', 'description' => 'Lager och Underjäst öl', 'filename' => 'data/list_fv1.txt' ),
    array( 'class_id' => '6', 'name' => 'Klass 2', 'description' => 'Maltdominerad öl', 'filename' => 'data/list_fv2.txt' ),
    array( 'class_id' => '7', 'name' => 'Klass 3', 'description' => 'Humledominerad öl', 'filename' => 'data/list_fv3.txt' ),
    array( 'class_id' => '8', 'name' => 'Klass 4', 'description' => 'Jästdominerad öl', 'filename' => 'data/list_fv4.txt' ),
    array( 'class_id' => '9', 'name' => 'Klass 5', 'description' => 'Syrligt och Spontanjäst öl', 'filename' => 'data/list_fv5.txt' ),
    array( 'class_id' => '10', 'name' => 'Klass 6', 'description' => 'Övriga öl', 'filename' => 'data/list_fv6.txt' )
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
        $beer = array('class' => $class['class_id'],
                      'entry_id' => $entryId,
                      'entry_code' => $cols[0],
                      'styleId' => $styleParts[0],
                      'styleName' => $styleParts[1],
                      'name' => $cols[2],
                      'brewer' => $cols[3],
                      'alk' => $cols[4],
                      'OG' => $cols[5],
                      'FG' => $cols[6],
                      'IBU' => $cols[7]);
        $beers[$entryId] = $beer;
    }
}
 
header('Content-Type: application/json', true);
echo json_encode(array('competition_id' => $competitionId,
                       'enable_voting' => $enableVoting,
                       'classes' => $classes,
                       'beers' => $beers));
