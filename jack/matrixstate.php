<?php
header("Content-type: text/plain; charset=utf8");
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

$patchLog = "";
function DoNothing($foo) {}
$debug = "DoNothing";

const kLogFile = "/var/www/jack/patchlog.txt";

function LogLine($line)
{
	global $logFile;
	echo($line."\n");
	fwrite($logFile, date(DATE_ATOM)." ".$line."\n");
	fflush($logFile);
}

require_once "jack_matrix.php";
require_once "jack_patching.php";
require_once "commandproc.php";

AddFixedCommand(new GetMatrixState(true, false), kMatrixStateCmd);
AddFixedCommand(new ChangePatch(), kChangePatchCmd);
ProcessCommands(ExtractKeyCmds($_GET));
ProcessDeferredCommands();

if ($patchLog != "")
{
	global $logFile;
	global $debug;
	$logFile = fopen(kLogFile, "a");
	$debug = "LogLine";
	Debug($patchLog);
	// ProcessCommands($cmd);

	if (ApplyPatching())
	{
		PopulateState();
	}
};


?>
