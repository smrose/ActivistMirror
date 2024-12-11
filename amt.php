<?php
/*
 * NAME
 *
 *  amt.php
 *
 * CONCEPT
 *
 *  Program logic for the Activist Mirror translate application.
 *
 * FUNCTIONS
 *
 *  ItemTypes          return a list of all the itemtypes
 *  GetRoles           return roles
 *  GetPatterns        return patterns
 *  Locals             fetch strings of this type and language
 *  AllVerbiage        fetch verbiage in the argument language
 *  GetTranslators     return a list of users and the languages they support
 *  InsertLocal        insert a row into 'locals'
 *  UpdateLocal        update a row in 'locals'
 *  DeleteLocal        delete a row from 'locals'
 *  InsertVerbiage     insert a row into 'verbiage'
 *  UpdateVerbiage     update a row in 'verbiage'
 *  DeleteVerbiage     delete a row from 'verbiage'
 *  GetUsers           return array of all translators
 *  InsertUser         insert one user/translator
 *  InsertTranslator   insert a translator record
 *  DeleteTranslator   delete a translator record
 *  CreateString       create a new 'en' string in locals
 *  UpdateLanguage     set language.active
 */


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
  $params = [];
  if(isset($itemtype_id))
    $params = [$itemtype_id];
  try {
    $sth = $con->prepare($sql);
    $sth->execute($params);
    $itemtypes = $sth->fetchAll();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  $itemtypes[] = [VERBIAGE_T, 'verbiage'];
  return $itemtypes;

} /* end ItemTypes() */


/* GetRoles()
 *
 *  Return an array of roles as associative arrays with 'id_role' and
 *  'name' fields.
 */

function GetRoles() {
  global $con;

  try {
    $sth = $con->prepare('SELECT id_role, name FROM roles ORDER BY id_role');
    $sth->execute();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  return $sth->fetchAll(PDO::FETCH_ASSOC);
  
} /* end GetRoles() */


/* GetPatterns()
 *
 *  Return an array of patterns as associative arrays with 'id' and
 *  'title' fields, ordered by title.
 */

function GetPatterns() {
  global $con;

  try {
    $sth = $con->prepare('SELECT id, title FROM pattern ORDER BY title');
    $sth->execute();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  return $sth->fetchAll(PDO::FETCH_ASSOC);
  
} /* end GetPatterns() */


/* Locals()
 *
 *  Fetch the locals for this itemtype and language.
 */

function Locals($itemtype, $language) {
  global $con;

  $sql = 'SELECT localstring, local_id, object_id
 FROM locals
 WHERE itemtype = ? AND language = ?';
  $param = [$itemtype, $language];
  try {
    $sth = $con->prepare($sql);
    $sth->execute($param);
    $locals = $sth->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  foreach($locals as $local)
    $r[$local['object_id']] = $local;
  return $r;

} /* end Locals() */


/* AllVerbiage()
 *
 *  Return rows from 'verbiage' in the argument language. What we return
 *  is an array of arrays, sorted on role and pattern, with these fields:
 *
 *     vstring  string
 *        role  numeric role id
 *     pattern  numeric id
 *    rolename  roles.name
 *     patname  pattern.title
 */
 
function AllVerbiage($language) {
  global $con;

  $sql = 'SELECT vstring, role, pattern, r.name AS rolename,
  p.title AS patname
 FROM verbiage v
  JOIN roles r ON v.role = r.id_role
  LEFT JOIN pattern p ON v.pattern = p.id 
 WHERE language = ?
 ORDER BY role, pattern';

 try {
    $sth = $con->prepare($sql);
    $sth->execute([$language]);
    $verbiages = $sth->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
    
  foreach($verbiages as $verbiage) {
    $k = $verbiage['role'] .
      (isset($verbiage['pattern']) ? "_{$verbiage['pattern']}" : '');
    $r[$k] = $verbiage;
  }
  return $r;
  
} /* end AllVerbiage() */


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
  $params = isset($userid) ? [$userid] : [];
  try {
    $sth = $con->prepare($sql);
    $sth->execute($params);
    $values = $sth->fetchAll();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
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
  return $translators;
  
} /* end GetTranslators() */


/* InsertLocal()
 *
 *  Insert one record in locals.
 */
 
function InsertLocal($opt) {
  global $con;

  $sql = 'INSERT INTO locals (localstring, language, object_id, itemtype)
 VALUES(?,?,?,?)';
 try {
   $sth = $con->prepare($sql);
   $sth->execute([
    $opt['localstring'], $opt['language'], $opt['object_id'], $opt['itemtype']
   ]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  
} /* end InsertLocal() */


/* UpdateLocal()
 *
 *  Update one record in locals.
 */
 
function UpdateLocal($opt) {
  global $con;

  $sql = 'UPDATE locals SET localstring = ?, translator = ?
 WHERE object_id = ? AND itemtype = ? AND language = ?';
  try {
    $sth = $con->prepare($sql);
    $sth->execute([$opt['localstring'], $opt['translator'], $opt['object_id'], $opt['itemtype'], $opt['language']]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} /* end UpdateLocal() */


/* DeleteLocal()
 *
 *  Delete one record from locals.
 */
 
function DeleteLocal($opt) {
  global $con;

  $sql = 'DELETE FROM locals
 WHERE object_id = ? AND itemtype = ? AND language = ?';
  try {
    $sth = $con->prepare($sql);
    $sth->execute([$opt['object_id'], $opt['itemtype'], $opt['language']]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} /* end DeleteLocal() */


/* InsertVerbiage()
 *
 *  Insert one record in 'verbiage'.
 */
 
function InsertVerbiage($opt) {
  global $con;

  $sql = 'INSERT INTO verbiage (vstring, language, role, pattern)
 VALUES(?,?,?,?)';

  try {
    $sth = $con->prepare($sql);
    $sth->execute([$opt['vstring'], $opt['language'], $opt['role'], $opt['pattern']]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

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

  $params = [
    $opt['vstring'],
    $opt['translator'],
    $opt['role'],
    $opt['language']
  ];

  if(isset($opt['pattern']))
    $params[] = $opt['pattern'];

  try {		     
    $sth = $con->prepare($sql);
    $sth->execute($params);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} /* end UpdateVerbiage() */


/* DeleteVerbiage()
 *
 *  Delete one record from 'verbiage'.
 */
 
function DeleteVerbiage($opt) {
  global $con;

  $params = [$opt['role'], $opt['language']];
  $sql = 'DELETE FROM verbiage
 WHERE role = ? AND language = ?';
  if(isset($opt['pattern'])) {
    $sql .= ' AND pattern = ?';
    $params[] = $opt['pattern'];
  } else
    $sql .= ' AND pattern IS NULL';

  try {
    $sth = $con->prepare($sql);
    $sth->execute($params);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} /* end DeleteVerbiage() */


/* GetUsers()
 *
 *  Return a list of all the translators.
 */

function GetUsers() {
  global $con;
  
  if($r = $con->query('SELECT * FROM translator'))
    $translators = $r->fetchAll(PDO::FETCH_ASSOC);
  return($translators);

} /* end GetUsers() */


/* InsertUser()
 *
 *  Insert a user record.
 *
 */

function InsertUser($userid, $super) {
  global $con;

  $super = is_null($super) ? 0 : 1;

  try {
    $sth = $con->prepare('INSERT INTO translator(userid, super) VALUES(?, ?)');
    $sth->execute([$userid, $super]);
  } catch(Exception $e) {
    $error = $e->getMessage();
    return $error;
  }
  return "Added user <code>$userid</code>.";
  
} /* end InsertUser() /*


/* InsertTranslator()
 *
 *  Insert a row into the translator table.
 */
 
function InsertTranslator($userid, $lcode) {
  global $con;

  $sql = 'INSERT INTO ltrans (tid, lcode) VALUES (?,?)';
  try {
    $sth = $con->prepare($sql);
    $sth->execute([$userid, $lcode]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} // end InsertTranslator()


/* DeleteTranslator()
 *
 *  Delete a row from the translator table.
 */
 
function DeleteTranslator($userid, $lcode) {
  global $con;

  $sql = 'DELETE FROM translator WHERE userid = ? AND lcode = ?';
  try {
    $sth = $con->prepare($sql);
    $sth->execute([$userid, $lcode]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }

} // end DeleteTranslator()


/* CreateString()
 *
 *  Create a new string.
 */

function CreateString($itemtype) {
  global $con;
  
  try {
    $sth = $con->prepare('SELECT max(object_id) FROM locals WHERE itemtype = ?');
    $sth->execute([$itemtype]);
    $object_id = $sth->fetch();
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  }
  $object_id = $object_id[0];
  $object_id++;
  
  InsertLocal([
    'localstring' => '',
    'object_id' => $object_id,
    'itemtype' => $itemtype,
    'language' => 'en'
  ]);
  return "Inserted new string with itemtype <code>$itemtype</code> and object id <code>$object_id</code>";
  
} // end CreateString()


/* UpdateLanguage()
 *
 *  Set language.active for the argument language.
 */

function UpdateLanguage($code, $active) {
  global $con;

  try {
    $sth = $con->prepare('UPDATE language SET active = ? WHERE code = ?');
    $sth->execute([$active, $code]);
  } catch(PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
  } 

} // end UpdateLanguage()
