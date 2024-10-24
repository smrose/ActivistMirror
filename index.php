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
  <style type="text/css">
    body {
	background-image: url("/publicsphereproject/ActivistMirror-devel/img/SummerWinter.png");
	font-family: Inter, sans-serif;
    }
    h1 {
	font-family: "Archivo Black", sans-serif;
	text-align: center;
	font-size: 5vw;
	position: relative;
	top: 50px;
    }
    .blur {
	padding: 20px;
	backdrop-filter: blur(8px);
	background-color: rgb(255 255 255 / 20%);
	text-align: center;
	position: relative;
	top: 150px;
    }
    #lz {
	position: relative;
	top: 250px;
	text-align: center;
    }
    #lz button {
	color: white;
	padding: .1em;
        background-color: #71a78c;
	border: 1px solid #666;
	border-radius: 15px;
	font-size: 30pt;
	font-weight: bold;
	padding-right: 2em;
	padding-left: 2em;
    }
    .ak {
	font-size: 20pt;
	font-weight: 400;
    }
    .wk {
	font-size: 20pt;
    }
  </style>
  <script>
    function pagetwo() {
	location = document.location
	location.assign('pagetwo.php')
    } // end pagetwo()
  </script>
</head>
<body>
<h1>ACTIVIST<br>MIR<span class="a">R</span>OR</h1>

<div class="blur">
 <div class="ak">It takes all kinds of people to make a better world.</div>
 <div class="wk">What kind are you?</div>
</div>
<div id="lz">
  <button>BEGIN</button>
</div>
<script>
  lz = document.getElementById('lz')
  lz.addEventListener('click', pagetwo)
</script>
</body>

</html>
