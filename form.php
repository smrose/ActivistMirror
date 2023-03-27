<?php
/* NAME
 *
 *  form.php
 *
 * CONCEPT
 *
 *  Activist Mirror application.
 *
 */

include "lib/am.php";

// Main program follows.

$debug = 2; // calls to Debug() with $level <= $debug will emit

header("Content-Type: text/html; charset=utf-8");
mb_language('uni'); 
mb_internal_encoding('UTF-8');

DataStoreConnect();

if(isset($_GET["language"])) {     // forgotten language var
  $language = $_GET["language"];
} elseif (isset($_POST["language"])) {     // for posted language var
  $language = $_POST["language"];
} else { 
  $language = "en"; 
}

$title = LocalString($language, MESSAGES, TITLE);
$next = LocalString($language, MESSAGES, NEXT);

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

// We are presenting a form with a multiple-choice question.

?>
<html>
<head>
 <meta http-equiv="X-UA-Compatible" content="IE=edge">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta charset="utf-8">
 <title>Playing the Activist Mirror Game</title>
 <link rel="stylesheet" href="css/surveyStyle.css">
</head>

<body>
<div id="container">

<header>
<h1><?=$title?></h1>
</header>

<p style="display: none">language = <?= $language ?>, uid = <?= $uid ?>, screen width: <?= $screen_width ?>, screen height: <?= $screen_height ?></p>

<section>

<img src="img/activist-images-band.jpg" style="width: 95%;" >

<!--Questions-->
<?php

$action = ($page < 8) ? 'form.php' : 'result.php';

echo "<p><form name=\"answer\" action=\"$action\" method=\"post\">";

// Retrieve and display the question for this page and language.

$question = GetQuestion($language, $page);

echo "<p class=\"nlead\">$question</p>\n";   // prints the question

// presenting possible (5) answers for each question [below]
// Need to increment for each of the 8 questions (use $page)

$answers = GetAnswers($language, $page);
$answervar = "q" . $page;

$i = 1;
foreach($answers as $answer) {
  echo "<p class=\"quest\"><input type=\"radio\" id=\"$i\" name=\"$answervar\" value=\"$i\"><label for=\"$i\">&nbsp;$answer</label>";
  $i++;
} // end loop on answers

echo "<input type=\"hidden\" name=\"page\" value=\"$page\">";
echo "<input type=\"hidden\" name=\"language\" value=\"$language\">";
echo "<input type=\"hidden\" name=\"uid\" value=\"$uid\">";
echo "<input type=\"hidden\" name=\"screen_width\" value=\"$screen_width\">";
echo "<input type=\"hidden\" name=\"screen_height\" value=\"$screen_height\">";

if ($page != 1) { echo "<input type=\"hidden\" name=\"q1\" value=\"$q1\">"; }
if ($page != 2) { echo "<input type=\"hidden\" name=\"q2\" value=\"$q2\">"; }
if ($page != 3) { echo "<input type=\"hidden\" name=\"q3\" value=\"$q3\">"; }
if ($page != 4) { echo "<input type=\"hidden\" name=\"q4\" value=\"$q4\">"; }
if ($page != 5) { echo "<input type=\"hidden\" name=\"q5\" value=\"$q5\">"; }
if ($page != 6) { echo "<input type=\"hidden\" name=\"q6\" value=\"$q6\">"; }
if ($page != 7) { echo "<input type=\"hidden\" name=\"q7\" value=\"$q7\">"; }
if ($page != 8) { echo "<input type=\"hidden\" name=\"q8\" value=\"$q8\">"; }
?>

<p>
<input type="submit" name="submit" value="<?=$next?>" class="btn btn-default">

</div>
</form>

<hr>

</div>

</section>

<br>


<footer>
The Public Sphere Project
</footer>

</body>
</html>
