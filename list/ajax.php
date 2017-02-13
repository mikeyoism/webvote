<?php // -*- coding: utf-8 -*-
$str = file_get_contents('fvlista.serialized');
$rawBeers = unserialize($str);

array_walk_recursive($rawBeers, function(&$value, $key) {
    if (is_string($value)) {
        $value = iconv('latin1', 'utf-8', $value);
    }
});

$beers = array();
$classes = array(array('id' => 'c1', 'name' => 'Klass 1'),
                 array('id' => 'c2', 'name' => 'Klass 2'),
                 array('id' => 'c3', 'name' => 'Klass 3'));

foreach ($rawBeers as $beer) {
    if ($beer['alk'] < 4) {
        $class = 'c1';
    } else if ($beer['alk'] < 6.5) {
        $class = 'c2';
    } else {
        $class = 'c3';
    }

    $beer['class'] = $class;

    $entry_id = 'beer-' . $class . '-' . $beer['beerCounter'];
    $beer['entry_id'] = $entry_id;
    
    $style = $beer['beerType'];
    $beer['styleName'] = substr($style, strpos($style, '-') + 2);
    $beer['styleId'] = substr($style, 0, strpos($style, '-') - 1);

    $beers[$entry_id] = $beer;
}
 
header('Content-Type: application/json', true);
echo json_encode(array('competition_id' => 2, 'classes' => $classes, 'beers' => $beers));
