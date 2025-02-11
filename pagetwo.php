<?php
/* NAME
 *
 *  pagetwo.php
 *
 * CONCEPT
 *
 *  Second page of the Activist Mirror. Gives background information and
 *  solicits optional group and project names, and a prompt.
 */
 
include "am.php";
$dev = Dev();
$aversion = date('H:i:s d/m/Y', filectime('.git/index'));
DataStoreConnect();

// Query parameters possibly passed forward.

foreach(['group', 'project', 'prompt', 'version'] as $QueryParam) {
  $qp[$QueryParam] = isset($_REQUEST[$QueryParam]) ? $_REQUEST[$QueryParam] : '';
}

$language = (isset($_REQUEST['language']))
  ? $_REQUEST['language']
  : 'en';
$langinput = ($language == 'en')
  ? ''
  : "<input type=\"hidden\" name=\"language\" value=\"$language\">\n";
$versioninput = strlen($qp['version'])
  ? "<input type=\"hidden\" name=\"version\" value=\"{$qp['version']}\">\n"
  : '';
$next = LocalString($language, MESSAGES, NEXT);
$Based = LocalString($language, MESSAGES, INSTRUCTIONS);
$providing = LocalString($language, MESSAGES, PROVIDING);
$project = LocalString($language, MESSAGES, PROJNAME);
$group = LocalString($language, MESSAGES, GROUPNAME);
$prompt = LocalString($language, MESSAGES, PROMPT);
$provprompt = LocalString($language, MESSAGES, PROVPROMPT);
$examprompt = LocalString($language, MESSAGES, EXAMPROMPT);
$ACTIVIST = LocalString($language, MESSAGES, ACTIVIST);
$any = LocalString($language, MESSAGES, ANY);
?>
<!DOCTYPE html>
<html lang="<?=$lang?>">
<head>
  <title>Playing the Activist Mirror</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Paytone+One&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
  
  <script>
  
    const threshold = 850
    
   /* mode()
    *
    *  Swap between portrait and landscpe mode according to viewport width.
    */

    function mode() {
    
      const vpwidth = window.innerWidth

      if(vpwidth > threshold) {

        // Landscape for wide screens.

        if(c2.style.flexDirection != 'row') {

	  // Switch to landscape.
	  
	  c2.style.flexDirection = 'row'
	  c2ds.forEach(c2d => {
	    c2d.style.width = '50vw'
	  })
	}
      } else {

        // Portrait for narrow screens.

        if(c2.style.flexDirection != 'column') {

	  // Switch to portrait.

	  c2.style.flexDirection = 'column'
	  c2ds.forEach(c2d => {
	    c2d.style.width = '90vw'
	  })
	}
      }
      vp.innerHTML = 'Viewport width: <code>' + window.innerWidth +
       "</code><br>\n Viewport height: <code>" + window.innerHeight + "</code>\n</div>\n"
      
    } // end mode()

  </script>

  <link rel="stylesheet" href="surveyStyle.css">
</head>

<body>
<div id="dev" title="<?=$aversion?>">DEVELOPER</div>
<div id="h">
 <span id="act"><?=$ACTIVIST?>:</span>
 <span id="actd"><?=$any?></span>
</div>

<form method="POST" action="form.php">
<?=$langinput?>
<?=$versioninput?>
<div id="cl2">
 <?=$Based?>
</div>

<div id="c2">
  <div class="c2d">
    <div class="fh"><?=$providing?></div>
    <div class="fhb">
      <?=$group?>:
    </div>
    <div>
      <input type="text" name="group" value="<?=$qp['group']?>" size="40">
    </div>
    <div class="fhb">
      <?=$project?>:
    </div>
    <div>
      <input type="text" value="<?=$qp['project']?>" name="project">
    </div>
  </div>
  <div class="c2d">
    <div class="fh"><?=$provprompt?></div>
    <div class="fh"><?=$examprompt?></div>
    <div class="fhb"><?=$prompt?>:</div>
    <div>
      <textarea name="prompt" rows="3" cols="60"><?=$qp['prompt']?></textarea>
    </div>
  </div>
</div>
<div id="loz">
  <input type="submit" name="submit" value="<?=$next?>">
</div>
</form>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>
<div id="dev">DEVELOPER</div>
<div id="vp"></div>

<script>
  const dev = document.querySelector('#dev')
  const vp = document.querySelector('#vp')
<?php
  if(!isset($dev))
   print("dev.style.display = 'none'\nvp.style.display = 'none'\n");
?>
  const cl2 = document.querySelector('#cl2')
  const c2 = document.querySelector('#c2')
  const c2ds = document.querySelectorAll('.c2d')
  window.addEventListener('resize', mode)
  mode()
</script>

</body>
</html>
