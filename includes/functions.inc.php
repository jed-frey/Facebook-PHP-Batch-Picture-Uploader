<?php
# Misc program functions
# parseParameters - Parse input parameters. Taken from the comments on the getopts page.
# Input: nothing
# Output: array of input parameters
function parseParameters($noopt = array()) {
	$result = array();
	$params = $GLOBALS['argv'];
	// could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
	reset($params);
	while (list($tmp, $p) = each($params)) {
		if ($p{0} == '-') {
			$pname = substr($p, 1);
			$value = true;
			if ($pname{0} == '-') {
				// long-opt (--<param>)
				$pname = substr($pname, 1);
				if (strpos($p, '=') !== false) {
					// value specified inline (--<param>=<value>)
					list($pname, $value) = explode('=', substr($p, 2), 2);
				}
			}
			// check if next parameter is a descriptor or a value
			$nextparm = current($params);
			if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') list($tmp, $value) = each($params);
			$result[$pname] = $value;
		} else {
			// param doesn't belong to any option
			$result[] = $p;
		}
	}
	return $result;
}
# arrayMutate - Mutates array so that it can be easily used with array_search and other array functions
# Reversible so $arrays==arrayMutate(arrayMutate($arrays))
# Input: $arrays - Original array of arrays
# Output: Mutated array, $arrays[0][$key]=$value => $arrays[$key][0]=$value
function arrayMutate($arrays) {
	$arrays_final = array(); # Set Albums as array.
	if (is_array($arrays)) {
		foreach($arrays as $k1 => $array) {
			foreach($array as $k2 => $value) {
				$arrays_final[$k2][$k1] = $value;
			}
		}
	}
	# Return array.
	return $arrays_final;
}
# disp - Display messages according to verbosity level. Any message with a level <=1 will cause the program to exit
# Input: $message message to display
#		 $level display level of message. If verbrosity is >= to the display level, the message will be displayed
function disp($message, $level) {
	global $verbosity; # Get verbrosity level.
	# If the level of the message is less than the vebrosity level, display the message.
	# If verbrosity level >=3, display the duration
	$message = (($verbosity >= 3 && $level <= $verbosity) ? " (" . getDuration($verbosity) . " s) " : "") . (($level <= $verbosity) ? $message : "");
	echo empty($message) ? "" : $message . "\n";
	if ($level <= 1) die("\n");
}
# getDuration - Calculate diration between events.
# Input: verbrosity
function getDuration($verbosity) {
	global $start_time;
	$elapsed = sprintf("%5.3f",microtime(true) - ($start_time));
	# For Odd verbrosity, just display time since the beginning. For Evens, show elapsed time between events.
	if (($verbosity % 2) == 0) $start_time = microtime(true);
	return $elapsed;
}
