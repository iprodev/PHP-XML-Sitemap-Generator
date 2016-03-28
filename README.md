# PHP XML Sitemap Generator

This is a simple and small PHP script that I wrote quickly for myself to create a XML sitemap of my page for Google and other search engines. Maybe others can use the script too.

Sitemap format: [http://www.sitemaps.org/protocol.html](http://www.sitemaps.org/protocol.html)

##Features
 - Actually crawls webpages like Google would
 - Generates seperate XML file which gets updated every time the script gets executed (Runnable via CRON)
 - Awesome for SEO
 - Crawls faster than online services
 - Adaptable

## Usage
Usage is pretty strait forward:
 - Configure the crawler by modifying the `sitemap-generator.php` file
    - Select URL to crawl
    - Select the file to which the sitemap will be saved
    - Select accepted extensions ("/" is manditory for proper functionality)
    - Select change frequency (always, daily, weekly, monthly, never, etc...)
    - Choose priority (It is all relative so it may as well be 1)
 - Generate sitemap
    - Either send a GET request to this script or simply point your browser
    - A sitemap will be generated and displayed
    - Submit sitemap.xml to Google
    - Setup a CRON Job to send web requests to this script every so often, this will keep the sitemap.xml file up to date

The script can be started as CLI script or as Website. CLI is the prefered way to start this script.

CLI scripts are started from the command line, can be used with CRON and so on. You start it with the php program.

CLI command to create the XML file: `php sitemap-generator.php`

To start the program with your Webserver as Website change in the script the line 22 from
```php
   define ('CLI', true);
```
to 
```php
   define ('CLI', false);
```


## sitemap.xml
Add the XML file to your `/robots.txt`.

Example line for the robots.txt:

```
Sitemap: http://www.iprodev.com/sitemap.xml
```


## Credits

PHP XML Sitemap Generator was created by [Hemn Chawroka](http://iprodev.com) from [iProDev](http://iprodev.com). Released under the MIT license.

Included scripts:

 - [PHP Simple HTML DOM Parser](http://simplehtmldom.sourceforge.net/) - A HTML DOM parser written in PHP5+ let you manipulate HTML in a very easy way!.
