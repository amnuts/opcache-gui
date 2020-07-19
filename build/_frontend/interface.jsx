var UsageGraph = React.createClass({
    getInitialState: function() {
        return {
            gauge : null
        };
    },
    componentDidMount: function() {
        if (this.props.chart) {
            this.state.gauge = new Gauge('#' + this.props.gaugeId);
            this.state.gauge.setValue(this.props.value);
        }
    },
    componentDidUpdate: function() {
        if (this.state.gauge != null) {
            this.state.gauge.setValue(this.props.value);
        }
    },
    render: function() {
        if (this.props.chart == true) {
            return(<canvas id={this.props.gaugeId} className="graph-widget" width="250" height="250" data-value={this.props.value} />);
        }
        return(<p><span className="large">{this.props.value}</span><span>%</span></p>);
    }
});

var MemoryUsagePanel = React.createClass({
    render: function() {
        return (
            <div className="widget-panel">
                <h3 className="widget-header">memory usage</h3>
                <div className="widget-value widget-info">
                    <p><b>total memory:</b> {this.props.total}</p>
                    <p><b>used memory:</b> {this.props.used}</p>
                    <p><b>free memory:</b> {this.props.free}</p>
                    <p><b>wasted memory:</b> {this.props.wasted} ({this.props.wastedPercent}%)</p>
                </div>
            </div>
        );
    }
});

var StatisticsPanel = React.createClass({
    render: function() {
        return (
            <div className="widget-panel">
                <h3 className="widget-header">opcache statistics</h3>
                <div className="widget-value widget-info">
                    <p><b>number of cached files:</b> {this.props.num_cached_scripts}</p>
                    <p><b>number of hits:</b> {this.props.hits}</p>
                    <p><b>number of misses:</b> {this.props.misses}</p>
                    <p><b>blacklist misses:</b> {this.props.blacklist_miss}</p>
                    <p><b>number of cached keys:</b> {this.props.num_cached_keys}</p>
                    <p><b>max cached keys:</b> {this.props.max_cached_keys}</p>
                </div>
            </div>
        );
    }
});

var InternedStringsPanel = React.createClass({
    render: function() {
        return (
            <div className="widget-panel">
                <h3 className="widget-header">interned strings usage</h3>
                <div className="widget-value widget-info">
                    <p><b>buffer size:</b> {this.props.buffer_size}</p>
                    <p><b>used memory:</b> {this.props.strings_used_memory}</p>
                    <p><b>free memory:</b> {this.props.strings_free_memory}</p>
                    <p><b>number of strings:</b> {this.props.number_of_strings}</p>
                </div>
            </div>
        );
    }
});

var OverviewCounts = React.createClass({
    getInitialState: function() {
        return {
            data  : opstate.overview,
            chart : useCharts,
            highlight: highlight
        };
    },
    render: function() {
        if (this.state.data == false) {
            return (
                <p class="file-cache-only">
                    You have <i>opcache.file_cache_only</i> turned on.  As a result, the memory information is not available.  Statistics and file list may also not be returned by <i>opcache_get_statistics()</i>.
                </p>
            );
        }
        var interned = (this.state.data.readable.interned != null 
            ? <InternedStringsPanel
                    buffer_size={this.state.data.readable.interned.buffer_size}
                    strings_used_memory={this.state.data.readable.interned.strings_used_memory}
                    strings_free_memory={this.state.data.readable.interned.strings_free_memory}
                    number_of_strings={this.state.data.readable.interned.number_of_strings}
              /> 
            : ''
        );

        var memoryHighlight = this.state.highlight.memory ? (
                <div className="widget-panel">
                    <h3 className="widget-header">memory</h3>
                    <p className="widget-value"><UsageGraph chart={this.state.chart} value={this.state.data.used_memory_percentage} gaugeId="memoryUsageCanvas"/></p>
                </div>
            ) : null;

        var hitsHighlight = this.state.highlight.hits ? (
                <div className="widget-panel">
                    <h3 className="widget-header">hit rate</h3>
                    <p className="widget-value"><UsageGraph chart={this.state.chart} value={this.state.data.hit_rate_percentage} gaugeId="hitRateCanvas"/></p>
                </div>
            ) : null;

        var keysHighlight = this.state.highlight.keys ? (
                <div className="widget-panel">
                    <h3 className="widget-header">keys</h3>
                    <p className="widget-value"><UsageGraph chart={this.state.chart} value={this.state.data.used_key_percentage} gaugeId="keyUsageCanvas"/></p>
                </div>
            ) : null;


        return (
            <div>
                {memoryHighlight}
                {hitsHighlight}
                {keysHighlight}
                <MemoryUsagePanel
                    total={this.state.data.readable.total_memory}
                    used={this.state.data.readable.used_memory}
                    free={this.state.data.readable.free_memory}
                    wasted={this.state.data.readable.wasted_memory}
                    wastedPercent={this.state.data.wasted_percentage}
                />
                <StatisticsPanel
                    num_cached_scripts={this.state.data.readable.num_cached_scripts}
                    hits={this.state.data.readable.hits}
                    misses={this.state.data.readable.misses}
                    blacklist_miss={this.state.data.readable.blacklist_miss}
                    num_cached_keys={this.state.data.readable.num_cached_keys}
                    max_cached_keys={this.state.data.readable.max_cached_keys}
                />
                {interned}
            </div>
        );
    }
});

var GeneralInfo = React.createClass({
    getInitialState: function() {
        return {
            version : opstate.version,
            start : opstate.overview ? opstate.overview.readable.start_time : null,
            reset : opstate.overview ? opstate.overview.readable.last_restart_time : null
        };
    },
    render: function() {
        var startTime = this.state.start
            ? <tr><td>Start time</td><td>{this.state.start}</td></tr>
            : '';
        var lastReset = this.state.reset
            ? <tr><td>Last reset</td><td>{this.state.reset}</td></tr>
            : '';
        return (
            <table className="tables general-info-table">
                <thead>
                    <tr><th colSpan="2">General info</th></tr>
                </thead>
                <tbody>
                    <tr><td>Zend OPcache</td><td>{this.state.version.version}</td></tr>
                    <tr><td>PHP</td><td>{this.state.version.php}</td></tr>
                    <tr><td>Host</td><td>{this.state.version.host}</td></tr>
                    <tr><td>Server Software</td><td>{this.state.version.server}</td></tr>
                    { startTime }
                    { lastReset }
                </tbody>
            </table>
        );
    }
});

var Directives = React.createClass({
    getInitialState: function() {
        return { data : opstate.directives };
    },
    render: function() {
        var directiveNodes = this.state.data.map(function(directive) {
            var map = { 'opcache.':'', '_':' ' };
            var dShow = directive.k.replace(/opcache\.|_/gi, function(matched){
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
                <thead>
                    <tr><th colSpan="2">Directives</th></tr>
                </thead>
                <tbody>{directiveNodes}</tbody>
            </table>
        );
    }
});

var Files = React.createClass({
    getInitialState: function() {
        return {
            data : opstate.files,
            showing: null,
            allowFiles: allowFiles
        };
    },
    handleInvalidate: function(e) {
        e.preventDefault();
        if (realtime) {
            $.get('#', { invalidate: e.currentTarget.getAttribute('data-file') }, function(data) {
                console.log('success: ' + data.success);
            }, 'json');
        } else {
            window.location.href = e.currentTarget.href;
        }
    },
    render: function() {
        if (this.state.allowFiles) {
            var fileNodes = this.state.data.map(function(file, i) {
                var invalidate, invalidated;
                if (file.timestamp == 0) {
                    invalidated = <span><i className="invalid metainfo"> - has been invalidated</i></span>;
                }
                if (canInvalidate) {
                    invalidate = <span>,&nbsp;<a className="file-metainfo" href={'?invalidate='
                        + file.full_path} data-file={file.full_path} onClick={this.handleInvalidate}>force file invalidation</a></span>;
                }
                return (
                    <tr key={file.full_path} data-path={file.full_path.toLowerCase()} className={i%2?'alternate':''}>
                        <td>
                            <span className="file-pathname">{file.full_path}</span>
                            <FilesMeta data={[file.readable.hits, file.readable.memory_consumption, file.last_used]} />
                            {invalidate}
                            {invalidated}
                        </td>
                    </tr>
                );
            }.bind(this));
            return (
                <div>
                    <FilesListed showing={this.state.showing}/>
                    <table className="tables file-list-table">
                        <thead>
                        <tr>
                            <th>Script</th>
                        </tr>
                        </thead>
                        <tbody>{fileNodes}</tbody>
                    </table>
                </div>
            );
        } else {
            return <span></span>;
        }
    }
});

var FilesMeta = React.createClass({
    render: function() {
        return (
            <span className="file-metainfo">
                <b>hits: </b><span>{this.props.data[0]}, </span>
                <b>memory: </b><span>{this.props.data[1]}, </span>
                <b>last used: </b><span>{this.props.data[2]}</span>
            </span>
        );
    }
});

var FilesListed = React.createClass({
    getInitialState: function() {
        return {
            formatted : opstate.overview ? opstate.overview.readable.num_cached_scripts : 0,
            total     : opstate.overview ? opstate.overview.num_cached_scripts : 0
        };
    },
    render: function() {
        var display = this.state.formatted + ' file' + (this.state.total == 1 ? '' : 's') + ' cached';
        if (this.props.showing !== null && this.props.showing != this.state.total) {
            display += ', ' + this.props.showing + ' showing due to filter';
        }
        return (<h3>{display}</h3>);
    }
});

var overviewCountsObj = ReactDOM.render(<OverviewCounts/>, document.getElementById('counts'));
var generalInfoObj = ReactDOM.render(<GeneralInfo/>, document.getElementById('generalInfo'));
var filesObj = ReactDOM.render(<Files/>, document.getElementById('filelist'));
ReactDOM.render(<Directives/>, document.getElementById('directives'));
