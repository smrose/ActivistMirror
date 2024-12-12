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
 *  TimeMenus    create menu elements for selecting a date range
 *  VersionMenus create a versions menu
 *  ShowSessions table of session data
 *  SetFilter    form for selecting session summary
 */

$debug = 2;
require "am.php";
$aversion = date('H:i:s d/m/Y', filectime('.git/index'));


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


function names($p) {
  return $p['title'];
} // end names()

function ids($p) {
  return $p['pattern_id'];
} // end ids()


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

  # column headings
  
  $headings = "  <div class=\"sh\">Created</div>
  <div class=\"sh\">Language</div>
  <div class=\"sh\">Role</div>
  <div class=\"sh\">Discussed</div>
  <div class=\"sh\">Patterns</div>
  <div class=\"sh\">Version</div>
  <div class=\"sh\">Group</div>
  <div class=\"sh\">Project</div>
  <div class=\"sh\">Prompt</div>
  <div class=\"sh\">Suggestion</div>
  <div class=\"sh\">Dev</div>
  <div class=\"sh\">Delete</div>
";

  # a row of buttons to display above and below the form

  $bcontain = " <div class=\"bcontain\">
  <input type=\"submit\" id=\"delete\" name=\"submit\" value=\"Process Deletions\">
  <button id=\"cancel\">Cancel</button>
  <input type=\"submit\" id=\"download\" name=\"submit\" value=\"Download\">
  <button type=\"button\" id=\"selectall\">Select All</button>
  <button type=\"button\" id=\"clearall\">Unselect All</button>
 </div>
";

  # display a table of sessions wrapped in a form

  print "<form id=\"lcontain\" method=\"POST\">
$bcontain
<div id=\"sess\">
";

  # count the rows we display so we can reprint headings periodically

  $row = 0;

  foreach($sessions as $session) {

    if(!($row % 20))
      print $headings;

    # Add a hidden element containing the session_id to support downloads.

    print "<input type=\"hidden\" name=\"id[]\" value=\"{$session['session_id']}\">\n";

    $date = UnixToDate($session['uid']);
    $date = "{$date['year']}-{$date['month']}-{$date['day']}";
    $patterns = implode(',', array_map('names', $session['patterns']));
    $pids = implode(',', array_map('ids', $session['patterns']));
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
    $discussed = isset($session['discussed']) ? $session['discussed'] : '';

    print "  <div title=\"{$session['session_id']}\">$date</div>
  <div>{$session['language']}</div>
  <div>{$session['role']}</div>
  <div title=\"{$session['discussed_id']}\">$discussed</div>
  <div title=\"$pids\">$patterns</div>
  <div>{$session['version']}</div>
  <div>{$session['group']}</div>
  <div>{$session['project']}</div>
  <div$prtitle>{$prompt}</div>
  <div$sugtitle>$suggestion</div>
  <div>{$session['dev']}</div>
  <div><input type=\"checkbox\" name=\"${session['session_id']}\" value=\"1\"></div>\n\n";

  $row++;
  
  } // end loop on sessions
  
  print " </div>
$bcontain
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


DataStoreConnect();

# Access is authorized for superusers only.

$user = GetUser($_SERVER['REMOTE_USER']);
if(!isset($user) || !$user['super']) {
  print "<p>Cannot confirm that you are authorized to use this tool. Contact project administration.</p>\n";
  exit;
}
if(isset($_POST['submit']) && $_POST['submit'] == 'Download') {
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

<header><h1 title="<?=$aversion?>">Activist Mirror Session Browser</h1></header>

<?php

$rv = 0;

if(sizeof($_POST)) {

  // Form submission.

  if(isset($_POST['submit']) && $_POST['submit'] == 'Go') {

    // request for sessions has been submitted.

    $sessions = GetSessions();
    if(count($sessions)) {
      ShowSessions($sessions);
      $rv = 1;
    }
    else
      print "<p>No matching sessions.</p>\n";
  } elseif(isset($_POST['submit']) && $_POST['submit'] == 'Process Deletions')
    DoDeletes();
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
