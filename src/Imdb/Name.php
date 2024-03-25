<?php

#############################################################################
# IMDBPHP6                             (c) Giorgos Giagas & Itzchak Rehberg #
# written by Giorgos Giagas                                                 #
# extended & maintained by Itzchak Rehberg <izzysoft AT qumran DOT org>     #
# written extended & maintained by Ed                                       #
# http://www.izzysoft.de/                                                   #
# ------------------------------------------------------------------------- #
# This program is free software; you can redistribute and/or modify it      #
# under the terms of the GNU General Public License (see doc/LICENSE)       #
#############################################################################

namespace Imdb;

use Psr\SimpleCache\CacheInterface;

/**
 * A person on IMDb
 * @author Izzy (izzysoft AT qumran DOT org)
 * @author Ed
 * @copyright 2008 by Itzchak Rehberg and IzzySoft
 */
class Name extends MdbBase
{

    // "Name" page:
    protected $mainPhoto = null;
    protected $fullName = "";
    protected $birthday = array();
    protected $deathday = array();
    protected $professions = array();
    protected $popRank = array();

    // "Bio" page:
    protected $birthName = "";
    protected $nickName = array();
    protected $akaName = array();
    protected $bodyheight = array();
    protected $spouses = array();
    protected $children = array();
    protected $parents = array();
    protected $relatives = array();
    protected $bioBio = array();
    protected $bioTrivia = array();
    protected $bioQuotes = array();
    protected $bioTrademark = array();
    protected $bioSalary = array();

    // "Publicity" page:
    protected $pubPrints = array();
    protected $pubMovies = array();
    protected $pubOtherWorks = array();

    // "Credits" page:
    protected $awards = array();
    protected $creditKnownFor = array();
    protected $credits = array();

    /**
     * @param string $id IMDBID to use for data retrieval
     * @param Config $config OPTIONAL override default config
     * @param CacheInterface $cache OPTIONAL override the default cache with any PSR-16 cache.
     */
    public function __construct($id, Config $config = null, CacheInterface $cache = null)
    {
        parent::__construct($config, $cache);
        $this->setid($id);
    }

    #=============================================================[ Main Page ]===

    #------------------------------------------------------------------[ Name ]---
    /** Get the name of the person
     * @return string name full name of the person
     * @see IMDB person page / (Main page)
     */
    public function name()
    {
        $query = <<<EOF
query Name(\$id: ID!) {
  name(id: \$id) {
    nameText {
      text
    }
  }
}
EOF;

        $data = $this->graphql->query($query, "Name", ["id" => "nm$this->imdbID"]);
        $this->fullName = isset($data->name->nameText->text) ? $data->name->nameText->text : '';
        return $this->fullName;
    }

    #--------------------------------------------------------[ Photo specific ]---
    /** Get cover photo
     * @param boolean $size (optional) small:  thumbnail (67x98, default)
     *                                 medium: image size (621x931)
     *                                 large:  image maximum size
     * @note if small or medium url 404 or 401 the full image url is returned!
     * @return mixed photo (string url if found, empty string otherwise)
     * @see IMDB person page / (Main page)
     */
    public function photo($size = "small")
    {
    $query = <<<EOF
query PrimaryImage(\$id: ID!) {
  name(id: \$id) {
    primaryImage {
      url
    }
  }
}
EOF;
        if ($this->mainPhoto === null) {
            $data = $this->graphql->query($query, "PrimaryImage", ["id" => "nm$this->imdbID"]);
            if ($data->name->primaryImage->url != null) {
                $img = str_replace('.jpg', '', $data->name->primaryImage->url);
                if ($size == "small") {
                    $this->mainPhoto = $img . 'QL100_SY98_.jpg';
                    $headers = get_headers($this->mainPhoto);
                    if (substr($headers[0], 9, 3) == "404" || substr($headers[0], 9, 3) == "401") {
                        $this->mainPhoto = $data->name->primaryImage->url;
                    }
                }
                if ($size == "medium") {
                    $this->mainPhoto = $img . 'QL100_SY931_.jpg';
                    $headers = get_headers($this->mainPhoto);
                    if (substr($headers[0], 9, 3) == "404" || substr($headers[0], 9, 3) == "401") {
                        $this->mainPhoto = $data->name->primaryImage->url;
                    }
                }
                if ($size == "large") {
                    $this->mainPhoto = $data->name->primaryImage->url;
                }
            } else {
                return $this->mainPhoto;
            }
        }
        return $this->mainPhoto;
    }

    #==================================================================[ /bio ]===
    #------------------------------------------------------------[ Birth Name ]---
    /** Get the birth name
     * @return string birthname
     * @see IMDB person page /bio
     */
    public function birthname()
    {
    $query = <<<EOF
query BirthName(\$id: ID!) {
  name(id: \$id) {
    birthName {
      text
    }
  }
}
EOF;

        $data = $this->graphql->query($query, "BirthName", ["id" => "nm$this->imdbID"]);
        $this->birthName = isset($data->name->birthName->text) ? $data->name->birthName->text : '';
        return $this->birthName;
    }

    #-------------------------------------------------------------[ Nick Name ]---
    /** Get the nick name
     * @return array nicknames array[0..n] of strings
     * @see IMDB person page /bio
     */
    public function nickname()
    {
        if (empty($this->nickName)) {
            $query = <<<EOF
query NickName(\$id: ID!) {
  name(id: \$id) {
    nickNames {
      text
    }
  }
}
EOF;

            $data = $this->graphql->query($query, "NickName", ["id" => "nm$this->imdbID"]);
            foreach ($data->name->nickNames as $nickName) {
                if (!empty($nickName->text)) {
                    $this->nickName[] = $nickName->text;
                }
            }
        }
        return $this->nickName;
    }

    #-------------------------------------------------------------[ Alternative Names ]---
    /** Get alternative names for a person
     * @return array[0..n] of alternative names
     * @see IMDB person page /bio
     */
    public function akaName()
    {
        if (empty($this->akaName)) {
            $query = <<<EOF
query AkaName(\$id: ID!) {
  name(id: \$id) {
    akas(first: 9999) {
      edges {
        node {
          text
        }
      }
    }
  }
}
EOF;

            $data = $this->graphql->query($query, "AkaName", ["id" => "nm$this->imdbID"]);
            if ($data->name->akas->edges != null) {
                foreach ($data->name->akas->edges as $edge) {
                    $this->akaName[] = isset($edge->node->text) ? $edge->node->text : '';
                }
            } else {
                return $this->akaName;
            }
        }
        return $this->akaName;
    }

    #------------------------------------------------------------------[ Born ]---
    /** Get Birthday
     * @return array|null birthday [day,month,mon,year,place]
     *         where $monthName is the month name, and $monthInt the month number
     * @see IMDB person page /bio
     */
    public function born()
    {
        if (empty($this->birthday)) {
            $query = <<<EOF
query BirthDate(\$id: ID!) {
  name(id: \$id) {
    birthDate {
      dateComponents {
        day
        month
        year
      }
    }
    birthLocation {
      text
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "BirthDate", ["id" => "nm$this->imdbID"]);
            $day = isset($data->name->birthDate->dateComponents->day) ? $data->name->birthDate->dateComponents->day : '';
            $monthInt = isset($data->name->birthDate->dateComponents->month) ? $data->name->birthDate->dateComponents->month : '';
            $monthName = '';
            if (!empty($monthInt)) {
                $monthName = date("F", mktime(0, 0, 0, $monthInt, 10));
            }
            $year = isset($data->name->birthDate->dateComponents->year) ? $data->name->birthDate->dateComponents->year : '';
            $place = isset($data->name->birthLocation->text) ? $data->name->birthLocation->text : '';
            $this->birthday = array(
                "day" => $day,
                "month" => $monthName,
                "mon" => $monthInt,
                "year" => $year,
                "place" => $place
            );
        }
        return $this->birthday;
    }

    #------------------------------------------------------------------[ Died ]---
    /**
     * Get date of death with place and cause
     * @return array [day,monthName,monthInt,year,place,cause,status]
     *         New: Status returns current state: ALIVE,DEAD or PRESUMED_DEAD
     * @see IMDB person page /bio
     */
    public function died()
    {
        if (empty($this->deathday)) {
            $query = <<<EOF
query DeathDate(\$id: ID!) {
  name(id: \$id) {
    deathDate {
      dateComponents {
        day
        month
        year
      }
    }
    deathLocation {
      text
    }
    deathCause {
      text
    }
    deathStatus
  }
}
EOF;
            $data = $this->graphql->query($query, "DeathDate", ["id" => "nm$this->imdbID"]);
            $day = isset($data->name->deathDate->dateComponents->day) ? $data->name->deathDate->dateComponents->day : '';
            $monthInt = isset($data->name->deathDate->dateComponents->month) ? $data->name->deathDate->dateComponents->month : '';
            $monthName = '';
            if (!empty($monthInt)) {
                $monthName = date("F", mktime(0, 0, 0, $monthInt, 10));
            }
            $year = isset($data->name->deathDate->dateComponents->year) ? $data->name->deathDate->dateComponents->year : '';
            $place = isset($data->name->deathLocation->text) ? $data->name->deathLocation->text : '';
            $cause = isset($data->name->deathCause->text) ? $data->name->deathCause->text : '';
            $status = isset($data->name->deathStatus) ? $data->name->deathStatus : '';
            $this->deathday = array(
                "day" => $day,
                "month" => $monthName,
                "mon" => $monthInt,
                "year" => $year,
                "place" => $place,
                "cause" => $cause,
                "status" => $status
            );
        }
        return $this->deathday;
    }

    #-----------------------------------------------------------[ Primary Professions ]---
    /** Get primary professions of this person
     * @return array() all professions
     * @see IMDB person page
     */
    public function profession()
    {
        if (empty($this->professions)) {
            $query = <<<EOF
query Professions(\$id: ID!) {
  name(id: \$id) {
    primaryProfessions {
      category {
        text
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "Professions", ["id" => "nm$this->imdbID"]);
            if (isset($data->name->primaryProfessions) && $data->name->primaryProfessions != null) {
                foreach ($data->name->primaryProfessions as $primaryProfession) {
                    $this->professions[] = isset($primaryProfession->category->text) ? $primaryProfession->category->text : '';
                }
            } else {
                return $this->professions;
            }
        }
        return $this->professions;
    }

    #----------------------------------------------------------[ Popularity ]---
    /**
     * Get current popularity rank of a person
     * @return array(currentRank: int, changeDirection: enum (string?), difference: int)
     * @see IMDB page / (NamePage)
     */
    public function rank()
    {
        if (empty($this->popRank)) {
            $query = <<<EOF
query Rank(\$id: ID!) {
  name(id: \$id) {
    meterRanking {
      currentRank
      rankChange {
        changeDirection
        difference
      }
    }
  }
}
EOF;

            $data = $this->graphql->query($query, "Rank", ["id" => "nm$this->imdbID"]);
            if (isset($data->name->meterRanking)) {
                $this->popRank['currentRank'] = isset($data->name->meterRanking->currentRank) ?
                                                        $data->name->meterRanking->currentRank : null;
                                                        
                $this->popRank['changeDirection'] = isset($data->name->meterRanking->rankChange->changeDirection) ?
                                                            $data->name->meterRanking->rankChange->changeDirection : null;
                                                            
                $this->popRank['difference'] = isset($data->name->meterRanking->rankChange->difference) ?
                                                       $data->name->meterRanking->rankChange->difference : null;
            } else {
                return $this->popRank;
            }
        }
        return $this->popRank;
    }

    #-----------------------------------------------------------[ Body Height ]---
    /** Get the body height
     * @return array [imperial,metric] height in feet and inch (imperial) an meters (metric)
     * @see IMDB person page /bio
     */
    public function height()
    {
        if (empty($this->bodyheight)) {
            $query = <<<EOF
query BodyHeight(\$id: ID!) {
  name(id: \$id) {
    height {
      displayableProperty {
        value {
          plainText
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "BodyHeight", ["id" => "nm$this->imdbID"]);
            if (isset($data->name->height->displayableProperty->value->plainText)) {
                $heightParts = explode("(", $data->name->height->displayableProperty->value->plainText);
                $this->bodyheight["imperial"] = trim($heightParts[0]);
                if (isset($heightParts[1]) && !empty($heightParts[1])) {
                    $this->bodyheight["metric"] = trim($heightParts[1], " m)");
                } else {
                    $this->bodyheight["metric"] = '';
                }
            } else {
                return $this->bodyheight;
            }
        }
        return $this->bodyheight;
    }

    #----------------------------------------------------------------[ Spouse ]---
    /** Get spouse(s)
     * @return array [0..n] of array spouses [string imdb, string name, array from,
     *         array to, string comment, int children] where from/to are array
     *         [day,month,mon,year] (MonthName is the name, MonthInt the number of the month),
     * @see IMDB person page /bio
     */
    public function spouse()
    {
        if (empty($this->spouses)) {
            $query = <<<EOF
query Spouses(\$id: ID!) {
  name(id: \$id) {
    spouses {
      spouse {
        name {
          id
        }
        asMarkdown {
          plainText
        }
      }
      timeRange {
        fromDate {
          dateComponents {
            day
            month
            year
          }
        }
        toDate {
          dateComponents {
            day
            month
            year
          }
        }
      }
      attributes {
        text
      }
      current
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "Spouses", ["id" => "nm$this->imdbID"]);
            if ($data != null && $data->name->spouses != null) {
                foreach ($data->name->spouses as $spouse) {
                    // Spouse name
                    $name = isset($spouse->spouse->asMarkdown->plainText) ? $spouse->spouse->asMarkdown->plainText : '';
                    
                    // Spouse id
                    $imdbId = '';
                    if ($spouse->spouse->name != null) {
                        if (isset($spouse->spouse->name->id)) {
                            $imdbId = str_replace('nm', '', $spouse->spouse->name->id);
                        }
                    }
                    
                    // From date
                    $fromDateDay = isset($spouse->timeRange->fromDate->dateComponents->day) ? $spouse->timeRange->fromDate->dateComponents->day : '';
                    $fromDateMonthInt = isset($spouse->timeRange->fromDate->dateComponents->month) ? $spouse->timeRange->fromDate->dateComponents->month : '';
                    $fromDateMonthName = '';
                    if (!empty($fromDateMonthInt)) {
                        $fromDateMonthName = date("F", mktime(0, 0, 0, $fromDateMonthInt, 10));
                    }
                    $fromDateYear = isset($spouse->timeRange->fromDate->dateComponents->year) ? $spouse->timeRange->fromDate->dateComponents->year : '';
                    $fromDate = array(
                        "day" => $fromDateDay,
                        "month" => $fromDateMonthName,
                        "mon" => $fromDateMonthInt,
                        "year" => $fromDateYear
                    );
                    
                    // To date
                    $toDateDay = isset($spouse->timeRange->toDate->dateComponents->day) ? $spouse->timeRange->toDate->dateComponents->day : '';
                    $toDateMonthInt = isset($spouse->timeRange->toDate->dateComponents->month) ? $spouse->timeRange->toDate->dateComponents->month : '';
                    $toDateMonthName = '';
                    if (!empty($toDateMonthInt)) {
                        $toDateMonthName = date("F", mktime(0, 0, 0, $toDateMonthInt, 10));
                    }
                    $toDateYear = isset($spouse->timeRange->toDate->dateComponents->year) ? $spouse->timeRange->toDate->dateComponents->year : '';
                    $toDate = array(
                        "day" => $toDateDay,
                        "month" => $toDateMonthName,
                        "mon" => $toDateMonthInt,
                        "year" => $toDateYear
                    );
                    
                    // Comments and children
                    $comment = '';
                    $children = 0;
                    if ($spouse->attributes != null) {
                        foreach ($spouse->attributes as $key => $attribute) {
                            if (stripos($attribute->text, "child") !== false) {
                                $children = (int) preg_replace('/[^0-9]/', '', $attribute->text);
                            } else {
                                $comment .= $attribute->text;
                            }
                        }
                    }
                    $this->spouses[] = array(
                        'imdb' => $imdbId,
                        'name' => $name,
                        'from' => $fromDate,
                        'to' => $toDate,
                        'comment' => $comment,
                        'children' => $children,
                        'current' => $spouse->current
                    );
                }
            } else {
                return $this->spouses;
            }
        }
        return $this->spouses;
    }

    #----------------------------------------------------------------[ Children ]---
    /** Get the Children
     * @return array children array[0..n] of array(imdb, name, relType)
     * @see IMDB person page /bio
     */
    public function children()
    {
        if (empty($this->children)) {
            return $this->nameDetailsParse("CHILDREN", $this->children);
        }
        return $this->children;
    }
    
    #----------------------------------------------------------------[ Parents ]---
    /** Get the Parents
     * @return array parents array[0..n] of array(imdb, name, relType)
     * @see IMDB person page /bio
     */
    public function parents()
    {
        if (empty($this->parents)) {
            return $this->nameDetailsParse("PARENTS", $this->parents);
        }
        return $this->parents;
    }
    
    #----------------------------------------------------------------[ Relatives ]---
    /** Get the relatives
     * @return array relatives array[0..n] of array(imdb, name, relType)
     * @see IMDB person page /bio
     */
    public function relatives()
    {
        if (empty($this->relatives)) {
            return $this->nameDetailsParse("OTHERS", $this->relatives);
        }
        return $this->relatives;
    }

    #---------------------------------------------------------------[ MiniBio ]---
    /** Get the person's mini bio
     * @return array bio array [0..n] of array[string desc, string author]
     * @see IMDB person page /bio
     */
    public function bio()
    {
        if (empty($this->bioBio)) {
            $query = <<<EOF
query MiniBio(\$id: ID!) {
  name(id: \$id) {
    bios(first: 9999) {
      edges {
        node {
          text {
            plainText
          }
          author {
            plainText
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "MiniBio", ["id" => "nm$this->imdbID"]);
            foreach ($data->name->bios->edges as $edge) {
                $bio_bio["desc"] = isset($edge->node->text->plainText) ? $edge->node->text->plainText : '';
                $bioAuthor = '';
                if ($edge->node->author != null) {
                    if (isset($edge->node->author->plainText)) {
                        $bioAuthor = $edge->node->author->plainText;
                    }
                }
                $bio_bio["author"] = $bioAuthor;
                $this->bioBio[] = $bio_bio;
            }
        }
        return $this->bioBio;
    }

    #----------------------------------------------------------------[ Trivia ]---
    /** Get the Trivia
     * @return array trivia array[0..n] of string
     * @see IMDB person page /bio
     */
    public function trivia()
    {
        if (empty($this->bioTrivia)) {
            return $this->dataParse("trivia", $this->bioTrivia);
        }
        return $this->bioTrivia;
    }

    #----------------------------------------------------------------[ Quotes ]---
    /** Get the Personal Quotes
     * @return array quotes array[0..n] of string
     * @see IMDB person page /bio
     */
    public function quotes()
    {
        if (empty($this->bioQuotes)) {
            return $this->dataParse("quotes", $this->bioQuotes);
        }
        return $this->bioQuotes;
    }

    #------------------------------------------------------------[ Trademarks ]---
    /** Get the "trademarks" of the person
     * @return array trademarks array[0..n] of strings
     * @see IMDB person page /bio
     */
    public function trademark()
    {
        if (empty($this->bioTrademark)) {
            return $this->dataParse("trademarks", $this->bioTrademark);
        }
        return $this->bioTrademark;
    }

    #----------------------------------------------------------------[ Salary ]---
    /** Get the salary list
     * @return array salary array[0..n] of array [strings imdb, name, year, amount, currency, array comments[]]
     * @see IMDB person page /bio
     */
    public function salary()
    {
        if (empty($this->bioSalary)) {
            $query = <<<EOF
query Salaries(\$id: ID!) {
  name(id: \$id) {
    titleSalaries(first: 9999) {
      edges {
        node {
          title {
            titleText {
              text
            }
            id
            releaseYear {
              year
            }
          }
          amount {
            amount
            currency
          }
          attributes {
            text
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "Salaries", ["id" => "nm$this->imdbID"]);
            if ($data != null && $data->name->titleSalaries != null) {
                foreach ($data->name->titleSalaries->edges as $edge) {
                    $title = isset($edge->node->title->titleText->text) ? $edge->node->title->titleText->text : '';
                    $imdbId = isset($edge->node->title->id) ? str_replace('tt', '', $edge->node->title->id) : '';
                    $year = isset($edge->node->title->releaseYear->year) ? $edge->node->title->releaseYear->year : '';
                    $amount = isset($edge->node->amount->amount) ? $edge->node->amount->amount : '';
                    $currency = isset($edge->node->amount->currency) ? $edge->node->amount->currency : '';
                    $comments = array();
                    if ($edge->node->attributes != null) {
                        foreach ($edge->node->attributes as $attribute) {
                            if (isset($attribute->text)) {
                                $comments[] = $attribute->text;
                            }
                        }
                    }
                    $this->bioSalary[] = array(
                        'imdb' => $imdbId,
                        'name' => $title,
                        'year' => $year,
                        'amount' => $amount,
                        'currency' => $currency,
                        'comment' => $comments
                    );
                }
            } else {
                return $this->bioSalary;
            }
        }
        return $this->bioSalary;
    }

    #============================================================[ /publicity ]===

    #-----------------------------------------------------------[ Print media ]---
    /** Print media about this person
     * @return array prints array[0..n] of array[title, author, place, publisher, isbn],
     *         where "place" refers to the place of publication including year
     * @see IMDB person page /publicity
     */
    public function pubprints()
    {
        if (empty($this->pubPrints)) {
            $query = <<<EOF
query PubPrint(\$id: ID!) {
  name(id: \$id) {
    publicityListings(first: 9999, filter: {categories: ["namePrintBiography"]}) {
      edges {
        node {
          ... on NamePrintBiography {
            title {
                text
            }
            authors {
                plainText
            }
            isbn
            publisher
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "PubPrint", ["id" => "nm$this->imdbID"]);
            if ($data != null && $data->name->publicityListings != null) {
                foreach ($data->name->publicityListings->edges as $edge) {
                    $title = isset($edge->node->title->text) ? $edge->node->title->text : '';
                    $isbn = isset($edge->node->isbn) ? $edge->node->isbn : '';
                    $publisher = isset($edge->node->publisher) ? $edge->node->publisher : '';
                    $authors = array();
                    if ($edge->node->authors != null) {
                        foreach ($edge->node->authors as $author) {
                            if (isset($author->plainText)) {
                                $authors[] = $author->plainText;
                            }
                        }
                    }
                    $this->pubPrints[] = array(
                        "title" => $title,
                        "author" => $authors,
                        "publisher" => $publisher,
                        "isbn" => $isbn
                    );
                }
            } else {
                return $this->pubPrints;
            }
        }
        return $this->pubPrints;
    }

    #----------------------------------------------------[ Biographical movies ]---
    /** Biographical Movies
     * @return array pubmovies array[0..n] of array[title, id, year, seriesTitle, seriesSeason, seriesEpisode]
     * @see IMDB person page /publicity
     */
    public function pubmovies()
    {
        if (empty($this->pubMovies)) {
            $query = <<<EOF
query PubFilm(\$id: ID!) {
  name(id: \$id) {
    publicityListings(first: 9999, filter: {categories: ["nameFilmBiography"]}) {
      edges {
        node {
          ... on NameFilmBiography {
            title {
              titleText {
                text
              }
              id
              releaseYear {
                year
              }
              series {
                displayableEpisodeNumber {
                  displayableSeason {
                    text
                  }
                  episodeNumber {
                    text
                  }
                }
                series {
                  titleText {
                    text
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "PubFilm", ["id" => "nm$this->imdbID"]);
            if ($data != null && $data->name->publicityListings != null) {
                foreach ($data->name->publicityListings->edges as $edge) {
                    $filmTitle = isset($edge->node->title->titleText->text) ? $edge->node->title->titleText->text : '';
                    $filmId = isset($edge->node->title->id) ? str_replace('tt', '', $edge->node->title->id) : '';
                    $filmYear = isset($edge->node->title->releaseYear->year) ? $edge->node->title->releaseYear->year : '';
                    $filmSeriesSeason = '';
                    $filmSeriesEpisode = '';
                    $filmSeriesTitle = '';
                    if ($edge->node->title->series != null) {
                        $filmSeriesTitle = isset($edge->node->title->series->series->titleText->text) ? $edge->node->title->series->series->titleText->text : '';
                        $filmSeriesSeason = isset($edge->node->title->series->displayableEpisodeNumber->displayableSeason->text) ?
                                                  $edge->node->title->series->displayableEpisodeNumber->displayableSeason->text : '';
                        $filmSeriesEpisode = isset($edge->node->title->series->displayableEpisodeNumber->episodeNumber->text) ?
                                                   $edge->node->title->series->displayableEpisodeNumber->episodeNumber->text : '';
                    }
                    $this->pubMovies[] = array(
                        "title" => $filmTitle,
                        "id" => $filmId,
                        "year" => $filmYear,
                        "seriesTitle" => $filmSeriesTitle,
                        "seriesSeason" => $filmSeriesSeason,
                        "seriesEpisode" => $filmSeriesEpisode,
                    );
                }
            } else {
                return $this->pubMovies;
            }
        }
        return $this->pubMovies;
    }

    #----------------------------------------------------[ Other Works ]---
    /** Other works of this person
     * @return array pubOtherWorks array[0..n] of array[category, fromDate array(day, month,year), toDate array(day, month,year), text]
     * @see IMDB person page /otherworks
     */
    public function pubother()
    {
        if (empty($this->pubOtherWorks)) {
            $query = <<<EOF
query PubOther(\$id: ID!) {
  name(id: \$id) {
    otherWorks(first: 9999) {
      edges {
        node {
          category {
            text
          }
          fromDate
          toDate
          text {
            plainText
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "PubOther", ["id" => "nm$this->imdbID"]);
            if ($data->name->otherWorks->edges != null) {
                foreach ($data->name->otherWorks->edges as $edge) {
                    $category = isset($edge->node->category) ? $edge->node->category->text : null;
                    
                    // From date
                    $fromDateDay = isset($edge->node->fromDate->day) ? $edge->node->fromDate->day : null;
                    $fromDateMonth = isset($edge->node->fromDate->month) ? $edge->node->fromDate->month : null;
                    $fromDateYear = isset($edge->node->fromDate->year) ? $edge->node->fromDate->year : null;
                    $fromDate = array(
                        "day" => $fromDateDay,
                        "month" => $fromDateMonth,
                        "year" => $fromDateYear
                    );

                    // To date
                    $toDateDay = isset($edge->node->toDate->day) ? $edge->node->toDate->day : null;
                    $toDateMonth = isset($edge->node->toDate->month) ? $edge->node->toDate->month : null;
                    $toDateYear = isset($edge->node->toDate->year) ? $edge->node->toDate->year : null;
                    $toDate = array(
                        "day" => $toDateDay,
                        "month" => $toDateMonth,
                        "year" => $toDateYear
                    );

                    $text = isset($edge->node->text->plainText) ? $edge->node->text->plainText : null;

                    $this->pubOtherWorks[] = array(
                        "category" => $category,
                        "fromDate" => $fromDate,
                        "toDate" => $toDate,
                        "text" => $text
                    );
                }
            } else {
                return $this->pubOtherWorks;
            }
        }
        return $this->pubOtherWorks;
    }

    #-------------------------------------------------------[ Awards ]---
    /**
     * Get all awards for a name
     * @param $winsOnly Default: false, set to true to only get won awards
     * @param $event Default: "" fill eventId Example " ev0000003" to only get Oscars
     *  Possible values for $event:
     *  ev0000003 (Oscar)
     *  ev0000223 (Emmy)
     *  ev0000292 (Golden Globe)
     * @return array[festivalName][0..n] of 
     *      array[awardYear,awardWinner(bool),awardCategory,awardName,awardNotes
     *      array awardTitles[titleId,titleName,titleNote],awardOutcome] array total(win, nom)
     *  Array
     *       (
     *           [Academy Awards, USA] => Array
     *               (
     *                   [0] => Array
     *                   (
     *                   [awardYear] => 1972
     *                   [awardWinner] => 
     *                   [awardCategory] => Best Picture
     *                   [awardName] => Oscar
     *                   [awardTitles] => Array
     *                       (
     *                           [0] => Array
     *                               (
     *                                   [titleId] => 0000040
     *                                   [titleName] => 1408
     *                                   [titleNote] => screenplay/director
     *                               )
     *
     *                       )
     *                   [awardNotes] => Based on the novel
     *                   [awardOutcome] => Nominee
     *                   )
     *               )
     *           )
     *           [total] => Array
     *           (
     *               [win] => 12
     *               [nom] => 26
     *           )
     *
     *       )
     * @see IMDB page / (TitlePage)
     */
    public function award($winsOnly = false, $event = "")
    {
        $winsOnly = $winsOnly === true ? "WINS_ONLY" : "null";
        $event = !empty($event) ? "events: " . '"' . trim($event) . '"' : "";
        if (empty($this->awards)) {
            $query = <<<EOF
query Award(\$id: ID!) {
  name(id: \$id) {
    awardNominations(
      first: 9999
      sort: {by: PRESTIGIOUS, order: DESC}
      filter: {wins: $winsOnly $event}
    ) {
      edges {
        node {
          award {
            event {
              text
            }
            text
            category {
              text
            }
            eventEdition {
              year
            }
            notes {
              plainText
            }
          }
          isWinner
          awardedEntities {
            ... on AwardedNames {
              secondaryAwardTitles {
                title {
                  id
                  titleText {
                    text
                  }
                }
                note {
                  plainText
                }
              }
            }
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "Award", ["id" => "nm$this->imdbID"]);
            $winnerCount = 0;
            $nomineeCount = 0;
            foreach ($data->name->awardNominations->edges as $edge) {
                $eventName = isset($edge->node->award->event->text) ? $edge->node->award->event->text : '';
                $eventEditionYear = isset($edge->node->award->eventEdition->year) ? $edge->node->award->eventEdition->year : '';
                $awardName = isset($edge->node->award->text) ? $edge->node->award->text : '';
                $awardCategory = isset($edge->node->award->category->text) ? $edge->node->award->category->text : '';
                $awardNotes = isset($edge->node->award->notes->plainText) ? $edge->node->award->notes->plainText : '';
                $awardIsWinner = $edge->node->isWinner;
                $conclusion = $awardIsWinner === true ? "Winner" : "Nominee";
                $awardIsWinner === true ? $winnerCount++ : $nomineeCount++;
                
                //credited titles
                $titles = array();
                if ($edge->node->awardedEntities->secondaryAwardTitles !== null) {
                    foreach ($edge->node->awardedEntities->secondaryAwardTitles as $title) {
                        $titleName = isset($title->title->titleText->text) ? $title->title->titleText->text : '';
                        $titleId = isset($title->title->id) ? $title->title->id : '';
                        $titleNote = isset($title->note->plainText) ? $title->note->plainText : '';
                        $titles[] = array(
                            'titleId' => str_replace('tt', '', $titleId),
                            'titleName' => $titleName,
                            'titleNote' => trim($titleNote, " ()")
                        );
                    }
                }
                
                $this->awards[$eventName][] = array(
                    'awardYear' => $eventEditionYear,
                    'awardWinner' => $awardIsWinner,
                    'awardCategory' => $awardCategory,
                    'awardName' => $awardName,
                    'awardNotes' => $awardNotes,
                    'awardTitles' => $titles,
                    'awardOutcome' => $conclusion
                );
            }
            if ($winnerCount > 0 || $nomineeCount > 0) {
                $this->awards['total'] = array(
                    'win' => $winnerCount,
                    'nom' => $nomineeCount
                );
            }
        }
        return $this->awards;
    }

    #============================================================[ /creditKnownFor ]===
    /** All prestigious title credits for this person
     * @return array creditKnownFor array[0..n] of array[title, titleId, titleYear, titleEndYear, array titleCharacters]
     * @see IMDB person page /credits
     */
    public function creditKnownFor()
    {
        if (empty($this->creditKnownFor)) {
            $query = <<<EOF
query KnownFor(\$id: ID!) {
  name(id: \$id) {
    knownFor(first: 9999) {
      edges {
        node{
          credit {
            title {
              id
              titleText {
                text
              }
              releaseYear {
                year
                endYear
              }
            }
            ... on Cast {
              characters {
                name
              }
            }
          }
        }
      }
    }
  }
}
EOF;
            $data = $this->graphql->query($query, "KnownFor", ["id" => "nm$this->imdbID"]);
            if ($data != null) {
                foreach ($data->name->knownFor->edges as $edge) {
                    $title = isset($edge->node->credit->title->titleText->text) ?
                                   $edge->node->credit->title->titleText->text : '';
                                   
                    $titleId = isset($edge->node->credit->title->id) ?
                                     str_replace('tt', '', $edge->node->credit->title->id) : '';
                                     
                    $titleYear = isset($edge->node->credit->title->releaseYear->year) ?
                                       $edge->node->credit->title->releaseYear->year : null;
                                       
                    $titleEndYear = isset($edge->node->credit->title->releaseYear->endYear) ?
                                          $edge->node->credit->title->releaseYear->endYear : null;
                                        
                    $characters = array();
                    if ($edge->node->credit->characters != null) {
                        foreach ($edge->node->credit->characters as $character) {
                            $characters[] = $character->name;
                        }
                    }
                    $this->creditKnownFor[] = array(
                        'title' => $title,
                        'titleId' => $titleId,
                        'titleYear' => $titleYear,
                        'titleEndYear' => $titleEndYear,
                        'titleCharacters' => $characters
                    );
                }
            } else {
                return $this->creditKnownFor;
            }
        }
        return $this->creditKnownFor;
    }

    #-------------------------------------------------------[ Credits ]---
    /** Get all credits for a person
     * @return array[categoryId] of array('titleId: string, 'titleName: string, titleType: string,
     *      year: int, endYear: int, characters: array(),jobs: array())
     * @see IMDB page /credits
     */
    public function credit()
    {
        // imdb credits category ids to camelCase names
        $categoryIds = array(
            'director' => 'Director',
            'writer' => 'Writer',
            'actress' => 'Actress',
            'actor' => 'Actor',
            'producer' => 'Producer',
            'composer' => 'Composer',
            'cinematographer' => 'Cinematographer',
            'editor' => 'Editor',
            'casting_director' => 'castingDirector',
            'production_designer' => 'productionDesigner',
            'art_director' => 'artDirector',
            'set_decorator' => 'setDecorator',
            'costume_designer' => 'costumeDesigner',
            'make_up_department' => 'makeUpDepartment',
            'production_manager' => 'productionManager',
            'assistant_director' => 'assistantDirector',
            'art_department' => 'artDepartment',
            'sound_department' => 'soundDepartment',
            'special_effects' => 'specialEffects',
            'visual_effects' => 'visualEffects',
            'stunts' => 'Stunts',
            'choreographer' => 'Choreographer',
            'camera_department' => 'cameraDepartment',
            'animation_department' => 'animationDepartment',
            'casting_department' => 'castingDepartment',
            'costume_department' => 'costumeDepartment',
            'editorial_department' => 'editorialDepartment',
            'electrical_department' => 'electricalDepartment',
            'location_management' => 'locationManagement',
            'music_department' => 'musicDepartment',
            'production_department' => 'productionDepartment',
            'script_department' => 'scriptDepartment',
            'transportation_department' => 'transportationDepartment',
            'miscellaneous' => 'Miscellaneous',
            'thanks' => 'Thanks',
            'executive' => 'Executive',
            'legal' => 'Legal',
            'soundtrack' => 'Soundtrack',
            'manager' => 'Manager',
            'assistant' => 'Assistant',
            'talent_agent' => 'talentAgent',
            'self' => 'Self',
            'publicist' => 'Publicist',
            'music_artist' => 'musicArtist',
            'podcaster' => 'Podcaster',
            'archive_footage' => 'archiveFootage',
            'archive_sound' => 'archiveSound',
            'costume_supervisor' => 'costumeSupervisor',
            'hair_stylist' => 'hairStylist',
            'intimacy_coordinator' => 'intimacyCoordinator',
            'make_up_artist' => 'makeUpArtist',
            'music_supervisor' => 'musicSupervisor',
            'property_master' => 'propertyMaster',
            'script_supervisor' => 'scriptSupervisor',
            'showrunner' => 'Showrunner',
            'stunt_coordinator' => 'stuntCoordinator',
            'accountant' => 'Accountant'
        );
        
        if (empty($this->credits)) {
            
            foreach ($categoryIds as $categoryId) {
                $this->credits[$categoryId] = array();
            }
            
            $query = <<<EOF
          category {
            id
          }
          title {
            id
            titleText {
              text
            }
            titleType {
              text
            }
            releaseYear {
              year
              endYear
            }
          }
          ... on Cast {
            characters {
              name
            }
          }
          ... on Crew {
            jobs {
              text
            }
          }
EOF;
            $edges = $this->graphQlGetAll("Credits", "credits", $query);
            foreach ($edges as $edge) {
                $characters = array();
                if (isset($edge->node->characters) && $edge->node->characters != null) {
                    foreach ($edge->node->characters as $character) {
                        $characters[] = $character->name;
                    }
                }
                $jobs = array();
                if (isset($edge->node->jobs) && $edge->node->jobs != null) {
                    foreach ($edge->node->jobs as $job) {
                        $jobs[] = $job->text;
                    }
                }
                $this->credits[$categoryIds[$edge->node->category->id]][] = array(
                    'titleId' => str_replace('tt', '', $edge->node->title->id),
                    'titleName' => $edge->node->title->titleText->text,
                    'titleType' => isset($edge->node->title->titleType->text) ?
                                         $edge->node->title->titleType->text : '',
                    'year' => isset($edge->node->title->releaseYear->year) ?
                                    $edge->node->title->releaseYear->year : null,
                    'endYear' => isset($edge->node->title->releaseYear->endYear) ?
                                       $edge->node->title->releaseYear->endYear : null,
                    'characters' => $characters,
                    'jobs' => $jobs
                );
            }
        }
        return $this->credits;
    }
    
    #========================================================[ Helper functions ]===

    #-----------------------------------------[ Helper for Trivia, Quotes and Trademarks ]---
    /** Parse Trivia, Quotes and Trademarks
     * @param string $name
     * @param array $arrayName
     */
    protected function dataParse($name, $arrayName)
    {
        $query = <<<EOF
query Data(\$id: ID!) {
  name(id: \$id) {
    $name(first: 9999) {
      edges {
        node {
          text {
            plainText
          }
        }
      }
    }
  }
}
EOF;
        $data = $this->graphql->query($query, "Data", ["id" => "nm$this->imdbID"]);
        if ($data != null && $data->name->$name != null) {
            foreach ($data->name->$name->edges as $edge) {
                if (isset($edge->node->text->plainText)) {
                    $arrayName[] = $edge->node->text->plainText;
                }
            }
        }
        return $arrayName;
    }

    #-----------------------------------------[ Helper for children, parents, relatives ]---
    /** Parse children, parents, relatives
     * @param string $name
     *     possible values for $name: CHILDREN, PARENTS, OTHERS
     * @param array $arrayName
     * @return array
     */
    protected function nameDetailsParse($name, $arrayName)
    {
        $query = <<<EOF
query Data(\$id: ID!) {
  name(id: \$id) {
    relations(first: 9999, filter: {relationshipTypes: $name}) {
      edges {
        node {
          relationName {
            name {
              id
              nameText {
                text
              }
              
            }
            nameText
          }
          relationshipType {
            text
          }
        }
      }
    }
  }
}
EOF;
        $data = $this->graphql->query($query, "Data", ["id" => "nm$this->imdbID"]);
        if ($data != null) {
            foreach ($data->name->relations->edges as $edge) {
                if (isset($edge->node->relationName->name->id)) {
                    $relName = $edge->node->relationName->name->nameText->text;
                    $relNameId = str_replace('nm', '', $edge->node->relationName->name->id);
                } else {
                    $relName = $edge->node->relationName->nameText;
                    $relNameId = '';
                }
                $relType = isset($edge->node->relationshipType->text) ? $edge->node->relationshipType->text : '';
                $arrayName[] = array(
                    'imdb' => $relNameId,
                    'name' => $relName,
                    'relType' => $relType
                );
            }
        }
        return $arrayName;
    }

    #-----------------------------------------[ Helper GraphQL Paginated ]---
    /**
     * Get all edges of a field in the name type
     * @param string $queryName The cached query name
     * @param string $fieldName The field on name you want to get
     * @param string $nodeQuery Graphql query that fits inside node { }
     * @param string $filter Add's extra Graphql query filters like categories
     * @return \stdClass[]
     */
    protected function graphQlGetAll($queryName, $fieldName, $nodeQuery, $filter = '')
    {
    
        $query = <<<EOF
query $queryName(\$id: ID!, \$after: ID) {
  name(id: \$id) {
    $fieldName(first: 9999, after: \$after$filter) {
      edges {
        node {
          $nodeQuery
        }
      }
      pageInfo {
        endCursor
        hasNextPage
      }
    }
  }
}
EOF;

        // Results are paginated, so loop until we've got all the data
        $endCursor = null;
        $hasNextPage = true;
        $edges = array();
        while ($hasNextPage) {
            $data = $this->graphql->query($query, $queryName, ["id" => "nm$this->imdbID", "after" => $endCursor]);
            $edges = array_merge($edges, $data->name->{$fieldName}->edges);
            $hasNextPage = $data->name->{$fieldName}->pageInfo->hasNextPage;
            $endCursor = $data->name->{$fieldName}->pageInfo->endCursor;
        }
        return $edges;
    }
}
