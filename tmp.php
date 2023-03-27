<?php
include "lib/am.php";
$debug = 2;
DataStoreConnect();
$title = LocalString('es', MESSAGES, TITLE);
$intro = LocalString('es', MESSAGES, INTRO);
?>
<!doctype html>
<html lang=eh">
<head>
 <title>tmp</title>
</head>
<body>

<h1><?=$title?></h1>

<p><?=$intro?></p>

</body>
</html>
