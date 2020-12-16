# opcache-gui

A clean and responsive interface for Zend OPcache information, showing statistics, settings and cached files, and providing a real-time update for the information.

This interface uses ReactJS and Axios and is for modern browsers and requires a minimum of PHP 7.1.

[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=acollington&url=https://github.com/amnuts/opcache-gui&title=opcache-gui&language=&tags=github&category=software)
If you like this software or find it helpful then maybe you'll consider supporting my efforts in some way by [signing up to Flattr and leaving a micro-donation](https://flattr.com/@acollington).

### Using the opcache-gui

There are two ways to getting started using this gui:

#### Copy/clone this repo

The easiest way to start using the opcache-gui is to clone this repo, or simply to copy/paste/download the `index.php` file, to a location which your web server can load.  Then point your browser to that location, such as `https://www.example.com/opcache/index.php`.

#### Install via composer

You can include the files with [Composer](https://getcomposer.org/) by running the command `composer require amnuts/opcache-gui`.

Once in your `vendor` directory, there are numerous ways in which you can use the interface.  For example if you're using a framework such as Symfony or Laravel, you could load opcache-gui into a `Controller`.  Your requirements of setting it up within your framework of choice will vary, so it's not really possible to detail how to do that within this readme... but I have faith in your ability to figure it out!

The namespace used for the class is `Amnuts\Opcache`, so once the dependency is in your `autoload.php` you can use the `\Amnuts\Opcache\Service` class.  For example, you could do something like:

```php
<?php

use Amnuts\Opcache\Service;

// assuming location of: /var/www/html/opcache.php
require_once __DIR__ . '/../vendor/autoload.php';

// specify any options you want different from the defaults, if any
$options = [/* ... */];

// setup the class and pass in your options, if you have any
$opcache = (new Service($options))->handle();
```

Then you can create whatever view you want with which to show the opcache details.  Although there is a pretty neat React-based interface available for you in this repo.

Alternatively, include `vendor/amnuts/opcache-gui/index.php` directly and this'll give you the same result as just copying/pasting the `index.php` somewhere.

```php
<?php

// assuming location of: /var/www/html/opcache.php

require_once __DIR__ . '/../vendor/amnuts/opcache-gui/index.php';
```

You could even simply create a symlink to the `index.php` that's in the `vendor` directory:

```shell script
ln -s /var/www/vendor/amnuts/opcache-gui/index.php /var/www/html/opcache.php
```

Basically, there are plenty of ways to get the interface up and running - pick whichever suits your needs.

### Configuration

The default configuration for the interface looks like this:

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
   'per_page'         => 200,           // How many results per page to show in the file list, false for no pagination
   'cookie_name'      => 'opcachegui',  // name of cookie
   'cookie_ttl'       => 365,           // days to store cookie
   'highlight'        => [
       'memory' => true,                // show the memory chart/big number
       'hits'   => true,                // show the hit rate chart/big number
       'keys'   => true                 // show the keys used chart/big number
   ]
];
```

If you want to change any of the defaults, you can pass in just the ones you want to change if you're happy to keep the rest as-is.  Just alter the array at the top of the `index.php` script (or pass in the array differently to the `Service` class).  For example, the following would change only the `allow_reset` and `refresh_time` values but keep everything else as the default:

```php
$opcache = (new Service([
    'refresh_time' => 2,
    'allow_reset' => false
]))->handle();
```

### Changing the look

The interface has been split up to allow you to easily change the colours of the gui, or even the core components, should you wish.

The CSS for the interface is in the `build/_frontend/interface.scss` file.  If you want to change the interface itself, update the `build/_frontend/interface.jsx` file - it's basically a set of ReactJS components.

If you update those files, you will want to build the interface again and have the new jsx/css put into use.  To do that, run the command `php ./build/build.php` from the repo root (you will need `nodejs` and `npm` installed).  Once running, you should see the output:

```
🐢 Installing node modules
🏗️ Building js and css
🚀 Creating single build file
💯 Done!
```

The build script will only need to install the `node_modules` once, so on subsequent builds it should be a fair bit quicker!

The build process will create a compiled css file at `build/interface.css` and the javascript of the interface will be in `build/interface.js`.  You could probably use both of these within your own frameworks and templating systems, should you wish.

The core PHP template used in the build process, and that acts to pass various bits of data to the ReactJS side of things, is located at `build/template.phps`.  If you wanted to update the version of ReactJS used, or how the wrapper html is structured, then this would be the file you'd want to update. 

### Overview

The overview will show you all the core information.  From here you'll be able to see what host and platform you're running on, what version of OPcache you're using, when it was last reset, the functions that are available, all the directives and all the statistics associated with the OPcache (number of hits, memory used, free and wasted memory, etc.)

![Screenshot of the Overview tab](http://amnuts.com/images/opcache/screenshot/overview-v3.0.0.png)

### Cached files

All the files currently in the cache are listed here with their associated statistics.

You can filter the results to help find the particular scripts you're looking for and change the way cached files are sorted.  From here you can invalidate the cache for individual files or invalidate the cache for all the files matching your search.

If you do not want to show the file list at all then you can use the `allow_filelist` configuration option; setting it to `false` will suppress the file list altogether.

If you want to adjust the pagination length you can do so with the `per_page` configuration option.

![Screenshot of the Cached files list showing filtered results and pagination](http://amnuts.com/images/opcache/screenshot/cached-v3.png)

### Ignored files

If you have set up a list of files which you don't want cache by supplying an `opcache.blacklist_filename` value, then the list of files will be listed within this tab.

If you have not supplied that configuration option in the `php.ini` file then this tab will not be displayed.  If you set the `allow_filelist` configuration option to `false` then this tab will not be displayed irrespective of your ini setting.

### Preloaded files

PHP 7.4 introduced the ability to pre-load a set of files on server start by way of the `opcache.preload` setting in your `php.ini` file.  If you have set that up then the list of files specifically pre-loaded will be listed within this tab.

As with the ignored file, if you have not supplied the ini setting, or the `allow_filelist` configuration option is `false`, then this tab will not be displayed.

### Reset the cache

You can reset the whole cache as well as force individual files, or groups of files, to become invalidated so that they will be cached again.

Resetting can be disabled with the use of the configuration options `allow_reset` and `allow_invalidate`.

### Real-time updates

The interface can poll every so often to get a fresh look at the opcache.  You can change how often this happens with the configuration option `refresh_time`, which is in seconds.

When the real-time updates are active the interface will automatically update all the values as needed.  Also, if you choose to invalidate any files or reset the cache it will do this without reloading the page, so the search term you've entered, or the page you've navigated to do not get reset.  If the real-time update is not on then the page will reload on any invalidation usage.

## Releases

**Version 3.2.1**\
Minor maintenance release to:
* Put back "spaceship operator" so PHP8 doesn't give deprecation warnings (must have been accidentally removed in a previous commit)
* More refined axios usage when it comes to parameters
* A little extra formatting on the opcache optimization levels

**Version 3.2.0**\
Updated ReactJS to latest and used minified versions and made slight improvement to sort option when no pagination is present.

**Version 3.1.0**\
Added the ability to sort the cached file list in a variety of ways.

**Version 3.0.1**\
A minor update that will use http or https to get the javascript libraries, depending on what you're using.

**Version 3.0.0**\
Although the interface looks mostly the same, it's had a complete re-write under the hood!  Some of the more notable changes are:
* New namespace for the base service class which ensure composer compatibility
* You can now paginate the cached files list to make it easier to render a large file list
* Any scripts that have been preloaded are displayed in a tab
* Any file paths ignored are displayed in a tab
* You can now invalidate all the files matching a search in one go
* jQuery has been removed; the whole interface is now using ReactJS and more modern javascript (so only modern browsers)
* The CSS is now using SASS and is now much easier to change all the colours of the interface as you wish
* SVGs are now used for any icons or gauge graphs
* A more responsive interface when the 'enable real-time' is activated
* Build script added to compile the ReactJS and SASS and put them into the single, simple, gui script

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

### Making is compatible with PHP 7.0

The script requires PHP 7.1 or above.  I'm not tempted to downgrade the code to make it compatible with version 7.0, and hopefully most people would have upgraded by now. But I really do appreciate that sometimes people just don't have the ability to change the version of PHP they use because it's out of their control.  So if you're one of the unlucky ones, you can make the following changes to `index.php` (or `Service.php` and run the build script).  For the lines:

```
public function getOption(?string $name = null)

public function getData(?string $section = null, ?string $property = null)

public function resetCache(?string $file = null): bool
```

It'll just be a case of removing the `?` from each of the params.

# License

MIT: http://acollington.mit-license.org/
