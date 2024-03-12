<?php // -*- coding: utf-8 -*-
require_once '_config.php';
class DbAccess
{
    private $dbConn = null;
    private $dbConnDb2_Eventreg = null;

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

    public function getCompetition($id)
    {
        $sql = 'SELECT count(*) as c FROM voteCodes WHERE competitionId=' . $this->escape($id);
        $result = $this->getConnection()->query($sql) or die("getCompetition: Query failed: $sql");

        $voteCodeCount = $result->fetch_assoc()['c'];

        $sql = 'SELECT name, openTime, closeTime, testingOpenUntilTime '
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

        return array(
            'id' => $id,
            'name' => $row['name'],
            'openTime' => $openTime,
            'closeTime' => $closeTime,
            'testingOpenUntilTime' => $testingOpenUntilTime,
            'voteCodeCount' => $voteCodeCount
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



    
    //get eventreg event_id corresponding to our $compentionId
    public function getEventReg_EventId($competitionId)
    {
        $sql = 'SELECT event_id, event_name FROM Events WHERE deleted= 0 AND votesys_competition_id=' . $this->escape($competitionId);
        $result = $this->getConnectionDb2_Eventreg()->query($sql) or die("getEventId: Query failed: $sql");
        if ($result->num_rows < 1)
            die("Invalid eventreg competition id." . $competitionId . " num_rows: " . $result->num_rows);
        
        //return list of event_ids (typiskt FV + etikett tävling)
        $events = array();
        while ($row = $result->fetch_assoc()) {
            array_push($events,$row['event_id']); 
        }
        return $events;
    }

    //get beer info from eventreg database
    public function getBeers($competitionId, $grouped = false)
    {

        $evenreg_event_ids = $this->getEventReg_EventId($competitionId);
        if (count($evenreg_event_ids) < 1)
            die("Invalid eventreg,getBeers, competition id." . $competitionId + " num_rows: " + count($evenreg_event_ids));

        $sql =  'SELECT Beers_in_event.event_id,Beers.beer_id, Beers.user_id, Beer_data.beer_name, Beer_data.main_class, Beer_data.sub_class, ' .
            'Beer_data.low_alc, Beer_data.og, Beer_data.fg, Beer_data.bu, Beer_data.alc,User_data.name,Beers_in_event.fv_competition_no  FROM Beers ' .
            'INNER JOIN Beer_data USING (beer_id) INNER JOIN Beers_in_event USING (beer_id) ' .
            'INNER JOIN Users USING (user_id) INNER JOIN User_data USING (user_id) ' .

            'WHERE Beers_in_event.event_id IN (' . implode(',', $evenreg_event_ids) .   ') ' .
            'AND Beers_in_event.deleted = 0 ' .
            'AND Beer_data.deleted = 0 AND Beers.deleted = 0 ' .
            'AND Users.deleted = 0 AND User_data.deleted = 0 ' .
            'ORDER BY Beer_data.main_class, Beer_data.sub_class ASC';;

        $result = $this->getConnectionDb2_Eventreg()->query($sql) or die("getBeers: Query failed: $sql");

        //the result is grouped by main_class, so we need to create a new array to store the beers
        if ($result->num_rows < 1)
            die("Invalid getBeers result id=" . $competitionId  . " num_rows: " . $result->num_rows . " sql: " . $sql . " event_ids: " . implode(',', $evenreg_event_ids));


        //get all brewers participating in this brew from Brewers_of_beer (user_data.name is just the registering brewer))


        $classes = $this->getCategories($competitionId, true);
        $beers = array();
        $nextEntryId = 1;
        $classOffset = CONST_SETTING_CATEGORY_OFFSET -1;
        $classIndex = 0;
        while ($row = $result->fetch_assoc()) {
            //store beer info in arrays grouped by FvMainClass
            if ($row['fv_competition_no'] >= 900){ //etikett tävling (övriga fält lika som för ditto öl)
                $fv_class_id_nr = 8;
                $classIndex = $classOffset + $fv_class_id_nr + 1;
            }else{
                $fv_class_id_nr =  FvClass($row['low_alc'], $row['main_class'], $row['sub_class']);
                $classIndex = $classOffset + $fv_class_id_nr;
            }
 
            
            //find classes.id in $classes
            $class = array_values(array_filter($classes, function ($var) use ($classIndex) {
                return ($var['id'] == $classIndex);
            }));

            if (!array_key_exists(0, $class))
                die("Invalid class id." . $classIndex);
            //find the class name in $classes by array key Id


            $className = $class[0]['description']; //$classes[$fv_class_id_nr-1]['description'];
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
                'class' =>  $class[0]['id'], //must always match id in categories/competitions
                'entry_id' => $entryId,
                'entry_code' => $row['fv_competition_no'],
                'styleId' => $row['main_class'] . $row['sub_class'],
                'styleName' => $className,
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
        $useOffset = false;//CONST_SETTING_CONNECT_EVENTREG_DB > 1 ? true : false;
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
            $idStartPos = $useOffset ? $id  : $idStartPos;
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
        $sql = 'INSERT INTO entries '
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

    public function insertVote($voteCodeId, $categoryId, $votes)
    {
        $vote1 = !array_key_exists('1', $votes) || $votes['1'] === null ? 'null' : $votes['1'];
        $vote2 = !array_key_exists('2', $votes) || $votes['2'] === null ? 'null' : $votes['2'];
        $vote3 = !array_key_exists('3', $votes) || $votes['3'] === null ? 'null' : $votes['3'];

        $sql = 'insert into votes ' .
            '(id, votecodeId, categoryId, vote1, vote2, vote3, votingMethod, creationTime) ' .
            'values (null, ' . $voteCodeId . ', ' .
            $this->escape($categoryId) . ', ' . $vote1 . ', ' . $vote2 . ', ' . $vote3 . ', "web", null)';

        $stmt = $this->getConnection()->prepare($sql) or die("insertVote: prepare failed: $sql");
        $stmt->execute() or die("insertVote: Execute failed: $sql");
    }

    //store rating (insert or update), returns false on failure (true on success, or if rating is unchanged)
    public function storeRating($voteCodeId, $categoryId, $beerEntryId, $ratingScore, $ratingComment, $drankCheck)
    {


        $sql = 'select id,ratingScore,ratingComment,drankCheck from ratings where voteCodeId=' . $voteCodeId . ' and categoryId=' . $categoryId . ' and beerEntryId=' . $beerEntryId;
        $result = $this->getConnection()->query($sql) or die("storeRating: Query failed: $sql");

        if ($result->num_rows == 0) {

            if ($this->insertRating($voteCodeId, $categoryId, $beerEntryId, $ratingScore, $ratingComment, $drankCheck) === true)
                return true;
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
        return true;
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
            .  '(select * from votes where id in ' .
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
    public function getRatings($competitionId, $voteCodeId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="' . $voteCountStartTime->format('Y-m-d G:i:s') . '"';
        }
        $sql = 'select C.id categoryId, T.ratingScore, T.ratingComment, T.drankCheck, T.beerEntryId ' .
            '  from categories C ' .
            '  left outer join '
            .  '(select * from ratings where id in ' .
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
            array_push($ratings[$row['categoryId']], array('categoryId' => $row['categoryId'], 'beerEntryId' => $row['beerEntryId'], 'drankCheck' => $row['drankCheck'], 'ratingScore' => $row['ratingScore'], 'ratingComment' =>  $row['ratingComment'] !== null ? htmlspecialchars_decode($row['ratingComment']) : null));
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
        $host  = $_SERVER['HTTP_HOST'];
        $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
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
    $host  = $_SERVER['HTTP_HOST'];
    $uri   = $_SERVER['PHP_SELF'];
    $query = isset($_GET['competitionId']) ? '?competitionId=' . $_GET['competitionId'] : '';

    header("Location: http://$host$uri$query");
    exit;
}


// Returns FV class as string or numeric based on the main and sub class of the beer
// this is a copy of the function in the eventreg code, *but with returned numerics adusted to order in the competition table*
//TODO store in database instead of hardcoding

function FvClass($low_alc, $main_class, $sub_class)
{
    // $classesCurrentCompteition is an array of categories for the current competition, as returned by getCategories
    // if it is null, the function will just fetch default to values defined in this fuction (will match the eventreg database, but not out competition-database!)
    $as_numeric = true;
    if (!empty($low_alc)) {
        $fv_class = !$as_numeric ? 'Folköl' : '8';
    } elseif (($main_class == 1) || ($main_class == 2)) {
        $fv_class = !$as_numeric ? 'Lager och underjäst öl' : '1';
    } elseif ((($main_class == 3) && ($sub_class != 'F')) ||
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
    } elseif ((($main_class == 3) && ($sub_class == 'F')) ||
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
    } elseif ((($main_class == 6) && ($sub_class != 'F')) ||
        (($main_class == 9) && ($sub_class == 'A')) ||
        (($main_class == 9) && ($sub_class == 'C')) ||
        (($main_class == 9) && ($sub_class == 'D')) ||
        (($main_class == 9) && ($sub_class == 'F')) ||
        (($main_class == 9) && ($sub_class == 'G')) ||
        (($main_class == 9) && ($sub_class == 'H')) ||
        (($main_class == 9) && ($sub_class == 'I')) ||
        (($main_class == 9) && ($sub_class == 'J')) ||
        (($main_class == 9) && ($sub_class == 'K'))
    ) {
        $fv_class = !$as_numeric ? 'Jästdominerad öl' : '4';
    } elseif (($main_class == 10) ||
        (($main_class == 11) && ($sub_class == 'C'))
    ) {
        $fv_class = !$as_numeric ? 'Syrligt och spontanjäst öl' : '5';
    } elseif ((($main_class == 11) && ($sub_class == 'A')) ||
        (($main_class == 11) && ($sub_class == 'B')) ||
        (($main_class == 11) && ($sub_class == 'D')) ||
        (($main_class == 11) && ($sub_class == 'F')) ||
        (($main_class == 11) && ($sub_class == 'G')) ||
        (($main_class == 11) && ($sub_class == 'H')) ||
        (($main_class == 11) && ($sub_class == 'I')) ||
        (($main_class == 11) && ($sub_class == 'J')) ||
        (($main_class == 11) && ($sub_class == 'L'))
    ) {
        $fv_class = !$as_numeric ?  'Övriga öl' : '6';
    } elseif (($main_class == 12)) {
        $fv_class = !$as_numeric ? 'Cider och mjöd' : '7';
    } else {
        $fv_class = !$as_numeric ? 'Okänd': '0';
    }

    return $fv_class;
}
