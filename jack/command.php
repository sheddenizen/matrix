<?php

const kCmdPrefix = "cmd-";
const kParamSeparator = "_";
const kCmdIdLen = 8;
const kCmdPreOrder = "-";
$nextSessionCmds = array();
$deferredCommands = array();
$deferredParams = array();
$fixedCommands = array();
$persistCommands = array();

$cmdIdPre = substr(uniqid(),-4);
$cmdIdSuf = 0;

interface Command
{
    const kIntParam = "int";
    const kStringParam = "string";

    public function Exec($value = null);
    public function IsDeferred();
    public function IsPersistent();
    public function ParamType();
};

abstract class UpdateCommand implements Command
{
    abstract public function Exec($value = null);
    public function IsDeferred()
    {
        return false;
    }
    public function IsPersistent()
    {
        return false;
    }
    public function ParamType()
    {
        return array();
    }
};

abstract class DisplayCommand implements Command
{
    abstract public function Exec($value = null);
    public function IsDeferred()
    {
        return true;
    }
    public function ParamType()
    {
        return array();
    }
    public function IsPersistent()
    {
        return false;
    }
};

function AddDeferredCommand($command, $param)
{
    global $deferredCommands;
    global $deferredParams;
    
    $idx = count($deferredCommands);
    $deferredCommands[$idx] = $command;
    $deferredParams[$idx] = $param;
}

function AddSessionCommand($command, $prefix = true)
{
    global $nextSessionCmds;
    global $cmdIdPre;
    global $cmdIdSuf;

    if (!isset($nextSessionCmds))
    {
        $nextSessionCmds = array();
    }
    $id = $cmdIdPre.substr("0000$cmdIdSuf",-4);
    ++$cmdIdSuf;
    $nextSessionCmds[$id] = $command;
    if ($prefix)
    {
        return kCmdPrefix.$id;
    }
    else
    {
        return $id;
    }
}

function ClearSessionCommands()
{
    global $nextSessionCmds;
    global $_SESSION;
    $_SESSION[kCommands] = $nextSessionCmds;
}

function AddFixedCommand($command, $id)
{
    global $fixedCommands;
    assert(!isset($fixedCommands[$id]));
    $fixedCommands[$id] = $command;
    return kCmdPrefix.$id;
}

function GetPersistUrl()
{
    global $persistCommands;
    $url = $_SERVER["PHP_SELF"];
    $first = true;
    foreach($persistCommands as $cmd=>$value)
    {
        $url .= ($first ? "?" : "&")."$cmd=$value";
        $first = false;
    }
    return $url;
}

function AddPersistHiddenFields()
{
    $h = new HtmlState();
    global $persistCommands;
    foreach($persistCommands as $cmd=>$value)
    {
        $h->LeafElem("input", array("type"=>"hidden","name"=>$cmd,"value"=>implode(kParamSeparator, $value)));
    }
}

?>