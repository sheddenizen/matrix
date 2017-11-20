<?php

function DebugLine($line)
{
    echo("<!-- ");
    echo(microtime(true)." ".$line);
    echo(" -->\n");
}

global $debug;
if (!isset($debug))
{
	$debug = "DebugLine";
}

class HtmlState
{
    private static $tab = "";
    private $elems = array();
    private $nodeelem = "";
    private $nodeattrs = array();
    public function Elem($elem, $attrs = array())
    {
        array_push($this->elems, $elem);
        echo self::$tab."<$elem";
        foreach ($attrs as $name=>$value)
        {
            $name = htmlspecialchars($name);
            $value = htmlspecialchars($value);
            echo " $name=\"$value\"";
        }
        echo ">\n";
        self::$tab .= "  ";
    }
    public function Text($text)
    {
        echo (self::$tab.preg_replace("/\n/", "<br/>\n", htmlspecialchars($text, ENT_NOQUOTES)));
    }
    public function Comment($text)
    {
        echo (self::$tab."<!-- $text -->\n");
    }
    public function SetNode($elem, $attrs = array())
    {
        $this->nodeelem = $elem;
        $this->nodeattrs = $attrs;
    }
    public function Node($nodeValue)
    {
        echo self::$tab."<$this->nodeelem";
        foreach ($this->nodeattrs as $name=>$value)
        {
            $name = htmlspecialchars($name);
            $value = htmlspecialchars($value);
            echo " $name=\"$value\"";
        }
        echo ">$nodeValue</$this->nodeelem>\n";
    }
    public function LeafElem($elem, $attrs = array(), $nodeValue = null)
    {
        echo self::$tab."<$elem";
        foreach ($attrs as $name=>$value)
        {
            $name = htmlspecialchars($name);
            $value = htmlspecialchars($value);
            echo " $name=\"$value\"";
        }
        if (is_null($nodeValue))
        {
            echo " />\n";
        }
        else
        {
            $nodeValue = htmlspecialchars($nodeValue);
            echo ">$nodeValue</$elem>\n";
        }
    }
    public function ElemEnd()
    {
        self::$tab = substr(self::$tab,2);
        $elem = array_pop($this->elems);
        echo self::$tab."</$elem>\n";
    }
    public function __destruct()
    {
        while (count($this->elems))
        {
            $this->ElemEnd();
        }
    }
}; // class HtmlElem

function Debug($stuff)
{
   global $debug;
   
   if ($debug)
   {
      if (is_array($stuff))
      {
        $debug(var_export($stuff, true));
      }
      else
      {
        $debug($stuff);
      }
   }
}



?>