<?php

/**
 * OPcache GUI
 *
 * A simple but effective single-file GUI for the OPcache PHP extension.
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 3.6.0
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, https://acollington.mit-license.org/
 */

/*
 * User configuration
 * These are all the default values; you only really need to supply the ones
 * that you wish to change.
 */

$options = [
    'allow_filelist'   => true,                // show/hide the files tab
    'allow_invalidate' => true,                // give a link to invalidate files
    'allow_reset'      => true,                // give option to reset the whole cache
    'allow_realtime'   => true,                // give option to enable/disable real-time updates
    'refresh_time'     => 5,                   // how often the data will refresh, in seconds
    'size_precision'   => 2,                   // Digits after decimal point
    'size_space'       => false,               // have '1MB' or '1 MB' when showing sizes
    'charts'           => true,                // show gauge chart or just big numbers
    'debounce_rate'    => 250,                 // milliseconds after key press to send keyup event when filtering
    'per_page'         => 200,                 // How many results per page to show in the file list, false for no pagination
    'cookie_name'      => 'opcachegui',        // name of cookie
    'cookie_ttl'       => 365,                 // days to store cookie
    'datetime_format'  => 'D, d M Y H:i:s O',  // Show datetime in this format
    'highlight'        => [
        'memory' => true,                      // show the memory chart/big number
        'hits'   => true,                      // show the hit rate chart/big number
        'keys'   => true,                      // show the keys used chart/big number
        'jit'    => true                       // show the jit buffer chart/big number
    ],
    // json structure of all text strings used, or null for default
    'language_pack'    => null
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
    public const VERSION = '3.6.0';

    protected $tz;
    protected $data;
    protected $options;
    protected $optimizationLevels;
    protected $jitModes;
    protected $jitModeMapping = [
        'tracing' => 1254,
        'on' => 1254,
        'function' => 1205
    ];
    protected $defaults = [
        'allow_filelist'   => true,                // show/hide the files tab
        'allow_invalidate' => true,                // give a link to invalidate files
        'allow_reset'      => true,                // give option to reset the whole cache
        'allow_realtime'   => true,                // give option to enable/disable real-time updates
        'refresh_time'     => 5,                   // how often the data will refresh, in seconds
        'size_precision'   => 2,                   // Digits after decimal point
        'size_space'       => false,               // have '1MB' or '1 MB' when showing sizes
        'charts'           => true,                // show gauge chart or just big numbers
        'debounce_rate'    => 250,                 // milliseconds after key press to send keyup event when filtering
        'per_page'         => 200,                 // How many results per page to show in the file list, false for no pagination
        'cookie_name'      => 'opcachegui',        // name of cookie
        'cookie_ttl'       => 365,                 // days to store cookie
        'datetime_format'  => 'D, d M Y H:i:s O',  // Show datetime in this format
        'highlight'        => [
            'memory' => true,                      // show the memory chart/big number
            'hits'   => true,                      // show the hit rate chart/big number
            'keys'   => true,                      // show the keys used chart/big number
            'jit'    => true                       // show the jit buffer chart/big number
        ],
        'language_pack'    => null                 // json structure of all text strings used, or null for default
    ];

    /**
     * Service constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->defaults, $options);
        $this->tz = new DateTimeZone(date_default_timezone_get());
        if (is_string($this->options['language_pack'])) {
            $this->options['language_pack'] = json_decode($this->options['language_pack'], true);
        }

        $this->optimizationLevels = [
            1 << 0  => $this->txt('CSE, STRING construction'),
            1 << 1  => $this->txt('Constant conversion and jumps'),
            1 << 2  => $this->txt('++, +=, series of jumps'),
            1 << 3  => $this->txt('INIT_FCALL_BY_NAME -> DO_FCALL'),
            1 << 4  => $this->txt('CFG based optimization'),
            1 << 5  => $this->txt('DFA based optimization'),
            1 << 6  => $this->txt('CALL GRAPH optimization'),
            1 << 7  => $this->txt('SCCP (constant propagation)'),
            1 << 8  => $this->txt('TMP VAR usage'),
            1 << 9  => $this->txt('NOP removal'),
            1 << 10 => $this->txt('Merge equal constants'),
            1 << 11 => $this->txt('Adjust used stack'),
            1 << 12 => $this->txt('Remove unused variables'),
            1 << 13 => $this->txt('DCE (dead code elimination)'),
            1 << 14 => $this->txt('(unsafe) Collect constants'),
            1 << 15 => $this->txt('Inline functions'),
        ];
        $this->jitModes = [
            [
                'flag' => $this->txt('CPU-specific optimization'),
                'value' => [
                    $this->txt('Disable CPU-specific optimization'),
                    $this->txt('Enable use of AVX, if the CPU supports it')
                ]
            ],
            [
                'flag' => $this->txt('Register allocation'),
                'value' => [
                    $this->txt('Do not perform register allocation'),
                    $this->txt('Perform block-local register allocation'),
                    $this->txt('Perform global register allocation')
                ]
            ],
            [
                'flag' => $this->txt('Trigger'),
                'value' => [
                    $this->txt('Compile all functions on script load'),
                    $this->txt('Compile functions on first execution'),
                    $this->txt('Profile functions on first request and compile the hottest functions afterwards'),
                    $this->txt('Profile on the fly and compile hot functions'),
                    $this->txt('Currently unused'),
                    $this->txt('Use tracing JIT. Profile on the fly and compile traces for hot code segments')
                ]
            ],
            [
                'flag' => $this->txt('Optimization level'),
                'value' => [
                    $this->txt('No JIT'),
                    $this->txt('Minimal JIT (call standard VM handlers)'),
                    $this->txt('Inline VM handlers'),
                    $this->txt('Use type inference'),
                    $this->txt('Use call graph'),
                    $this->txt('Optimize whole script')
                ]
            ]
        ];

        $this->data = $this->compileState();
    }

    /**
     * @return string
     */
    public function txt(): string
    {
        $args = func_get_args();
        $text = array_shift($args);
        if ((($lang = $this->getOption('language_pack')) !== null) && !empty($lang[$text])) {
            $text = $lang[$text];
        }
        foreach ($args as $i => $arg) {
            $text = str_replace('{' . $i . '}', $arg, $text);
        }
        return $text;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function handle(): Service
    {
        $response = function($success) {
            if ($this->isJsonRequest()) {
                echo '{ "success": "' . ($success ? 'yes' : 'no') . '" }';
            } else {
                header('Location: ?');
            }
            exit;
        };

        if (isset($_GET['reset']) && $this->getOption('allow_reset')) {
            $response($this->resetCache());
        } elseif (isset($_GET['invalidate']) && $this->getOption('allow_invalidate')) {
            $response($this->resetCache($_GET['invalidate']));
        } elseif (isset($_GET['invalidate_searched']) && $this->getOption('allow_invalidate')) {
            $response($this->resetSearched($_GET['invalidate_searched']));
        } elseif ($this->isJsonRequest() && $this->getOption('allow_realtime')) {
            echo json_encode($this->getData($_GET['section'] ?? null));
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

        return $this->options[$name] ?? null;
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
     * @throws Exception
     */
    public function resetCache(?string $file = null): bool
    {
        $success = false;
        if ($file === null) {
            $success = opcache_reset();
        } elseif (function_exists('opcache_invalidate')) {
            $success = opcache_invalidate(urldecode($file), true);
        }
        if ($success) {
            $this->compileState();
        }
        return $success;
    }

    /**
     * @param string $search
     * @return bool
     * @throws Exception
     */
    public function resetSearched(string $search): bool
    {
        $found = $success = 0;
        $search = urldecode($search);
        foreach ($this->getData('files') as $file) {
            if (strpos($file['full_path'], $search) !== false) {
                ++$found;
                $success += (int)opcache_invalidate($file['full_path'], true);
            }
        }
        if ($success) {
            $this->compileState();
        }
        return $found === $success;
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
     * @return bool
     */
    protected function isJsonRequest(): bool
    {
        return !empty($_SERVER['HTTP_ACCEPT'])
            && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    /**
     * @return array
     * @throws Exception
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
            uasort($status['scripts'], static function ($a, $b) {
                return $a['hits'] <=> $b['hits'];
            });
            foreach ($status['scripts'] as &$file) {
                $file['full_path'] = str_replace('\\', '/', $file['full_path']);
                $file['readable'] = [
                    'hits' => number_format($file['hits']),
                    'memory_consumption' => $this->size($file['memory_consumption'])
                ];
                $file['last_used'] = (new DateTimeImmutable("@{$file['last_used_timestamp']}"))
                    ->setTimezone($this->tz)
                    ->format($this->getOption('datetime_format'));
                $file['last_modified'] = "";
                if (!empty($file['timestamp'])) {
                    $file['last_modified'] = (new DateTimeImmutable("@{$file['timestamp']}"))
                        ->setTimezone($this->tz)
                        ->format($this->getOption('datetime_format'));
                }
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
                        'start_time' => (new DateTimeImmutable("@{$status['opcache_statistics']['start_time']}"))
                            ->setTimezone($this->tz)
                            ->format($this->getOption('datetime_format')),
                        'last_restart_time' => ($status['opcache_statistics']['last_restart_time'] === 0
                            ? $this->txt('never')
                            : (new DateTimeImmutable("@{$status['opcache_statistics']['last_restart_time']}"))
                                ->setTimezone($this->tz)
                                ->format($this->getOption('datetime_format'))
                        )
                    ]
                ]
            );
        }

        $preload = [];
        if (!empty($status['preload_statistics']['scripts']) && $this->getOption('allow_filelist')) {
            $preload = $status['preload_statistics']['scripts'];
            sort($preload, SORT_STRING);
            if ($overview) {
                $overview['preload_memory'] = $status['preload_statistics']['memory_consumption'];
                $overview['readable']['preload_memory'] = $this->size($status['preload_statistics']['memory_consumption']);
            }
        }

        if (!empty($status['interned_strings_usage'])) {
            $overview['readable']['interned'] = [
                'buffer_size' => $this->size($status['interned_strings_usage']['buffer_size']),
                'strings_used_memory' => $this->size($status['interned_strings_usage']['used_memory']),
                'strings_free_memory' => $this->size($status['interned_strings_usage']['free_memory']),
                'number_of_strings' => number_format($status['interned_strings_usage']['number_of_strings'])
            ];
        }

        if ($overview && !empty($status['jit']['enabled'])) {
            $overview['jit_buffer_used_percentage'] = ($status['jit']['buffer_size']
                ? round(100 * (($status['jit']['buffer_size'] - $status['jit']['buffer_free']) / $status['jit']['buffer_size']))
                : 0
            );
            $overview['readable'] = array_merge($overview['readable'], [
                'jit_buffer_size' => $this->size($status['jit']['buffer_size']),
                'jit_buffer_free' => $this->size($status['jit']['buffer_free'])
            ]);
        } else {
            $this->options['highlight']['jit'] = false;
        }

        $directives = [];
        ksort($config['directives']);
        foreach ($config['directives'] as $k => $v) {
            if (in_array($k, ['opcache.max_file_size', 'opcache.memory_consumption', 'opcache.jit_buffer_size']) && $v) {
                $v = $this->size($v) . " ({$v})";
            } elseif ($k === 'opcache.optimization_level') {
                $levels = [];
                foreach ($this->optimizationLevels as $level => $info) {
                    if ($level & $v) {
                        $levels[] = "{$info} [{$level}]";
                    }
                }
                $v = $levels ?: 'none';
            } elseif ($k === 'opcache.jit') {
                if ($v === '1') {
                    $v = 'on';
                }
                if (isset($this->jitModeMapping[$v]) || is_numeric($v)) {
                    $levels = [];
                    foreach (str_split((string)($this->jitModeMapping[$v] ?? $v)) as $type => $level) {
                        $levels[] = "{$level}: {$this->jitModes[$type]['value'][$level]} ({$this->jitModes[$type]['flag']})";
                    }
                    $v = [$v, $levels];
                } elseif (empty($v) || strtolower($v) === 'off') {
                    $v = 'Off';
                }
            }
            $directives[] = [
                'k' => $k,
                'v' => $v
            ];
        }

        $version = array_merge(
            $config['version'],
            [
                'php' => PHP_VERSION,
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'host' => (function_exists('gethostname')
                    ? gethostname()
                    : (php_uname('n')
                        ?: (empty($_SERVER['SERVER_NAME'])
                            ? $_SERVER['HOST_NAME']
                            : $_SERVER['SERVER_NAME']
                        )
                    )
                ),
                'gui' => self::VERSION
            ]
        );

        return [
            'version' => $version,
            'overview' => $overview,
            'files' => $files,
            'preload' => $preload,
            'directives' => $directives,
            'blacklist' => $config['blacklist'],
            'functions' => get_extension_funcs('Zend OPcache'),
            'jitState' => $this->jitState($status, $config['directives']),
        ];
    }

    protected function jitState(array $status, array $directives): array
    {
        $state = [
            'enabled' => $status['jit']['enabled'] ?? false,
            'reason' => ''
        ];

        if (!$state['enabled']) {
            if (empty($directives['opcache.jit']) || $directives['opcache.jit'] === 'disable') {
                $state['reason'] = $this->txt('disabled due to <i>opcache.jit</i> setting');
            } elseif (!$directives['opcache.jit_buffer_size']) {
                $state['reason'] = $this->txt('the <i>opcache.jit_buffer_size</i> must be set to fully enable JIT');
            } else {
                $state['reason'] = $this->txt('incompatible with extensions that override <i>zend_execute_ex()</i>, such as <i>xdebug</i>');
            }
        }

        return $state;
    }
}

$opcache = (new Service($options))->handle();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow" />
    <title>OPcache statistics on <?= $opcache->getData('version', 'host'); ?></title>
    <script src="//cdnjs.cloudflare.com/ajax/libs/react/18.3.1/umd/react.production.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/react-dom/18.3.1/umd/react-dom.production.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/axios/1.11.0/axios.min.js"></script>
    <style>
        :root{--opcache-gui-graph-track-fill-color: #6CA6EF;--opcache-gui-graph-track-background-color: rgba(229, 231, 231, 0.9058823529)}.opcache-gui{font-family:sans-serif;font-size:90%;padding:0;margin:0;min-height:100vh;display:flex;flex-direction:column}.opcache-gui #interface{flex:1 1 auto;display:flex;flex-direction:column}.opcache-gui .hide{display:none}.opcache-gui .sr-only{border:0 !important;clip:rect(1px, 1px, 1px, 1px) !important;-webkit-clip-path:inset(50%) !important;clip-path:inset(50%) !important;height:1px !important;margin:-1px !important;overflow:hidden !important;padding:0 !important;position:absolute !important;width:1px !important;white-space:nowrap !important}.opcache-gui header,.opcache-gui .main-nav{display:flex;flex-direction:column;min-height:0;padding-top:20px}.opcache-gui .main-nav{position:relative}.opcache-gui .nav-tab-list{list-style-type:none;padding-left:8px;padding-right:160px;margin:0;border-bottom:1px solid #ccc;display:flex;align-items:end}.opcache-gui .nav-tab{display:inline-flex;margin:0 0 -1px 0;padding:15px 30px;border:1px solid rgba(0,0,0,0);border-bottom-color:#ccc;text-decoration:none;background-color:#fff;cursor:pointer;user-select:none;align-items:center}.opcache-gui .nav-tab:hover{background-color:#f4f4f4;text-decoration:underline}.opcache-gui .nav-tab.active{border:1px solid #ccc;border-bottom-color:#fff;border-top:3px solid #6ca6ef}.opcache-gui .nav-tab.active:hover{background-color:initial}.opcache-gui .nav-tab:focus{outline:0;text-decoration:underline}.opcache-gui .nav-tab-link-reset>svg,.opcache-gui .nav-tab-link-realtime>svg{overflow:visible;width:1.1rem;height:1.1rem;margin-right:.5em}.opcache-gui .nav-tab-link-reset>svg>path,.opcache-gui .nav-tab-link-realtime>svg>path{fill:#626262}.opcache-gui .nav-tab-link-reset.activated>svg>path,.opcache-gui .nav-tab-link-realtime.activated>svg>path{fill:#00ba00;transform-origin:50% 50%;display:inline-block}.opcache-gui .nav-tab-link-reset.activated>svg>path{animation:spin-all 2s linear infinite}.opcache-gui .nav-tab-link-reset.is-resetting>svg>path{fill:#00ba00}.opcache-gui .nav-tab-link-realtime.activated>svg>path{animation:spin-pause 2s ease-in infinite}main{flex:1 1 auto}.tab-content{padding:2em;flex:1 1 auto;min-height:0;overflow:auto}.tab-content-overview-counts{width:270px;float:right}.tab-content-overview-info{margin-right:280px}.graph-widget{max-width:100%;height:auto;margin:0 auto;display:flex;position:relative}.graph-widget .widget-value{display:flex;align-items:center;justify-content:center;text-align:center;position:absolute;top:0;width:100%;height:100%;margin:0 auto;font-size:3.2em;font-weight:100;color:#6ca6ef;user-select:none}.widget-panel{background-color:#ededed;margin-bottom:10px}.widget-header{background-color:#cdcdcd;padding:4px 6px;margin:0;text-align:center;font-size:1rem;font-weight:bold}.widget-value{margin:0;text-align:center}.widget-value span.large{color:#6ca6ef;font-size:80pt;margin:0;padding:0;text-align:center}.widget-value span.large+span{font-size:20pt;margin:0;color:#6ca6ef}.widget-info{margin:0;padding:10px}.widget-info *{margin:0;line-height:1.75em;text-align:left}.tables{margin:0 0 1em 0;border-collapse:collapse;width:100%;table-layout:fixed}.tables tr:nth-child(odd){background-color:#effeff}.tables tr:nth-child(even){background-color:#e0ecef}.tables th{text-align:left;padding:6px;background-color:#6ca6ef;color:#fff;border-color:#fff;font-weight:normal}.tables td{padding:4px 6px;line-height:1.4em;vertical-align:top;border-color:#fff;overflow:hidden;overflow-wrap:break-word;text-overflow:ellipsis}.directive-list{list-style-type:none;padding:0;margin:0}.directive-list li{margin-bottom:.5em}.directive-list li:last-child{margin-bottom:0}.directive-list li ul{margin-top:1.5em}.file-filter{width:520px}.file-metainfo{font-size:80%}.file-metainfo.invalid{font-style:italic}.file-pathname{width:70%;display:block}.nav-tab-link-reset,.nav-tab-link-realtime,.github-link,.sponsor-link{background-repeat:no-repeat;background-color:rgba(0,0,0,0)}.nav-tab-link-reset,.nav-tab-link-realtime{background-position:24px 50%}.main-footer{border-top:1px solid #ccc;padding:1em 2em;display:flex;align-items:center}.github-link,.sponsor-link{background-position:0 50%;padding:2em 0 2em 2.3em;text-decoration:none;opacity:.7;font-size:80%;display:flex;align-items:center}.github-link:hover,.sponsor-link:hover{opacity:1}.github-link>svg,.sponsor-link>svg{height:1rem;width:1rem;margin-right:.25rem}.theme-switcher{position:absolute;top:0;right:8px;height:54px;display:flex;align-items:center;z-index:2;margin:15px 20px 0 0}.theme-toggle{position:relative;display:grid;grid-template-columns:1fr 1fr 1fr;align-items:stretch;min-width:156px;height:30px;padding:0;border:1px solid #ccc;border-radius:999px;background-color:#fff;box-shadow:inset 0 0 0 1px rgba(0,0,0,.02)}.theme-toggle-slider{position:absolute;left:3px;top:3px;bottom:3px;width:calc((100% - 12px)/3);border-radius:999px;background-color:#f4f4f4;transition:transform .2s ease;z-index:0}.theme-toggle-btn{appearance:none;-webkit-appearance:none;border:0;background:rgba(0,0,0,0);padding:0;margin:0;display:inline-flex;align-items:center;justify-content:center;color:#626262;cursor:pointer;border-radius:999px;position:relative;z-index:1}.theme-toggle-btn.active{color:#00ba00}.github-link>svg>path{fill:#626262}.sponsor-link{margin-left:2em}.file-cache-only{margin-top:0}.paginate-filter{display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap}.paginate-filter .filter>*{padding:3px;margin:3px 3px 10px 0}.pagination{margin:10px 0;padding:0}.pagination li{display:inline-block}.pagination li a{display:inline-flex;align-items:center;white-space:nowrap;line-height:1;padding:.5rem .75rem;border-radius:3px;text-decoration:none;height:100%}.pagination li a.arrow{font-size:1.1rem}.pagination li a:active{transform:translateY(2px)}.pagination li a.active{background-color:#4d75af;color:#fff}.pagination li a:hover:not(.active){background-color:#ff7400;color:#fff}@media screen and (max-width: 750px){.opcache-gui .nav-tab-list{border-bottom:0;display:flex;flex-direction:column;padding:0;align-items:center}.opcache-gui .nav-tab{margin:0;border:0;border-top:1px solid #ccc;width:100%;align-items:center;justify-content:center}.opcache-gui .nav-tab:last-child{border-bottom:1px solid #ccc}.opcache-gui .nav-tab.active{border:0;border-top:1px solid #ccc;background:rgba(108,166,239,.1)}.opcache-gui .nav-tab-link{display:block;margin:0 10px;padding:10px 0 10px 30px;border:0}.opcache-gui .theme-switcher{position:static;height:auto;margin:5px;align-self:center;transform:none}.opcache-gui .tab-content-overview-info{margin-right:auto;clear:both}.opcache-gui .tab-content-overview-counts{position:relative;display:block;width:100%}.opcache-gui header{flex-direction:column-reverse}}@media screen and (max-width: 750px)and (max-width: 550px){.file-filter{width:100%}}@keyframes spin-pause{0%{transform:rotate(0deg)}50%,100%{transform:rotate(360deg)}}@keyframes spin-all{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}@media(prefers-color-scheme: dark){:root:not(.light) .opcache-gui{background-color:#121212;color:#ddd}:root:not(.light) .opcache-gui a{color:#1e90ff}:root:not(.light) .opcache-gui .nav-tab{background-color:rgba(0,0,0,0);border-color:rgba(0,0,0,0)}:root:not(.light) .opcache-gui .nav-tab:hover{background-color:#2a2a2a}:root:not(.light) .opcache-gui .nav-tab.active{background-color:#1e1e1e;border-color:rgba(0,0,0,0);border-top-color:#84b8ff;border-bottom-color:#1e1e1e}:root:not(.light) .opcache-gui .nav-tab-link-reset,:root:not(.light) .opcache-gui .nav-tab-link-realtime{background-color:rgba(0,0,0,0)}:root:not(.light) .opcache-gui .nav-tab-link-reset.is-resetting,:root:not(.light) .opcache-gui .nav-tab-link-reset.live-update,:root:not(.light) .opcache-gui .nav-tab-link-realtime.is-resetting,:root:not(.light) .opcache-gui .nav-tab-link-realtime.live-update{background-color:rgba(0,0,0,0)}:root:not(.light) .opcache-gui .nav-tab-link-reset.pulse::before,:root:not(.light) .opcache-gui .nav-tab-link-realtime.pulse::before{border-color:#00e600}:root:not(.light) .opcache-gui .github-link>svg>path{fill:#ccc}:root:not(.light) .opcache-gui .graph-widget .widget-value,:root:not(.light) .opcache-gui .widget-value span.large,:root:not(.light) .opcache-gui .widget-value span.large+span{color:#84b8ff}:root:not(.light) .opcache-gui .widget-panel{background-color:#2a2a2a}:root:not(.light) .opcache-gui .widget-header{background-color:#333;color:#ccc}:root:not(.light) .opcache-gui .tables tr:nth-child(odd){background-color:#2a2a2a}:root:not(.light) .opcache-gui .tables tr:nth-child(even){background-color:#1f1f1f}:root:not(.light) .opcache-gui .tables th{background-color:#3a4a5e;color:#ddd;border-color:#444}:root:not(.light) .opcache-gui .tables td{border-color:#333}:root:not(.light) .opcache-gui .main-footer{border-top-color:#444;color:#ccc}:root:not(.light) .opcache-gui .pagination li a.active{background-color:#4d75af;color:#fff}:root:not(.light) .opcache-gui .pagination li a:hover:not(.active){background-color:#ff7400;color:#fff}:root:not(.light) .opcache-gui .theme-toggle{background-color:#1e1e1e;border-color:#444}:root:not(.light) .opcache-gui .theme-toggle-slider{background-color:#2a2a2a}:root:not(.light) .opcache-gui .theme-toggle-btn{color:#ccc}:root:not(.light) .opcache-gui .theme-toggle-btn.active{color:#84b8ff}}.dark .opcache-gui{background-color:#121212;color:#ddd}.dark .opcache-gui a{color:#1e90ff}.dark .opcache-gui .nav-tab{background-color:rgba(0,0,0,0);border-color:rgba(0,0,0,0)}.dark .opcache-gui .nav-tab:hover{background-color:#2a2a2a}.dark .opcache-gui .nav-tab.active{background-color:#1e1e1e;border-color:rgba(0,0,0,0);border-top-color:#84b8ff;border-bottom-color:#1e1e1e}.dark .opcache-gui .nav-tab-link-reset,.dark .opcache-gui .nav-tab-link-realtime{background-color:rgba(0,0,0,0)}.dark .opcache-gui .nav-tab-link-reset.is-resetting,.dark .opcache-gui .nav-tab-link-reset.live-update,.dark .opcache-gui .nav-tab-link-realtime.is-resetting,.dark .opcache-gui .nav-tab-link-realtime.live-update{background-color:rgba(0,0,0,0)}.dark .opcache-gui .nav-tab-link-reset.pulse::before,.dark .opcache-gui .nav-tab-link-realtime.pulse::before{border-color:#00e600}.dark .opcache-gui .github-link>svg>path{fill:#ccc}.dark .opcache-gui .graph-widget .widget-value,.dark .opcache-gui .widget-value span.large,.dark .opcache-gui .widget-value span.large+span{color:#84b8ff}.dark .opcache-gui .widget-panel{background-color:#2a2a2a}.dark .opcache-gui .widget-header{background-color:#333;color:#ccc}.dark .opcache-gui .tables tr:nth-child(odd){background-color:#2a2a2a}.dark .opcache-gui .tables tr:nth-child(even){background-color:#1f1f1f}.dark .opcache-gui .tables th{background-color:#3a4a5e;color:#ddd;border-color:#444}.dark .opcache-gui .tables td{border-color:#333}.dark .opcache-gui .main-footer{border-top-color:#444;color:#ccc}.dark .opcache-gui .pagination li a.active{background-color:#4d75af;color:#fff}.dark .opcache-gui .pagination li a:hover:not(.active){background-color:#ff7400;color:#fff}.dark .opcache-gui .theme-toggle{background-color:#1e1e1e;border-color:#444}.dark .opcache-gui .theme-toggle-slider{background-color:#2a2a2a}.dark .opcache-gui .theme-toggle-btn{color:#ccc}.dark .opcache-gui .theme-toggle-btn.active{color:#84b8ff}
    </style>
</head>

<body style="padding: 0; margin: 0;" class="opcache-gui">

    <div id="interface" />

    <script type="text/javascript">

    function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == typeof i ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != typeof t || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != typeof i) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
class Interface extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "startTimer", () => {
      this.setState({
        realtime: true
      });
      this.polling = setInterval(() => {
        this.setState({
          fetching: true,
          resetting: false
        });
        axios.get(window.location.pathname, {
          time: Date.now()
        }).then(response => {
          this.setState({
            opstate: response.data
          });
        });
      }, this.props.realtimeRefresh * 1000);
    });
    _defineProperty(this, "stopTimer", () => {
      this.setState({
        realtime: false,
        resetting: false
      });
      clearInterval(this.polling);
    });
    _defineProperty(this, "realtimeHandler", () => {
      const realtime = !this.state.realtime;
      if (!realtime) {
        this.stopTimer();
        this.removeCookie();
      } else {
        this.startTimer();
        this.setCookie();
      }
    });
    _defineProperty(this, "resetHandler", () => {
      if (this.state.realtime) {
        this.setState({
          resetting: true
        });
        axios.get(window.location.pathname, {
          params: {
            reset: 1
          }
        }).then(response => {
          console.log('success: ', response.data);
        });
      } else {
        window.location.href = '?reset=1';
      }
    });
    _defineProperty(this, "setCookie", () => {
      let d = new Date();
      d.setTime(d.getTime() + this.props.cookie.ttl * 86400000);
      document.cookie = `${this.props.cookie.name}=true;expires=${d.toUTCString()};path=/${this.isSecure ? ';secure' : ''}`;
    });
    _defineProperty(this, "removeCookie", () => {
      document.cookie = `${this.props.cookie.name}=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/${this.isSecure ? ';secure' : ''}`;
    });
    _defineProperty(this, "getCookie", () => {
      const v = document.cookie.match(`(^|;) ?${this.props.cookie.name}=([^;]*)(;|$)`);
      return v ? !!v[2] : false;
    });
    _defineProperty(this, "getStoredTheme", () => {
      try {
        const t = localStorage.getItem(Interface.THEME_STORAGE_KEY);
        return t === 'light' || t === 'dark' || t === 'system' ? t : 'system';
      } catch (e) {
        return 'system';
      }
    });
    _defineProperty(this, "applyTheme", theme => {
      const root = document.documentElement;
      root.classList.remove('dark');
      root.classList.remove('light');
      if (theme === 'dark') {
        root.classList.add('dark');
      } else if (theme === 'light') {
        root.classList.add('light');
      }
    });
    _defineProperty(this, "setTheme", theme => {
      this.setState({
        theme
      });
      try {
        localStorage.setItem(Interface.THEME_STORAGE_KEY, theme);
      } catch (e) {}
      this.applyTheme(theme);
    });
    _defineProperty(this, "txt", (text, ...args) => {
      if (this.props.language !== null && this.props.language.hasOwnProperty(text) && this.props.language[text]) {
        text = this.props.language[text];
      }
      args.forEach((arg, i) => {
        text = text.replaceAll(`{${i}}`, arg);
      });
      return text;
    });
    this.state = {
      realtime: this.getCookie(),
      resetting: false,
      opstate: props.opstate,
      theme: this.getStoredTheme()
    };
    this.polling = false;
    this.isSecure = window.location.protocol === 'https:';
    if (this.getCookie()) {
      this.startTimer();
    }
  }
  componentDidMount() {
    this.applyTheme(this.state.theme);
  }
  render() {
    const {
      opstate,
      realtimeRefresh,
      ...otherProps
    } = this.props;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(MainNavigation, _extends({}, otherProps, {
      opstate: this.state.opstate,
      realtime: this.state.realtime,
      resetting: this.state.resetting,
      realtimeHandler: this.realtimeHandler,
      resetHandler: this.resetHandler,
      theme: this.state.theme,
      onThemeChange: this.setTheme,
      txt: this.txt
    })), /*#__PURE__*/React.createElement(Footer, {
      version: this.props.opstate.version.gui,
      txt: this.txt
    }));
  }
}
_defineProperty(Interface, "THEME_STORAGE_KEY", 'opcache_gui_theme');
function MainNavigation(props) {
  return /*#__PURE__*/React.createElement(Tabs, props, /*#__PURE__*/React.createElement("div", {
    label: props.txt("Overview"),
    tabId: "overview",
    tabIndex: 1
  }, /*#__PURE__*/React.createElement(OverviewCounts, {
    overview: props.opstate.overview,
    highlight: props.highlight,
    useCharts: props.useCharts,
    txt: props.txt
  }), /*#__PURE__*/React.createElement("div", {
    id: "info",
    className: "tab-content-overview-info"
  }, /*#__PURE__*/React.createElement(GeneralInfo, {
    start: props.opstate.overview && props.opstate.overview.readable.start_time || null,
    reset: props.opstate.overview && props.opstate.overview.readable.last_restart_time || null,
    version: props.opstate.version,
    jit: props.opstate.jitState,
    txt: props.txt
  }), /*#__PURE__*/React.createElement(Directives, {
    directives: props.opstate.directives,
    txt: props.txt
  }), /*#__PURE__*/React.createElement(Functions, {
    functions: props.opstate.functions,
    txt: props.txt
  }))), props.allow.filelist && /*#__PURE__*/React.createElement("div", {
    label: props.txt("Cached"),
    tabId: "cached",
    tabIndex: 2
  }, /*#__PURE__*/React.createElement(CachedFiles, {
    perPageLimit: props.perPageLimit,
    allFiles: props.opstate.files,
    searchTerm: props.searchTerm,
    debounceRate: props.debounceRate,
    allow: {
      fileList: props.allow.filelist,
      invalidate: props.allow.invalidate
    },
    realtime: props.realtime,
    txt: props.txt
  })), props.allow.filelist && props.opstate.blacklist.length && /*#__PURE__*/React.createElement("div", {
    label: props.txt("Ignored"),
    tabId: "ignored",
    tabIndex: 3
  }, /*#__PURE__*/React.createElement(IgnoredFiles, {
    perPageLimit: props.perPageLimit,
    allFiles: props.opstate.blacklist,
    allow: {
      fileList: props.allow.filelist
    },
    txt: props.txt
  })), props.allow.filelist && props.opstate.preload.length && /*#__PURE__*/React.createElement("div", {
    label: props.txt("Preloaded"),
    tabId: "preloaded",
    tabIndex: 4
  }, /*#__PURE__*/React.createElement(PreloadedFiles, {
    perPageLimit: props.perPageLimit,
    allFiles: props.opstate.preload,
    allow: {
      fileList: props.allow.filelist
    },
    txt: props.txt
  })), props.allow.reset && /*#__PURE__*/React.createElement("div", {
    label: props.txt("Reset cache"),
    tabId: "resetCache",
    className: `nav-tab-link-reset${props.resetting ? ' is-resetting pulse' : ''}`,
    handler: props.resetHandler,
    tabIndex: 5,
    icon: /*#__PURE__*/React.createElement("svg", {
      xmlns: "http://www.w3.org/2000/svg",
      "aria-hidden": "true",
      focusable: "false",
      viewBox: "0 0 489.645 489.645"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M460.656,132.911c-58.7-122.1-212.2-166.5-331.8-104.1c-9.4,5.2-13.5,16.6-8.3,27c5.2,9.4,16.6,13.5,27,8.3 c99.9-52,227.4-14.9,276.7,86.3c65.4,134.3-19,236.7-87.4,274.6c-93.1,51.7-211.2,17.4-267.6-70.7l69.3,14.5 c10.4,2.1,21.8-4.2,23.9-15.6c2.1-10.4-4.2-21.8-15.6-23.9l-122.8-25c-20.6-2-25,16.6-23.9,22.9l15.6,123.8 c1,10.4,9.4,17.7,19.8,17.7c12.8,0,20.8-12.5,19.8-23.9l-6-50.5c57.4,70.8,170.3,131.2,307.4,68.2 C414.856,432.511,548.256,314.811,460.656,132.911z"
    }))
  }), props.allow.realtime && /*#__PURE__*/React.createElement("div", {
    label: props.txt(`${props.realtime ? 'Disable' : 'Enable'} real-time update`),
    tabId: "toggleRealtime",
    className: `nav-tab-link-realtime${props.realtime ? ' live-update activated' : ''}`,
    handler: props.realtimeHandler,
    tabIndex: 6,
    icon: /*#__PURE__*/React.createElement("svg", {
      xmlns: "http://www.w3.org/2000/svg",
      "aria-hidden": "true",
      focusable: "false",
      viewBox: "0 0 489.698 489.698"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M468.999,227.774c-11.4,0-20.8,8.3-20.8,19.8c-1,74.9-44.2,142.6-110.3,178.9c-99.6,54.7-216,5.6-260.6-61l62.9,13.1 c10.4,2.1,21.8-4.2,23.9-15.6c2.1-10.4-4.2-21.8-15.6-23.9l-123.7-26c-7.2-1.7-26.1,3.5-23.9,22.9l15.6,124.8 c1,10.4,9.4,17.7,19.8,17.7c15.5,0,21.8-11.4,20.8-22.9l-7.3-60.9c101.1,121.3,229.4,104.4,306.8,69.3 c80.1-42.7,131.1-124.8,132.1-215.4C488.799,237.174,480.399,227.774,468.999,227.774z"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M20.599,261.874c11.4,0,20.8-8.3,20.8-19.8c1-74.9,44.2-142.6,110.3-178.9c99.6-54.7,216-5.6,260.6,61l-62.9-13.1 c-10.4-2.1-21.8,4.2-23.9,15.6c-2.1,10.4,4.2,21.8,15.6,23.9l123.8,26c7.2,1.7,26.1-3.5,23.9-22.9l-15.6-124.8 c-1-10.4-9.4-17.7-19.8-17.7c-15.5,0-21.8,11.4-20.8,22.9l7.2,60.9c-101.1-121.2-229.4-104.4-306.8-69.2 c-80.1,42.6-131.1,124.8-132.2,215.3C0.799,252.574,9.199,261.874,20.599,261.874z"
    }))
  }));
}
function ThemeSwitcher(props) {
  const themeOrder = ['light', 'dark', 'system'];
  const index = Math.max(0, themeOrder.indexOf(props.theme));
  const set = t => props.onThemeChange && props.onThemeChange(t);
  const btn = (t, icon, label) => /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: `theme-toggle-btn${props.theme === t ? ' active' : ''}`,
    "aria-pressed": props.theme === t,
    "aria-label": props.txt(label),
    title: props.txt(label),
    onClick: () => set(t)
  }, icon, /*#__PURE__*/React.createElement("span", {
    className: "sr-only"
  }, props.txt(label)));
  const SunIcon = /*#__PURE__*/React.createElement("svg", {
    width: "18",
    height: "18",
    viewBox: "0 0 24 24",
    fill: "currentColor",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M6.76 4.84l-1.8-1.79-1.41 1.41 1.79 1.8 1.42-1.42zm10.45 12.02l1.79 1.8 1.41-1.41-1.8-1.79-1.4 1.4zM12 4V1h-0v3h0zm0 19v-3h0v3h0zM4 12H1v0h3v0zm19 0h-3v0h3v0zM6.76 19.16l-1.42 1.42-1.79-1.8 1.41-1.41 1.8 1.79zM17.24 4.84l1.4-1.4 1.8 1.79-1.41 1.41-1.79-1.8zM12 6a6 6 0 100 12 6 6 0 000-12z"
  }));
  const MoonIcon = /*#__PURE__*/React.createElement("svg", {
    width: "18",
    height: "18",
    viewBox: "0 0 24 24",
    fill: "currentColor",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"
  }));
  const LaptopIcon = /*#__PURE__*/React.createElement("svg", {
    width: "18",
    height: "18",
    viewBox: "0 0 24 24",
    fill: "currentColor",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M4 5h16a1 1 0 011 1v9H3V6a1 1 0 011-1zm-2 12h20a1 1 0 01-1 1H3a1 1 0 01-1-1z"
  }));
  return /*#__PURE__*/React.createElement("div", {
    className: "theme-switcher",
    "aria-label": props.txt('Theme')
  }, /*#__PURE__*/React.createElement("div", {
    className: "theme-toggle",
    role: "radiogroup",
    "aria-label": props.txt('Theme')
  }, /*#__PURE__*/React.createElement("div", {
    className: "theme-toggle-slider",
    style: {
      transform: `translateX(${index * 100}%)`
    }
  }), btn('light', SunIcon, 'Light'), btn('dark', MoonIcon, 'Dark'), btn('system', LaptopIcon, 'System')));
}
class Tabs extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "onClickTabItem", tab => {
      this.setState({
        activeTab: tab
      });
    });
    this.state = {
      activeTab: this.props.children[0].props.label
    };
  }
  render() {
    const {
      onClickTabItem,
      state: {
        activeTab
      }
    } = this;
    const children = this.props.children.filter(Boolean);
    console.log(this.props);
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("header", null, /*#__PURE__*/React.createElement(ThemeSwitcher, {
      theme: this.props.theme,
      onThemeChange: this.props.onThemeChange,
      txt: this.props.txt
    }), /*#__PURE__*/React.createElement("nav", null, /*#__PURE__*/React.createElement("ul", {
      className: "nav-tab-list"
    }, children.map(child => {
      const {
        tabId,
        label,
        className,
        handler,
        tabIndex,
        icon
      } = child.props;
      return /*#__PURE__*/React.createElement(Tab, {
        activeTab: activeTab,
        key: tabId,
        label: label,
        onClick: handler || onClickTabItem,
        className: className,
        tabIndex: tabIndex,
        tabId: tabId,
        icon: icon
      });
    })))), /*#__PURE__*/React.createElement("main", null, /*#__PURE__*/React.createElement("div", {
      className: "tab-content"
    }, children.map(child => /*#__PURE__*/React.createElement("div", {
      key: child.props.label,
      style: {
        display: child.props.label === activeTab ? 'block' : 'none'
      },
      id: `${child.props.tabId}-content`
    }, child.props.children)))));
  }
}
class Tab extends React.Component {
  constructor(...args) {
    super(...args);
    _defineProperty(this, "onClick", () => {
      const {
        label,
        onClick
      } = this.props;
      onClick(label);
    });
  }
  render() {
    const {
      onClick,
      props: {
        activeTab,
        label,
        tabIndex,
        tabId,
        icon
      }
    } = this;
    let className = 'nav-tab';
    if (this.props.className) {
      className += ` ${this.props.className}`;
    }
    if (activeTab === label) {
      className += ' active';
    }
    return /*#__PURE__*/React.createElement("li", {
      className: className,
      onClick: onClick,
      tabIndex: tabIndex,
      role: "tab",
      "aria-controls": `${tabId}-content`
    }, icon, label);
  }
}
function OverviewCounts(props) {
  if (props.overview === false) {
    return /*#__PURE__*/React.createElement("p", {
      className: "file-cache-only",
      dangerouslySetInnerHTML: {
        __html: props.txt(`You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.`)
      }
    });
  }
  const graphList = [{
    id: 'memoryUsageCanvas',
    title: props.txt('memory'),
    show: props.highlight.memory,
    value: props.overview.used_memory_percentage
  }, {
    id: 'hitRateCanvas',
    title: props.txt('hit rate'),
    show: props.highlight.hits,
    value: props.overview.hit_rate_percentage
  }, {
    id: 'keyUsageCanvas',
    title: props.txt('keys'),
    show: props.highlight.keys,
    value: props.overview.used_key_percentage
  }, {
    id: 'jitUsageCanvas',
    title: props.txt('jit buffer'),
    show: props.highlight.jit,
    value: props.overview.jit_buffer_used_percentage
  }];
  return /*#__PURE__*/React.createElement("div", {
    id: "counts",
    className: "tab-content-overview-counts"
  }, graphList.map(graph => {
    if (!graph.show) {
      return null;
    }
    return /*#__PURE__*/React.createElement("div", {
      className: "widget-panel",
      key: graph.id
    }, /*#__PURE__*/React.createElement("h3", {
      className: "widget-header"
    }, graph.title), /*#__PURE__*/React.createElement(UsageGraph, {
      charts: props.useCharts,
      value: graph.value,
      gaugeId: graph.id
    }));
  }), /*#__PURE__*/React.createElement(MemoryUsagePanel, {
    total: props.overview.readable.total_memory,
    used: props.overview.readable.used_memory,
    free: props.overview.readable.free_memory,
    wasted: props.overview.readable.wasted_memory,
    preload: props.overview.readable.preload_memory || null,
    wastedPercent: props.overview.wasted_percentage,
    jitBuffer: props.overview.readable.jit_buffer_size || null,
    jitBufferFree: props.overview.readable.jit_buffer_free || null,
    jitBufferFreePercentage: props.overview.jit_buffer_used_percentage || null,
    txt: props.txt
  }), /*#__PURE__*/React.createElement(StatisticsPanel, {
    num_cached_scripts: props.overview.readable.num_cached_scripts,
    hits: props.overview.readable.hits,
    misses: props.overview.readable.misses,
    blacklist_miss: props.overview.readable.blacklist_miss,
    num_cached_keys: props.overview.readable.num_cached_keys,
    max_cached_keys: props.overview.readable.max_cached_keys,
    txt: props.txt
  }), props.overview.readable.interned && /*#__PURE__*/React.createElement(InternedStringsPanel, {
    buffer_size: props.overview.readable.interned.buffer_size,
    strings_used_memory: props.overview.readable.interned.strings_used_memory,
    strings_free_memory: props.overview.readable.interned.strings_free_memory,
    number_of_strings: props.overview.readable.interned.number_of_strings,
    txt: props.txt
  }));
}
function GeneralInfo(props) {
  return /*#__PURE__*/React.createElement("table", {
    className: "tables general-info-table"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
    colSpan: "2"
  }, props.txt('General info')))), /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Zend OPcache"), /*#__PURE__*/React.createElement("td", null, props.version.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "PHP"), /*#__PURE__*/React.createElement("td", null, props.version.php)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, props.txt('Host')), /*#__PURE__*/React.createElement("td", null, props.version.host)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, props.txt('Server Software')), /*#__PURE__*/React.createElement("td", null, props.version.server)), props.start ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, props.txt('Start time')), /*#__PURE__*/React.createElement("td", null, props.start)) : null, props.reset ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, props.txt('Last reset')), /*#__PURE__*/React.createElement("td", null, props.reset)) : null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, props.txt('JIT enabled')), /*#__PURE__*/React.createElement("td", null, props.txt(props.jit.enabled ? "Yes" : "No"), props.jit.reason && /*#__PURE__*/React.createElement("span", {
    dangerouslySetInnerHTML: {
      __html: ` (${props.jit.reason})`
    }
  })))));
}
function Directives(props) {
  let directiveList = directive => {
    return /*#__PURE__*/React.createElement("ul", {
      className: "directive-list"
    }, directive.v.map((item, key) => {
      return Array.isArray(item) ? /*#__PURE__*/React.createElement("li", {
        key: "sublist_" + key
      }, directiveList({
        v: item
      })) : /*#__PURE__*/React.createElement("li", {
        key: key
      }, item);
    }));
  };
  let directiveNodes = props.directives.map(function (directive) {
    let map = {
      'opcache.': '',
      '_': ' '
    };
    let dShow = directive.k.replace(/opcache\.|_/gi, function (matched) {
      return map[matched];
    });
    let vShow;
    if (directive.v === true || directive.v === false) {
      vShow = React.createElement('i', {}, props.txt(directive.v.toString()));
    } else if (directive.v === '') {
      vShow = React.createElement('i', {}, props.txt('no value'));
    } else {
      if (Array.isArray(directive.v)) {
        vShow = directiveList(directive);
      } else {
        vShow = directive.v;
      }
    }
    let directiveLink = name => {
      if (name === 'opcache.jit_max_recursive_returns') {
        return 'opcache.jit-max-recursive-return';
      }
      return ['opcache.file_update_protection', 'opcache.huge_code_pages', 'opcache.lockfile_path', 'opcache.opt_debug_level'].includes(name) ? name : name.replace(/_/g, '-');
    };
    return /*#__PURE__*/React.createElement("tr", {
      key: directive.k
    }, /*#__PURE__*/React.createElement("td", {
      title: props.txt('View {0} manual entry', directive.k)
    }, /*#__PURE__*/React.createElement("a", {
      href: 'https://php.net/manual/en/opcache.configuration.php#ini.' + directiveLink(directive.k),
      target: "_blank"
    }, dShow)), /*#__PURE__*/React.createElement("td", null, vShow));
  });
  return /*#__PURE__*/React.createElement("table", {
    className: "tables directives-table"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
    colSpan: "2"
  }, props.txt('Directives')))), /*#__PURE__*/React.createElement("tbody", null, directiveNodes));
}
function Functions(props) {
  return /*#__PURE__*/React.createElement("div", {
    id: "functions"
  }, /*#__PURE__*/React.createElement("table", {
    className: "tables"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, props.txt('Available functions')))), /*#__PURE__*/React.createElement("tbody", null, props.functions.map(f => /*#__PURE__*/React.createElement("tr", {
    key: f
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
    href: "https://php.net/" + f,
    title: props.txt('View manual page'),
    target: "_blank"
  }, f)))))));
}
function UsageGraph(props) {
  const percentage = Math.round(3.6 * props.value / 360 * 100);
  return props.charts ? /*#__PURE__*/React.createElement(ReactCustomizableProgressbar, {
    progress: percentage,
    radius: 100,
    strokeWidth: 30,
    trackStrokeWidth: 30,
    strokeColor: getComputedStyle(document.documentElement).getPropertyValue('--opcache-gui-graph-track-fill-color') || "#6CA6EF",
    trackStrokeColor: getComputedStyle(document.documentElement).getPropertyValue('--opcache-gui-graph-track-background-color') || "#CCC",
    gaugeId: props.gaugeId
  }) : /*#__PURE__*/React.createElement("p", {
    className: "widget-value"
  }, /*#__PURE__*/React.createElement("span", {
    className: "large"
  }, percentage), /*#__PURE__*/React.createElement("span", null, "%"));
}

/**
 * This component is from <https://github.com/martyan/react-customizable-progressbar/>
 * MIT License (MIT), Copyright (c) 2019 Martin Juzl
 */
class ReactCustomizableProgressbar extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "initAnimation", () => {
      this.setState({
        animationInited: true
      });
    });
    _defineProperty(this, "getProgress", () => {
      const {
        initialAnimation,
        progress
      } = this.props;
      const {
        animationInited
      } = this.state;
      return initialAnimation && !animationInited ? 0 : progress;
    });
    _defineProperty(this, "getStrokeDashoffset", strokeLength => {
      const {
        counterClockwise,
        inverse,
        steps
      } = this.props;
      const progress = this.getProgress();
      const progressLength = strokeLength / steps * (steps - progress);
      if (inverse) return counterClockwise ? 0 : progressLength - strokeLength;
      return counterClockwise ? -1 * progressLength : progressLength;
    });
    _defineProperty(this, "getStrokeDashArray", (strokeLength, circumference) => {
      const {
        counterClockwise,
        inverse,
        steps
      } = this.props;
      const progress = this.getProgress();
      const progressLength = strokeLength / steps * (steps - progress);
      if (inverse) return `${progressLength}, ${circumference}`;
      return counterClockwise ? `${strokeLength * (progress / 100)}, ${circumference}` : `${strokeLength}, ${circumference}`;
    });
    _defineProperty(this, "getTrackStrokeDashArray", (strokeLength, circumference) => {
      const {
        initialAnimation
      } = this.props;
      const {
        animationInited
      } = this.state;
      if (initialAnimation && !animationInited) return `0, ${circumference}`;
      return `${strokeLength}, ${circumference}`;
    });
    _defineProperty(this, "getExtendedWidth", () => {
      const {
        strokeWidth,
        pointerRadius,
        pointerStrokeWidth,
        trackStrokeWidth
      } = this.props;
      const pointerWidth = pointerRadius + pointerStrokeWidth;
      if (pointerWidth > strokeWidth && pointerWidth > trackStrokeWidth) return pointerWidth * 2;else if (strokeWidth > trackStrokeWidth) return strokeWidth * 2;else return trackStrokeWidth * 2;
    });
    _defineProperty(this, "getPointerAngle", () => {
      const {
        cut,
        counterClockwise,
        steps
      } = this.props;
      const progress = this.getProgress();
      return counterClockwise ? (360 - cut) / steps * (steps - progress) : (360 - cut) / steps * progress;
    });
    this.state = {
      animationInited: false
    };
  }
  componentDidMount() {
    const {
      initialAnimation,
      initialAnimationDelay
    } = this.props;
    if (initialAnimation) setTimeout(this.initAnimation, initialAnimationDelay);
  }
  render() {
    const {
      radius,
      pointerRadius,
      pointerStrokeWidth,
      pointerFillColor,
      pointerStrokeColor,
      fillColor,
      trackStrokeWidth,
      trackStrokeColor,
      trackStrokeLinecap,
      strokeColor,
      strokeWidth,
      strokeLinecap,
      rotate,
      cut,
      trackTransition,
      transition,
      progress
    } = this.props;
    const d = 2 * radius;
    const width = d + this.getExtendedWidth();
    const circumference = 2 * Math.PI * radius;
    const strokeLength = circumference / 360 * (360 - cut);
    return /*#__PURE__*/React.createElement("figure", {
      className: `graph-widget`,
      style: {
        width: `${width || 250}px`
      },
      "data-value": progress,
      id: this.props.guageId
    }, /*#__PURE__*/React.createElement("svg", {
      width: width,
      height: width,
      viewBox: `0 0 ${width} ${width}`,
      style: {
        transform: `rotate(${rotate}deg)`
      }
    }, trackStrokeWidth > 0 && /*#__PURE__*/React.createElement("circle", {
      cx: width / 2,
      cy: width / 2,
      r: radius,
      fill: "none",
      stroke: trackStrokeColor,
      strokeWidth: trackStrokeWidth,
      strokeDasharray: this.getTrackStrokeDashArray(strokeLength, circumference),
      strokeLinecap: trackStrokeLinecap,
      style: {
        transition: trackTransition
      }
    }), strokeWidth > 0 && /*#__PURE__*/React.createElement("circle", {
      cx: width / 2,
      cy: width / 2,
      r: radius,
      fill: fillColor,
      stroke: strokeColor,
      strokeWidth: strokeWidth,
      strokeDasharray: this.getStrokeDashArray(strokeLength, circumference),
      strokeDashoffset: this.getStrokeDashoffset(strokeLength),
      strokeLinecap: strokeLinecap,
      style: {
        transition
      }
    }), pointerRadius > 0 && /*#__PURE__*/React.createElement("circle", {
      cx: d,
      cy: "50%",
      r: pointerRadius,
      fill: pointerFillColor,
      stroke: pointerStrokeColor,
      strokeWidth: pointerStrokeWidth,
      style: {
        transformOrigin: '50% 50%',
        transform: `rotate(${this.getPointerAngle()}deg) translate(${this.getExtendedWidth() / 2}px)`,
        transition
      }
    })), /*#__PURE__*/React.createElement("figcaption", {
      className: `widget-value`
    }, progress, "%"));
  }
}
ReactCustomizableProgressbar.defaultProps = {
  radius: 100,
  progress: 0,
  steps: 100,
  cut: 0,
  rotate: -90,
  strokeWidth: 20,
  strokeColor: 'indianred',
  fillColor: 'none',
  strokeLinecap: 'round',
  transition: '.3s ease',
  pointerRadius: 0,
  pointerStrokeWidth: 20,
  pointerStrokeColor: 'indianred',
  pointerFillColor: 'white',
  trackStrokeColor: '#e6e6e6',
  trackStrokeWidth: 20,
  trackStrokeLinecap: 'round',
  trackTransition: '.3s ease',
  counterClockwise: false,
  inverse: false,
  initialAnimation: false,
  initialAnimationDelay: 0
};
function MemoryUsagePanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, props.txt('memory usage')), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('total memory'), ":"), " ", props.total), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('used memory'), ":"), " ", props.used), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('free memory'), ":"), " ", props.free), props.preload && /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('preload memory'), ":"), " ", props.preload), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('wasted memory'), ":"), " ", props.wasted, " (", props.wastedPercent, "%)"), props.jitBuffer && /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('jit buffer'), ":"), " ", props.jitBuffer), props.jitBufferFree && /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('jit buffer free'), ":"), " ", props.jitBufferFree, " (", 100 - props.jitBufferFreePercentage, "%)")));
}
function StatisticsPanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, props.txt('opcache statistics')), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('number of cached files'), ":"), " ", props.num_cached_scripts), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('number of hits'), ":"), " ", props.hits), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('number of misses'), ":"), " ", props.misses), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('blacklist misses'), ":"), " ", props.blacklist_miss), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('number of cached keys'), ":"), " ", props.num_cached_keys), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('max cached keys'), ":"), " ", props.max_cached_keys)));
}
function InternedStringsPanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, props.txt('interned strings usage')), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('buffer size'), ":"), " ", props.buffer_size), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('used memory'), ":"), " ", props.strings_used_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('free memory'), ":"), " ", props.strings_free_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, props.txt('number of strings'), ":"), " ", props.number_of_strings)));
}
class CachedFiles extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "setSearchTerm", debounce(searchTerm => {
      this.setState({
        searchTerm,
        refreshPagination: !this.state.refreshPagination
      });
    }, this.props.debounceRate));
    _defineProperty(this, "onPageChanged", currentPage => {
      this.setState({
        currentPage
      });
    });
    _defineProperty(this, "handleInvalidate", e => {
      e.preventDefault();
      if (this.props.realtime) {
        axios.get(window.location.pathname, {
          params: {
            invalidate_searched: this.state.searchTerm
          }
        }).then(response => {
          console.log('success: ', response.data);
        });
      } else {
        window.location.href = e.currentTarget.href;
      }
    });
    _defineProperty(this, "changeSort", e => {
      this.setState({
        [e.target.name]: e.target.value
      });
    });
    _defineProperty(this, "compareValues", (key, order = 'asc') => {
      return function innerSort(a, b) {
        if (!a.hasOwnProperty(key) || !b.hasOwnProperty(key)) {
          return 0;
        }
        const varA = typeof a[key] === 'string' ? a[key].toUpperCase() : a[key];
        const varB = typeof b[key] === 'string' ? b[key].toUpperCase() : b[key];
        let comparison = 0;
        if (varA > varB) {
          comparison = 1;
        } else if (varA < varB) {
          comparison = -1;
        }
        return order === 'desc' ? comparison * -1 : comparison;
      };
    });
    this.doPagination = typeof props.perPageLimit === "number" && props.perPageLimit > 0;
    this.state = {
      currentPage: 1,
      searchTerm: props.searchTerm,
      refreshPagination: 0,
      sortBy: `last_used_timestamp`,
      sortDir: `desc`
    };
  }
  render() {
    if (!this.props.allow.fileList) {
      return null;
    }
    if (this.props.allFiles.length === 0) {
      return /*#__PURE__*/React.createElement("p", {
        dangerouslySetInnerHTML: {
          __html: this.props.txt(`No files have been cached or you have <i>opcache.file_cache_only</i> turned on`)
        }
      });
    }
    const {
      searchTerm,
      currentPage
    } = this.state;
    const offset = (currentPage - 1) * this.props.perPageLimit;
    const filesInSearch = searchTerm ? this.props.allFiles.filter(file => {
      return !(file.full_path.indexOf(searchTerm) === -1);
    }) : this.props.allFiles;
    filesInSearch.sort(this.compareValues(this.state.sortBy, this.state.sortDir));
    const filesInPage = this.doPagination ? filesInSearch.slice(offset, offset + this.props.perPageLimit) : filesInSearch;
    const allFilesTotal = this.props.allFiles.length;
    const showingTotal = filesInSearch.length;
    const showing = showingTotal !== allFilesTotal ? ", {1} showing due to filter '{2}'" : "";
    return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("form", {
      action: "#"
    }, /*#__PURE__*/React.createElement("label", {
      htmlFor: "frmFilter"
    }, this.props.txt('Start typing to filter on script path')), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("input", {
      type: "text",
      name: "filter",
      id: "frmFilter",
      className: "file-filter",
      onChange: e => {
        this.setSearchTerm(e.target.value);
      }
    })), /*#__PURE__*/React.createElement("h3", null, this.props.txt(`{0} files cached${showing}`, allFilesTotal, showingTotal, this.state.searchTerm)), this.props.allow.invalidate && this.state.searchTerm && showingTotal !== allFilesTotal && /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: `?invalidate_searched=${encodeURIComponent(this.state.searchTerm)}`,
      onClick: this.handleInvalidate
    }, this.props.txt('Invalidate all matching files'))), /*#__PURE__*/React.createElement("div", {
      className: "paginate-filter"
    }, this.doPagination && /*#__PURE__*/React.createElement(Pagination, {
      totalRecords: filesInSearch.length,
      pageLimit: this.props.perPageLimit,
      pageNeighbours: 2,
      onPageChanged: this.onPageChanged,
      refresh: this.state.refreshPagination,
      txt: this.props.txt
    }), /*#__PURE__*/React.createElement("nav", {
      className: "filter",
      "aria-label": this.props.txt('Sort order')
    }, /*#__PURE__*/React.createElement("select", {
      name: "sortBy",
      onChange: this.changeSort,
      value: this.state.sortBy
    }, /*#__PURE__*/React.createElement("option", {
      value: "last_used_timestamp"
    }, this.props.txt('Last used')), /*#__PURE__*/React.createElement("option", {
      value: "last_modified"
    }, this.props.txt('Last modified')), /*#__PURE__*/React.createElement("option", {
      value: "full_path"
    }, this.props.txt('Path')), /*#__PURE__*/React.createElement("option", {
      value: "hits"
    }, this.props.txt('Number of hits')), /*#__PURE__*/React.createElement("option", {
      value: "memory_consumption"
    }, this.props.txt('Memory consumption'))), /*#__PURE__*/React.createElement("select", {
      name: "sortDir",
      onChange: this.changeSort,
      value: this.state.sortDir
    }, /*#__PURE__*/React.createElement("option", {
      value: "desc"
    }, this.props.txt('Descending')), /*#__PURE__*/React.createElement("option", {
      value: "asc"
    }, this.props.txt('Ascending'))))), /*#__PURE__*/React.createElement("table", {
      className: "tables cached-list-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, this.props.txt('Script')))), /*#__PURE__*/React.createElement("tbody", null, filesInPage.map((file, index) => {
      return /*#__PURE__*/React.createElement(CachedFile, _extends({
        key: file.full_path,
        canInvalidate: this.props.allow.invalidate,
        realtime: this.props.realtime,
        txt: this.props.txt
      }, file));
    }))));
  }
}
class CachedFile extends React.Component {
  constructor(...args) {
    super(...args);
    _defineProperty(this, "handleInvalidate", e => {
      e.preventDefault();
      if (this.props.realtime) {
        axios.get(window.location.pathname, {
          params: {
            invalidate: e.currentTarget.getAttribute('data-file')
          }
        }).then(response => {
          console.log('success: ', response.data);
        });
      } else {
        window.location.href = e.currentTarget.href;
      }
    });
  }
  render() {
    return /*#__PURE__*/React.createElement("tr", {
      "data-path": this.props.full_path.toLowerCase()
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: "file-pathname"
    }, this.props.full_path), /*#__PURE__*/React.createElement("span", {
      className: "file-metainfo"
    }, /*#__PURE__*/React.createElement("b", null, this.props.txt('hits'), ": "), /*#__PURE__*/React.createElement("span", null, this.props.readable.hits, ", "), /*#__PURE__*/React.createElement("b", null, this.props.txt('memory'), ": "), /*#__PURE__*/React.createElement("span", null, this.props.readable.memory_consumption, ", "), this.props.last_modified && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("b", null, this.props.txt('last modified'), ": "), /*#__PURE__*/React.createElement("span", null, this.props.last_modified, ", ")), /*#__PURE__*/React.createElement("b", null, this.props.txt('last used'), ": "), /*#__PURE__*/React.createElement("span", null, this.props.last_used)), !this.props.timestamp && /*#__PURE__*/React.createElement("span", {
      className: "invalid file-metainfo"
    }, " - ", this.props.txt('has been invalidated')), this.props.canInvalidate && /*#__PURE__*/React.createElement("span", null, ",\xA0", /*#__PURE__*/React.createElement("a", {
      className: "file-metainfo",
      href: '?invalidate=' + this.props.full_path,
      "data-file": this.props.full_path,
      onClick: this.handleInvalidate
    }, this.props.txt('force file invalidation')))));
  }
}
class IgnoredFiles extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "onPageChanged", currentPage => {
      this.setState({
        currentPage
      });
    });
    this.doPagination = typeof props.perPageLimit === "number" && props.perPageLimit > 0;
    this.state = {
      currentPage: 1,
      refreshPagination: 0
    };
  }
  render() {
    if (!this.props.allow.fileList) {
      return null;
    }
    if (this.props.allFiles.length === 0) {
      return /*#__PURE__*/React.createElement("p", null, this.props.txt('No files have been ignored via <i>opcache.blacklist_filename</i>'));
    }
    const {
      currentPage
    } = this.state;
    const offset = (currentPage - 1) * this.props.perPageLimit;
    const filesInPage = this.doPagination ? this.props.allFiles.slice(offset, offset + this.props.perPageLimit) : this.props.allFiles;
    const allFilesTotal = this.props.allFiles.length;
    return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h3", null, this.props.txt('{0} ignore file locations', allFilesTotal)), this.doPagination && /*#__PURE__*/React.createElement(Pagination, {
      totalRecords: allFilesTotal,
      pageLimit: this.props.perPageLimit,
      pageNeighbours: 2,
      onPageChanged: this.onPageChanged,
      refresh: this.state.refreshPagination,
      txt: this.props.txt
    }), /*#__PURE__*/React.createElement("table", {
      className: "tables ignored-list-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, this.props.txt('Path')))), /*#__PURE__*/React.createElement("tbody", null, filesInPage.map((file, index) => {
      return /*#__PURE__*/React.createElement("tr", {
        key: file
      }, /*#__PURE__*/React.createElement("td", null, file));
    }))));
  }
}
class PreloadedFiles extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "onPageChanged", currentPage => {
      this.setState({
        currentPage
      });
    });
    this.doPagination = typeof props.perPageLimit === "number" && props.perPageLimit > 0;
    this.state = {
      currentPage: 1,
      refreshPagination: 0
    };
  }
  render() {
    if (!this.props.allow.fileList) {
      return null;
    }
    if (this.props.allFiles.length === 0) {
      return /*#__PURE__*/React.createElement("p", null, this.props.txt('No files have been preloaded <i>opcache.preload</i>'));
    }
    const {
      currentPage
    } = this.state;
    const offset = (currentPage - 1) * this.props.perPageLimit;
    const filesInPage = this.doPagination ? this.props.allFiles.slice(offset, offset + this.props.perPageLimit) : this.props.allFiles;
    const allFilesTotal = this.props.allFiles.length;
    return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h3", null, this.props.txt('{0} preloaded files', allFilesTotal)), this.doPagination && /*#__PURE__*/React.createElement(Pagination, {
      totalRecords: allFilesTotal,
      pageLimit: this.props.perPageLimit,
      pageNeighbours: 2,
      onPageChanged: this.onPageChanged,
      refresh: this.state.refreshPagination,
      txt: this.props.txt
    }), /*#__PURE__*/React.createElement("table", {
      className: "tables preload-list-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, this.props.txt('Path')))), /*#__PURE__*/React.createElement("tbody", null, filesInPage.map((file, index) => {
      return /*#__PURE__*/React.createElement("tr", {
        key: file
      }, /*#__PURE__*/React.createElement("td", null, file));
    }))));
  }
}
class Pagination extends React.Component {
  constructor(props) {
    super(props);
    _defineProperty(this, "gotoPage", page => {
      const {
        onPageChanged = f => f
      } = this.props;
      const currentPage = Math.max(0, Math.min(page, this.totalPages()));
      this.setState({
        currentPage
      }, () => onPageChanged(currentPage));
    });
    _defineProperty(this, "totalPages", () => {
      return Math.ceil(this.props.totalRecords / this.props.pageLimit);
    });
    _defineProperty(this, "handleClick", (page, evt) => {
      evt.preventDefault();
      this.gotoPage(page);
    });
    _defineProperty(this, "handleJumpLeft", evt => {
      evt.preventDefault();
      this.gotoPage(this.state.currentPage - this.pageNeighbours * 2 - 1);
    });
    _defineProperty(this, "handleJumpRight", evt => {
      evt.preventDefault();
      this.gotoPage(this.state.currentPage + this.pageNeighbours * 2 + 1);
    });
    _defineProperty(this, "handleMoveLeft", evt => {
      evt.preventDefault();
      this.gotoPage(this.state.currentPage - 1);
    });
    _defineProperty(this, "handleMoveRight", evt => {
      evt.preventDefault();
      this.gotoPage(this.state.currentPage + 1);
    });
    _defineProperty(this, "range", (from, to, step = 1) => {
      let i = from;
      const range = [];
      while (i <= to) {
        range.push(i);
        i += step;
      }
      return range;
    });
    _defineProperty(this, "fetchPageNumbers", () => {
      const totalPages = this.totalPages();
      const pageNeighbours = this.pageNeighbours;
      const totalNumbers = this.pageNeighbours * 2 + 3;
      const totalBlocks = totalNumbers + 2;
      if (totalPages > totalBlocks) {
        let pages = [];
        const leftBound = this.state.currentPage - pageNeighbours;
        const rightBound = this.state.currentPage + pageNeighbours;
        const beforeLastPage = totalPages - 1;
        const startPage = leftBound > 2 ? leftBound : 2;
        const endPage = rightBound < beforeLastPage ? rightBound : beforeLastPage;
        pages = this.range(startPage, endPage);
        const pagesCount = pages.length;
        const singleSpillOffset = totalNumbers - pagesCount - 1;
        const leftSpill = startPage > 2;
        const rightSpill = endPage < beforeLastPage;
        const leftSpillPage = "LEFT";
        const rightSpillPage = "RIGHT";
        if (leftSpill && !rightSpill) {
          const extraPages = this.range(startPage - singleSpillOffset, startPage - 1);
          pages = [leftSpillPage, ...extraPages, ...pages];
        } else if (!leftSpill && rightSpill) {
          const extraPages = this.range(endPage + 1, endPage + singleSpillOffset);
          pages = [...pages, ...extraPages, rightSpillPage];
        } else if (leftSpill && rightSpill) {
          pages = [leftSpillPage, ...pages, rightSpillPage];
        }
        return [1, ...pages, totalPages];
      }
      return this.range(1, totalPages);
    });
    this.state = {
      currentPage: 1
    };
    this.pageNeighbours = typeof props.pageNeighbours === "number" ? Math.max(0, Math.min(props.pageNeighbours, 2)) : 0;
  }
  componentDidMount() {
    this.gotoPage(1);
  }
  componentDidUpdate(props) {
    const {
      refresh
    } = this.props;
    if (props.refresh !== refresh) {
      this.gotoPage(1);
    }
  }
  render() {
    if (!this.props.totalRecords || this.totalPages() === 1) {
      return null;
    }
    const {
      currentPage
    } = this.state;
    const pages = this.fetchPageNumbers();
    return /*#__PURE__*/React.createElement("nav", {
      "aria-label": "File list pagination"
    }, /*#__PURE__*/React.createElement("ul", {
      className: "pagination"
    }, pages.map((page, index) => {
      if (page === "LEFT") {
        return /*#__PURE__*/React.createElement(React.Fragment, {
          key: index
        }, /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": this.props.txt('Previous'),
          onClick: this.handleJumpLeft
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u219E"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, this.props.txt('Jump back')))), /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": this.props.txt('Previous'),
          onClick: this.handleMoveLeft
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21E0"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, this.props.txt('Previous page')))));
      }
      if (page === "RIGHT") {
        return /*#__PURE__*/React.createElement(React.Fragment, {
          key: index
        }, /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": this.props.txt('Next'),
          onClick: this.handleMoveRight
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21E2"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, this.props.txt('Next page')))), /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": this.props.txt('Next'),
          onClick: this.handleJumpRight
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21A0"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, this.props.txt('Jump forward')))));
      }
      return /*#__PURE__*/React.createElement("li", {
        key: index,
        className: "page-item"
      }, /*#__PURE__*/React.createElement("a", {
        className: `page-link${currentPage === page ? " active" : ""}`,
        href: "#",
        onClick: e => this.handleClick(page, e)
      }, page));
    })));
  }
}
function Footer(props) {
  return /*#__PURE__*/React.createElement("footer", {
    className: "main-footer"
  }, /*#__PURE__*/React.createElement("a", {
    className: "github-link",
    href: "https://github.com/amnuts/opcache-gui",
    target: "_blank",
    title: props.txt("opcache-gui (currently version {0}) on GitHub", props.version)
  }, /*#__PURE__*/React.createElement("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    "aria-hidden": "true",
    focusable: "false",
    width: "1.19em",
    height: "1em",
    viewBox: "0 0 1664 1408"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M640 960q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm640 0q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm160 0q0-120-69-204t-187-84q-41 0-195 21q-71 11-157 11t-157-11q-152-21-195-21q-118 0-187 84t-69 204q0 88 32 153.5t81 103t122 60t140 29.5t149 7h168q82 0 149-7t140-29.5t122-60t81-103t32-153.5zm224-176q0 207-61 331q-38 77-105.5 133t-141 86t-170 47.5t-171.5 22t-167 4.5q-78 0-142-3t-147.5-12.5t-152.5-30t-137-51.5t-121-81t-86-115Q0 992 0 784q0-237 136-396q-27-82-27-170q0-116 51-218q108 0 190 39.5T539 163q147-35 309-35q148 0 280 32q105-82 187-121t189-39q51 102 51 218q0 87-27 168q136 160 136 398z"
  })), " https://github.com/amnuts/opcache-gui - ", props.txt("version {0}", props.version)), /*#__PURE__*/React.createElement("a", {
    className: "sponsor-link",
    href: "https://github.com/sponsors/amnuts",
    target: "_blank",
    title: props.txt("Sponsor this project and author on GitHub")
  }, /*#__PURE__*/React.createElement("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    width: "24",
    height: "24",
    viewBox: "0 0 24 24"
  }, /*#__PURE__*/React.createElement("path", {
    fill: "crimson",
    d: "M12 21.35l-1.45-1.32c-5.15-4.67-8.55-7.75-8.55-11.53 0-3.08 2.42-5.5 5.5-5.5 1.74 0 3.41.81 4.5 2.09 1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.42 5.5 5.5 0 3.78-3.4 6.86-8.55 11.54l-1.45 1.31z"
  })), " ", props.txt("Sponsor this project")));
}
function debounce(func, wait, immediate) {
  let timeout;
  wait = wait || 250;
  return function () {
    let context = this,
      args = arguments;
    let later = function () {
      timeout = null;
      if (!immediate) {
        func.apply(context, args);
      }
    };
    let callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow) {
      func.apply(context, args);
    }
  };
}

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
        realtimeRefresh: <?= json_encode($opcache->getOption('refresh_time')); ?>,
        language: <?= json_encode($opcache->getOption('language_pack')); ?>,
    }), document.getElementById('interface'));

    </script>

</body>
</html>