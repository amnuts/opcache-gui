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
    'per_page'         => 200,           // How many results per page to show in the file list, false for no pagination
    'cookie_name'      => 'opcachegui',  // name of cookie
    'cookie_ttl'       => 365,           // days to store cookie
    'highlight'        => [
        'memory' => true,                // show the memory chart/big number
        'hits'   => true,                // show the hit rate chart/big number
        'keys'   => true                 // show the keys used chart/big number
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
        if (!empty($_SERVER['HTTP_ACCEPT'])
            && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        ) {
            if (isset($_GET['reset']) && $this->getOption('allow_reset')) {
                echo '{ "success": "' . ($this->resetCache() ? 'yes' : 'no') . '" }';
            } else if (isset($_GET['invalidate']) && $this->getOption('allow_invalidate')) {
                echo '{ "success": "' . ($this->resetCache($_GET['invalidate']) ? 'yes' : 'no') . '" }';
            } else if ($this->getOption('allow_realtime')) {
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
                ),
                'gui' => self::VERSION
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
    <title>OPcache statistics on <?= $opcache->getData('version', 'host'); ?></title>
    <script src="https://unpkg.com/react@16/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@16/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js" crossorigin></script>
    <style type="text/css">
        .opcache-gui{font-family:sans-serif;font-size:90%;padding:0;margin:0}.opcache-gui .hide{display:none}.opcache-gui .sr-only{border:0 !important;clip:rect(1px, 1px, 1px, 1px) !important;-webkit-clip-path:inset(50%) !important;clip-path:inset(50%) !important;height:1px !important;margin:-1px !important;overflow:hidden !important;padding:0 !important;position:absolute !important;width:1px !important;white-space:nowrap !important}.opcache-gui .main-nav{padding-top:20px}.opcache-gui .nav-tab-list{list-style-type:none;padding-left:8px;margin:0;border-bottom:1px solid #CCC}.opcache-gui .nav-tab{display:inline-block;margin:0 0 -1px 0;padding:15px 30px;border:1px solid transparent;border-bottom-color:#CCC;text-decoration:none;background-color:#fff}.opcache-gui .nav-tab:hover{background-color:#F4F4F4;text-decoration:underline}.opcache-gui .nav-tab.active{border:1px solid #CCC;border-bottom-color:#fff;border-top:3px solid #6CA6EF}.opcache-gui .nav-tab.active:hover{background-color:initial}.opcache-gui .nav-tab-link-reset{padding-left:50px;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" focusable="false" width="1.5em" height="1.5em" viewBox="0 0 24 24"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z" fill="rgb(98, 98, 98)"/></svg>')}.opcache-gui .nav-tab-link-realtime{position:relative;padding-left:50px;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" focusable="false" width="1.5em" height="1.5em" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8s8 3.58 8 8s-3.58 8-8 8z" fill="rgb(98, 98, 98)"/><path d="M12.5 7H11v6l5.25 3.15l.75-1.23l-4.5-2.67z" fill="rgb(98, 98, 98)"/></svg>')}.opcache-gui .nav-tab-link-realtime.live-update{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" focusable="false" width="1.5em" height="1.5em" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8s8 3.58 8 8s-3.58 8-8 8z" fill="rgb(0, 186, 0)"/><path d="M12.5 7H11v6l5.25 3.15l.75-1.23l-4.5-2.67z" fill="rgb(0, 186, 0)"/></svg>')}.opcache-gui .nav-tab-link-realtime.pulse::before{content:"";position:absolute;top:12px;left:25px;width:18px;height:18px;z-index:10;opacity:0;background-color:transparent;border:2px solid #00ba00;border-radius:100%;animation:pulse 2s linear infinite}.opcache-gui .tab-content{padding:2em}.opcache-gui .tab-content-overview-counts{width:270px;float:right}.opcache-gui .tab-content-overview-info{margin-right:280px}.opcache-gui .graph-widget{display:block;max-width:100%;height:auto;margin:0 auto}.opcache-gui .widget-panel{background-color:#EDEDED;margin-bottom:10px}.opcache-gui .widget-header{background-color:#CDCDCD;padding:4px 6px;margin:0;text-align:center;font-size:1rem;font-weight:bold}.opcache-gui .widget-value{margin:0;text-align:center}.opcache-gui .widget-value span.large{color:#6CA6EF;font-size:80pt;margin:0;padding:0;text-align:center}.opcache-gui .widget-value span.large+span{font-size:20pt;margin:0;color:#6CA6EF}.opcache-gui .widget-info{margin:0;padding:10px}.opcache-gui .widget-info *{margin:0;line-height:1.75em;text-align:left}.opcache-gui .tables{margin:0 0 1em 0;border-collapse:collapse;width:100%;table-layout:fixed}.opcache-gui .tables tr:nth-child(odd){background-color:#EFFEFF}.opcache-gui .tables tr:nth-child(even){background-color:#E0ECEF}.opcache-gui .tables th{text-align:left;padding:6px;background-color:#6CA6EF;color:#fff;border-color:#fff;font-weight:normal}.opcache-gui .tables td{padding:4px 6px;line-height:1.4em;vertical-align:top;border-color:#fff;overflow:hidden;overflow-wrap:break-word;text-overflow:ellipsis}.opcache-gui .tables.file-list-table tr{background-color:#EFFEFF}.opcache-gui .tables.file-list-table tr.alternate{background-color:#E0ECEF}.opcache-gui .file-filter{width:520px}.opcache-gui .file-metainfo{font-size:80%}.opcache-gui .file-metainfo.invalid{font-style:italic}.opcache-gui .file-pathname{width:70%;display:block}.opcache-gui .nav-tab-link-reset,.opcache-gui .nav-tab-link-realtime,.opcache-gui .github-link{background-repeat:no-repeat;background-color:transparent}.opcache-gui .nav-tab-link-reset,.opcache-gui .nav-tab-link-realtime{background-position:24px 50%}.opcache-gui .github-link{background-position:5px 50%}.opcache-gui .main-footer{border-top:1px solid #CCC;padding:1em 2em}.opcache-gui .github-link{background-position:0 50%;padding:2em 0 2em 2.3em;text-decoration:none;opacity:0.7;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" focusable="false" width="1.19em" height="1em" viewBox="0 0 1664 1408"><path d="M640 960q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm640 0q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm160 0q0-120-69-204t-187-84q-41 0-195 21q-71 11-157 11t-157-11q-152-21-195-21q-118 0-187 84t-69 204q0 88 32 153.5t81 103t122 60t140 29.5t149 7h168q82 0 149-7t140-29.5t122-60t81-103t32-153.5zm224-176q0 207-61 331q-38 77-105.5 133t-141 86t-170 47.5t-171.5 22t-167 4.5q-78 0-142-3t-147.5-12.5t-152.5-30t-137-51.5t-121-81t-86-115Q0 992 0 784q0-237 136-396q-27-82-27-170q0-116 51-218q108 0 190 39.5T539 163q147-35 309-35q148 0 280 32q105-82 187-121t189-39q51 102 51 218q0 87-27 168q136 160 136 398z" fill="rgb(98, 98, 98)"/></svg>');font-size:80%}.opcache-gui .github-link:hover{opacity:1}.opcache-gui .file-cache-only{margin-top:0}.opcache-gui .pagination{margin:10px 0;padding:0}.opcache-gui .pagination li{display:inline-block}.opcache-gui .pagination li a{display:inline-block;display:inline-flex;align-items:center;white-space:nowrap;line-height:1;padding:0.5rem 0.75rem;border-radius:3px;text-decoration:none;height:100%}.opcache-gui .pagination li a.arrow{font-size:1.1rem}.opcache-gui .pagination li a:active{transform:translateY(2px)}.opcache-gui .pagination li a.active{background-color:#4d75af;color:#fff}.opcache-gui .pagination li a:hover:not(.active){background-color:#FF7400;color:#fff}@media screen and (max-width: 750px){.opcache-gui .nav-tab-list{border-bottom:0}.opcache-gui .nav-tab{display:block;margin:0}.opcache-gui .nav-tab-link{display:block;margin:0 10px;padding:10px 0 10px 30px;border:0}.opcache-gui .nav-tab-link[data-for].active{border-bottom-color:#CCC}.opcache-gui .tab-content-overview-info{margin-right:auto;clear:both}.opcache-gui .tab-content-overview-counts{position:relative;display:block;width:100%}}@media screen and (max-width: 550px){.opcache-gui .file-filter{width:100%}}@keyframes pulse{0%{transform:scale(1);opacity:1}50%,100%{transform:scale(2);opacity:0}}
    </style>
</head>

<body style="padding: 0; margin: 0;">

    <div class="opcache-gui" id="interface" />

    <script type="text/javascript">

    function _extends() { _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; }; return _extends.apply(this, arguments); }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

class Interface extends React.Component {
  constructor(props) {
    super(props);

    _defineProperty(this, "startTimer", () => {
      this.setState({
        realtime: true
      });
      this.polling = setInterval(() => {
        this.setState({
          fetching: true
        });
        axios.get('#', {
          time: Date.now()
        }).then(response => {
          console.log(response.data);
          this.setState({
            opstate: response.data
          });
        });
      }, this.props.realtimeRefresh * 1000);
    });

    _defineProperty(this, "stopTimer", () => {
      this.setState({
        realtime: false
      });
      clearInterval(this.polling);
    });

    _defineProperty(this, "realtimeHandler", () => {
      const realtime = !this.state.realtime;

      if (!realtime) {
        this.stopTimer();
      } else {
        this.startTimer();
      }
    });

    this.state = {
      realtime: false,
      opstate: props.opstate
    };
    this.polling = false;
  }

  render() {
    const {
      opstate,
      realtimeRefresh,
      ...otherProps
    } = this.props;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("header", null, /*#__PURE__*/React.createElement(MainNavigation, _extends({}, otherProps, {
      opstate: this.state.opstate,
      realtime: this.state.realtime,
      realtimeHandler: this.realtimeHandler
    }))), /*#__PURE__*/React.createElement(Footer, this.props));
  }

}

class MainNavigation extends React.Component {
  constructor(props) {
    super(props);
  }

  renderOverview() {
    return /*#__PURE__*/React.createElement("div", {
      label: "Overview",
      tabId: "overview"
    }, /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(OverviewCounts, this.props), /*#__PURE__*/React.createElement("div", {
      id: "info",
      className: "tab-content-overview-info"
    }, /*#__PURE__*/React.createElement(GeneralInfo, this.props), /*#__PURE__*/React.createElement(Directives, this.props), /*#__PURE__*/React.createElement(Functions, this.props))));
  }

  renderFileList() {
    if (this.props.allow.filelist) {
      return /*#__PURE__*/React.createElement("div", {
        label: "Files",
        tabId: "files"
      }, /*#__PURE__*/React.createElement(Files, this.props));
    }

    return null;
  }

  renderReset() {
    if (this.props.allow.reset) {
      return /*#__PURE__*/React.createElement("div", {
        label: "Reset cache",
        tabId: "resetCache",
        className: "nav-tab-link-reset",
        handler: () => {
          window.location.href = '?reset=1';
        }
      });
    }

    return null;
  }

  renderRealtime() {
    if (this.props.allow.realtime) {
      return /*#__PURE__*/React.createElement("div", {
        label: "Enable real-time update",
        tabId: "toggleRealtime",
        className: `nav-tab-link-realtime${this.props.realtime ? ' live-update pulse' : ''}`,
        handler: this.props.realtimeHandler
      });
    }

    return null;
  }

  render() {
    return /*#__PURE__*/React.createElement("nav", {
      className: "main-nav"
    }, /*#__PURE__*/React.createElement(Tabs, null, this.renderOverview(), this.renderFileList(), this.renderReset(), this.renderRealtime()));
  }

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
      props: {
        children
      },
      state: {
        activeTab
      }
    } = this;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("ul", {
      className: "nav-tab-list"
    }, children.map(child => {
      const {
        tabId,
        label,
        className,
        handler
      } = child.props;
      return /*#__PURE__*/React.createElement(Tab, {
        activeTab: activeTab,
        key: tabId,
        label: label,
        onClick: handler || onClickTabItem,
        className: className
      });
    })), /*#__PURE__*/React.createElement("div", {
      className: "tab-content"
    }, children.map(child => /*#__PURE__*/React.createElement("div", {
      key: child.props.label,
      style: {
        display: child.props.label === activeTab ? 'block' : 'none'
      }
    }, child.props.children))));
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
        label
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
      onClick: onClick
    }, label);
  }

}

class OverviewCounts extends React.Component {
  constructor(props) {
    super(props);
    this.overview = props.opstate.overview;
    this.readable = props.opstate.overview.readable;
  }

  renderInternedStrings() {
    if (!this.readable.interned) {
      return null;
    }

    return /*#__PURE__*/React.createElement(InternedStringsPanel, {
      buffer_size: this.readable.interned.buffer_size,
      strings_used_memory: this.readable.interned.strings_used_memory,
      strings_free_memory: this.readable.interned.strings_free_memory,
      number_of_strings: this.readable.interned.number_of_strings
    });
  }

  renderGraphs(useCharts, graphList) {
    return graphList.map(graph => {
      if (!graph.show) {
        return null;
      }

      return /*#__PURE__*/React.createElement("div", {
        className: "widget-panel",
        key: graph.id
      }, /*#__PURE__*/React.createElement("h3", {
        className: "widget-header"
      }, graph.title), /*#__PURE__*/React.createElement("p", {
        className: "widget-value"
      }, /*#__PURE__*/React.createElement(UsageGraph, {
        charts: useCharts,
        value: graph.value,
        gaugeId: graph.id
      })));
    });
  }

  render() {
    if (this.overview === false) {
      return /*#__PURE__*/React.createElement("p", {
        class: "file-cache-only"
      }, "You have ", /*#__PURE__*/React.createElement("i", null, "opcache.file_cache_only"), " turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by ", /*#__PURE__*/React.createElement("i", null, "opcache_get_statistics()"), ".");
    }

    return /*#__PURE__*/React.createElement("div", {
      id: "counts",
      className: "tab-content-overview-counts"
    }, this.renderGraphs(this.props.useCharts, [{
      id: 'memoryUsageCanvas',
      title: 'memory',
      show: this.props.highlight.memory,
      value: this.props.opstate.overview.used_memory_percentage
    }, {
      id: 'hitRateCanvas',
      title: 'hit rate',
      show: this.props.highlight.hits,
      value: this.props.opstate.overview.hit_rate_percentage
    }, {
      id: 'keyUsageCanvas',
      title: 'keys',
      show: this.props.highlight.keys,
      value: this.props.opstate.overview.used_key_percentage
    }]), /*#__PURE__*/React.createElement(MemoryUsagePanel, {
      total: this.readable.total_memory,
      used: this.readable.used_memory,
      free: this.readable.free_memory,
      wasted: this.readable.wasted_memory,
      wastedPercent: this.overview.wasted_percentage
    }), /*#__PURE__*/React.createElement(StatisticsPanel, {
      num_cached_scripts: this.readable.num_cached_scripts,
      hits: this.readable.hits,
      misses: this.readable.misses,
      blacklist_miss: this.readable.blacklist_miss,
      num_cached_keys: this.readable.num_cached_keys,
      max_cached_keys: this.readable.max_cached_keys
    }), this.renderInternedStrings());
  }

}

class GeneralInfo extends React.Component {
  constructor(props) {
    super(props);
    this.start = props.opstate.overview ? props.opstate.overview.readable.start_time : null;
    this.reset = props.opstate.overview ? props.opstate.overview.readable.last_restart_time : null;
  }

  renderStart() {
    return this.start === null ? null : /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Start time"), /*#__PURE__*/React.createElement("td", null, this.start));
  }

  renderReset() {
    return this.reset === null ? null : /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Last reset"), /*#__PURE__*/React.createElement("td", null, this.reset));
  }

  render() {
    return /*#__PURE__*/React.createElement("table", {
      className: "tables general-info-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
      colSpan: "2"
    }, "General info"))), /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Zend OPcache"), /*#__PURE__*/React.createElement("td", null, this.props.opstate.version.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "PHP"), /*#__PURE__*/React.createElement("td", null, this.props.opstate.version.php)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Host"), /*#__PURE__*/React.createElement("td", null, this.props.opstate.version.host)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, "Server Software"), /*#__PURE__*/React.createElement("td", null, this.props.opstate.version.server)), this.renderStart(), this.renderReset()));
  }

}

class Directives extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    let directiveNodes = this.props.opstate.directives.map(function (directive) {
      let map = {
        'opcache.': '',
        '_': ' '
      };
      let dShow = directive.k.replace(/opcache\.|_/gi, function (matched) {
        return map[matched];
      });
      let vShow;

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

}

function Functions(props) {
  return /*#__PURE__*/React.createElement("div", {
    id: "functions"
  }, /*#__PURE__*/React.createElement("table", {
    className: "tables"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Available functions"))), /*#__PURE__*/React.createElement("tbody", null, props.opstate.functions.map(f => /*#__PURE__*/React.createElement("tr", {
    key: f
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
    href: "http://php.net/" + f,
    title: "View manual page",
    target: "_blank"
  }, f)))))));
}

function UsageGraph(props) {
  return props.charts ? /*#__PURE__*/React.createElement(Canvas, {
    value: props.value,
    gaugeId: props.gaugeId
  }) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", {
    className: "large"
  }, props.value), /*#__PURE__*/React.createElement("span", null, "%"));
}

class Canvas extends React.Component {
  constructor(props) {
    super(props);
    this.saveContext = this.saveContext.bind(this);
    this.animate = this.animate.bind(this);
    this.draw = this.draw.bind(this);
    this.loop = null;
    this.state = {
      degrees: 0,
      newdegs: 0
    };
  }

  saveContext(ctx) {
    this.ctx = ctx;
    this.width = this.ctx.canvas.width;
    this.height = this.ctx.canvas.height;
    this.gaugeColour = this.props.colour || '#6ca6ef';
    this.gaugeBackgroundColour = this.props.bgcolour || '#e2e2e2';
    this.loop = null;
  }

  animate() {
    const {
      degrees,
      newdegs
    } = this.state;

    if (degrees == newdegs) {
      clearInterval(this.loop);
    }

    this.setState({
      degrees: degrees + (degrees < newdegs ? 1 : -1)
    });
  }

  draw() {
    if (typeof this.loop != 'undefined') {
      clearInterval(this.loop);
    }

    this.loop = setInterval(this.animate, 1000 / (this.state.newdegs - this.state.degrees));
  }

  componentDidUpdate() {
    const {
      degrees
    } = this.state;
    const text = Math.round(degrees / 360 * 100) + '%';
    this.ctx.clearRect(0, 0, this.width, this.height);
    this.ctx.beginPath();
    this.ctx.strokeStyle = this.gaugeBackgroundColour;
    this.ctx.lineWidth = 30;
    this.ctx.arc(this.width / 2, this.height / 2, 100, 0, Math.PI * 2, false);
    this.ctx.stroke();
    this.ctx.beginPath();
    this.ctx.strokeStyle = this.gaugeColour;
    this.ctx.lineWidth = 30;
    this.ctx.arc(this.width / 2, this.height / 2, 100, 0 - 90 * Math.PI / 180, degrees * Math.PI / 180 - 90 * Math.PI / 180, false);
    this.ctx.stroke();
    this.ctx.fillStyle = this.gaugeColour;
    this.ctx.font = '60px sans-serif';
    this.ctx.fillText(text, this.width / 2 - this.ctx.measureText(text).width / 2, this.height / 2 + 20);
  }

  componentDidMount() {
    this.setState({
      newdegs: Math.round(3.6 * this.props.value)
    });
    this.draw();
  }

  render() {
    return /*#__PURE__*/React.createElement(PureCanvas, _extends({
      key: this.props.gaugeId,
      contextRef: this.saveContext
    }, this.props));
  }

}

class PureCanvas extends React.Component {
  constructor(props) {
    super(props);
  }

  shouldComponentUpdate() {
    return false;
  }

  render() {
    return /*#__PURE__*/React.createElement("canvas", {
      id: this.props.gaugeId,
      className: "graph-widget",
      width: "250",
      height: "250",
      "data-value": this.props.value,
      ref: node => node ? this.props.contextRef(node.getContext('2d')) : null
    });
  }

}

function MemoryUsagePanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, "memory usage"), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "total memory:"), " ", props.total), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "used memory:"), " ", props.used), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "free memory:"), " ", props.free), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "wasted memory:"), " ", props.wasted, " (", props.wastedPercent, "%)")));
}

function StatisticsPanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, "opcache statistics"), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of cached files:"), " ", props.num_cached_scripts), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of hits:"), " ", props.hits), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of misses:"), " ", props.misses), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "blacklist misses:"), " ", props.blacklist_miss), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of cached keys:"), " ", props.num_cached_keys), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "max cached keys:"), " ", props.max_cached_keys)));
}

function InternedStringsPanel(props) {
  return /*#__PURE__*/React.createElement("div", {
    className: "widget-panel"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "widget-header"
  }, "interned strings usage"), /*#__PURE__*/React.createElement("div", {
    className: "widget-value widget-info"
  }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "buffer size:"), " ", props.buffer_size), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "used memory:"), " ", props.strings_used_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "free memory:"), " ", props.strings_free_memory), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("b", null, "number of strings:"), " ", props.number_of_strings)));
}

class Files extends React.Component {
  constructor(props) {
    super(props);

    _defineProperty(this, "setSearchTerm", debounce(searchTerm => {
      const availableFiles = this.props.opstate.files.filter(file => {
        return !(file.full_path.indexOf(searchTerm) == -1);
      });
      const currentFiles = this.doPagination ? this.state.currentFiles : availableFiles;
      this.setState({
        searchTerm,
        availableFiles,
        currentFiles,
        refreshPagination: !this.state.refreshPagination
      });
    }, this.props.debounceRate));

    _defineProperty(this, "onPageChanged", data => {
      const {
        availableFiles
      } = this.state;
      const {
        currentPage,
        totalPages,
        pageLimit
      } = data;
      const offset = (currentPage - 1) * pageLimit;
      const currentFiles = availableFiles.slice(offset, offset + pageLimit);
      this.setState({
        currentPage,
        currentFiles,
        totalPages
      });
    });

    this.doPagination = typeof this.props.perPageLimit === "number" && this.props.perPageLimit > 0;
    this.totalFiles = props.opstate.files.length;
    this.state = {
      availableFiles: props.opstate.files,
      currentFiles: this.doPagination ? [] : props.opstate.files,
      currentPage: null,
      totalPages: null,
      searchTerm: props.searchTerm,
      refreshPagination: 0
    };
  }

  renderPageHeader() {
    const showingTotal = this.state.availableFiles.length;
    const showing = showingTotal != this.totalFiles ? `, ${showingTotal} showing due to filter '${this.state.searchTerm}'` : null;
    return /*#__PURE__*/React.createElement("h3", null, this.totalFiles, " files cached", showing);
  }

  render() {
    if (!this.props.allow.filelist) {
      return null;
    }

    if (this.props.opstate.files.length === 0) {
      return /*#__PURE__*/React.createElement("p", null, "No files have been cached");
    }

    return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("form", {
      action: "#"
    }, /*#__PURE__*/React.createElement("label", {
      htmlFor: "frmFilter"
    }, "Start typing to filter on script path"), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("input", {
      type: "text",
      name: "filter",
      id: "frmFilter",
      className: "file-filter",
      onChange: e => {
        this.setSearchTerm(e.target.value);
      }
    })), this.renderPageHeader(), this.doPagination && /*#__PURE__*/React.createElement(Pagination, {
      totalRecords: this.state.availableFiles.length,
      pageLimit: this.props.perPageLimit,
      pageNeighbours: 2,
      onPageChanged: this.onPageChanged,
      refresh: this.state.refreshPagination
    }), /*#__PURE__*/React.createElement("table", {
      className: "tables file-list-table"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Script"))), /*#__PURE__*/React.createElement("tbody", null, this.state.currentFiles.map((file, index) => {
      return /*#__PURE__*/React.createElement(File, _extends({
        key: file.full_path,
        canInvalidate: this.props.allow.invalidate
      }, file, {
        colourRow: index
      }));
    }))));
  }

}

class File extends React.Component {
  constructor(props) {
    super(props);
    this.renderInvalidateLink = this.renderInvalidateLink.bind(this);
    this.renderInvalidateStatus = this.renderInvalidateStatus.bind(this);
  }

  handleInvalidate(e) {
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
  }

  renderInvalidateStatus() {
    return !this.props.timestamp ? /*#__PURE__*/React.createElement("span", {
      className: "invalid file-metainfo"
    }, " - has been invalidated") : null;
  }

  renderInvalidateLink() {
    return this.props.canInvalidate ? /*#__PURE__*/React.createElement("span", null, ",\xA0", /*#__PURE__*/React.createElement("a", {
      className: "file-metainfo",
      href: '?invalidate=' + this.props.full_path,
      "data-file": this.props.full_path,
      onClick: this.handleInvalidate
    }, "force file invalidation")) : null;
  }

  render() {
    return /*#__PURE__*/React.createElement("tr", {
      "data-path": this.props.full_path.toLowerCase(),
      className: this.props.colourRow % 2 ? 'alternate' : ''
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: "file-pathname"
    }, this.props.full_path), /*#__PURE__*/React.createElement("span", {
      className: "file-metainfo"
    }, /*#__PURE__*/React.createElement("b", null, "hits: "), /*#__PURE__*/React.createElement("span", null, this.props.readable.hits, ", "), /*#__PURE__*/React.createElement("b", null, "memory: "), /*#__PURE__*/React.createElement("span", null, this.props.readable.memory_consumption, ", "), /*#__PURE__*/React.createElement("b", null, "last used: "), /*#__PURE__*/React.createElement("span", null, this.props.last_used)), this.renderInvalidateStatus(), this.renderInvalidateLink()));
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
      const paginationData = {
        currentPage,
        totalPages: this.totalPages(),
        pageLimit: this.props.pageLimit,
        totalRecords: this.props.totalRecords
      };
      this.setState({
        currentPage
      }, () => onPageChanged(paginationData));
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
          "aria-label": "Previous",
          onClick: this.handleJumpLeft
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u219E"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, "Jump back"))), /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": "Previous",
          onClick: this.handleMoveLeft
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21E0"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, "Previous page"))));
      }

      if (page === "RIGHT") {
        return /*#__PURE__*/React.createElement(React.Fragment, {
          key: index
        }, /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": "Next",
          onClick: this.handleMoveRight
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21E2"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, "Next page"))), /*#__PURE__*/React.createElement("li", {
          className: "page-item arrow"
        }, /*#__PURE__*/React.createElement("a", {
          className: "page-link",
          href: "#",
          "aria-label": "Next",
          onClick: this.handleJumpRight
        }, /*#__PURE__*/React.createElement("span", {
          "aria-hidden": "true"
        }, "\u21A0"), /*#__PURE__*/React.createElement("span", {
          className: "sr-only"
        }, "Jump forward"))));
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
    title: "opcache-gui (currently version {props.opstate.version.gui}) on GitHub"
  }, "https://github.com/amnuts/opcache-gui - version ", props.opstate.version.gui));
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
        opstate: <?= json_encode($opcache->getData()); ?>,
        useCharts: <?= json_encode($opcache->getOption('charts')); ?>,
        highlight: <?= json_encode($opcache->getOption('highlight')); ?>,
        debounceRate: <?= $opcache->getOption('debounce_rate'); ?>,
        perPageLimit: <?= json_encode($opcache->getOption('per_page')); ?>,
        realtimeRefresh: <?= json_encode($opcache->getOption('refresh_time')); ?>
    }), document.getElementById('interface'));

    </script>

</body>
</html>