<?php // -*- coding: utf-8 -*-

class DbAccess
{
    private $dbConn = null;

    function __construct()
    {
    }

    function __destruct()
    {
        if ($this->dbConn != null) {
            mysqli_close($this->dbConn);
        }
        $this->dbConn = 0;
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
        $sql = 'SELECT id, username FROM users '
             .'WHERE username="'.$this->escape($user).'" '
             .'AND password=password("'.$this->escape($password).'")';
        $result = mysqli_query($this->getConnection(), $sql) or die ("login: Query failed: $sql")
;
        if (!($row = $result->fetch_assoc())) {
            return;
        }
        $userId = $row['id'];
        $username = $row['username'];
        
        $sql = 'SELECT competition, privilegeLevel FROM privileges WHERE user='.$userId;
        $result = mysqli_query($this->getConnection(), $sql) or die ("login: Query failed: $sql");

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
        $sql = 'SELECT count(*) as c FROM voteCodes WHERE competitionId='.$this->escape($id);
        $result = $this->getConnection()->query($sql) or die ("getCompetition: Query failed: $sql");

        $voteCodeCount = $result->fetch_assoc()['c'];

        $sql = 'SELECT name, openTime, closeTime, testingOpenUntilTime '
             .'FROM competitions '
             .'WHERE competitions.id='.$this->escape($id);
        $result = $this->getConnection()->query($sql) or die ("getCompetition: Query failed: $sql");
    
        if ($result->num_rows != 1)
            die("Invalid competition id.");
        $row = $result->fetch_assoc();

        $openTime = new DateTime($row['openTime']);
        $closeTime = new DateTime($row['closeTime']);
        if (is_null($row['testingOpenUntilTime'])) {
            $testingOpenUntilTime = null;
        } else {
            $testingOpenUntilTime = new DateTime($row['testingOpenUntilTime']);
        }

        return array('id' => $id,
                     'name' => $row['name'],
                     'openTime' => $openTime,
                     'closeTime' => $closeTime,
                     'testingOpenUntilTime' => $testingOpenUntilTime,
                     'voteCodeCount' => $voteCodeCount);
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
            $openCloseText = 'Röstningen stänger om ' . self::formatDateInterval($timeBeforeClose).'.';
            $voteCountStartTime = $openTime;
        } else if ($timeBeforeTestingClose != null && !$timeBeforeTestingClose->invert) {
            $open = true;
            $openCloseText = 'Röstningen öppen för test och stänger om '.
                           self::formatDateInterval($timeBeforeTestingClose).'.';
            $timeBeforeClose = $timeBeforeTestingClose;
            $voteCountStartTime = null;
        } else {
            $open = false;
            $openCloseText = 'Röstningen öppnar om '.
                           self::formatDateInterval($timeBeforeOpen).'.';
            $voteCountStartTime = null;
        }
        
        return array('openTime' => $openTime,
                     'closeTime' => $closeTime,
                     'open'=> $open,
                     'openCloseText'=> $openCloseText,
                     'timeBeforeOpen' => $timeBeforeOpen,
                     'timeBeforeClose' => $timeBeforeClose,
                     'voteCountStartTime' => $voteCountStartTime);
    }
    
    public function setCompetitionOpenForTestUntil($competitionId, $time)
    {
        $sql = 'update competitions '.
             'set testingOpenUntilTime="'.$time->format('Y-m-d G:i:s').'" '.
             'where id='.$this->escape($competitionId);
        $stmt = $this->getConnection()->prepare($sql)
              or die ("setCompetitionOpenForTestUntil: prepare failed: $sql");
        $stmt->execute() or die ("setCompetitionOpenForTestUntil: Execute failed: $sql");
    }

    public function getCategories($competitionId) {
        $sql = 'select id, name, description from categories '.
             'where competitionId="'.$this->escape($competitionId).'"';
        $result = $this->getConnection()->query($sql) or die ("getCategories: Query failed: $sql");

        $categories = array();
        
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $name = $row['name'];
            $description = $row['description'];

            $sql = "select entryCode from entries where categoryId=$id order by entryCode";
            $result2 = $this->getConnection()->query($sql) or die ("getCategories: Query failed: $sql");
            $entries = array_map(function($a) { return $a[0]; }, $result2->fetch_all());
            
            $categories[$id] = array('id' => $id,
                                     'name' => $name,
                                     'description' => $description,
                                     'entries' => $entries);
            // Notice there is also color field that could be filled in if added to the database.
        }

        return $categories;
    }
    
    public function addCategoryEntries($categoryId, $codes) {
        $catId = $this->escape($categoryId);
        $sql = 'INSERT INTO entries '
             . 'VALUES '
             . implode(',',
                       array_map(
                           function($c) use ($catId) { return "(null, $catId, $c)"; },
                           $codes));
        $stmt = $this->getConnection()->prepare($sql) or die ("addCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die ("addCategoryEntries: Execute failed: $sql");
    }

    public function removeCategoryEntries($categoryId, $codes) {
        $catId = $this->escape($categoryId);
        $sql = "DELETE FROM entries WHERE categoryId=$catId AND entryCode IN ("
             . implode(',', $codes) .')';
        $stmt = $this->getConnection()->prepare($sql) or die ("removeCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die ("removeCategoryEntries: Execute failed: $sql");
    }
    
    // Might change in future -- probably hard coded at some other places too right now.
    public function getVoteWeightAndLabels($competitionId)
    {
        return array('Guld' => 3, 'Silver' => 2, 'Brons' => 1);
    }
    
    public function getVoteCodes($competitionId)
    {
        $sql = 'SELECT code FROM voteCodes '.
             'WHERE competitionId='.$this->escape($competitionId).' '.
             'ORDER BY code';
        $result = $this->getConnection()->query($sql) or die ("getVoteCodes: Query failed: $sql");

        $array = $result->fetch_all(MYSQLI_NUM);

        return array_map(function($a) { return $a[0];}, $array);
    }
    
    public function insertVoteCodes($competitionId, $codes)
    {
        $compId = $this->escape($competitionId);
        $sql = 'INSERT IGNORE INTO voteCodes (id, competitionId, code) '
             . 'VALUES '
             . implode(',',
                       array_map(
                           function($c) use ($compId) { return "(null, $compId, '$c')"; },
                           $codes));
        $stmt = $this->getConnection()->prepare($sql) or die ("insertVoteCodes: prepare failed: $sql");
        $stmt->execute() or die ("insertVoteCodes: Execute failed: $sql");
    }

    public function checkVoteCode($competitionId, $voteCode)
    {
        $voteCode = strtoupper($voteCode);
        $voteCode = trim($voteCode);

        $sql = 'SELECT id FROM voteCodes '
             .'WHERE competitionId='.$this->escape($competitionId).' '
             .'  AND code="'.$this->escape($voteCode).'"';
             
        $result = $this->getConnection()->query($sql) or die ("checkVoteCode: Query failed: $sql");
        if ($result->num_rows != 1) {
            return 0;
        }
        
        $id = $result->fetch_assoc()['id'];
        return $id;
    }

    public function insertVote($voteCodeId, $categoryId, $votes)
    {
        $vote1 = $votes['1'] === null ? 'null' : $votes['1'];
        $vote2 = $votes['2'] === null ? 'null' : $votes['2'];
        $vote3 = $votes['3'] === null ? 'null' : $votes['3'];
        
        $sql = 'insert into votes '.
             '(id, votecodeId, categoryId, vote1, vote2, vote3, votingMethod, creationTime) '.
             'values (null, '.$voteCodeId.', '.
                  $this->escape($categoryId).', '.$vote1.', '.$vote2.', '.$vote3.', "web", null)';
        
        $stmt = $this->getConnection()->prepare($sql) or die ("insertVote: prepare failed: $sql");
        $stmt->execute() or die ("insertVote: Execute failed: $sql");
    }

    public function getVotes($competitionId, $voteCodeId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="'.$voteCountStartTime->format('Y-m-d G:i:s').'"';
        }
        $sql = 'select C.id categoryId, T.vote1, T.vote2, T.vote3 '.
             '  from categories C '.
             '  left outer join '
             .  '(select * from votes where id in '.
             '      ( select max(votes.id) id '.
             '         from votes join categories on votes.categoryId=categories.id '.
             "         where categories.competitionId=$competitionId ".
             "         and voteCodeId=$voteCodeId $extraCond ".
             '         group by categoryId ) '.
             '   ) T'.
             '  on C.id=T.categoryId '.
             "  where C.competitionId=$competitionId";

        $result = $this->getConnection()->query($sql) or die ("getVotes: Query failed: $sql");

        $votes = array();
        while ($row = $result->fetch_assoc()) {
            $votes[$row['categoryId']] =
                   array('1' => $row['vote1'], '2' => $row['vote2'], '3' => $row['vote3']);
        }
        return $votes;
    }

    public function getVoteResult($categoryId, $voteCountStartTime)
    {
        if ($voteCountStartTime === null) {
            $extraCond = '';
        } else {
            $extraCond = ' and creationTime>="'.$voteCountStartTime->format('Y-m-d G:i:s').'"';
        }
        $sql = 'select vote1, vote2, vote3 '.
             '  from votes V '.
             '  join ( select max(id) id from votes where categoryId='.
                       $this->escape($categoryId).$extraCond.' group by voteCodeId ) T '.
             '  on V.id=T.id';
        $result = $this->getConnection()->query($sql) or die ("getVoteResults: Query failed: $sql");

        $voteCounts = array();
        while ($row = $result->fetch_assoc()) {
            $this->addVote($voteCounts, 'vote1', $row['vote1']);
            $this->addVote($voteCounts, 'vote2', $row['vote2']);
            $this->addVote($voteCounts, 'vote3', $row['vote3']);
        }

        foreach ($voteCounts as &$voteCount) {
            $voteCount['points'] = $voteCount['vote1'] * 3 + $voteCount['vote2'] * 2 + $voteCount['vote3'];
        }
        
        usort($voteCounts, function($a, $b) {
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
            $ret);
        
        $ret = str_replace(
            array(' 1 månader,', ' 1 dagar,', ' 1 timmar,', ' 1 minuter', ' 1 sekunder'),
            array(' 1 månad, ',' 1 dag, ',' 1 timme, ',' 1 minut', ' 1 sekund'),
            $ret);
        
        $ret = substr($ret, 1);
        
        return $ret;
    }
}

function getCompetitionId() {
    if (!isset($_GET['competitionId'])) {
        die('Missing competition');
    }
    
    return $_GET['competitionId'];
}

function isLoggedIn() {
    return isset($_SESSION['privileges']);
}

// Ensures that the session has authenticated with at least the given level.
function requireLoggedIn($competitionId, $minLevel) {
    if (isset($_SESSION['privileges']) &&
        isset($_SESSION['privileges'][$competitionId]) &&
        $_SESSION['privileges'][$competitionId] >= $minLevel) {
        return array($_SESSION['privileges'][$competitionId], $_SESSION['username']);
    } else {
        die('Not authorized.');
    }
}

// Ensures that the session has authenticated with at least the given level.
// If not, the user is redirected to the index page if not logged in at all.
function requireLoggedInOrRedirect($competitionId, $minLevel) {
    if (!isLoggedIn()) {
        $host  = $_SERVER['HTTP_HOST'];
        $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $query = isset($_GET['competitionId']) ? '?competitionId='.$_GET['competitionId'] : '';
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
    $query = isset($_GET['competitionId']) ? '?competitionId='.$_GET['competitionId'] : '';

    header("Location: http://$host$uri$query"); 
    exit;
}

?>
