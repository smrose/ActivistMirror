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
$next = LocalString($language, MESSAGES, NEXT);
$Based = LocalString($language, MESSAGES, INSTRUCTIONS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Playing the Activist Mirror</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Paytone+One&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="surveyStyle.css">
</head>

<body>
<div id="dev" title="<?=$aversion?>">DEVELOPER</div>
<div id="h">
 <span id="act">ACTIVIST:</span>
 <span id="actd">any person who is purposely       
   working for positive social change.</span>
</div>

<form method="POST" action="form.php">
<div id="twocol">
  <div id="bothcol">
   <?=$Based?>
  </div>
  <div>
    <div class="fh">
      Providing a group and/or project name, actual or hypothetical, can be
      useful for individual or collaborative work.
    </div>
    <div class="fh">
      1. Group Name:
    </div>
    <div>
      <input type="text" name="group" size="40">
    </div>
    <div class="fh">
      2. Project Name:
    </div>
    <div>
      <input type="text" name="project">
    </div>
 </div>
 <div>
   <div class="fh">
     Providing a prompt can be useful in considering your answers to the
     Activist Mirror questions. It can also be important if other people
     working with you will be answering the Activist Mirror.
   </div>
   <div class="fh">
     Example: As Chair of the Community Committee of a environmental
     action group in Seattle, I'd like to better understand what my role
     might be in developing projects and how patterns from the Liberating
     Voices pattern language could help me in that role.
   </div>
   <div class="fh">
     3. Prompt:
   </div>
   <div>
     <textarea name="prompt" rows="3" cols="80"></textarea>
   </div>
 </div>
</div>
<div id="loz">
  <input type="submit" name="submit" value="<?=$next?>">
</div>
</form>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>

<script>
  loz = document.querySelector('#loz')
  loz.addEventListener('click', q)
  dev = document.querySelector('#dev')
<?php
  if(!isset($dev))
   print("dev.style.display = 'none'");
?>
</script>

</body>
</html>
