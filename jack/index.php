<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Jack Switch</title>
  <link rel="stylesheet" type="text/css" href="jack_common.css"/>
</head>	
<body>
<!-- <h1>Shed Matrix.</h1> -->

<script type="application/javascript">
	<!--
	function confirmDelete()
	{
		var agree=confirm("Are you sure you want to delete?");
		if (agree) return true ;
		else return false ;
	}
	
	var clock = setInterval(function(){updateClock()},1000);
	function updateClock()
	{
	    document.getElementById("clock").innerHTML = new Date();
	}

	-->
</script>
<noscript><p>Your browser either does not support JavaScript, or has JavaScript turned off.</p></noscript>
	
<?php


require_once "jack_patching.php";
require_once "jack_command.php";
require_once "commandproc.php";

session_start();
$h = new HtmlState();
$matrixCmds = new MatrixDrawCommands();
$cfgCmds = new ConfigureCommands();

PopulateState();

ProcessCommands(ExtractKeyCmds($_GET + $_POST));

if (ApplyPatching())
{
	PopulateState();
}

//echo("<br><hr>\n");

$h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));
//$h->LeafElem("h3", array(), "Goto Matrix");
$h->Elem("p");
foreach($matrixCmds->Get() as $name => $cmd)
{
	$h->LeafElem("input", array("type"=>"submit", "value"=>$name, "class"=>"btnNav",
					"name"=>$cmd));	
}
$h->ElemEnd(); // p
$h->ElemEnd(); // form

ProcessDeferredCommands();

/////////////////////////////////


$h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));

$h->LeafElem("h3", array(), "Configure");
$h->Elem("p");
// echo("<br><hr>\n");

foreach($cfgCmds->Get() as $name => $cmd)
{
	$h->LeafElem("input", array("type"=>"submit", "value"=>$name, "class"=>"btnNav", "name"=>$cmd));
}
$h->ElemEnd(); // p
$h->ElemEnd(); // form

//////////////////////////////////

$h->LeafElem("p", array("id"=>"clock"), "");

//////////////////////////////////
$h->Elem("p");
$h->LeafElem("br");

//$h->Text($patchLog);

//$h->LeafElem("br");

//$h->Text(var_export($_SERVER, true));

ClearSessionCommands();
$h->__destruct();
?>
</body>
</html>
