<?php
require_once "jack_cfg.php";
require_once "utility.php";

global $dblink;

# Establish connection to Data Base
$dblink = mysqli_connect($sql_server, $sql_login, $sql_pass);

if (!$dblink) {
   die('Not connected : ' . mysqli_error());
}
else
{
    Debug("Connected to $sql_dbase data base");
}
 
// make photo the current db
$db_selected = mysqli_select_db($dblink, $sql_dbase);
if (!$db_selected) {
   die ('Can\'t use '.$sql_dbase.' database : ' . mysqli_error($dblink));
}
# else echo "Connection successful";

function GetSingleResult($querystring)
{
      global $dblink;

      $sqlres = mysqli_query($dblink, $querystring)
        or die("Query failed : " . mysqli_error($dblink)." on Query: $querystring");

      $info = mysqli_fetch_array($sqlres);

      while (mysqli_fetch_array($sqlres))
      {
          Debug("Got more than one match for query \"$querystring\"");
      }
      mysqli_free_result($sqlres);

      return $info;
}

function SetSQLQuery($querystring,$dieonerror)
{
      global $dblink;
      global $sqlmultiresult;
      if ($sqlmultiresult = mysqli_query($dblink, $querystring))
      {
         Debug(str_replace("\n"," ",substr($querystring,0, 25))."... OK.");
		 return 1;
      }
      else
      {
		 if ($dieonerror) die("Query failed : " . mysqli_error($dblink)."<br>Query: $querystring<br>");
		 return 0;
      }
}

function UpdateSQL($querystring,$dieonerror)
{
   global $dblink;
   if ($sqlresult = mysqli_query($dblink, $querystring))
   {
	  Debug(substr(str_replace("\n"," ",$querystring),0, 25)."... OK: ".mysqli_insert_id($dblink));
	  return mysqli_insert_id($dblink);
   }
   else
   {
	  if ($dieonerror)
	  {
		die("Query failed : " . mysqli_error($dblink)."\nQuery = ".$querystring);
	  }
	  else
	  {
		Debug("Update failed: ".mysqli_error($dblink)."\nQuery = ".$querystring);
	  }
	  return NULL;
   }
}


function GetMultipleResult()
{
      global $dblink;
      global $sqlmultiresult;

      if ($info = mysqli_fetch_array ($sqlmultiresult))
      {
		 return $info;
      }
      else
      {
		 Debug("Fetch complete.");
		 mysqli_free_result($sqlmultiresult);
		 return 0;
      }

}

function GetAffectedRowCount()
{
   global $dblink;
   return mysqli_affected_rows($dblink);
}

function SanitizeUserInput($str)
{
   global $dblink;
   $result = mysqli_real_escape_string($dblink, $str);
   if (!$result && $str !="")
   {
      die("Unable to sanitize string, '$str'\nmysqli_error() returns:".mysqli_error($dblink)."\n");
   }
   return $result;
}

?>
