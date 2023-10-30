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
                <Footer version={this.props.opstate.version.gui} />
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
                    (props.allow.filelist && props.opstate.blacklist.length &&
                        <div label={props.txt("Ignored")} tabId="ignored" tabIndex={3}>
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
                        <div label={props.txt("Preloaded")} tabId="preloaded" tabIndex={4}>
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
                            className={`nav-tab-link-reset${props.resetting ? ' is-resetting activated' : ''}`}
                            handler={props.resetHandler}
                            tabIndex={5}
                            icon={(
                                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" viewBox="0 0 489.645 489.645">
                                    <path d="M460.656,132.911c-58.7-122.1-212.2-166.5-331.8-104.1c-9.4,5.2-13.5,16.6-8.3,27c5.2,9.4,16.6,13.5,27,8.3 c99.9-52,227.4-14.9,276.7,86.3c65.4,134.3-19,236.7-87.4,274.6c-93.1,51.7-211.2,17.4-267.6-70.7l69.3,14.5 c10.4,2.1,21.8-4.2,23.9-15.6c2.1-10.4-4.2-21.8-15.6-23.9l-122.8-25c-20.6-2-25,16.6-23.9,22.9l15.6,123.8 c1,10.4,9.4,17.7,19.8,17.7c12.8,0,20.8-12.5,19.8-23.9l-6-50.5c57.4,70.8,170.3,131.2,307.4,68.2 C414.856,432.511,548.256,314.811,460.656,132.911z"/>
                                </svg>
                            )}
                        ></div>
                }
                {
                    props.allow.realtime &&
                        <div label={props.txt(`${props.realtime ? 'Disable' : 'Enable'} real-time update`)} tabId="toggleRealtime"
                            className={`nav-tab-link-realtime${props.realtime ? ' live-update activated' : ''}`}
                            handler={props.realtimeHandler}
                            tabIndex={6}
                            icon={(
                                <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" viewBox="0 0 489.698 489.698">
                                    <path d="M468.999,227.774c-11.4,0-20.8,8.3-20.8,19.8c-1,74.9-44.2,142.6-110.3,178.9c-99.6,54.7-216,5.6-260.6-61l62.9,13.1 c10.4,2.1,21.8-4.2,23.9-15.6c2.1-10.4-4.2-21.8-15.6-23.9l-123.7-26c-7.2-1.7-26.1,3.5-23.9,22.9l15.6,124.8 c1,10.4,9.4,17.7,19.8,17.7c15.5,0,21.8-11.4,20.8-22.9l-7.3-60.9c101.1,121.3,229.4,104.4,306.8,69.3 c80.1-42.7,131.1-124.8,132.1-215.4C488.799,237.174,480.399,227.774,468.999,227.774z"/>
                                    <path d="M20.599,261.874c11.4,0,20.8-8.3,20.8-19.8c1-74.9,44.2-142.6,110.3-178.9c99.6-54.7,216-5.6,260.6,61l-62.9-13.1 c-10.4-2.1-21.8,4.2-23.9,15.6c-2.1,10.4,4.2,21.8,15.6,23.9l123.8,26c7.2,1.7,26.1-3.5,23.9-22.9l-15.6-124.8 c-1-10.4-9.4-17.7-19.8-17.7c-15.5,0-21.8,11.4-20.8,22.9l7.2,60.9c-101.1-121.2-229.4-104.4-306.8-69.2 c-80.1,42.6-131.1,124.8-132.2,215.3C0.799,252.574,9.199,261.874,20.599,261.874z"/>
                                </svg>
                            )}
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
            colourMode: 0, // 0 = light, 1 = dark
        };
    }

    onClickTabItem = (tab) => {
        this.setState({ activeTab: tab });
    }

    onClickModeSwitch = (event) => {
        event.stopPropagation()
        console.log(event)
        this.setState({ colourMode: event.target.checked ? 1 : 0 });
        if (event.target.checked) {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    }

    render() {
        const {
            onClickTabItem,
            onClickModeSwitch,
            state: { activeTab, colourMode }
        } = this;

        const children = this.props.children.filter(Boolean);

        return (
            <>
                <ul className="nav-tab-list">
                    {children.map((child) => {
                        const { tabId, label, className, handler, tabIndex, icon } = child.props;
                        return (
                            <Tab
                                activeTab={activeTab}
                                key={tabId}
                                label={label}
                                onClick={handler || onClickTabItem}
                                className={className}
                                tabIndex={tabIndex}
                                tabId={tabId}
                                icon={icon}
                            />
                        );
                    })}

                    <Tab
                        activeTab={activeTab}
                        key={7}
                        label={(
                            <div className="mode-container" onClick={onClickModeSwitch}>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                </svg>
                                <label className="switch mode-switch">
                                    <input type="checkbox" name="dark_mode" id="dark_mode" value={colourMode} />
                                    <label htmlFor="dark_mode" data-on="Dark" data-off="Light" className="mode-switch-inner"></label>
                                </label>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                </svg>
                            </div>
                        )}
                        onClick={() => null}
                        className=""
                        tabIndex={7}
                        tabId="mode-switch"
                    />
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
            props: { activeTab, label, tabIndex, tabId, icon },
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
            >{icon}{label}</li>
        );
    }
}


function OverviewCounts(props) {
    if (props.overview === false) {
        return (
            <p class="file-cache-only">
                {props.txt(`You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.`)}
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
            <h3 className="widget-header">memory usage</h3>
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
                <p><b>{props.txt('number of cached')} files:</b> {props.num_cached_scripts}</p>
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
            return <p>{this.props.txt('No files have been cached or you have <i>opcache.file_cache_only</i> turned on')}</p>;
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
               title="opcache-gui (currently version {props.version}) on GitHub"
            ><svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="1.19em" height="1em" viewBox="0 0 1664 1408">
                <path d="M640 960q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm640 0q0 40-12.5 82t-43 76t-72.5 34t-72.5-34t-43-76t-12.5-82t12.5-82t43-76t72.5-34t72.5 34t43 76t12.5 82zm160 0q0-120-69-204t-187-84q-41 0-195 21q-71 11-157 11t-157-11q-152-21-195-21q-118 0-187 84t-69 204q0 88 32 153.5t81 103t122 60t140 29.5t149 7h168q82 0 149-7t140-29.5t122-60t81-103t32-153.5zm224-176q0 207-61 331q-38 77-105.5 133t-141 86t-170 47.5t-171.5 22t-167 4.5q-78 0-142-3t-147.5-12.5t-152.5-30t-137-51.5t-121-81t-86-115Q0 992 0 784q0-237 136-396q-27-82-27-170q0-116 51-218q108 0 190 39.5T539 163q147-35 309-35q148 0 280 32q105-82 187-121t189-39q51 102 51 218q0 87-27 168q136 160 136 398z"/>
            </svg> https://github.com/amnuts/opcache-gui, v{props.version}</a>

            <a className="sponsor-link" href="https://github.com/sponsors/amnuts"
               target="_blank"
               title="Sponsor this project and author on GitHub"
            ><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                <path fill="crimson" d="M12 21.35l-1.45-1.32c-5.15-4.67-8.55-7.75-8.55-11.53 0-3.08 2.42-5.5 5.5-5.5 1.74 0 3.41.81 4.5 2.09 1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.42 5.5 5.5 0 3.78-3.4 6.86-8.55 11.54l-1.45 1.31z"/>
            </svg> Sponsor this project</a>
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

