<?php // -*- coding: utf-8 -*-

class DbAccess
{
    private $dbConn;

    function __construct()
    {
        $settings = parse_ini_file('config.ini', true);
        $db = $settings['db'];
        
        $this->dbConn = mysqli_connect($db['host'], $db['user'], $db['password'])
                      or die('Unable to connect to database: ' . mysqli_connect_error());
        $this->dbConn->select_db($db['database']);
        $this->dbConn->set_charset('utf8');
    }

    function __descruct()
    {
        mysqli_close($this->dbConn);
        $this->dbConn = 0;
    }

    public function login($user, $password)
    {
        $sql = 'SELECT privilege, username '
             .'FROM users '
             .'WHERE username="'.$this->escape($user).'" '
             .'AND password=password("'.$this->escape($password).'")';
        $result = mysqli_query($this->dbConn, $sql) or die ("login: Query failed: $sql");
        
        if (mysqli_num_rows($result) != 1) {
            return;
        } else {
            $row = mysqli_fetch_assoc($result);
            $_SESSION['privilegeLevel'] = $row['privilege'];
            $_SESSION['username'] = $row['username'];
            return;
        }
    }
    
    public function getCompetition($id)
    {
        $sql = "SELECT count(*) as c FROM voteCodes WHERE competitionId=$id";
        $result = $this->dbConn->query($sql) or die ("getCompetition: Query failed: $sql");

        $voteCodeCount = $result->fetch_assoc()['c'];

        $sql = 'SELECT name, openTime, closeTime, testingOpenUntilTime '
             .'FROM competitions '
             .'WHERE competitions.id='.$this->escape($id);
        $result = $this->dbConn->query($sql) or die ("getCompetition: Query failed: $sql");
    
        if ($result->num_rows != 1)
            die("Invalid current competition.");
        $row = $result->fetch_assoc();

        $now = new DateTime();

        $openTime = new DateTime($row['openTime']);
        $timeBeforeOpen = date_diff($now, $openTime);

        $closeTime = new DateTime($row['closeTime']);
        $timeBeforeClose = date_diff($now, $closeTime);

        if (is_null($row['testingOpenUntilTime'])) {
            $testingOpenUntilTime = null;
            $timeBeforeTestingClose = null;
        } else {
            $testingOpenUntilTime = new DateTime($row['testingOpenUntilTime']);
            $timeBeforeTestingClose = date_diff($now, $testingOpenUntilTime);
        }

        if ($timeBeforeClose->invert) {
            $open = false;
            $timeBeforeClose = NULL;
            $openCloseText = 'Röstningen har stängt.';
        } else if ($timeBeforeOpen->invert) {
            $open = true;
            $openCloseText = 'Röstningen stänger om ' . self::formatDateInterval($timeBeforeClose).'.';
        } else if ($timeBeforeTestingClose != null && !$timeBeforeTestingClose->invert) {
            $open = true;
            $openCloseText = 'Röstningen öppen för test och stänger om '.
                           self::formatDateInterval($timeBeforeTestingClose).'.';
        } else {
            $open = false;
            $openCloseText = 'Röstningen öppnar om '.
                           self::formatDateInterval($timeBeforeOpen).'.';
        }
        
        return array('id' => $id,
                     'name' => $row['name'],
                     'openTime' => $openTime,
                     'closeTime' => $closeTime,
                     'voteCodeCount' => $voteCodeCount,
                     'open'=> $open,
                     'openCloseText'=> $openCloseText,
                     'timeBeforeClose' => $timeBeforeClose);
    }
    public function getCurrentCompetition()
    {
        $sql = 'SELECT currentCompetitionId as id FROM config';
        $result = $this->dbConn->query($sql) or die ("getCurrentCompetition: Query failed: $sql");
        $id = $result->fetch_assoc()['id'];
    
        return $this->getCompetition($id);
    }

    public function setCompetitionOpenForTestUntil($competitionId, $time)
    {
        $sql = 'update competitions '.
             'set testingOpenUntilTime="'.$time->format('Y-m-d G:i:s').'" '.
             'where id='.$this->escape($competitionId);
        $stmt = $this->dbConn->prepare($sql) or die ("setCompetitionOpenForTestUntil: prepare failed: $sql");
        $stmt->execute() or die ("insertVote: Execute failed: $sql");
    }

    public function getCategories($competitionId) {
        $sql = 'select id, name, description from categories '.
             'where competitionId="'.$this->escape($competitionId).'"';
        $result = $this->dbConn->query($sql) or die ("getCategories: Query failed: $sql");

        $categories = array();
        
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $name = $row['name'];
            $description = $row['description'];

            $sql = "select entryCode from entries where categoryId=$id order by entryCode";
            $result2 = $this->dbConn->query($sql) or die ("getCategories: Query failed: $sql");
            $entries = array_map(function($a) { return $a[0]; }, $result2->fetch_all());
            
            $categories[$id] = array('id' => $id,
                                     'name' => $name,
                                     'description' => $description,
                                     'entries' => $entries);
            // Notice there is also color field that could be filled in if added to the database.
        }

        return $categories;
    }
    
    public function getCurrentCategories()
    {
        $sql = 'SELECT currentCompetitionId as id FROM config';
        $result = $this->dbConn->query($sql) or die ("getCurrentCompetition: Query failed: $sql");
        $id = $result->fetch_assoc()['id'];

        return $this->getCategories($id);
    }

    public function addCategoryEntries($categoryId, $codes) {
        $catId = $this->escape($categoryId);
        $sql = 'INSERT INTO entries '
             . 'VALUES '
             . implode(',',
                       array_map(
                           function($c) use ($catId) { return "(null, $catId, $c)"; },
                           $codes));
        $stmt = $this->dbConn->prepare($sql) or die ("addCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die ("addCategoryEntries: Execute failed: $sql");
    }

    public function removeCategoryEntries($categoryId, $codes) {
        $catId = $this->escape($categoryId);
        $sql = "DELETE FROM entries WHERE categoryId=$catId AND entryCode IN ("
             . implode(',', $codes) .')';
        $stmt = $this->dbConn->prepare($sql) or die ("removeCategoryEntries: prepare failed: $sql");
        $stmt->execute() or die ("removeCategoryEntries: Execute failed: $sql");
    }

    
    // Might change in future -- probably hard coded at some other places too right now.
    public function getCurrentVoteWeightAndLabels()
    {
        return array('Guld' => 3, 'Silver' => 2, 'Brons' => 1);
    }
    
    public function getVoteCodes($competitionId)
    {
        $sql = 'SELECT code FROM voteCodes '.
             'WHERE competitionId='.$this->escape($competitionId).' '.
             'ORDER BY code';
        $result = $this->dbConn->query($sql) or die ("getCurrentCompetition: Query failed: $sql");

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
        $stmt = $this->dbConn->prepare($sql) or die ("insertVoteCodes: prepare failed: $sql");
        $stmt->execute() or die ("insertVoteCodes: Execute failed: $sql");
    }

    public function checkVoteCode($voteCode)
    {
        $voteCode = strtoupper($voteCode);
        $voteCode = trim($voteCode);

        $sql = 'SELECT VC.id FROM '.
             'config JOIN competitions C ON config.currentCompetitionId=C.id '.
             'JOIN voteCodes VC ON VC.competitionId=C.id '.
             'WHERE VC.code="'.$this->escape($voteCode).'"';
             
        $result = $this->dbConn->query($sql) or die ("checkVoteCode: Query failed: $sql");
        if ($result->num_rows != 1) {
            return 0;
        }
        
        $id = $result->fetch_assoc()['id'];
        return $id;
    }

    public function insertVote($voteCodeId, $categoryId, $vote1, $vote2, $vote3)
    {
        $sql = 'insert into votes (id, votecodeId, categoryId, vote1, vote2, vote3, votingMethod, creationTime) '.
             'values (null, '.$voteCodeId.', '.$this->escape($categoryId).', '.$vote1.', '.$vote2.', '.$vote3.', "web", null)';
        
        $stmt = $this->dbConn->prepare($sql) or die ("insertVote: prepare failed: $sql");
        $stmt->execute() or die ("insertVote: Execute failed: $sql");
    }

    public function getCurrentVotes($competitionId, $voteCodeId)
    {
        $sql = 'select categoryId, vote1, vote2, vote3 '.
             '  from votes V '.
             '  join ( select max(V.id) id from votes V join categories C on V.categoryId=C.id '.
             "         where C.competitionId=$competitionId and V.voteCodeId=$voteCodeId ".
             '         group by voteCodeId, categoryId ) T '.
             '  on V.id=T.id';

        $result = $this->dbConn->query($sql) or die ("getCurrentVotes: Query failed: $sql");

        $votes = array();
        while ($row = $result->fetch_assoc()) {
            $votes[$row['categoryId']] =
                   array('vote1' => $row['vote1'], 'vote2' => $row['vote2'], 'vote3' => $row['vote3']);
        }
        return $votes;
    }

    public function getVoteResult($categoryId) {
        $sql = 'select vote1, vote2, vote3 '.
             '  from votes V '.
             '  join ( select max(id) id from votes where categoryId='.
                                        $this->escape($categoryId).' group by voteCodeId ) T '.
             '  on V.id=T.id';
        $result = $this->dbConn->query($sql) or die ("getVoteResults: Query failed: $sql");

        $voteCounts = array();
        while ($row = $result->fetch_assoc()) {
            $this->addVote($voteCounts, 'vote1', $row['vote1']);
            $this->addVote($voteCounts, 'vote2', $row['vote2']);
            $this->addVote($voteCounts, 'vote3', $row['vote3']);
        }

        foreach ($voteCounts as &$voteCount) {
            $voteCount['points'] = $voteCount['vote1'] * 3 + $voteCount['vote2'] * 2 + $voteCount['vote3'];
        }
        
        usort($voteCounts, function($a, $b) { return $b['points'] - $a['points']; });
        return $voteCounts;
    }

    private function addVote(&$voteCounts, $level, $vote)
    {
        if (array_key_exists($vote, $voteCounts)) {
            $count = $voteCounts[$vote];
        } else {
            $count = array('entryId' => $vote, 'vote1' => 0, 'vote2' => 0, 'vote3' => 0);
        }
        $count[$level]++;
        $voteCounts[$vote] = $count;
}
    
    private function escape($s)
    {
        return $this->dbConn->real_escape_string($s);
    }

    private static function formatDateInterval($diff)
    {
        $ret = $diff->format(' %y years, %m months, %a days, %h hours, %i minutes, %S seconds');
        
        $ret = str_replace(array(' 0 years,', ' 0 months,', ' 0 days,', ' 0 hours,', ' 0 minutes,'),
                           ' ',
                           $ret);
        
        $ret = str_replace(array(' 1 years,', ' 1 months,', '1 days,', ' 1 hours,', ' 1 minutes'),
                           array(' 1 year, ',' 1 month, ',' 1 day, ',' 1 hour, ',' 1 minute'),
                           $ret);
        $ret = substr($ret, 1);
        return $ret;
    }
}

// Checks that the session has authenticated with at least the given level.
// If not, the user is redirected to the index page if $redirect is true.
// (The intention is that $redirect is false when called from the index page.)
function requireLoggedIn($minLevel, $redirect)
{
    if (isset($_SESSION['privilegeLevel']) &&  $_SESSION['privilegeLevel'] >= $minLevel) {
        return array($_SESSION['privilegeLevel'], $_SESSION['username']);
    } else {
        if ($redirect) {
            $host  = $_SERVER['HTTP_HOST'];
            $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            header("Location: http://$host$uri/"); 
            exit;
        } else {
            return array(0, NULL);
        }
    }
}

function logout()
{
    unset($_SESSION['privilegeLevel']);
    unset($_SESSION['username']);
}

function redirectToSelf()
{
    $host  = $_SERVER['HTTP_HOST'];
    $uri   = $_SERVER['PHP_SELF'];
    header("Location: http://$host$uri"); 
    exit;
}

/*
 * Returns two values (vote, errorDescription):
 * vote is an integer: positivt means a vote, -1 a missing vote, and 0 that an error occurred.
 */
function parseVote($categoryEntries, $vote)
{
    if ($vote == '') {
        return array(-1, '');
    }

    if ($vote == '0') { // special case since we otherwise say it's not an integer...
        return array(0, 'ogiltig röst');
    }

    
    $ivote = (int)$vote; // 0 vid icke-numerisk sträng
    if ($ivote == 0) {
        return array(0, "ogiltig röst ($vote), ej heltal");
    }

    $sr = array_search($ivote, $categoryEntries);
    if ($sr === false) {
        return array(0, "ogiltig röst ($vote), otillåtet tävlings-id");
    }

    return array($ivote, '');
}

/*
 * $ivotes: integer array of votes to check for one category
 * $votesPerCategory: number of possible votes per category
 */
function checkVoteRules($ivotes, $votesPerCategory)
{
    if (count($ivotes) != $votesPerCategory) {
        return "Exakt $votesPerCategory röster krävs.";
    }
    
    if (count(array_unique($ivotes)) != $votesPerCategory) {
        return "Max en röst per öl.";
    }
    
    if (array_search(-1, $ivotes)) {
        return "Tomma röster är ej tillåtna.";
    }

    return ''; 
}

define("SETTING_SYSSTATUS_INTERVAL", 10000);
define("CONST_SETTING_VOTES_PER_CATEGORY", 3);

?>
