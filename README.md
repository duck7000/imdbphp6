imdbphp6
=======

PHP library for retrieving film and TV information from IMDb.<br>
Retrieve most of the information you can see on IMDb including films, TV series, TV episodes, people and coming soon releases.<br>
Search for titles on IMDb.<br>
Download film posters, actor, recommendations, foto's and episode images.<br>
The results can be localized and now also cached! Localization only seems to effect title, photo, plotoutline and recommendations (titles only). Check wiki homepage to enable.<br>
imdbphp6 is not a complete fork of imdbphp although there are things simular.<br>
There is a full list of all methods, descriptions and outputs in the wiki.
https://github.com/duck7000/imdbphp6/wiki


Quick Start
===========

* Clone this repo or download the latest [release zip]
* Find a film you want the data for e.g. A clockwork orange https://www.imdb.com/title/tt0066921/
* Include `bootstrap.php`.
* Get some data

For title search:
```php
$imdb = new \Imdb\TitleSearch();
$results = $imdb->search("1408", "MOVIE,TV");
All info is in the wiki page
```

For Advanced title search:
```php
$imdb = new \Imdb\TitleSearchAdvanced();
$results = $imdb->advancedSearch($searchTerm, $genres, $types, $creditId, $startDate, $endDate, $countryId, $languageId);
All info is in the wiki page
```

For titles:
```php
$title = new \Imdb\Title("335266");
$rating = $title->rating();
$plotOutline = $title->plotoutline();
```

For persons:
```php
$name = new \Imdb\Name("0000154");
$name = $name->name();
$nickname = $name->nickname();
```

For Calendar:
```php
$calendar = new \Imdb\Calendar();
$releases = $calendar->comingSoon();
```

Installation
============

This library uses GraphQL API from imdb to get the data.<br>
The data received from imdb GraphQL API could however be different compared to previous scraper methods.<br>
There seems to be a 250 limit on episodes per season, this may also be true for year based tv series.<br>
Thanks to @tBoothman for his groundwork to make this possible!

Get the files with one of:
* Git clone. Checkout the latest release tag
* [Zip/Tar download]

### Requirements
* PHP >= recommended 8.1 (it works from 5.6 - 8.1) Remember all versions < 8.0 are EOL!
* PHP cURL extension
