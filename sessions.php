<?php
/* NAME
 *
 *  sessions.php
 *
 * CONCEPT
 *
 *  Browse Activist Mirror sessions.
 *
 * FUNCTIONS
 *
 *  versions     for each sessions.version value, return ['count', 'version']
 *  times        [earliest, latest] Unix times
 *  UnixToDate   [year, month, day] from a Unix time
 *  TimeMenus    create menu elements for selecting a date range
 *  VersionMenus create a versions menu
 *  GetSessions  return sessions, filtered
 *  ShowSessions table of session data
 *  SetFilter    form for selecting session summary
 */

$debug = 2;
require "am.php";

/* versions()
 *
 *  Return ['count', 'version'] arrays for every value of sessions.version.
 */

function versions() {
  global $con;

  $sth = $con->prepare('SELECT version, count(*) AS count
 FROM sessions
 GROUP BY version');
  $sth->execute();
  $res = $sth->get_result();
  return($res->fetch_all(MYSQLI_ASSOC));

} // end versions()


/* times()
 *
 *  Return a 2-element array with earliest and latest values of
 *  sessions.uid, which is a Unix timestamp.
 */

function times() {
  global $con;
  
  $sth = $con->prepare('SELECT max(uid) AS latest, min(uid) AS earliest
 FROM sessions');
  $sth->execute();
  $res = $sth->get_result();
  return($res->fetch_assoc());
  
} // end times()


function UnixToDate($date) {
    $date = date('Y-n-j', $date);
    preg_match('/^(\d+)-(\d+)-(\d+)$/', $date, $matches);
    return([
            'year' => $matches[1],
            'month' => $matches[2],
            'day' => $matches[3]
           ]);

} // end UnixToDate()


/* TimeMenus()
 *
 *  Build popup menus for a range of times.
 *
 *  What we'll have is $times, with Unix times of the earliest and latest
 *  sessions in ['earliest','latest']['limit'], broken out into
 *  $times['earliest','latest']['year','month','day']
 *  $times['earliest','latest']['menu']['year','month','day'] will contain
 *  either popup menus or, if earliest and latest don't differ in year/month,
 *  hidden input fields. And $times['menu']['earliest','latest']['unix'] will
 *  be hidden input fields with the unix timestamps.
*/

function TimeMenus() {
  $month = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
  ];

  $limits = times();

  foreach(['earliest', 'latest'] as $limit) {
    $times[$limit] = UnixToDate($limits[$limit]);
    $times[$limit]['limit'] = $limits[$limit];
    $times[$limit]['menu']['unix'] =
      "<input type=\"hidden\" name=\"$limit\" value=\"{$limits[$limit]}\">\n";
  }

  if($times['earliest']['year'] == $times['latest']['year']) {

    # All the sessions are in the same year. Don't make year menus.

    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['year'] =
        "<input type=\"hidden\" name=\"{$limit}_year\" value=\"{$times[$limit]['year']}\"> {$times[$limit]['year']}\n";

  } else {

    # Allow the year to be selected.

    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['year'] = "<select name=\"{$limit}_year\">\n";

    for($year = $times['earliest']['year']; $year<= $times['latest']['year']; $year++) {
      foreach(['earliest', 'latest'] as $limit) {
        $selected = ($year == $times[$limit]['year']) ? ' selected' : '';
        $times[$limit]['menu']['year'] .= " <option value=\"$year\"$selected>$year</option>\n";
      }
    }
    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['year'] .= "</select>\n";
  }
  if($times['earliest']['year'] == $times['latest']['year'] &&
    $times['earliest']['month'] == $times['latest']['month']) {

    # All the sessions are in the same month and year. Don't make month menus.

    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['month'] =
        "<input type=\"hidden\" name=\"{$limit}_month\" value=\"{$times[$limit]['month']}\"> {$month[$times[$limit]['month']]}\n";

  } else {

    # Build month menus.

    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['month'] = "<select name=\"{$limit}_month\">\n";

    for($monthno = 1; $monthno <= 12; $monthno++) {
      foreach(['earliest', 'latest'] as $limit) {
        $selected = ($monthno == $times[$limit]['month']) ? ' selected' : '';
        $times[$limit]['menu']['month'] .= " <option value=\"$monthno\"$selected>$month[$monthno]</option>\n";
      }
    }
    foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['month'] .= "</select>\n";
  }
  # Build day menus.

  foreach(['earliest', 'latest'] as $limit)
    $times[$limit]['menu']['day'] = "<select name=\"{$limit}_day\">\n";

  for($day = 1; $day <= 31; $day++) {
    foreach(['earliest', 'latest'] as $limit) {
      $selected = ($day == $times[$limit]['day']) ? 'selected' : '';
        $times[$limit]['menu']['day'] .= " <option value=\"$day\"$selected>$day</option>\n";
    }
  }
  foreach(['earliest', 'latest'] as $limit)
      $times[$limit]['menu']['day'] .= "</select>\n";

  return($times);
  
} // end TimeMenus()


/* VersionMenu()
 *
 *  Build a popup menu for a filter on sessions.version.
 */

function VersionMenu() {
  $versions = versions();
  $multiple = (sizeof($versions) > 2) ? ' multiple' : '';
  $vsel = "<select name=\"version[]\"$multiple>
 <option value=\"all\">all</option>
";
  foreach($versions as $version) {
    $v = isset($version['version']) ? $version['version'] : 'none';
    $vsel .= " <option value=\"$v\">$v</option>\n";
  }
  $vsel .= "</select>\n";
  return($vsel);

} // end VersionMenu()


/* AddRolePat()
 *
 * Add name of top role and names and IDs of top patterns.
 */

function AddRolePat(&$sessions) {
  global $con;

  $sth = $con->prepare('SELECT name
 FROM match_roles mr
  JOIN roles r ON role_id = id_role
 WHERE session_id = ?
 ORDER BY total DESC LIMIT 1');
  $sth->bind_param('i', $session_id);

  $sth2 = $con->prepare('SELECT p.id AS pattern_id, p.title
 FROM match_patterns mp
  JOIN pattern p ON mp.pattern_id = p.id
 WHERE session_id = ?
 ORDER BY tweaked_total DESC LIMIT 4');
  $sth2->bind_param('i', $session_id);
    
  foreach($sessions as $id => $session) {
    $session_id = $session['session_id'];
    $sth->execute();
    $res = $sth->get_result();
    $role = $res->fetch_row();
    $sessions[$id]['role'] = $role[0];
    $sth2->execute();
    $res = $sth2->get_result();
    $patterns = $res->fetch_all(MYSQLI_ASSOC);
    $sessions[$id]['patterns'] = $patterns;
  }

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
    
    if(!in_array('all', $_POST['version']))
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
      $conditions['uid'] = "BETWEEN {$filter['earliest']} AND {filter['latest']}";
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
  $sth = $con->prepare($sql);
  $sth->execute();
  $res = $sth->get_result();
  $sessions = $res->fetch_all(MYSQLI_ASSOC);

  AddRolePat($sessions);
  return($sessions);
  
} // end GetSessions()


function names($p) {
  return $p['title'];
} // end names()


/* ShowSessions()
 *
 *  Present a table of sessions.
 *
 *  Each row shows:
 *   when submitted
 *   language
 *   selected role
 *   displayed (4) patterns
 *   version
 *   group, project, prompt, suggestion, developer
 *   if the session was a developer, their token
 */

function ShowSessions($sessions) {
  print "<form id=\"lcontain\" method=\"POST\">
 <div class=\"bcontain\">
  <input type=\"submit\" id=\"delete\" name=\"submit\" value=\"Process Deletions\">
  <button id=\"cancel\">Cancel</button>
  <input type=\"submit\" id=\"download\" name=\"submit\" value=\"Download\">
  <button type=\"button\" id=\"selectall\">Select All</button>
  <button type=\"button\" id=\"clearall\">Unselect All</button>
 </div>
 <div id=\"sess\">
  <div class=\"sh\">Created</div>
  <div class=\"sh\">Language</div>
  <div class=\"sh\">Role</div>
  <div class=\"sh\">Patterns</div>
  <div class=\"sh\">Version</div>
  <div class=\"sh\">Group</div>
  <div class=\"sh\">Project</div>
  <div class=\"sh\">Prompt</div>
  <div class=\"sh\">Suggestion</div>
  <div class=\"sh\">Dev</div>
  <div class=\"sh\">Delete</div>
";

  foreach($sessions as $session) {

    # Add a hidden element containing the session_id to support downloads.

    print "<input type=\"hidden\" name=\"id[]\" value=\"{$session['session_id']}\">\n";

    $date = UnixToDate($session['uid']);
    $date = "{$date['year']}-{$date['month']}-{$date['day']}";
    $patterns = implode(',', array_map('names', $session['patterns']));
    if(strlen($session['prompt']) > 40) {
      $prompt = substr($session['prompt'], 0, 37) . '...';
      $prtitle = ' title="' . $session['prompt'] . '"';
    } else {
      $prompt = $session['prompt'];
      $prtitle = '';
    }
    if(strlen($session['suggestion']) > 40) {
      $suggestion = substr($session['suggestion'], 0, 37) . '...';
      $sugtitle = ' title="' . $session['suggestion'] . '"';
    } else {
      $suggestion = $session['suggestion'];
      $sugtitle = '';
    }
    print "  <div title=\"{$session['session_id']}\">$date</div>
  <div>{$session['language']}</div>
  <div>{$session['role']}</div>
  <div>$patterns</div>
  <div>{$session['version']}</div>
  <div>{$session['group']}</div>
  <div>{$session['project']}</div>
  <div$prtitle>{$prompt}</div>
  <div$sugtitle>$suggestion</div>
  <div>{$session['dev']}</div>
  <div><input type=\"checkbox\" name=\"${session['session_id']}\" value=\"1\"></div>\n\n";
  } // end loop on sessions
  
  print " </div>
 <div class=\"bcontain\">
  <input type=\"submit\" id=\"delete\" name=\"submit\" value=\"Process Deletions\">
  <button id=\"cancel\">Cancel</button>
  <input type=\"submit\" id=\"download\" name=\"submit\" value=\"Download\">
  <button type=\"button\" id=\"selectall\">Select All</button>
  <button type=\"button\" id=\"clearall\">Unselect All</button>
 </div>
</form>
";

} // end ShowSessions()


/* SetFilter()
 *
 *  Display a form for setting filter parameters.
 */

function SetFilter() {
  $vsel = VersionMenu();
  $times = TimeMenus();

  print "<h2>Set a Filter</h2>

<p>By default, we'll show the set of all sessions except those submitted
by developers. Uncheck the <em>Include all</em> box to expose other filter
criterea. Check the <em>Include developer submissions</em> box to include
developer submissions.</p>

<form method=\"POST\" id=\"filter\">

 <div class=\"fh\">Include all:</div>
 <div><input type=\"checkbox\" name=\"all\" id=\"all\" value=\"1\" checked></div>

 <div class=\"fh\" id=\"dev\">Include developer submissions:</div>
 <div><input type=\"checkbox\" name=\"dev\" value=\"1\"></div>

 <div class=\"sel fh\">Versions:</div>
 <div class=\"sel\">$vsel</div>

 <div class=\"sel fh\">No earlier than:</div>
 <div class=\"sel\">
  {$times['earliest']['menu']['day']}
  {$times['earliest']['menu']['month']}
  {$times['earliest']['menu']['year']}
 </div>

 <div class=\"sel fh\">No later than:</div>
 <div class=\"sel\">
  {$times['latest']['menu']['day']}
  {$times['latest']['menu']['month']}
  {$times['latest']['menu']['year']}
 </div>

 <div id=\"fsub\">
  <input type=\"submit\" name=\"submit\" value=\"Go\">
 </div>
 {$times['latest']['menu']['unix']}
 {$times['earliest']['menu']['unix']}
</form>
";

} // end SetFilter()


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
  $sth = $con->prepare($sql);
  $res = $sth->execute();
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
  $sth = $con->prepare($sql);
  $sth->execute();
  $res = $sth->get_result();
  $sessions = $res->fetch_all(MYSQLI_ASSOC);
  AddRolePat($sessions);

  # Create the CSV content.

  $fh = fopen('php://output', 'w');
  $fields = [
    'sessionid',
    'timestamp', 'language', 'version', 'group', 'project', 'prompt',
    'suggestion', 'developer', 'role', 'pattern1', 'pid1', 'pattern2',
    'pid2', 'pattern3', 'pid3', 'pattern4', 'pid4'
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
  header('Content-Type: text/csv'); 
  header('Content-Disposition: attachment; filename=".doggy.csv";'); 
  exit();
  
} // end Download()


DataStoreConnect();

# Access is authorized for superusers only.

$user = GetUser($_SERVER['REMOTE_USER']);
if(!isset($user) || !$user['super']) {
  print "<p>Cannot confirm that you are authorized to use this tool. Contact project administration.</p>\n";
  exit;
}
if($_POST['submit'] == 'Download') {
  Download();
  exit;
}
?>
<!DOCTYPE html>
<html>

<head>
 <title>Activist Mirror Session Browser</title>
 <link rel="stylesheet" href="sessions.css">
 <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Piazzolla:ital,opsz,wght@0,8..30,100..900;1,8..30,100..900&display=swap" rel="stylesheet">
 
<script>

  /* all()
   *
   *  Called when the 'all' checkbox changes value to expose or hide other
   *  form elements.
   */
   
  function all(event) {
    els = document.getElementsByClassName('sel')
    if(allel.checked) {
      for(i = 0; i < els.length; i++) {
        els[i].style.display = 'none'
      }
    } else {
      for(i = 0; i < els.length; i++) {
        els[i].style.display = 'block'
      }
    }
    return(true)
  } // all()

  function cancel(event) {
    nlocation = "<?=$_SERVER['SCRIPT_NAME']?>"
    document.location.assign(nlocation)

  } // end cancel()

  function selectall(event) {
    inputs = document.getElementsByTagName('input')
    for(i = 0; i < inputs.length; i++)
      if(inputs[i].type === 'checkbox')
        inputs[i].checked = true;
    return(true)
	
  } // end selectall()

  function clearall(event) {
    inputs = document.getElementsByTagName('input')
    for(i = 0; i < inputs.length; i++)
      if(inputs[i].type === 'checkbox')
        inputs[i].checked = false;
    return(true)
	
  } // end clearall()

</script>

</head>

<body>

<header><h1>Activist Mirror Session Browser</h1></header>

<?php

$rv = 0;

if(sizeof($_POST)) {

  // Form submission.

  if($_POST['submit'] == 'Go') {

    // request for sessions has been submitted.

    $sessions = GetSessions();
    if(count($sessions)) {
      ShowSessions($sessions);
      $rv = 1;
    }
    else
      print "<p>No matching sessions.</p>\n";
  } elseif($_POST['submit'] == 'Process Deletions') {
    DoDeletes();
  }
} 
if(!$rv)
  SetFilter();

?>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>

<script>
  if(allel = document.querySelector('#all'))
    allel.addEventListener('change', all)
  if(cancelel = document.querySelector('#cancel')) {
    cancelel.addEventListener('click', cancel)

    dlel = document.querySelector('#download')
    dlel.addEventListener('click', download)

    sael = document.querySelector('#selectall')
    sael.addEventListener('click', selectall)

    cel = document.querySelector('#clearall')
    cel.addEventListener('click', clearall)

    dlel = document.querySelector('#download')
    dlel.addEventListener('click', download)
  }
</script>

</body>

</html>
