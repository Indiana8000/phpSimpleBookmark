<?php

$x = parse_url('https://example.com/a/b/c/test.png?arg=value#anchor');

print_r($x);

$y = pathinfo($x['path']);

print_r($y);


?>