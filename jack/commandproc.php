<?php

const kCommands = "commands";
const kDeferred = "zzzDeferred";



function ValidateParams(&$params, $types)
{
    Debug($types);
    Debug($params);
    if (count($types) == 0)
    {
        $params = null;
        return true;
    }
    if (count($params)!= count($types))
    {
        return false;
    }
    for($i = 0; $i < count($types); ++$i)
    {
        $ok = $types[$i] == Command::kIntParam && is_numeric($params[$i]);
        $ok |= $types[$i] == Command::kStringParam && is_string($params[$i]);
        if (!$ok)
        {
            Debug($params[$i]." not a ".$types[$i]);
            return false;
        }
    }
    return true;
}

function ProcessCommands($cmdIds)
{
    global $_SESSION;
    global $deferredCommands;
    global $deferredParams;
    global $fixedCommands;
    global $persistCommands;

    $processDeferred = false;
    if (is_array($cmdIds))
    {
        $commandIds = &$cmdIds;
    }
    elseif ($cmdIds == kDeferred)
    {
        $commandIds = $deferredParams;
        $deferredParams = array();
        $processDeferred = true;
    }
    else
    {
        $commandIds = array($cmdIds);
    }

    if ($processDeferred)
    {
        $commands = &$deferredCommands;        
    }
    else
    {
        $commands = &$_SESSION[kCommands];
    }

    if (!isset($commands))
    {
        $commands = array();
        Debug("No commands in session");
    }
    ksort($commandIds);
    $persistCount = 0;
    foreach ($commandIds as $id=>$param)
    {
        if ($pos = strpos($id, kCmdPreOrder))
        {
            $id = substr($id, $pos+1);
        }
        if (isset($commands[$id]))
        {
            Debug("Processing session command, $id");
            $command = $commands[$id];
            $isFixed = false;
        }
        else if (!$processDeferred && isset($fixedCommands[$id]))
        {
            Debug("Processing fixed command, $id");
            $command = $fixedCommands[$id];
            $isFixed = true;
        }
        
        if (isset($command))
        {
            if (ValidateParams($param, $command->ParamType()))
            {
                if (!$processDeferred && $command->IsPersistent())
                {
                    if ($isFixed)
                    {
                        $persistCommands[++$persistCount.kCmdPreOrder.kCmdPrefix.$id] = $param;
                    }
                    else
                    {
                        $persistCommands[++$persistCount.kCmdPreOrder.AddSessionCommand($command)] = $param;
                    }
                }
                
                if (!$command->IsDeferred() || $processDeferred)
                {
                    $command->Exec($param);
                }
                else
                {
                    AddDeferredCommand($command, $param);
                }
            }
            else
            {
                //echo("Unable to process command, id $id: : Parameter types do not match");
                Debug("Unable to process command, id $id: Parameter mismatch");
            }
        }
        else
        {
            Debug("Unable to process command, id $id not found");
        }
    }
}

function ProcessDeferredCommands()
{
    ProcessCommands(kDeferred);
}

function ExtractKeyCmds($var)
{
    $kCmdPrefixLen = strlen(kCmdPrefix);
    
    $result = array();
    
    foreach($var as $key => $value)
    {
        if (FALSE !== $cmdpos = strpos($key, kCmdPrefix))
        {
            $cmd = substr($key, $cmdpos + $kCmdPrefixLen);
            
            $sepIdx = strpos($cmd, kParamSeparator);
            if ($sepIdx)
            {
                $val = explode(kParamSeparator, substr($cmd, $sepIdx +1));
                $cmd = substr($cmd, 0, $sepIdx);
            }
            else
            {
                $val = array($value);
            }
            if ($preOrd = strstr($key, kCmdPreOrder, true))
            {
                Debug($key);
                $cmd = $preOrd.kCmdPreOrder.$cmd;
            }
            Debug ("$cmd => $value Added from key, $key<br>\n");
            $result[$cmd] = $val;
        }
        else
        {
            Debug($key." Ignored<br>\n");
        }
    }
    return $result;
}

?>