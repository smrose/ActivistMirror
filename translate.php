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
 *  GetRoles         return roles
 *  GetPatterns      return patterns
 *  Choose           select languages and item type
 *  NewString        present a form for adding a new string
 *  ManageLanguages  add and edit supported languages
 *  ManageTranslators edit the translators
 *  Locals           fetch strings of this type and language
 *  AllVerbiage      fetch verbiage in the argument language
 *  Error            fail in disgrace
 *  Translate        present the form for entering strings
 *  Absorb           absorb edited strings
 *  GetTranslators   return a list of users and the languages they support
 *  InsertLocal      insert a row into 'locals'
 *  UpdateLocal      update a row in 'locals'
 *  DeleteLocal      delete a row from 'locals'
 *  InsertVerbiage   insert a row into 'verbiage'
 *  UpdateVerbiage   update a row in 'verbiage'
 *  DeleteVerbiage   delete a row from 'verbiage'
 *  GetUsers         return array of all translators
 *  GetUser          return a translator
 *  Translators      present a form for assigning translators
 *  Users            add user
 *  SetTranslators   absorb setting of translators
 *  InsertTranslator insert a translator record
 *  DeleteTranslator delete a translator record
 *  CreateString     create a new 'en' string in locals
 *
 * NOTES
 *

 *  Strings are held in the 'locals' and 'verbiage' tables. Each row
 *  in 'locals' has 'itemtype' and 'object_id' values that determine the
 *  role it plays in the system and 'language' value that determines
 *  what language it's in. Rows in 'verbiage' have 'role' and 'pattern'
 *  values as well as 'language'.
 *
 *   CREATE TABLE locals (
 *    local_id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *    localstring varchar(2048) DEFAULT NULL,
 *    language char(2) DEFAULT NULL,
 *    itemtype int DEFAULT NULL,
 *    object_id int DEFAULT NULL,
 *   );
 *
 *   CREATE TABLE verbiage (
 *    id NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *    role int NOT NULL,
 *    pattern int,
 *    vstring varchar(1023),
 *    language char(2)
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
 *  Each authorized user is represented by a row in the 'translator' table
 *  which associates a username and role with their unique 'id':
 *
 *   CREATE TABLE translator(
 *    id integer NOT NULL PRIMARY KEY,
 *    userid varchar(16) NOT NULL,
 *    super tinyint(1) NOT NULL DEFAULT 0
 *   );
 *
 *  Each language/translator pair is represented by a row in the
 *  'ltrans' table, each row in which associates a translator.id
 *  value with a language.code value.
 *
 *   CREATE TABLE ltrans (
 *    tid integer NOT NULL,
 *    lcode char(2) NOT NULL,
 *    FOREIGN KEY(tid) REFERENCES translator(id)
 *   );
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

const VERBIAGE_T = 30;


/* ItemTypes()
 *
 *  Return a list of all the itemtypes for which there is at least one value
 *  in the "locals" table. Plus, "verbiage."
 */

function ItemTypes($itemtype_id = null) {
  global $con;

  $sql = 'SELECT *, count(*) FROM itemtypes i
 JOIN locals l ON i.itemtype_id = l.itemtype';
  if(isset($itemtype_id))
    $sql .= ' WHERE itemtype_id = ?';
  $sql .= ' GROUP BY itemtype_id';
  $sth = $con->prepare($sql);
  if(isset($itemtype_id))
    $sth->bind_param('i', $itemtype_id);
  $sth->execute();
  $res = $sth->get_result();
  $itemtypes = $res->fetch_all();
  $sth->close();
  $itemtypes[] = [VERBIAGE_T, 'verbiage'];
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


/* GetRoles()
 *
 *  Return an array of roles as associative arrays with 'id_role' and
 *  'name' fields.
 */

function GetRoles() {
  global $con;

  $sth = $con->prepare('SELECT id_role, name FROM roles ORDER BY id_role');
  $sth->execute();
  $res = $sth->get_result();
  return($res->fetch_all(MYSQLI_ASSOC));
  
} /* end GetRoles() */


/* GetPatterns()
 *
 *  Return an array of patterns as associative arrays with 'id' and
 *  'title' fields, ordered by title.
 */

function GetPatterns() {
  global $con;

  $sth = $con->prepare('SELECT id, title FROM pattern ORDER BY title');
  $sth->execute();
  $res = $sth->get_result();
  return($res->fetch_all(MYSQLI_ASSOC));
  
} /* end GetPatterns() */


/* Choose()
 *
 *  Present a form for selection of the itemtype and source and
 *  destination languages for translation.
 */

function Choose($langs) {
  global $user;

  $super = $user['super'];

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

    if($super || $code != 'en') {
      $selected = ($code == $defaultDest) ? ' selected' : '';
      $able = (!$super && !in_array($code, $langs)) ? ' disabled' : '';
      $destination .= " <option value=\"${language['code']}\"$able$selected>${language['description']}</option>\n";
    }
  } // end loop on languages

  # Loop on itemtypes.

  foreach($itemtypes as $it) {
    $selected = ($it[0] == $defaultItem) ? ' selected' : '';
    $opt = " <option value=\"${it[0]}\"$selected>${it[1]}</option>\n";
    $itemtype .= $opt;
  }

  $source .= "</select>\n";
  $destination .= "</select>\n";
  $itemtype .= "</select>\n";

  print "<h2>Translate</h2>
  
<p style=\"font-weight: bold\">Start by selecting a source and destination language and the type of strings you intend to translate.</p>
";
  if($super)
    print "<p style=\"font-weight: bold\">Choose 'en' as both the source and destination language to edit the English text of a string.</p>\n";

  print "<form method=\"POST\" class=\"challah\">
<input name=\"state\" type=\"hidden\" value=\"lang\">
<div class=\"chead\">Source language:</div><div>$source</div>
<div class=\"chead\">Destination language:</div><div>$destination</div>
<div class=\"chead\">String type:</div><div>$itemtype</div>
<div class=\"csub\"><input type=\"submit\" name=\"submit\" value=\"Select\"></div>
</form>
";

} /* end Choose() */


/* NewString()
 *
 *  Present a form for selecting an itemtype for creation of a new string.
 *
 *  A row in 'verbiage' is quite different from one in 'locals' - it needs
 *  a role and pattern to be specified.
 */

function NewString() {

  $itemtypes = ItemTypes(); // itemtype_id, itemtype
  $roles = GetRoles();
  $patterns = GetPatterns();
  

  $rolesel = "<select name=\"role\" id=\"role\" disabled>\n";
  foreach($roles as $role)
    $rolesel .= " <option value=\"{$role['id_role']}\">{$role['name']}</option>\n";
  $rolesel .= "</select>\n";

  $patternsel = "<select name=\"pattern\" id=\"pattern\" disabled>
";
  foreach($patterns as $pattern)
    $patternsel .= " <option value=\"{$pattern['id']}\">{$pattern['title']}</option>\n";
  $patternsel .= "</select>\n";

  $nitemtype = "<select name=\"itemtype\" id=\"news\">\n";
  foreach($itemtypes as $it) {
    $selected = ($it[0] == $defaultItem) ? ' selected' : '';
    $opt = " <option value=\"${it[0]}\"$selected>${it[1]}</option>\n";
    $nitemtype .= $opt;
  }
  $nitemtype .= "</select>\n";

  print "<h2>Create New String</h2>

<p style=\"font-weight: bold\">Select a string type and press the
<code>Create</code> button to create an empty new string of the selected
type.</p>

<form method=\"POST\" class=\"challah\">
 <input name=\"state\" type=\"hidden\" value=\"new\">
 
 <div class=\"chead\">String type:</div>
 <div>$nitemtype</div>
 
 <div class=\"chead\">Role:</div>
 <div>$rolesel</div>
 
 <div class=\"chead\">Pattern:</div>
 <div>$patternsel</div>

 <div class=\"csub\"><input type=\"submit\" name=\"submit\" value=\"Select\"></div>
 
</form>
";

} // end NewString()


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
  $locals = $res->fetch_all(MYSQLI_ASSOC);
  $sth->close();
  
  foreach($locals as $local)
    $r[$local['object_id']] = $local;
  return $r;

} /* end Locals() */


/* AllVerbiage()
 *
 *  Return rows from 'verbiage' in the argument language.
 */
 
function AllVerbiage($language) {
  global $con;

  $sth = $con->prepare('SELECT vstring, role, pattern, r.name AS rolename,
  p.title AS patname
 FROM verbiage v
  JOIN roles r ON v.role = r.id_role
  LEFT JOIN pattern p ON v.pattern = p.id 
 WHERE language = ?
 ORDER BY role, pattern');
  $sth->bind_param('s', $language);
  $sth->execute();
  $res = $sth->get_result();
  $verbiages = $res->fetch_all(MYSQLI_ASSOC);
  foreach($verbiages as $verbiage) {
    $k = $verbiage['role'] .
      (isset($verbiage['pattern']) ? "_{$verbiage['pattern']}" : '');
    $r[$k] = $verbiage;
  }
  return($r);
  
} /* end AllVerbiage() */


/* Error()
 *
 *  Fatal error.
 */

function Error($s) {
  print "<p class=\"error\">$s</p>\n";
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

  if($opts['itemtype'] == VERBIAGE_T) {
  
    // Fetch verbiage records matching source and destination languages.

    $sources = AllVerbiage($opts['source']);
    $destinations = AllVerbiage($opts['destination']);
  } else {

    // Fetch all the locals that match the source language and itemtype.

    $sources = Locals($opts['itemtype'], $opts['source']);
    $destinations = Locals($opts['itemtype'], $opts['destination']);
  }

  // Build a form for translating.

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

    if($opts['itemtype'] == VERBIAGE_T) {
      $k = $source['role'] .
        (isset($source['pattern']) ? "_{$source['pattern']}" : '');
      $ovalue = $source['vstring'];
      $value = (isset($destinations[$k])) ? $destinations[$k]['vstring'] : '';
      $pattern = isset($source['pattern'])
        ? ($source['pattern'] ? $source['patname'] : 'none matched')
        : 'n/a';
      $placeholder = " placeholder=\"role: {$source['rolename']}  pattern: $pattern\"";
      $title = "Role: $k, Pattern: $pattern";
    } else {
      $k = $source['object_id'];
      $ovalue = $source['localstring'];
      $value = (isset($destinations[$k])) ? $destinations[$k]['localstring'] : '';
      $placeholder = '';
      $title = "ID: $k";
    }
    print "<div class=\"mute\" title=\"$title\">$ovalue</div>
  <div><textarea name=\"$k\" style=\"width: 100%\"$placeholder>$value</textarea></div>
  ";
  } // end loop on elements

  print "<div class=\"sub\">
 <input type=\"submit\" name=\"submit\" value=\"Absorb\">
 <input type=\"submit\" name=\"submit\" value=\"Absorb and Continue\">
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
  global $user;
  
  $insertions = $updates = $deletions = 0;

  // get the existing strings of this itemtype and destination language.

  if($opts['itemtype'] == VERBIAGE_T) {
    $destinations = AllVerbiage($opts['destination']);
    $vfield = 'vstring';
  } else {
    $destinations = Locals($opts['itemtype'], $opts['destination']);
    $vfield = 'localstring';
  }
    
  // loop on strings in the form

  foreach($_POST as $k => $newvalue) {

    if(preg_match('/^(\d+)_(\d+)$/', $k, $matches)) {
      $role = $matches[1];
      $pattern = $matches[2];
    } elseif(preg_match('/^(\d+)$/', $k, $matches)) {
      if($opts['itemtype'] == VERBIAGE_T) {
        $role = $matches[1];
        $pattern = NULL;
      } else
        $object_id = $k;
    } else
      continue;

    if(isset($destinations[$k])) {

      // there was an existing value for this string
      
      $oldvalue = $destinations[$k][$vfield];
      if($newvalue != $oldvalue) {

        // the new value is different

        if(strlen($newvalue)) {

          // update the value

          if($opts['itemtype'] == VERBIAGE_T)
            UpdateVerbiage([
              'vstring' => $newvalue,
              'role' => $role,
              'pattern' => $pattern,
              'language' => $opts['destination'],
              'translator' => $user['id']
            ]);
          else
            UpdateLocal([
              'localstring' => $newvalue,
              'object_id' => $object_id,
              'itemtype' => $opts['itemtype'],
              'language' => $opts['destination'],
              'translator' => $user['id']
              ]);
          $updates++;
        } else {

          // the new value is empty; delete

          if($opts['itemtype'] == VERBIAGE_T)
            DeleteVerbiage([
              'role' => $role,
              'pattern' => $pattern,
              'language' => $opts['destination']
            ]);
          else
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

      if($opts['itemtype'] == VERBIAGE_T)
        InsertVerbiage([
          'vstring' => $newvalue,
          'role' => $role,
          'pattern' => $pattern,
          'language' => $opts['destination']
        ]);
      else
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

  $sql = 'SELECT tid, lcode
 FROM translator t
  JOIN ltrans lt ON t.id = lt.tid';
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

  $sql = 'UPDATE locals SET localstring = ?, translator = ?
 WHERE object_id = ? AND itemtype = ? AND language = ?';
 $sth = $con->prepare($sql);
 $sth->bind_param('siiis',
                  $opt['localstring'],
                  $opt['translator'],
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


/* InsertVerbiage()
 *
 *  Insert one record in 'verbiage'.
 */
 
function InsertVerbiage($opt) {
  global $con;

  $sql = 'INSERT INTO verbiage (vstring, language, role, pattern)
 VALUES(?,?,?,?)';
 $sth = $con->prepare($sql);
 $sth->bind_param('ssii',
                  $opt['vstring'],
                  $opt['language'],
                  $opt['role'],
                    $opt['pattern']);
 $sth->execute();
 $sth->close();

} /* end InsertVerbiage() */


/* UpdateVerbiage()
 *
 *  Update one record in 'verbiage'.
 */
 
function UpdateVerbiage($opt) {
  global $con;

  $sql = 'UPDATE verbiage SET vstring = ?, translator = ?
 WHERE role = ? AND language = ?';
  if(isset($opt['pattern']))
    $sql .= ' AND pattern = ?';
  else
    $sql .= ' AND pattern IS NULL';
  $sth = $con->prepare($sql);
  if(isset($opt['pattern']))
    $sth->bind_param('siisi',
                     $opt['vstring'],
                     $opt['translator'],
                     $opt['role'],
                     $opt['language'],
                     $opt['pattern']);
  else
    $sth->bind_param('siis',
                     $opt['vstring'],
                     $opt['translator'],
                     $opt['role'],
                     $opt['language']);
 $sth->execute();
 $sth->close();

} /* end UpdateVerbiage() */


/* DeleteVerbiage()
 *
 *  Delete one record from 'verbiage'.
 */
 
function DeleteVerbiage($opt) {
  global $con;

  $sql = 'DELETE FROM verbiage
 WHERE role = ? AND language = ?';
  if(isset($opt['pattern']))
    $sql .= ' AND pattern = ?';
  else
    $sql .= ' AND pattern IS NULL';
  $sth = $con->prepare($sql);
  if(isset($opt['pattern']))
    $sth->bind_param('isi',
                     $opt['role'],
                     $opt['language'],
                     $opt['pattern']);
  else
    $sth->bind_param('is',
                     $opt['role'],
                     $opt['language']);
 $sth->execute();
 $sth->close();

} /* end DeleteVerbiage() */


/* GetUsers()
 *
 *  Return a list of all the translators.
 */

function GetUsers() {
  global $con;
  
  if($r = $con->query('SELECT * FROM translator'))
    $translators = $r->fetch_all(MYSQLI_ASSOC);
  return($translators);

} /* end GetUsers() */


/* GetUser()
 *
 *  Return a translator.
 *
 *   CREATE TABLE `translator` (
 *    id integet NOT NULL AUTO_INCREMENT,
 *    userid varchar(16) NOT NULL,
 *    super tinyint(1) NOT NULL DEFAULT 0,
 *    PRIMARY KEY (id),
 *    UNIQUE KEY userid (userid)
 *   );
 */

function GetUser($username) {
  global $con;
  
  if($r = $con->execute_query('SELECT * FROM translator WHERE userid = ?', [$username]))
    $translator = $r->fetch_assoc();
  return($translator);

} /* end GetUser() */


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
<div class=\"thead\">Username</div>
<div class=\"thead\">Languages</div>
";

  # loop on users

  foreach($users as $user) {
    $id = $user['id'];
    if(array_key_exists($id, $translators))
      $tlanguages = $translators[$id]; // this user is a translator
    else
      $tlanguages = []; // this user is not a translator

    $sname = $id . '[]';
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
    print "<div title=\"{$user['id']}\">{$user['userid']}</div>
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


/* Users()
 *
 *  Add a row to the translator table.
 */

function Users($userid = NULL, $super = NULL) {
  global $con;

  if(isset($userid)) {
  
    // Create new user.

    $super = is_null($super) ? 0 : 1;
    $sth = $con->prepare('INSERT INTO translator(userid, super) VALUES(?, ?)');
    $sth->bind_param('si', $userid, $super);
    try {
      $sth->execute();
      print "<p class=\"alert\">Added user <code>$userid</code>.</p>\n";
    } catch(Exception $e) {
      $error = $e->getMessage();
      print "<p class=\"alert\">$error</p>\n";
    }
    
  } else {

    // Solicit new user.
    
    print "<h2>Add a Translator</h2>

<p style=\"font-weight: bold\">Superusers create users in this form.</p>

<form method=\"POST\" action=\"${_SERVER['SCRIPT_NAME']}\" enctype=\"multipart/form-data\" class=\"challah\">
 <input type=\"hidden\" name=\"state\" value=\"u\">
 <div class=\"tfield\">Username:</div>
 <div>
  <input type=\"text\" name=\"userid\">
 </div>
 <div class=\"tfield\">
  Super:
 </div>
 <div>
  <input type=\"checkbox\" name=\"super\" value=\"1\">
 </div>
 <div class=\"csub\">
  <input type=\"submit\" name=\"submit\" value=\"Absorb\">
 </div>
</form>
";
  }
} // end Users()


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
    print "<p class=\"alert\">Added <em>${user['userid']}</em> as a translator of <em>${language['description']}</em></p>\n";
  }

  foreach($deletes as $delete) {
    DeleteTranslator($delete[0], $delete[1]);
    $user = $users[$delete[0]];
    $language = $languages[$delete[1]];
    print "<p class=\"alert\">Removed <em>${user['userid']}</em> as a translator of <em>${language['description']}</em></p>\n";
  }
  if(count($deletes) + count($inserts) == 0)
    print "<p class=\"alert\">No changes.</p>\n";

} /* end SetTranslators() */


/* InsertTranslator()
 *
 *  Insert a row into the translator table.
 */
 
function InsertTranslator($userid, $lcode) {
  global $con;

  $sql = 'INSERT INTO ltrans (tid, lcode) VALUES (?,?)';
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


/* CreateString()
 *
 *  Create a new string.
 */

function CreateString($itemtype) {
  global $con;
  
  $sth = $con->prepare('SELECT max(object_id) FROM locals WHERE itemtype = ?');
  $sth->bind_param('i', $itemtype);
  $sth->execute();
  $res = $sth->get_result();
  $object_id = $res->fetch_column();
  $object_id++;
  
  InsertLocal([
    'localstring' => '',
    'object_id' => $object_id,
    'itemtype' => $itemtype,
    'language' => 'en'
  ]);
  print "<p class=\"alert\">Inserted new string with itemtype $itemtype and object id $object_id</p>\n";
  
} // end CreateString()


?>
<!DOCTYPE html>
<html> 
<head>
  <meta charset="UTF-8">
  <title>Activist Mirror Translation</title>
  <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Paytone+One&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Piazzolla:ital,opsz,wght@0,8..30,100..900;1,8..30,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="translate.css">  
  <script>
    function isverb(event) {
      if(news.value == verbiage) {
        role.disabled = false
        pattern.disabled = false
      } else {
        role.disabled = true
        pattern.disabled = true
      }

    } // end isverb()
  </script>
</head>

<body>

<div id="container">

<header>
 <h1>Activist Mirror Translation Tool</h1>
</header>

<section>

<?php

// Main program ho!

require "am.php";
require "db.php";

DataStoreConnect(); // connect to the Activist Mirror database

$user = GetUser($_SERVER['REMOTE_USER']);
$languages = GetTranslators($user['id']);

/* Smell test: if no user is set, or there are no languages authorized for
 * a non-superuser, stop right now. */

if(!isset($user) || (!count($languages) && !$user['super'])) {
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
      if($_POST['submit'] == 'Absorb and Continue') {
        $source = $_POST['source'];
        Translate([
         'itemtype' => $itemtype,
         'source' => $source,
         'destination' => $destination
        ]);
        $rv = 1; # suppress generate other forms
      }
    
    } else {

      # presenting a form for translation entry
    
      $source = $_POST['source'];
      if($source == $destination && !$user['super'] && $source != 'en')
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
  } elseif($_POST['state'] == 'new') {

    /* A new string. */

    if($_POST['itemtype'] == VERBIAGE_T) {

      /* Creating a new 'verbiage' string is fundamentally different from
       * any other type: 'role' and 'pattern' are specified and must be
       * unique. */

       $role = $_POST['role'];
       $pattern = $_POST['pattern'];
       if(!is_null(Verbiage($role, $pattern, 'en')))
         Error("Verbiage entries must be unique across role and pattern");
       else
         InsertVerbiage([
           'vstring' => '',
           'role' => $role,
           'pattern' => $pattern,
           'language' => 'en'
         ]);
         print "<p class=\"alert\">Added a row to the <code>verbiage</code> table.</p>\n";
    } else {
       $itemtype = $_POST['itemtype'];
       CreateString($itemtype);
    }
  } elseif($_POST['state'] == 'st') {

    // absorbing translators

    SetTranslators();
  } elseif($_POST['state'] == 'u') {

    // Absorb a new user.
      
    Users($_POST['userid'], $_POST['super']);
  }      
}

if(!$rv) {
  Choose($languages);
  NewString();
  if($user['super']) { // superusers create and assign translators
    Translators();
    Users();
  }
}
?>

</section>
</div>

<div id="brand">ACTIVIST<br>MIR<span class="a">R</span>OR</div>

<script>
<?php
 print(' verbiage = ' . VERBIAGE_T . "\n");
?>
 news = document.querySelector('#news')
 role = document.querySelector('#role')
 pattern = document.querySelector('#pattern')
 news.addEventListener('change', isverb)
</script>

</body>
</html>
