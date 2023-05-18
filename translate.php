<?php
/* NAME
 *
 *  translate.php
 *
 * CONCEPT
 *
 *  Language translation tool for strings in Activist Mirror.
 *
 * FUNCTIONS
 *
 *  ItemTypes        return a list of all the itemtypes
 *  GetLanguages     return a list of supported languages
 *  ChooseLanguages  select languages and item type
 *  ManageLanguages  add and edit supported languages
 *  ManageTranslators edit the translators
 *  AuthConnect      connect to the auth database
 *  Locals           fetch strings of this type and language
 *  Error            fail in disgrace
 *  Translate        present the form for entering strings
 *  Absorb           absorb edited strings
 *  GetTranslators   return a list of users and the languages they support
 *  InsertLocal      insert a row into 'locals'
 *  UpdateLocal      update a row in 'locals'
 *  DeleteLocal      delete a row from 'locals'
 *  GetUsers         return array of all PHPAuth users
 *  Translators      present a form for assigning translators
 *  SetTranslators   absorb setting of translators
 *
 * NOTES
 *
 *  Strings are held in the 'locals' table. Each has itemtype and object_id
 *  values that determine the role it plays in the system and a language that
 *  determines what language it's in:
 *
 *   CREATE TABLE locals (
 *    local_id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *    localstring varchar(2048) DEFAULT NULL,
 *    language char(2) DEFAULT NULL,
 *    itemtype int DEFAULT NULL,
 *    object_id int DEFAULT NULL,
 *   );
 *
 *  The 'language' table has a row for each language we support in which
 *  the primary key is a two-letter 'code' with a value such as 'en' and
 *  a description such as 'US English':
 *
 *   CREATE TABLE language (
 *    code char(2) NOT NULL PRIMARY KEY,
 *    description varchar(80) NOT NULL,
 *   );
 *
 *  Each authorized user/language pair is represented by a row in the
 *  'translator' table:
 *
 *   CREATE TABLE translator (
 *    userid int NOT NULL COMMENT 'references ps.phpauth_users.id',
 *    lcode char(2) NOT NULL COMMENT 'language code'
 *   );
 *
 *  The Activist Mirror, lacking an auth system of its own, borrows from
 *  the PHPAuth data used for the Pattern Sphere. Users must authenticate
 *  in that application before using this one.
 *
 *  Authenticated users are first offered a choice of destination languages
 *  which they are authorized to support and which type of strings they wish
 *  to translate. All the strings of that type in the source language - 
 *  usually US English - are presented next to a textarea in which the
 *  corresponding string in the destination language - never US English -
 *  can be edited or entered. Those entries and/or edits are then used to
 *  generate INSERT and/or UPDATE statements.
 *
 *  Superusers are authorized to assign users to roles as translators for
 *  supported languages and to edit the list of supported languages.
 *  They can also enter translations.
 */


/* ItemTypes()
 *
 *  Return a list of all the itemtypes.
 */

function ItemTypes($itemtype_id = null) {
  global $con;

  $sql = 'SELECT * FROM itemtypes';
  if(isset($itemtype_id))
    $sql .= ' WHERE itemtype_id = ?';
  $sth = $con->prepare($sql);
  if(isset($itemtype_id))
    $sth->bind_param('i', $itemtype_id);
  $sth->execute();
  $res = $sth->get_result();
  $itemtypes = $res->fetch_all();
  $sth->close();
  return $itemtypes;

} /* end ItemTypes() */


/* GetLanguages()
 *
 *  If 'code' is specified, return a single array with 'code' and
 *  'description' fields, else a list of all the languages that are
 *  currently supported as an array keyed on 'code'.
 */

function GetLanguages($code = null) {
  global $con;

  $sql = 'SELECT * FROM language';
  if(isset($code))
    $sql .= ' WHERE code = ?';
  else
    $sql .= ' ORDER BY description';
  $sth = $con->prepare($sql);
  if(isset($code))
    $sth->bind_param('s', $code);
  $sth->execute();
  $res = $sth->get_result();
  if(isset($code)) {
    return($res->fetch_assoc());
  } else {
    $languages = [];
    while($language = $res->fetch_assoc())
      $languages[$language['code']] = $language;
    return($languages);
  }

} /* end GetLanguages() */


/* ChooseLanguages()
 *
 *  Present a form for selection of the itemtype and source and
 *  destination languages.
 *
 *  $langs lists, for a non-superuser, for which destination languages
 *  they are certified to create and edit content.
 *
 *  US English is a special case: it's the default source language and
 *  is never a destination language.				    
 */

function ChooseLanguages($langs) {
  global $user;

  $super = $user['role'] == 'super';

  $languages = GetLanguages(); // code, description
  $itemtypes = ItemTypes(); // itemtype_id, itemtype
  
  $source = "<select name=\"source\">\n";
  $destination = "<select name=\"destination\">\n";
  $itemtype = "<select name=\"itemtype\">\n";

  # Loop on languages.

  $defaultDest = null;
  if(isset($_POST)) {
    if(isset($_POST['source']))
      $defaultSource = $_POST['source'];
    else
      $defaultSource = 'en';
    if(isset($_POST['destination']))
      $defaultDest = $_POST['destination'];
    if(isset($_POST['itemtype']))
      $defaultItem = $_POST['itemtype'];
  }

  foreach($languages as $language) {
    $code = $language['code'];
    $description = $language['description'];
    $able = '';

    $selected = ($code == $defaultSource) ? ' selected' : '';
    $source .= " <option value=\"${language['code']}\"$selected>${language['description']}</option>\n";

    if($code != 'en') {
      $selected = ($code == $defaultDest) ? ' selected' : '';
      $able = (!$super && !in_array($code, $langs)) ? ' disabled' : '';
      $destination .= " <option value=\"${language['code']}\"$able$selected>${language['description']}</option>\n";
    }
  } // end loop on languages

  # Loop on itemtypes.

  foreach($itemtypes as $it) {
    $selected = ($it[0] == $defaultItem) ? ' selected' : '';
    $itemtype .= " <option value=\"${it[0]}\"$selected>${it[1]}</option>\n";
  }

  $source .= "</select>\n";
  $destination .= "</select>\n";
  $itemtype .= "</select>\n";

  print "<h2>Translate</h2>
<p style=\"font-weight: bold\">Start by selecting a source and destination language and the type of strings you intend to translate.</p>

<form method=\"POST\" class=\"challah\">
<input name=\"state\" type=\"hidden\" value=\"lang\">
<div class=\"chead\">Source language:</div><div>$source</div>
<div class=\"chead\">Destination language:</div><div>$destination</div>
<div class=\"chead\">String type:</div><div>$itemtype</div>
<div class=\"csub\"><input type=\"submit\" name=\"submit\" value=\"Select\"></div>
</form>
";

} /* end ChooseLanguages() */


/* ManageLanguages()
 *
 *  Add or edit supported languages.
 */
 
function ManageLanguages() {
  
} /* end ManageLanguages() */


/* ManageTranslators()
 *
 *  Manage translators.
 */
 
function ManageTranslators() {
  
} /* end ManageTranslators() */


/* AuthConnect()
 *
 *  Connect to the database containing PHPAuth tables.
 */

function AuthConnect() {
  global $pdo;
  if(isset($pdo))
    return;
  try {
    $pdo = new PDO(DSN, USER, PW);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
    
} /* end AuthConnect() */


/* Locals()
 *
 *  Fetch the locals for this itemtype and language.
 */

function Locals($itemtype, $language) {
  global $con;

  $sql = 'SELECT localstring, local_id, object_id
 FROM locals
 WHERE itemtype = ? AND language = ?';
  $sth = $con->prepare($sql);
  $sth->bind_param('is', $itemtype, $language);
  $sth->execute();
  $res = $sth->get_result();
  $itemtypes = $res->fetch_all();
  $sth->close();
  return $itemtypes;

} /* end Locals() */


/* Error()
 *
 *  Fatal error.
 */

function Error($s) {
  print "<p>$s</p>\n";
  exit;

} /* end Error() */


/* Translate()
 *
 *  Present a translation form.
 */
 
function Translate($opts) {

  // Get the itemtypes.itemtype value into $itemtype.
  
  $itemtype = ItemTypes($opts['itemtype']);
  $itemtype = $itemtype[0][1];

  // Get the languages.name values into $sname and $dname.

  $sname = GetLanguages($opts['source']);
  $dname = GetLanguages($opts['destination']);

  /* Fetch all the locals that match the source language and itemtype.
   * [localstring, local_id, object_id] */

  $sources = Locals($opts['itemtype'], $opts['source']);

  // Fetch all the locals that match the destination language and itemtype.
  
  $destinations = Locals($opts['itemtype'], $opts['destination']);
  $dbyobjid = [];
  foreach($destinations as $destination)
    $dbyobjid[$destination[2]] = $destination;

  print "<h2>Translating <em>$itemtype</em> elements from <em>${sname['description']}</em> to <em>${dname['description']}</em></h2>
  
<form method=\"POST\" class=\"cronut\">
<input type=\"hidden\" name=\"source\" value=\"${opts['source']}\">
<input type=\"hidden\" name=\"destination\" value=\"${opts['destination']}\">
<input type=\"hidden\" name=\"itemtype\" value=\"${opts['itemtype']}\">
<input type=\"hidden\" name=\"state\" value=\"absorb\">
";

  /* Loop on elements in the source language, adding a textarea to the form
   * for each element. If a corresponding string exists in the destination
   * language, use its 'local_id' value as the name of the textarea; if not,
   * use the <object_id>. */

  foreach($sources as $source) {

    $object_id = $source[2];

    if(array_key_exists($object_id, $dbyobjid)) {
      $destination = $dbyobjid[$source[2]];
      $value = $destination[0];
    } else {
      $value = '';
    }
    
    print "<div class=\"mute\">${source[0]}</div>
<div><textarea name=\"$object_id\" style=\"width: 100%\">$value</textarea></div>
";
  } // end loop on elements
  
  print "<div class=\"sub\">
 <input type=\"submit\" name=\"submit\" value=\"Absorb\">
 <input type=\"submit\" name=\"submit\" value=\"Cancel\">
</div>
</form>
";
  
} /* end Translate() */


/* Absorb()
 *
 *  Absorb translations.
 */

function Absorb($opts) {
   $insertions = $updates = $deletions = 0;

  // get the existing strings of this itemtype and destination language.

  $destinations = Locals($opts['itemtype'], $opts['destination']);
  $dbyobjid = [];
  foreach($destinations as $destination)
    $dbyobjid[$destination[2]] = $destination;
    
  // loop on strings in the form

  foreach($_POST as $object_id => $newvalue) {
    if(!preg_match('/^\d+$/', $object_id))
      continue;

    if(array_key_exists($object_id, $dbyobjid)) {

      // there was an existing value for this string
      
      $oldvalue = $dbyobjid[$object_id][0];
      if($newvalue != $oldvalue) {

        // the new value is different

	if(strlen($newvalue)) {

	  // update the value

          UpdateLocal([
	    'localstring' => $newvalue,
	    'object_id' => $object_id,
	    'itemtype' => $opts['itemtype'],
	    'language' => $opts['destination']
	  ]);
	  $updates++;
	} else {

	  // the new value is empty; delete

          DeleteLocal([
	    'object_id' => $object_id,
	    'itemtype' => $opts['itemtype'],
	    'language' => $opts['destination']
	  ]);
	  $deletions++;	  
	}
      }
    } elseif(strlen($newvalue)) {

      // this is a new value; insert

      InsertLocal([
        'localstring' => $newvalue,
        'object_id' => $object_id,
	'itemtype' => $opts['itemtype'],
        'language' => $opts['destination']
      ]);
      $insertions++;
    }
  } // end loop on post parameters

  // report

  if($insertions + $deletions + $updates) {
    if($insertions)
      print "<p class=\"alert\">Inserted $insertions strings.</p>\n";
    if($updates)
      print "<p class=\"alert\">Updated $updates strings.</p>\n";
    if($deletions)
      print "<p class=\"alert\">Deleted $deletions strings.</p>\n";
  } else {
    print "<p class=\"alert\">No changes.</p>\n";
  }
  
} /* end Absorb() */


/* GetTranslators()
 *
 *  Fetch rows from the 'translator' table, which maps userids to languages
 *  to represent who is authorized to perform translations for each language.
 *
 *  If a userid is provided, we return an array of their languages. If not
 *  we return an array keyed on userid to arrays of their languages.
 */

function GetTranslators($userid = null) {
  global $con;

  $sql = 'SELECT userid, lcode FROM translator';
  if(isset($userid))
    $sql .= ' WHERE userid = ?';
  $sth = $con->prepare($sql);
  if(isset($userid))
    $sth->bind_param('i', $userid);
  $sth->execute();
  $res = $sth->get_result();
  $values = $res->fetch_all();
  
  $translators = [];
  
  foreach($values as $value) {
    $lcode = $value[1];
    if(isset($userid))
      $translators[] = $lcode;
    else {
      $uid = $value[0];
      if(! array_key_exists($uid, $translators))
        $translators[$uid] = [];
      $translators[$uid][] = $lcode;
    }
  }
  $sth->close();
  return($translators);
  
} /* end GetTranslators() */


/* InsertLocal()
 *
 *  Insert one record in locals.
 */
 
function InsertLocal($opt) {
  global $con;

  $sql = 'INSERT INTO locals (localstring, language, object_id, itemtype)
 VALUES(?,?,?,?)';
 $sth = $con->prepare($sql);
 $sth->bind_param('ssii',
                  $opt['localstring'],
		  $opt['language'],
		  $opt['object_id'],
  		  $opt['itemtype']);
 $sth->execute();
 $sth->close();

} /* end InsertLocal() */


/* UpdateLocal()
 *
 *  Update one record in locals.
 */
 
function UpdateLocal($opt) {
  global $con;

  $sql = 'UPDATE locals SET localstring = ?
 WHERE object_id = ? AND itemtype = ? AND language = ?';
 $sth = $con->prepare($sql);
 $sth->bind_param('siis',
                  $opt['localstring'],
		  $opt['object_id'],
  		  $opt['itemtype'],
  		  $opt['language']);
 $sth->execute();
 $sth->close();

} /* end UpdateLocal() */


/* DeleteLocal()
 *
 *  Delete one record from locals.
 */
 
function DeleteLocal($opt) {
  global $con;

  $sql = 'DELETE FROM locals
 WHERE object_id = ? AND itemtype = ? AND language = ?';
 $sth = $con->prepare($sql);
 $sth->bind_param('iis',
		  $opt['object_id'],
  		  $opt['itemtype'],
  		  $opt['language']);
 $sth->execute();
 $sth->close();

} /* end DeleteLocal() */


/* GetUsers()
 *
 *  Return a list of all the users from the PHPAuth datastore.
 */

function GetUsers() {
  global $pdo;

  $sql = 'SELECT id AS uid, email, isactive, dt, fullname, username, role
 FROM phpauth_users';
  if(isset($filter)) {
    $conditions = '';
    foreach($filter as $name => $value) {
      if(strlen($conditions))
        $conditions .= ' AND ';
      $conditions .= "$name = $value";
    }
    $sql .= " WHERE $conditions";
  }
  $sth = $pdo->prepare($sql);
  $sth->execute();
  $users = [];
  while($user = $sth->fetch(PDO::FETCH_ASSOC)) {
    $users[$user['uid']] = $user;
  }
  return($users);

} /* end GetUsers() */


/* Translators()
 *
 *  Present a form to assign languages to users.
 *
 *  What we offer is a list of all the users with a popup menu of all the
 *  supported languages. The user uid is used for the name of each SELECT.
 */

function Translators() {
  $users = GetUsers();
  $translators = GetTranslators();
  $languages = GetLanguages();
  
  print "<h2>Assign Translators</h2>

<p style=\"font-weight: bold\">Superusers assign users to languages in this form.</p>

<form method=\"POST\" action=\"${_SERVER['SCRIPT_NAME']}\" enctype=\"multipart/form-data\" class=\"tform\">
<input type=\"hidden\" name=\"state\" value=\"st\">
<div class=\"thead\">Name</div>
<div class=\"thead\">Email</div>
<div class=\"thead\">Username</div>
<div class=\"thead\">Languages</div>
";

  # loop on users

  foreach($users as $uid => $user) {
  
    if(array_key_exists($uid, $translators))
      $tlanguages = $translators[$uid]; // this user is a translator
    else
      $tlanguages = []; // this user is not a translator

    $sname = $uid . '[]';
    $select = "<select name=\"$sname\" multiple>\n";

    foreach($languages as $language) {
      if($language['code'] == 'en')
        continue;
      if(in_array($language['code'], $tlanguages))
        $selected = ' selected';
      else
        $selected = '';
      $select .= " <option value=\"${language['code']}\"$selected>${language['description']}\n";
    }
    $select .= "</select>\n";
    print "<div>${user['fullname']}</div>
<div>${user['email']}</div>
<div>${user['username']}</div>
<div>$select</div>
";
  } // end loop on users
  
  print "<div class=\"tforms\">
 <input type=\"submit\" name=\"submit\" value=\"Absorb\">
 <input type=\"submit\" name=\"submit\" value=\"Cancel\">
</div>
</form>
";

} /* end Translators() */


/* SetTranslators()
 *
 *  Absorb setting and clearing of translators.
 *
 *  Our strategy is:
 *   load existing 'translator' records
 *   collect form data in $trans
 *   collect new translators in $inserts
 *   collect stale translators in $deletes
 *   apply database updates
 */
 
function SetTranslators() {
  $users = GetUsers();
  $translators = GetTranslators();
  $languages = GetLanguages();

  $trans = [];   # everything in the form
  $inserts = []; # all new languages
  $deletes = []; # all stale languages

  foreach($_POST as $uid => $lang) {

    if(!preg_match('/^\d+$/', $uid))
      continue;
      
    # work on this user

    if(array_key_exists($uid, $translators))
      $lcodes = $translators[$uid];
    else
      $lcodes = []; # no translator records for this user
    $trans[$uid] = [];
      
    # loop on languages they are being authorized for, looking for new
    
    foreach($lang as $lcode) {
      if(!in_array($lcode, $lcodes))
        $inserts[] = [$uid, $lcode];
      $trans[$uid][] = $lcode;
    }
    
  } // end loop on users in form

  // loop on existing translators, looking for stale

  foreach($translators as $uid => $lcodes) {
  
    if(array_key_exists($uid, $trans))
      $t = $trans[$uid];
    else
      $t = [];

    # loop on their existing authorized languages
    
    foreach($lcodes as $lcode)
      if(!in_array($lcode, $t))
        $deletes[] = [$uid, $lcode];
	
  } // end loop on form elements
  
  # Perform inserts.

  foreach($inserts as $insert) {
    InsertTranslator($insert[0], $insert[1]);
    $user = $users[$insert[0]];
    $language = $languages[$insert[1]];
    print "Added <em>${user['fullname']}</em> as a translator of <em>${language['description']}</em><br>\n";
  }

  foreach($deletes as $delete) {
    DeleteTranslator($delete[0], $delete[1]);
    $user = $users[$delete[0]];
    $language = $languages[$delete[1]];
    print "Removed <em>${user['fullname']}</em> as a translator of <em>${language['description']}</em><br>\n";
  }
  if(count($deletes) + count($inserts) == 0)
    print "<p>No changes.</p>\n";

} /* end SetTranslators() */


/* InsertTranslator()
 *
 *  Insert a row into the translator table.
 */
 
function InsertTranslator($userid, $lcode) {
  global $con;

  $sql = 'INSERT INTO translator (userid, lcode) VALUES (?,?)';
  $sth = $con->prepare($sql);
  $sth->bind_param('is', $userid, $lcode);
  $sth->execute();
  $sth->close();

} // end InsertTranslator()


/* DeleteTranslator()
 *
 *  Delete a row from the translator table.
 */
 
function DeleteTranslator($userid, $lcode) {
  global $con;

  $sql = 'DELETE FROM translator WHERE userid = ? AND lcode = ?';
  $sth = $con->prepare($sql);
  $sth->bind_param('is', $userid, $lcode);
  $sth->execute();
  $sth->close();

} // end DeleteTranslator()


?>
<!DOCTYPE html>
<html> 
<head>
  <meta charset="UTF-8">
  <title>Activist Mirror Translation</title>
  <link rel="stylesheet" href="css/surveyStyle.css">
</head>

<body>

<div id="container">

<header>
 <h1>Activist Mirror Translation Tool</h1>
</header>

<section>
<div style="width: 95%; margin-bottom: 4em">
  <img src="img/activist-images-band.jpg" style="width: 100%">
<div style="margin-top: 1em; margin-bottom: 2em">
</div>
</div>

<?php

// Main program ho!

set_include_path(get_include_path() . PATH_SEPARATOR . '../ps/project');
require 'vendor/autoload.php';

require "lib/am.php";
require "lib/db.php";

DataStoreConnect(); // connect to the Activist Mirror database
AuthConnect(); // connect to the PHPAuth data

$config = new PHPAuth\Config($pdo);
$auth = new PHPAuth\Auth($pdo, $config);

if(!$isLogged = $auth->isLogged()) {
  print "<p>You are not authenticated but must be to use this tool. Please
visit the <a href=\"../ps/\">Pattern Sphere</a> to authenticate.</p>\n";
  exit;
}
$user = $auth->getCurrentUser(true);
$languages = GetTranslators($user['id']);

if(!count($languages) && $user['role'] != 'super') {
  print "<p>Cannot confirm that you are authorized to use this tool. Contact project administration.</p>\n";
  exit;
}

$rv = 0;

if($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['submit'] != 'Cancel') {

  if($_POST['state'] == 'lang' || $_POST['state'] == 'absorb') {

    # languages and itemtype have been specified; present or absorb translations
  
    $destination = $_POST['destination'];
    $itemtype = $_POST['itemtype'];
  
    if($itemtype == 'itemtype')
      Error("You failed to specify an itemtype");
 
    if($_POST['state'] == 'absorb') {

      # absorbing translations
    
      Absorb([
       'itemtype' => $itemtype,
       'destination' => $destination
      ]);
    
    } else {

      # presenting a form for translation entry
    
      $source = $_POST['source'];
      if($source == $destination)
        Error("Source and destination languages may not be the same");
      else if($source == 'source' || $destination == 'destination')
        Error("You failed to specify a source and destination language");
      Translate([
       'itemtype' => $itemtype,
       'source' => $source,
       'destination' => $destination
      ]);
      $rv = 1;
    }
  } elseif($_POST['state'] == 'st') {

    // absorbing translators

    SetTranslators();
  }
}

if(!$rv) {
  ChooseLanguages($languages);
  if($user['role'] == 'super') // superusers assign translators
    Translators();
}
?>

</section>
</div>

<footer>
The Public Sphere Project
</footer>

</body>
</html>
