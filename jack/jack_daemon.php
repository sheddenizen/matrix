#!/usr/bin/php5
<?php

declare(ticks = 1);

// Log file path
const kLogFile = "/var/www/html/jack/patchlog.txt";
// When log file reaches size limit, it is archived to this file, replacing the previous
const kLogFileArch = "/var/www/html/jack/patchlog.txt.1";
// Threshold for archival
const kLogSizeLimit = 1000000;

$logFile = NULL;
$logStat = stat(kLogFile);
$logSize = $logStat ? $logStat["size"] : 0;

function LogLine($line)
{
    global $logFile;
    global $logSize;
    
    if ($logSize >= kLogSizeLimit)
    {
        if (!is_null($logFile))
        {
            fclose($logFile);
            $logFile = NULL;
            $logSize = 0;
        }
        rename(kLogFile, kLogFileArch);
    }
    if (is_null($logFile))
    {
        $logFile = fopen(kLogFile, "a");
    }
    echo($line."\n");
    $utime = microtime(true); // Seconds 1970 in floating point
    $line = date("y-m-d H:i:s", $utime).".".sprintf('%04d', ($utime - floor($utime)) * 10000)." ".$line."\n";
    fwrite($logFile, $line);
    $logSize += strlen($line);
    fflush($logFile);
}

$debug = "LogLine";

require_once "jack_patching.php";

function HandleUsr1( integer $signo , mixed $signinfo )
{
    echo("Got signal ".$signo."/n");
}

pcntl_signal(SIGUSR1, "HandleUsr1", false);

while (1)
{
    echo ("Patching daemon\n");
    $lsp = popen ("/var/www/html/jack/jack_evmon", "r");
    
    $newPort = false;
    $removedPort = false;
    $newConnect = false;
    $newDisconnect = false;

    if ($lsp !== FALSE)
    {
        echo ("Monitoring...\n");
        $res = FALSE;
        while (!feof($lsp))
        {
            $res = rtrim(fgets($lsp, 256));
            
            pcntl_signal_dispatch();
            
            if ($res !==false)
            {
                $newPort |= preg_match("/^Port.* registered/", $res);
                $removedPort |= preg_match("/^Port.* unregistered/", $res);
                $newConnect = preg_match("/^Ports.* connected/", $res);
                $newDisconnect = preg_match("/^Port.* disconnected/", $res);
            
                if ($newPort || $removedPort || $newConnect || $newDisconnect)
                {
                    LogLine($res);
                }
                
                if (preg_match("/^Graph reordered/", $res))
                {
                    if ($newPort || $removedPort)
                    {
                        PopulateState();
                    }
                    if ($newPort)
                    {
                        if (ApplyPatching())
                        {
                            PopulateState();
                        }
                        $newPort = false;
                    }
                    $newPort = $removedPort = false;
                }
            }
            else
            {
                LogLine("Failed to read line");
            }
        }
        pclose($lsp);
    }
    sleep(5);
    LogLine("Broken pipe");
} 

fclose($logFile);

?>
