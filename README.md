# opcache-gui

A simple, responsive interface for Zend OPcache information showing the statistics, settings and cached files, and also provides a real-time update for the information (using jQuery and React).

[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=acollington&url=https://github.com/amnuts/opcache-gui&title=opcache-gui&language=&tags=github&category=software)

## What's new

Version 2.0.0 introduces the use of React.js provides the ability to seamlessly update more of the information in real-time (well, every five seconds by default) - so now the files as well as the overview get refreshed. There is an updated look, removing the gradients and going for a flatter feel. And the code in general has had an overhaul.

### Overview

The overview will show you all the core information.  From here you'll be able to see what host and platform you're running on, what version of OPcache you're using, when it was last reset, the functions that are available, all the directives and all the statistics associated with the OPcache (number of hits, memory used, free and wasted memory, etc.)

![Overview](http://amnuts.com/images/opcache/screenshot/overview-v2.png)

### File usage

All the files currently in the cache are listed here with their associated statistics.  You can filter the results very easily to key in on the particular scripts you're looking for, and you can optionally set levels of the path to be hidden (handy if they all share a common root and you don't want that displayed). It will also indicate if the file cache has expired.

![File list showing filtered results](http://amnuts.com/images/opcache/screenshot/files-v2.png)

### Reset cache

There is an option to reset the whole cache and you can also optionally force individual files to become invalidated so they will be cached again.  (NB: *Apparently, some version of PHP may cause a segmentation fault when using opcache_invalidate, so there is a setting in the gui script if you want to turn off the invalidate links.*)

## Project files

The status.jsx file is provided solely for you to be able to edit the jsx code should you wish.  For production purposes it's best to have the jsx code pre-compiled which is what's used in index.php.  You in no way need to use status.jsx to use the opcache gui.

The composer.json file is provided to allow you to deploy the opcache gui a little easier by using composer.

## Previous releases

Previous releases of the GUI are available at:

https://github.com/amnuts/opcache-gui/releases/

# License

MIT: http://acollington.mit-license.org/
