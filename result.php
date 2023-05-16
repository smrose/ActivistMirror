<?php
/* NAME
 *
 *  result.php
 *
 * CONCEPT
 *
 *  Activist Mirror application.
 */

include "lib/am.php";

// Main program follows.

$debug = 2; // calls to Debug() with $level <= $debug will emit

header("Content-Type: text/html; charset=utf-8");
mb_language('uni'); 
mb_internal_encoding('UTF-8');

DataStoreConnect();

if (isset($_GET["language"])) {     // forgotten language var
  $language = $_GET["language"];
} elseif (isset($_POST["language"])) {     // for posted language var
  $language = $_POST["language"];
} else { 
  $language = "en"; 
}

$title = LocalString($language, MESSAGES, TITLE);

// increment or initialize page number (and hence question and answers)

if (isset($_POST["page"])) {
  $page = $_POST["page"];
  ++$page;
} else {
  $page = 1;
}

// SelectedQ[] contains the user input, if any, to the eight questions.

$SelectedQ = array();
$SelectedQ[] = $q1 = isset($_POST['q1']) ? $_POST['q1'] : NULL;
$SelectedQ[] = $q2 = isset($_POST['q2']) ? $_POST['q2'] : NULL;
$SelectedQ[] = $q3 = isset($_POST['q3']) ? $_POST['q3'] : NULL;
$SelectedQ[] = $q4 = isset($_POST['q4']) ? $_POST['q4'] : NULL;
$SelectedQ[] = $q5 = isset($_POST['q5']) ? $_POST['q5'] : NULL;
$SelectedQ[] = $q6 = isset($_POST['q6']) ? $_POST['q6'] : NULL;
$SelectedQ[] = $q7 = isset($_POST['q7']) ? $_POST['q7'] : NULL;
$SelectedQ[] = $q8 = isset($_POST['q8']) ? $_POST['q8'] : NULL;

Debug("\$SelectedQ[] = " . print_r($SelectedQ, TRUE), 3);

// The (poorly-named) $uid is actually the value of time() when the
// user first starts the application.

$uid = isset($_POST['uid']) ? $_POST["uid"] : time();

if (isset($_POST["screen_width"])) {
  $screen_width = $_POST["screen_width"];
}
if (isset($_POST["screen_height"])) {
  $screen_height = $_POST["screen_height"];
}
$mode = Mode();
?>
<html>
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
  <title>Playing the Activist Mirror Game</title>
<link rel="stylesheet" href="css/surveyStyle.css">

<script>
function PatternLink(pname, psppath, slot, imgpath) {
  document.getElementById(slot).innerHTML = 
'<div><center>' + ' <a href="' + psppath + '" target = \"_blank\"> <img src=\"' + imgpath + '\"> </a></center></div>';

  } // end PatternLink()
</script>

</head>

<body>

<div id="container">

<header>
<h1><?=$title?></h1>
</header>

<p style="display: none">mode = <?= $mode ?></p>
<section>

<?php

/* Create the session record. Note that the timestamp alone doesn't define 
 * the session - just the user_id — which can have multiple "sessions". */

$session_id = RecordSession($uid, $language);

// Save the answers for this user session to the 'responses' table.

SaveResponses($language, $session_id, $SelectedQ);

// Compute the top role.

$toprole = ComputeRole($session_id, $SelectedQ);
$looking = GetReveals($language);
$role = GetRoleName($language, $toprole);

if($mode == DESKTOP) {
  echo "<div style=\"width: 48%; float: left\">\n";
} else {
  echo "<div>\n";
}
echo "<div align='center' style=\"margin-left: 5%; margin-right: 5%;\">

<h3>$looking</h3>

<h2>$role</h2>
";

// ROLE Image

$image = GetRoleImage($toprole);

if($mode == MOBILE) {
  echo '<img src="40img/' . $image . '"><br>';
} else {
  echo '<img src="img/' . $image . '"><br>';
}
echo "</div>\n";

// ROLE description

$description = GetRoleDescription($language, $toprole);
echo "<p> <p>$description";

// ROLE post

$post = GetRolePost($language, $toprole);
echo "<p><p>$post";

// ------------------------------------------------------------------

echo "\n<hr class=\"bone\">\n";
$thanks = GetThanks($language);
echo "$thanks";   // Thanks to Moyers & activist reminder

echo "</div>\n";

if($mode == DESKTOP) {
  echo '<div style="width: 48%; float: right">
';
} else {
  echo "\n<div>\n<hr class=\"bone\">\n";
}

if($full = GetFull($language)) {
    echo "<h3>$full</h3>\n";   // click to see full pattern
}

// find weight for each pattern (1-22) / question (1-8) / answer (1-5)
// columns weight tb    id_p             id_q             id_ans
// example: SELECT weight FROM pattern_weights WHERE id_q = 1 and id_ans = 1 and id _p = 22

$PatternTotals = ComputePatterns($SelectedQ);

// Apply tweaks.

$tweak_values = GetTweaked();
Debug("\$tweak_values = " . print_r($tweak_values, 1), 3);
for($pattern = 1; $pattern <= PATCOUNT; $pattern++) {
  $tweaked[$pattern] = $PatternTotals[$pattern] * $tweak_values[$pattern];
}
Debug("\$tweaked = " . print_r($tweaked, 1), 3);
$biggest = max($tweaked);

// Populate the match_patterns table with values for this session.

MatchPatterns($session_id, $PatternTotals, $tweaked);

// Get the names of each pattern (in the appropriate language) in tweaked order.

$PatternNames = PatternNames($session_id, $language);
Debug("\$PatternNames = " . print_r($PatternNames, 1), 3);

// Get the url of each pattern (in the appropriate language) to the PSP site

$PatternPSPlinks = PatternURLs($session_id, $language);

// Construct the local path of the pattern image (in the appropriate
// language) to the PSP site.

$PatternImages = PatternImages($session_id, $language);
Debug("\$PatternImages = " . print_r($PatternImages, 1), 1);

?>

<div align="center" style="margin-left: 3%; margin-right: 3%;">

<table cellpadding="2" cellspacing="8">
<tr>
<?php 

// Four patterns displayed in a 2x2 table.

for($i = 0; $i < 4; $i++) { 
    $slot = "slot-" . ($i+1); 
    $pic = "pic" . ($i+1);
    echo "<td><div id=\"$slot\"><img name=\"$pic\" src=\"" .
      $PatternImages[$i]['image'] .
      "\" onclick='PatternLink(\"$PatternNames[$i]\", \"$PatternPSPlinks[$i]\", \"$slot\", \"" .
      $PatternImages[$i]['text'] .
      "\")' /></div></td>\n";
    if($i == 1) {
        echo "</tr>\n<tr>";
    }
} // end loop on top patterns
?>
  </tr>
</table> 

</div>

<?php
$localstring = LocalString($language, MESSAGES, POSTREPORT);
echo "$localstring";   // post report message
?>

</div>

<?php
if($mode == DESKTOP) {
  echo "<div style=\"width: 48%; float: left\">\n";
} else {
  echo "<div>\n";
}
?>

<hr style="height:2px;border:none;color:#08B;background-color:#08B;">


<table>
<tr valign="top">
 <td width="50%">
<h4>Liberating Voices Pattern Language</h4>
<div style="margin-left: 16px">
<a href="http://www.publicsphereproject.org/patterns/lv">Liberating Voices (English)</a><br />

<a href="http://www.publicsphereproject.org/patterns_arabic" target="_blank">Arabic</a><br />
<a href="http://www.publicsphereproject.org/patterns_chinese" target="_blank">Chinese</a><br />
<a href="http://www.publicsphereproject.org/patterns_french" target="_blank">Libérer les voix (Français)</a><br />
<a href="http://www.publicsphereproject.org/patterns_german" target="_blank">German</a><br />
<a href="http://www.publicsphereproject.org/patterns_greek" target="_blank">Greek</a><br />
<a href="http://www.publicsphereproject.org/patterns_hebrew" target="_blank">Hebrew</a><br />
<a href="http://www.publicsphereproject.org/patterns_italian" target="_blank">Voci liberatorie (Italiano)</a><br />
<a href="http://www.publicsphereproject.org/patterns_korean" target="_blank">Korean</a><br />
<a href="http://www.publicsphereproject.org/patterns_portuguese" target="_blank">Portuguese</a><br />
<a href="http://www.publicsphereproject.org/patterns_russian" target="_blank">Russian</a><br />
<a href="http://www.publicsphereproject.org/patterns_serbian" target="_blank">Serbian</a><br />
<a href="http://www.publicsphereproject.org/patterns_spanish" target="_blank">Spanish</a><br />
<a href="http://www.publicsphereproject.org/patterns_swahili" target="_blank">Swahili</a><br />
<a href="http://www.publicsphereproject.org/patterns_vietnamese" target="_blank">Vietnamese</a><br />

 </td>
 <td>
<h4>Other Links</h4>
<p>
<a href="https://eliberate.publicsphereproject.org">eLiberate</a>, Conduct online meetings 
using Roberts Rules of Order<p>

The Conversation:
<a href="https://theconversation.com/how-civic-intelligence-can-teach-what-it-means-to-be-a-citizen-63170">How civic intelligence can teach what it means to be a citizen</a><p>

Project website:
<a href="https://ci4cg.org/">Collective Intelligence for the Common Good</a> 
<p>

Read more!
<a href="https://link.springer.com/article/10.1007/s00146-017-0776-6">Collective 
intelligence for the common good: cultivating the seeds for an intentional 
collaborative enterprise</a>
<p>
</td>
</tr>
</table>
</div>

</section>

<footer>
The Public Sphere Project
</footer>

</body>
</html>
