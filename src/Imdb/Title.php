<?php
#############################################################################
# IMDBPHP                              (c) Giorgos Giagas & Itzchak Rehberg #
# written by Giorgos Giagas                                                 #
# extended & maintained by Itzchak Rehberg <izzysoft AT qumran DOT org>     #
# http://www.izzysoft.de/                                                   #
# ------------------------------------------------------------------------- #
# This program is free software; you can redistribute and/or modify it      #
# under the terms of the GNU General Public License (see doc/LICENSE)       #
#############################################################################

namespace Imdb;

/**
 * A title on IMDb
 * @author Georgos Giagas
 * @author Izzy (izzysoft AT qumran DOT org)
 * @copyright (c) 2002-2004 by Giorgos Giagas and (c) 2004-2009 by Itzchak Rehberg and IzzySoft
 */
class Title extends MdbBase
{

    const MOVIE = 'Movie';
    const TV_SERIES = 'TV Series';
    const TV_EPISODE = 'TV Episode';
    const TV_MINI_SERIES = 'TV Mini Series';
    const TV_MOVIE = 'TV Movie';
    const TV_SPECIAL = 'TV Special';
    const TV_SHORT = 'TV Short';
    const GAME = 'Video Game';
    const VIDEO = 'Video';
    const SHORT = 'Short';

    protected $akas = array();
    protected $countries = array();
    protected $credits_cast = array();
    protected $credits_composer = array();
    protected $credits_director = array();
    protected $credits_producer = array();
    protected $credits_writing = array();
    protected $langs = array();
    protected $all_keywords = array();
    protected $main_poster = "";
    protected $main_poster_thumb = "";
    protected $main_plotoutline = "";
    protected $main_movietype = "";
    protected $main_title = "";
    protected $main_year = -1;
    protected $main_endyear = -1;
    protected $main_top250 = -1;
    protected $main_rating = -1;
    protected $moviegenres = array();
    protected $moviequotes = array();
    protected $movierecommendations = array();
    protected $movieruntimes = array();
    protected $mpaas = array();
    protected $plot = array();
    protected $seasoncount = -1;
    protected $season_episodes = array();
    protected $soundtracks = array();
    protected $taglines = array();
    protected $trivia = array();
    protected $locations = array();
    protected $moviealternateversions = array();
    protected $jsonLD = null;

    protected $pageUrls = array(
        "AlternateVersions" => '/alternateversions',
        "Credits" => "/fullcredits",
        "Episodes" => "/episodes",
        "Keywords" => "/keywords",
        "Locations" => "/locations",
        "ParentalGuide" => "/parentalguide",
        "Plot" => "/plotsummary",
        "Quotes" => "/quotes",
        "ReleaseInfo" => "/releaseinfo",
        "Soundtrack" => "/soundtrack",
        "Taglines" => "/taglines",
        "Technical" => "/technical",
        "Title" => "/",
        "Trivia" => "/trivia",
    );

    /**
     * Create an imdb object populated with id, title, year, and movie type
     * @param string $id imdb ID
     * @param string $title film title
     * @param int $year
     * @param string $type
     * @param Config $config
     * @return Title
     */
    public static function fromSearchResult(
        $id,
        $title,
        $year,
        $type,
        Config $config = null
    ) {
        $imdb = new Title($id, $config);
        $imdb->main_title = $title;
        $imdb->main_year = (int)$year;
        $imdb->main_movietype = $type;
        return $imdb;
    }

    /**
     * @param string $id IMDb ID. e.g. 285331 for https://www.imdb.com/title/tt0285331/
     * @param Config $config OPTIONAL override default config
     */
    public function __construct(
        $id,
        Config $config = null
    ) {
        parent::__construct($config);
        $this->setid($id);
    }

    #-------------------------------------------------------------[ Open Page ]---

    protected function buildUrl($page = null)
    {
        return "https://" . $this->imdbsite . "/title/tt" . $this->imdbID . $this->getUrlSuffix($page);
    }

    /**
     * @param string $pageName internal name of the page
     * @return string
     */
    protected function getUrlSuffix($pageName)
    {
        if (isset($this->pageUrls[$pageName])) {
            return $this->pageUrls[$pageName];
        }

        if (preg_match('!^Episodes-(-?\d+)$!', $pageName, $match)) {
            if (strlen($match[1]) == 4) {
                return '/episodes?year=' . $match[1];
            } else {
                return '/episodes?season=' . $match[1];
            }
        }

        throw new \Exception("Could not find URL for page $pageName");
    }

    /**
     * Get the URL for this title's page
     * @return string
     */
    public function main_url()
    {
        return "https://" . $this->imdbsite . "/title/tt" . $this->imdbid() . "/";
    }

    /**
     * Setup title and year properties
     */
    protected function title_year()
    {
        $xpath = $this->getXpathPage("Title");
        if (empty($xpath)) {
            return array();  // no such page
        }
        if ($cells = $xpath->query("//title")) {
            $title = explode("(", $cells->item(0)->nodeValue);
            if (!empty($title[0])) {
                $this->main_title = htmlspecialchars_decode(trim($title[0]), ENT_QUOTES);
            }
            if (isset($title[1]) && !empty($title[1])) {
                $typeYear = explode(")", $title[1]);
                $posFirstDigit = strcspn($typeYear[0], '0123456789');
                // Movietype available, year or year span possible
                if ($posFirstDigit > 2) {
                    $this->main_movietype = trim(substr($typeYear[0], 0, $posFirstDigit));
                    $yearRaw = trim(substr($typeYear[0], $posFirstDigit));
                    // Year only
                    if (strpos($yearRaw, "–") === false) { // Not a normal dash
                        $this->main_year = trim($yearRaw);
                        $this->main_endyear = '0';
                    } else {
                        // year span
                        $yearSpan = explode("–", $yearRaw); // Not a normal dash
                        $this->main_year = trim($yearSpan[0]);
                        $this->main_endyear = trim($yearSpan[1]);
                    }
                } else {
                    // No movietype, year or year span possible
                    $yearRaw = trim(substr($typeYear[0], $posFirstDigit));
                    // Year only
                    if (strpos($yearRaw, "–") === false) { // Not a normal dash
                        $this->main_year = trim($yearRaw);
                        $this->main_endyear = '0';
                    } else {
                        //year span
                        $yearSpan = explode("–", $yearRaw); // Not a normal dash
                        $this->main_year = trim($yearSpan[0]);
                        $this->main_endyear = trim($yearSpan[1]);
                    }
                }
                if ($this->main_year == "????") {
                $this->main_year = "";
            }
            }
        }
    }

    /** Get movie type
     * @return string movietype (TV Series, Movie, TV Episode, TV Special, TV Movie, TV Mini-Series, Video Game, TV Short, Video)
     * @see IMDB page / (TitlePage)
     * If no movietype has been defined explicitly, it returns 'Movie' -- so this is always set.
     */
    public function movietype()
    {
        if (empty($this->main_movietype)) {
            if (empty($this->main_title)) {
                $this->title_year();
            } // Most types are shown in the <title> tag
            if (!empty($this->main_movietype)) {
                return $this->main_movietype;
            }
            if (empty($this->main_movietype)) {
                $this->main_movietype = 'Movie';
            }
        }
        return $this->main_movietype;
    }

    /** Get movie title
     * @return string title movie title (name)
     * @see IMDB page / (TitlePage)
     */
    public function title()
    {
        if ($this->main_title == "") {
            $this->title_year();
        }
        return $this->main_title;
    }

    /** Get year
     * @return string year
     * @see IMDB page / (TitlePage)
     */
    public function year()
    {
        if ($this->main_year == -1) {
            $this->title_year();
        }
        return $this->main_year;
    }

    /** Get end-year
     * if production spanned multiple years, usually for series
     * @return string year,  '' stil running tv series,  '0' if no end-year (Movies)
     * @see IMDB page / (TitlePage)
     */
    public function endyear()
    {
        if ($this->main_endyear == -1) {
            $this->title_year();
        }
        return $this->main_endyear;
    }

    #---------------------------------------------------------------[ Runtime ]---
    /**
     * Retrieve all runtimes and their descriptions
     * @return array runtimes (array[0..n] of array[time,annotations]) where annotations is an array of comments meant to describe this cut
     * @see IMDB page / (TitlePage)
     */
    public function runtimes()
    {
        if (empty($this->movieruntimes)) {
            $xpath = $this->getXpathPage("Technical");
            if (empty($xpath)) {
                return array();
            }
            if ($runtimesRaw = $xpath->query("//td[normalize-space(text())='Runtime']/following-sibling::td[1]")) {
                $runtimesHtml = $runtimesRaw->item(0)->ownerDocument->saveHTML($runtimesRaw->item(0));
                $runtimes = explode("<br>", $runtimesHtml);
                foreach ($runtimes as $runtime) {
                    if ($runtime != "") {
                        $pos = strpos($runtime, '(');
                        $annotations = array();
                        if ($pos !== false) {
                            $timeTemp = explode("(", substr($runtime, $pos + 1));
                            $time = intval(preg_replace('/[^0-9]/', '', $timeTemp[0]));
                            if (isset($timeTemp[1]) && !empty($timeTemp[1])) {
                                $desc = '(' . trim(strip_tags($timeTemp[1]));
                            } else {
                                $desc = '';
                            }
                        } else {
                            $time = intval(preg_replace('/[^0-9]/', '', $runtime));
                            $desc = '';
                        }
                        $annotations[] = $desc;
                        $this->movieruntimes[] = array(
                            "time" => $time,
                            "annotations" => $annotations
                        );
                    }
                }
            }
            return $this->movieruntimes;
        }
    }

    #----------------------------------------------------------[ Movie Rating ]---
    /**
     * Get movie rating
     * @return float rating current rating as given by IMDB site
     * @see IMDB page / (TitlePage)
     */
    public function rating()
    {
        if ($this->main_rating == -1) {
            $xpath = $this->getXpathPage("Title");
            $ratingRaw = $xpath->query("//div[@data-testid='hero-rating-bar__aggregate-rating__score']//span[1]");
            if (!empty($ratingRaw->item(0)->nodeValue)) {
                $this->main_rating = floatval($ratingRaw->item(0)->nodeValue);
            } else {
                $this->main_rating = 0;
            }
        }
        return $this->main_rating;
    }

    /**
     * Rating out of 100 on metacritic
     * @return int|null
     */
    public function metacriticRating()
    {
        $xpath = $this->getXpathPage("Title");
        $extract = $xpath->query("//span[@class='score-meta']");
        if ($extract && $extract->item(0) != null) {
            return intval(trim($extract->item(0)->nodeValue));
        }
        return null;
    }

    #-------------------------------------------------------[ Recommendations ]---
    /**
     * Get recommended movies (People who liked this...also liked)
     * @return array recommendations (array[title,imdbid,rating,img])
     * @see IMDB page / (TitlePage)
     */
    public function recommendations()
    {
        if (empty($this->movierecommendations)) {
            $xp = $this->getXpathPage("Title");
            $cells = $xp->query("//div[contains(@class, 'ipc-poster-card ipc-poster-card--base')]");
            /** @var \DOMElement $cell */
            foreach ($cells as $key => $cell) {
                $movie = array();
                $get_link_and_name = $xp->query(".//a[contains(@class, 'ipc-poster-card__title')]", $cell);
                if (!empty($get_link_and_name) && preg_match('!tt(\d+)!',
                        $get_link_and_name->item(0)->getAttribute('href'), $ref)) {
                    $movie['title'] = trim($get_link_and_name->item(0)->nodeValue);
                    $movie['imdbid'] = $ref[1];
                    $get_rating = $xp->query(".//span[contains(@class, 'ipc-rating-star--imdb')]", $cell);
                    if (!empty($get_rating->item(0))) {
                        $movie['rating'] = trim($get_rating->item(0)->nodeValue);
                    } else {
                        $movie['rating'] = -1;
                    }
                    $getImage = $xp->query(".//div[contains(@class, 'ipc-media ipc-media--poster')]//img", $cell);
                    if (!empty($getImage->item(0)) && !empty($getImage->item(0)->getAttribute('src'))) {
                        $movie['img'] = $getImage->item(0)->getAttribute('src');
                    } else {
                        $movie['img'] = "";
                    }
                    $this->movierecommendations[] = $movie;
                }
            }
        }
        return $this->movierecommendations;
    }

    #--------------------------------------------------------[ Language Stuff ]---
    /** Get all languages this movie is available in
     * @return array languages (array[0..n] of strings)
     * @see IMDB page / (TitlePage)
     */
    public function languages()
    {
        if (empty($this->langs)) {
            $xpath = $this->getXpathPage("Title");
            if (empty($xpath)) {
                return array();
            }
            if ($languages = $xpath->query("//li[@data-testid=\"title-details-languages\"]//a")) {
                foreach ($languages as $language) {
                    if ($language->nodeValue != "") {
                        $this->langs[] = trim($language->nodeValue);
                    }
                }
            }
            return $this->langs;
        }
    }

    #--------------------------------------------------------------[ Genre(s) ]---
    /** Get all genres the movie is registered for
     * @return array genres (array[0..n] of strings)
     * @see IMDB page / (TitlePage)
     */
    public function genres()
    {
        if (empty($this->moviegenres)) {
            $genres = isset($this->jsonLD()->genre) ? $this->jsonLD()->genre : array();
            if (!is_array($genres)) {
                $genres = (array)$genres;
            }
            $this->moviegenres = $genres;
        }
        return $this->moviegenres;
    }

    #---------------------------------------------------------------[ Creator ]---
    /**
     * Get the creator(s) of a TV Show
     * @return array creator (array[0..n] of array[name,imdb])
     * @see IMDB page / (TitlePage)
     */
    public function creator()
    {
        $result = array();
        if ($this->jsonLD()->{'@type'} === 'TVSeries' && isset($this->jsonLD()->creator) && is_array($this->jsonLD()->creator)) {
            foreach ($this->jsonLD()->creator as $creator) {
                if ($creator->{'@type'} === 'Person') {
                    $result[] = array(
                        'name' => $creator->name,
                        'imdb' => rtrim(str_replace('/name/nm', '', $creator->url), '/')
                    );
                }
            }
        }
        return $result;
    }

    #---------------------------------------------------------------[ Seasons ]---
    /** Get the number of seasons or 0 if not a series
     * @return int seasons number of seasons
     * @see IMDB page / (TitlePage)
     */
    public function seasons()
    {
        if ($this->seasoncount == -1) {
            $xpath = $this->getXpathPage("Title");
            $dom_xpath_result = $xpath->query('//select[@id="browse-episodes-season"]//option');
            $this->seasoncount = 0;
            foreach ($dom_xpath_result as $xnode) {
                if (!empty($xnode->getAttribute('value')) && intval($xnode->getAttribute('value')) > $this->seasoncount) {
                    $this->seasoncount = intval($xnode->getAttribute('value'));
                }
            }

            if ($this->seasoncount === 0) {
                // Single season shows have a link rather than a select box
                $singleSeason = $xpath->query('//div[@data-testid="episodes-browse-episodes"]//a');
                if (!empty($singleSeason->item(0))) {
                    $href = $singleSeason->item(0)->getAttribute('href');
                    if (stripos($href, "?season=1") !== false) {
                        $this->seasoncount = 1;
                    }
                }
            }
        }
        return $this->seasoncount;
    }

    #--------------------------------------------------------[ Plot (Outline) ]---
    /** Get the main Plot outline for the movie
     * @param boolean $fallback Fallback to storyline if we could not catch plotoutline
     * @return string plotoutline
     * @see IMDB page / (TitlePage)
     */
    public function plotoutline()
    {
        if ($this->main_plotoutline == "") {
            if (isset($this->jsonLD()->description)) {
                $this->main_plotoutline = htmlspecialchars_decode($this->jsonLD()->description, ENT_QUOTES | ENT_HTML5);
            } else {
                $page = $this->getPage("Title");
                if (preg_match('!class="summary_text">\s*(.*?)\s*</div>!ims', $page, $match)) {
                    $this->main_plotoutline = trim($match[1]);
                }
            }

        }
        $this->main_plotoutline = preg_replace('!\s*<a href="/title/tt\d{7,8}/(plotsummary|synopsis)[^>]*>See full (summary|synopsis).*$!i',
            '', $this->main_plotoutline);
        $this->main_plotoutline = preg_replace('#<a href="[^"]+"\s+>Add a Plot</a>&nbsp;&raquo;#', '',
            $this->main_plotoutline);
        return $this->main_plotoutline;
    }

    #--------------------------------------------------------[ Photo specific ]---
    /**
     * Setup cover photo (thumbnail and big variant)
     * @see IMDB page / (TitlePage)
     */
    private function populatePoster()
    {
        if (isset($this->jsonLD()->image)) {
            $this->main_poster = $this->jsonLD()->image;
        }
        if (preg_match('!<img [^>]+title="[^"]+Poster"[^>]+src="([^"]+)"[^>]+/>!ims', $this->getPage("Title"), $match)
            && !empty($match[1])) {
            $this->main_poster_thumb = $match[1];
        } else {
            $xpath = $this->getXpathPage("Title");
            $thumb = $xpath->query("//div[contains(@class, 'ipc-poster ipc-poster--baseAlt') and contains(@data-testid, 'hero-media__poster')]//img");
            if (!empty($thumb) && $thumb->item(0) != null) {
                $this->main_poster_thumb = $thumb->item(0)->getAttribute('src');
            }
        }
    }


    /**
     * Get the poster/cover image URL
     * @param boolean $thumb get the thumbnail (182x268) or the full sized image
     * @return string|false photo (string URL if found, FALSE otherwise)
     * @see IMDB page / (TitlePage)
     */
    public function photo($thumb = true)
    {
        if (empty($this->main_poster)) {
            $this->populatePoster();
        }
        if (!$thumb && empty($this->main_poster)) {
            return false;
        }
        if ($thumb && empty($this->main_poster_thumb)) {
            return false;
        }
        if ($thumb) {
            return $this->main_poster_thumb;
        }
        return $this->main_poster;
    }

    /**
     * Save the poster/cover image to disk
     * @param string $path where to store the file
     * @param boolean $thumb get the thumbnail (100x140, default) or the
     *        bigger variant (400x600 - FALSE)
     * @return boolean success
     * @see IMDB page / (TitlePage)
     */
    public function savephoto($path, $thumb = true)
    {
        $photo_url = $this->photo($thumb);
        if (!$photo_url) {
            return false;
        }

        $req = new Request($photo_url, $this->config);
        $req->sendRequest();
        if (strpos($req->getResponseHeader("Content-Type"), 'image/jpeg') === 0 ||
            strpos($req->getResponseHeader("Content-Type"), 'image/gif') === 0 ||
            strpos($req->getResponseHeader("Content-Type"), 'image/bmp') === 0) {
            $image = $req->getResponseBody();
        } else {
            $ctype = $req->getResponseHeader("Content-Type");
            $this->debug_scalar("*photoerror* at " . __FILE__ . " line " . __LINE__ . ": " . $photo_url . ": Content Type is '$ctype'");
            if (substr($ctype, 0, 4) == 'text') {
                $this->debug_scalar("Details: <PRE>" . $req->getResponseBody() . "</PRE>\n");
            }
            return false;
        }

        $fp2 = fopen($path, "w");
        if (!$fp2) {
            return false;
        }
        fputs($fp2, $image);
        return true;
    }

    #-------------------------------------------------[ Country of Production ]---
    /**
     * Get country of production
     * @return array country (array[0..n] of string)
     * @see IMDB page / (TitlePage)
     */
    public function country()
    {
        if (empty($this->countries)) {
            $xpath = $this->getXpathPage("Title");
            if (empty($xpath)) {
                return array();
            }
            if ($countrys = $xpath->query("//li[@data-testid=\"title-details-origin\"]//a")) {
                foreach ($countrys as $country) {
                    if ($country->nodeValue != "") {
                        $this->countries[] = trim($country->nodeValue);
                    }
                }
            }
        }
        return $this->countries;
    }

    #------------------------------------------------------------[ Movie AKAs ]---
    /**
     * Get movie's alternative names
     * @return array array[0..n] of array[title,country]
     * @see IMDB page ReleaseInfo
     */
    public function alsoknow()
    {
        if (empty($this->akas)) {
            $xpath = $this->getXpathPage("ReleaseInfo");
            if (empty($xpath)) {
                return array(); // no such page
            }
            $akaTableRows = $xpath->query("//*[@id=\"akas\"]/following-sibling::table/tr");

            if (empty($akaTableRows)) {
                return array(); // no data available
            }
            foreach ($akaTableRows as $row) {
                $akaTds = $row->getElementsByTagName('td');
                $title = trim($akaTds->item(1)->nodeValue);
                $description = trim($akaTds->item(0)->nodeValue);
                if (stripos($description, 'original title') !== false) {
                    $country = $description;
                } else {
                    $countryRaw = explode("(", $description);
                    $country = trim($countryRaw[0]);
                }
                $this->akas[] = array(
                    "country" => $country,
                    "title" => $title
                );
            }
        }
        return $this->akas;
    }

    #-------------------------------------------------------[ MPAA / PG / FSK ]---
    /**
     * Get the MPAA rating / Parental Guidance / Age rating for this title by country
     * @param bool $ratings On false it will return the last rating for each country,
     *                      otherwise return every rating in an array.
     * @return array [country => rating] or [country => [rating,]]
     * @see IMDB Parental Guidance page / (parentalguide)
     */
    public function mpaa($ratings = false)
    {
        if (empty($this->mpaas)) {
            $xpath = $this->getXpathPage("ParentalGuide");
            if (empty($xpath)) {
                return array();
            }
            $cells = $xpath->query("//section[@id=\"certificates\"]//li[@class=\"ipl-inline-list__item\"]");
            foreach ($cells as $cell) {
                if ($a = $cell->getElementsByTagName('a')->item(0)) {
                    $mpaa = explode(':', $a->nodeValue, 2);
                    $country = trim($mpaa[0]);
                    $rating = isset($mpaa[1]) ? $mpaa[1] : '';

                    if ($ratings) {
                        if (!isset($this->mpaas[$country])) {
                            $this->mpaas[$country] = [];
                        }

                        $this->mpaas[$country][] = $rating;
                    } else {
                        $this->mpaas[$country] = $rating;
                    }
                }
            }
        }
        return $this->mpaas;
    }

    #----------------------------------------------[ Position in the "Top250" ]---
    /**
     * Find the position of a movie or tv show in the top 250 ranked movies or tv shows
     * @return int position a number between 1..250 if ranked, 0 otherwise
     * @author abe
     * @see http://projects.izzysoft.de/trac/imdbphp/ticket/117
     */
    public function top250()
    {
        if ($this->main_top250 == -1) {
            $xpath = $this->getXpathPage("Title");
            $topRated = $xpath->query("//a[@data-testid='award_top-rated']")->item(0);
            if ($topRated && preg_match('/#(\d+)/', $topRated->nodeValue, $match)) {
                $this->main_top250 = (int)$match[1];
            } else {
                $this->main_top250 = 0;
            }
        }
        return $this->main_top250;
    }

    #=====================================================[ /plotsummary page ]===
    /** Get the movie plot(s) - split-up variant
     * @return array array[0..n] of array[string plot,string author]
     * @see IMDB page /plotsummary
     */
    public function plot()
    {
        if (empty($this->plot)) {
            $xpath = $this->getXpathPage("Plot");
            if (empty($xpath)) {
                return array();
            } // no such page
            if ($cells = $xpath->query("//ul[@id=\"plot-summaries-content\"]/li[@id!=\"no-summary-content\"]")) {
                foreach ($cells as $key => $cell) {
                    if ($key >= 1) { //skip first element, this is often used as plotoutline
                        $author = '';
                        $xml = $cell->ownerDocument->saveXML($cell);
                        $t = explode("—", $xml); //this is not a normal dash!
                        if (count($t) > 1) {
                            // author available, get only author name
                            $authorRaw = explode("@", strip_tags($t[1]));
                            $authorArray = array_values(array_filter(explode("&lt;", $authorRaw[0])));
                            $authorStripped = explode(",", $authorArray[0]);
                            $author = trim($authorStripped[0]);
                        }
                        // plot
                        if ($cell->getElementsByTagName('p')->item(0)) {
                            $plotRaw = $cell->getElementsByTagName('p')->item(0)->nodeValue;
                            $plot = trim(strip_tags($plotRaw));
                        }
                        $this->plot[] = array("plot" => $plot, "author" => $author);
                    } else {
                    if (count($cells) == 1)
                        $this->plot[] = array("plot" => '', "author" => '');
                    }
                }
            }
        }
        return $this->plot;
    }

    #========================================================[ /taglines page ]===
    /**
     * Get all available taglines for the movie
     * @return array taglines (array[0..n] of strings)
     * @see IMDB page /taglines
     */
    public function taglines()
    {
        if (empty($this->taglines)) {
            $xpath = $this->getXpathPage("Taglines");
            if (empty($xpath)) {
                return array(); // no such page
            }
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array(); // no data available
            }
            if ($taglinesContent = $xpath->query("//div[@class=\"soda odd\" or @class=\"soda even\"]")) {
                foreach ($taglinesContent as $tagline) {
                    if ($tagline->nodeValue != "") {
                        $this->taglines[] = trim($tagline->nodeValue);
                    }
                }
            }
        }
        return $this->taglines;
    }

    #=====================================================[ /fullcredits page ]===
    
    #-------------------------------------------[ Helper: Get IMDBID from URL ]---
    /** Get the IMDB ID from a names URL
     * @param string href url to the staff members IMDB page
     * @return string IMDBID of the staff member
     * @see used by the methods director, cast, writing, producer, composer
     */
    protected function get_imdbname($href)
    {
        return preg_replace('!^.*nm(\d+).*$!ims', '$1', $href);
    }
    
    #-----------------------------------------------------[ Helper: TableRows ]---
    /**
     * Get rows for a given table on the page
     * @param string html
     * @param string table_start
     * @return string[] Contents of each row of the table
     * @see used by the methods director, writing, producer, composer
     */
    protected function get_table_rows($id)
    {
        $xpath = $this->getXpathPage("Credits");
        if (empty($xpath)) {
            return array();
        } // no such page
        if ($cells = $xpath->query("//h4[@id='$id']/following-sibling::table[1]/tbody/tr")) {
            return $cells;
        
        }
    }

    #------------------------------------------------------[ Helper: RowCells ]---
    /** Get content of table row cells
     * @param string row (as returned by imdb::get_table_rows)
     * @return array cells (array[0..n] of strings)
     * @see used by the methods director, writing, producer, composer
     */
    protected function get_row_cels($row)
    {
        if ($rowTds = $row->getElementsByTagName('td')) {
            return $rowTds;
        }
        return array();
    }

    #-------------------------------------------------------------[ Directors ]---
    /**
     * Get the director(s) of the movie
     * @return array director (array[0..n] of arrays[imdb,name,role])
     * @see IMDB page /fullcredits
     */
    public function director()
    {
        if (!empty($this->credits_director)) {
            return $this->credits_director;
        }
        $directorRows = $this->get_table_rows("director");
        foreach ($directorRows as $directorRow) {
            $directorTds = $this->get_row_cels($directorRow);
            $imdb = '';
            $name = '';
            $role = null;
            if (!empty(preg_replace('/[\s]+/mu', '', $directorTds->item(0)->nodeValue))) {
                if ($directorTds->item(2)) {
                    $role = trim(strip_tags($directorTds->item(2)->nodeValue));
                }
                if ($anchor = $directorTds->item(0)->getElementsByTagName('a')->item(0)) {
                    $imdb = $this->get_imdbname($anchor->getAttribute('href'));
                    $name = trim(strip_tags($anchor->nodeValue));
                } elseif (!empty($directorTds->item(0)->nodeValue)) {
                        $name = trim($directorTds->item(0)->nodeValue);
                }
                $this->credits_director[] = array(
                    'imdb' => $imdb,
                    'name' => $name,
                    'role' => $role
                );
            }
        }
        return $this->credits_director;
    }

    #----------------------------------------------------------------[ Actors ]---
    /*
    * Get the Stars members for this title
    * @return empty array OR array Stars (array[0..n] of array[imdb,name])
     * e.g.
     * <pre>
     * array (
     *  'imdb' => '0000134',
     *  'name' => 'Robert De Niro', // Actor's name on imdb
     * )
     * </pre>
    */
    public function actor_stars()
    {
        $stars = array();
        if (empty($this->jsonLD()->actor)) {
            return $stars;
        }
        $actors = $this->jsonLD()->actor;
        if (!is_array($this->jsonLD()->actor)) {
            $actors = array($this->jsonLD()->actor);
        }
        foreach ($actors as $actor) {
            $act = array(
                'imdb' => preg_replace('!.*?/name/nm(\d+)/.*!', '$1', $actor->url),
                'name' => $actor->name,
            );
            $stars[] = $act;
        }
        return $stars;
    }

    /**
     * Get the actors/cast members for this title
     * @return array cast (array[0..n] of array[imdb,name,role,thumb])
     * e.g.
     * <pre>
     * array (
     *  'imdb' => '0922035',
     *  'name' => 'Dominic West', // Actor's name on imdb
     *  'role' => "Det. James 'Jimmy' McNulty" including all comments in brackets,
     *  'thumb' => 'https://ia.media-imdb.com/images/M/MV5BMTY5NjQwNDY2OV5BMl5BanBnXkFtZTcwMjI2ODQ1MQ@@._V1_SY44_CR0,0,32,44_AL_.jpg',
     * )
     * </pre>
     * @see IMDB page /fullcredits
     */
    public function cast()
    {
        if (!empty($this->credits_cast)) {
            return $this->credits_cast;
        }
        $xpath = $this->getXpathPage("Credits");
        if (empty($xpath)) {
            return array(); // no such page
        }
        if ($castRows = $xpath->query("//table[@class='cast_list']/tr[@class=\"odd\" or @class=\"even\"]")) {
            foreach ($castRows as $castRow) {
                $castTds = $castRow->getElementsByTagName('td');
                if (4 !== count($castTds)) {
                    continue;
                }
                $dir = array(
                    'imdb' => null,
                    'name' => null,
                    'role' => null,
                    'thumb' => null
                );
                //Actor name and imdbId
                if ($actorAnchor = $castTds->item(1)->getElementsByTagName('a')->item(0)) {
                    $actorHref = $actorAnchor->getAttribute('href');
                    $dir["imdb"] = preg_replace('!.*/name/nm(\d+)/.*!ims', '$1', $actorHref);
                    $dir["name"] = trim($actorAnchor->nodeValue);
                } else {
                    if (!empty(trim($castTds->item(1)->nodeValue))) {
                       $dir["name"] = trim($castTds->item(1)->nodeValue);
                    } else {
                        continue;
                    }
                }
                // actor thumb image
                if ($imgUrl = $castTds->item(0)->getElementsByTagName('img')->item(0)->getAttribute('loadlate')) {
                    $dir["thumb"] = $imgUrl;
                } else {
                    $dir["thumb"] = '';
                }
                //Role including all comments in brackets
                if ($roleCell = $castTds->item(3)->nodeValue) {
                    $roleLines = explode("\n", $roleCell);
                    $role = '';
                    foreach ($roleLines as $key => $roleLine) {
                        //get rid of not needed episode info
                        if (strpos($roleLine, 'episode') !== false || strpos($roleLine, '/ ...') !== false || empty($roleLine)) {
                            continue;
                        } else {
                            $role .=  trim(preg_replace('#[\xC2\xA0]#', '', $roleLine)) . ' ';
                        }
                    }
                }
                $dir['role'] = trim($role);
                $this->credits_cast[] = $dir;
            }
        }
        return $this->credits_cast;
    }

    #---------------------------------------------------------------[ Writers ]---
    /** Get the writer(s)
     * @return array writers (array[0..n] of arrays[imdb,name,role])
     * @see IMDB page /fullcredits
     */
    public function writer()
    {
        if (!empty($this->credits_writer)) {
            return $this->credits_writer;
        }
        $writerRows = $this->get_table_rows("writer");
        foreach ($writerRows as $writerRow) {
            $writerTds = $this->get_row_cels($writerRow);
            $imdb = '';
            $name = '';
            $role = null;
            if (!empty(preg_replace('/[\s]+/mu', '', $writerTds->item(0)->nodeValue))) {
                if ($writerTds->item(2)) {
                    $role = trim(strip_tags($writerTds->item(2)->nodeValue));
                }
                if ($anchor = $writerTds->item(0)->getElementsByTagName('a')->item(0)) {
                    $imdb = $this->get_imdbname($anchor->getAttribute('href'));
                    $name = trim(strip_tags($anchor->nodeValue));
                } elseif (!empty($writerTds->item(0)->nodeValue)) {
                        $name = trim($writerTds->item(0)->nodeValue);
                }
                $this->credits_writer[] = array(
                    'imdb' => $imdb,
                    'name' => $name,
                    'role' => $role
                );
            }
        }
        return $this->credits_writer;
    }

    #---------------------------------------------------------------[ Producers ]---
    /** Get the producers(s)
     * @return array producers (array[0..n] of arrays[imdb,name,role])
     * @see IMDB page /fullcredits
     */
    public function producer()
    {
        if (!empty($this->credits_producer)) {
            return $this->credits_producer;
        }
        $producerRows = $this->get_table_rows("producer");
        foreach ($producerRows as $producerRow) {
            $producerTds = $this->get_row_cels($producerRow);
            $imdb = '';
            $name = '';
            $role = null;
            if (!empty(preg_replace('/[\s]+/mu', '', $producerTds->item(0)->nodeValue))) {
                if ($producerTds->item(2)) {
                    $role = trim(strip_tags($producerTds->item(2)->nodeValue));
                }
                if ($anchor = $producerTds->item(0)->getElementsByTagName('a')->item(0)) {
                    $imdb = $this->get_imdbname($anchor->getAttribute('href'));
                    $name = trim(strip_tags($anchor->nodeValue));
                } elseif (!empty($producerTds->item(0)->nodeValue)) {
                        $name = trim($producerTds->item(0)->nodeValue);
                }
                $this->credits_producer[] = array(
                    'imdb' => $imdb,
                    'name' => $name,
                    'role' => $role
                );
            }
        }
        return $this->credits_producer;
    }

    #-------------------------------------------------------------[ Composers ]---
    /** Obtain the composer(s) ("Original Music by...")
     * @return array composer (array[0..n] of arrays[imdb,name,role])
     * @see IMDB page /fullcredits
     */
    public function composer()
    {
        if (!empty($this->credits_composer)) {
            return $this->credits_composer;
        }
        $composerRows = $this->get_table_rows("composer");
        foreach ($composerRows as $composerRow) {
            $composerTds = $this->get_row_cels($composerRow);
            $imdb = '';
            $name = '';
            $role = null;
            if (!empty(preg_replace('/[\s]+/mu', '', $composerTds->item(0)->nodeValue))) {
                if ($composerTds->item(2)) {
                    $role = trim(strip_tags($composerTds->item(2)->nodeValue));
                }
                if ($anchor = $composerTds->item(0)->getElementsByTagName('a')->item(0)) {
                    $imdb = $this->get_imdbname($anchor->getAttribute('href'));
                    $name = trim(strip_tags($anchor->nodeValue));
                } elseif (!empty($composerTds->item(0)->nodeValue)) {
                        $name = trim($composerTds->item(0)->nodeValue);
                }
                $this->credits_composer[] = array(
                    'imdb' => $imdb,
                    'name' => $name,
                    'role' => $role
                );
            }
        }
        return $this->credits_composer;
    }

    #========================================================[ /episodes page ]===
    #--------------------------------------------------------[ Episodes Array ]---
    /**
     * Get the series episode(s)
     * @return array episodes (array[0..n] of array[0..m] of array[imdbid,title,airdate,plot,season,episode,image_url])
     * @see IMDB page /episodes
     * @version Attention: Starting with revision 506 (version 2.1.3), the outer array no longer starts at 0 but reflects the real season number!
     */
    public function episodes()
    {
        if (empty($this->season_episodes)) {
            $xpath = $this->getXpathPage("Episodes");
            if (empty($xpath)) {
                return $this->season_episodes; // no such page
            }
            /*
             * There are (sometimes) two select boxes: one per season and one per year.
             * IMDb picks one select to use by default and the other starts with an empty option.
             * The one which starts with a numeric option is the one we need to loop over sometimes the other doesn't work
             * (e.g. a show without seasons might have 100s of episodes in season 1 and its page won't load)
             *
             * default to year based
             */
             $selectId = "byYear";
             if ($bySeason = $xpath->query("//select[@id='bySeason']//option")) {
                if (is_numeric(trim($bySeason->item(0)->nodeValue))) {
                    $selectId = "bySeason";
                }
             }
             if ($select = $xpath->query("//select[@id='" . $selectId . "']//option")) {
                $total = count($select);
                for ($i = 0; $i < $total; ++$i) {
                    $value = $select->item($i)->getAttribute('value');
                    $s = (int) $value;
                    $xpathEpisodes = $this->getXpathPage("Episodes-$s");
                    if (empty($xpathEpisodes)) {
                        return $this->season_episodes; // no episode page
                    }
                    $cells = $xpathEpisodes->query("//div[@class=\"list_item odd\" or @class=\"list_item even\"]");
                    foreach ($cells as $cell) {
                        //image
                        $imgUrl = '';
                        if ($cell->getElementsByTagName('img')->item(0)) {
                            $imgUrl = $cell->getElementsByTagName('img')->item(0)->getAttribute('src');
                        }
                        // ImdbId and Title
                        $imdbId = '';
                        $title = '';
                        if ($cell->getElementsByTagName('a')->item(0)) {
                           $imdbRaw = $cell->getElementsByTagName('a')->item(0)->getAttribute('href');
                           preg_match('!tt(\d+)!', $imdbRaw, $imdb);
                            $imdbId = $imdb[1];
                            $title = trim($cell->getElementsByTagName('a')->item(0)->getAttribute('title'));
                        }
                        //Episodenumber
                        if ($cell->getElementsByTagName('meta')->item(0)) {
                            $epNumberRaw = $cell->getElementsByTagName('meta')->item(0)->getAttribute('content');
                            $epNumber = (int) $epNumberRaw;
                        }
                        //Airdate and plot
                        $airdatePlot = array();
                        if ($divs = $cell->getElementsByTagName('div')) {
                            foreach ($divs as $div) {
                                $t = $div->getAttribute('class');
                                //Airdate
                                if ($t == 'airdate') {
                                    $airdatePlot[] = trim($div->nodeValue);
                                }
                                //Plot
                                if ($t == 'item_description') {
                                    if (stripos($div->nodeValue, 'add a plot') === false) {
                                        $airdatePlot[] = trim(strip_tags($div->nodeValue));
                                    } else {
                                        $airdatePlot[] = '';
                                    }
                                }
                            }
                        }
                        $episode = array(
                            'imdbid' => $imdbId,
                            'title' => $title,
                            'airdate' => $airdatePlot[0],
                            'plot' => $airdatePlot[1],
                            'season' => $s,
                            'episode' => $epNumber,
                            'image_url' => $imgUrl
                        );
                        
                        if ($epNumber == -1) {
                            $this->season_episodes[$s][] = $episode;
                        } else {
                            $this->season_episodes[$s][$epNumber] = $episode;
                        }
                    }
                }
                
             }
        }
        return $this->season_episodes;
    }

    #==========================================================[ /quotes page ]===
    /** Get the quotes for a given movie (split-up variant)
     * @return array quote array[string quote, array character]; character: array[string url, string name]
     * @see IMDB page /quotes
     */
    public function quotes()
    {
        if (empty($this->moviequotes)) {
            $xpath = $this->getXpathPage("Quotes");
            if (empty($xpath)) {
                return array(); // no such page
            }
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array(); // no data available
            }
            if ($quotesContent = $xpath->query("//div[@class='sodatext']")) {
                foreach ($quotesContent as $key => $value) {
                    $p = $value->getElementsByTagName('p');
                    foreach ($p as $quoteItem) {
                        if ($anchor = $quoteItem->getElementsByTagName('a')->item(0)) {
                           $href = $anchor->getAttribute('href');
                           $url = str_replace('/name/', 'https://' . $this->imdbsite . '/name/', $href);
                           $quoteLine = explode(":", $quoteItem->nodeValue, 2);
                           $this->moviequotes[$key][] = array(
                                        'quote' => trim(strip_tags($quoteLine[1])),
                                        'character' => array('url' => $url, 'name' => trim($quoteLine[0]))
                                    );
                        } else {
                            $this->moviequotes[$key][] = array(
                                        'quote' => trim(strip_tags($quoteItem->nodeValue)),
                                        'character' => array('url' => '', 'name' => '')
                                    );
                        }
                    }
                    ++$key;
                }
            }
        }
        return $this->moviequotes;
    }

    #==========================================================[ /trivia page ]===
    /**
     * Get the trivia info
     * @param boolean $spoil if true spoilers are also included.
     * @return array trivia (array[0..n] string
     * @see IMDB page /trivia
     */
    public function trivia($spoil = false)
    {
        if (empty($this->trivia)) {
            $xpath = $this->getXpathPage("Trivia");
            if (empty($xpath)) {
                return array(); // no such page
            }
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array(); // no data available
            }
            if ($triviaContent = $xpath->query("//div[@id='trivia_content']//div[@class='list']")) {
                foreach ($triviaContent as $value) {
                    if ($value->getElementsByTagName('a')->item(0)->getAttribute('id') != "spoilers") {
                        if ($cells = $xpath->query('.//div[contains(@class, "sodatext")]', $value)) {
                            foreach ($cells as $cell) {
                                if ($cell->nodeValue != "") {
                                    $this->trivia[] = trim($cell->nodeValue);
                                }
                            }
                        }
                    } elseif ($spoil == true) {
                        if ($cells = $xpath->query('.//div[contains(@class, "sodatext")]', $value)) {
                            foreach ($cells as $cell) {
                                if ($cell->nodeValue != "") {
                                    $this->trivia[] = trim($cell->nodeValue);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->trivia;
    }

    #======================================================[ Soundtrack ]===
    /**
     * Get the soundtrack listing
     * @return array soundtracks
     * [ soundtrack : name of the soundtrack
     *   credits : Full text only description of the credits. Contains newline characters
     * ]
     * @see IMDB page /soundtrack
     */
    public function soundtrack()
    {
        if (empty($this->soundtracks)) {
            $xpath = $this->getXpathPage("Soundtrack");
            if (empty($xpath)) {
                return array(); // no such page
            }
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array(); // no data available
            }
            $cells = $xpath->query("//div[@class=\"soundTrack soda odd\" or @class=\"soundTrack soda even\"]");
            if (!empty($cells)) {
                foreach ($cells as $cell) {
                    // Get all values from xpath query and save it as XML
                    // to ensure soundtrack can be sepparated from credits
                    $html = explode("<br/>", $cell->ownerDocument->saveXML($cell), 2);
                    // explode all credit lines to array.
                    $creditsExp = explode("<br/>", $html[1]);
                    $count = count($creditsExp);
                    $credits = '';
                    foreach ($creditsExp as $key => $value) {
                        $credits .= trim(strip_tags($value));
                        if ($key < $count -1) {
                            $credits .= "\n";
                        }
                    }
                    $this->soundtracks[] = array(
                        'soundtrack' => trim(strip_tags($html[0])),
                        'credits' => trim($credits)
                    );
                }
            }
        }
        return $this->soundtracks;
    }

    #=======================================================[ /locations page ]===
    /**
     * Filming locations
     * @return array locations (array[0..n] of arrays[real_loc,movie_loc])
     * real_loc: Real filming location, movie_loc: location in the movie
     * @see IMDB page /locations
     */
    public function locations()
    {
        if (empty($this->locations)) {
            $xpath = $this->getXpathPage("Locations");
            if (empty($xpath)) {
                return array();
            } // no such page
            $cells = $xpath->query("//section[@id=\"filming_locations\"]
                                    //div[@class=\"soda sodavote odd\" or @class=\"soda sodavote even\"]");
            if ($cells != null) {
                foreach ($cells as $cell) {
                    $real = '';
                    $movie = '';
                    if ($cell->getElementsByTagName('dt')->item(0)) {
                        $real = trim($cell->getElementsByTagName('dt')->item(0)->nodeValue);
                    }
                    if ($cell->getElementsByTagName('dd')->item(0)) {
                        $movie = trim($cell->getElementsByTagName('dd')->item(0)->nodeValue);
                    }
                    $this->locations[] = array(
                        'real_loc' => $real,
                        'movie_loc' => $movie
                    );
                }
            }
        }
        return $this->locations;
    }

    #========================================================[ /keywords page ]===
    /**
     * Get all keywords from movie
     * @return array keywords
     * @see IMDB page /keywords
     */
    public function keywords()
    {
        if (empty($this->all_keywords)) {
            $xpath = $this->getXpathPage("Keywords");
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array();
            }
            if ($cells = $xpath->query("//div[@class=\"sodatext\"]/a")) {
                foreach ($cells as $cell) {
                    if ($cell->nodeValue != "") {
                        $this->all_keywords[] = trim($cell->nodeValue);
                    }
                }
            }
        }
        return $this->all_keywords;
    }

    #========================================================[ /Alternate versions page ]===
    /**
     * Get the Alternate Versions for a given movie
     * @return array Alternate Version (array[0..n] of string)
     * @see IMDB page /alternateversions
     */
    public function alternateVersions()
    {
        if (empty($this->moviealternateversions)) {
            $xpath = $this->getXpathPage("AlternateVersions");
            if ($xpath->evaluate("//div[contains(@id,'no_content')]")->count()) {
                return array();
            }
            $cells = $xpath->query("//div[@class=\"soda odd\" or @class=\"soda even\"]");
            foreach ($cells as $cell) {
                $output = '';
                $nodes = $xpath->query(".//text()", $cell);
                foreach ($nodes as $node) {
                    if ($node->parentNode->nodeName === 'li') {
                        $output .= '- ';
                    }
                    $output .= trim($node->nodeValue) . "\n";
                }
                $this->moviealternateversions[] = trim($output);
            }
        }
        return $this->moviealternateversions;
    }

    #========================================================[ Helper Functions]===
    protected function getPage($page = null)
    {
        if (!empty($this->page[$page])) {
            return $this->page[$page];
        }

        $this->page[$page] = parent::getPage($page);

        return $this->page[$page];
    }

    protected function jsonLD()
    {
        if ($this->jsonLD) {
            return $this->jsonLD;
        }
        $page = $this->getPage("Title");
        preg_match('#<script type="application/ld\+json">(.+?)</script>#ims', $page, $matches);
        $this->jsonLD = json_decode($matches[1]);
        return $this->jsonLD;
    }
}
