<?php namespace ProcessWire;
include 'index.php';

foreach($pages->find('template=admission-record') as $a) {
    $a->of(false);
    $a->admitted_on = time();
    $a->save('admitted_on');
    echo "Fixed date for {$a->title}\n";
}
