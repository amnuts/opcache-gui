<?php

namespace Amnuts\Opcache;

/**
 * OPcache GUI
 *
 * A simple but effective single-file GUI for the OPcache PHP extension.
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.2.1
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, http://acollington.mit-license.org/
 */

/*
 * User configuration
 * These are all the default values; you only really need to supply the ones
 * that you wish to change.
 */

$options = [
    'allow_filelist'   => true,          // show/hide the files tab
    'allow_invalidate' => true,          // give a link to invalidate files
    'allow_reset'      => true,          // give option to reset the whole cache
    'allow_realtime'   => true,          // give option to enable/disable real-time updates
    'refresh_time'     => 5,             // how often the data will refresh, in seconds
    'size_precision'   => 2,             // Digits after decimal point
    'size_space'       => false,         // have '1MB' or '1 MB' when showing sizes
    'charts'           => true,          // show gauge chart or just big numbers
    'debounce_rate'    => 250,           // milliseconds after key press to send keyup event when filtering
    'per_page'         => 200,           // How many results per page to show in the file list, false for no pagination
    'cookie_name'      => 'opcachegui',  // name of cookie
    'cookie_ttl'       => 365,           // days to store cookie
    'highlight'        => [
        'memory' => true,                // show the memory chart/big number
        'hits'   => true,                // show the hit rate chart/big number
        'keys'   => true                 // show the keys used chart/big number
    ]
];

/*
 * Shouldn't need to alter anything else below here
 */

if (!extension_loaded('Zend OPcache')) {
    die('The Zend OPcache extension does not appear to be installed');
}

$ocEnabled = ini_get('opcache.enable');
if (empty($ocEnabled)) {
    die('The Zend OPcache extension is installed but not active');
}

header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

{{PHP_OUTPUT}}

$opcache = (new Service($options))->handle();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>OPcache statistics on <?= $opcache->getData('version', 'host'); ?></title>
    <script src="//unpkg.com/react/umd/react.production.min.js" crossorigin></script>
    <script src="//unpkg.com/react-dom/umd/react-dom.production.min.js" crossorigin></script>
    <script src="//unpkg.com/axios/dist/axios.min.js" crossorigin></script>
    <style type="text/css">
        {{CSS_OUTPUT}}
    </style>
</head>

<body style="padding: 0; margin: 0;">

    <div class="opcache-gui" id="interface" />

    <script type="text/javascript">

    {{JS_OUTPUT}}

    ReactDOM.render(React.createElement(Interface, {
        allow: {
            filelist: <?= $opcache->getOption('allow_filelist') ? 'true' : 'false'; ?>,
            invalidate: <?= $opcache->getOption('allow_invalidate') ? 'true' : 'false'; ?>,
            reset: <?= $opcache->getOption('allow_reset') ? 'true' : 'false'; ?>,
            realtime: <?= $opcache->getOption('allow_realtime') ? 'true' : 'false'; ?>
        },
        cookie: {
            name: '<?= $opcache->getOption('cookie_name'); ?>',
            ttl: <?= $opcache->getOption('cookie_ttl'); ?>
        },
        opstate: <?= json_encode($opcache->getData()); ?>,
        useCharts: <?= json_encode($opcache->getOption('charts')); ?>,
        highlight: <?= json_encode($opcache->getOption('highlight')); ?>,
        debounceRate: <?= $opcache->getOption('debounce_rate'); ?>,
        perPageLimit: <?= json_encode($opcache->getOption('per_page')); ?>,
        realtimeRefresh: <?= json_encode($opcache->getOption('refresh_time')); ?>
    }), document.getElementById('interface'));

    </script>

</body>
</html>
