# opcache-gui

A clean and responsive interface for Zend OPcache information, showing statistics, settings and cached files, and providing a real-time update for the information (using jQuery and React).

[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=acollington&url=https://github.com/amnuts/opcache-gui&title=opcache-gui&language=&tags=github&category=software)
If you like this software or find it helpful then maybe you'll consider supporting my efforts in some way by [signing up to Flattr and leaving a micro-donation](https://flattr.com/@acollington).

### Getting started

There are two ways to getting started using this gui:

#### Copy/clone this repo

The easiest way to start using the opcache-gui is to clone this repo, or simply to copy/paste/download the `index.php` file, to a location which your web server can load.  Then simply point your browser to that location, such as `https://www.example.com/opcache/index.php`.

#### Install via composer

You can include the files with [Composer](https://getcomposer.org/) by running the command `composer require amnuts/opcache-gui`.

Once in your `vendor` directory, there are numerous ways in which you can use the interface.  For example if you're using a framework such as Symfony or Laravel, you could load opcache-gui into a `Controller`.  Your requirements of setting it up within a framework will vary, and wouldn't be possible to detail how to do that within this readme.

The namespace used for the class is `OpcacheGui`, so once the dependency is in your `autoload.php` you can use the `\OpcacheGui\OpCacheService` class.  For example, you could do something like:

```php
<?php

// assuming location of: /var/www/html/opcache.php
require_once __DIR__ . '/../vendor/autoload.php';

// specify your options (see next section)
$options = [/* ... */];

// setup the class
\OpcacheGui\OpCacheService::init($options);
```

And then you can create whatever view you want to show the details.

Alternatively include `vendor/amnuts/opcache-gui/index.php` directly to use the default `$options`:

```php
<?php

// assuming location of: /var/www/html/opcache.php

require_once __DIR__ . '/../vendor/amnuts/opcache-gui/index.php';
```

Or you could simple copy or create a symlink to the `index.php` in the vendor directory:

```shell script
ln -s /var/www/vendor/amnuts/opcache-gui/index.php /var/www/html/opcache.php
```

### Configuration

If you want to set the configuration options just alter the array at the top of the `index.php` script:
```php
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
```

### Overview

The overview will show you all the core information.  From here you'll be able to see what host and platform you're running on, what version of OPcache you're using, when it was last reset, the functions that are available, all the directives and all the statistics associated with the OPcache (number of hits, memory used, free and wasted memory, etc.)

![Overview](http://amnuts.com/images/opcache/screenshot/overview-v2.5.0.png)

### File usage

All the files currently in the cache are listed here with their associated statistics.  You can filter the results very easily to key in on the particular scripts you're looking for, and you can optionally set levels of the path to be hidden (handy if they all share a common root and you don't want that displayed). It will also indicate if the file cache has expired.

If you do not want to show the file list at all then you can use the `allow_filelist` configuration option; setting it to `false` will suppress the file list altogether.

![File list showing filtered results](http://amnuts.com/images/opcache/screenshot/files-v2.png)

### Reset cache

You can reset the whole cache as well as force individual files to become invalidated so they will be cached again.

Both reset types can be disabled with the options `allow_reset` and `allow_invalidate`.

### Real-time updates

The interface can poll every so often to get a fresh look at the opcache.  You can change how often this happens with the option `refresh_time`.  The React javascript library is used to handle data refresh so you don't need to keep reloading the page.

## Project files

The status.jsx file is provided solely for you to be able to edit the jsx code should you wish.  For production purposes it's best to have the jsx code pre-compiled which is what's used in index.php.  You do not need to use status.jsx at all in order to use the opcache gui.  However, should you wish to compile the jsx code then you'll need to use [babel](https://babeljs.io/) or the [react-tools](https://www.npmjs.com/package/react-tools) (no longer supported with newer versions of React).

The composer.json file is provided to allow you to deploy the opcache gui a little easier by using composer.

## Releases

**Version 2.5.4**\
Refined placement of initial css namespace to play nicely within Moodle plugin and possibly other systems.  Also tweaked some CSS.

**Version 2.5.3**\
CSS class names have been added and style rules updated to use them.

**Version 2.5.2**\
Hotfix for the optimisation_level values that was put out in v2.5.1.

**Version 2.5.1**\
A couple bug fixes and improvement on the optimisation level details.
* optimisation_level now shows the levels of optimisations that will be performed rather than an abstract number
* Fixed issue #43
* Fixed issue #44

**Version 2.5.0**\
Added a new highlight chart to show the cached keys percentage with options to turn on/off the individual highlight graphs. 

**Version 2.4.1**\
Mostly bug fixes
* `memory_consumption` and `max_file_size` config settings now display as human-readable sizes
* four missing directives have been included
* better handling if `file_cache_only` is active
* cache-control header set to not cache the page

**Version 2.4.0**\
Adds cookie store for the real-time state allowing real-time to be activated on load.  Cookie name and TTL length can be adjusted in the config

**Version 2.3.0**\
Adds information for interned strings and PHP 5.4 compatibility

**Version 2.2.2**\
Brings in optimisations for the file listing when filtering

**Version 2.2.1**\
Has the gauges now updating with the real-time pulse and a couple rounding issues fixed

**Version 2.2.0**\
Provides the ability to turn on/off the file list (default is on)

**Version 2.1.0**\
Now provides a much easier way to configure some options, be it the poll time, toggling the ability to reset the cache, real-time updates, etc. It also allows you to show the big values (memory usage and hit rate) as gauge graphs instead of big numbers.

**Version 2.0.0**\
Introduces the use of React.js provides the ability to seamlessly update more of the information in real-time (well, every five seconds by default) - so now the files as well as the overview get refreshed. There is an updated look, removing the gradients and going for a flatter feel. And the code in general has had an overhaul.

Releases of the GUI are available at:

https://github.com/amnuts/opcache-gui/releases/

# License

MIT: http://acollington.mit-license.org/
