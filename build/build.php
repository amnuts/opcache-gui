<?php

chdir(dirname(__DIR__));

exec('npm run compile-jsx');
exec('npm run compile-scss');

$template = file_get_contents(__DIR__ . '/template.php');
$jsOutput = file_get_contents(__DIR__ . '/interface.js');
$cssOutput = file_get_contents(__DIR__ . '/interface.css');

$output = str_replace(['{{JS_OUTPUT}}', '{{CSS_OUTPUT}}'], [$jsOutput, $cssOutput], $template);
file_put_contents(dirname(__DIR__) . '/index.php', $output);

echo 'Compiled';
