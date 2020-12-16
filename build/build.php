<?php

/**
 * OPcache GUI - build script
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.2.1
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, http://acollington.mit-license.org/
 */

$parentPath = dirname(__DIR__);

if (!file_exists($parentPath . '/node_modules')) {
    echo "🐢 Installing node modules\n";
    exec('npm install');
}

echo "🏗️ Building js and css\n";
chdir($parentPath);
exec('npm run compile-jsx');
exec('npm run compile-scss');

echo "🚀 Creating single build file\n";
$template = trim(file_get_contents(__DIR__ . '/template.phps'));
$jsOutput = trim(file_get_contents(__DIR__ . '/interface.js'));
$cssOutput = trim(file_get_contents(__DIR__ . '/interface.css'));
$phpOutput = trim(join('', array_slice(file($parentPath . '/src/Opcache/Service.php'), 3)));
$output = str_replace(
    ['{{JS_OUTPUT}}', '{{CSS_OUTPUT}}', '{{PHP_OUTPUT}}'],
    [$jsOutput, $cssOutput, $phpOutput],
    $template
);
file_put_contents($parentPath . '/index.php', $output);

echo "💯 Done!\n";
