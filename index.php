<?php
// imagine Bob. Bob has a slow computer
// but he likes osu! very much. his HDD
// is nearly failing, but the script will
// optimize his experience eventually.
// trust me, 83 hours is still not enough
// for the likes of Bob.
set_time_limit(298800);

// around 13 000 maps, the new parser
// exhausted the default 512MB limit,
// this might need to be adjusted in
// the future
ini_set('memory_limit', '1024M');

// the entry point
require "main.php";