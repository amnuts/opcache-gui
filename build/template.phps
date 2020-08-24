<?php

namespace Amnuts\Opcache;

/**
 * OPcache GUI
 *
 * A simple but effective single-file GUI for the OPcache PHP extension.
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.0.0
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, http://acollington.mit-license.org/
 */

/*
 * User configuration
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
    'cookie_name'      => 'opcachegui',  // name of cookie
    'cookie_ttl'       => 365,           // days to store cookie
    'highlight'        => [              // highlight charts/big numbers
        'memory' => true,
        'hits'   => true,
        'keys'   => true
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
    <title>OPcache statistics on <?php echo $opcache->getData('version', 'host'); ?></title>
    <script src="//unpkg.com/react@16/umd/react.development.js" crossorigin></script>
    <script src="//unpkg.com/react-dom@16/umd/react-dom.development.js" crossorigin></script>
    <script src="//cdn.jsdelivr.net/npm/jquery@3.5.0/dist/jquery.min.js"></script>
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
            filelist: <?php echo $opcache->getOption('allow_filelist') ? 'true' : 'false'; ?>,
            invalidate: <?php echo $opcache->getOption('allow_invalidate') ? 'true' : 'false'; ?>,
            reset: <?php echo $opcache->getOption('allow_reset') ? 'true' : 'false'; ?>,
            realtime: <?php echo $opcache->getOption('allow_realtime') ? 'true' : 'false'; ?>,
        },
        opstate: <?php echo json_encode($opcache->getData()); ?>,
        useCharts: <?php echo json_encode($opcache->getOption('charts')); ?>,
        highlight: <?php echo json_encode($opcache->getOption('highlight')); ?>,
    }), document.getElementById('interface'));

    </script>

</body>
</html>
