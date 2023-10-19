<?php

namespace Amnuts\Opcache;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class Service
{
    public const VERSION = '3.5.3';

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

        if ($overview && !empty($status['jit'])) {
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
