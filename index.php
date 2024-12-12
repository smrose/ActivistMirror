<?php
/* NAME
 *
 *  index.php
 *
 * CONCEPT
 *
 *  Entry page for the Activist Mirror.
 */

include "am.php";

$debug = 2;
$dev = Dev();
$aversion = date('H:i:s d/m/Y', filectime('.git/index'));

// Open connection with db

DataStoreConnect();

/* We support passing a set of query parameters forward. That allows a URL
 * to be published that will pre-populate those parameters in the form
 * on "pagetwo". */

$qps = '';
foreach(['prompt', 'group', 'project', 'version'] as $qp) {
  if(isset($_REQUEST[$qp])) {
    $qps .= strlen($qps) ? '&' : '';
    $qps .= "$qp={$_REQUEST[$qp]}";
  }
}

/// set headers and flags based on language
 
if(isset($_GET["language"]))     // if language var passed via url
  $language = $_GET["language"];
else
  $language = "en";

// build a popup menu, initially hidden, to allow users to select a language

$langs = GetLanguages();
$langsel = "<select name=\"language\" id=\"langsel\">\n";
foreach($langs as $lang) {
  if($lang['active']) {
    $selected = ($lang['code'] == $language) ? ' selected' : '';
    $langsel .= " <option value=\"{$lang['code']}\"$selected>{$lang['description']}</option>\n";
  }
}
$langsel .= "</select>\n";

// Get various strings from the 'locals' table.

$title = LocalString($language, MESSAGES, TITLE);
$ptitle = ($language == 'en')
  ? 'ACTIVIST<br>MIR<span class="a">R</span>OR'
  : $title;
$submitLabel = LocalString($language, MESSAGES, SUBMITLABEL);
$allTypes = LocalString($language, MESSAGES, ALLTYPES);
$whatKind = LocalString($language, MESSAGES, WHATKIND);
$begin =  LocalString($language, MESSAGES, BEGIN);
$lang_sel = LocalString($language, MESSAGES, LANGSEL);
$instructions = '<p class="nlead">' . implode("</p>\n<p class=\"nlead\">", explode("\n", LocalString($language, MESSAGES, INSTRUCTIONS))) . "</p>\n";

$uid = time();
?>
<!DOCTYPE html>
<html>
<head>
 <meta charset="utf-8">
 <meta http-equiv="X-UA-Compatible" content="IE=edge">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title><?=$title?></title>
 <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="surveyStyle.css">
 <link rel="stylesheet" href="index.css">

  <script>

    /* pagetwo()
     *
     *  Build a URL to pagetwo.php and assign it to document.location.
     */

    function pagetwo(event) {
        url = 'pagetwo.php'
        if(typeof language !== 'undefined') {
          url += '?language=' + language
          if(typeof qps !== 'undefined')
            url += '&' + qps
        } else
          if(typeof qps !== 'undefined')
            url += '?' + qps
        location = document.location
        location.assign(url)

    } // end pagetwo()

    /* sl()
     *
     *  Expose the popup menu for selecting a language.
     */
     
    function sl() {
      slel.style.display = 'none'
      langsel.style.display = 'block'
    } // end sl()

    /* nl()
     *
     *  Called when a language is selected from the popup to set the
     *  location to include a 'language' query parameter.
     */

     function nl(event) {
       nlocation = '<?=$_SERVER['SCRIPT_NAME']?>?language=' + langsel.value
       if(typeof qps !== 'undefined')
         nlocation += '&' + qps
       document.location.assign(nlocation)
       
     } // end nl()

  </script>

</head>

<body>
<h1><?=$ptitle?></h1>

<div id="dev" title="<?=$aversion?>">DEVELOPER</div>

<div class="blur">
 <div class="ak"><?=$allTypes?></div>
 <div class="wk"><?=$whatKind?></div>
</div>

<div id="lz">
  <button><?=$begin?></button>
</div>

<div id="sl">
 <button><?=$lang_sel?></button>
</div>
<?=$langsel?>

<script>
  lz = document.querySelector('#lz')
  lz.addEventListener('click', pagetwo)
  slel = document.querySelector('#sl')
  slel.addEventListener('click', sl)
  langsel = document.querySelector('#langsel')
  langsel.style.display = 'none'
  langsel.addEventListener('change', nl)
  dev = document.querySelector('#dev')
<?php
  if(!isset($dev))
    print "  dev.style.display = 'none'\n";
  if($language != 'en')
    print "  language = \"$language\"\n";
  if(strlen($qps))
    print "  qps = \"$qps\"\n";
?>
</script>

</body>

</html>
