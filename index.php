<?php

/**
 * OPcache GUI
 * 
 * A simple but effective single-file GUI for the OPcache PHP extension.
 * 
 * @author Andrew Collington, andy@amnuts.com
 * @license MIT, http://acollington.mit-license.org/
 */

if (!function_exists('opcache_get_status')) {
    die('The Zend OPcache extension does not appear to be installed');
}

$settings = array(
    'compress_path_threshold' => 2,
    'used_memory_percentage_high_threshold' => 80,
    'used_memory_percentage_mid_threshold' => 60,
    'allow_invalidate' => true
);


$validPages = array('overview', 'files', 'reset', 'invalidate');
$page = (empty($_GET['page']) || !in_array($_GET['page'], $validPages)
    ? 'overview'
    : strtolower($_GET['page'])
);

if ($page == 'reset') {
    opcache_reset();
    header('Location: ?page=overview');
    exit;
}

if ($page == 'invalidate') {
    $file = (isset($_GET['file']) ? trim($_GET['file']) : null);
    if (!$settings['allow_invalidate'] || !function_exists('opcache_invalidate') || empty($file)) {
        header('Location: ?page=files&error=1');
        exit;
    }
    $success = (int)opcache_invalidate(urldecode($file), true);
    header("Location: ?page=files&success={$success}");
    exit;
}

$opcache_config = opcache_get_configuration();
$opcache_status = opcache_get_status();
$opcache_funcs  = get_extension_funcs('Zend OPcache');

if (!empty($opcache_status['scripts'])) {
    uasort($opcache_status['scripts'], function($a, $b) {
        return $a['hits'] < $b['hits'];
    });
}

function memsize($size, $precision = 3, $space = false)
{
    $i = 0;
    $val = array(' bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    while (($size / 1024) > 1) {
        $size /= 1024;
        ++$i;
    }
    return sprintf("%.{$precision}f%s%s",
    $size, (($space && $i) ? ' ' : ''), $val[$i]);
}

function rc($at = null)
{
    static $i = 0;
    if ($at !== null) {
        $i = $at;
    } else {
        echo (++$i % 2 ? 'even' : 'odd');
    }
}

$data = array_merge(
    $opcache_status['memory_usage'],
    $opcache_status['opcache_statistics'],
    array(
        'total_memory_size'       => memsize($opcache_config['directives']['opcache.memory_consumption']),
        'used_memory_percentage'  => round(100 * (
            ($opcache_status['memory_usage']['used_memory'] + $opcache_status['memory_usage']['wasted_memory']) 
                / $opcache_config['directives']['opcache.memory_consumption'])),
        'hit_rate_percentage'     => round($opcache_status['opcache_statistics']['opcache_hit_rate']),
        'wasted_percentage'       => round($opcache_status['memory_usage']['current_wasted_percentage'], 2),
        'used_memory_size'        => memsize($opcache_status['memory_usage']['used_memory']),
        'free_memory_size'        => memsize($opcache_status['memory_usage']['free_memory']),
        'wasted_memory_size'      => memsize($opcache_status['memory_usage']['wasted_memory']),
        'files_cached'            => number_format($opcache_status['opcache_statistics']['num_cached_scripts']),
        'hits_size'               => number_format($opcache_status['opcache_statistics']['hits']),
        'miss_size'               => number_format($opcache_status['opcache_statistics']['misses']),
        'blacklist_miss_size'     => number_format($opcache_status['opcache_statistics']['blacklist_misses']),
        'num_cached_keys_size'    => number_format($opcache_status['opcache_statistics']['num_cached_keys']),
        'max_cached_keys_size'    => number_format($opcache_status['opcache_statistics']['max_cached_keys']),
    )
);

$threshold = '';
if ($data['used_memory_percentage'] >= $settings['used_memory_percentage_high_threshold']) {
    $threshold = ' high';
} elseif ($data['used_memory_percentage'] >= $settings['used_memory_percentage_mid_threshold']) {
    $threshold = ' mid';
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) {
    echo json_encode($data);
    exit;
}

$host = (function_exists('gethostname')
    ? gethostname()
    : (php_uname('n')
        ?: (empty($_SERVER['SERVER_NAME'])
            ? $_SERVER['HOST_NAME']
            : $_SERVER['SERVER_NAME']
        )
    )
);

?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
    <link href="//fonts.googleapis.com/css?family=Roboto" rel="stylesheet" type="text/css">
    <style type="text/css">
        html{font-family:sans-serif;font-size:100%;line-height:1.2;padding:2em;}
        body {font-size:75%;}
        .container{overflow:auto;width:100%;position:relative;}
        #info{margin-right:290px;}
        #counts{position:absolute;top:0;right:0;width:280px;}
        #counts div{
            padding:1em;
            margin-bottom:1em;
            border-radius: 5px;
            background-image: linear-gradient(bottom, #B7C8CC 21%, #D5DEE0 60%, #E0ECEF 80%);
            background-image: -webkit-gradient(linear,left bottom,left top,color-stop(0.21, #B7C8CC),color-stop(0.6, #D5DEE0),color-stop(0.8, #E0ECEF));
        }
        #counts p {text-align:center;}
        #counts div.values p {text-align:left;}
        #counts p span{font-family:'Roboto',sans-serif;}
        #counts p span.large{display:block;line-height:90%;font-size:800%;}   
        table { margin: 0 0 1em 0; border-collapse: collapse; border-color: #fff; width: 100%; }
        table caption { text-align: left; font-size: 1.5em; }        
        table tr { background-color: #99D0DF; border-color: #fff; }
        table th { text-align: left; padding: 6px; background-color: #0BA0C8; color: #fff; border-color: #fff; }
        table td { padding: 4px 6px; line-height: 1.4em; vertical-align: top; border-color: #fff; }
        table tr.odd { background-color: #EFFEFF; }
        table tr.even { background-color: #E0ECEF; }
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

    <div style="text-align:center;margin-bottom:2em;">
        <p>
            <a href="?page=overview" class="button">Overview</a>
            <a href="?page=files" class="button">File usage</a>
            <a href="?page=reset" class="button" onclick="return confirm('Are you sure you want to reset the cache?');">Reset cache</a>
        </p>
    </div>

    <?php if ($page == 'overview'): ?>
    <h2>Overview</h2>
    <div class="container">
        <div id="counts">
            <div>
                <p><span class="large <?php echo $threshold; ?>"><span class="realtime" data-value="used_memory_percentage"><?php echo $data['used_memory_percentage']; ?></span>%</span><br/>memory usage</p>
            </div>
            <div>
                <p><span class="large"><span class="realtime" data-value="hit_rate"><?php echo $data['hit_rate_percentage']; ?></span>%</span><br/>hit rate</p>
            </div>
            <div class="values">
                <p><b>total memory:</b> <span data-value="total_memory_size"><?php echo $data['total_memory_size']; ?></span></p>
                <p><b>used memory:</b> <span class="realtime" data-value="used_memory_size"><?php echo $data['used_memory_size']; ?></span></p>
                <p><b>free memory:</b> <span class="realtime" data-value="free_memory_size"><?php echo $data['free_memory_size']; ?></span></p>
                <p><b>wasted memory:</b> <span class="realtime" data-value="wasted_memory_size"><?php echo $data['wasted_memory_size']; ?></span> (<span class="realtime" data-value="wasted_percentage"><?php echo $data['wasted_percentage']; ?></span>%)</p>
                <p><b>number of cached files:</b> <span class="realtime" data-value="files_cached"><?php echo $data['files_cached']; ?></span></p>
                <p><b>number of hits:</b> <span class="realtime" data-value="hits_size"><?php echo $data['hits_size']; ?></span></p>
                <p><b>number of misses:</b> <span class="realtime" data-value="miss_size"><?php echo $data['miss_size']; ?></span></p>
                <p><b>blacklist misses:</b> <span class="realtime" data-value="blacklist_miss_size"><?php echo $data['blacklist_miss_size']; ?></span></p>
                <p><b>number of cached keys:</b> <span class="realtime" data-value="num_cached_keys_size"><?php echo $data['num_cached_keys_size']; ?></span></p>
                <p><b>max cached keys:</b> <span class="realtime" data-value="max_cached_keys_size"><?php echo $data['max_cached_keys_size']; ?></span></p>
            </div>
            <br />
            <p><a href="#" id="toggleRealtime">Enable real-time update of stats</a></p>
        </div>
        <div id="info">
        
            <table>
                <tr><th colspan="2">General info</th></tr>
                <tr class="<?php rc(); ?>">
                    <td>Zend OPcache</td>
                    <td><?php echo $opcache_config['version']['version']; ?></td>
                </tr>
                <tr class="<?php rc(); ?>">
                    <td>PHP</td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr class="<?php rc(); ?>">    
                    <td>Host</td>
                    <td><?php echo $host; ?></td>
                </tr>
                <?php if (!empty($_SERVER['SERVER_SOFTWARE'])): ?>
                <tr class="<?php rc(); ?>">
                    <td>Server Software</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                </tr>
                <?php endif; ?>
                <tr class="<?php rc(); ?>">
                    <td>Start time</td>
                    <td><?php echo date_format(date_create("@{$data['start_time']}"), 'Y-m-d H:i:s'); ?></td>
                </tr>
                <tr class="<?php rc(); ?>">
                    <td>Last reset</td>
                    <td><?php echo ($data['last_restart_time'] == 0
                            ? '<em>never</em>'
                            : date_format(date_create("@{$data['last_restart_time']}"), 'Y-m-d H:i:s')); ?></td>
                </tr>
            </table>
            
            <table>
                <tr><th colspan="2">Directives</th></tr>
                <?php ksort($opcache_config['directives']); ?>
                <?php rc(0); foreach ($opcache_config['directives'] as $d => $v): ?>
                <tr class="<?php rc(); ?>">
                    <td><span title="<?php echo $d; ?>"><?php echo str_replace(array('opcache.', '_'), array('', ' '), $d); ?></span></td>
                    <td><?php echo (is_bool($v)
                        ? ($v ? '<i>true</i>' : '<i>false</i>')
                        : (empty($v) ? '<i>no value</i>' : $v)); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <table>
                <tr><th>Available functions</th></tr>
                <?php rc(0); foreach ($opcache_funcs as $f): ?>
                <tr class="<?php rc(); ?>">
                    <td><a href="http://php.net/<?php echo $f; ?>" title="View manual page" target="_blank"><?php echo $f; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <br style="clear:both;" />
        </div>
    </div>
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
        });
    </script>
    <?php endif; ?>

    <?php if ($page == 'files'): ?>
    <h2>File usage</h2>
    <p><label>Start typing to filter on script path<br/><input type="text" style="width:40em;" name="filter" id="frmFilter" /><label></p>
    <div class="container">
        <h3><?php echo $data['files_cached']; ?> file<?php echo ($data['files_cached'] == 1 ? '' : 's'); ?> cached <span id="filterShowing"></span></h3>
        <table>
        <tr>
            <th>Script</th>
            <th>Details</th>
        </tr>
        <?php rc(0); foreach ($opcache_status['scripts'] as $s): ?>
        <tr class="<?php rc(); ?>">
            <td class="pathname"><p><?php 
                $base  = basename($s['full_path']);
                $parts = array_filter(explode(DIRECTORY_SEPARATOR, dirname($s['full_path'])));
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
                    echo htmlentities($s['full_path'], ENT_COMPAT, 'UTF-8');
                }
                ?></p>
                <?php if ($settings['allow_invalidate'] && function_exists('opcache_invalidate')): ?>
                <a href="?page=invalidate&file=<?php echo urlencode($s['full_path']); ?>">Force file invalidation</a>
                <?php endif; ?>
            </td>
            <td>
                <p>
                    hits: <?php echo $s['hits']; ?>, 
                    memory: <?php echo memsize($s['memory_consumption']); ?><br />
                    last used: <?php echo date_format(date_create($s['last_used']), 'Y-m-d H:i:s'); ?>
                    <?php if ($s['timestamp'] === 0): ?>
                    <br /><i class="invalid">has been invalidated</i>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div>
    <script type="text/javascript">
        $(function(){
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
    <?php endif; ?>


</body>
</html>
