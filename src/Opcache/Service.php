<?php

namespace Amnuts\Opcache;

class Service
{
    const VERSION = '3.2.1';

    protected $data;
    protected $options;
    protected $optimizationLevels;
    protected $defaults = [
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
        } else if (isset($_GET['invalidate']) && $this->getOption('allow_invalidate')) {
            $response($this->resetCache($_GET['invalidate']));
        } else if (isset($_GET['invalidate_searched']) && $this->getOption('allow_invalidate')) {
            $response($this->resetSearched($_GET['invalidate_searched']));
        } else if (isset($_GET['invalidate_searched']) && $this->getOption('allow_invalidate')) {
            $response($this->resetSearched($_GET['invalidate_searched']));
        } else if ($this->isJsonRequest() && $this->getOption('allow_realtime')) {
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
     * @param string $search
     * @return bool
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
                return $a['hits'] <=> $b['hits'];
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

        $directives = [];
        ksort($config['directives']);
        foreach ($config['directives'] as $k => $v) {
            if (in_array($k, ['opcache.max_file_size', 'opcache.memory_consumption']) && $v) {
                $v = $this->size($v) . " ({$v})";
            } elseif ($k == 'opcache.optimization_level') {
                $levels = [];
                foreach ($this->optimizationLevels as $level => $info) {
                    if ($level & $v) {
                        $levels[] = "{$info} [{$level}]";
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
            'functions' => get_extension_funcs('Zend OPcache')
        ];
    }
}
