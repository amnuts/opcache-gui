function Interface(props) {
    return (
        <>
            <header><MainNavigation {...props} /></header>
            <Footer {...props} />
        </>
    );
}


class MainNavigation extends React.Component {
    constructor(props) {
        super(props);
    }

    renderOverview() {
        return (
            <div label="Overview" tabId="overview">
                <>
                    <OverviewCounts {...this.props} />
                    <div id="info" className="tab-content-overview-info">
                        <GeneralInfo {...this.props} />
                        <Directives {...this.props} />
                        <Functions {...this.props} />
                        <br style={{ clear: 'both' }} />
                    </div>
                </>
            </div>
        );
    }

    renderFileList() {
        if (this.props.allow.filelist) {
            return (
                <div label="Files" tabId="files">
                    <Files {...this.props} />
                </div>
            );
        }
        return null;
    }

    renderReset() {
        if (this.props.allow.reset) {
            return (
                <div label="Reset cache" tabId="resetCache" link="?reset=1"></div>
            );
        }
        return null;
    }

    renderRealtime() {
        if (this.props.allow.realtime) {
            return (
                <div label="Enable real-time update" tabId="toggleRealtime"></div>
            );
        }
        return null;
    }

    render() {
        return (
            <nav className="main-nav">
                <Tabs>
                    {this.renderOverview()}
                    {this.renderFileList()}
                    {this.renderReset()}
                    {this.renderRealtime()}
                </Tabs>
            </nav>
        );
    }
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
            props: { children },
            state: { activeTab }
        } = this;

        return (
            <>
                <ul className="nav-tab-list">
                    {children.map((child) => {
                        const { label } = child.props;
                        return (
                            <Tab
                                activeTab={activeTab}
                                key={label}
                                label={label}
                                onClick={onClickTabItem}
                            />
                        );
                    })}
                </ul>
                <div className="tab-content">
                    {children.map((child) => {
                        if (child.props.label !== activeTab) return undefined;
                        return child.props.children;
                    })}
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
            props: {activeTab, label },
        } = this;

        let className = 'nav-tab';
        if (activeTab === label) {
            className += ' active';
        }

        return (
            <li className={className} onClick={onClick}>{label}</li>
        );
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
        return <InternedStringsPanel
            buffer_size={this.readable.interned.buffer_size}
            strings_used_memory={this.readable.interned.strings_used_memory}
            strings_free_memory={this.readable.interned.strings_free_memory}
            number_of_strings={this.readable.interned.number_of_strings}
        />;
    }

    renderGraphs(useCharts, graphList) {
        return graphList.map((graph) => {
            if (!graph.show) {
                return null;
            }
            return (
                <div className="widget-panel" key={graph.id}>
                    <h3 className="widget-header">{graph.title}</h3>
                    <p className="widget-value"><UsageGraph charts={useCharts} value={graph.value} gaugeId={graph.id} /></p>
                </div>
            );
        });
    }

    render() {
        if (this.overview === false) {
            return (
                <p class="file-cache-only">
                    You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.
                </p>
            );
        }
        return (
            <div id="counts" className="tab-content-overview-counts">
                {this.renderGraphs(this.props.useCharts, [
                    {id: 'memoryUsageCanvas', title: 'memory', show: this.props.highlight.memory, value: this.props.opstate.overview.used_memory_percentage},
                    {id: 'hitRateCanvas', title: 'hit rate', show: this.props.highlight.hits, value: this.props.opstate.overview.hit_rate_percentage},
                    {id: 'keyUsageCanvas', title: 'keys', show: this.props.highlight.keys, value: this.props.opstate.overview.used_key_percentage}
                ])}
                <MemoryUsagePanel
                    total={this.readable.total_memory}
                    used={this.readable.used_memory}
                    free={this.readable.free_memory}
                    wasted={this.readable.wasted_memory}
                    wastedPercent={this.overview.wasted_percentage}
                />
                <StatisticsPanel
                    num_cached_scripts={this.readable.num_cached_scripts}
                    hits={this.readable.hits}
                    misses={this.readable.misses}
                    blacklist_miss={this.readable.blacklist_miss}
                    num_cached_keys={this.readable.num_cached_keys}
                    max_cached_keys={this.readable.max_cached_keys}
                />
                {this.renderInternedStrings()}
            </div>
        );
    }
}


class GeneralInfo extends React.Component {
    constructor(props) {
        super(props);
        this.start = props.opstate.overview ? props.opstate.overview.readable.start_time : null;
        this.reset = props.opstate.overview ? props.opstate.overview.readable.last_restart_time : null;
    }

    renderStart() {
        return (this.start === null
            ? null
            : <tr><td>Start time</td><td>{this.start}</td></tr>
        );
    }

    renderReset() {
        return (this.reset === null
            ? null
            : <tr><td>Last reset</td><td>{this.reset}</td></tr>
        );
    }

    render() {
        return (
            <table className="tables general-info-table">
                <thead>
                    <tr><th colSpan="2">General info</th></tr>
                </thead>
                <tbody>
                    <tr><td>Zend OPcache</td><td>{this.props.opstate.version.version}</td></tr>
                    <tr><td>PHP</td><td>{this.props.opstate.version.php}</td></tr>
                    <tr><td>Host</td><td>{this.props.opstate.version.host}</td></tr>
                    <tr><td>Server Software</td><td>{this.props.opstate.version.server}</td></tr>
                    { this.renderStart() }
                    { this.renderReset() }
                </tbody>
            </table>
        );
    }
}


class Directives extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        let directiveNodes = this.props.opstate.directives.map(function(directive) {
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
                    vShow = directive.v.map((item, key) => {
                        return <span key={key}>{item}<br/></span>
                    });
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
}

function Functions(props) {
    return (
        <div id="functions">
            <table className="tables">
                <thead>
                <tr><th>Available functions</th></tr>
                </thead>
                <tbody>
                {props.opstate.functions.map(f =>
                    <tr key={f}><td><a href={"http://php.net/"+f} title="View manual page" target="_blank">{f}</a></td></tr>
                )}
                </tbody>
            </table>
        </div>
    );
}


function UsageGraph(props) {
    return (props.charts
        ? <Canvas value={props.value} gaugeId={props.gaugeId} />
        : <><span className="large">{props.value}</span><span>%</span></>
    );
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
        }
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
        const { degrees, newdegs } = this.state;
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
        this.loop = setInterval(this.animate, 1000/(this.state.newdegs - this.state.degrees));
    }

    componentDidUpdate() {
        const { degrees } = this.state;
        const text = Math.round((degrees/360)*100) + '%';
        this.ctx.clearRect(0, 0, this.width, this.height);
        this.ctx.beginPath();
        this.ctx.strokeStyle = this.gaugeBackgroundColour;
        this.ctx.lineWidth = 30;
        this.ctx.arc(this.width/2, this.height/2, 100, 0, Math.PI*2, false);
        this.ctx.stroke();
        this.ctx.beginPath();
        this.ctx.strokeStyle = this.gaugeColour;
        this.ctx.lineWidth = 30;
        this.ctx.arc(this.width/2, this.height/2, 100, 0 - (90 * Math.PI / 180), (degrees * Math.PI / 180) - (90 * Math.PI / 180), false);
        this.ctx.stroke();
        this.ctx.fillStyle = this.gaugeColour;
        this.ctx.font = '60px sans-serif';
        this.ctx.fillText(text, (this.width/2) - (this.ctx.measureText(text).width/2), (this.height/2) + 20);
    }

    componentDidMount() {
        this.setState({ newdegs: Math.round(3.6 * this.props.value) });
        this.draw();
    }

    render() {
        return <PureCanvas key={this.props.gaugeId} contextRef={this.saveContext} {...this.props} />;
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
        return (
            <canvas id={this.props.gaugeId} className="graph-widget" width="250" height="250" data-value={this.props.value}
                    ref={node =>
                        node ? this.props.contextRef(node.getContext('2d')) : null
                    }
            />
        );
    }
}


function MemoryUsagePanel(props) {
    return (
        <div className="widget-panel">
            <h3 className="widget-header">memory usage</h3>
            <div className="widget-value widget-info">
                <p><b>total memory:</b> {props.total}</p>
                <p><b>used memory:</b> {props.used}</p>
                <p><b>free memory:</b> {props.free}</p>
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


class Files extends React.Component {
    constructor(props) {
        super(props);
        this.renderFileList = this.renderFileList.bind(this);
        this.renderInvalidateStatus = this.renderInvalidateStatus.bind(this);
        this.renderInvalidateLink = this.renderInvalidateLink.bind(this);
        this.state = {
            data : this.props.opstate.files,
            showing: null
        };
    }

    handleInvalidate(e) {
        e.preventDefault();
        if (realtime) {
            $.get('#', { invalidate: e.currentTarget.getAttribute('data-file') }, function(data) {
                console.log('success: ' + data.success);
            }, 'json');
        } else {
            window.location.href = e.currentTarget.href;
        }
    }

    renderInvalidateStatus(file) {
        return (file.timestamp == 0
            ? <span className="invalid file-metainfo"> - has been invalidated</span>
            : null
        );
    }

    renderInvalidateLink(file, canInvalidate) {
        return (canInvalidate
            ? <span>,&nbsp;<a className="file-metainfo" href={'?invalidate=' + file.full_path} data-file={file.full_path} onClick={this.handleInvalidate}>force file invalidation</a></span>
            : null
        );
    }

    renderFileList() {
        return this.state.data.map(function(file, i) {
            return (
                <tr key={file.full_path} data-path={file.full_path.toLowerCase()} className={i%2?'alternate':''}>
                    <td>
                        <span className="file-pathname">{file.full_path}</span>
                        <FilesMeta data={[file.readable.hits, file.readable.memory_consumption, file.last_used]} />
                        { this.renderInvalidateStatus(file) }
                        { this.renderInvalidateLink(file, this.props.allow.invalidate) }
                    </td>
                </tr>
            );
        }.bind(this));
    }

    render() {
        if (this.props.allow.filelist) {
            return (
                <div>
                    <form action="#">
                        <label htmlFor="frmFilter">Start typing to filter on script path</label><br />
                        <input type="text" name="filter" id="frmFilter" className="file-filter" />
                    </form>
                    <FilesListed
                        showing={this.state.showing}
                        cachedTotal={this.props.opstate.overview.num_cached_scripts}
                        cachedReadable={this.props.opstate.overview.readable.num_cached_scripts}
                    />
                    <table className="tables file-list-table">
                        <thead><tr><th>Script</th></tr></thead>
                        <tbody>{ this.renderFileList() }</tbody>
                    </table>
                </div>
            );
        } else {
            return <span></span>;
        }
    }
}


function FilesMeta(props) {
    return (
        <span className="file-metainfo">
            <b>hits: </b><span>{props.data[0]}, </span>
            <b>memory: </b><span>{props.data[1]}, </span>
            <b>last used: </b><span>{props.data[2]}</span>
        </span>
    );
}


class FilesListed extends React.Component {
    constructor(props) {
        super(props);
        this.formatted = props.cachedReadable || 0;
        this.total = props.cachedTotal || 0;
    }

    render() {
        let display = this.formatted + ' file' + (this.total == 1 ? '' : 's') + ' cached';
        if (this.props.showing !== null && this.props.showing != this.total) {
            display += ', ' + this.props.showing + ' showing due to filter';
        }
        return (
            <h3>{display}</h3>
        );
    }
}


function Footer(props) {
    return (
        <footer className="main-footer">
            <a className="github-link" href="https://github.com/amnuts/opcache-gui"
               target="_blank"
               title="opcache-gui (currently version {props.opstate.version.gui}) on GitHub"
            >https://github.com/amnuts/opcache-gui - version {props.opstate.version.gui}</a>
        </footer>
    );
}
