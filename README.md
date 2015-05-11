# opcache-gui

A clean and responsive interface for Zend OPcache information, showing statistics, settings and cached files, and providing a real-time update for the information (using jQuery and React).

[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=acollington&url=https://github.com/amnuts/opcache-gui&title=opcache-gui&language=&tags=github&category=software)

## What's new

Version 2.1.0 now provides a much easier way to configure some options, be it the poll time, toggling the ability to reset the cache, real-time updates, etc. It also allows you to show the big values (memory usage and hit rate) as gauge graphs instead of big numbers.

Version 2.0.0 introduces the use of React.js provides the ability to seamlessly update more of the information in real-time (well, every five seconds by default) - so now the files as well as the overview get refreshed. There is an updated look, removing the gradients and going for a flatter feel. And the code in general has had an overhaul.

### Overview

The overview will show you all the core information.  From here you'll be able to see what host and platform you're running on, what version of OPcache you're using, when it was last reset, the functions that are available, all the directives and all the statistics associated with the OPcache (number of hits, memory used, free and wasted memory, etc.)

![Overview](http://amnuts.com/images/opcache/screenshot/overview-v2.1.1.png)

### Getting started

There are two ways to getting started using this gui.

1. Simply to copy/paste or download the index.php to your server.
2. Install via composer by adding this to your composer.json file:
```json
{
    "require": {
        "amnuts/opcache-gui": "*"
    }
 }
```

If you want to set the configuration options just alter the array at the top of the script:
```php
$options = [
    'allow_invalidate' => true,  // give a link to invalidate files
    'allow_reset'      => true,  // give option to reset the whole cache
    'allow_realtime'   => true,  // give option to enable/disable real-time updates
    'refresh_time'     => 5,     // how often the data will refresh, in seconds
    'size_precision'   => 2,     // Digits after decimal point
    'size_space'       => false, // have '1MB' or '1 MB' when showing sizes
    'charts'           => true   // show gauge chart or just big numbers
];
```

### File usage

All the files currently in the cache are listed here with their associated statistics.  You can filter the results very easily to key in on the particular scripts you're looking for, and you can optionally set levels of the path to be hidden (handy if they all share a common root and you don't want that displayed). It will also indicate if the file cache has expired.

![File list showing filtered results](http://amnuts.com/images/opcache/screenshot/files-v2.png)

### Reset cache

You can reset the whole cache as well as force individual files to become invalidated so they will be cached again.

Both reset types can be disabled with the options `allow_reset` and `allow_invalidate`.

### Real-time updates

The interface can poll every so often to get a fresh look at the opcache.  You can change how often this happens with the option `refresh_time`.  The React javascript library is used to handle data refresh so you don't need to keep reloading the page.

## Project files

The status.jsx file is provided solely for you to be able to edit the jsx code should you wish.  For production purposes it's best to have the jsx code pre-compiled which is what's used in index.php.  You in no way need to use status.jsx to use the opcache gui.

The composer.json file is provided to allow you to deploy the opcache gui a little easier by using composer.

## Releases

Releases of the GUI are available at:

https://github.com/amnuts/opcache-gui/releases/

# License

MIT: http://acollington.mit-license.org/
