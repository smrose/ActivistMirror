<?php
/* NAME
 *
 *  result.php
 *
 * CONCEPT
 *
 *  Activist Mirror application. This page shows the role and patterns
 *  and stores user input.
 */

include "am.php";

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

// SelectedQ[] contains the user input, if any, to the eight questions.

for($i = 1; $i <= QCOUNT; $i++)
  $SelectedQ[] = $_POST["q$i"] ? $_POST["q$i"] : NULL;

Debug("\$SelectedQ[] = " . print_r($SelectedQ, TRUE), 3);

// The (poorly-named) $uid is actually the value of time() when the
// user first starts the application.

$uid = isset($_POST['uid']) ? $_POST["uid"] : time();

/* Create the session record, returning the unique sessions.session_id
 * value. */

$session = [
  'uid' => $uid,
  'language' => $language
  ];
foreach(['group', 'project', 'prompt'] as $meta)
  if(isset($_POST[$meta]) && strlen($_POST[$meta]))
    $session[$meta] = $_POST['meta'];
if((Dev() !== NULL))
  $session['dev'] = Dev();
$session_id = RecordSession($session);

// Save the answers for this user session to the 'responses' table.

SaveResponses($language, $session_id, $SelectedQ);

// Compute the top role.

$toprole = ComputeRole($session_id, $SelectedQ);
$looking = GetReveals($language);
$role = GetRoleName($language, $toprole);
$rimage = 'img/' . GetRoleImage($toprole);
$description = GetRoleDescription($language, $toprole);
$post = GetRolePost($language, $toprole);
$thanks = LocalString($language, MESSAGES, THANKS);
$note = LocalString($language, MESSAGES, NOTE);
$feed = LocalString($language, MESSAGES, FEED);
$feedph = LocalString($language, MESSAGES, FEEDPH);
$full = GetFull($language);
$PatternTotals = ComputePatterns($SelectedQ); # 1-based

// Apply tweaks.

$tweak_values = GetTweaked();
Debug("\$tweak_values = " . print_r($tweak_values, 1), 3);
for($pattern = 1; $pattern <= PATCOUNT; $pattern++) {
  $tweaked[$pattern] = $PatternTotals[$pattern] * $tweak_values[$pattern];
}
Debug("\$tweaked = " . print_r($tweaked, 1), 3);

// $bytweak is top pattern numbers ordered by descending tweak value, top 4

$bytweak = $tweaked;
arsort($bytweak);
$bytweak = array_slice($bytweak, 0, TOPPATS, true);

// Populate the match_patterns table with values for this session.

MatchPatterns($session_id, $PatternTotals, $tweaked);

// Get the names of each pattern (in the appropriate language) in tweaked order.

$PatternNames = PatternNames($session_id, $language); # 0-based
Debug("\$PatternNames = " . print_r($PatternNames, 1), 3);

// Get the url of each pattern (in the appropriate language) to the PSP site

$PatternPSPlinks = PatternURLs($session_id, $language); # 0-based

// Construct the local path of the pattern image (in the appropriate
// language) to the PSP site.

$PatternImages = PatternImages($session_id, $language); # 0-based
Debug("\$PatternImages = " . print_r($PatternImages, 1), 1);
$postReport = LocalString($language, MESSAGES, POSTREPORT);

// Look for verbiage to match this role and set of patterns.

foreach($bytweak as $patno => $tweak) {
  $Verbiage = Verbiage($toprole, $patno);
  if(isset($Verbiage))
    $break;
}
if(isset($Verbiage)) {
  $Remember = LocalString($language, MESSAGES, REMEMBER);
} else {
  $Verbiage = Verbiage($toprole);
  $Remember = LocalString($language, MESSAGES, ASSUME);
}
$Remember = str_replace('%%ROLENAME%%', $role, $Remember);

?>
<html>
<head>
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta charset="utf-8">
  <title>Playing the Activist Mirror Game</title>
  <link rel="stylesheet" href="surveyStyle.css">
  <style>
    #tcol {
	display: grid;
	grid-template-columns: repeat(2, 50%);
	grid-column-gap: 1vw;
	margin: 1vw;
    }
    #rolepanel {
	backdrop-filter: blur(8px);
	background-color: rgb(255 255 255 / 20%);
	padding: .5vw;
    }
    #revpan {
        text-align: center;
    }
    #reveal {
	font-size: 1.4vw;
    }
    #role {
	font-size: 3vw;
	margin-bottom: 2vh;
    }
    #rimagectnr {
	margin-bottom: 1vh;
    }
    #rimage {
	left: auto;
	width: 50%;
	display: block;
	margin-left: auto;
	margin-right: auto;
    }
    #patterns {
    }
    #twotwo {
	display: grid;
	grid-template-columns: repeat(2, auto);
	column-gap: .4vw;
	row-gap: .4vh;
    }
    #verbiage {
    }
    #remember {
    }
    #deets {
    	text-align: right;
	font-size: 1vw;
	margin-bottom: 1vh;
	margin-right: 1vw;
    }
    #twotwo div {
        margin: auto;
    }
    #feed {
      border: 1px solid #bbb;
      width: max-content;
      position: relative;
      margin: auto;
    }
    #sub {
      background-color: #833;
      color: white;
      border: 1px solid #555;
      border-radius: 8px;
      position: absolute;
      right: 4px;
      bottom: 4px;
      font-weight: 500;
    }
    #ta {
      border: none;
    }
  </style>

  <script>
    function card(event) {
	id = event.target.id
	selector = '#' + id
	img = document.querySelector(selector)
	imageCard = img.attributes['src'].value
	textCard = imageCard.replace('image', 'text')
	div = img.parentElement
	pattern = div.attributes['data-pattern'].value
	div.innerHTML = '<a href="' + pattern + '" target="_blank"><img src="' + textCard + '"></a>'
    } // end card()

    function subf(event) {
      alert('subf()')
    } // end subf()
  </script>

</head>

<body>
  <div id="tcol">
    <div id="rolepanel">
      <div id="revpan">
        <span id="reveal"><?=$looking?>&nbsp;</span><span id="role"><?=$role?></span>
      </div>
      <div id="rimagectnr">
	<img src="<?=$rimage?>" id="rimage">
      </div>
      <p><!-- role description -->
       <?=$description?>
      </p>
      <p><!-- role post -->
	<?=$post?>
      </p>
      <p>
	<?=$note?>
      </p>
      <p>
        <?=$feed?>
      </p>
      <div id="feed">
        <textarea rows="4" cols="80" id="ta" placeholder="<?=$feedph?>"></textarea>
	<button id="sub">Submit feedback</button>
      </div>
      <p><!-- role thanks -->
	<?=$thanks?>
      </p>
    </div>
    <div id="patterns">
      <p id="verbiage">
       <?=$Verbiage?>
      </p>
      <p id="remember">
       <?=$Remember?>
      </p>
      <div id="deets">
        <?=$full?>
      </div>
      <div id="twotwo"><!-- pattern images -->
	<div id="slot-1" data-pattern="<?=$PatternPSPlinks[0]?>">
	  <img id="p-1" src="<?=$PatternImages[0]['image']?>">
	</div>
	<div id="slot-2" data-pattern="<?=$PatternPSPlinks[1]?>">
	  <img id="p-2" src="<?=$PatternImages[1]['image']?>">'
	</div>
	<div id="slot-3" data-pattern="<?=$PatternPSPlinks[2]?>">
	  <img id="p-3" src="<?=$PatternImages[2]['image']?>">
	</div>
	<div id="slot-4" data-pattern="<?=$PatternPSPlinks[3]?>">
	  <img id="p-4" src="<?=$PatternImages[3]['image']?>">
	</div>
      </div><!-- #patterns -->
    </div><!-- #twotwo -->
  </div>

  <div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>

  <script>
    twotwo = document.querySelector('#twotwo')
    imgs = twotwo.querySelectorAll('img')
    for(img of imgs) {
 	img.addEventListener('click', card)
    }
    sub = document.querySelector('#sub')
    sub.addEventListener('click', subf)
  </script>

</body>
</html>
