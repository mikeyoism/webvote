<?php // -*- coding: utf-8 -*-
require_once '_config.php';
class DbAccess
{
    private $dbConn = null;
    private $dbConnDb2_Eventreg = null;
    protected $NrOfBeersPerCategory = null; //cache for number of beers per category

    function __construct()
    {
    }

    function __destruct()
    {
        if ($this->dbConn != null) {
            mysqli_close($this->dbConn);
        }
        $this->dbConn = 0;

        if ($this->dbConnDb2_Eventreg != null) {
            mysqli_close($this->dbConnDb2_Eventreg);
        }
    }
    private function getConnectionDb2_Eventreg()
    {
        if ($this->dbConnDb2_Eventreg === null) {
            $settings = parse_ini_file('config.ini', true);
            $db = $settings['db2_shbfevent'];

            $this->dbConnDb2_Eventreg = mysqli_connect($db['host'], $db['user'], $db['password'])
                or die('Unable to connect to database: ' . mysqli_connect_error());
            $this->dbConnDb2_Eventreg->select_db($db['database']);
            $this->dbConnDb2_Eventreg->set_charset('utf8');
        }
        return $this->dbConnDb2_Eventreg;
    }

    private function getConnection()
    {
        if ($this->dbConn === null) {
            $settings = parse_ini_file('config.ini', true);
            $db = $settings['db'];

            $this->dbConn = mysqli_connect($db['host'], $db['user'], $db['password'])
                or die('Unable to connect to database: ' . mysqli_connect_error());
            $this->dbConn->select_db($db['database']);
            $this->dbConn->set_charset('utf8');
        }
        return $this->dbConn;
    }


    public function login($user, $password)
    {
        # We make two queries so that we can differentiate between the user
        # not found (or wrong password), and the case where the user has insufficient
        # privileges.
        $sql = 'SELECT id, username FROM votesys_users '
            . 'WHERE username="' . $this->escape($user) . '" '
            . 'AND password=password("' . $this->escape($password) . '")';
        $result = mysqli_query($this->getConnection(), $sql) or die("login: Query failed: $sql");
        if (!($row = $result->fetch_assoc())) {
            return;
        }
        $userId = $row['id'];
        $username = $row['username'];

        $sql = 'SELECT competition, privilegeLevel FROM privileges WHERE user=' . $userId;
        $result = mysqli_query($this->getConnection(), $sql) or die("login: Query failed: $sql");

        $privileges = array();
        while ($row = $result->fetch_assoc()) {
            $privileges[$row['competition']] = $row['privilegeLevel'];
        }

        $_SESSION['username'] = $username;
        $_SESSION['privileges'] = $privileges;

        return;
    }
    //get css theme for competition
    public function getCssCompetitionTheme($competitionId)
    {
        $sql = 'SELECT cssCompetitionTheme FROM competitions WHERE id=' . $this->escape($competitionId);
        $result = $this->getConnection()->query($sql) or die("getCssTheme: Query failed: $sql");

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            return $row['cssCompetitionTheme'];
        } else
            return "";

    }
    //get url for beer style guide
    public function getBeerStyleGuideUrl($competitionId)
    {
        $sql = 'SELECT styleDefinitionsUrl FROM competitions WHERE id=' . $this->escape($competitionId);
        $result = $this->getConnection()->query($sql) or die("getBeerStyleGuideUrl: Query failed: $sql");

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            return $row['styleDefinitionsUrl'];
        } else
            return "";

    }
    //returns array with beer style definitions frp
    public function getBeerStyleGuides($compentionId)
    {
        $url = $this->getBeerStyleGuideUrl($compentionId);
        if (!empty($url)) {
         
            try {
                $json = file_get_contents($url);
                $json = mb_convert_encoding($json,"UTF-8");
            } catch (Exception $e) {
                die('Error no beer styles found from url: ' . $url);
            }
            if (empty($json) || $json === false) {
                die('Error reading beer styles from url: ' . $url);
            }
            $styles = json_decode($json, true);
            if (empty($styles)) {
                die('Error decoding beer styles from url: ' . $url);
            
            }
            return $styles;
        }
        else
            return null;
    }

    public function getCompetition($id)
    {
        $sql = 'SELECT count(*) as c FROM voteCodes WHERE competitionId=' . $this->escape($id);
        $result = $this->getConnection()->query($sql) or die("getCompetition: Query failed: $sql");

        $voteCodeCount = $result->fetch_assoc()['c'];

        $sql = 'SELECT name, openTime, closeTime, testingOpenUntilTime,lastEventRegCache '
            . 'FROM competitions '
            . 'WHERE competitions.id=' . $this->escape($id);
        $result = $this->getConnection()->query($sql) or die("getCompetition: Query failed: $sql");

        if ($result->num_rows != 1)
            die("Invalid competition id." . $id);
        $row = $result->fetch_assoc();

        $openTime = new DateTime($row['openTime']);
        $closeTime = new DateTime($row['closeTime']);

        if (is_null($row['testingOpenUntilTime'])) {
            $testingOpenUntilTime = null;
        } else {
            $testingOpenUntilTime = new DateTime($row['testingOpenUntilTime']);
        }
        if (is_null($row['lastEventRegCache'])) {
            $lastEventRegCache = null;
        } else {
            $lastEventRegCache = new DateTime($row['lastEventRegCache']);
        }
        //get evetreg event_id's corresponding to our $compentionId
        $evenreg_event_ids = $this->getEventReg_EventId($id);
        $ids = array();
        $eventInfo = array();
        foreach ($evenreg_event_ids as $event) {
            array_push($ids, $event['event_id']);
            //store event info in array, by id
            $eventInfo[$event['event_id']] = array('event_name' => $event['event_name'], 'competition' => $event['competition']);
        }

        return array(
            'id' => $id,
            'name' => $row['name'],
            'openTime' => $openTime,
            'closeTime' => $closeTime,
            'testingOpenUntilTime' => $testingOpenUntilTime,
            'lastEventRegCache' => $lastEventRegCache,
            'voteCodeCount' => $voteCodeCount,
            'eventReg_ids' => $ids,
            'eventReg_Info' => $eventInfo,
            

        );
    }


    /*
     * Computes some practical information from the competitino open and close times.
     * This is separate from getting the competition information itself for the benefit of caching.
     */
    public static function calcCompetitionTimes($competition)
    {
        $openTime = $competition['openTime'];
        $closeTime = $competition['closeTime'];
        $testingOpenUntilTime = $competition['testingOpenUntilTime'];

        $now = new DateTime();
        $timeBeforeOpen = date_diff($now, $openTime);
        $timeBeforeClose = date_diff($now, $closeTime);

        if ($testingOpenUntilTime === null) {
            $timeBeforeTestingClose = null;
        } else {
            $timeBeforeTestingClose = date_diff($now, $competition['testingOpenUntilTime']);
        }

        if ($timeBeforeClose->invert) {
            $open = false;
            $timeBeforeClose = NULL;
            $openCloseText = 'Röstningen har stängt.';
            $voteCountStartTime = $openTime;
        } else if ($timeBeforeOpen->invert) {
            $open = true;
            $openCloseText = 'Röstningen stänger om ' . self::formatDateInterval($timeBeforeClose) . '.';
            $voteCountStartTime = $openTime;
        } else if ($timeBeforeTestingClose != null && !$timeBeforeTestingClose->invert) {
            $open = true;
            $openCloseText = 'Röstningen öppen för test och stänger om ' .
                self::formatDateInterval($timeBeforeTestingClose) . '.';
            $timeBeforeClose = $timeBeforeTestingClose;
            $voteCountStartTime = null;
        } else {
            $open = false;
            $openCloseText = 'Röstningen öppnar om ' .
                self::formatDateInterval($timeBeforeOpen) . '.';
            $voteCountStartTime = null;
        }

        return array(
            'openTime' => $openTime,
            'closeTime' => $closeTime,
            'open' => $open,
            'openCloseText' => $openCloseText,
            'timeBeforeOpen' => $timeBeforeOpen,
            'timeBeforeClose' => $timeBeforeClose,
            'voteCountStartTime' => $voteCountStartTime
        );
    }

    public function setCompetitionOpenForTestUntil($competitionId, $time)
    {
        $sql = 'update competitions ' .
            'set testingOpenUntilTime="' . $time->format('Y-m-d G:i:s') . '" ' .
            'where id=' . $this->escape($competitionId);
        $stmt = $this->getConnection()->prepare($sql)
            or die("setCompetitionOpenForTestUntil: prepare failed: $sql");
        $stmt->execute() or die("setCompetitionOpenForTestUntil: Execute failed: $sql");
    }




    //get eventreg event_id and info corresponding to our $compentionId
    public function getEventReg_EventId($competitionId)
    {
        $sql = 'SELECT event_id, event_name,comp FROM Events WHERE deleted= 0 AND votesys_competition_id=' . $this->escape($competitionId);
        $result = $this->getConnectionDb2_Eventreg()->query($sql) or die("getEventId: Query failed: $sql");
        if ($result->num_rows < 1)
            die("Invalid eventreg competition id." . $competitionId . " num_rows: " . $result->num_rows);

        //return list of event_ids (typiskt FV + etikett tävling)
        $events = array();
        while ($row = $result->fetch_assoc()) {
            array_push($events, array('event_id' => $row['event_id'], 'event_name' => $row['event_name'], 'competition' => $row['comp'])); //comp = FV, ET, DT
        }
        return $events;
    }


    //get beer info from eventreg database, for a single beerEntryId
    public function getBeer($competitionId, $beerEntryId)
    {
        $events = $this->getEventReg_EventId($competitionId);
        $evenreg_event_ids = array();
        foreach ($events as $event) {
            array_push($evenreg_event_ids, $event['event_id']);
        }
        if (count($evenreg_event_ids) < 1)
            die("Invalid eventreg,getBeers, competition id." . $competitionId + " num_rows: " + count($evenreg_event_ids));
        $result = $this->getConnectionDb2_Eventreg()->query("SET SESSION SQL_BIG_SELECTS=1") or die("getBeers: no big select support in database.");

        $sql = 'SELECT Beers_in_event.event_id,Beers.beer_id, Beers.user_id, Beer_data.beer_name, Beer_data.main_class, Beer_data.sub_class, Beer_data.type_name, ' .
            'Beer_data.low_alc, Beer_data.og, Beer_data.fg, Beer_data.bu, Beer_data.alc,User_data.name,Beers_in_event.fv_competition_no  FROM Beers ' .
            'INNER JOIN Beer_data USING (beer_id) INNER JOIN Beers_in_event USING (beer_id) ' .
            'INNER JOIN Users USING (user_id) INNER JOIN User_data USING (user_id) ' .
            'WHERE Beers_in_event.fv_competition_no =' . $beerEntryId . ' AND Beers_in_event.event_id IN (' . implode(',', $evenreg_event_ids) . ') ' .
            'AND Beers_in_event.deleted = 0 ' .
            'AND Beer_data.deleted = 0 AND Beers.deleted = 0 ' .
            'AND Users.deleted = 0 AND User_data.deleted = 0 ' .
            'ORDER BY Beer_data.main_class, Beer_data.sub_class ASC';
        ;

        $result = $this->getConnectionDb2_Eventreg()->query($sql) or die("getBeer: Query failed: $sql");

        if ($result->num_rows != 1)
            die("Invalid getBeer result, har ölen tilldelats FV-nr i eventdatasen? id=" . $beerEntryId . " num_rows: " . $result->num_rows);//. " sql: " . $sql);

        $row = $result->fetch_assoc();
        $fv_class_id_nr = FvClass($row['low_alc'], $row['main_class'], $row['sub_class']);
        if ($fv_class_id_nr == 0)
            die("FvClass, Incompatible class id. for main:sub class" . $row['main_class'] . ":" . $row['sub_class'] . " low_alc:" . $row['low_alc'] . " beer_id:" . $row['beer_id'] . " beer_name:" . $row['beer_name'] . " beerEntryId:" . $beerEntryId);
        $classOffset = CONST_SETTING_CATEGORY_OFFSET - 1;
        $classIndex = $classOffset + $fv_class_id_nr;
        $nextEntryId = 1;
        $classes = $this->getCategories($competitionId, true);
        $class = array_values(array_filter($classes, function ($var) use ($classIndex) {
            return ($var['id'] == $classIndex);
        }));
        if (!array_key_exists(0, $class))
            die("Invalid class id." . $classIndex);
        //find the class name in $classes by array key Id


        //extract style name from type_name (format main_class.sub_class style_name)
        $styleName = str_replace($row['main_class'] . ':' . $row['sub_class'] . ' ', '', $row['type_name']);



        $entryId = $nextEntryId++;
        $sql2 =
            'SELECT Brewers_of_beer.brewer_id, Brewers.brewer_name FROM Brewers_of_beer ' .
            'INNER JOIN Brewers USING (brewer_id) ' .
            'WHERE Brewers_of_beer.beer_id =' . $row['beer_id'] . ' ' .
            'AND Brewers_of_beer.deleted = 0';
        $result2 = $this->getConnectionDb2_Eventreg()->query($sql2) or die("getBeers: Query failed: $sql2");
        $i = 0;
        $brewer_names = "";
        while ($row2 = mysqli_fetch_array($result2)) {
            if ($i > 0) {
                $brewer_names = $brewer_names . ", " . $row2['brewer_name'];
            } else {
                $brewer_names = $row2['brewer_name'];
            }
            $i++;
        }

        $beer = array(
            'class' => $class[0]['id'], //must always match id in categories/competitions
            'entry_id' => $entryId,
            'entry_code' => $row['fv_competition_no'],
            'styleId' => $row['main_class'] . $row['sub_class'],
            'styleName' => $styleName,
            'name' => $row['beer_name'],
            'brewer' => $brewer_names,
            'alk' => $row['alc'],
            'OG' => $row['og'],
            'FG' => $row['fg'],
            'IBU' => $row['bu']
        );

        return $beer;
    }


    //get beer info from eventreg database
    public function getBeers($competitionId, $grouped = false)
    {

        $events = $this->getEventReg_EventId($competitionId);
        $evenreg_event_ids = array();
        foreach ($events as $event) {
            array_push($evenreg_event_ids, $event['event_id']);
        }
        if (count($evenreg_event_ids) < 1)
            die("Invalid eventreg,getBeers, competition id." . $competitionId + " num_rows: " + count($evenreg_event_ids));
        //set big selects - one.com requires this, not oderlan (we should probably improve the sql query instead of using this setting, atm the query is as in the eventreg code)
        $result = $this->getConnectionDb2_Eventreg()->query("SET SESSION SQL_BIG_SELECTS=1") or die("getBeers: no big select support in database.");
        //get all beers in the competition
        $sql = 'SELECT Beers_in_event.event_id,Beers.beer_id, Beers.user_id, Beer_data.beer_name, Beer_data.main_class, Beer_data.sub_class, Beer_data.type_name, ' .
            'Beer_data.low_alc, Beer_data.og, Beer_data.fg, Beer_data.bu, Beer_data.alc,User_data.name,Beers_in_event.fv_competition_no  FROM Beers ' .
            'INNER JOIN Beer_data USING (beer_id) INNER JOIN Beers_in_event USING (beer_id) ' .
            'INNER JOIN Users USING (user_id) INNER JOIN User_data USING (user_id) ' .

            'WHERE Beers_in_event.event_id IN (' . implode(',', $evenreg_event_ids) . ') ' .
            'AND Beers_in_event.deleted = 0 ' .
            'AND Beer_data.deleted = 0 AND Beers.deleted = 0 ' .
            'AND Users.deleted = 0 AND User_data.deleted = 0 ' .
            'ORDER BY Beer_data.main_class, Beer_data.sub_class ASC';
        ;
        //printf($sql);
        $result = $this->getConnectionDb2_Eventreg()->query($sql) or die("getBeers: Query failed: $sql");

        //the result is grouped by main_class, so we need to create a new array to store the beers
        if ($result->num_rows < 1)
            die("Invalid getBeers result id=" . $competitionId . " num_rows: " . $result->num_rows . " sql: " . $sql . " event_ids: " . implode(',', $evenreg_event_ids));


        //get all brewers participating in this brew from Brewers_of_beer (user_data.name is just the registering brewer))


        $classes = $this->getCategories($competitionId, true);
        $beers = array();
        $nextEntryId = 1;
        $classOffset = CONST_SETTING_CATEGORY_OFFSET - 1;
        $classIndex = 0;
        while ($row = $result->fetch_assoc()) {
            //store beer info in arrays grouped by FvMainClass
            if ($row['fv_competition_no'] >= 900) { //etikett tävling (övriga fält lika som för ditto öl) //TODO improve this with more info than just number via $evenreg_event_ids ("ET","FV")
                $fv_class_id_nr = 8;
                $classIndex = $classOffset + $fv_class_id_nr + 1;
            } else {
                $fv_class_id_nr = FvClass($row['low_alc'], $row['main_class'], $row['sub_class']);
                if ($fv_class_id_nr == 0)
                    die("FvClass, Incompatible class id. for main:sub class" . $row['main_class'] . ":" . $row['sub_class'] . " low_alc:" . $row['low_alc'] . " beer_id:" . $row['beer_id'] . " beer_name:" . $row['beer_name']);
                $classIndex = $classOffset + $fv_class_id_nr;
            }


            //find classes.id in $classes
            $class = array_values(array_filter($classes, function ($var) use ($classIndex) {
                return ($var['id'] == $classIndex);
            }));

            if (!array_key_exists(0, $class))
                die("Invalid class id." . $classIndex);
            //find the class name in $classes by array key Id


            //extract style name from type_name (format main_class.sub_class style_name)
            $styleName = str_replace($row['main_class'] . ':' . $row['sub_class'] . ' ', '', $row['type_name']);

            $entryId = $nextEntryId++;

            $sql2 =
                'SELECT Brewers_of_beer.brewer_id, Brewers.brewer_name FROM Brewers_of_beer ' .
                'INNER JOIN Brewers USING (brewer_id) ' .
                'WHERE Brewers_of_beer.beer_id =' . $row['beer_id'] . ' ' .
                'AND Brewers_of_beer.deleted = 0';
            $result2 = $this->getConnectionDb2_Eventreg()->query($sql2) or die("getBeers: Query failed: $sql2");
            $i = 0;
            $brewer_names = "";
            while ($row2 = mysqli_fetch_array($result2)) {
                if ($i > 0) {
                    $brewer_names = $brewer_names . ", " . $row2['brewer_name'];
                } else {
                    $brewer_names = $row2['brewer_name'];
                }
                $i++;
            }

            $beer = array(
                'class' => $class[0]['id'], //must always match id in categories/competitions
                'entry_id' => $entryId,
                'entry_code' => $row['fv_competition_no'],
                'styleId' => $row['main_class'] . $row['sub_class'],
                'styleName' => $styleName,
                'name' => $row['beer_name'],
                'brewer' => $brewer_names,
                'alk' => $row['alc'],
                'OG' => $row['og'],
                'FG' => $row['fg'],
                'IBU' => $row['bu']
            );
            //note: no offset in arrayposition, as we use the entryId as key
            if ($grouped == true) {
                if (!array_key_exists($fv_class_id_nr, $beers)) {
                    $beers[$fv_class_id_nr] = array();
                }

                array_push($beers[$fv_class_id_nr], $beer);
            } else {

                $beers[$entryId] = $beer;
            }
        }

        return $beers;
    }

    public function getCategories($competitionId, $NoEntries = false)
    {
        $sql = 'select id, name, description from categories ' .
            'where competitionId="' . $this->escape($competitionId) . '"';
        $result = $this->getConnection()->query($sql) or die("getCategories: Query failed: $sql");

        $categories = array();
        $useOffset = false; //CONNECT_EVENTREG_DB > 1 ? true : false;
        $idStartPos = 0;

        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $name = $row['name'];
            $description = $row['description'];

            //when we don't want to get entries, we can skip the entries query
            //(ie rating-mode where entries are fetched separately fram the eventreg database)
            if ($NoEntries) {
                $entries = array();
            } else {
                $sql = "select entryCode from entries where categoryId=$id order by entryCode";
                $result2 = $this->getConnection()->query($sql) or die("getCategories: Query failed: $sql");
                $entries = array_map(function ($a) {
                    return $a[0];
                }, $result2->fetch_all());
            }
            $idStartPos = $useOffset ? $id : $idStartPos;
            $categories[$idStartPos] = array(
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'entries' => $entries
            );
            $idStartPos++;
            // Notice there is also color field that could be filled in if added to the database.
        }

        return $categories;
    }

    public function addCategoryEntries($categoryId, $codes)
    {
        $catId = $this->escape($categoryId);
        $sql = 'INSERT IGNORE INTO entries '
            . 'VALUES '
            . implode(
                ',',
                array_map(
                    function ($c) use ($catId) {
                        return "(null, $catId, $c)";
                    },
                    $codes
                )
            );
        $stmt = $this->getConnection()->prepare($sql) or die("addCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die("addCategoryEntries: Execute failed: $sql");
    }

    public function removeCategoryEntries($categoryId, $codes)
    {
        $catId = $this->escape($categoryId);
        $sql = "DELETE FROM entries WHERE categoryId=$catId AND entryCode IN ("
            . implode(',', $codes) . ')';
        $stmt = $this->getConnection()->prepare($sql) or die("removeCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die("removeCategoryEntries: Execute failed: $sql");
    }

    // Might change in future -- probably hard coded at some other places too right now.
    public function getVoteWeightAndLabels($categoryId)
    {
        $sql = 'SELECT label, weight FROM vote_weights_and_labels ' .
            'WHERE category=' . $this->escape($categoryId) . ' ' .
            'ORDER BY weight DESC';
        $result = $this->getConnection()->query($sql) or die("getVoteWeightAndLabels: Query failed: $sql");

        $x = array();
        while ($row = $result->fetch_assoc()) {
            $x[$row['label']] = $row['weight'];
        }

        if (count($x) > 0) {
            return $x;
        } else {
            return array('Guld' => 3, 'Silver' => 2, 'Brons' => 1);
        }
    }

    public function getVoteCodes($competitionId)
    {
        $sql = 'SELECT code FROM voteCodes ' .
            'WHERE competitionId=' . $this->escape($competitionId) . ' ' .
            'ORDER BY code';
        $result = $this->getConnection()->query($sql) or die("getVoteCodes: Query failed: $sql");

        $array = $result->fetch_all(MYSQLI_NUM);

        return array_map(function ($a) {
            return $a[0];
        }, $array);
    }

    public function insertVoteCodes($competitionId, $codes)
    {
        $compId = $this->escape($competitionId);
        $sql = 'INSERT IGNORE INTO voteCodes (id, competitionId, code) '
            . 'VALUES '
            . implode(
                ',',
                array_map(
                    function ($c) use ($compId) {
                        return "(null, $compId, '$c')";
                    },
                    $codes
                )
            );
        $stmt = $this->getConnection()->prepare($sql) or die("insertVoteCodes: prepare failed: $sql");
        $stmt->execute() or die("insertVoteCodes: Execute failed: $sql");
    }

    public function checkVoteCode($competitionId, $voteCode)
    {
        $voteCode = strtoupper($voteCode);
        $voteCode = trim($voteCode);

        $sql = 'SELECT id FROM voteCodes '
            . 'WHERE competitionId=' . $this->escape($competitionId) . ' '
            . '  AND code="' . $this->escape($voteCode) . '"';

        $result = $this->getConnection()->query($sql) or die("checkVoteCode: Query failed: $sql");
        if ($result->num_rows != 1) {
            return 0;
        }

        $id = $result->fetch_assoc()['id'];
        return $id;
    }

    public function insertVote($voteCodeId, $categoryId, $votes, $validateBeerEntryId = false)
    {
        $vote1 = !array_key_exists('1', $votes) || $votes['1'] === null ? 'null' : $votes['1'];
        $vote2 = !array_key_exists('2', $votes) || $votes['2'] === null ? 'null' : $votes['2'];
        $vote3 = !array_key_exists('3', $votes) || $votes['3'] === null ? 'null' : $votes['3'];

        //validate beerEntryId against eventreg database
        //(not neccessary for rating, as the app will/should only send valid beerEntryId's)
        if ($validateBeerEntryId) {
            $entries = array();
            $beer = null;
            if ($vote1 != "null") {
                $beer = $this->getBeer(getCompetitionId(), $vote1);
                if ($beer === null)
                    return array(-1, "ogiltig röst, " . $vote1 . " är inte ett giltigt tävlingsnummer");
            }
            $beer2 = null;
            if ($vote2 != "null") {
                $beer2 = $this->getBeer(getCompetitionId(), $vote2);
                if ($beer2 === null)
                    return array(-1, "ogiltig röst, " . $vote2 . " är inte ett giltigt tävlingsnummer");
            }
            $beer3 = null;
            if ($vote3 != "null") {
                $beer3 = $this->getBeer(getCompetitionId(), $vote3);
                if ($beer3 === null)
                    return array(-1, "ogiltig röst, " . $vote3 . " är inte ett giltigt tävlingsnummer");
            }
            //validate that the beer is in the correct category
            if ($beer != null && $beer['class'] != $categoryId)
                return array(-1, "ogiltig röst, " . $vote1 . " är inte ett giltigt tävlingsnummer i denna kategori");
            else if ($vote1 != "null")
                array_push($entries, $vote1);
            if ($beer2 != null && $beer2['class'] != $categoryId)
                return array(-1, "ogiltig röst, " . $vote2 . " är inte ett giltigt tävlingsnummer i denna kategori");
            else if ($vote2 != "null")
                array_push($entries, $vote2);
            if ($beer3 != null && $beer3['class'] != $categoryId)
                return array(-1, "ogiltig röst, " . $vote3 . " är inte ett giltigt tävlingsnummer i denna kategori");
            else if ($vote3 != "null")
                array_push($entries, $vote3);

            //for backwards compatibility, we add the entries to the entries table, to maintain referential integrity
            //insert or update entryCodes in entries table - on the fly
            $this->addCategoryEntries($categoryId, $entries);
        }



        $sql = 'insert into votes ' .
            '(id, votecodeId, categoryId, vote1, vote2, vote3, votingMethod, creationTime) ' .
            'values (null, ' . $voteCodeId . ', ' .
            $this->escape($categoryId) . ', ' . $vote1 . ', ' . $vote2 . ', ' . $vote3 . ', "web", null)';

        $stmt = $this->getConnection()->prepare($sql) or die("insertVote: prepare failed: $sql");
        $stmt->execute() or die("insertVote: Execute failed: $sql");
    }

    //store rating (insert or update), returns false on failure (true on success, or if rating is unchanged)
    public function storeRating($voteCodeId, $categoryId, $beerEntryId, $ratingScore, $ratingComment, $drankCheck, $validateBeerEntryId = false)
    {
        //validate beerEntryId against eventreg database
        //(not neccessary for rating, as the app will/should only send valid beerEntryId's)
        if ($validateBeerEntryId) {

            $beer = $this->getBeer(getCompetitionId(), $beerEntryId);
            if ($beer === null) {
                return array(-1, "ogiltig röst, " . $beerEntryId . " är inte ett giltigt tävlingsnummer");
            }
            //validate that the beer is in the correct category
            if ($beer['class'] != $categoryId) {
                return array(-1, "ogiltig röst, " . $beerEntryId . " är inte ett giltigt tävlingsnummer i denna kategori");
            }
        }

        $sql = 'select id,ratingScore,ratingComment,drankCheck from ratings where voteCodeId=' . $voteCodeId . ' and categoryId=' . $categoryId . ' and beerEntryId=' . $beerEntryId;
        $result = $this->getConnection()->query($sql) or die("storeRating: Query failed: $sql");

        if ($result->num_rows == 0) {

            if ($this->insertRating($voteCodeId, $categoryId, $beerEntryId, $ratingScore, $ratingComment, $drankCheck) === true)
                return array(1, "OK!");
        } else {
            $row = $result->fetch_assoc();
            //update rating if it has changed
            if ($row['ratingScore'] != $ratingScore || $row['ratingComment'] != $ratingComment || $row['drankCheck'] != $drankCheck) {

                $ratingScore = $ratingScore === null ? 'null' : $ratingScore;
                $drankCheck = $drankCheck === null ? 'null' : $drankCheck;
                $ratingComment = $ratingComment === null ? 'null' : "'" . $ratingComment . "'";

                $sql = 'update ratings set ratingScore=' . $this->escape($ratingScore) . ', ratingComment=' . $ratingComment . ', drankCheck=' . $this->escape($drankCheck) . ' where id=' . $row['id'];
                $stmt = $this->getConnection()->prepare($sql) or die("storeRating: prepare failed: $sql");
                $stmt->execute() or die("storeRating: Execute failed: $sql");
            }
        }
        return array(1, "OK!");
    }
    //insert new rating
    protected function insertRating($voteCodeId, $categoryId, $beerEntryId, $ratingScore, $ratingComment, $drankCheck)
    {
        $ratingScore = $ratingScore === null ? 'null' : $ratingScore;
        $drankCheck = $drankCheck === null ? 'null' : $drankCheck;
        $ratingComment = $ratingComment === null ? 'null' : "'" . $ratingComment . "'";

        $sql = 'insert into ratings ' .
            '(id, voteCodeId, categoryId, beerEntryId, ratingScore, ratingComment, drankCheck,ratingMethod, creationTime) ' .
            'values (null, ' . $voteCodeId . ', ' .
            $this->escape($categoryId) . ', ' . $this->escape($beerEntryId) . ', ' . $this->escape($ratingScore) . ', ' . $ratingComment . ', ' . $this->escape($drankCheck) . ', "web", null)';

        $stmt = $this->getConnection()->prepare($sql) or die("insertRating: prepare failed: $sql");
        $stmt->execute() or die("insertRating: Execute failed: $sql");
        return true;
    }
    public function getVotes($competitionId, $voteCodeId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select C.id categoryId, T.vote1, T.vote2, T.vote3 ' .
            '  from categories C ' .
            '  left outer join '
            . '(select * from votes where id in ' .
            '      ( select max(votes.id) id ' .
            '         from votes join categories on votes.categoryId=categories.id ' .
            "         where categories.competitionId=$competitionId " .
            "         and voteCodeId=$voteCodeId $extraCond " .
            '         group by categoryId ) ' .
            '   ) T' .
            '  on C.id=T.categoryId ' .
            "  where C.competitionId=$competitionId";

        $result = $this->getConnection()->query($sql) or die("getVotes: Query failed: $sql");

        $votes = array();
        while ($row = $result->fetch_assoc()) {
            $votes[$row['categoryId']] =
                array('1' => $row['vote1'], '2' => $row['vote2'], '3' => $row['vote3']);
        }
        return $votes;
    }
    //get ratings
    public function getRatings($competitionId, $voteCodeId, $voteCountStartTime = null)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select C.id categoryId, T.ratingScore, T.ratingComment, T.drankCheck, T.beerEntryId ' .
            '  from categories C ' .
            '  left outer join '
            . '(select * from ratings where id in ' .
            '      ( select ratings.id id ' .
            '         from ratings join categories on ratings.categoryId=categories.id ' .
            "         where categories.competitionId=$competitionId " .
            "         and voteCodeId=$voteCodeId $extraCond " .
            '         group by categoryId ) ' .
            '   ) T' .
            '  on C.id=T.categoryId ' .
            "  where C.competitionId=$competitionId";

        $result = $this->getConnection()->query($sql) or die("getRatings: Query failed: $sql");

        $ratings = array();

        while ($row = $result->fetch_assoc()) {

            //create lists of ratings grouped by key = categoryId
            if (!array_key_exists($row['categoryId'], $ratings)) {
                $ratings[$row['categoryId']] = array();
            }
            array_push($ratings[$row['categoryId']], array('categoryId' => $row['categoryId'], 'beerEntryId' => $row['beerEntryId'], 'drankCheck' => $row['drankCheck'], 'ratingScore' => $row['ratingScore'], 'ratingComment' => $row['ratingComment'] !== null ? htmlspecialchars_decode($row['ratingComment']) : null));
        }



        return $ratings;
    }

    public function getVoteResult($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select vote1, vote2, vote3 ' .
            '  from votes V ' .
            '  join ( select max(id) id from votes where categoryId=' .
            $this->escape($categoryId) . $extraCond . ' group by voteCodeId ) T ' .
            '  on V.id=T.id';
        $result = $this->getConnection()->query($sql) or die("getVoteResults: Query failed: $sql");

        $voteCounts = array();
        while ($row = $result->fetch_assoc()) {
            $this->addVote($voteCounts, 'vote1', $row['vote1']);
            $this->addVote($voteCounts, 'vote2', $row['vote2']);
            $this->addVote($voteCounts, 'vote3', $row['vote3']);
        }

        foreach ($voteCounts as &$voteCount) {
            $voteCount['points'] = $voteCount['vote1'] * 3 + $voteCount['vote2'] * 2 + $voteCount['vote3'];
        }

        usort($voteCounts, function ($a, $b) {
            $diff = $b['points'] - $a['points'];
            if ($diff != 0) {
                return $diff;
            }
            $diff = $b['vote1'] - $a['vote1'];
            if ($diff != 0) {
                return $diff;
            }
            $diff = $b['vote2'] - $a['vote2'];
            return $diff;
        });

        return $voteCounts;
    }

    private function addVote(&$voteCounts, $level, $vote)
    {
        if (!is_null($vote)) {
            if (array_key_exists($vote, $voteCounts)) {
                $count = $voteCounts[$vote];
            } else {
                $count = array('entryId' => $vote, 'vote1' => 0, 'vote2' => 0, 'vote3' => 0);
            }
            $count[$level]++;
            $voteCounts[$vote] = $count;
        }
    }


    //get weigthed rating results sorted by calculated total points for each beerEntryId
    public function getRatingResultTot($categoryId, $voteCountStartTime)
    {
        $competitionId = getCompetitionId();
        //get number of unique beerEntryId:s for all categories (once)


        $beerCountforCategory = $this->getBeerCountForCategory($categoryId);
        $ratingResults = array();
        if ($beerCountforCategory > 0) {
            $weightedScores = $this->getWeightedRatingResult($categoryId, $voteCountStartTime, $beerCountforCategory);

            foreach ($weightedScores as $voteCodeId => $scores) {
                foreach ($scores as $beerEntryId => $score) {
                    if ($beerEntryId == 'VoteCodeStat') {
                        continue;
                    }
                    if (!array_key_exists($beerEntryId, $ratingResults)) {
                        $ratingResults[$beerEntryId] = array('beerEntryId' => $beerEntryId, 'ratingScore' => 0, 'weightedScore' => 0, 'weightedScoreNorm' => 0, 'votersCount' => 0, 'weightedMeanValue' => 0);
                    }
                    $ratingResults[$beerEntryId]['ratingScore'] += $score['ratingScore'];
                    $ratingResults[$beerEntryId]['weightedScore'] += $score['weightedScore'];
                    $ratingResults[$beerEntryId]['weightedScoreNorm'] += $score['weightedScoreNorm'];
                    $ratingResults[$beerEntryId]['votersCount']++;
                    //Grab beername, brewer and style from eventreg database

                    $beer = $this->getBeer($competitionId, $beerEntryId);
                    $ratingResults[$beerEntryId]['beerName'] = $beer['name'];
                    $ratingResults[$beerEntryId]['brewer'] = $beer['brewer'];
                    $ratingResults[$beerEntryId]['styleName'] = $beer['styleName'];
                    $ratingResults[$beerEntryId]['styleId'] = $beer['styleId'];
                }
            }

            //Medelbetyg för alla som röstat på denna öl
            //obs enbart statistik, används ej i rankingen, då ett fåtal höga betyg kan ge en hög medelpoäng
            foreach ($ratingResults as &$ratingResult) {
                $ratingResult['weightedMeanValue'] = round($ratingResult['weightedScore'] / $ratingResult['votersCount'], 3);
            }


            //sort the array by weightedScore (instead of beerEntryId)
            usort($ratingResults, function ($a, $b) {
                $sort = $b['weightedScore'] - $a['weightedScore'];
                //utjämningsregler vid lika poäng
                if ($sort == 0)
                    $sort = $b['votersCount'] - $a['votersCount'];
                if ($sort == 0)
                    $sort = $b['ratingScore'] - $a['ratingScore'];
                return $sort;
            });
        }
        return $ratingResults;
    }


    //get weigted results
    public function getWeightedRatingResult($categoryId, $voteCountStartTime, $beerCountforCategory)
    {
        //reglerna i denna funktion är till för att ta udden av eventuellt taktikröstande, 
        //och ge en rättvis tävling där alla besökare propotionerligt kan påverka resultatet ungefär likvärdigt, betyg översätts  med en jämn fördelning av poäng.
        //tanken är att en vanlig besökare ska uppmuntaras att betygsätta många öl utifrån smak och tycke, liknande untappd.
        //besökaren ska inte få upplevelsen att hen sitter i en jury som ska utse vinnare 
        //och därmed föröker hitta ett fåtal favoriter att betygsätta, eller tvärt om drar sig för att betygsätta alls eftersom denne druckit för få öl i klassen för kunna "rösta rättvist"





        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }



        //each voteCodeId is a unique voter (participating user)
        //get a list of all unique voteCodeId:s for this $categoryId
        $sql = 'select distinct voteCodeId from ratings where categoryId=' . $this->escape($categoryId) . $extraCond;
        $result = $this->getConnection()->query($sql) or die("getRatingResults: Query failed: $sql");
        $voteCodeIds = array();
        while ($row = $result->fetch_assoc()) {
            array_push($voteCodeIds, $row['voteCodeId']);
        }
        $voteCodeCount = count($voteCodeIds);
        //for each voteCodeId, get all ratingScores and beerEntryId sorted by ratingScore
        $weightedScores = array();
        foreach ($voteCodeIds as $voteCodeId) {
            $sql = 'select ratingScore, drankCheck, beerEntryId ' .
                '  from ratings where categoryId=' . $this->escape($categoryId) . ' and ratingscore>0 and voteCodeId=' . $voteCodeId . $extraCond . ' order by ratingScore desc';
            $result = $this->getConnection()->query($sql) or die("getRatingResults: Query failed: $sql");
            //Create a list of ratingScores  and calculated WeigthedScores for this voteCodeId (voter)
            //ratingScore is a number between 1 and 5 for each beerEntryId
            //a ratingScore score of 5 is given to the highest rated beer(s), 4 to the second highest etc.

            $ratingCount = 0;

            $weightedScoreCount = 0;
            //get rowcount in $result
            $ratingCount = mysqli_num_rows($result);


            $weightedScores[$voteCodeId] = array();
            $ratingScoreCount = array();
            for ($i = 0; $i <= 5; $i++) { //pos zero is for null values (not rated at all)
                $ratingScoreCount[$i] = 0;
            }

            while ($row = $result->fetch_assoc()) {
                //insert or update weigthedScore for this beerEntryId 
                $weightedScoreCount++;
                //count number of equal ratingScores for each voteCodeId
                //add these to the array $ratingScoreCount
                if (!isset($row['ratingScore']))
                    $ratingScoreCount[0]++;
                else if (array_key_exists($row['ratingScore'], $ratingScoreCount)) {
                    $ratingScoreCount[$row['ratingScore']]++;
                }

                //initialize weightedScore to ratingScore
                if (isset($row['ratingScore'])) { //null are common (drankcheck's) and should not be counted/listed at all
                    if ($row['ratingScore'] == 0) {
                        $weightedScore = 0;
                    } else {
                        $weightedScore = $row['ratingScore']; //initialize to ratingScore
                    }

                    $weightedScores[$voteCodeId][$row['beerEntryId']] = array('beerEntryId' => $row['beerEntryId'], 'ratingScore' => $row['ratingScore'], 'weightedScore' => $weightedScore);
                }
            }
            //add zero values to $ratingScoreCount for ratingScores not present in the $result
            //to get accuarate median value
            $ratingScoreCount[0] = $beerCountforCategory - $weightedScoreCount;
            //store to $weightedScores
            $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCounts'] = $ratingScoreCount;

            //calcualte the median value in $ratingScoreCount, for detecting unusual voting patterns

            $ratingScoreSum = 0;
            $ScoreCountsSum = array_sum($ratingScoreCount);
            $ratingScoreMedian = 0;
            $ratingScoreMedianPos = $ScoreCountsSum / 2;
            $ScoreCountsSum = 0;
            foreach ($ratingScoreCount as $ratingScore => $count) {
                $ScoreCountsSum += $count;
                if ($ScoreCountsSum >= $ratingScoreMedianPos) {
                    $ratingScoreMedian = $ratingScore;
                    break;
                }
            }
            $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMedian'] = $ratingScoreMedian;
            $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCountsSum'] = $ScoreCountsSum;


            //calulate mean value for ratingScores for this voteCodeId, for beers rated.
            $ratingScoreSum = 0;
            $ratingScoreCount = 0;
            $ratedScoreCount = 0;
            foreach ($weightedScores[$voteCodeId] as $beerEntryId => $score) {
                if ($beerEntryId == 'VoteCodeStat') {
                    continue;
                }
                if ($score['ratingScore'] > 0) {
                    $ratingScoreSum += $score['ratingScore'];
                    $ratingScoreCount++;
                } else
                    $ratedScoreCount++; //integrity check, should be zero

            }

            $weightedScores[$voteCodeId]['VoteCodeStat']['ratedPercent'] = $ratingCount / $beerCountforCategory * 100;
            $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreSum'] = $ratingScoreSum;
            $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMean'] = $ratingScoreCount > 0 ? $ratingScoreSum / $ratingScoreCount : 0;



            //calculate max allowed weightedScore for this voteCodeId (voter)
            //lower weightedMaxScore using the diff betweeen ratingscoreMean and median value

            //note the Median is based on *all* beers in the category, while the mean value is based on the select beers that earned a rating by this voter
            //thus a low diff between the median indicates that the voter has rated a lot of beers (and adjustmenst to the weightedMaxScore is not really needed)
            //thus a high diff between the median and the mean value indicates that the voter has a few high ratings
            //and the weightedMaxScore should be lowered to prevent this voter from skewing the results
            $weightedMaxScore = 5;

            if ($weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMean'] > $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMedian'])
                $diff = $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMean'] - $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMedian'];
            else
                $diff = $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMedian'] - $weightedScores[$voteCodeId]['VoteCodeStat']['ratingScoreMean'];

            $weightedScores[$voteCodeId]['VoteCodeStat']['MeanMedianDiff'] = $diff;
            //count high & low ratings
            $top = $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCounts'][4] + $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCounts'][5];
            $low = $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCounts'][1] + $weightedScores[$voteCodeId]['VoteCodeStat']['ScoreCounts'][2];
            $weightedScores[$voteCodeId]['VoteCodeStat']['top'] = $top;
            $weightedScores[$voteCodeId]['VoteCodeStat']['low'] = $low;

            $weightedScores[$voteCodeId]['VoteCodeStat']['topPercent'] = $top / $beerCountforCategory * 100;
            $weightedScores[$voteCodeId]['VoteCodeStat']['lowPercent'] = $low / $beerCountforCategory * 100;
            $lowtopRatio = $low;
            if ($top > 0)
                $lowtopRatio = $low / $top;

            $weightedScores[$voteCodeId]['VoteCodeStat']['lowTopRatio'] = $lowtopRatio;

            //note: 0.5 is the lowest value allowed (no divide by zero if normalizing), 
            //it's also a way to prevent a single voter from skewing the results too much
            //as it (0.5) kicks in and affects the scaling further below, when the diff is high and weightedMaxScore drops to <=2, 
            //thus
            //it kicks in as floor for a single vote on just one beer in the class,
            //or multiple ratings all with the same rating of 5
            //or multiple ratings on a few beers with a high mean
            $weightedMaxScore = max(0.5, $weightedMaxScore - $diff);
            $weightedScores[$voteCodeId]['VoteCodeStat']['weightedMaxScore'] = $weightedMaxScore;
            $weightedScores[$voteCodeId]['VoteCodeStat']['weightedMaxScoreAdj'] = $weightedMaxScore;


            //produce a reduced range - a lower weightedMaxScoreAdj, for the following cases of skewed ratios /voting patterns

            //extreme edge, less than 10% of ratings, like one 5-star rating, and 1 or 2 1-star rating, ratio 1:1 to 1:2, should not be able to skew the results more than when voting on many beers
            if ($top > 0 && $low > 0 && $ratingCount < $beerCountforCategory * 0.1 && $weightedMaxScore >= 2)
                $weightedScores[$voteCodeId]['VoteCodeStat']['weightedMaxScoreAdj'] /= 2.5;

            //detect a high 1:3+ of low ratings vs high ratings, and reduce the weightedMaxScoreAdj proportionally
            //the extreme edge case is a single voter that rates all beers in the class with a 1-star rating except 1 beer with a 5-star rating, ratio 1:veryhigh
            else if ($top > 0 && $low > 0 && $weightedScores[$voteCodeId]['VoteCodeStat']['lowTopRatio'] >= 3 && $weightedMaxScore >= 2)
                $weightedScores[$voteCodeId]['VoteCodeStat']['weightedMaxScoreAdj'] /= (1.25 + ($weightedScores[$voteCodeId]['VoteCodeStat']['lowTopRatio'] / 12.5));

            $weightedMaxScoreAdj = $weightedScores[$voteCodeId]['VoteCodeStat']['weightedMaxScoreAdj'];


            // Calculate the weighted score for each beerEntryId
            foreach ($weightedScores[$voteCodeId] as $beerEntryId => $score) {
                if ($beerEntryId == 'VoteCodeStat') {
                    continue;
                }
                if ($score['ratingScore'] > 0) {
                    // Scale the ratingScore value into a range between 0 and weightedMaxScoreAdj
                    $scaledRatingScore = $score['ratingScore'] / 5 * $weightedMaxScoreAdj;
                    //normalize (compress) the weightedScore to a range between 1 and weightedMaxScoreAdj+1, so that no beer can get a score < 1
                    $scaledRatingScoreN = $scaledRatingScore * ($weightedMaxScoreAdj - 1) / $weightedMaxScoreAdj + 1;

                    $weightedScores[$voteCodeId][$beerEntryId]['weightedScore'] = $scaledRatingScore;
                    $weightedScores[$voteCodeId][$beerEntryId]['weightedScoreNorm'] = $scaledRatingScoreN;
                    //alternatively we could furhter punish a high lowTopRatio by applying the normalized score here


                }
            }



        }
        //debug output - to evaulate the algorithms above, preferbly run this with 1 voteCodeId
        // if (CONST_SYS_JS_DEBUG){
        //     echo '<pre>';
        //     foreach ($voteCodeIds as $voteCodeId) {

        //     print_r($weightedScores[$voteCodeId]['VoteCodeStat']);

        //     }
        //     echo '</pre>';
        // }        
        return $weightedScores;

    }

    //function that call getBeers, and counts the number of unique beers in each $categoryId
    //returns an array with the number of unique beers in each $categoryId, or the count for a specific $categoryId if param $categoryId is set
    //preferably call this function once with $categoryId = null, and use the returned array for further processing, as getBeers is a bit slow
    protected function getBeerCountsPerCategory($categoryId = null)
    {
        $beers = $this->getBeers(getCompetitionId(), false);
        $categories = $this->getCategories(getCompetitionId(), true);
        $beerCounts = array();
        foreach ($categories as $class) {
            $beerCounts[$class['id']] = 0;
        }
        foreach ($beers as $beer) {
            $beerCounts[$beer['class']]++;
        }
        if ($categoryId !== null) {
            return $beerCounts[$categoryId];
        }
        return $beerCounts;
    }
    public function getBeerCountForCategory($categoryId)
    {
        //chache the result in $this->NrOfBeersPerCategory
        if ($this->NrOfBeersPerCategory == null)
            $this->NrOfBeersPerCategory = $this->getBeerCountsPerCategory(null);

        return $this->NrOfBeersPerCategory[$categoryId];
    }


    //get number of unique beerEntryId:s for this rating  $categoryId
    //note:count rated beers, not unique beers
    public function getBeerEntryCount($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select count(distinct beerEntryId) as c from ratings where categoryId=' . $this->escape($categoryId) . $extraCond;
        $result = $this->getConnection()->query($sql) or die("getBeerEntryCount: Query failed: $sql");
        $beerCount = $result->fetch_assoc()['c'];
        return $beerCount;
    }
    //get number of unique voteCodeId:s for this rating  $categoryId
    public function getVoteCodeCount($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select count(distinct voteCodeId) as c from ratings where categoryId=' . $this->escape($categoryId) . $extraCond;
        $result = $this->getConnection()->query($sql) or die("getVoteCodeCount: Query failed: $sql");
        $voteCodeCount = $result->fetch_assoc()['c'];
        return $voteCodeCount;
    }
    //get number of ratings with a score > 0 for a $categoryId
    public function getRatingCount($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select count(ratingScore) as c from ratings where categoryId=' . $this->escape($categoryId) . ' and ratingScore>0' . $extraCond;
        $result = $this->getConnection()->query($sql) or die("getRatingCount: Query failed: $sql");
        $ratingCount = $result->fetch_assoc()['c'];
        return $ratingCount;
    }
    //get number of drankCheck's for a $categoryId
    public function getDrankCheckCount($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        //note: in a correct db, drankcheck is always 1 if ratingScore>0, but we check both here for safety
        $sql = 'select count(drankCheck) as c from ratings where categoryId=' . $this->escape($categoryId) . ' and (drankCheck=1)' . $extraCond;
        $result = $this->getConnection()->query($sql) or die("getDrankCheckCount: Query failed: $sql");
        $drankCheckCount = $result->fetch_assoc()['c'];
        return $drankCheckCount;
    }

    //cache-lagrar öldata från eventReg, i filer för att undvika att läsa från db varje gång
    //för att garantera prestanada vid större tävlingar..troligtvis overkill men ändå...
    //relevant endast CONNECT_EVENTREG_DB = FALSE, in _config.php
    function writeEventBeerDataToCacheFiles($competitionId)
    {
        $beers = $this->getBeers($competitionId, false);
        $categories = $this->getCategories($competitionId, true);

        //get count of categories and beers
        if (count($categories) == 0 || count($beers) == 0) {
            die('No categories or beers found in EventReg database. Check that the competitionId is correct and that the EventReg database is up and running.');
        }
        //delete old files named list_fv*.txt
        $files = glob('../../rate/data/list_fv*.txt');
        foreach ($files as $file) {
            unlink($file);
        }
        $idx = 1;
        foreach ($categories as $class) {
            $filename = '../../rate/data/list_fv' . $idx++ . '.txt';
            $str = '';
            //write class id, name and description  as header to first line of file
            $str = $class['id'] . "\t " . $class['name'] . "\t" . $class['description'] . "\n";
            //write beers one by one to file
            foreach ($beers as $beer) {
                if ($beer['class'] == $class['id']) {
                    $str .= $beer['entry_code'] . "\t" . $beer['styleId'] . ' - ' . $beer['styleName'] . "\t" . $beer['name'] . "\t" . $beer['brewer'] . "\t" . $beer['alk'] . "\t" . $beer['OG'] . "\t" . $beer['FG'] . "\t" . $beer['IBU'] . "\n";
                }
            }
            file_put_contents($filename, $str);
        }
        //update lastEventRegCache in database
        $sql = 'update competitions set lastEventRegCache=now() where id=' . $competitionId;
        $stmt = $this->getConnection()->prepare($sql) or die("update lastEventRegCache: prepare failed: $sql");
        $stmt->execute() or die("update lastEventRegCache: Execute failed: $sql");


    }
    function readEventBeerDataFromCacheFiles($competitionId)
    {
        //get all files in data folder that matches list_fv*.txt
        $files = glob('../../rate/data/list_fv*.txt');
        $categories = array();
        $beers = array();
        $nextEntryId = 1;
        foreach ($files as $file) {
            $str = file_get_contents($file);
            $lines = explode("\n", trim($str, "\n"));
            $class = explode("\t", $lines[0]);
            //header line contains class id, name and description
            array_push($categories, array('id' => trim($class[0]), 'name' => trim($class[1]), 'description' => trim($class[2])));

            foreach (array_slice($lines, 1) as $line) {
                $cols = explode("\t", $line);

                $styleParts = explode(" - ", $cols[1]);

                $entryId = $nextEntryId++;
                $beer = array(
                    'class' => $class[0],
                    'entry_id' => $entryId,
                    'entry_code' => $cols[0],
                    'styleId' => $styleParts[0],
                    'styleName' => $styleParts[1],
                    'name' => $cols[2],
                    'brewer' => $cols[3],
                    'alk' => $cols[4],
                    'OG' => $cols[5],
                    'FG' => $cols[6],
                    'IBU' => $cols[7]
                );
                $beers[$entryId] = $beer;
            }
        }
        return array($categories, $beers);
    }




    private function escape($s)
    {
        return $this->getConnection()->real_escape_string($s);
    }

    private static function formatDateInterval($diff)
    {
        $ret = $diff->format(' %y år, %m månader, %d dagar, %h timmar, %i minuter, %s sekunder');

        $ret = str_replace(
            array(' 0 år,', ' 0 månader,', ' 0 dagar,', ' 0 timmar,', ' 0 minuter,'),
            ' ',
            $ret
        );

        $ret = str_replace(
            array(' 1 månader,', ' 1 dagar,', ' 1 timmar,', ' 1 minuter', ' 1 sekunder'),
            array(' 1 månad, ', ' 1 dag, ', ' 1 timme, ', ' 1 minut', ' 1 sekund'),
            $ret
        );

        $ret = substr($ret, 1);

        return $ret;
    }
}

function getCompetitionId()
{

    if (!isset($_GET['competitionId'])) {
        if (COMPETITION_ID !== null)
            return COMPETITION_ID;
        else
            die('Missing competition');
    }

    return $_GET['competitionId'];
}

function isLoggedIn()
{
    return isset($_SESSION['privileges']);
}

// Ensures that the session has authenticated with at least the given level.
function requireLoggedIn($competitionId, $minLevel)
{
    if (
        isset($_SESSION['privileges']) &&
        isset($_SESSION['privileges'][$competitionId]) &&
        $_SESSION['privileges'][$competitionId] >= $minLevel
    ) {
        return array($_SESSION['privileges'][$competitionId], $_SESSION['username']);
    } else {
        die('Not authorized.');
    }
}

// Ensures that the session has authenticated with at least the given level.
// If not, the user is redirected to the index page if not logged in at all.
function requireLoggedInOrRedirect($competitionId, $minLevel)
{
    if (!isLoggedIn()) {
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $query = isset($_GET['competitionId']) ? '?competitionId=' . $_GET['competitionId'] : '';
        header("Location: http://$host$uri/$query");
        exit;
    }

    return requireLoggedIn($competitionId, 1);
}

function logout()
{
    unset($_SESSION['privileges']);
    unset($_SESSION['username']);
}

function redirectToSelf()
{
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['PHP_SELF'];
    $query = isset($_GET['competitionId']) ? '?competitionId=' . $_GET['competitionId'] : '';

    header("Location: http://$host$uri$query");
    exit;
}





// Returns FV class as string or numeric based on the main and sub class of the beer
// this is a copy of the function in the eventreg code, *but with returned numerics adjusted to order in the competition table*
//TODO store in database instead 

function FvClass($low_alc, $main_class, $sub_class)
{
    // $classesCurrentCompteition is an array of categories for the current competition, as returned by getCategories
    // if it is null, the function will just fetch default to values defined in this fuction (will match the eventreg database, but not out competition-database!)
    $as_numeric = true;
    if (!empty($low_alc)) {
        $fv_class = !$as_numeric ? 'Folköl' : '8';
    } elseif (($main_class == 1) || ($main_class == 2)) {
        $fv_class = !$as_numeric ? 'Lager och underjäst öl' : '1';
    } elseif (
        (($main_class == 3) && ($sub_class != 'F')) ||
        (($main_class == 4) && ($sub_class == 'A')) ||
        (($main_class == 4) && ($sub_class == 'B')) ||
        (($main_class == 4) && ($sub_class == 'F')) ||
        (($main_class == 4) && ($sub_class == 'G')) ||
        (($main_class == 5) && ($sub_class == 'A')) ||
        (($main_class == 5) && ($sub_class == 'G')) ||
        (($main_class == 5) && ($sub_class == 'H')) ||
        ($main_class == 7) ||
        ($main_class == 8) ||
        (($main_class == 9) && ($sub_class == 'B')) ||
        (($main_class == 9) && ($sub_class == 'E')) ||
        (($main_class == 11) && ($sub_class == 'E')) ||
        (($main_class == 11) && ($sub_class == 'K'))
    ) {
        $fv_class = !$as_numeric ? 'Maltdominerad öl' : '2';
    } elseif (
        (($main_class == 3) && ($sub_class == 'F')) ||
        (($main_class == 4) && ($sub_class == 'C')) ||
        (($main_class == 4) && ($sub_class == 'D')) ||
        (($main_class == 4) && ($sub_class == 'E')) ||
        (($main_class == 5) && ($sub_class == 'B')) ||
        (($main_class == 5) && ($sub_class == 'C')) ||
        (($main_class == 5) && ($sub_class == 'D')) ||
        (($main_class == 5) && ($sub_class == 'E')) ||
        (($main_class == 5) && ($sub_class == 'F')) ||
        (($main_class == 6) && ($sub_class == 'F'))
    ) {
        $fv_class = !$as_numeric ? 'Humledominerad öl' : '3';
    } elseif (
        (($main_class == 6) && ($sub_class != 'F')) ||
        (($main_class == 9) && ($sub_class == 'A')) ||
        (($main_class == 9) && ($sub_class == 'C')) ||
        (($main_class == 9) && ($sub_class == 'D')) ||
        (($main_class == 9) && ($sub_class == 'F')) ||
        (($main_class == 9) && ($sub_class == 'G')) ||
        (($main_class == 9) && ($sub_class == 'H')) ||
        (($main_class == 9) && ($sub_class == 'I')) ||
        (($main_class == 9) && ($sub_class == 'J')) ||
        (($main_class == 9) && ($sub_class == 'K')) ||
        (($main_class == 9) && ($sub_class == 'L'))
    ) {
        $fv_class = !$as_numeric ? 'Jästdominerad öl' : '4';
    } elseif (
        ($main_class == 10) ||
        (($main_class == 11) && ($sub_class == 'C'))
    ) {
        $fv_class = !$as_numeric ? 'Syrligt och spontanjäst öl' : '5';
    } elseif (
        (($main_class == 11) && ($sub_class == 'A')) ||
        (($main_class == 11) && ($sub_class == 'B')) ||
        (($main_class == 11) && ($sub_class == 'D')) ||
        (($main_class == 11) && ($sub_class == 'F')) ||
        (($main_class == 11) && ($sub_class == 'G')) ||
        (($main_class == 11) && ($sub_class == 'H')) ||
        (($main_class == 11) && ($sub_class == 'I')) ||
        (($main_class == 11) && ($sub_class == 'J')) ||
        (($main_class == 11) && ($sub_class == 'L'))
    ) {
        $fv_class = !$as_numeric ? 'Övriga öl' : '6';
    } elseif (($main_class == 12)) {
        $fv_class = !$as_numeric ? 'Cider och mjöd' : '7';
    } else {
        $fv_class = !$as_numeric ? 'Okänd' : '0';
    }

    return $fv_class;
}
