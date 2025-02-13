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
$qps = '';
foreach(['language', 'prompt', 'group', 'project', 'version'] as $qp)
  if(isset($_REQUEST[$qp]) && strlen($_REQUEST[$qp]))
    $qps .= "<input type=\"hidden\" name=\"$qp\" value=\"{$_REQUEST[$qp]}\">\n";

// increment or initialize page number (and hence question and answers)

if (isset($_POST["page"])) {
  $page = $_POST["page"];
  ++$page;
} else {
  $page = 1;
}

// Get the data to compose this page.

$action = ($page < 8) ? 'form.php' : 'result.php';
$question = LocalString($language, QUESTIONS, $page);
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
 
 <script>

   const threshold = <?= MODE_THRESHOLD ?>

   /* mode()
    *
    *  Swap between portrait and landscpe mode according to viewport width.
    */

   function mode() {
     const vpwidth = window.innerWidth
     if(vpwidth > threshold) {

       // A wide viewport? Landscape, with image on the right.
       
       if(container.style.flexDirection != 'row') {
         container.style.flexDirection = 'row'
         questions.style.height = 'auto'
         questions.style.width = '60%'
	 questions.style.order = 1
	 questions.style.fontSize = 'calc(12px + 1.2vw)'
         image.style.height = 'auto'
         image.style.width = '40%'
	 image.style.order = 2
       }
     } else {

       // A narrow viewport? Portrait, with image at the top.
       
       if(container.style.flexDirection != 'column') {
         container.style.flexDirection = 'column'
	 questions.style.fontSize = 'calc(12px + 1.2vw)'
         questions.style.width = '100%'
         image.style.width = '40vw'
	 image.style.marginLeft = 'auto'
	 image.style.marginRight = 'auto'
	 questions.style.order = 2
	 image.style.order = 1
       }
     }
     vp.innerHTML = 'Viewport width: <code>' + window.innerWidth +
      "</code><br>\n Viewport height: <code>" + window.innerHeight +
      "</code>\n</div>\n"

   } // end mode()

 </script>

 <style type="text/css">
    body {
      font-weight: 400;
      font-size: 1.8vw;
    }
    input[type="radio"] {
       margin-top: -1px;
       vertical-align: middle;
    }
    #container {
      display: flex;
      height: 100vh;
      gap: 1vh 1vw;
      margin: 1vw;
    }
    #questions {
      padding: 1vw;
      backdrop-filter: blur(8px);
      background-color: rgb(255 255 255 / 20%);
    }
    #image {
      padding: 1vw;
    }
 </style>
</head>

<body>

<div id="dev" title="<?=$aversion?>">DEVELOPER</div>

<div id="dhead">
 <?=$qdescriptor?>
</div>

<div id="container">
  <div id="questions">
   <?=$question?>

   <form method="POST" action="<?=$action?>">

     <?=$qps?>
     <div id="fc">
<?php

$answervar = "q" . $page;

$i = 1;
foreach($answers as $answer) {
  echo "<div class=\"answer\"><input type=\"radio\" id=\"$i\" name=\"$answervar\" value=\"$i\"><label for=\"$i\">&nbsp;$answer</label></div>\n";
  $i++;
} // end loop on answers

for($pn = 1; $pn < $page; $pn++)
  echo "<input type=\"hidden\" name=\"q$pn\" value=\"{$_POST["q$pn"]}\">\n";

?>
      <input type="hidden" name="page" value="<?=$page?>">
      <input type="hidden" name="language" value="<?=$language?>">
      <input type="hidden" name="uid" value="<?=$uid?>">
      </div>
      <div id="sbc">
	<input type="submit" name="submit" value="<?=$next?>" id="sb">
      </div>
    </form>
  </div>
  <div id="image">
   <img src="img/<?=$qimage?>" id="gi">
  </div>
</div>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>
<div id="dev">DEVELOPER</div>
<div id="vp"></div>

<script>
  const dev = document.querySelector('#dev')
  const vp = document.querySelector('#vp')
<?php
  if(!isset($dev))
    print("dev.style.display = 'none'\nvp.style.display = 'none'\n")
?>
  const container = document.querySelector('#container')
  const questions = document.querySelector('#questions')
  const image = document.querySelector('#image')
  window.addEventListener('resize', mode)
  mode()
</script>
</body>
</html>
