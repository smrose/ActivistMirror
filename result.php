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

/* RoleBar()
 *
 *  Build UI elements to allow developers to select the role and pattern
 *  set.
 */

function RoleBar() {
  global $roleSelect, $patSelect;
  
  // Fetch the role and pattern names.

  $rnames = LocalString('en', ROLE_NAMES, 1, TOPPATS);
  $pnames = LocalString('en', PATTERN_TITLES, 1, PATCOUNT);

  // Build a SELECT form element for role selection.

  $roleSelect = "<select name=\"role\" id=\"roleselect\">
   <option value=\"0\">Select a role</option>";
  for($i = 1; $i <= ROLES; $i++) {
    $roleSelect .= " <option value=\"$i\" title=\"$i\">{$rnames[$i-1]}</option>\n";
  }
  $roleSelect .= "</select>\n";

  // Build four SELECT form elements for patterns.

  for($j = 1; $j <= TOPPATS; $j++) {
    $patSelect[$j] = "<select name=\"pattern$j\" id=\"patselect$j\">
   <option value=\"0\">Select pattern $j</option>
  ";
    for($i = 1; $i <= PATCOUNT; $i++) {
      $patSelect[$j] .= " <option value=\"$i\" title=\"$i\">{$pnames[$i-1]}</option>\n";
    }
    $patSelect[$j] .= "</select>\n";
  }
} // end RoleBar()

include "am.php";

// Main program follows.

$debug = 2; // calls to Debug() with $level <= $debug will emit

header("Content-Type: text/html; charset=utf-8");
mb_language('uni'); 
mb_internal_encoding('UTF-8');

DataStoreConnect();
RoleBar();

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
    $session[$meta] = $_POST[$meta];
if((Dev() !== NULL))
  $session['dev'] = Dev();
$session_id = RecordSession($session);

// Save the answers for this user session to the 'responses' table.

$unanswered = SaveResponses($language, $session_id, $SelectedQ);
$unans = $unanswered ? LocalString($language, MESSAGES, UNANS) : '';
$looking = LocalString($language, MESSAGES, REVEALS);
//$generic = LocalString($language, MESSAGES, VERBIAGE);
$thanks = LocalString($language, MESSAGES, THANKS);
$note = LocalString($language, MESSAGES, NOTE);
$feed = LocalString($language, MESSAGES, FEED);
$feedph = LocalString($language, MESSAGES, FEEDPH);
$full = LocalString($language, MESSAGES, FULL);

// Compute the top role.

$toprole = (isset($session['dev']) && isset($_REQUEST['role']))
  ? $_REQUEST['role'] 
  : ComputeRole($session_id, $SelectedQ);
  
// Compute the relevant patterns.

$PatternTotals = ComputePatterns($SelectedQ); # 1-based

// Apply tweaks.

$tweak_values = GetTweaked();
Debug("\$tweak_values = " . print_r($tweak_values, 1), 3);
for($pattern = 1; $pattern <= PATCOUNT; $pattern++) {
  $tweaked[$pattern] = $PatternTotals[$pattern] * $tweak_values[$pattern];
}
Debug("\$tweaked = " . print_r($tweaked, 1), 3);

// Populate the match_patterns table with values for this session.

MatchPatterns($session_id, $PatternTotals, $tweaked);

// $bytweak is top pattern numbers ordered by descending tweak value, top 4.

if(isset($_REQUEST['patno'])) {
  $patno = explode(',', $_REQUEST['patno']);
  for($i = 1; $i <= TOPPATS; $i++)
    $bytweak[$patno[$i-1]] = 0;
} else {
  $bytweak = $tweaked;
  arsort($bytweak);
  $bytweak = array_slice($bytweak, 0, TOPPATS, true);
}

$topPatterns = TopPatterns($bytweak, $language);

// Look for verbiage to match this role and set of patterns.

foreach($bytweak as $patno => $tweak) {
  $Verbiage = Verbiage($toprole, $patno);
  if(isset($Verbiage))
    break;
}
if(isset($Verbiage)) {
  $Remember = LocalString($language, MESSAGES, REMEMBER);
} else {
  $Verbiage = Verbiage($toprole);
  $Remember = LocalString($language, MESSAGES, ASSUME);
}

// Fetch some strings we'll need to build the page in the sought language.

$role = LocalString($language, ROLE_NAMES, $toprole);
$Remember = str_replace('%%ROLENAME%%', $role, $Remember);
$rimage = 'img/' . LocalString(NULL, ROLE_IMGS, $toprole);
$description = LocalString($language, ROLE_DESCRIPTIONS, $toprole);
$post = LocalString($language, ROLE_POSTS, $toprole);
$postReport = LocalString($language, MESSAGES, POSTREPORT);

?>
<!DOCTYPE html>
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
            font-weight: 700;
        font-size: 1.4vw;
    }
    #role {
            font-weight: 700;
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
      padding-right: 1vw;
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
    #selcont {
      position: fixed;
      top: 0;
      z-index: 1;
      right: 0;
      left: 0;
      text-align: center;
      background-color: rgb(240,255,240,50%);
      border: 1px solid tan;
      padding: 2px;
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

    function resetf(event) {
      if(roleselect.value <= 0)
        return false
      roleselect.disabled = false
      roleselect.value = 0
      patselect1.disabled = true
      patselect2.disabled = true
      patselect3.disabled = true
      patselect4.disabled = true
      patselect1.value = 0
      patselect2.value = 0
      patselect3.value = 0
      patselect4.value = 0

    } // end resetf()
    
    // called when a role is selected

    function roled(event) {

      // disable the role button

      roleselect.disabled = true

      // enable the pattern buttons
      
      patselect1.disabled = false
      patselect2.disabled = false
      patselect3.disabled = false
      patselect4.disabled = false

    } //end roled()
  
    // called when a pattern selector changes

    function select(event) {
      id = event.target.id
    
      // Check that all SELECTs have values.

      if(roleselect.value < 1 || patselect1.value < 1 || patselect2.value < 1
               || patselect3.value < 1 || patselect4.value < 1)
        return false

      // Check that each pattern is selected and unique.
      
      const pats = []
      pats.push(patselect1.value)
      if(pats.indexOf(patselect2.value) >= 0)
        return false
      pats.push(patselect2.value)
      if(pats.indexOf(patselect3.value) >= 0)
        return false
      pats.push(patselect3.value)
      if(pats.indexOf(patselect4.value) >= 0)
        return false
      pats.push(patselect4.value)

      // Forge and visit a URL.

      nlocation = '<?=$_SERVER['SCRIPT_NAME']?>?role=' + roleselect.value +
       '&patno=' + patselect1.value + ',' + patselect2.value + ',' +
       patselect3.value + ',' + patselect4.value
      document.location.assign(nlocation)

    } // end select()

  </script>

</head>

<body>
  <div id="selcont">
    <input type="button" id="resetb" value="Reset">
    <?=$roleSelect?>
    <?=$patSelect[1]?>
    <?=$patSelect[2]?>
    <?=$patSelect[3]?>
    <?=$patSelect[4]?>
  </div>
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
        <div id="slot-1" data-pattern="<?=$topPatterns[0]['link']?>">
          <img id="p-1" src="<?=$topPatterns[0]['card']['image']?>">
        </div>
        <div id="slot-2" data-pattern="<?=$topPatterns[1]['link']?>">
          <img id="p-2" src="<?=$topPatterns[1]['card']['image']?>">'
        </div>
        <div id="slot-3" data-pattern="<?=$topPatterns[2]['link']?>">
          <img id="p-3" src="<?=$topPatterns[2]['card']['image']?>">
        </div>
        <div id="slot-4" data-pattern="<?=$topPatterns[3]['link']?>">
          <img id="p-4" src="<?=$topPatterns[3]['card']['image']?>">
        </div>
      </div><!-- #patterns -->
    </div><!-- #twotwo -->
  </div>

  <div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>
  <div id="dev">DEVELOPER</div>

  <script>
    twotwo = document.querySelector('#twotwo')
    imgs = twotwo.querySelectorAll('img')
    for(img of imgs) {
         img.addEventListener('click', card)
    }
    sub = document.querySelector('#sub')
    sub.addEventListener('click', subf)
    dev = document.querySelector('#dev')

    roleselect = document.querySelector('#roleselect')
    roleselect.addEventListener('change', roled)

    patselect1 = document.querySelector('#patselect1')
    patselect1.disabled = true
    patselect1.addEventListener('change', select)
    patselect2 = document.querySelector('#patselect2')
    patselect2.disabled = true
    patselect2.addEventListener('change', select)
    patselect3 = document.querySelector('#patselect3')
    patselect3.disabled = true
    patselect3.addEventListener('change', select)
    patselect4 = document.querySelector('#patselect4')
    patselect4.disabled = true
    patselect4.addEventListener('change', select)

    resetb = document.querySelector('#resetb')
    resetb.addEventListener('click', resetf)

    selcont = document.querySelector('#selcont')
<?php
  if(!isset($session['dev']))
    print("dev.style.display = 'none'; selcont.style.display = 'none'");
?>
  </script>

</body>
</html>
