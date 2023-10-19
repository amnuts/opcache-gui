<?php

/**
 * OPcache GUI - build script
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.5.3
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, https://acollington.mit-license.org/
 */

$remoteJsLocations = [
    'cloudflare' => [
        'cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js',
        'cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js',
        'cdnjs.cloudflare.com/ajax/libs/axios/1.3.6/axios.min.js',
    ],
    'jsdelivr' => [
        'cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js',
        'cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js',
        'cdn.jsdelivr.net/npm/axios/dist/axios.min.js',
    ],
    'unpkg' => [
        'unpkg.com/react@18/umd/react.production.min.js',
        'unpkg.com/react-dom@18/umd/react-dom.production.min.js',
        'unpkg.com/axios/dist/axios.min.js',
    ],
];
$defaultRemoteJsFrom = array_keys($remoteJsLocations)[0];

$options = getopt('jr:l:', ['local-js', 'remote-js', 'lang:']);
$makeJsLocal = (isset($options['j']) || isset($options['local-js']));
$useRemoteJsFrom = $options['r'] ?? $options['remote-js'] ?? $defaultRemoteJsFrom;
$useLanguage = $options['l'] ?? $options['lang'] ?? null;
$languagePack = 'null';
$parentPath = dirname(__DIR__);

if (!isset($remoteJsLocations[$useRemoteJsFrom])) {
    $validRemotes = implode(', ', array_keys($remoteJsLocations));
    echo "\nThe '{$useRemoteJsFrom}' remote js location is not valid - must be one of {$validRemotes} - defaulting to '{$defaultRemoteJsFrom}'\n\n";
    $useRemoteJsFrom = $defaultRemoteJsFrom;
}

if ($useLanguage !== null) {
    $useLanguage = preg_replace('/[^a-z_-]/', '', $useLanguage);
    $languageFile = __DIR__ . "/_languages/{$useLanguage}.json";
    if (!file_exists($languageFile)) {
        echo "\nThe '{$useLanguage}' file does not exist - using default English\n\n";
    } else {
        $languagePack = "<<< EOJSON\n" . file_get_contents($languageFile) . "\nEOJSON";
    }
}

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
$phpOutput = trim(implode('', array_slice(file($parentPath . '/src/Opcache/Service.php'), 7)));

$output = str_replace(
    ['{{JS_OUTPUT}}', '{{CSS_OUTPUT}}', '{{PHP_OUTPUT}}', '{{LANGUAGE_PACK}}'],
    [$jsOutput, $cssOutput, $phpOutput, $languagePack],
    $template
);

if ($makeJsLocal) {
    echo "🔗 Making js locally in-line\n";
    $jsContents = [];
    foreach ($remoteJsLocations[$useRemoteJsFrom] as $jsUrl) {
        $jsContents[] = file_get_contents('https://' . $jsUrl);
    }
    $output = str_replace('{{JS_LIBRARIES}}',
        "<script>\n" . implode(";\n\n", $jsContents) . ";\n</script>",
        $output
    );
} else {
    echo "🔗 Using remote js links from '{$useRemoteJsFrom}'\n";
    $output = str_replace('{{JS_LIBRARIES}}',
        implode("\n    ", array_map(static function ($jsUrl) {
            return "<script src=\"//{$jsUrl}\"></script>";
        }, $remoteJsLocations[$useRemoteJsFrom])),
        $output
    );
}

file_put_contents($parentPath . '/index.php', $output);

echo "💯 Done!\n";
