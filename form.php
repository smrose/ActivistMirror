<?php
/* NAME
 *
 *  form.php
 *
 * CONCEPT
 *
 *  Activist Mirror application. This page presents one of the questions
 *  and solicits an answer.
 *
 */

include "am.php";
$dev = Dev();
$aversion = date('H:i:s d/m/Y', filectime('.git/index'));

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

// increment or initialize page number (and hence question and answers)

if (isset($_POST["page"])) {
  $page = $_POST["page"];
  ++$page;
} else {
  $page = 1;
}

// Get the data to compose this page.

$action = ($page < 8) ? 'form.php' : 'result.php';
$question = GetQuestion($language, $page);
$answers = GetAnswers($language, $page);
$next = LocalString($language, MESSAGES, NEXT);
$qimage = LocalString(NULL, QIMAGE, $page);

// The (poorly-named) $uid is actually the value of time() when the
// user first starts the application.

$uid = isset($_POST['uid']) ? $_POST["uid"] : time();

$qdescriptor = LocalString($language, QDESCRIPTOR, $page);

// We are presenting a form with a multiple-choice question.

?>
<!DOCTYPE html>
<html>
<head>
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta charset="utf-8">
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Paytone+One&display=swap" rel="stylesheet">
 <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
 <title>Playing the Activist Mirror Game</title>
 <link rel="stylesheet" href="surveyStyle.css">
 <style>
    body {
	font-weight: 400;
	font-size: 1.8vw;
    }
    input[type="radio"] {
        margin-top: -1px;
        vertical-align: middle;
    }
 </style>
</head>

<body>

<div id="dev" title="<?=$aversion?>">DEVELOPER</div>

<div id="dhead">
 <?=$qdescriptor?>
</div>

<div id="qgrid">
  <div id="dog">
   <?=$question?>
   <form method="POST" action="<?=$action?>">
     <div id="fc">
<?php

$answervar = "q" . $page;

$i = 1;
foreach($answers as $answer) {
  echo "<input type=\"radio\" id=\"$i\" name=\"$answervar\" value=\"$i\"><label for=\"$i\">&nbsp;$answer</label><br>\n";
  $i++;
} // end loop on answers

for($pn = 1; $pn < $page; $pn++)
  echo "<input type=\"hidden\" name=\"q$pn\" value=\"{$_POST["q$pn"]}\">\n";

?>
      <input type="hidden" name="group">
      <input type="hidden" name="project">
      <input type="hidden" name="prompt">
      <input type="hidden" name="page" value="<?=$page?>">
      <input type="hidden" name="language" value="<?=$language?>">
      <input type="hidden" name="uid" value="<?=$uid?>">
      </div>
      <div id="sbc">
	<input type="submit" name="submit" value="<?=$next?>" id="sb">
      </div>
    </form>
  </div>
  <div id="ipan">
   <img src="/publicsphereproject/ActivistMirror-devel/img/<?=$qimage?>" id="gi">
  </div>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>

<script>
  dev = document.querySelector('#dev')
<?php
  if(!isset($dev))
   print("dev.style.display = 'none'");
?>
</script>
</body>
</html>
