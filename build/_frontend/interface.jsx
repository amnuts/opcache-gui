class Interface extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            realtime: this.getCookie(),
            resetting: false,
            opstate: props.opstate
        }
        this.polling = false;
        this.isSecure = (window.location.protocol === 'https:');
        if (this.getCookie()) {
            this.startTimer();
        }
    }

    startTimer = () => {
        this.setState({realtime: true})
        this.polling = setInterval(() => {
            this.setState({fetching: true, resetting: false});
            axios.get(window.location.pathname, {time: Date.now()})
                .then((response) => {
                    this.setState({opstate: response.data});
                });
        }, this.props.realtimeRefresh * 1000);
    }

    stopTimer = () => {
        this.setState({realtime: false, resetting: false})
        clearInterval(this.polling)
    }

    realtimeHandler = () => {
        const realtime = !this.state.realtime;
        if (!realtime) {
            this.stopTimer();
            this.removeCookie();
        } else {
            this.startTimer();
            this.setCookie();
        }
    }

    resetHandler = () => {
        if (this.state.realtime) {
            this.setState({resetting: true});
            axios.get(window.location.pathname, {params: {reset: 1}})
                .then((response) => {
                    console.log('success: ', response.data);
                });
        } else {
            window.location.href = '?reset=1';
        }
    }

    setCookie = () => {
        let d = new Date();
        d.setTime(d.getTime() + (this.props.cookie.ttl * 86400000));
        document.cookie = `${this.props.cookie.name}=true;expires=${d.toUTCString()};path=/${this.isSecure ? ';secure' : ''}`;
    }

    removeCookie = () => {
        document.cookie = `${this.props.cookie.name}=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/${this.isSecure ? ';secure' : ''}`;
    }

    getCookie = () => {
        const v = document.cookie.match(`(^|;) ?${this.props.cookie.name}=([^;]*)(;|$)`);
        return v ? !!v[2] : false;
    };

    render() {
        const { opstate, realtimeRefresh, ...otherProps } = this.props;
        return (
            <>
                <header>
                    <MainNavigation {...otherProps}
                        opstate={this.state.opstate}
                        realtime={this.state.realtime}
                        resetting={this.state.resetting}
                        realtimeHandler={this.realtimeHandler}
                        resetHandler={this.resetHandler}
                    />
                </header>
                <Footer version={this.props.opstate.version.gui} />
            </>
        );
    }
}


function MainNavigation(props) {
    return (
        <nav className="main-nav">
            <Tabs>
                <div label="Overview" tabId="overview" tabIndex={1}>
                    <OverviewCounts
                        overview={props.opstate.overview}
                        highlight={props.highlight}
                        useCharts={props.useCharts}
                    />
                    <div id="info" className="tab-content-overview-info">
                        <GeneralInfo
                            start={props.opstate.overview && props.opstate.overview.readable.start_time || null}
                            reset={props.opstate.overview && props.opstate.overview.readable.last_restart_time || null}
                            version={props.opstate.version}
                        />
                        <Directives
                            directives={props.opstate.directives}
                        />
                        <Functions
                            functions={props.opstate.functions}
                        />
                    </div>
                </div>
                {
                    props.allow.filelist &&
                        <div label="Cached" tabId="cached" tabIndex={2}>
                            <CachedFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.files}
                                searchTerm={props.searchTerm}
                                debounceRate={props.debounceRate}
                                allow={{fileList: props.allow.filelist, invalidate: props.allow.invalidate}}
                                realtime={props.realtime}
                            />
                        </div>
                }
                {
                    (props.allow.filelist && props.opstate.blacklist.length &&
                        <div label="Ignored" tabId="ignored" tabIndex={3}>
                            <IgnoredFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.blacklist}
                                allow={{fileList: props.allow.filelist }}
                            />
                        </div>)
                }
                {
                    (props.allow.filelist && props.opstate.preload.length &&
                        <div label="Preloaded" tabId="preloaded" tabIndex={4}>
                            <PreloadedFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.preload}
                                allow={{fileList: props.allow.filelist }}
                            />
                        </div>)
                }
                {
                    props.allow.reset &&
                        <div label="Reset cache" tabId="resetCache"
                           className={`nav-tab-link-reset${props.resetting ? ' is-resetting pulse' : ''}`}
                           handler={props.resetHandler}
                           tabIndex={5}
                        ></div>
                }
                {
                    props.allow.realtime &&
                        <div label={`${props.realtime ? 'Disable' : 'Enable'} real-time update`} tabId="toggleRealtime"
                            className={`nav-tab-link-realtime${props.realtime ? ' live-update pulse' : ''}`}
                            handler={props.realtimeHandler}
                            tabIndex={6}
                        ></div>
                }
            </Tabs>
        </nav>
    );
}


class Tabs extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            activeTab: this.props.children[0].props.label,
        };
    }

    onClickTabItem = (tab) => {
        this.setState({ activeTab: tab });
    }

    render() {
        const {
            onClickTabItem,
            state: { activeTab }
        } = this;

        const children = this.props.children.filter(Boolean);

        return (
            <>
                <ul className="nav-tab-list">
                    {children.map((child) => {
                        const { tabId, label, className, handler, tabIndex } = child.props;
                        return (
                            <Tab
                                activeTab={activeTab}
                                key={tabId}
                                label={label}
                                onClick={handler || onClickTabItem}
                                className={className}
                                tabIndex={tabIndex}
                                tabId={tabId}
                            />
                        );
                    })}
                </ul>
                <div className="tab-content">
                    {children.map((child) => (
                        <div key={child.props.label}
                             style={{ display: child.props.label === activeTab ? 'block' : 'none' }}
                             id={`${child.props.tabId}-content`}
                        >
                            {child.props.children}
                        </div>
                    ))}
                </div>
            </>
        );
    }
}


class Tab extends React.Component {
    onClick = () => {
        const { label, onClick } = this.props;
        onClick(label);
    }

    render() {
        const {
            onClick,
            props: { activeTab, label, tabIndex, tabId },
        } = this;

        let className = 'nav-tab';
        if (this.props.className) {
            className += ` ${this.props.className}`;
        }
        if (activeTab === label) {
            className += ' active';
        }

        return (
            <li className={className}
                onClick={onClick}
                tabIndex={tabIndex}
                role="tab"
                aria-controls={`${tabId}-content`}
            >{label}</li>
        );
    }
}


function OverviewCounts(props) {
    if (props.overview === false) {
        return (
            <p class="file-cache-only">
                You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.
            </p>
        );
    }

    const graphList = [
        {id: 'memoryUsageCanvas', title: 'memory', show: props.highlight.memory, value: props.overview.used_memory_percentage},
        {id: 'hitRateCanvas', title: 'hit rate', show: props.highlight.hits, value: props.overview.hit_rate_percentage},
        {id: 'keyUsageCanvas', title: 'keys', show: props.highlight.keys, value: props.overview.used_key_percentage}
    ];

    return (
        <div id="counts" className="tab-content-overview-counts">
            {graphList.map((graph) => {
                if (!graph.show) {
                    return null;
                }
                return (
                    <div className="widget-panel" key={graph.id}>
                        <h3 className="widget-header">{graph.title}</h3>
                        <UsageGraph charts={props.useCharts} value={graph.value} gaugeId={graph.id} />
                    </div>
                );
            })}
            <MemoryUsagePanel
                total={props.overview.readable.total_memory}
                used={props.overview.readable.used_memory}
                free={props.overview.readable.free_memory}
                wasted={props.overview.readable.wasted_memory}
                preload={props.overview.readable.preload_memory || null}
                wastedPercent={props.overview.wasted_percentage}
            />
            <StatisticsPanel
                num_cached_scripts={props.overview.readable.num_cached_scripts}
                hits={props.overview.readable.hits}
                misses={props.overview.readable.misses}
                blacklist_miss={props.overview.readable.blacklist_miss}
                num_cached_keys={props.overview.readable.num_cached_keys}
                max_cached_keys={props.overview.readable.max_cached_keys}
            />
            {props.overview.readable.interned &&
                <InternedStringsPanel
                    buffer_size={props.overview.readable.interned.buffer_size}
                    strings_used_memory={props.overview.readable.interned.strings_used_memory}
                    strings_free_memory={props.overview.readable.interned.strings_free_memory}
                    number_of_strings={props.overview.readable.interned.number_of_strings}
                />
            }
        </div>
    );
}


function GeneralInfo(props) {
    return (
        <table className="tables general-info-table">
            <thead>
                <tr><th colSpan="2">General info</th></tr>
            </thead>
            <tbody>
                <tr><td>Zend OPcache</td><td>{props.version.version}</td></tr>
                <tr><td>PHP</td><td>{props.version.php}</td></tr>
                <tr><td>Host</td><td>{props.version.host}</td></tr>
                <tr><td>Server Software</td><td>{props.version.server}</td></tr>
                { props.start ? <tr><td>Start time</td><td>{props.start}</td></tr> : null }
                { props.reset ? <tr><td>Last reset</td><td>{props.reset}</td></tr> : null }
            </tbody>
        </table>
    );
}


function Directives(props) {
    let directiveList = (directive) => {
        return (
            <ul className="directive-list">{
                directive.v.map((item, key) => {
                    return <li key={key}>{item}</li>
                })
            }</ul>
        );
    };

    let directiveNodes = props.directives.map(function(directive) {
        let map = { 'opcache.':'', '_':' ' };
        let dShow = directive.k.replace(/opcache\.|_/gi, function(matched){
            return map[matched];
        });
        let vShow;
        if (directive.v === true || directive.v === false) {
            vShow = React.createElement('i', {}, directive.v.toString());
        } else if (directive.v === '') {
            vShow = React.createElement('i', {}, 'no value');
        } else {
            if (Array.isArray(directive.v)) {
                vShow = directiveList(directive);
            } else {
                vShow = directive.v;
            }
        }
        return (
            <tr key={directive.k}>
                <td title={'View ' + directive.k + ' manual entry'}><a href={'http://php.net/manual/en/opcache.configuration.php#ini.'
                + (directive.k).replace(/_/g,'-')} target="_blank">{dShow}</a></td>
                <td>{vShow}</td>
            </tr>
        );
    });

    return (
        <table className="tables directives-table">
            <thead><tr><th colSpan="2">Directives</th></tr></thead>
            <tbody>{directiveNodes}</tbody>
        </table>
    );
}

function Functions(props) {
    return (
        <div id="functions">
            <table className="tables">
                <thead><tr><th>Available functions</th></tr></thead>
                <tbody>
                {props.functions.map(f =>
                    <tr key={f}><td><a href={"http://php.net/"+f} title="View manual page" target="_blank">{f}</a></td></tr>
                )}
                </tbody>
            </table>
        </div>
    );
}


function UsageGraph(props) {
    const percentage = Math.round(((3.6 * props.value)/360)*100);
    return (props.charts
        ? <ReactCustomizableProgressbar
            progress={percentage}
            radius={100}
            strokeWidth={30}
            trackStrokeWidth={30}
            strokeColor={getComputedStyle(document.documentElement).getPropertyValue('--opcache-gui-graph-track-fill-color') || "#6CA6EF"}
            trackStrokeColor={getComputedStyle(document.documentElement).getPropertyValue('--opcache-gui-graph-track-background-color') || "#CCC"}
            gaugeId={props.gaugeId}
        />
        : <p className="widget-value"><span className="large">{percentage}</span><span>%</span></p>
    );
}

/**
 * This component is from <https://github.com/martyan/react-customizable-progressbar/>
 * MIT License (MIT), Copyright (c) 2019 Martin Juzl
 */
class ReactCustomizableProgressbar extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            animationInited: false
        };
    }

    componentDidMount() {
        const { initialAnimation, initialAnimationDelay } = this.props
        if (initialAnimation)
            setTimeout(this.initAnimation, initialAnimationDelay)
    }

    initAnimation = () => {
        this.setState({ animationInited: true })
    }

    getProgress = () => {
        const { initialAnimation, progress } = this.props
        const { animationInited } = this.state

        return initialAnimation && !animationInited ? 0 : progress
    }

    getStrokeDashoffset = strokeLength => {
        const { counterClockwise, inverse, steps } = this.props
        const progress = this.getProgress()
        const progressLength = (strokeLength / steps) * (steps - progress)

        if (inverse) return counterClockwise ? 0 : progressLength - strokeLength

        return counterClockwise ? -1 * progressLength : progressLength
    }

    getStrokeDashArray = (strokeLength, circumference) => {
        const { counterClockwise, inverse, steps } = this.props
        const progress = this.getProgress()
        const progressLength = (strokeLength / steps) * (steps - progress)

        if (inverse) return `${progressLength}, ${circumference}`

        return counterClockwise
            ? `${strokeLength * (progress / 100)}, ${circumference}`
            : `${strokeLength}, ${circumference}`
    }

    getTrackStrokeDashArray = (strokeLength, circumference) => {
        const { initialAnimation } = this.props
        const { animationInited } = this.state
        if (initialAnimation && !animationInited) return `0, ${circumference}`
        return `${strokeLength}, ${circumference}`
    }

    getExtendedWidth = () => {
        const {
            strokeWidth,
            pointerRadius,
            pointerStrokeWidth,
            trackStrokeWidth
        } = this.props
        const pointerWidth = pointerRadius + pointerStrokeWidth
        if (pointerWidth > strokeWidth && pointerWidth > trackStrokeWidth) return pointerWidth * 2
        else if (strokeWidth > trackStrokeWidth) return strokeWidth * 2
        else return trackStrokeWidth * 2
    }

    getPointerAngle = () => {
        const { cut, counterClockwise, steps } = this.props
        const progress = this.getProgress()
        return counterClockwise
            ? ((360 - cut) / steps) * (steps - progress)
            : ((360 - cut) / steps) * progress
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
        } = this.props

        const d = 2 * radius
        const width = d + this.getExtendedWidth()

        const circumference = 2 * Math.PI * radius
        const strokeLength = (circumference / 360) * (360 - cut)

        return (
            <figure
                className={`graph-widget`}
                style={{width: `${width || 250}px`}}
                data-value={progress}
                id={this.props.guageId}
            >
                <svg width={width} height={width}
                    viewBox={`0 0 ${width} ${width}`}
                    style={{ transform: `rotate(${rotate}deg)` }}
                >
                    {trackStrokeWidth > 0 && (
                        <circle
                            cx={width / 2}
                            cy={width / 2}
                            r={radius}
                            fill="none"
                            stroke={trackStrokeColor}
                            strokeWidth={trackStrokeWidth}
                            strokeDasharray={this.getTrackStrokeDashArray(
                                strokeLength,
                                circumference
                            )}
                            strokeLinecap={trackStrokeLinecap}
                            style={{ transition: trackTransition }}
                        />
                    )}
                    {strokeWidth > 0 && (
                        <circle
                            cx={width / 2}
                            cy={width / 2}
                            r={radius}
                            fill={fillColor}
                            stroke={strokeColor}
                            strokeWidth={strokeWidth}
                            strokeDasharray={this.getStrokeDashArray(
                                strokeLength,
                                circumference
                            )}
                            strokeDashoffset={this.getStrokeDashoffset(
                                strokeLength
                            )}
                            strokeLinecap={strokeLinecap}
                            style={{ transition }}
                        />
                    )}
                    {pointerRadius > 0 && (
                        <circle
                            cx={d}
                            cy="50%"
                            r={pointerRadius}
                            fill={pointerFillColor}
                            stroke={pointerStrokeColor}
                            strokeWidth={pointerStrokeWidth}
                            style={{
                                transformOrigin: '50% 50%',
                                transform: `rotate(${this.getPointerAngle()}deg) translate(${this.getExtendedWidth() /
                                2}px)`,
                                transition
                            }}
                        />
                    )}
                </svg>
                <figcaption className={`widget-value`}>
                    {progress}%
                </figcaption>
            </figure>
        )
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
    return (
        <div className="widget-panel">
            <h3 className="widget-header">memory usage</h3>
            <div className="widget-value widget-info">
                <p><b>total memory:</b> {props.total}</p>
                <p><b>used memory:</b> {props.used}</p>
                <p><b>free memory:</b> {props.free}</p>
                { props.preload && <p><b>preload memory:</b> {props.preload}</p> }
                <p><b>wasted memory:</b> {props.wasted} ({props.wastedPercent}%)</p>
            </div>
        </div>
    );
}


function StatisticsPanel(props) {
    return (
        <div className="widget-panel">
            <h3 className="widget-header">opcache statistics</h3>
            <div className="widget-value widget-info">
                <p><b>number of cached files:</b> {props.num_cached_scripts}</p>
                <p><b>number of hits:</b> {props.hits}</p>
                <p><b>number of misses:</b> {props.misses}</p>
                <p><b>blacklist misses:</b> {props.blacklist_miss}</p>
                <p><b>number of cached keys:</b> {props.num_cached_keys}</p>
                <p><b>max cached keys:</b> {props.max_cached_keys}</p>
            </div>
        </div>
    );
}


function InternedStringsPanel(props) {
    return (
        <div className="widget-panel">
            <h3 className="widget-header">interned strings usage</h3>
            <div className="widget-value widget-info">
                <p><b>buffer size:</b> {props.buffer_size}</p>
                <p><b>used memory:</b> {props.strings_used_memory}</p>
                <p><b>free memory:</b> {props.strings_free_memory}</p>
                <p><b>number of strings:</b> {props.number_of_strings}</p>
            </div>
        </div>
    );
}


class CachedFiles extends React.Component {
    constructor(props) {
        super(props);
        this.doPagination = (typeof props.perPageLimit === "number"
            && props.perPageLimit > 0
        );
        this.state = {
            currentPage: 1,
            searchTerm: props.searchTerm,
            refreshPagination: 0,
            sortBy: `last_used_timestamp`,
            sortDir: `desc`
        }
    }

    setSearchTerm = debounce(searchTerm => {
        this.setState({
            searchTerm,
            refreshPagination: !(this.state.refreshPagination)
        });
    }, this.props.debounceRate);

    onPageChanged = currentPage => {
        this.setState({ currentPage });
    }

    handleInvalidate = e => {
        e.preventDefault();
        if (this.props.realtime) {
            axios.get(window.location.pathname, {params: { invalidate_searched: this.state.searchTerm }})
                .then((response) => {
                    console.log('success: ' , response.data);
                });
        } else {
            window.location.href = e.currentTarget.href;
        }
    }

    changeSort = e => {
        this.setState({ [e.target.name]: e.target.value });
    }

    compareValues = (key, order = 'asc') => {
        return function innerSort(a, b) {
            if (!a.hasOwnProperty(key) || !b.hasOwnProperty(key)) {
                return 0;
            }
            const varA = (typeof a[key] === 'string') ? a[key].toUpperCase() : a[key];
            const varB = (typeof b[key] === 'string') ? b[key].toUpperCase() : b[key];

            let comparison = 0;
            if (varA > varB) {
                comparison = 1;
            } else if (varA < varB) {
                comparison = -1;
            }
            return (
                (order === 'desc') ? (comparison * -1) : comparison
            );
        };
    }

    render() {
        if (!this.props.allow.fileList) {
            return null;
        }

        if (this.props.allFiles.length === 0) {
            return <p>No files have been cached or you have <i>opcache.file_cache_only</i> turned on</p>;
        }

        const { searchTerm, currentPage } = this.state;
        const offset = (currentPage - 1) * this.props.perPageLimit;
        const filesInSearch = (searchTerm
            ? this.props.allFiles.filter(file => {
                    return !(file.full_path.indexOf(searchTerm) === -1);
                })
            : this.props.allFiles
        );

        filesInSearch.sort(this.compareValues(this.state.sortBy, this.state.sortDir));

        const filesInPage = (this.doPagination
            ? filesInSearch.slice(offset, offset + this.props.perPageLimit)
            : filesInSearch
        );
        const allFilesTotal = this.props.allFiles.length;
        const showingTotal = filesInSearch.length;

        return (
            <div>
                <form action="#">
                    <label htmlFor="frmFilter">Start typing to filter on script path</label><br/>
                    <input type="text" name="filter" id="frmFilter" className="file-filter" onChange={e => {this.setSearchTerm(e.target.value)}} />
                </form>

                <h3>{allFilesTotal} files cached{showingTotal !== allFilesTotal && `, ${showingTotal} showing due to filter '${this.state.searchTerm}'`}</h3>

                { this.state.searchTerm && showingTotal !== allFilesTotal &&
                    <p><a href={`?invalidate_searched=${encodeURIComponent(this.state.searchTerm)}`} onClick={this.handleInvalidate}>Invalidate all matching files</a></p>
                }

                <div className="paginate-filter">
                    {this.doPagination && <Pagination
                        totalRecords={filesInSearch.length}
                        pageLimit={this.props.perPageLimit}
                        pageNeighbours={2}
                        onPageChanged={this.onPageChanged}
                        refresh={this.state.refreshPagination}
                    />}
                    <nav className="filter" aria-label="Sort order">
                        <select name="sortBy" onChange={this.changeSort} value={this.state.sortBy}>
                            <option value="last_used_timestamp">Last used</option>
                            <option value="full_path">Path</option>
                            <option value="hits">Number of hits</option>
                            <option value="memory_consumption">Memory consumption</option>
                        </select>
                        <select name="sortDir" onChange={this.changeSort} value={this.state.sortDir}>
                            <option value="desc">Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </nav>
                </div>

                <table className="tables cached-list-table">
                    <thead>
                    <tr>
                        <th>Script</th>
                    </tr>
                    </thead>
                    <tbody>
                    {filesInPage.map((file, index) => {
                        return <CachedFile
                            key={file.full_path}
                            canInvalidate={this.props.allow.invalidate}
                            realtime={this.props.realtime}
                            {...file}
                        />
                    })}
                    </tbody>
                </table>
            </div>
        );
    }
}


class CachedFile extends React.Component {
    handleInvalidate = e => {
        e.preventDefault();
        if (this.props.realtime) {
            axios.get(window.location.pathname, {params: { invalidate: e.currentTarget.getAttribute('data-file') }})
                .then((response) => {
                    console.log('success: ' , response.data);
                });
        } else {
            window.location.href = e.currentTarget.href;
        }
    }

    render() {
        return (
            <tr data-path={this.props.full_path.toLowerCase()}>
                <td>
                    <span className="file-pathname">{this.props.full_path}</span>
                    <span className="file-metainfo">
                        <b>hits: </b><span>{this.props.readable.hits}, </span>
                        <b>memory: </b><span>{this.props.readable.memory_consumption}, </span>
                        <b>last used: </b><span>{this.props.last_used}</span>
                    </span>
                    { !this.props.timestamp && <span className="invalid file-metainfo"> - has been invalidated</span> }
                    { this.props.canInvalidate && <span>,&nbsp;<a className="file-metainfo"
                          href={'?invalidate=' + this.props.full_path} data-file={this.props.full_path}
                          onClick={this.handleInvalidate}>force file invalidation</a></span> }
                </td>
            </tr>
        );
    }
}


class IgnoredFiles extends React.Component {
    constructor(props) {
        super(props);
        this.doPagination = (typeof props.perPageLimit === "number"
            && props.perPageLimit > 0
        );
        this.state = {
            currentPage: 1,
            refreshPagination: 0
        }
    }

    onPageChanged = currentPage => {
        this.setState({ currentPage });
    }

    render() {
        if (!this.props.allow.fileList) {
            return null;
        }

        if (this.props.allFiles.length === 0) {
            return <p>No files have been ignored via <i>opcache.blacklist_filename</i></p>;
        }

        const { currentPage } = this.state;
        const offset = (currentPage - 1) * this.props.perPageLimit;
        const filesInPage = (this.doPagination
            ? this.props.allFiles.slice(offset, offset + this.props.perPageLimit)
            : this.props.allFiles
        );
        const allFilesTotal = this.props.allFiles.length;

        return (
            <div>
                <h3>{allFilesTotal} ignore file locations</h3>

                {this.doPagination && <Pagination
                    totalRecords={allFilesTotal}
                    pageLimit={this.props.perPageLimit}
                    pageNeighbours={2}
                    onPageChanged={this.onPageChanged}
                    refresh={this.state.refreshPagination}
                />}

                <table className="tables ignored-list-table">
                    <thead><tr><th>Path</th></tr></thead>
                    <tbody>
                        {filesInPage.map((file, index) => {
                            return <tr key={file}><td>{file}</td></tr>
                        })}
                    </tbody>
                </table>
            </div>
        );
    }
}


class PreloadedFiles extends React.Component {
    constructor(props) {
        super(props);
        this.doPagination = (typeof props.perPageLimit === "number"
            && props.perPageLimit > 0
        );
        this.state = {
            currentPage: 1,
            refreshPagination: 0
        }
    }

    onPageChanged = currentPage => {
        this.setState({ currentPage });
    }

    render() {
        if (!this.props.allow.fileList) {
            return null;
        }

        if (this.props.allFiles.length === 0) {
            return <p>No files have been preloaded <i>opcache.preload</i></p>;
        }

        const { currentPage } = this.state;
        const offset = (currentPage - 1) * this.props.perPageLimit;
        const filesInPage = (this.doPagination
            ? this.props.allFiles.slice(offset, offset + this.props.perPageLimit)
            : this.props.allFiles
        );
        const allFilesTotal = this.props.allFiles.length;

        return (
            <div>
                <h3>{allFilesTotal} preloaded files</h3>

                {this.doPagination && <Pagination
                    totalRecords={allFilesTotal}
                    pageLimit={this.props.perPageLimit}
                    pageNeighbours={2}
                    onPageChanged={this.onPageChanged}
                    refresh={this.state.refreshPagination}
                />}

                <table className="tables preload-list-table">
                    <thead><tr><th>Path</th></tr></thead>
                    <tbody>
                        {filesInPage.map((file, index) => {
                            return <tr key={file}><td>{file}</td></tr>
                        })}
                    </tbody>
                </table>
            </div>
        );
    }
}


class Pagination extends React.Component {
    constructor(props) {
        super(props);
        this.state = { currentPage: 1 };
        this.pageNeighbours =
            typeof props.pageNeighbours === "number"
                ? Math.max(0, Math.min(props.pageNeighbours, 2))
                : 0;
    }

    componentDidMount() {
        this.gotoPage(1);
    }

    componentDidUpdate(props) {
        const { refresh } = this.props;
        if (props.refresh !== refresh) {
            this.gotoPage(1);
        }
    }

    gotoPage = page => {
        const { onPageChanged = f => f } = this.props;
        const currentPage = Math.max(0, Math.min(page, this.totalPages()));
        this.setState({ currentPage }, () => onPageChanged(currentPage));
    };

    totalPages = () => {
        return Math.ceil(this.props.totalRecords / this.props.pageLimit);
    }

    handleClick = (page, evt) => {
        evt.preventDefault();
        this.gotoPage(page);
    };

    handleJumpLeft = evt => {
        evt.preventDefault();
        this.gotoPage(this.state.currentPage - this.pageNeighbours * 2 - 1);
    };

    handleJumpRight = evt => {
        evt.preventDefault();
        this.gotoPage(this.state.currentPage + this.pageNeighbours * 2 + 1);
    };

    handleMoveLeft = evt => {
        evt.preventDefault();
        this.gotoPage(this.state.currentPage - 1);
    };

    handleMoveRight = evt => {
        evt.preventDefault();
        this.gotoPage(this.state.currentPage + 1);
    };

    range = (from, to, step = 1) => {
        let i = from;
        const range = [];
        while (i <= to) {
            range.push(i);
            i += step;
        }
        return range;
    }

    fetchPageNumbers = () => {
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
    };

    render() {
        if (!this.props.totalRecords || this.totalPages() === 1) {
            return null
        }

        const { currentPage } = this.state;
        const pages = this.fetchPageNumbers();

        return (
            <nav aria-label="File list pagination">
                <ul className="pagination">
                    {pages.map((page, index) => {
                        if (page === "LEFT") {
                            return (
                                <React.Fragment key={index}>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label="Previous" onClick={this.handleJumpLeft}>
                                            <span aria-hidden="true">↞</span>
                                            <span className="sr-only">Jump back</span>
                                        </a>
                                    </li>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label="Previous" onClick={this.handleMoveLeft}>
                                            <span aria-hidden="true">⇠</span>
                                            <span className="sr-only">Previous page</span>
                                        </a>
                                    </li>
                                </React.Fragment>
                            );
                        }
                        if (page === "RIGHT") {
                            return (
                                <React.Fragment key={index}>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label="Next" onClick={this.handleMoveRight}>
                                            <span aria-hidden="true">⇢</span>
                                            <span className="sr-only">Next page</span>
                                        </a>
                                    </li>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label="Next" onClick={this.handleJumpRight}>
                                            <span aria-hidden="true">↠</span>
                                            <span className="sr-only">Jump forward</span>
                                        </a>
                                    </li>
                                </React.Fragment>
                            );
                        }
                        return (
                            <li key={index} className="page-item">
                                <a className={`page-link${currentPage === page ? " active" : ""}`} href="#" onClick={e => this.handleClick(page, e)}>
                                    {page}
                                </a>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        );
    }
}


function Footer(props) {
    return (
        <footer className="main-footer">
            <a className="github-link" href="https://github.com/amnuts/opcache-gui"
               target="_blank"
               title="opcache-gui (currently version {props.version}) on GitHub"
            >https://github.com/amnuts/opcache-gui - version {props.version}</a>
        </footer>
    );
}


function debounce(func, wait, immediate) {
    let timeout;
    wait = wait || 250;
    return function() {
        let context = this, args = arguments;
        let later = function() {
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

