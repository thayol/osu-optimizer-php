<?php
// this could have been done better but whatever
header ('Content-Type: image/png');

$file = "./black.png";

if (!empty($_GET["path"]) && file_exists($_GET["path"]) && !is_dir($_GET["path"]))
{
	$file = $_GET["path"];
}

echo file_get_contents($file);
