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

$settings = array(
    'compress_path_threshold' => 2,
    'used_memory_percentage_high_threshold' => 80,
    'used_memory_percentage_mid_threshold' => 60,
    'allow_invalidate' => true
);

class OpCacheService
{
    protected $config;
    protected $status;
    protected $functions;

    private function __construct()
    {
        $this->data = $this->compileState();
    }

    public static function init()
    {
        $self = new self;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            echo json_encode($self->getData(@$_GET['section'] ?: null));
            exit;
        } else if ((isset($_GET['reset']))) {
            $self->resetCache();
        } else if ((isset($_GET['invalidate']))) {
            $self->resetCache($_GET['invalidate']);
        }
        return $self;
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

        $status = opcache_get_status();
        if (!empty($status['scripts'])) {
            uasort($status['scripts'], function($a, $b) {
                return $a['hits'] < $b['hits'];
            });
            $this->data['files'] = $status['scripts'];
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
            'files'      => $status['scripts'],
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
        body {
            font-family:sans-serif;
            font-size:100%;
            padding: 2em;
        }
        .container{overflow:auto;width:100%;position:relative;}
        #info{margin-right:290px;}
        #counts{position:absolute;top:0;right:0;width:280px;}
        #counts > div {
            padding:0;
            margin-bottom:1em;
            background-color: #efefef;
        }
        #counts > div > h3 {
            background-color: #dedede;
            padding: 7px;
            font-size: 100%;
            margin: 0;
        }
        #counts div.values { padding: 10px; }
        #counts p.large {
            font-family:'Roboto',sans-serif;
            line-height:90%;
            font-size:800%;
            text-align:center;
            margin: 20px 0;
        }
        #counts p.large > span { font-size: 50%; }

        table { margin: 0 0 1em 0; border-collapse: collapse; border-color: #fff; width: 100%; }
        table caption { text-align: left; font-size: 1.5em; }
        table tr { background-color: #99D0DF; border-color: #fff; }
        table th { text-align: left; padding: 6px; background-color: #0BA0C8; color: #fff; border-color: #fff; }
        table td { padding: 4px 6px; line-height: 1.4em; vertical-align: top; border-color: #fff; }
        table tr:nth-child(odd) { background-color: #EFFEFF; }
        table tr:nth-child(even) { background-color: #E0ECEF; }
        table tr.highlight { background-color: #61C4DF; }
        td.pathname p { margin-bottom: 0.25em; }
        .wsnw { white-space: nowrap; }
        .low{color:#000000;}
        .mid{color:#550000;}
        .high{color:#FF0000;}

        .invalid{color:#FF4545;}

        span.showmore span.button {
            display: inline-block;
            margin-right: 5px;
            position: relative;
            top: -1px;
            color: #333333;
            background: none repeat scroll 0 0 #DDDDDD;
            border-radius: 2px 2px 2px 2px;
            font-size: 12px;
            font-weight: bold;
            height: 12px;
            line-height: 6px;
            padding: 0 5px;
            vertical-align: middle;
            cursor: pointer;
        }
        a.button {
            text-decoration: none;
            font-size: 110%;
            color: #292929;
            padding: 10px 26px;
            background: -moz-linear-gradient(top, #ffffff 0%, #b4b7b8);
            background: -webkit-gradient(linear, left top, left bottom, from(#ffffff), to(#b4b7b8));
            -moz-border-radius: 6px;
            -webkit-border-radius: 6px;
            border-radius: 6px;
            border: 1px solid #a1a1a1;
            text-shadow: 0px -1px 0px rgba(000,000,000,0), 0px 1px 0px rgba(255,255,255,0.4);
            margin: 0 1em;
            white-space: nowrap;
        }
        span.showmore span.button:hover {
            background-color: #CCCCCC;
        }

        @media screen and (max-width: 700px) {
            #info{margin-right:auto;}
            #counts{position:relative;display:block;margin-bottom:2em;width:100%;}
        }
        @media screen and (max-width: 550px) {
            a.button{display:block;margin-bottom:2px;}
            #frmFilter{width:99% !important;}
        }
    </style>
</head>

<body>

<nav>
    <ul>
        <li><a data-for="overview" href="#overview" class="active">Overview</a></li>
        <li><a data-for="files" href="#files">File usage</a></li>
        <li><a data-for="reset" href="?reset=1" onclick="return confirm('Are you sure you want to reset the cache?');">Reset cache</a></li>
    </ul>
</nav>

<div id="overview">
    <h2>Overview</h2>
    <div class="container">
        <div id="counts"></div>
        <div id="info">
            <div id="generalInfo"></div>
            <div id="directives"></div>
            <div id="functions"></div>
            <br style="clear:both;" />
        </div>
    </div>
</div>

<div id="files">
    <h2>File usage</h2>
    <p><label>Start typing to filter on script path<br/><input type="text" style="width:40em;" name="filter" id="frmFilter" /><label></p>
    <div class="container">
        <h3><?php echo $data['readable']['num_cached_scripts']; ?> file<?php echo ($data['readable']['num_cached_scripts'] == 1 ? '' : 's'); ?> cached <span id="filterShowing"></span></h3>
        <table>
            <thead>
                <tr>
                    <th>Script</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php $files = $opcache->getData('files'); ?>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td class="pathname">
                        <p><?php
                            $base  = basename($f['full_path']);
                            $parts = array_filter(explode(DIRECTORY_SEPARATOR, dirname($f['full_path'])));
                            if (!empty($settings['compress_path_threshold'])) {
                                echo '<span class="showmore"><span class="button">…</span><span class="text" style="display:none;">' . DIRECTORY_SEPARATOR;
                                echo join(DIRECTORY_SEPARATOR, array_slice($parts, 0, $settings['compress_path_threshold'])) . DIRECTORY_SEPARATOR;
                                echo '</span>';
                                echo join(DIRECTORY_SEPARATOR, array_slice($parts, $settings['compress_path_threshold']));
                                if (count($parts) > $settings['compress_path_threshold']) {
                                    echo DIRECTORY_SEPARATOR;
                                }
                                echo "{$base}</span>";
                            } else {
                                echo htmlentities($f['full_path'], ENT_COMPAT, 'UTF-8');
                            }
                        ?></p>
                        <?php if ($settings['allow_invalidate'] && function_exists('opcache_invalidate')): ?>
                            <a href="?invalidate=<?php echo urlencode($f['full_path']); ?>">Force file invalidation</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <p>
                            hits: <?php echo $f['hits']; ?>,
                            memory: <?php echo $f['memory_consumption']; ?><br />
                            last used: <?php echo date_format(date_create($f['last_used']), 'Y-m-d H:i:s'); ?>
                            <?php if ($f['timestamp'] === 0): ?>
                                <br /><i class="invalid">has been invalidated</i>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

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
                        <p className="large">{this.state.data.used_memory_percentage}</p>
                    </div>
                    <div>
                        <h3>hit rate</h3>
                        <p className="large">{this.state.data.hit_rate_percentage}</p>
                    </div>
                    <div>
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
                        <td title="{directive.k}">{dShow}</td>
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

    var Functions = React.createClass({
        getInitialState: function() {
            return { data : opstate.functions };
        },
        render: function() {
            var functionNodes = this.state.data.map(function(func) {
                return (
                    <tr>
                        <td><a href="http://php.net/{func}" title="View manual page" target="_blank">{func}</a></td>
                    </tr>
                );
            });
            return (
                <table>
                    <thead>
                    <tr><th>Available functions</th></tr>
                    </thead>
                    <tbody>{functionNodes}</tbody>
                </table>
            );
        }
    });

    React.render(<OverviewCounts/>, document.getElementById('counts'));
    React.render(<GeneralInfo/>, document.getElementById('generalInfo'));
    React.render(<Directives/>, document.getElementById('directives'));
    React.render(<Functions/>, document.getElementById('functions'));
</script>

<script type="text/javascript">
    $(function(){
        var realtime = false;
        function ping() {
            $.ajax({
                url: "#",
                dataType: "json",
                cache: false,
                success: function(data){
                    $('.realtime').each(function(){
                        $(this).text(data[$(this).attr('data-value')]);
                    });
                }
            });
        }
        $('#toggleRealtime').click(function(){
            if (realtime === false) {
                realtime = setInterval(function(){ping()}, 5000);
                $(this).text('Disable real-time update of stats');
            } else {
                clearInterval(realtime);
                realtime = false;
                $(this).text('Enable real-time update of stats');
            }
        });
        $('span.showmore span.button').click(function(){
            if ($(this).next().is(":visible")) {
                $(this).next().hide();
                $(this).css('padding-top', '0').text('…');
            } else {
                $(this).next().show();
                $(this).css('padding-top', '2px').text('«');
            }
        });
        $('.container table').bind('paint', function(event, params) {
            var trs = $('tr:visible', $(this)).not(':first');
            trs.removeClass('odd even')
                .filter(':odd').addClass('odd')
                .end()
                .filter(':even').addClass('even');
            $('#filterShowing').text(($('#frmFilter').val().length
                ? trs.length + ' showing due to filter'
                : ''
            ));
        });
        $('#frmFilter').bind('keyup', function(event){
            $('td.pathname p').each(function(index){
                if ($(this).text().toLowerCase().indexOf($('#frmFilter').val().toLowerCase()) == -1) {
                    $(this).closest('tr').hide();
                } else {
                    $(this).closest('tr').show();
                }
            });
            $('.container table').trigger('paint');
        });
    });
</script>

</body>
</html>
