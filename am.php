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
 * NOTES
 *
 *  A MySQL datastore is employed.
 *
 *  The application consists of eight multiple-choice questions, each
 *  with five choices, implemented as radio buttons, each on its own
 *  page. After the final question is answered, a results screen is
 *  generated and results are stored in the database. The answers
 *  determine which of four roles is displayed and which four of the
 *  twenty-two patterns is displayed and linked.
 *
 * FUNCTIONS
 *
 *  Debug()              debugging message to error log
 *  DataStoreConnect()   connect to the data store
 *  LocalString()        retrieve string or strings
 *  GetQuestion()        retrieve question text
 *  GetAnswers()         retrieve answer labels
 *  GetReveals()         retrieve "reveals" string
 *  GetRoleName()        retrieve role name
 *  GetRoleImage()       retrieve role image
 *  GetRoleDescription() retrieve role description, locals
 *  GetRolePost()        retrieve role.post
 *  GetRecommended()     retrieve "recommended" string
 *  GetFull()            retrieve "full" string
 *  RecordSession()      record a session record
 *  SaveResponses()      record all responses for session
 *  ComputeRole()        compute top role
 *  ComputePatterns()    compute weight totals for each pattern
 *  GetTweaked()         fetch pattern.tweak_value values
 *  MatchPatterns()      store values in match_patterns table
 *  PatternNames()       fetch pattern names and ids in tweaked_total order
 *  PatternURLs()        fetch pattern URLs
 *  PatternImages()      fetch pattern image paths
 *  Mode()               DESKTOP or MOBILE
 *  Dev()                value of the 'dev' cookie; NULL if it doesn't exist
 *  Verbiage()           fetch a row from the verbiage table
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

$languages = [
  'en' => [
    'active' => false,
    'flag' => 'en-lang-flag.small.png',
    'longlang' => 'English language'
  ],
  'es' => [
    'active' => false,
    'flag' => 'es-lang-flag.small.png',
    'longlang' => ' lengua española'
  ],
  'it' => [
    'active' => false,
    'longlang' => 'lingua Italiana',
    'flag' => 'it-lang-flag.small.png'
  ]
];

/* Debug()
 *
 *  Print $text to the error log if (global) $debug >= (argument) $level.
 */

function Debug($text, $level) {
  global $debug;

  if($debug >= $level) {
    error_log($text);
  }
  return($text);

} // end Debug()


/* DataStoreConnect()
 *
 *  Connect to the data store and initialise the data store connection.
 */

function DataStoreConnect() {
  global $con;
  include "db.php"; // database connection parameters

  // Open connection with db

  $con = new mysqli($SERVER, $USER, $PASSWD, $DATABASE);

  // Check connection

  if ($con->connect_errno) {
    printf("Failed to connect to MySQL: %s\n", $con->connect_error);
    exit();
  }
  $con->query("SET NAMES 'utf8'");

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
  
  if(isset($top)) {

    // Multiple rows.

    $sql = 'SELECT localstring FROM locals WHERE itemtype = ? AND object_id BETWEEN ? AND ?';
    if(isset($language)) {
      $sql .= ' AND language = ?';
    }
    $sth = $con->prepare($sql);
    if(isset($language)) {
      $sth->bind_param('iiis', $itemtype, $bottom, $top, $language);
    } else {
      $sth->bind_param('iii', $itemtype, $bottom, $top);
    }
    $sth->execute();
    $res = $sth->get_result();
    $values = $res->fetch_all();
    foreach($values as $value) {
      $text[] = $value[0];
    }
    $sth->close();
    
  } else {

    // Single row. query() is the better choice.
    
    $sql = "SELECT localstring FROM locals WHERE itemtype = $itemtype AND object_id = $bottom";
    if(isset($language)) {
      $sql .= " AND language = '$language'";
    }
    if($result = $con->query($sql)) {
      $row = $result->fetch_row();
      if(is_null($row)) {
        Debug("LocalString($language,$itemtype,$bottom) found no value", 2);
	if(isset($language) && $language != 'en') {
	  // If we specified a language but found no string, return the 'en'.
	  return LocalString('en', $itemtype, $bottom, $top);
	}
	$text = '';
      } else {
        $text = $row[0];
      }
    } else {
        echo __FILE__ . ', line ' . __LINE__ . " query failed: " . $con->error . "\n";
    }
  }
  return($text);

} // end LocalString()


/* GetQuestion()
 *
 *  Retrieve the right question for the page.
 */

function GetQuestion($language, $page) {
  return(LocalString($language, QUESTIONS, $page));

} // end GetQuestion()


/* GetAnswers()
 *
 *  Fetch and return the labels for the five radio buttons offered as answers
 *  to this question.
 */

function GetAnswers($language, $page) {
  return(LocalString($language, ANSWERS, 5 * $page - 4, 5 * $page));

} // end GetAnswers()


/* GetReveals()
 *
 *  Fetch 'reveals' string.
 */

function GetReveals($language) {
  return(LocalString($language, MESSAGES, REVEALS));
  
} // end GetReveals()


/* GetRoleName()
 *
 *  Fetch role name for this language and id.
 */

function GetRoleName($language, $id) {
  return(LocalString($language, ROLE_NAMES, $id));
  
} // end GetRoleName()


/* GetRoleImage()
 *
 *  Fetch role image.
 */

function GetRoleImage($id) {
  return(LocalString(NULL, ROLE_IMGS, $id));
  
} // end GetRoleImage()


/* GetRoleDescription()
 *
 *  Fetch role description.
 */

function GetRoleDescription($language, $id) {
  return(LocalString($language, ROLE_DESCRIPTIONS, $id));
  
} // end GetRoleDescription()


/* GetRolePost()
 *
 *  Fetch role.post for this language and role id.
 */

function GetRolePost($language, $id) {
  return(LocalString($language, ROLE_POSTS, $id));
  
} // end GetRolePost()


/* GetRecommended()
 *
 *  Get 'recommended' string.
 */

function GetRecommended($language) {
  return(LocalString($language, MESSAGES, RECOMMENDED));
  
} // end GetRecommended()


/* GetFull()
 *
 *  Get 'full' string.
 */

function GetFull($language) {
  return(LocalString($language, MESSAGES, FULL));
  
} // end GetRecommended()


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
    'dev' => NULL
  ];

  foreach($session as $column => $value) {
    $param['column'] = $value;
  }
  $sth = $con->prepare("INSERT INTO sessions(uid, language, `group`, project, prompt, dev) VALUES(?, ?, ?, ?, ?, ?)");
  $sth->bind_param('isssss', $param['uid'], $param['language'], $param['group'],
                   $param['project'], $param['prompt'], $param['dev']);
  $sth->execute();
  $sth->close();
  
  // no user input involved, no prepare() required

  $result = $con->query('SELECT max(session_id) FROM sessions');
  $row = $result->fetch_row();
  $session_id = $row[0];
  $result->free();
  return($session_id);

} // end RecordSession()


/* SaveResponses()
 *
 *  Save the user responses to the 'responses' table, one row per response.
 *  Returns the number of unanswered questions.
 */

function SaveResponses($language, $session_id, $SelectedQ) {
  global $con;
  $unanswered = 0;

  $sth = $con->prepare("INSERT INTO responses(response_id, session_id, id_q, id_ans) VALUES(NULL, ?, ?, ?)");
  $sth->bind_param('iii', $session_id, $i, $user_answer);

  for($i = 1; $i < 9; $i++) {
    $index = $i - 1;
    $user_answer = $SelectedQ[$index];

    if($user_answer != "") {
      if(! $sth->execute()) {
        echo "DB error: couldn't insert response to question " . $i . "<br />"; 
        echo "$query;<br />"; // DEBUG 4
      }
    } else {
      $unanswered++;
    }
  } // end loop

  $sth->close();
  return($unanswered);
  
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

  $sth = $con->prepare('SELECT factor, id_role FROM role_factors WHERE id_q = ? AND position = ?');
  $sth->bind_param('ii', $page, $qval);
  $sth->bind_result($factor, $role);

  for($page = 1; $page <= QCOUNT; $page++) { 
    $qval = $SelectedQ[$page-1]; 
    $sth->execute();
    $res = $sth->get_result();
    $values = $res->fetch_all(); # expect 4 rows, 1 per role
    
    foreach($values as $value) {
      $RoleTotals[$value[1]] += $value[0];
      Debug("page $page, role $value[1], qval $qval, factor $value[0]", 3);
    }
  } // end loop on pages

  $sth->close();
  
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

  $sth = $con->prepare('INSERT INTO match_roles(match_role_id, session_id, role_id, total) VALUES(NULL, ?, ?, ?)');
  $sth->bind_param('iii', $session_id, $role, $rt);

  $max = 0;
  for($role = 1; $role <= 4; $role++) {
    $rt = $RoleTotals[$role];
    $sth->execute();
    if($RoleTotals[$role] > $max) {
      $max = ($RoleTotals[$role]);
      $toprole = $role;
    }
  } // end loop
  
  $sth->close();
  
  Debug("Highest (role $toprole): $RoleTotals[$toprole]", 1);

  return($toprole);
  
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
 *   )
 *
 *  is used to select patterns to associate with a session. We sum the
 *  weights associated with each of this user's answers for each
 *  pattern, accumulating in $PatternTotals[], which is indexed on
 *  pattern number, 1..22.
 */

function ComputePatterns($SelectedQ) {
  global $con;

  $PatternTotals = Array();
  for($i = 1; $i <= PATCOUNT; $i++) { $PatternTotals[$i] = 0; }
  
  $sth = $con->prepare('SELECT id_p, weight FROM pattern_weights WHERE id_q = ? AND id_ans = ?');
  $sth->bind_param('ii', $question, $x);
  
  // Loop on questions.
  
  for($question = 1; $question <= QCOUNT; $question++) {
    $id_ans = $SelectedQ[$question-1]; // 1..5
    $x = ($question-1) * 5 + $id_ans; // pattern_weights.id_ans
    $sth->execute();
    $result = $sth->get_result();
    $weights = $result->fetch_all();
    foreach($weights as $weight) {
      $PatternTotals[$weight[0]] += $weight[1];
    }
  } // end loop on questions

  Debug("\$PatternTotals = " . print_r($PatternTotals, 1), 3);
  $sth->close();
  return($PatternTotals);
  
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
 *   )
 */

function GetTweaked() {
  global $con;

  $tweak_values = array();
  $sth = $con->prepare('SELECT id, tweak_value FROM pattern');
  $sth->execute();
  $res = $sth->get_result();
  $values = $res->fetch_all();
  foreach($values as $value) {
    $tweak_values[$value[0]] = $value[1];
  }
  $sth->close();
  return($tweak_values);
  
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
  
  $sth = $con->prepare("INSERT INTO match_patterns(match_pattern_id, session_id, pattern_id, total, tweaked_total) VALUES (NULL, ?, ?, ?, ?)");
  $sth->bind_param('iiid', $session_id, $pattern, $pt, $tt);

  for($pattern = 1; $pattern <= PATCOUNT; $pattern++) {
    $pt = $PatternTotals[$pattern];
    $tt = $tweaked[$pattern];
    $sth->execute();
    
  } // end loop on patterns

  $sth->close();

} // end MatchPatterns()


/* PatternNames()
 *
 *  Fetch pattern names in match_patterns.tweaked_total order.
 */

function PatternNames($session_id, $language) {
  global $con;

  $sth = $con->prepare("SELECT pattern_id, localstring 
 FROM match_patterns mp JOIN locals l ON mp.pattern_id = l.object_id 
 WHERE session_id = \"$session_id\" AND language = \"$language\"
  AND itemtype = " . PATTERN_TITLES . "
 ORDER BY tweaked_total DESC");
  $sth->execute();
  $res = $sth->get_result();
  $values = $res->fetch_all();

  $PatternNames = array();

  foreach($values as $value) {
    $PatternNames[] = htmlspecialchars($value[1]);
  }
  $sth->close();
  return($PatternNames);

} // end PatternNames()


/* PatternURLs()
 *
 *  Fetch pattern URLs in the correct language in
 *  match_patterns.tweaked_total order.
 */

function PatternURLs($session_id, $language) {
  global $con;

  $sth = $con->prepare("SELECT pattern_id, localstring FROM match_patterns mp
 JOIN locals l ON mp.pattern_id = l.object_id
 WHERE session_id = $session_id AND language = \"$language\" AND itemtype = 17
 ORDER BY tweaked_total DESC");
  $sth->execute();
  $res = $sth->get_result();
  $values = $res->fetch_all();
  if(!count($values) && $language != 'en') {
    # If we don't find the pattern URLs for this language, return those for 'en'.
    return(PatternURLs($session_id, 'en'));
  }

  $PatternPSPlinks = array();
    
  foreach($values as $value) {
    $PatternPSPlinks[] = $value[1];
  }
  $sth->close();
  return($PatternPSPlinks);
    
} // end PatternURLs()


/* PatternImages()
 *
 *  Compute paths to pattern image files in descending tweaked_total order.
 */

function PatternImages($session_id, $language) {
  global $con;
  global $mode;
  
  $PatternImages = Array();
  $size = ($mode == MOBILE) ? 40 : 100;

  $sth = $con->prepare("SELECT p.id, p.rpat FROM match_patterns mp
 JOIN pattern p ON mp.pattern_id = p.id
 WHERE mp.session_id = $session_id
 ORDER BY mp.tweaked_total DESC");
  $sth->execute();
  $res = $sth->get_result();
  $values = $res->fetch_all();

  if($language != 'en' && ! file_exists("cards/$language")) {
    # No cards for this language? Revert to 'en'.
    $language = 'en';
  }

  foreach($values as $value) {
    $PatternImages[] = array(
      'image' => "cards/$language/image/$size/$value[1].jpg",
      'text' => "cards/$language/text/$size/$value[1].jpg"
    );
  };
  return($PatternImages);
  
} // end PatternImages()


/* Mode()
 *
 *  Select DESKTOP or MOBILE as the mode depending upon the screen width.
 */

function Mode() {
  global $screen_width;

  return(($screen_width >= MODE_THRESHOLD) ? DESKTOP : MOBILE);

} // end Mode()


/* Dev()
 *
 *  Returns the value of the 'dev' cookie or null if it doesn't exist.
 */

function Dev() {
  if(isset($_COOKIE['dev'])) {
    return($_COOKIE['dev']);
  } else {
    return(null);
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
 */

function Verbiage($role, $pattern = NULL) {
  global $con;

  $sql = "SELECT vstring FROM verbiage WHERE role = $role";
  if(is_null($pattern))
    $sql .= ' AND pattern IS NULL';
  else
    $sql .= " AND pattern = $pattern";
  $result = $con->execute_query($sql);
  $v = $result->fetch_row();
  return(isset($v) ? $v[0] : NULL);

} /* end Verbiage() */
