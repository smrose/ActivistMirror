<?php
/* NAME
 *
 *  suggestion.php
 *
 * CONCEPT
 *
 *  RESTful service to add sessions.suggestion.
 *
 * NOTES
 *
 *  URL is .../suggestion.php/session/<id>
 *
 *  Payload is POSTed JSON.
 */
 
include 'am.php';
set_error_handler('oops');


/* oops()
 *
 *  Error handler.
 */

function oops($errno, $errstr) {
  error_log($errstr);
  http_response_code(500);
  echo json_encode(['error' => $errstr]);
  
} // end oops()


header('Content-Type: application/json');

# Extract the session_id from the URL.

if(! isset($_SERVER['PATH_INFO'])) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
preg_match('/\/session\/(\d+)$/', $_SERVER['PATH_INFO'], $match);

if(!isset($match[1])) {

  // Bad request.

  http_response_code(400);
  error_log("Bad request");
  echo json_encode(['error' => 'Bad request']);
  exit();
}
$session_id = $match[1];

# Check if the session_id corresponds to a session.
  
DataStoreConnect();
  
try {
  $res = $con->query("SELECT count(*) AS count, rando FROM sessions WHERE session_id = $session_id");
} catch(Exception $e) {

  // Server error.
  
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
  exit();
}
$session = $res->fetch_assoc();

if($session['count'] != 1) {

  // Not found.
  
  http_response_code(404);
  error_log("Session $session_id not found");
  echo json_encode(['error' => 'Session not found']);
  exit();
}

# Absorb the content.

$data = json_decode(file_get_contents('php://input'), true);
$rando = $data['rando'];
if($rando != $session['rando']) {
  http_response_code();
  error_log("rando values don't match");
  echo json_encode(['error' => "rando values don't match"]);
}
$suggestion = $data['suggestion'];

# Update the record.

$sth = $con->prepare('UPDATE sessions SET suggestion = ? WHERE session_id = ?');
$sth->bind_param('si', $suggestion, $session_id);
$sth->execute();
