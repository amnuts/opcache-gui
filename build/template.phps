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
    <script src="//cdn.jsdelivr.net/react/15.5.4/react.min.js"></script>
    <script src="//cdn.jsdelivr.net/react/15.5.4/react-dom.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/jquery@3.5.0/dist/jquery.min.js"></script>
    <style type="text/css">
        {{CSS_OUTPUT}}
    </style>
</head>

<body style="padding: 0; margin: 0;">

<div class="opcache-gui">

    <header>
        <nav class="main-nav">
            <ul class="nav-tab-list">
                <li class="nav-tab"><a data-for="overview" href="#overview" class="active nav-tab-link">Overview</a></li>
                <?php if ($opcache->getOption('allow_filelist')): ?>
                    <li class="nav-tab"><a data-for="files" href="#files" class="nav-tab-link">File usage</a></li>
                <?php endif; ?>
                <?php if ($opcache->getOption('allow_reset')): ?>
                    <li class="nav-tab"><a href="?reset=1" id="resetCache" onclick="return confirm('Are you sure you want to reset the cache?');" class="nav-tab-link nav-tab-link-reset">Reset cache</a></li>
                <?php endif; ?>
                <?php if ($opcache->getOption('allow_realtime')): ?>
                    <li class="nav-tab"><a href="#" id="toggleRealtime" class="nav-tab-link nav-tab-link-realtime">Enable real-time update</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div id="tabs" class="tab-content-container">
        <div id="overview" class="tab-content tab-content-overview">
            <div>
                <div id="counts" class="tab-content-overview-counts"></div>
                <div id="info" class="tab-content-overview-info">
                    <div id="generalInfo"></div>
                    <div id="directives"></div>
                    <div id="functions">
                        <table class="tables">
                            <thead>
                            <tr><th>Available functions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($opcache->getData('functions') as $func): ?>
                                <tr><td><a href="http://php.net/<?php echo $func; ?>" title="View manual page" target="_blank"><?php echo $func; ?></a></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <br style="clear:both;" />
                </div>
            </div>
        </div>
        <div id="files" class="tab-content tab-content-files">
            <?php if ($opcache->getOption('allow_filelist')): ?>
                <form action="#">
                    <label for="frmFilter">Start typing to filter on script path</label><br>
                    <input type="text" name="filter" id="frmFilter" class="file-filter">
                </form>
            <?php endif; ?>
            <div id="filelist"></div>
        </div>
    </div>

    <footer class="main-footer">
        <a class="github-link" href="https://github.com/amnuts/opcache-gui" target="_blank" title="opcache-gui (currently version <?php echo Service::VERSION; ?>) on GitHub">https://github.com/amnuts/opcache-gui - version <?php echo Service::VERSION; ?></a>
    </footer>

</div>

<script type="text/javascript">
    var realtime = false;
    var opstate = <?php echo json_encode($opcache->getData()); ?>;
    var canInvalidate = <?php echo json_encode($opcache->canInvalidate()); ?>;
    var useCharts = <?php echo json_encode($opcache->getOption('charts')); ?>;
    var highlight = <?php echo json_encode($opcache->getOption('highlight')); ?>;
    var allowFiles = <?php echo json_encode($opcache->getOption('allow_filelist')); ?>;
    var debounce = function(func, wait, immediate) {
        var timeout;
        wait = wait || 250;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) {
                    func.apply(context, args);
                }
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) {
                func.apply(context, args);
            }
        };
    };
    function keyUp(event){
        var compare = $('#frmFilter').val().toLowerCase();
        $('#filelist').find('table tbody tr').each(function(index){
            if ($(this).data('path').indexOf(compare) == -1) {
                $(this).addClass('hide');
            } else {
                $(this).removeClass('hide');
            }
        });
        $('#filelist table tbody').trigger('paint');
    };
    <?php if ($opcache->getOption('charts')): ?>
    var Gauge = function(el, colour) {
        this.canvas  = $(el).get(0);
        this.ctx     = this.canvas.getContext('2d');
        this.width   = this.canvas.width;
        this.height  = this.canvas.height;
        this.colour  = colour || '#6ca6ef';
        this.loop    = null;
        this.degrees = 0;
        this.newdegs = 0;
        this.text    = '';
        this.init = function() {
            this.ctx.clearRect(0, 0, this.width, this.height);
            this.ctx.beginPath();
            this.ctx.strokeStyle = '#e2e2e2';
            this.ctx.lineWidth = 30;
            this.ctx.arc(this.width/2, this.height/2, 100, 0, Math.PI*2, false);
            this.ctx.stroke();
            this.ctx.beginPath();
            this.ctx.strokeStyle = this.colour;
            this.ctx.lineWidth = 30;
            this.ctx.arc(this.width/2, this.height/2, 100, 0 - (90 * Math.PI / 180), (this.degrees * Math.PI / 180) - (90 * Math.PI / 180), false);
            this.ctx.stroke();
            this.ctx.fillStyle = this.colour;
            this.ctx.font = '60px sans-serif';
            this.text = Math.round((this.degrees/360)*100) + '%';
            this.ctx.fillText(this.text, (this.width/2) - (this.ctx.measureText(this.text).width/2), (this.height/2) + 20);
        };
        this.draw = function() {
            if (typeof this.loop != 'undefined') {
                clearInterval(this.loop);
            }
            var self = this;
            self.loop = setInterval(function(){ self.animate(); }, 1000/(this.newdegs - this.degrees));
        };
        this.animate = function() {
            if (this.degrees == this.newdegs) {
                clearInterval(this.loop);
            }
            if (this.degrees < this.newdegs) {
                ++this.degrees;
            } else {
                --this.degrees;
            }
            this.init();
        };
        this.setValue = function(val) {
            this.newdegs = Math.round(3.6 * val);
            this.draw();
        };
    }
    <?php endif; ?>

    $(function(){
        <?php if ($opcache->getOption('allow_realtime')): ?>
        function setCookie() {
            var d = new Date();
            var secure = (window.location.protocol === 'https:' ? ';secure' : '');
            d.setTime(d.getTime() + (<?php echo ($opcache->getOption('cookie_ttl')); ?> * 86400000));
            var expires = "expires="+d.toUTCString();
            document.cookie = "<?php echo ($opcache->getOption('cookie_name')); ?>=true;" + expires + ";path=/" + secure;
        };
        function removeCookie() {
            var secure = (window.location.protocol === 'https:' ? ';secure' : '');
            document.cookie = "<?php echo ($opcache->getOption('cookie_name')); ?>=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/" + secure;
        };
        function getCookie() {
            var v = document.cookie.match('(^|;) ?<?php echo ($opcache->getOption('cookie_name')); ?>=([^;]*)(;|$)');
            return v ? v[2] : null;
        };
        function updateStatus() {
            $('#toggleRealtime').removeClass('pulse');
            $.ajax({
                url: "#",
                dataType: "json",
                cache: false,
                success: function(data) {
                    $('#toggleRealtime').addClass('pulse');
                    opstate = data;
                    overviewCountsObj.setState({
                        data : opstate.overview
                    });
                    generalInfoObj.setState({
                        version : opstate.version,
                        start : opstate.overview.readable.start_time,
                        reset : opstate.overview.readable.last_restart_time
                    });
                    filesObj.setState({
                        data : opstate.files,
                        count_formatted : opstate.overview.readable.num_cached_scripts,
                        count : opstate.overview.num_cached_scripts
                    });
                    keyUp();
                }
            });
        }
        $('#toggleRealtime').click(function(){
            if (realtime === false) {
                realtime = setInterval(function(){updateStatus()}, <?php echo (int)$opcache->getOption('refresh_time') * 1000; ?>);
                $(this).text('Disable real-time update');
                setCookie();
            } else {
                clearInterval(realtime);
                realtime = false;
                $(this).text('Enable real-time update').removeClass('pulse');
                removeCookie();
            }
        });
        if (getCookie() == 'true') {
            realtime = setInterval(function(){updateStatus()}, <?php echo (int)$opcache->getOption('refresh_time') * 1000; ?>);
            $('#toggleRealtime').text('Disable real-time update');
        }
        <?php endif; ?>
        $('nav a[data-for]').click(function(){
            $('#tabs > div').hide();
            $('#' + $(this).data('for')).show();
            $('nav a[data-for]').removeClass('active');
            $(this).addClass('active');
            return false;
        });
        $(document).on('paint', '#filelist table tbody', function(event, params) {
            var trs = $('#filelist').find('tbody tr');
            trs.removeClass('alternate');
            trs.filter(':not(.hide):odd').addClass('alternate');
            filesObj.setState({showing: trs.filter(':not(.hide)').length});
        });
        $('#frmFilter').bind('keyup', debounce(keyUp, <?php echo $opcache->getOption('debounce_rate'); ?>));
    });

    {{JS_OUTPUT}}
</script>

</body>
</html>
