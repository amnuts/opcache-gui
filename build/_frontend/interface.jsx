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

    txt = (text, ...args) => {
        if (this.props.language !== null && this.props.language.hasOwnProperty(text) && this.props.language[text]) {
            text = this.props.language[text];
        }
        args.forEach((arg, i) => {
            text = text.replaceAll(`{${i}}`, arg);
        });
        return text;
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
                        txt={this.txt}
                    />
                </header>
                <Footer
                    version={this.props.opstate.version.gui}
                    txt={this.txt}
                />
            </>
        );
    }
}


function MainNavigation(props) {
    return (
        <nav className="main-nav">
            <Tabs>
                <div label={props.txt("Overview")} tabId="overview" tabIndex={1}>
                    <OverviewCounts
                        overview={props.opstate.overview}
                        highlight={props.highlight}
                        useCharts={props.useCharts}
                        txt={props.txt}
                    />
                    <div id="info" className="tab-content-overview-info">
                        <GeneralInfo
                            start={props.opstate.overview && props.opstate.overview.readable.start_time || null}
                            reset={props.opstate.overview && props.opstate.overview.readable.last_restart_time || null}
                            version={props.opstate.version}
                            jit={props.opstate.jitState}
                            txt={props.txt}
                        />
                        <Directives
                            directives={props.opstate.directives}
                            txt={props.txt}
                        />
                        <Functions
                            functions={props.opstate.functions}
                            txt={props.txt}
                        />
                    </div>
                </div>
                {
                    props.allow.filelist &&
                        <div label={props.txt("Cached")} tabId="cached" tabIndex={2}>
                            <CachedFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.files}
                                searchTerm={props.searchTerm}
                                debounceRate={props.debounceRate}
                                allow={{fileList: props.allow.filelist, invalidate: props.allow.invalidate}}
                                realtime={props.realtime}
                                txt={props.txt}
                            />
                        </div>
                }
                {
                    (props.allow.filelist && props.opstate.files.length &&
                        <div label={props.txt("Treemap")} tabId="treemap" tabIndex={3}>
                            <Treemap
                                allFiles={props.opstate.files}
                                allow={{fileList: props.allow.filelist}}
                                txt={props.txt}
                            />
                        </div>)
                }
                {
                    (props.allow.filelist && props.opstate.blacklist.length &&
                        <div label={props.txt("Ignored")} tabId="ignored" tabIndex={4}>
                            <IgnoredFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.blacklist}
                                allow={{fileList: props.allow.filelist }}
                                txt={props.txt}
                            />
                        </div>)
                }
                {
                    (props.allow.filelist && props.opstate.preload.length &&
                        <div label={props.txt("Preloaded")} tabId="preloaded" tabIndex={5}>
                            <PreloadedFiles
                                perPageLimit={props.perPageLimit}
                                allFiles={props.opstate.preload}
                                allow={{fileList: props.allow.filelist }}
                                txt={props.txt}
                            />
                        </div>)
                }
                {
                    props.allow.reset &&
                        <div label={props.txt("Reset cache")} tabId="resetCache"
                           className={`nav-tab-link-reset${props.resetting ? ' is-resetting pulse' : ''}`}
                           handler={props.resetHandler}
                           tabIndex={6}
                        ></div>
                }
                {
                    props.allow.realtime &&
                        <div label={props.txt(`${props.realtime ? 'Disable' : 'Enable'} real-time update`)} tabId="toggleRealtime"
                            className={`nav-tab-link-realtime${props.realtime ? ' live-update pulse' : ''}`}
                            handler={props.realtimeHandler}
                            tabIndex={7}
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
            <p className="file-cache-only"
                dangerouslySetInnerHTML={{__html: props.txt(`You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.`)}}
            >
            </p>
        );
    }

    const graphList = [
        {id: 'memoryUsageCanvas', title: props.txt('memory'), show: props.highlight.memory, value: props.overview.used_memory_percentage},
        {id: 'hitRateCanvas', title: props.txt('hit rate'), show: props.highlight.hits, value: props.overview.hit_rate_percentage},
        {id: 'keyUsageCanvas', title: props.txt('keys'), show: props.highlight.keys, value: props.overview.used_key_percentage},
        {id: 'jitUsageCanvas', title: props.txt('jit buffer'), show: props.highlight.jit, value: props.overview.jit_buffer_used_percentage}
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
                jitBuffer={props.overview.readable.jit_buffer_size || null}
                jitBufferFree={props.overview.readable.jit_buffer_free || null}
                jitBufferFreePercentage={props.overview.jit_buffer_used_percentage || null}
                txt={props.txt}
            />
            <StatisticsPanel
                num_cached_scripts={props.overview.readable.num_cached_scripts}
                hits={props.overview.readable.hits}
                misses={props.overview.readable.misses}
                blacklist_miss={props.overview.readable.blacklist_miss}
                num_cached_keys={props.overview.readable.num_cached_keys}
                max_cached_keys={props.overview.readable.max_cached_keys}
                txt={props.txt}
            />
            {props.overview.readable.interned &&
                <InternedStringsPanel
                    buffer_size={props.overview.readable.interned.buffer_size}
                    strings_used_memory={props.overview.readable.interned.strings_used_memory}
                    strings_free_memory={props.overview.readable.interned.strings_free_memory}
                    number_of_strings={props.overview.readable.interned.number_of_strings}
                    txt={props.txt}
                />
            }
        </div>
    );
}


function GeneralInfo(props) {
    return (
        <table className="tables general-info-table">
            <thead>
                <tr><th colSpan="2">{props.txt('General info')}</th></tr>
            </thead>
            <tbody>
                <tr><td>Zend OPcache</td><td>{props.version.version}</td></tr>
                <tr><td>PHP</td><td>{props.version.php}</td></tr>
                <tr><td>{props.txt('Host')}</td><td>{props.version.host}</td></tr>
                <tr><td>{props.txt('Server Software')}</td><td>{props.version.server}</td></tr>
                { props.start ? <tr><td>{props.txt('Start time')}</td><td>{props.start}</td></tr> : null }
                { props.reset ? <tr><td>{props.txt('Last reset')}</td><td>{props.reset}</td></tr> : null }
                <tr>
                    <td>{props.txt('JIT enabled')}</td>
                    <td>
                        {props.txt(props.jit.enabled ? "Yes" : "No")}
                        {props.jit.reason && (<span dangerouslySetInnerHTML={{__html: ` (${props.jit.reason})` }} />)}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}


function Directives(props) {
    let directiveList = (directive) => {
        return (
            <ul className="directive-list">{
                directive.v.map((item, key) => {
                    return Array.isArray(item)
                        ? <li key={"sublist_" + key}>{directiveList({v:item})}</li>
                        : <li key={key}>{item}</li>
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
        let directiveLink = (name) => {
            if (name === 'opcache.jit_max_recursive_returns') {
                return 'opcache.jit-max-recursive-return';
            }
            return (
                [
                    'opcache.file_update_protection',
                    'opcache.huge_code_pages',
                    'opcache.lockfile_path',
                    'opcache.opt_debug_level',
                ].includes(name)
                ? name
                : name.replace(/_/g,'-')
            );
        }
        return (
            <tr key={directive.k}>
                <td title={props.txt('View {0} manual entry', directive.k)}><a href={'https://php.net/manual/en/opcache.configuration.php#ini.'
                + directiveLink(directive.k)} target="_blank">{dShow}</a></td>
                <td>{vShow}</td>
            </tr>
        );
    });

    return (
        <table className="tables directives-table">
            <thead><tr><th colSpan="2">{props.txt('Directives')}</th></tr></thead>
            <tbody>{directiveNodes}</tbody>
        </table>
    );
}

function Functions(props) {
    return (
        <div id="functions">
            <table className="tables">
                <thead><tr><th>{props.txt('Available functions')}</th></tr></thead>
                <tbody>
                {props.functions.map(f =>
                    <tr key={f}><td><a href={"https://php.net/"+f} title={props.txt('View manual page')} target="_blank">{f}</a></td></tr>
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
            <h3 className="widget-header">{props.txt('memory usage')}</h3>
            <div className="widget-value widget-info">
                <p><b>{props.txt('total memory')}:</b> {props.total}</p>
                <p><b>{props.txt('used memory')}:</b> {props.used}</p>
                <p><b>{props.txt('free memory')}:</b> {props.free}</p>
                { props.preload && <p><b>{props.txt('preload memory')}:</b> {props.preload}</p> }
                <p><b>{props.txt('wasted memory')}:</b> {props.wasted} ({props.wastedPercent}%)</p>
                { props.jitBuffer && <p><b>{props.txt('jit buffer')}:</b> {props.jitBuffer}</p> }
                { props.jitBufferFree && <p><b>{props.txt('jit buffer free')}:</b> {props.jitBufferFree} ({100 - props.jitBufferFreePercentage}%)</p> }
            </div>
        </div>
    );
}


function StatisticsPanel(props) {
    return (
        <div className="widget-panel">
            <h3 className="widget-header">{props.txt('opcache statistics')}</h3>
            <div className="widget-value widget-info">
                <p><b>{props.txt('number of cached files')}:</b> {props.num_cached_scripts}</p>
                <p><b>{props.txt('number of hits')}:</b> {props.hits}</p>
                <p><b>{props.txt('number of misses')}:</b> {props.misses}</p>
                <p><b>{props.txt('blacklist misses')}:</b> {props.blacklist_miss}</p>
                <p><b>{props.txt('number of cached keys')}:</b> {props.num_cached_keys}</p>
                <p><b>{props.txt('max cached keys')}:</b> {props.max_cached_keys}</p>
            </div>
        </div>
    );
}


function InternedStringsPanel(props) {
    return (
        <div className="widget-panel">
            <h3 className="widget-header">{props.txt('interned strings usage')}</h3>
            <div className="widget-value widget-info">
                <p><b>{props.txt('buffer size')}:</b> {props.buffer_size}</p>
                <p><b>{props.txt('used memory')}:</b> {props.strings_used_memory}</p>
                <p><b>{props.txt('free memory')}:</b> {props.strings_free_memory}</p>
                <p><b>{props.txt('number of strings')}:</b> {props.number_of_strings}</p>
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
            return <p dangerouslySetInnerHTML={{__html: this.props.txt(`No files have been cached or you have <i>opcache.file_cache_only</i> turned on`)}}></p>;
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
        const showing = showingTotal !== allFilesTotal ? ", {1} showing due to filter '{2}'" : "";

        return (
            <div>
                <form action="#">
                    <label htmlFor="frmFilter">{this.props.txt('Start typing to filter on script path')}</label><br/>
                    <input type="text" name="filter" id="frmFilter" className="file-filter" onChange={e => {this.setSearchTerm(e.target.value)}} />
                </form>

                <h3>{this.props.txt(`{0} files cached${showing}`, allFilesTotal, showingTotal, this.state.searchTerm)}</h3>

                { this.props.allow.invalidate && this.state.searchTerm && showingTotal !== allFilesTotal &&
                    <p><a href={`?invalidate_searched=${encodeURIComponent(this.state.searchTerm)}`} onClick={this.handleInvalidate}>{this.props.txt('Invalidate all matching files')}</a></p>
                }

                <div className="paginate-filter">
                    {this.doPagination && <Pagination
                        totalRecords={filesInSearch.length}
                        pageLimit={this.props.perPageLimit}
                        pageNeighbours={2}
                        onPageChanged={this.onPageChanged}
                        refresh={this.state.refreshPagination}
                        txt={this.props.txt}
                    />}
                    <nav className="filter" aria-label={this.props.txt('Sort order')}>
                        <select name="sortBy" onChange={this.changeSort} value={this.state.sortBy}>
                            <option value="last_used_timestamp">{this.props.txt('Last used')}</option>
                            <option value="last_modified">{this.props.txt('Last modified')}</option>
                            <option value="full_path">{this.props.txt('Path')}</option>
                            <option value="hits">{this.props.txt('Number of hits')}</option>
                            <option value="memory_consumption">{this.props.txt('Memory consumption')}</option>
                        </select>
                        <select name="sortDir" onChange={this.changeSort} value={this.state.sortDir}>
                            <option value="desc">{this.props.txt('Descending')}</option>
                            <option value="asc">{this.props.txt('Ascending')}</option>
                        </select>
                    </nav>
                </div>

                <table className="tables cached-list-table">
                    <thead>
                    <tr>
                        <th>{this.props.txt('Script')}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {filesInPage.map((file, index) => {
                        return <CachedFile
                            key={file.full_path}
                            canInvalidate={this.props.allow.invalidate}
                            realtime={this.props.realtime}
                            txt={this.props.txt}
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
                        <b>{this.props.txt('hits')}: </b><span>{this.props.readable.hits}, </span>
                        <b>{this.props.txt('memory')}: </b><span>{this.props.readable.memory_consumption}, </span>
                        { this.props.last_modified && <><b>{this.props.txt('last modified')}: </b><span>{this.props.last_modified}, </span></> }
                        <b>{this.props.txt('last used')}: </b><span>{this.props.last_used}</span>
                    </span>
                    { !this.props.timestamp && <span className="invalid file-metainfo"> - {this.props.txt('has been invalidated')}</span> }
                    { this.props.canInvalidate && <span>,&nbsp;<a className="file-metainfo"
                          href={'?invalidate=' + this.props.full_path} data-file={this.props.full_path}
                          onClick={this.handleInvalidate}>{this.props.txt('force file invalidation')}</a></span> }
                </td>
            </tr>
        );
    }
}


function buildFileTree(files) {
    const root = { name: '', path: '', children: {}, isDir: true, value: 0, hits: 0, fileCount: 0 };
    for (const file of files) {
        const parts = file.full_path.split('/').filter(p => p !== '');
        if (parts.length === 0) continue;
        let node = root;
        for (let i = 0; i < parts.length - 1; i++) {
            const p = parts[i];
            if (!node.children[p]) {
                node.children[p] = {
                    name: p,
                    path: (node.path ? node.path + '/' : '') + p,
                    children: {},
                    isDir: true,
                    value: 0,
                    hits: 0,
                    fileCount: 0
                };
            }
            node = node.children[p];
        }
        const leafName = parts[parts.length - 1];
        node.children[leafName] = {
            name: leafName,
            path: (node.path ? node.path + '/' : '') + leafName,
            isDir: false,
            value: file.memory_consumption || 0,
            hits: file.hits || 0,
            fileCount: 1,
            file
        };
    }
    const aggregate = (node) => {
        if (!node.isDir) return;
        let totalValue = 0, totalHits = 0, totalFiles = 0;
        for (const key of Object.keys(node.children)) {
            const child = node.children[key];
            aggregate(child);
            totalValue += child.value;
            totalHits += child.hits;
            totalFiles += child.fileCount;
        }
        node.value = totalValue;
        node.hits = totalHits;
        node.fileCount = totalFiles;
    };
    aggregate(root);
    return root;
}

function nodeFromPath(root, path) {
    if (!path) return root;
    const parts = path.split('/').filter(p => p !== '');
    let node = root;
    for (const p of parts) {
        if (node.children && node.children[p]) {
            node = node.children[p];
        } else {
            return root;
        }
    }
    return node;
}

function squarify(items, rect) {
    if (!items.length) return [];
    const totalValue = items.reduce((s, i) => s + (i.value || 0), 0);
    if (totalValue <= 0 || rect.w <= 0 || rect.h <= 0) return [];
    const area = rect.w * rect.h;
    const scaled = items
        .filter(i => (i.value || 0) > 0)
        .map(i => ({ item: i, area: ((i.value || 0) / totalValue) * area }))
        .sort((a, b) => b.area - a.area);
    const placed = [];
    let remaining = scaled;
    let row = [];
    let cur = { ...rect };
    const worst = (rowArr, side) => {
        if (!rowArr.length) return Infinity;
        const sum = rowArr.reduce((s, r) => s + r.area, 0);
        if (sum === 0) return Infinity;
        let maxA = -Infinity, minA = Infinity;
        for (const r of rowArr) {
            if (r.area > maxA) maxA = r.area;
            if (r.area < minA) minA = r.area;
        }
        const s2 = sum * sum;
        const w2 = side * side;
        return Math.max((w2 * maxA) / s2, s2 / (w2 * minA));
    };
    const layoutRow = (rowArr, r) => {
        const sum = rowArr.reduce((s, x) => s + x.area, 0);
        const horizontal = r.w >= r.h;
        if (horizontal) {
            const colW = sum / r.h;
            let y = r.y;
            for (const x of rowArr) {
                const h = x.area / colW;
                placed.push({ item: x.item, x: r.x, y, w: colW, h });
                y += h;
            }
            return { x: r.x + colW, y: r.y, w: r.w - colW, h: r.h };
        } else {
            const rowH = sum / r.w;
            let xPos = r.x;
            for (const x of rowArr) {
                const w = x.area / rowH;
                placed.push({ item: x.item, x: xPos, y: r.y, w, h: rowH });
                xPos += w;
            }
            return { x: r.x, y: r.y + rowH, w: r.w, h: r.h - rowH };
        }
    };
    while (remaining.length > 0) {
        const side = Math.min(cur.w, cur.h);
        const next = remaining[0];
        if (row.length === 0 || worst([...row, next], side) <= worst(row, side)) {
            row.push(next);
            remaining = remaining.slice(1);
        } else {
            cur = layoutRow(row, cur);
            row = [];
        }
    }
    if (row.length > 0) layoutRow(row, cur);
    return placed;
}

function lerpHeatColor(t) {
    const a = [108, 166, 239];
    const b = [255, 116, 0];
    const clamp = Math.max(0, Math.min(1, t));
    const r = Math.round(a[0] + (b[0] - a[0]) * clamp);
    const g = Math.round(a[1] + (b[1] - a[1]) * clamp);
    const bl = Math.round(a[2] + (b[2] - a[2]) * clamp);
    return `rgb(${r},${g},${bl})`;
}

function formatBytesShort(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

function Treemap(props) {
    const [zoomPath, setZoomPath] = React.useState('');
    const [hovered, setHovered] = React.useState(null);

    const tree = React.useMemo(
        () => buildFileTree(props.allFiles || []),
        [props.allFiles]
    );
    const currentNode = React.useMemo(
        () => nodeFromPath(tree, zoomPath),
        [tree, zoomPath]
    );

    const VIEW_W = 1200;
    const VIEW_H = 600;

    const childItems = React.useMemo(() => {
        if (!currentNode || !currentNode.isDir) return [];
        const items = Object.values(currentNode.children).filter(c => c.value > 0);
        return squarify(items, { x: 0, y: 0, w: VIEW_W, h: VIEW_H });
    }, [currentNode]);

    const maxHits = React.useMemo(() => {
        let m = 0;
        if (currentNode && currentNode.children) {
            for (const c of Object.values(currentNode.children)) {
                if ((c.hits || 0) > m) m = c.hits;
            }
        }
        return m;
    }, [currentNode]);

    if (!props.allow.fileList) {
        return null;
    }
    if (!props.allFiles || props.allFiles.length === 0) {
        return <p dangerouslySetInnerHTML={{__html: props.txt(`No files have been cached or you have <i>opcache.file_cache_only</i> turned on`)}}></p>;
    }

    const breadcrumbParts = currentNode.path
        ? currentNode.path.split('/').filter(p => p !== '')
        : [];

    return (
        <div className="treemap-container">
            <h3>{props.txt('{0} files cached', props.allFiles.length)}</h3>
            <nav className="treemap-breadcrumb" aria-label={props.txt('Treemap location')}>
                <a href="#" onClick={e => { e.preventDefault(); setZoomPath(''); }}>{props.txt('root')}</a>
                {breadcrumbParts.map((part, i) => {
                    const targetPath = breadcrumbParts.slice(0, i + 1).join('/');
                    return (
                        <React.Fragment key={targetPath}>
                            <span className="treemap-breadcrumb-sep"> / </span>
                            <a href="#" onClick={e => { e.preventDefault(); setZoomPath(targetPath); }}>{part}</a>
                        </React.Fragment>
                    );
                })}
            </nav>
            <p className="treemap-meta">
                <span><b>{props.txt('files')}:</b> {currentNode.fileCount.toLocaleString()}</span>
                <span><b>{props.txt('memory')}:</b> {formatBytesShort(currentNode.value)}</span>
                <span><b>{props.txt('hits')}:</b> {(currentNode.hits || 0).toLocaleString()}</span>
                <span className="treemap-hint">{props.txt('Click a directory to zoom in')}</span>
            </p>
            <div className="treemap-svg-wrap">
                <svg
                    className="treemap-svg"
                    viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={props.txt('Treemap of cached files')}
                >
                    {childItems.map(({ item, x, y, w, h }) => {
                        const t = maxHits > 0
                            ? Math.log(1 + (item.hits || 0)) / Math.log(1 + maxHits)
                            : 0;
                        const fill = lerpHeatColor(t);
                        const showLabel = w > 60 && h > 18;
                        const tip = (item.isDir
                            ? `${item.path}/\n${props.txt('files')}: ${item.fileCount}\n${props.txt('memory')}: ${formatBytesShort(item.value)}\n${props.txt('hits')}: ${(item.hits || 0).toLocaleString()}`
                            : `${item.path}\n${props.txt('memory')}: ${formatBytesShort(item.value)}\n${props.txt('hits')}: ${(item.hits || 0).toLocaleString()}`
                        );
                        return (
                            <g key={item.path}
                               className={`treemap-cell ${item.isDir ? 'is-dir' : 'is-file'}`}
                               onClick={() => { if (item.isDir) setZoomPath(item.path); }}
                               onMouseEnter={() => setHovered(item)}
                               onMouseLeave={() => setHovered(prev => (prev === item ? null : prev))}
                            >
                                <rect x={x} y={y} width={w} height={h} fill={fill} />
                                {showLabel && (
                                    <text x={x + 4} y={y + 14} className="treemap-label">
                                        {item.name}{item.isDir ? '/' : ''}
                                    </text>
                                )}
                                <title>{tip}</title>
                            </g>
                        );
                    })}
                </svg>
            </div>
            <p className="treemap-hover-info">
                {hovered
                    ? <>
                        <b>{hovered.path}{hovered.isDir ? '/' : ''}</b>
                        {hovered.isDir
                            ? <> — {props.txt('{0} files', hovered.fileCount)}, {formatBytesShort(hovered.value)}, {(hovered.hits || 0).toLocaleString()} {props.txt('hits')}</>
                            : <> — {formatBytesShort(hovered.value)}, {(hovered.hits || 0).toLocaleString()} {props.txt('hits')}</>
                        }
                    </>
                    : <span className="treemap-hover-placeholder">{props.txt('Hover a tile for details')}</span>
                }
            </p>
        </div>
    );
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
            return <p>{this.props.txt('No files have been ignored via <i>opcache.blacklist_filename</i>')}</p>;
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
                <h3>{this.props.txt('{0} ignore file locations', allFilesTotal)}</h3>

                {this.doPagination && <Pagination
                    totalRecords={allFilesTotal}
                    pageLimit={this.props.perPageLimit}
                    pageNeighbours={2}
                    onPageChanged={this.onPageChanged}
                    refresh={this.state.refreshPagination}
                    txt={this.props.txt}
                />}

                <table className="tables ignored-list-table">
                    <thead><tr><th>{this.props.txt('Path')}</th></tr></thead>
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
            return <p>{this.props.txt('No files have been preloaded <i>opcache.preload</i>')}</p>;
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
                <h3>{this.props.txt('{0} preloaded files', allFilesTotal)}</h3>

                {this.doPagination && <Pagination
                    totalRecords={allFilesTotal}
                    pageLimit={this.props.perPageLimit}
                    pageNeighbours={2}
                    onPageChanged={this.onPageChanged}
                    refresh={this.state.refreshPagination}
                    txt={this.props.txt}
                />}

                <table className="tables preload-list-table">
                    <thead><tr><th>{this.props.txt('Path')}</th></tr></thead>
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
                                        <a className="page-link" href="#" aria-label={this.props.txt('Previous')} onClick={this.handleJumpLeft}>
                                            <span aria-hidden="true">↞</span>
                                            <span className="sr-only">{this.props.txt('Jump back')}</span>
                                        </a>
                                    </li>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label={this.props.txt('Previous')} onClick={this.handleMoveLeft}>
                                            <span aria-hidden="true">⇠</span>
                                            <span className="sr-only">{this.props.txt('Previous page')}</span>
                                        </a>
                                    </li>
                                </React.Fragment>
                            );
                        }
                        if (page === "RIGHT") {
                            return (
                                <React.Fragment key={index}>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label={this.props.txt('Next')} onClick={this.handleMoveRight}>
                                            <span aria-hidden="true">⇢</span>
                                            <span className="sr-only">{this.props.txt('Next page')}</span>
                                        </a>
                                    </li>
                                    <li className="page-item arrow">
                                        <a className="page-link" href="#" aria-label={this.props.txt('Next')} onClick={this.handleJumpRight}>
                                            <span aria-hidden="true">↠</span>
                                            <span className="sr-only">{this.props.txt('Jump forward')}</span>
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
               title={props.txt("opcache-gui (currently version {0}) on GitHub", props.version)}
            >https://github.com/amnuts/opcache-gui - {props.txt("version {0}", props.version)}</a>

            <a className="sponsor-link" href="https://github.com/sponsors/amnuts"
               target="_blank"
               title={props.txt("Sponsor this project and author on GitHub")}
            >{props.txt("Sponsor this project")}</a>
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

