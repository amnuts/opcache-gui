<?php

/**
 * OPcache GUI - build script
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.4.0
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, https://acollington.mit-license.org/
 */

$options = getopt('l', ['local-js']);
$makeJsLocal = (isset($options['l']) || isset($options['local-js']));
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
$phpOutput = trim(implode('', array_slice(file($parentPath . '/src/Opcache/Service.php'), 3)));
$output = str_replace(
    ['{{JS_OUTPUT}}', '{{CSS_OUTPUT}}', '{{PHP_OUTPUT}}', '{{LANGUAGE_PACK}}'],
    [$jsOutput, $cssOutput, $phpOutput, 'null'],
    $template
);
if ($makeJsLocal) {
    echo "🔗 Making js links local\n";
    $jsTags = [];
    $matched = preg_match_all('!<script src="([^"]*)"></script>!', $output, $jsTags);
    if ($matched) {
        foreach ($jsTags[1] as $jsUrl) {
            $jsFile = basename($jsUrl);
            $jsFilePath = $parentPath . '/' . $jsFile;
            file_put_contents($jsFilePath, file_get_contents('https:' . $jsUrl));
            $output = str_replace($jsUrl, $jsFile, $output);
        }
    }
}

file_put_contents($parentPath . '/index.php', $output);

echo "💯 Done!\n";
