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

class Service
{
    const VERSION = '3.0.0';

    protected $data;
    protected $options;
    protected $optimizationLevels;
    protected $defaults = [
        'allow_filelist' => true,
        'allow_invalidate' => true,
        'allow_reset' => true,
        'allow_realtime' => true,
        'refresh_time' => 5,
        'size_precision' => 2,
        'size_space' => false,
        'charts' => true,
        'debounce_rate' => 250,
        'cookie_name' => 'opcachegui',
        'cookie_ttl' => 365
    ];

    /**
     * Service constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->optimizationLevels = [
            1 << 0 => 'CSE, STRING construction',
            1 << 1 => 'Constant conversion and jumps',
            1 << 2 => '++, +=, series of jumps',
            1 << 3 => 'INIT_FCALL_BY_NAME -> DO_FCALL',
            1 << 4 => 'CFG based optimization',
            1 << 5 => 'DFA based optimization',
            1 << 6 => 'CALL GRAPH optimization',
            1 << 7 => 'SCCP (constant propagation)',
            1 << 8 => 'TMP VAR usage',
            1 << 9 => 'NOP removal',
            1 << 10 => 'Merge equal constants',
            1 << 11 => 'Adjust used stack',
            1 << 12 => 'Remove unused variables',
            1 << 13 => 'DCE (dead code elimination)',
            1 << 14 => '(unsafe) Collect constants',
            1 << 15 => 'Inline functions'
        ];

        $this->options = array_merge($this->defaults, $options);
        $this->data = $this->compileState();
    }

    /**
     * @return $this
     */
    public function handle(): Service
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            if (isset($_GET['reset']) && $this->getOption('allow_reset')) {
                echo '{ "success": "' . ($this->resetCache() ? 'yes' : 'no') . '" }';
            } else if (isset($_GET['invalidate']) && $this->getOption('allow_invalidate')) {
                echo '{ "success": "' . ($this->resetCache($_GET['invalidate']) ? 'yes' : 'no') . '" }';
            } else {
                echo json_encode($this->getData((empty($_GET['section']) ? null : $_GET['section'])));
            }
            exit;
        } else if (isset($_GET['reset']) && $this->getOption('allow_reset')) {
            $this->resetCache();
            header('Location: ?');
            exit;
        } else if (isset($_GET['invalidate']) && $this->getOption('allow_invalidate')) {
            $this->resetCache($_GET['invalidate']);
            header('Location: ?');
            exit;
        }

        return $this;
    }

    /**
     * @param string|null $name
     * @return array|mixed|null
     */
    public function getOption(?string $name = null)
    {
        if ($name === null) {
            return $this->options;
        }
        return (isset($this->options[$name])
            ? $this->options[$name]
            : null
        );
    }

    /**
     * @param string|null $section
     * @param string|null $property
     * @return array|mixed|null
     */
    public function getData(?string $section = null, ?string $property = null)
    {
        if ($section === null) {
            return $this->data;
        }
        $section = strtolower($section);
        if (isset($this->data[$section])) {
            if ($property === null || !isset($this->data[$section][$property])) {
                return $this->data[$section];
            }
            return $this->data[$section][$property];
        }
        return null;
    }

    /**
     * @return bool
     */
    public function canInvalidate(): bool
    {
        return ($this->getOption('allow_invalidate') && function_exists('opcache_invalidate'));
    }

    /**
     * @param string|null $file
     * @return bool
     */
    public function resetCache(?string $file = null): bool
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

    /**
     * @param mixed $size
     * @return string
     */
    protected function size($size): string
    {
        $i = 0;
        $val = ['b', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        while (($size / 1024) > 1) {
            $size /= 1024;
            ++$i;
        }
        return sprintf('%.' . $this->getOption('size_precision') . 'f%s%s',
            $size, ($this->getOption('size_space') ? ' ' : ''), $val[$i]
        );
    }

    /**
     * @return array
     */
    protected function compileState(): array
    {
        $status = opcache_get_status();
        $config = opcache_get_configuration();
        $missingConfig = array_diff_key(ini_get_all('zend opcache', false), $config['directives']);
        if (!empty($missingConfig)) {
            $config['directives'] = array_merge($config['directives'], $missingConfig);
        }

        $files = [];
        if (!empty($status['scripts']) && $this->getOption('allow_filelist')) {
            uasort($status['scripts'], function ($a, $b) {
                return $a['hits'] < $b['hits'];
            });
            foreach ($status['scripts'] as &$file) {
                $file['full_path'] = str_replace('\\', '/', $file['full_path']);
                $file['readable'] = [
                    'hits' => number_format($file['hits']),
                    'memory_consumption' => $this->size($file['memory_consumption'])
                ];
            }
            $files = array_values($status['scripts']);
        }

        if ($config['directives']['opcache.file_cache_only'] || !empty($status['file_cache_only'])) {
            $overview = false;
        } else {
            $overview = array_merge(
                $status['memory_usage'], $status['opcache_statistics'], [
                    'used_memory_percentage' => round(100 * (
                        ($status['memory_usage']['used_memory'] + $status['memory_usage']['wasted_memory'])
                        / $config['directives']['opcache.memory_consumption']
                    )),
                    'hit_rate_percentage' => round($status['opcache_statistics']['opcache_hit_rate']),
                    'used_key_percentage' => round(100 * (
                        $status['opcache_statistics']['num_cached_keys']
                        / $status['opcache_statistics']['max_cached_keys']
                    )),
                    'wasted_percentage' => round($status['memory_usage']['current_wasted_percentage'], 2),
                    'readable' => [
                        'total_memory' => $this->size($config['directives']['opcache.memory_consumption']),
                        'used_memory' => $this->size($status['memory_usage']['used_memory']),
                        'free_memory' => $this->size($status['memory_usage']['free_memory']),
                        'wasted_memory' => $this->size($status['memory_usage']['wasted_memory']),
                        'num_cached_scripts' => number_format($status['opcache_statistics']['num_cached_scripts']),
                        'hits' => number_format($status['opcache_statistics']['hits']),
                        'misses' => number_format($status['opcache_statistics']['misses']),
                        'blacklist_miss' => number_format($status['opcache_statistics']['blacklist_misses']),
                        'num_cached_keys' => number_format($status['opcache_statistics']['num_cached_keys']),
                        'max_cached_keys' => number_format($status['opcache_statistics']['max_cached_keys']),
                        'interned' => null,
                        'start_time' => date('Y-m-d H:i:s', $status['opcache_statistics']['start_time']),
                        'last_restart_time' => ($status['opcache_statistics']['last_restart_time'] == 0
                            ? 'never'
                            : date('Y-m-d H:i:s', $status['opcache_statistics']['last_restart_time'])
                        )
                    ]
                ]
            );
        }

        if (!empty($status['interned_strings_usage'])) {
            $overview['readable']['interned'] = [
                'buffer_size' => $this->size($status['interned_strings_usage']['buffer_size']),
                'strings_used_memory' => $this->size($status['interned_strings_usage']['used_memory']),
                'strings_free_memory' => $this->size($status['interned_strings_usage']['free_memory']),
                'number_of_strings' => number_format($status['interned_strings_usage']['number_of_strings'])
            ];
        }

        $directives = [];
        ksort($config['directives']);
        foreach ($config['directives'] as $k => $v) {
            if (in_array($k, ['opcache.max_file_size', 'opcache.memory_consumption']) && $v) {
                $v = $this->size($v) . " ({$v})";
            } elseif ($k == 'opcache.optimization_level') {
                $levels = [];
                foreach ($this->optimizationLevels as $level => $info) {
                    if ($level & $v) {
                        $levels[] = $info;
                    }
                }
                $v = $levels ?: 'none';
            }
            $directives[] = [
                'k' => $k,
                'v' => $v
            ];
        }

        $version = array_merge(
            $config['version'],
            [
                'php' => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?: '',
                'host' => (function_exists('gethostname')
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
            'version' => $version,
            'overview' => $overview,
            'files' => $files,
            'directives' => $directives,
            'blacklist' => $config['blacklist'],
            'functions' => get_extension_funcs('Zend OPcache')
        ];
    }
}

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
        .opcache-gui{font-family:sans-serif;font-size:90%;padding:0;margin:0}.opcache-gui .hide{display:none}.opcache-gui .main-nav{padding-top:20px}.opcache-gui .nav-tab-list{list-style-type:none;padding-left:8px;margin:0;border-bottom:1px solid #ccc}.opcache-gui .nav-tab{display:inline-block;padding:0;margin:0 0 -1px 0}.opcache-gui .nav-tab-link{display:block;margin:0 10px;padding:15px 30px;border:1px solid transparent;border-bottom-color:#ccc;text-decoration:none}.opcache-gui .nav-tab-link:hover{background-color:#f4f4f4;text-decoration:underline}.opcache-gui .nav-tab-link.active:hover{background-color:initial}.opcache-gui .nav-tab-link[data-for].active{border:1px solid #ccc;border-bottom-color:#ffffff;border-top:3px solid #6ca6ef}.opcache-gui .nav-tab-link-reset{background-image:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NjBFMUMyMjI3NDlGMTFFNEE3QzNGNjQ0OEFDQzQ1MkMiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NjBFMUMyMjM3NDlGMTFFNEE3QzNGNjQ0OEFDQzQ1MkMiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo2MEUxQzIyMDc0OUYxMUU0QTdDM0Y2NDQ4QUNDNDUyQyIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo2MEUxQzIyMTc0OUYxMUU0QTdDM0Y2NDQ4QUNDNDUyQyIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PplZ+ZkAAAD1SURBVHjazFPtDYJADIUJZAMZ4UbACWQENjBO4Ao6AW5AnODcADZQJwAnwJ55NbWhB/6zycsdpX39uDZNpsURtjgzwkDoCBecs5ITPGGMwCNAkIrQw+8ri36GhBHsavFdpILEo4wEpZxRigy009EhG760gr0VhFoyZfvJKPwsheIWIeGejBZRIxRVhMRFevbuUXBew/iE/lhlBduV0j8Jx+TvJEWPphq8n5li9utgaw6cW/h6NSt/JcnVBhQxotIgKTBrbNvIHo2G0x1rwlKqTDusxiAz6hHNL1zayTVqVKRKpa/LPljPH1sJh6l/oNSrZfwSYABtq3tFdZA5BAAAAABJRU5ErkJggg==")}.opcache-gui .nav-tab-link-realtime{position:relative;background-image:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAAUCAYAAACAl21KAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6ODE5RUU4NUE3NDlGMTFFNDkyMzA4QzY1RjRBQkIzQjUiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6ODE5RUU4NUI3NDlGMTFFNDkyMzA4QzY1RjRBQkIzQjUiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo4MTlFRTg1ODc0OUYxMUU0OTIzMDhDNjVGNEFCQjNCNSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo4MTlFRTg1OTc0OUYxMUU0OTIzMDhDNjVGNEFCQjNCNSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PpXjpvMAAAD2SURBVHjarFQBEcMgDKR3E1AJldA5wMEqAQmTgINqmILdFCChdUAdMAeMcukuSwnQbbnLlZLwJPkQIcrSiT/IGNQHNb8CGQDyRw+2QWUBqC+luzo4OKQZIAVrB+ssyKp3Bkijf0+ijzIh4wQppoBauMSjyDZfMSCDxYZMsfHF120T36AqWZMkgyguQ3GOfottJ5TKnHC+wfeRsC2oDVayPgr3bbN2tHBH3tWuJCPa0JUgKtFzMQrcZH3FNHAc0yOp1cCASALyngoN6lhDopkJWxdifwY9A3u7l29ImpxOFSWIOVsGwHKENIWxss2eBVKdOeeXAAMAk/Z9h4QhXmUAAAAASUVORK5CYII=")}.opcache-gui .nav-tab-link-realtime.pulse::before{content:"";position:absolute;top:13px;left:3px;width:18px;height:18px;z-index:10;opacity:0;background-color:transparent;border:2px solid #ff7400;border-radius:100%;animation:pulse 1s linear 2}.opcache-gui .tab-content-container{padding:2em}.opcache-gui .tab-content{display:none}.opcache-gui .tab-content-overview{display:block}.opcache-gui .tab-content-overview-counts{width:270px;float:right}.opcache-gui .tab-content-overview-info{margin-right:280px}.opcache-gui .graph-widget{display:block;max-width:100%;height:auto;margin:0 auto}.opcache-gui .widget-panel{background-color:#ededed;margin-bottom:10px}.opcache-gui .widget-header{background-color:#cdcdcd;padding:4px 6px;margin:0;text-align:center;font-size:1rem;font-weight:bold}.opcache-gui .widget-value{margin:0;text-align:center}.opcache-gui .widget-value span.large+span{font-size:20pt;margin:0;color:#6ca6ef}.opcache-gui .widget-value span.large span.large{color:#6ca6ef;font-size:80pt;margin:0;padding:0;text-align:center}.opcache-gui .widget-info{margin:0;padding:10px}.opcache-gui .widget-info *{margin:0;line-height:1.75em;text-align:left}.opcache-gui .tables{margin:0 0 1em 0;border-collapse:collapse;border-color:#fff;width:100%;table-layout:fixed}.opcache-gui .tables tr{background-color:#99D0DF;border-color:#fff}.opcache-gui .tables tr:nth-child(odd){background-color:#EFFEFF}.opcache-gui .tables tr:nth-child(even){background-color:#E0ECEF}.opcache-gui .tables th{text-align:left;padding:6px;background-color:#6ca6ef;color:#fff;border-color:#fff;font-weight:normal}.opcache-gui .tables td{padding:4px 6px;line-height:1.4em;vertical-align:top;border-color:#fff;overflow:hidden;overflow-wrap:break-word;text-overflow:ellipsis}.opcache-gui .tables.file-list-table tr{background-color:#EFFEFF}.opcache-gui .tables.file-list-table tr.alternate{background-color:#E0ECEF}.opcache-gui .file-filter{width:520px}.opcache-gui .file-metainfo{font-size:80%}.opcache-gui .file-pathname{width:70%;display:block}.opcache-gui .nav-tab-link-reset,.opcache-gui .nav-tab-link-realtime,.opcache-gui .github-link{background-position:5px 50%;background-repeat:no-repeat;background-color:transparent}.opcache-gui .main-footer{border-top:1px solid #ccc;padding:1em 2em}.opcache-gui .github-link{background-position:0 50%;padding:2em;text-decoration:none;opacity:0.7;background-image:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAAQCAYAAAAbBi9cAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjE2MENCRkExNzVBQjExRTQ5NDBGRTUzMzQyMDVDNzFFIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjE2MENCRkEyNzVBQjExRTQ5NDBGRTUzMzQyMDVDNzFFIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6MTYwQ0JGOUY3NUFCMTFFNDk0MEZFNTMzNDIwNUM3MUUiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6MTYwQ0JGQTA3NUFCMTFFNDk0MEZFNTMzNDIwNUM3MUUiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz7HtUU1AAABN0lEQVR42qyUvWoCQRSF77hCLLKC+FOlCKTyIbYQUuhbWPkSFnZ2NpabUvANLGyz5CkkYGMlFtFAUmiSM8lZOVkWsgm58K079+fMnTusZl92BXbgDrTtZ2szd8fas/XBOzmBKaiCEFyTkL4pc9L8vgpNJJDyWtDna61EoXpO+xcFfXUVqtrf7Vx7m9Pub/EatvgHoYXD4ylztC14BBVwydvydgDPHPgNaErN3jLKIxAUmEvAXK21I18SJpXBGAxyBAaMlblOWOs1bMXFkMGeBFsi0pJNe/QNuV7563+gs8LfhrRfE6GaHLuRqfnUiKi6lJ034B44EXL0baTTJWujNGkG3kBX5uRyZuRkPl3WzDTBtzjnxxiDDq83yNxUk7GYuXM53jeLuMNavvAXkv4zrJkTaeGHAAMAIal3icPMsyQAAAAASUVORK5CYII=");font-size:80%}.opcache-gui .github-link:hover{opacity:1}.opcache-gui .file-cache-only{margin-top:0}@media screen and (max-width: 750px){.opcache-gui .nav-tab-list{border-bottom:0}.opcache-gui .nav-tab{display:block;margin:0}.opcache-gui .nav-tab-link{display:block;margin:0 10px;padding:10px 0 10px 30px;border:0}.opcache-gui .nav-tab-link[data-for].active{border-bottom-color:#ccc}.opcache-gui .tab-content-overview-info{margin-right:auto;clear:both}.opcache-gui .tab-content-overview-counts{position:relative;display:block;width:100%}.opcache-gui .nav-tab-link-realtime.pulse::before{top:8px}}@media screen and (max-width: 550px){.opcache-gui .file-filter{width:100%}}@keyframes pulse{0%{transform:scale(1);opacity:0}50%{transform:scale(1.3);opacity:0.7}100%{transform:scale(1.6);opacity:1}}
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

    var UsageGraph = React.createClass({
  displayName: "UsageGraph",
  getInitialState: function () {
    return {
      gauge: null
    };
  },
  componentDidMount: function () {
    if (this.props.chart) {
      this.state.gauge = new Gauge('#' + this.props.gaugeId);
      this.state.gauge.setValue(this.props.value);
    }
  },
  componentDidUpdate: function () {
    if (this.state.gauge != null) {
      this.state.gauge.setValue(this.props.value);
    }
  },
  render: function () {
    if (this.props.chart == true) {
      return /*#__PURE__*/React.createElement("canvas", {
        id: this.props.gaugeId,
        className: "graph-widget",
        width: "250",
        height: "250",
        "data-value": this.props.value
      });
    }

    return /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("span", {
      className: "large"
    }, this.props.value), /*#__PURE__*/React.createElement("span", null, "%"));
  }
});
var MemoryUsagePanel = React.createClass({
  displayName: "MemoryUsagePanel",
  render: function () {
    return /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "memory usage"), /*#__PURE__*/React.createElement("div", {
      className: "widget-value widget-info"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "total memory:"), " ", this.props.total), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "used memory:"), " ", this.props.used), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "free memory:"), " ", this.props.free), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "wasted memory:"), " ", this.props.wasted, " (", this.props.wastedPercent, "%)")));
  }
});
var StatisticsPanel = React.createClass({
  displayName: "StatisticsPanel",
  render: function () {
    return /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "opcache statistics"), /*#__PURE__*/React.createElement("div", {
      className: "widget-value widget-info"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of cached files:"), " ", this.props.num_cached_scripts), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of hits:"), " ", this.props.hits), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of misses:"), " ", this.props.misses), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "blacklist misses:"), " ", this.props.blacklist_miss), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of cached keys:"), " ", this.props.num_cached_keys), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "max cached keys:"), " ", this.props.max_cached_keys)));
  }
});
var InternedStringsPanel = React.createClass({
  displayName: "InternedStringsPanel",
  render: function () {
    return /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "interned strings usage"), /*#__PURE__*/React.createElement("div", {
      className: "widget-value widget-info"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "buffer size:"), " ", this.props.buffer_size), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "used memory:"), " ", this.props.strings_used_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "free memory:"), " ", this.props.strings_free_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of strings:"), " ", this.props.number_of_strings)));
  }
});
var OverviewCounts = React.createClass({
  displayName: "OverviewCounts",
  getInitialState: function () {
    return {
      data: opstate.overview,
      chart: useCharts,
      highlight: highlight
    };
  },
  render: function () {
    if (this.state.data == false) {
      return /*#__PURE__*/React.createElement("p", {
        class: "file-cache-only"
      }, "You have ", /*#__PURE__*/React.createElement("i", null, "opcache.file_cache_only"), " turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by ", /*#__PURE__*/React.createElement("i", null, "opcache_get_statistics()"), ".");
    }

    var interned = this.state.data.readable.interned != null ? /*#__PURE__*/React.createElement(InternedStringsPanel, {
      buffer_size: this.state.data.readable.interned.buffer_size,
      strings_used_memory: this.state.data.readable.interned.strings_used_memory,
      strings_free_memory: this.state.data.readable.interned.strings_free_memory,
      number_of_strings: this.state.data.readable.interned.number_of_strings
    }) : '';
    var memoryHighlight = this.state.highlight.memory ? /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "memory"), /*#__PURE__*/React.createElement("p", {
      className: "widget-value"
    }, /*#__PURE__*/React.createElement(UsageGraph, {
      chart: this.state.chart,
      value: this.state.data.used_memory_percentage,
      gaugeId: "memoryUsageCanvas"
    }))) : null;
    var hitsHighlight = this.state.highlight.hits ? /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "hit rate"), /*#__PURE__*/React.createElement("p", {
      className: "widget-value"
    }, /*#__PURE__*/React.createElement(UsageGraph, {
      chart: this.state.chart,
      value: this.state.data.hit_rate_percentage,
      gaugeId: "hitRateCanvas"
    }))) : null;
    var keysHighlight = this.state.highlight.keys ? /*#__PURE__*/React.createElement("div", {
      className: "widget-panel"
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, "keys"), /*#__PURE__*/React.createElement("p", {
      className: "widget-value"
    }, /*#__PURE__*/React.createElement(UsageGraph, {
      chart: this.state.chart,
      value: this.state.data.used_key_percentage,
      gaugeId: "keyUsageCanvas"
    }))) : null;
    return /*#__PURE__*/React.createElement("div", null, memoryHighlight, hitsHighlight, keysHighlight, /*#__PURE__*/React.createElement(MemoryUsagePanel, {
      total: this.state.data.readable.total_memory,
      used: this.state.data.readable.used_memory,
      free: this.state.data.readable.free_memory,
      wasted: this.state.data.readable.wasted_memory,
      wastedPercent: this.state.data.wasted_percentage
    }), /*#__PURE__*/React.createElement(StatisticsPanel, {
      num_cached_scripts: this.state.data.readable.num_cached_scripts,
      hits: this.state.data.readable.hits,
      misses: this.state.data.readable.misses,
      blacklist_miss: this.state.data.readable.blacklist_miss,
      num_cached_keys: this.state.data.readable.num_cached_keys,
      max_cached_keys: this.state.data.readable.max_cached_keys
    }), interned);
  }
});
var GeneralInfo = React.createClass({
  displayName: "GeneralInfo",
  getInitialState: function () {
    return {
      version: opstate.version,
      start: opstate.overview ? opstate.overview.readable.start_time : null,
      reset: opstate.overview ? opstate.overview.readable.last_restart_time : null
    };
  },
  render: function () {
    var startTime = this.state.start ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Start time"), /*#__PURE__*/React.createElement("td", null, this.state.start)) : '';
    var lastReset = this.state.reset ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Last reset"), /*#__PURE__*/React.createElement("td", null, this.state.reset)) : '';
    return /*#__PURE__*/React.createElement("table", {
      className: "tables general-info-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
      colSpan: "2"
    }, "General info"))), /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Zend OPcache"), /*#__PURE__*/React.createElement("td", null, this.state.version.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "PHP"), /*#__PURE__*/React.createElement("td", null, this.state.version.php)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Host"), /*#__PURE__*/React.createElement("td", null, this.state.version.host)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Server Software"), /*#__PURE__*/React.createElement("td", null, this.state.version.server)), startTime, lastReset));
  }
});
var Directives = React.createClass({
  displayName: "Directives",
  getInitialState: function () {
    return {
      data: opstate.directives
    };
  },
  render: function () {
    var directiveNodes = this.state.data.map(function (directive) {
      var map = {
        'opcache.': '',
        '_': ' '
      };
      var dShow = directive.k.replace(/opcache\.|_/gi, function (matched) {
        return map[matched];
      });
      var vShow;

      if (directive.v === true || directive.v === false) {
        vShow = React.createElement('i', {}, directive.v.toString());
      } else if (directive.v === '') {
        vShow = React.createElement('i', {}, 'no value');
      } else {
        if (Array.isArray(directive.v)) {
          vShow = directive.v.map((item, key) => {
            return /*#__PURE__*/React.createElement("span", {
              key: key
            }, item, /*#__PURE__*/React.createElement("br", null));
          });
        } else {
          vShow = directive.v;
        }
      }

      return /*#__PURE__*/React.createElement("tr", {
        key: directive.k
      }, /*#__PURE__*/React.createElement("td", {
        title: 'View ' + directive.k + ' manual entry'
      }, /*#__PURE__*/React.createElement("a", {
        href: 'http://php.net/manual/en/opcache.configuration.php#ini.' + directive.k.replace(/_/g, '-'),
        target: "_blank"
      }, dShow)), /*#__PURE__*/React.createElement("td", null, vShow));
    });
    return /*#__PURE__*/React.createElement("table", {
      className: "tables directives-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
      colSpan: "2"
    }, "Directives"))), /*#__PURE__*/React.createElement("tbody", null, directiveNodes));
  }
});
var Files = React.createClass({
  displayName: "Files",
  getInitialState: function () {
    return {
      data: opstate.files,
      showing: null,
      allowFiles: allowFiles
    };
  },
  handleInvalidate: function (e) {
    e.preventDefault();

    if (realtime) {
      $.get('#', {
        invalidate: e.currentTarget.getAttribute('data-file')
      }, function (data) {
        console.log('success: ' + data.success);
      }, 'json');
    } else {
      window.location.href = e.currentTarget.href;
    }
  },
  render: function () {
    if (this.state.allowFiles) {
      var fileNodes = this.state.data.map(function (file, i) {
        var invalidate, invalidated;

        if (file.timestamp == 0) {
          invalidated = /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("i", {
            className: "invalid metainfo"
          }, " - has been invalidated"));
        }

        if (canInvalidate) {
          invalidate = /*#__PURE__*/React.createElement("span", null, ",\xA0", /*#__PURE__*/React.createElement("a", {
            className: "file-metainfo",
            href: '?invalidate=' + file.full_path,
            "data-file": file.full_path,
            onClick: this.handleInvalidate
          }, "force file invalidation"));
        }

        return /*#__PURE__*/React.createElement("tr", {
          key: file.full_path,
          "data-path": file.full_path.toLowerCase(),
          className: i % 2 ? 'alternate' : ''
        }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
          className: "file-pathname"
        }, file.full_path), /*#__PURE__*/React.createElement(FilesMeta, {
          data: [file.readable.hits, file.readable.memory_consumption, file.last_used]
        }), invalidate, invalidated));
      }.bind(this));
      return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(FilesListed, {
        showing: this.state.showing
      }), /*#__PURE__*/React.createElement("table", {
        className: "tables file-list-table"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Script"))), /*#__PURE__*/React.createElement("tbody", null, fileNodes)));
    } else {
      return /*#__PURE__*/React.createElement("span", null);
    }
  }
});
var FilesMeta = React.createClass({
  displayName: "FilesMeta",
  render: function () {
    return /*#__PURE__*/React.createElement("span", {
      className: "file-metainfo"
    }, /*#__PURE__*/React.createElement("b", null, "hits: "), /*#__PURE__*/React.createElement("span", null, this.props.data[0], ", "), /*#__PURE__*/React.createElement("b", null, "memory: "), /*#__PURE__*/React.createElement("span", null, this.props.data[1], ", "), /*#__PURE__*/React.createElement("b", null, "last used: "), /*#__PURE__*/React.createElement("span", null, this.props.data[2]));
  }
});
var FilesListed = React.createClass({
  displayName: "FilesListed",
  getInitialState: function () {
    return {
      formatted: opstate.overview ? opstate.overview.readable.num_cached_scripts : 0,
      total: opstate.overview ? opstate.overview.num_cached_scripts : 0
    };
  },
  render: function () {
    var display = this.state.formatted + ' file' + (this.state.total == 1 ? '' : 's') + ' cached';

    if (this.props.showing !== null && this.props.showing != this.state.total) {
      display += ', ' + this.props.showing + ' showing due to filter';
    }

    return /*#__PURE__*/React.createElement("h3", null, display);
  }
});
var overviewCountsObj = ReactDOM.render( /*#__PURE__*/React.createElement(OverviewCounts, null), document.getElementById('counts'));
var generalInfoObj = ReactDOM.render( /*#__PURE__*/React.createElement(GeneralInfo, null), document.getElementById('generalInfo'));
var filesObj = ReactDOM.render( /*#__PURE__*/React.createElement(Files, null), document.getElementById('filelist'));
ReactDOM.render( /*#__PURE__*/React.createElement(Directives, null), document.getElementById('directives'));
</script>

</body>
</html>