<?php
// Change Change below to switch between rrd_graph() and RRDGraph().
// - rrd_graph() requires writing to file and allows for cached images.
// - RRDGraph() supports direct output of image without any intermediate files.
$enablecache = false; // Set false if cpu is fast enough to not require cache.

// Get params if specified in the URL - otherwise assume some sensible defaults:
$host = isset($_GET['host']) ? htmlspecialchars($_GET['host']) : "palm";
$res = isset($_GET['res']) ? htmlspecialchars($_GET['res']) : "wan";
$start = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 1;
$end = isset($_GET['end']) ? htmlspecialchars($_GET['end']) : 0;
$type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : "b"; 
$ds0 = str_pad(isset($_GET['ds0']) ? htmlspecialchars($_GET['ds0']) : "input", 10, " ");
$ds1 = str_pad(isset($_GET['ds1']) ? htmlspecialchars($_GET['ds1']) : "output", 10, " ");
$ds0color = isset($_GET['ds0color']) ? htmlspecialchars($_GET['ds0color']) : "00FF00";
$ds1color = isset($_GET['ds1color']) ? htmlspecialchars($_GET['ds1color']) : "0000FF";

$rrdfile = $host . "." . $res . ".rrd";

// At some point, we should check if the input is a valid AT Style date/time.
// For now, we just parse $start and $end text as days only. Additionally,
// convert the start and end "days" into "hours" as suggested by rrdgraph.
// "day", "month" and "year" are inaccurate date formats, but "hour" is exact.
if (!is_numeric($start)) {
	$start = 1;
};
$startstr = "-" . $start * 24 . "h";

if (!is_numeric($end)) {
	$endstr = "now";
	$end = 0;
} elseif ($end >= $start) { // end needs to be smaller than start ofcourse
	$endstr = "now";
	$end = 0;
} else {
	$endstr = "-" . $end * 24 . "h";
};

// Default to average values in the Options unless the start time is less
// than or equal to 2 days. Otherwise use "LAST" which has the highest data
// resolution but only goes back about 66 hours by default.
$datapoint = "AVERAGE";
$datatext = "Average";
if ($start <= 2) {
	$datapoint = "LAST";
	$datatext = "Last";
};

// This requires more work to get the graphs to look better. We need two
// distinct methods to handle the Bytes graphs (ie: Interfaces, Disk IO)
// and to handle the percentage graphs (ie: CPU, RAM, disk space)
if (strtolower($type) == "p") {
	// $datatype = "Percent";
	$dataformat = " %6.1lf %%   ";
} elseif (strtolower($type) == "v") {
	// $datatype = "Volts";
	$dataformat = " %6.1lf V   ";
} elseif (strtolower($type) == "h") {
        // $datatype = "Hertz";
        $dataformat = " %6.1lf Hz  ";
} else { 
	//$datatype = "Bytes";
	$dataformat = "%6.1lf %sB/s";
};

// Some text padding to make the table line up more neatly in case ds0
// or ds1 are more than 10 chars:
if (strlen($ds0) != strlen($ds1)) {
	$ds0 = str_pad($ds0, strlen($ds1), " ");
	$ds1 = str_pad($ds1, strlen($ds0), " ");
};

// The actual RRDGRAPH OPTIONS getting set:
$options = array(
	"--start", $startstr,
	"--end", $endstr,
	"--title=" . $host . " - " . $res . " - " . ($start - $end) . " day(s)",
	"--lower-limit=0",
	"--upper-limit=100",
	"--width=450",
	"--height=120",
	"--slope-mode",
	"DEF:" . $ds0 . "=" . $rrdfile . ":ds0:" . $datapoint,
	"DEF:" . $ds1 . "=" . $rrdfile . ":ds1:" . $datapoint,
	"VDEF:min0=" . $ds0 . ",MINIMUM",
	"VDEF:max0=" . $ds0 . ",MAXIMUM",
	"VDEF:avg0=" . $ds0 . "," . $datapoint,
	"VDEF:min1=" . $ds1 . ",MINIMUM",
	"VDEF:max1=" . $ds1 . ",MAXIMUM",
	"VDEF:avg1=" . $ds1 . "," . $datapoint,
	"COMMENT:\t\t   Minimum       Maximum       " . $datatext . "\\n",
	"LINE:" . $ds0 . "#" . $ds0color . ":" . $ds0 . "\t",
	"GPRINT:min0:" . $dataformat,
	"GPRINT:max0:" . $dataformat,
	"GPRINT:avg0:" . $dataformat,
	"COMMENT:\\n",
	"LINE:" . $ds1 . "#" . $ds1color . ":" . $ds1 . "\t",
	"GPRINT:min1:" . $dataformat,
	"GPRINT:max1:" . $dataformat,
	"GPRINT:avg1:" . $dataformat,
	"COMMENT:\\n",
);

// Output method section 
// - use either rrd_graph() (enablecache = True)
// - or use RRDGraph() (enablecache = False)

// First, set expire headers so the client doesn't cache the image too long.
header("Content-Type: image/png");
header("Expires: 300");

if ($enablecache) {
	// rrd_graph cannot output image data directly, as such we need to 
	// generate the actual graph into a file and then read-in that file to
	// output to the client.
	$fileName = "cache/" . $host . "-" . $res . "-" . $start . "-" . $end . ".png";

	// Check cache file modification time and only generate graph if expired.
	// If the data span is 1 days old, expire after 5 minutes.
	// If the data span is 30 days old, expire after 30 minutes.
	// Else expire after 2 hours.

	if (file_exists($fileName) && $end == 0 && in_array($start, array(1, 7, 30, 365))) {
		// File exists and matches standards, checking modification time
		if (time()-filemtime($fileName) > 7200) {
			// File is more than 2 hours minutes old
			$expired = True;
		} elseif ($start <= 30 && time()-filemtime($fileName) > 1800) {
			// File is more than 30 minutes old
			$expired = True;
		} elseif ($start <= 1 && time()-filemtime($fileName) > 300) {
			// File is more than 5 minutes old.
			$expired = True;
		} else {
			// any other cases, assume expired.
			$expired = False;
		};
	} else {
		// File does not exist or not matching standards, marking as "expired"
		$expired = True;
	};
	
	// Only generate a new graph if the Expired flag is True.
	if ($expired) {
		rrd_graph($fileName, $options);
		// Sleep 250ms to allow file write to complete.
		usleep(250000);
	};

	//header("Content-Length: " . filesize($fileName));

	// Dump the png file to the client
	readfile($fileName);

	// Do not preserve/cache graphs with non-standard values
	if (!in_array($start, array(1, 7, 30, 365)) || $end != 0) {
		unlink($fileName);
	};
} else {
	// Create the graph (no caching) and directly dump it to the client!
	$graphObj = new RRDGraph('-');
	$graphObj->setOptions($options);
	$res = $graphObj->saveVerbose();
	echo $res['image'];
};

?>
