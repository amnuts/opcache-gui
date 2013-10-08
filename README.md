# opcache-gui

A simple, responsive interface for Zend OPcache information showing the statistics, settings and cached files.

### overview

The overview will show you all the core information.  From here you'll be able to see what host and platform you're running on, what version of OPcache you're using, when it was last reset, the functions that are available, all the directives and all the statistics associated with the OPcache (number of hits, memory used, free and wasted memory, etc.)

![Overview](http://amnuts.com/images/opcache/screenshot/overview.png)

### file usage

All the files currently in the cache are listed here with their associated statistics.  You can filter the results very easily to key in on the particular scripts you're looking for, and you can optionally set levels of the path to be hidden (handy if they all share a common root and you don't want that displayed). It will also indicate if the file cache has expired.

![File list showing filtered results](http://amnuts.com/images/opcache/screenshot/files.png)

### reset cache

There is an option to reset the whole cache and you can also optionally force individual files to become invalidated so they will be cached again.  (NB: *Apparently, some version of PHP may cause a segmentation fault when using opcache_invalidate, so there is a setting in the gui script if you want to turn off the invalidate links.*)

# License

MIT: http://acollington.mit-license.org/
