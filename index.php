<?php

/**
 * OPcache GUI
 *
 * A simple but effective single-file GUI for the OPcache PHP extension.
 *
 * @author Andrew Collington, andy@amnuts.com
 * @license MIT, http://acollington.mit-license.org/
 */

if (!extension_loaded('Zend OPcache')) {
    die('The Zend OPcache extension does not appear to be installed');
}

class OpCacheService
{
    protected $data;
    protected $options = [
        'allow_invalidate' => true
    ];

    private function __construct($options = [])
    {
        $this->data = $this->compileState();
        $this->options = array_merge($this->options, $options);
    }

    public static function init()
    {
        $self = new self;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            if ((isset($_GET['reset']))) {
                echo '{ "success": "' . ($self->resetCache() ? 'yes' : 'no') . '" }';
            } else if ((isset($_GET['invalidate']))) {
                echo '{ "success": "' . ($self->resetCache($_GET['invalidate']) ? 'yes' : 'no') . '" }';
            } else {
                echo json_encode($self->getData(@$_GET['section'] ?: null));
            }
            exit;
        } else if ((isset($_GET['reset']))) {
            $self->resetCache();
        } else if ((isset($_GET['invalidate']))) {
            $self->resetCache($_GET['invalidate']);
        }
        return $self;
    }

    public function getOption($name = null)
    {
        if ($name === null) {
            return $this->options;
        }
        return (isset($this->options[$name])
            ? $this->options[$name]
            : null
        );
    }

    public function getData($section = null)
    {
        if ($section === null) {
            return $this->data;
        }
        $section = strtolower($section);
        return (isset($this->data[$section])
            ? $this->data[$section]
            : null
        );
    }

    public function resetCache($file = null)
    {
        $success = false;
        if ($file === null) {
            $success = opcache_reset();
        } else if (function_exists('opcache_invalidate')) {
            $success = opcache_invalidate(urldecode($file), true);
        }
        if ($success) {
            $this->compileState();
        }
        return $success;
    }

    protected function compileState()
    {
        $status = opcache_get_status();
        $config = opcache_get_configuration();
        $memsize = function($size, $precision = 3, $space = false)
        {
            $i = 0;
            $val = array(' bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
            while (($size / 1024) > 1) {
                $size /= 1024;
                ++$i;
            }
            return sprintf("%.{$precision}f%s%s", $size, (($space && $i) ? ' ' : ''), $val[$i]);
        };

        $files = [];
        if (!empty($status['scripts'])) {
            uasort($status['scripts'], function($a, $b) {
                return $a['hits'] < $b['hits'];
            });
            foreach ($status['scripts'] as &$file) {
                $file['full_path'] = str_replace('\\', '/', $file['full_path']);
                $file['readable'] = [
                    'hits'               => number_format($file['hits']),
                    'memory_consumption' => $memsize($file['memory_consumption'])
                ];
            }
            $files = array_values($status['scripts']);
        }

        $overview = array_merge(
            $status['memory_usage'], $status['opcache_statistics'], [
                'used_memory_percentage'  => round(100 * (
                        ($status['memory_usage']['used_memory'] + $status['memory_usage']['wasted_memory'])
                        / $config['directives']['opcache.memory_consumption'])),
                'hit_rate_percentage'     => round($status['opcache_statistics']['opcache_hit_rate']),
                'wasted_percentage'       => round($status['memory_usage']['current_wasted_percentage'], 2),
                'readable' => [
                    'total_memory'       => $memsize($config['directives']['opcache.memory_consumption']),
                    'used_memory'        => $memsize($status['memory_usage']['used_memory']),
                    'free_memory'        => $memsize($status['memory_usage']['free_memory']),
                    'wasted_memory'      => $memsize($status['memory_usage']['wasted_memory']),
                    'num_cached_scripts' => number_format($status['opcache_statistics']['num_cached_scripts']),
                    'hits'               => number_format($status['opcache_statistics']['hits']),
                    'misses'             => number_format($status['opcache_statistics']['misses']),
                    'blacklist_miss'     => number_format($status['opcache_statistics']['blacklist_misses']),
                    'num_cached_keys'    => number_format($status['opcache_statistics']['num_cached_keys']),
                    'max_cached_keys'    => number_format($status['opcache_statistics']['max_cached_keys']),
                    'start_time'         => date_format(date_create("@{$status['opcache_statistics']['start_time']}"), 'Y-m-d H:i:s'),
                    'last_restart_time'  => ($status['opcache_statistics']['last_restart_time'] == 0
                            ? 'never'
                            : date_format(date_create("@{$status['opcache_statistics']['last_restart_time']}"), 'Y-m-d H:i:s')
                        )
                ]
            ]
        );

        $directives = [];
        ksort($config['directives']);
        foreach ($config['directives'] as $k => $v) {
            $directives[] = ['k' => $k, 'v' => $v];
        }

        $version = array_merge(
            $config['version'],
            [
                'php'    => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'],
                'host'   => (function_exists('gethostname')
                    ? gethostname()
                    : (php_uname('n')
                        ?: (empty($_SERVER['SERVER_NAME'])
                            ? $_SERVER['HOST_NAME']
                            : $_SERVER['SERVER_NAME']
                        )
                    )
                )
            ]
        );

        return [
            'version'    => $version,
            'overview'   => $overview,
            'files'      => $files,
            'directives' => $directives,
            'blacklist'  => $config['blacklist'],
            'functions'  => get_extension_funcs('Zend OPcache')
        ];
    }
}

$opcache = OpCacheService::init();

?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <script src="http://fb.me/react-0.12.1.js"></script>
    <script src="http://fb.me/JSXTransformer-0.12.1.js"></script>
    <script src="http://code.jquery.com/jquery-2.1.1.min.js"></script>
    <style type="text/css">
        body { font-family:sans-serif; font-size:90%; padding: 0; margin: 0 }
        nav { padding-top: 20px; }
        nav > ul { list-style-type: none; padding-left: 8px; margin: 0; border-bottom: 1px solid #ccc; }
        nav > ul > li { display: inline-block; padding: 0; margin: 0 0 -1px 0; }
        nav > ul > li > a { display: block; margin: 0 10px; padding: 15px 30px; border: 1px solid transparent; border-bottom-color: #ccc; text-decoration: none; }
        nav > ul > li > a:hover { background-color: #f4f4f4; text-decoration: underline; }
        nav > ul > li > a.active:hover { background-color: initial; }
        nav > ul > li > a[data-for].active { border: 1px solid #ccc; border-bottom-color: #ffffff; border-top: 3px solid #6ca6ef; }
        table { margin: 0 0 1em 0; border-collapse: collapse; border-color: #fff; width: 100%; }
        table caption { text-align: left; font-size: 1.5em; }
        table tr { background-color: #99D0DF; border-color: #fff; }
        table th { text-align: left; padding: 6px; background-color: #0BA0C8; color: #fff; border-color: #fff; font-weight: normal; }
        table td { padding: 4px 6px; line-height: 1.4em; vertical-align: top; border-color: #fff; }
        table tr:nth-child(odd) { background-color: #EFFEFF; }
        table tr:nth-child(even) { background-color: #E0ECEF; }
        td.pathname { width: 70%; }
        #tabs { padding: 2em; }
        #tabs > div { display: none; }
        #tabs > div#overview { display:block; }
        #resetCache, #toggleRealtime { background-position: 5px 50%; background-repeat: no-repeat; background-color: transparent; }
        #resetCache { background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NjBFMUMyMjI3NDlGMTFFNEE3QzNGNjQ0OEFDQzQ1MkMiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NjBFMUMyMjM3NDlGMTFFNEE3QzNGNjQ0OEFDQzQ1MkMiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo2MEUxQzIyMDc0OUYxMUU0QTdDM0Y2NDQ4QUNDNDUyQyIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo2MEUxQzIyMTc0OUYxMUU0QTdDM0Y2NDQ4QUNDNDUyQyIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PplZ+ZkAAAD1SURBVHjazFPtDYJADIUJZAMZ4UbACWQENjBO4Ao6AW5AnODcADZQJwAnwJ55NbWhB/6zycsdpX39uDZNpsURtjgzwkDoCBecs5ITPGGMwCNAkIrQw+8ri36GhBHsavFdpILEo4wEpZxRigy009EhG760gr0VhFoyZfvJKPwsheIWIeGejBZRIxRVhMRFevbuUXBew/iE/lhlBduV0j8Jx+TvJEWPphq8n5li9utgaw6cW/h6NSt/JcnVBhQxotIgKTBrbNvIHo2G0x1rwlKqTDusxiAz6hHNL1zayTVqVKRKpa/LPljPH1sJh6l/oNSrZfwSYABtq3tFdZA5BAAAAABJRU5ErkJggg=='); }
        #toggleRealtime { background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAAUCAYAAACAl21KAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6ODE5RUU4NUE3NDlGMTFFNDkyMzA4QzY1RjRBQkIzQjUiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6ODE5RUU4NUI3NDlGMTFFNDkyMzA4QzY1RjRBQkIzQjUiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo4MTlFRTg1ODc0OUYxMUU0OTIzMDhDNjVGNEFCQjNCNSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo4MTlFRTg1OTc0OUYxMUU0OTIzMDhDNjVGNEFCQjNCNSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PpXjpvMAAAD2SURBVHjarFQBEcMgDKR3E1AJldA5wMEqAQmTgINqmILdFCChdUAdMAeMcukuSwnQbbnLlZLwJPkQIcrSiT/IGNQHNb8CGQDyRw+2QWUBqC+luzo4OKQZIAVrB+ssyKp3Bkijf0+ijzIh4wQppoBauMSjyDZfMSCDxYZMsfHF120T36AqWZMkgyguQ3GOfottJ5TKnHC+wfeRsC2oDVayPgr3bbN2tHBH3tWuJCPa0JUgKtFzMQrcZH3FNHAc0yOp1cCASALyngoN6lhDopkJWxdifwY9A3u7l29ImpxOFSWIOVsGwHKENIWxss2eBVKdOeeXAAMAk/Z9h4QhXmUAAAAASUVORK5CYII='); }
        #counts { width: 270px; float: right; }
        #counts > div > div { background-color: #ededed; margin-bottom: 10px; }
        #counts > div > div > h3 { background-color: #cdcdcd; padding: 4px 6px; margin: 0; }
        #counts > div > div > p { margin: 0; text-align: center; }
        #counts > div > div > p > span.large ~ span { font-size: 20pt; margin: 0; }
        #counts > div > div > p > span.large { font-size: 80pt; margin: 0; padding: 0; text-align: center; }
        #info { margin-right: 280px; }
        #moreinfo { padding: 10px; }
        #moreinfo > p { text-align: left !important; line-height: 180%; }
        .metainfo { font-size: 80%; }
        @media screen and (max-width: 750px) {
            #info { margin-right:auto; }
            nav > ul { border-bottom: 0; }
            nav > ul > li { display: block; margin: 0; }
            nav > ul > li > a { display: block; margin: 0 10px; padding: 10px 0 10px 30px; border: 0; }
            nav > ul > li > a[data-for].active { border-bottom-color: #ccc; }
            #counts { position:relative; display:block; width:100%; }
        }
        @media screen and (max-width: 550px) {
            #frmFilter { width: 100%; }
        }
    </style>
</head>

<body>

<nav>
    <ul>
        <li><a data-for="overview" href="#overview" class="active">Overview</a></li>
        <li><a data-for="files" href="#files">File usage</a></li>
        <li><a href="?reset=1" id="resetCache" onclick="return confirm('Are you sure you want to reset the cache?');">Reset cache</a></li>
        <li><a href="#" id="toggleRealtime">Enable real-time update</a></li>
    </ul>
</nav>

<div id="tabs">
    <div id="overview">
        <div class="container">
            <div id="counts"></div>
            <div id="info">
                <div id="generalInfo"></div>
                <div id="directives"></div>
                <div id="functions">
                    <table>
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
    <div id="files">
        <p><label>Start typing to filter on script path<br/><input type="text" style="width:40em;" name="filter" id="frmFilter" /><label></p>
        <div class="container" id="filelist"></div>
    </div>
</div>

<script type="text/javascript">
    var realtime = false;

    $(function(){
        function updateStatus() {
            $.ajax({
                url: "#",
                dataType: "json",
                cache: false,
                success: function(data) {
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
                    $('#frmFilter').trigger('keyup');
                }
            });
        }
        $('#toggleRealtime').click(function(){
            if (realtime === false) {
                realtime = setInterval(function(){updateStatus()}, 5000);
                $(this).text('Disable real-time update');
            } else {
                clearInterval(realtime);
                realtime = false;
                $(this).text('Enable real-time update');
            }
        });
        $('nav a[data-for]').click(function(){
            $('#tabs > div').hide();
            $('#' + $(this).data('for')).show();
            $('nav a[data-for]').removeClass('active');
            $(this).addClass('active');
            return false;
        });
        $(document).on('paint', '#filelist table tbody', function(event, params) {
            var trs = $('tr:visible', $(this));
            trs.filter(':odd').css({backgroundColor:'#E0ECEF'})
               .end().filter(':even').css({backgroundColor:'#EFFEFF'});
            filesObj.setState({showing: trs.length});
        });
        $('#frmFilter').bind('keyup', function(event){
            $('span.pathname').each(function(index){
                if ($(this).text().toLowerCase().indexOf($('#frmFilter').val().toLowerCase()) == -1) {
                    $(this).closest('tr').hide();
                } else {
                    $(this).closest('tr').show();
                }
            });
            $('#filelist table tbody').trigger('paint');
        });
    });
</script>

<script type="text/jsx">
    var opstate = <?php echo json_encode($opcache->getData()); ?>;

    var OverviewCounts = React.createClass({
        getInitialState: function() {
            return { data : opstate.overview };
        },
        render: function() {
            return (
                <div>
                    <div>
                        <h3>memory usage</h3>
                        <p><span className="large">{this.state.data.used_memory_percentage}</span><span>%</span></p>
                    </div>
                    <div>
                        <h3>hit rate</h3>
                        <p><span className="large">{this.state.data.hit_rate_percentage}</span><span>%</span></p>
                    </div>
                    <div id="moreinfo">
                        <p><b>total memory:</b>{this.state.data.readable.total_memory}</p>
                        <p><b>used memory:</b>{this.state.data.readable.used_memory}</p>
                        <p><b>free memory:</b>{this.state.data.readable.free_memory}</p>
                        <p><b>wasted memory:</b>{this.state.data.readable.wasted_memory} ({this.state.data.wasted_percentage}%)</p>
                        <p><b>number of cached files:</b>{this.state.data.readable.num_cached_scripts}</p>
                        <p><b>number of hits:</b>{this.state.data.readable.hits}</p>
                        <p><b>number of misses:</b>{this.state.data.readable.misses}</p>
                        <p><b>blacklist misses:</b>{this.state.data.readable.blacklist_miss}</p>
                        <p><b>number of cached keys:</b>{this.state.data.readable.num_cached_keys}</p>
                        <p><b>max cached keys:</b>{this.state.data.readable.max_cached_keys}</p>
                    </div>
                </div>
            );
        }
    });

    var GeneralInfo = React.createClass({
        getInitialState: function() {
            return {
                version : opstate.version,
                start : opstate.overview.readable.start_time,
                reset : opstate.overview.readable.last_restart_time,
            };
        },
        render: function() {
            return (
                <table>
                    <thead>
                        <tr><th colSpan="2">General info</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Zend OPcache</td><td>{this.state.version.version}</td></tr>
                        <tr><td>PHP</td><td>{this.state.version.php}</td></tr>
                        <tr><td>Host</td><td>{this.state.version.host}</td></tr>
                        <tr><td>Server Software</td><td>{this.state.version.server}</td></tr>
                        <tr><td>Start time</td><td>{this.state.start}</td></tr>
                        <tr><td>Last reset</td><td>{this.state.reset}</td></tr>
                    </tbody>
                </table>
            );
        }
    });

    var Directives = React.createClass({
        getInitialState: function() {
            return { data : opstate.directives };
        },
        render: function() {
            var directiveNodes = this.state.data.map(function(directive) {
                var map = { 'opcache.':'', '_':' ' };
                var dShow = directive.k.replace(/opcache\.|_/gi, function(matched){
                    return map[matched];
                });
                var vShow;
                if (directive.v === true || directive.v === false) {
                    vShow = React.createElement('i', {}, directive.v.toString());
                } else if (directive.v == '') {
                    vShow = React.createElement('i', {}, 'no value');
                } else {
                    vShow = directive.v;
                }
                return (
                    <tr>
                        <td title={directive.k}>{dShow}</td>
                        <td>{vShow}</td>
                    </tr>
                );
            });
            return (
                <table>
                    <thead>
                        <tr><th colSpan="2">Directives</th></tr>
                    </thead>
                    <tbody>{directiveNodes}</tbody>
                </table>
            );
        }
    });

    var Files = React.createClass({
        getInitialState: function() {
            return {
                data : opstate.files,
                showing: null
            };
        },
        handleInvalidate: function(e) {
            e.preventDefault();
            if (realtime) {
                $.get('#', { invalidate: e.currentTarget.getAttribute('data-file') }, function(data) {
                    console.log('success: ' + data.success);
                }, 'json');
            } else {
                window.location.href = e.currentTarget.href;
            }
        },
        render: function() {
            var fileNodes = this.state.data.map(function(file) {
                var invalidated;
                if (file.timestamp == 0) {
                    invalidated = <span><i className="invalid metainfo">has been invalidated</i></span>;
                }
                var details = <span><b>hits: </b><span>{file.readable.hits}</span></span>;/* + file.readable.hits + ', memory: ' 
                    + file.readable.memory_consumption + ', last used: ' + file.last_used;*/
                return (
                    <tr>
                        <td>
                            <div>
                                <span className="pathname">{file.full_path}</span><br/>
                                <FilesMeta data={[file.readable.hits, file.readable.memory_consumption, file.last_used]} />
                                <?php if ($opcache->getOption('allow_invalidate') && function_exists('opcache_invalidate')): ?>
                                <span>,&nbsp;</span><a className="metainfo" href={'?invalidate=' + file.full_path} data-file={file.full_path} onClick={this.handleInvalidate}>force file invalidation</a>
                                <?php endif; ?>
                                {invalidated}
                            </div>
                        </td>
                    </tr>
                );
            }.bind(this));
            return (
                <div>
                    <FilesListed showing={this.state.showing} />
                    <table>
                        <thead><tr><th>Script</th></tr></thead>
                        <tbody>{fileNodes}</tbody>
                    </table>
                </div>
            );
        }
    });

    var FilesMeta = React.createClass({
        render: function() {
            return (
                <span className="metainfo">
                    <b>hits: </b><span>{this.props.data[0]}, </span>
                    <b>memory: </b><span>{this.props.data[1]}, </span>
                    <b>last used: </b><span>{this.props.data[2]}</span>
                </span>
            );
        }
    });

    var FilesListed = React.createClass({
        getInitialState: function() {
            return {
                formatted : opstate.overview.readable.num_cached_scripts,
                total     : opstate.overview.num_cached_scripts
            };
        },
        render: function() {
            var display = this.state.formatted + ' file' + (this.state.total == 1 ? '' : 's') + ' cached';
            if (this.props.showing !== null && this.props.showing != 0 && this.props.showing != this.state.total) {
                display += ', ' + this.props.showing + ' showing due to filter';
            }
            return (<h3>{display}</h3>);
        }
    });

    var overviewCountsObj = React.render(<OverviewCounts/>, document.getElementById('counts'));
    var generalInfoObj = React.render(<GeneralInfo/>, document.getElementById('generalInfo'));
    var filesObj = React.render(<Files/>, document.getElementById('filelist'));
    React.render(<Directives/>, document.getElementById('directives'));
</script>

</body>
</html>
