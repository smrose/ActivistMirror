<?php

include "lib/am.php";
$debug = 2;

// Open connection with db

DataStoreConnect();

/// set headers and flags based on language
 
if(isset($_GET["language"])) {     // if language var passed via url
  $language = $_GET["language"];
} else {
  $language = "en";
}

$languages[$language]['active'] = true;
$v = $languages[$language];
$thislang = "<div style=\"float: left\"><img alt=\"{$v['longlang']}\" src=\"img/${v['flag']}\" class=\"flag\"></div>\n";

$olangs = '<div style="float: right">';
foreach($languages as $l => $v) {
  if(!$v['active']) {
    $olangs .= "<a href=\"?language=$l\" title=\"${v['longlang']}\"><img alt=\"${v['longlang']}\" src=\"img/${v['flag']}\" class=\"flag\"></a>";
  }
}
$olangs .= "</div>\n";

// Get the application name from the database.

$title = LocalString($language, MESSAGES, TITLE);
if(isset($_COOKIE['dev'])) {
  $title .= ' (DEV MODE)';
}		  
$intro = LocalString($language, MESSAGES, INTRO);
$submitLabel = LocalString($language, MESSAGES, SUBMITLABEL);
$instructions = '<p class="nlead">' . implode("</p>\n<p class=\"nlead\">", explode("\n", LocalString($language, MESSAGES, INSTRUCTIONS))) . "</p>\n";

$uid = time();
?>
<html>
<head>
 <meta charset="utf-8">
 <meta http-equiv="X-UA-Compatible" content="IE=edge">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title><?=$title?></title>
 <link rel="stylesheet" href="css/surveyStyle.css">
</head>

<body>
<div id="container">

<header>
<h1><?=$title?></h1>
</header>

<section>
<div style="width: 95%; margin-bottom: 4em">
  <img src="img/activist-images-band.jpg" style="width: 100%">
<div style="margin-top: 1em; margin-bottom: 2em">
<?=$thislang?><?=$olangs?>
</div>
</div>

<form action="form.php" method="post" name="play">
<input type="hidden" name="language" value="<?=$language?>">
<input type="hidden" name="q1" value="0">
<input type="hidden" name="q2" value="0">
<input type="hidden" name="q3" value="0">
<input type="hidden" name="q4" value="0">
<input type="hidden" name="q5" value="0">
<input type="hidden" name="q6" value="0">
<input type="hidden" name="q7" value="0">
<input type="hidden" name="q8" value="0">
<input type="hidden" name="uid" value="<?=$uid?>">

<p class="nlead" id="demo"><i><?= $intro ?></i>

<input class="submitbutton nlead" type="submit" name="foo" value="<?=$submitLabel ?>"></p>

<script type="text/javascript">
var w = window.innerWidth
|| document.documentElement.clientWidth
|| document.body.clientWidth;

var h = window.innerHeight
|| document.documentElement.clientHeight
|| document.body.clientHeight;

var x = document.getElementById("demo");
x.innerHTML = x.innerHTML + "<input type=\"hidden\" name=\"screen_width\" value=\"" + w + "\"> <input type=\"hidden\" name=\"screen_height\" value=\"" + h + "\">";

</script>

</form>
<?= $instructions ?>
</section>

</div>

<footer>
The Public Sphere Project
</footer>

</body>
</html>
