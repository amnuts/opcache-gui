<?php

if (!function_exists('opcache_get_status')) {
    die('Zend OPcache does not appear to be running');
}

$settings = array(
    'compress_path_threshold' => 2
);

$opcache_config = opcache_get_configuration();
$opcache_status = opcache_get_status();

uasort($opcache_status['scripts'], function($a, $b) {
    return $a['hits'] < $b['hits'];
});

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
        echo (++$i % 2 ? 'odd' : 'even');
    }
}

$highlightValueMemoryUsage = round(100 * ($opcache_status['memory_usage']['used_memory'] / $opcache_status['memory_usage']['free_memory']));
$highlightValueHits = round($opcache_status['opcache_statistics']['opcache_hit_rate']);
$threshold = '';
if ($highlightValueMemoryUsage >= 80) {
    $threshold = ' high';
} elseif ($threshold >= 60) {
    $threshold = ' mid';
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) {
    $data = array(
        'memory_usage'      => "{$highlightValueMemoryUsage}%",
        'hit_rate'          => "{$highlightValueHits}%",
        'used_memory'       => memsize($opcache_status['memory_usage']['used_memory']),
        'free_memory'       => memsize($opcache_status['memory_usage']['free_memory']),
        'wasted_memory'     => memsize($opcache_status['memory_usage']['wasted_memory']),
        'wasted_percentage' => round($opcache_status['memory_usage']['current_wasted_percentage'], 2) . '%'
    );
    echo json_encode($data);
    exit;
}

?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
    <script src="http://code.jquery.com/ui/1.10.2/jquery-ui.js"></script>
    <link href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" rel="stylesheet" type="text/css">
    <link href="http://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" type="text/css">
    <style type="text/css">
        html,body,div,span,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,abbr,address,cite,code,del,dfn,em,img,ins,kbd,q,samp,small,strong,sub,sup,var,b,i,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td,article,aside,canvas,details,figcaption,figure,footer,header,hgroup,menu,nav,section,summary,time,mark,audio,video{margin:0;padding:0;border:0;outline:0;font-size:100%;vertical-align:baseline;background:transparent}body{line-height:1}article,aside,details,figcaption,figure,footer,header,hgroup,menu,nav,section{display:block}nav ul{list-style:none}blockquote,q{quotes:none}blockquote:before,blockquote:after,q:before,q:after{content:none}a{margin:0;padding:0;font-size:100%;vertical-align:baseline;background:transparent}ins{background-color:#ff9;color:#000;text-decoration:none}mark{background-color:#ff9;color:#000;font-style:italic;font-weight:bold}del{text-decoration:line-through}abbr[title],dfn[title]{border-bottom:1px dotted;cursor:help}table{border-collapse:collapse;border-spacing:0}hr{display:block;height:1px;border:0;border-top:1px solid #ccc;margin:1em 0;padding:0}input,select{vertical-align:middle}
        html{font-size:70%;line-height:1.2;padding:2em;}
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
        table {
            margin: 0 0 1em 0;
            border-collapse: collapse;
            border-color: #fff;
            width: 100%;
        }
        table caption {
            text-align: left;
            font-size: 1.5em;
        }        
        table tr {
            background-color: #99D0DF;
            border-color: #fff;
        }
        table th {
            text-align: left;
            padding: 6px;
            background-color: #0BA0C8;
            color: #fff;
            border-color: #fff;
        }
        table td {
            padding: 4px 6px;
            line-height: 1.4em;
            vertical-align: top;
            border-color: #fff;
        }
        table tr.odd       { background-color: #EFFEFF; }
        table tr.even      { background-color: #E0ECEF; }
        table tr.highlight { background-color: #61C4DF; }
        .wsnw { white-space: nowrap; }
        .low{color:#000000;}
        .mid{color:#550000;}
        .high{color:#FF0000;}
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
        span.showmore span.button:hover {
            background-color: #CCCCCC;
        }
        @media screen and (max-width: 700px) {
            #info{margin-right:auto;}
            #counts{position:relative;display:block;margin-bottom:2em;width:100%;}
        }
    </style>
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
            $("#tabs").tabs();
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
        });
    </script>
</head>

<body>

<div id="tabs" style="margin-bottom:2em;">
    <ul>
        <li><a href="#tab-info">Information overview</a></li>
        <li><a href="#tab-file">File usage</a></li>
    </ul>
    <div id="tab-info">
        <div class="container">
            <div id="counts">
                <div>
                    <p><span class="large realtime <?php echo $threshold; ?>" data-value="memory_usage"><?php echo $highlightValueMemoryUsage; ?>%</span> memory usage</p>
                </div>
                <div>
                    <p><span class="large realtime" data-value="hit_rate"><?php echo $highlightValueHits; ?>%</span> hit rate</p>
                </div>
                <div class="values">
                    <p><b>used memory:</b> <span class="realtime" data-value="used_memory"><?php echo memsize($opcache_status['memory_usage']['used_memory']); ?></span></p>
                    <p><b>free memory:</b> <span class="realtime" data-value="free_memory"><?php echo memsize($opcache_status['memory_usage']['free_memory']); ?></span></p>
                    <p><b>wasted memory:</b> <span class="realtime" data-value="wasted_memory"><?php echo memsize($opcache_status['memory_usage']['wasted_memory']); ?></span> (<span class="realtime" data-value="wasted_percentage"><?php echo round($opcache_status['memory_usage']['current_wasted_percentage'], 2); ?>%</span>)</p>
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
                    <?php if (!empty($_SERVER['SERVER_NAME'])): ?>
                    <tr class="<?php rc(); ?>">    
                        <td>Host</td>
                        <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($_SERVER['SERVER_SOFTWARE'])): ?>
                    <tr class="<?php rc(); ?>">
                        <td>Server Software</td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="<?php rc(); ?>">
                        <td>Start time</td>
                        <td><?php echo date_format(date_create("@{$opcache_status['opcache_statistics']['start_time']}"), 'Y-m-d H:i:s'); ?></td>
                    </tr>
                    <tr class="<?php rc(); ?>">
                        <td>Last reset</td>
                        <td><?php echo ($opcache_status['opcache_statistics']['last_restart_time'] == 0
                                ? '<em>never</em>'
                                : date_format(date_create("@{$opcache_status['opcache_statistics']['last_restart_time']}"), 'Y-m-d H:i:s')); ?></td>
                    </tr>
                </table>
                
                <table>
                    <tr><th colspan="2">Directives</th></tr>
                    <?php rc(0); foreach ($opcache_config['directives'] as $d => $v): ?>
                    <tr class="<?php rc(); ?>">
                        <td><span title="<?php echo $d; ?>"><?php echo str_replace(array('opcache.', '_'), array('', ' '), $d); ?></span></td>
                        <td><?php echo (is_bool($v)
                            ? ($v ? '<i>true</i>' : '<i>false</i>')
                            : $v); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <br style="clear:both;" />
            </div>
        </div>
    </div>
    <div id="tab-file">
        <div class="container">
            <table>
            <tr>
                <th>Script</th>
                <th>Details</th>
            </tr>
            <?php rc(0); foreach ($opcache_status['scripts'] as $s): ?>
            <tr class="<?php rc(); ?>">
                <td><?php 
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
                    ?>
                </td>
                <td>
                    <p>
                        hits: <?php echo $s['hits']; ?>, 
                        memory: <?php echo memsize($s['memory_consumption']); ?><br />
                        last used: <?php echo date_format(date_create($s['last_used']), 'Y-m-d H:i:s'); ?>
                    </p>
                </td>
            </tr>
            <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

</body>
</html>
