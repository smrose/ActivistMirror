<?php
/*
 * NAME
 *
 *  am.php
 *
 * CONCEPT
 *
 *  Constants and program logic for the Activist Mirror application.
 *
 * FUNCTIONS
 *
 *  Debug              debugging message to error log
 *  DataStoreConnect   connect to the data store
 *  LocalString        retrieve string or strings
 *  GetAnswers         retrieve answer labels
 *  RecordSession      record a session record
 *  SaveResponses      record all responses for session
 *  ComputeRole        compute top role
 *  ComputePatterns    compute weight totals for each pattern
 *  GetTweaked         fetch pattern.tweak_value values
 *  MatchPatterns      store values in match_patterns table
 *  PatternNames       fetch pattern names and ids in tweaked_total order
 *  TopPatterns        fetch pattern data for the top patterns
 *  Mode               DESKTOP or MOBILE
 *  Dev                value of the 'dev' cookie; NULL if it doesn't exist
 *  Verbiage           fetch a row from the verbiage table
 *  GetLanguages       return the supported languages
 *  GetUser            return a 'translator' record
 *  versions           ['count', 'version'] from sessions
 *  times              [earliest, latest] Unix times
 *  AddRolePat         augment sessions records with role and patterns
 *  GetSessions        return sessions, filtered
 *  DoDeletes          process session deletions
 *  Download           perform a download
 */

const MODE_THRESHOLD = 1000;
const DESKTOP = 1;
const MOBILE = 2;

const QCOUNT = 8;
const PATCOUNT = 22;
const TOPPATS = 4;

const REBEL = 1;
const CHANGE_AGENT = 2;
const CITIZEN = 3;
const REFORMER = 4;

const TWEAK = array(
  REBEL => 1,
  CHANGE_AGENT => 0,
  CITIZEN => 2,
  REFORMER => 3
);

// itemtype values

const QUESTIONS = 1;
const ANSWERS = 2;
const PATTERNS = 3;
const ROLES = 4;
const MESSAGES = 5;
const ROLE_NAMES = 11;
const ROLE_DESCRIPTIONS = 12;
const ROLE_POSTS = 13;
const ROLE_IMGS = 14;
const ROLE_LINKS = 15;
const PATTERN_TITLES = 16;
const PATTERN_LINKS = 17;
const PATTERN_DESCRIPTIONS = 18;
const PATTERN_GRAPHICS = 19;
const QDESCRIPTOR = 20;
const QIMAGE = 21;

// Values of locals.object_id when itemtype is 5 (MESSAGES):

const TITLE = 0;
const REVEALS = 4;
const RECOMMENDED = 7;
const FULL = 8;
const POSTREPORT = 9;
const THANKS = 10;
const INTRO = 11;
const SUBMITLABEL = 12;
const INSTRUCTIONS = 13;
const NEXT = 14;
const UNANS = 15;
const NOTE = 16;
const VERBIAGE = 17;
const REMEMBER = 18;
const ASSUME = 19;
const FEED = 20;
const FEEDPH = 21;
const PROVIDING = 22;
const PROJNAME = 23;
const GROUPNAME = 24;
const PROMPT = 25;
const PROVPROMPT = 26;
const EXAMPROMPT = 27;
const ALLTYPES = 28;
const WHATKIND = 29;
const BEGIN = 30;
const LANGSEL = 31;


/* Debug()
 *
 *  Print $text to the error log if (global) $debug >= (argument) $level.
 */

function Debug($text, $level) {
  global $debug;

  if($debug >= $level) {
    error_log($text);
  }
  return $text;

} // end Debug()


/* DataStoreConnect()
 *
 *  Connect to the data store and initialise the data store connection.
 */

function DataStoreConnect() {
  global $con;
  require_once 'db.php'; // database connection parameters

  // Open connection with db

  if(isset($con))
    return;
  try {
    $con = new PDO(DSN, USER, PASSWORD);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $con->query("SET NAMES 'utf8'");
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} // end DataStoreConnect()


/* LocalString()
 *
 *  If $top isn't set, fetch and return the single string matching the
 *  argument language, itemtype, and object_id. Else, return the array
 *  of strings matching the range of object_id values between the
 *  arguments $bottom and $top - from the "localstring" table.
 *
 *  The argument language can be unset.
 */

function LocalString($language, $itemtype, $bottom, $top = NULL) {
  global $con;
  
  $u = [$itemtype, $bottom];
  
  if(isset($top)) {
    $u[] = $top;

    // Multiple rows.

    $sql = 'SELECT localstring FROM locals WHERE itemtype = ? AND object_id BETWEEN ? AND ?';
    if(isset($language)) {
      $sql .= ' AND language = ?';
      $u[] = $language;
    }

    try {
      $sth = $con->prepare($sql);
      $rv = $sth->execute($u);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
    $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)
      $text[] = $row['localstring'];
    
  } else {

    // Single row.
    
    $sql = 'SELECT localstring FROM locals WHERE itemtype = ? AND object_id = ?';
    if(isset($language)) {
      $sql .= ' AND language = ?';
      $u[] = $language;
    }

    try {
      $sth = $con->prepare($sql);
      $rv = $sth->execute($u);
      $text = $sth->fetch(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }

    if(!$text) {
      Debug("LocalString($language,$itemtype,$bottom) found no value", 2);
      if(isset($language) && $language != 'en') {
      
        // If we specified a language but found no string, return the 'en'.

        return LocalString('en', $itemtype, $bottom);
      }
    }
  }
  return $text;

} // end LocalString()


/* GetAnswers()
 *
 *  Fetch and return the labels for the five radio buttons offered as answers
 *  to this question.
 */

function GetAnswers($language, $page) {
  return LocalString($language, ANSWERS, 5 * $page - 4, 5 * $page);

} // end GetAnswers()


/* RecordSession()
 *
 *  Create a record in 'sessions' table.
 */

function RecordSession($session) {
  global $con;

  $param = [
    'uid' => NULL,
    'language' => NULL,
    'group' => NULL,
    'project' => NULL,
    'prompt' => NULL,
    'dev' => NULL,
    'version' => NULL
  ];

  foreach($session as $column => $value)
    $param[$column] = $value;
  $param['rando'] = mt_rand();

  $sql = 'INSERT INTO sessions(uid, language, `group`, project, prompt, dev, rando, version) VALUES(?, ?, ?, ?, ?, ?, ?, ?)';

  try {
    $sth = $con->prepare($sql);
    $sth->execute($param);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  
  // no user input involved, no prepare() required

  $sth = $con->query('SELECT session_id, rando
 FROM sessions
 ORDER BY session_id DESC LIMIT 1');
  $row = $sth->fetch(PDO::FETCH_ASSOC);
  return $row;

} // end RecordSession()


/* SaveResponses()
 *
 *  Save the user responses to the 'responses' table, one row per response.
 *  Returns the number of unanswered questions.
 */

function SaveResponses($language, $session_id, $SelectedQ) {
  global $con;
  $unanswered = 0;

  try {
    $sth = $con->prepare("INSERT INTO responses(response_id, session_id, id_q, id_ans) VALUES(NULL, ?, ?, ?)");
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }

  for($i = 1; $i < 9; $i++) {
    $index = $i - 1;
    $user_answer = $SelectedQ[$index];

    if($user_answer != "") {
      $params = [$session_id, $i, $user_answer];
      try {
        $sth->execute($params);
      } catch(PDOException $e) {
        throw new PDOException($e->getMessage(), $e->getCode());
      }
    } else {
      $unanswered++;
    }
  } // end loop

  return $unanswered;
  
} // end SaveResponses()


/* ComputeRole()
 *
 *  Compute this user's role.
 *
 *  $SelectedQ contains the indices of the question responses and is indexed
 *  on question number. Each response contributes some weight to each of the
 *  roles. We sum those weights for each role across all questions.
 */

function ComputeRole($session_id, $SelectedQ) {
  global $con;
  
  $RoleTotals = array(1 => 0, 2 => 0, 3 => 0, 4 => 0);

  try {
    $sth = $con->prepare('SELECT factor, id_role FROM role_factors WHERE id_q = ? AND position = ?');
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }

  $params = [$page, $qval];

  for($page = 1; $page <= QCOUNT; $page++) { 
    $qval = $SelectedQ[$page-1]; 
    try {
      $sth->execute($params);
      $values = $sth->fetchAll(PDO::FETCH_ASSOC); # expect 4 rows, 1 per role
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
    
    foreach($values as $value) {
      $RoleTotals[$value[1]] += $value[0];
      Debug("page $page, role $value[1], qval $qval, factor $value[0]", 3);
    }
  } // end loop on pages

  // Break ties.
  
  $max = 0; // high water mark for scores
  $toprole = 0; // index of top-scoring role
  $ties = 0; // number of ties

  for($role = 1; $role <= 4; $role++) {
    if ($RoleTotals[$role] > $max) {  
      $max = $RoleTotals[$role]; // new high-water mark
      $toprole = $role;
      $ties = 0;
    } else if ($RoleTotals[$role] == $max) {
	$ties++; // found a tie
    }
  } // end loop

  for($role = 1; $role <= 4; $role++) {
    if ($RoleTotals[$role] == $max) {
      $RoleTotals[$role] = $RoleTotals[$role] + TWEAK[$role];
    } else if ($RoleTotals[$role] == $max) {
      $ties++;
    }
  } // end loop

  // Save the per-role totals to the match_role table, 4 rows.

  try {
    $sth = $con->prepare('INSERT INTO match_roles(match_role_id, session_id, role_id, total) VALUES(NULL, ?, ?, ?)');
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }

  $max = 0;
  for($role = 1; $role <= 4; $role++) {
    $rt = $RoleTotals[$role];
    $params = [$session_id, $role, $rt];
    try {
      $sth->execute($params);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
    if($RoleTotals[$role] > $max) {
      $max = ($RoleTotals[$role]);
      $toprole = $role;
    }
  } // end loop
  
  Debug("Highest (role $toprole): $RoleTotals[$toprole]", 1);

  return $toprole;
  
} // end ComputeRole()


/* ComputePatterns()
 *
 *  The 'pattern_weights' table:
 *
 *   CREATE TABLE pattern_weights (
 *     id_q INTEGER,   -- question id
 *     id_p INTEGER,   -- pattern id
 *     ans text,       -- question answers, in English
 *     id_ans INTEGER, -- per-pattern, per-answer, 1..5
 *     weight INTEGER  -- values 2..9
 *   );
 *
 *  is used to select patterns to associate with a session. We sum the
 *  weights associated with each of this user's answers for each
 *  pattern, accumulating in $PatternTotals[], which is indexed on
 *  pattern number, 1..22.
 */

function ComputePatterns($SelectedQ) {
  global $con;

  $PatternTotals = Array();
  for($i = 1; $i <= PATCOUNT; $i++)
    $PatternTotals[$i] = 0;
  
  try {
    $sth = $con->prepare('SELECT id_p, weight FROM pattern_weights WHERE id_q = ? AND id_ans = ?');
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  
  // Loop on questions.
  
  for($question = 1; $question <= QCOUNT; $question++) {
    $id_ans = $SelectedQ[$question-1]; // 1..5
    $x = ($question-1) * 5 + $id_ans; // pattern_weights.id_ans
    $params = [$question, $x];
    try {
      $sth->execute($params);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
    $weights = $sth->fetchAll(PDO::FETCH_ASSOC);

    foreach($weights as $weight)
      $PatternTotals[$weight[0]] += $weight[1];

  } // end loop on questions

  Debug("\$PatternTotals = " . print_r($PatternTotals, 1), 3);
  return $PatternTotals;
  
} // end ComputePatterns()


/* GetTweaked()
 *
 *  Fetch and return the tweak_value values from the pattern table.
 *
 *   CREATE TABLE pattern(
 *    id int(11),         -- pattern ID, [1..22]
 *    title varchar(60),  -- e.g. "Public Agenda"
 *    tweak_value float,  -- .0038028 to .00526316
 *    rpat int(11)        -- pattern number
 *   );
 */

function GetTweaked() {
  global $con;

  $tweak_values = array();
  try {
    $sth = $con->prepare('SELECT id, tweak_value FROM pattern');
    $sth->execute();
    $values = $sth->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  
  foreach($values as $value)
    $tweak_values[$value['id']] = $value['tweak_value'];

  return $tweak_values;
  
} // end GetTweaked()


/* MatchPatterns()
 *
 *  Store values in the match_patterns table.
 *
 *   CREATE TABLE match_patterns (
 *    match_pattern_id int(11) PRIMARY AUTO_INCREMENT,
 *    session_id int(11),
 *    pattern_id int(11),
 *    total int(11),
 *    tweaked_total float
 *   );
 */

function MatchPatterns($session_id, $PatternTotals, $tweaked) {
  global $con;
  
  try {
    $sth = $con->prepare('INSERT INTO match_patterns(match_pattern_id, session_id, pattern_id, total, tweaked_total) VALUES (NULL, ?, ?, ?, ?)');
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }

  for($pattern = 1; $pattern <= PATCOUNT; $pattern++) {
    $pt = $PatternTotals[$pattern];
    $tt = $tweaked[$pattern];
    $params = [$session_id, $pattern, $pt, $tt];
    try {
      $sth->execute($params);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
    
  } // end loop on patterns

} // end MatchPatterns()


/* TopPatterns()
 *
 *  Return data - id, name, link, cards - on the argument patterns.
 */

function TopPatterns($patnos, $language) {
  global $con;

  $i = 0;
  foreach($patnos as $id => $tweak) {
    $tops[$i]['id'] = $id;
    $index[$id] = $i++;
  }

  $patset = implode(',', array_keys($patnos));
  
  # Fetch the names and links from the 'locals' table.

  $sql = 'SELECT object_id, localstring, itemtype
 FROM locals l
 WHERE language = ? AND
  itemtype IN (' . PATTERN_TITLES . ',' . PATTERN_LINKS . ')
  AND object_id IN (' . $patset . ')';

  try {
    $sth = $con->prepare($sql);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
    try {
    $sth->execute(['language']);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
  $rows = $sth->fetchAll();
  
  foreach($rows as $row)
    if($row[2] == PATTERN_TITLES) {
      $tops[$index[$row[0]]]['name'] = $row[1];
    } else {
      $tops[$index[$row[0]]]['link'] = $row[1];
    }

  # Fetch pattern.rpat to compute paths to card images.

  try {
    $sth = $con->prepare("SELECT id, rpat FROM pattern WHERE id IN ($patset)");
    $sth->execute();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  
  $rows = $sth->fetchAll();

  foreach($rows as $row)
    foreach(['image', 'text'] as $type) {
      $file = "cards/$language/$type/100/{$row[1]}.jpg";
      if($language != 'en' && ! file_exists($file))
        $file = "cards/en/$type/100/{$row[1]}.jpg";
      $tops[$index[$row[0]]]['card'][$type] = $file;
    }
  return $tops;

} // end TopPatterns()


/* Mode()
 *
 *  Select DESKTOP or MOBILE as the mode depending upon the screen width.
 */

function Mode() {
  global $screen_width;

  return ($screen_width >= MODE_THRESHOLD) ? DESKTOP : MOBILE;

} // end Mode()


/* Dev()
 *
 *  Returns the value of the 'dev' cookie or null if it doesn't exist.
 */

function Dev() {
  if(isset($_COOKIE['dev'])) {
    return $_COOKIE['dev'];
  } else {
    return null;
  }  
} /* end Dev() */


/* Verbiage()
 *
 *  Fetch a row from the 'verbiage' table.
 *
 *  $role is a value [1..4] to specify a value from roles.id.
 *
 *  $pattern is one of:
 *    a value [1..22] to specify a value from pattern.id
 *    0 to specify leading text
 *    NULL for default text used when there is none for any matching pattern
 *
 *  $language is a language code
 *
 *  No string found and the language is other than 'en', return the 'en'
 *  string (if any).
 */

function Verbiage($role, $pattern, $language) {
  global $con;

  $sql = "SELECT vstring FROM verbiage WHERE role = ? AND language = ? AND pattern ";
  $sql .= " = ?";
  try {
    $sth = $con->prepare($sql);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
    try {
    $sth->execute([$role, $language, $pattern]);
    } catch(PDOException $e) {
      throw new PDOException($e->getMessage(), $e->getCode());
    }
  $v = $sth->fetch(PDO::FETCH_COLUMN);
  if($v)
    return $v;
  elseif($language != 'en')
    return Verbiage($role, $pattern, 'en');

} /* end Verbiage() */


/* GetLanguages()
 *
 *  Fetch associative arrays for all or one language specified by 'code' from
 *
 *   CREATE TABLE language(
 *    code char(2) NOT NULL PRIMARY KEY,
 *    description varchar(80) NOT NULL,
 *    active int(1) NOT NULL DEFAULT 0
 *   );
 *
 *  augmented by the number of strings in that language as 'count'.
 */

function GetLanguages($code = null) {
  global $con;

  $sql = 'SELECT code, description, active, COUNT(*) AS count
 FROM locals l
  JOIN language la ON l.language = la.code
 WHERE language IS NOT NULL'
 . (isset($code) ? ' AND code = ?' : '')
 . ' GROUP BY language ORDER BY description';

  try {
    $sth = $con->prepare($sql);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  $params = (isset($code)) ? [$code] :[];
  try {
    $sth->execute($params);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }

  if(isset($code))
    return $sth->fetch(PDO::FETCH_ASSOC);
  else {
    while($language = $sth->fetch(PDO::FETCH_ASSOC))
      $languages[$language['code']] = $language;
    return $languages;
  }
  
} /* end GetLanguages() */


/* GetUser()
 *
 *  Return a user.
 *
 *   CREATE TABLE translator (
 *    id integer NOT NULL AUTO_INCREMENT,
 *    userid varchar(16) NOT NULL,
 *    super tinyint(1) NOT NULL DEFAULT 0,
 *    PRIMARY KEY (id),
 *    UNIQUE KEY userid (userid)
 *   );
 */

function GetUser($username) {
  global $con;

  try {
    $sth = $con->prepare('SELECT * FROM translator WHERE userid = ?');
    $sth->execute([$username]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), $e->getCode());
  }
  $translator = $sth->fetch(PDO::FETCH_ASSOC);
  return $translator;

} /* end GetUser() */


/* versions()
 *
 *  Return ['count', 'version'] arrays for every value of sessions.version.
 */

function versions() {
  global $con;

  try {
    $sth = $con->prepare('SELECT version, count(*) AS count
 FROM sessions
 GROUP BY version');
    $sth->execute();
    $them = $sth->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  return $them;

} // end versions()


/* times()
 *
 *  Return a 2-element array with earliest and latest values of
 *  sessions.uid, which is a Unix timestamp.
 */

function times() {
  global $con;
  
  try {
    $sth = $con->prepare('SELECT max(uid) AS latest, min(uid) AS earliest
 FROM sessions');
    $sth->execute();
    $them = $sth->fetch(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  return $them;
  
} // end times()


/* AddRolePat()
 *
 * To $sessions, for each session add:
 *   1. name of top role as 'role'
 *   2. names and IDs of top patterns as 'patterns', an array of TOPPATS
 *       arrays with 'pattern_id' and 'title'  fields
 *   3. the title and id of the top-scoring pattern for which a 'verbiage'
 *       row exists as 'discussed' and 'discussed_id' or 'none' and 0
 */

function AddRolePat(&$sessions) {
  global $con;

  try {
    $sth = $con->prepare('SELECT name, role_id
 FROM match_roles mr
  JOIN roles r ON role_id = id_role
 WHERE session_id = ?
 ORDER BY total DESC LIMIT 1');

    $sth2 = $con->prepare('SELECT p.id AS pattern_id, p.title
 FROM match_patterns mp
  JOIN pattern p ON mp.pattern_id = p.id
 WHERE session_id = ?
 ORDER BY tweaked_total DESC LIMIT 4');

    $sth3 = $con->prepare('SELECT pattern, p.title
 FROM verbiage v JOIN pattern p ON v.pattern = p.id
 WHERE role = ? AND pattern IN (?,?,?,?)');
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
    
  foreach($sessions as $id => $session) {
    $session_id = $session['session_id'];
    $param = [$session_id];
    $sth->execute($param);
    $role = $sth->fetch();
    $toprole = $role[0];
    $toproleid = $role[1];
    $sessions[$id]['role'] = $toprole;

    $sth2->execute($param);
    $patterns = $sth2->fetchAll(PDO::FETCH_ASSOC);
    for($i = 0; $i < TOPPATS; $i++)
      $patid[$i] = $patterns[$i]['pattern_id'];
    $sessions[$id]['patterns'] = $patterns;

    $param = [$toproleid, $patid[0], $patid[1], $patid[2], $patid[3]];
    $sth3->execute($param);
    if($sth3->rowCount()) {
      $v = $sth3->fetchAll();
      
      if($sth3->rowCount() == 1) {

        // One verbiage row matches.
	
        $sessions[$id]['discussed'] = $v[0][1];
        $sessions[$id]['discussed_id'] = $v[0][0];

      } else {

        /* There are multiple verbiage rows for this role and pattern set.
         * We want the row corresponding to the highest-scoring pattern. */
      
        $found = false;
        foreach($patid as $pid) {
          foreach($v as $tv) {
	    if($tv[0] == $pid) {
	      $sessions[$id]['discussed'] = $tv[1];
	      $sessions[$id]['discussed_id'] = $tv[0];
	      $found = true;
	      break;
	    }
          }
	  if($found)
	    break;
        } // end loop on patterns
      }
    } else {

      // There is no verbiage for this role and pattern set.
      
      $sessions[$id]['discussed_title'] = 'none';
      $sessions[$id]['discussed_id'] = 0;
    }
  } // end loop on sessions

} // end AddRolePat()


/* GetSessions()
 *
 *  Fetch a filtered set of submissions.
 *
 *  First, we build $filter[], with selected query conditions (if any).
 *
 *  From $filter, we build $conditions, with SQL subclauses for a WHERE
 *  clause.
 *
 *  $conditions is used to build and execute an SQL statement with columns
 *  from 'sessions'.
 *
 *  A second query adds role.name using tables match_roles and roles.
 *
 *  A third query adds the top pattern ids and titles for the session.
 */

function GetSessions() {
  global $con;

  # By default, developer submissions are not included.

  if(!isset($_POST['dev'])) // "include developer submissions"
    $filter['dev'] = 1;

  if(! isset($_POST['all'])) {

    # A filter on dates and/or versions may apply.
    
    if(isset($_POST['version']) && !in_array('all', $_POST['version']))
      foreach($_POST['version'] as $version)
        if($version != 'all')
          $filter['version'][] = $version;

    # Determine if the date limits have been changed.

    foreach(['earliest', 'latest'] as $limit) {
      $d = UnixToDate($_POST[$limit]); # unix time for $limit session
      $p['year'] = $_POST["${limit}_year"];
      $p['month'] = $_POST["${limit}_month"];
      $p['day'] = $_POST["${limit}_day"];
      if($d['year'] != $p['year'] ||
         $d['month'] != $p['month'] ||
         $d['day'] != $p['day'])
         
        // filter on $limit

        $filter[$limit] = gmmktime(0, 0, 0, $p['month'], $p['day'], $p['year']);
    }
  } // end building $filter
  
  if(isset($filter) && count($filter)) {

    // dev

    if(isset($filter['dev'])) // exclude developer submissions
      $conditions['dev'] = 'dev IS NULL';

    // uid range
    
    if(isset($filter['earliest']) && isset($filter['latest']))
      $conditions['uid'] = "uid BETWEEN {$filter['earliest']} AND {$filter['latest']}";
    elseif(isset($filter['earliest']))
      $conditions['uid'] = "uid >= {$filter['earliest']}";
    elseif(isset($filter['latest']))
      $conditions['uid'] = "uid <= {$filter['latest']}";
    
    // versions

    if(isset($filter['version']))
      if(sizeof($filter['version']) > 1)
        $conditions['version'] = 'version IN ("' .
	  implode('","', $filter['version']) . '")';
      else
        $conditions['version'] = "version = '{$filter['version']}'";
	
  } // end building $conditions
  
  $sql = 'SELECT s.*
 FROM sessions s';
  if(isset($conditions) && count($conditions)) {
    $sql .= ' WHERE ';
    $first = true;
    foreach($conditions as $condition) {
      if(! $first)
        $sql .= ' AND ';
      $sql .= $condition;
      $first = false;
    }
  }
  $sql .= ' ORDER BY uid';
  Debug($sql, 2);
  try {
    $sth = $con->prepare($sql);
    $sth->execute();
    $sessions = $sth->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  AddRolePat($sessions);
  return $sessions;
  
} // end GetSessions()


/* DoDeletes()
 *
 *  Process requested session deletions.
 */

function DoDeletes() {
  global $con;
  
  $deletes = [];

  foreach($_POST as $id => $value)
    if(preg_match('/^\d+$/', $id, $matches))
      $deletes[] = $id;
      
  if(!count($deletes)) {
    print "<p>No sessions selected for deletion.</p>\n";
    exit;
  }
  $sql = 'DELETE FROM sessions WHERE session_id in (' .
    implode(',', $deletes) . ')';
  try {
    $sth = $con->prepare($sql);
    $sth->execute();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  print '<p>Deleted ' . count($deletes) . " sessions.</p>\n";

} // end DoDeletes()


/* Download()
 *
 *  Download all the sessions for which the ID is in the form.
 */
 
function Download() {
  global $con;

  ob_start();

  # Build a list of session_id values from the form.

  $ids = implode(',', $_POST['id']);

  # Fetch those sessions plus the top role for each

  $sql = "SELECT s.session_id, uid, language, version, `group`, project, prompt, suggestion, dev AS developer
  FROM sessions s
   JOIN match_roles mr ON s.session_id = mr.session_id
   JOIN roles r ON mr.role_id = id_role
 WHERE s.session_id IN ($ids)
 GROUP BY s.session_id
 ORDER BY uid";
  try {
    $sth = $con->prepare($sql);
    $sth->execute();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  $sessions = $sth->fetchAll(PDO::FETCH_ASSOC);
  AddRolePat($sessions);

  # Create the CSV content.

  $fh = fopen('php://output', 'w');
  $fields = [
    'sessionid',
    'timestamp', 'language', 'version', 'group', 'project', 'prompt',
    'suggestion', 'developer', 'role', 'discussed', 'discussed_id',
    'pattern1', 'pid1', 'pattern2', 'pid2', 'pattern3', 'pid3', 'pattern4',
    'pid4'
  ];
  fputcsv($fh, $fields, "\t");

  foreach($sessions as $session) {
    $i = 1;
    foreach($session['patterns'] as $pattern) {
      $session["pattern$i"] = $pattern['title'];
      $session["pid$i"] = $pattern['pattern_id'];
      $i++;
    }
    unset($session['patterns']);
    fputcsv($fh, $session, "\t");
  }
  fclose($fh);
  $csv = 'AM-sessions.' . date('Y.m.d') . '.csv';
  header('Content-Type: text/csv'); 
  header('Content-Disposition: attachment; filename="' . $csv . '";'); 
  exit();
  
} // end Download()
