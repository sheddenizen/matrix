<?php

function GetMetersQuery($mxId)
{
return
"
SELECT line.name, jackClient.name, portMap.channel, jackPort.name 

FROM meter,line,portMap,jackPort,jackClient 

WHERE

meter.matrix = '$mxId' AND
meter.line = line.index AND 
line.index = portMap.line AND
portMap.jackPort = jackPort.index AND 
jackPort.jackClient = jackClient.index AND
jackClient.isMeter = '1' 

ORDER BY jackClient.index, line.name, portMap.channel
;
";
}

class DrawMeters extends DisplayCommand
{
    private static $scriptInjected = false;
    const kMeterHeight = 200;
    const kUpperDb = 20;
    const kLowerDb = -40;
    const kDbRange = 60;
    const kScaleMkHeight = 1;
    
    private static $kMarkers = array(12, 6, 3, 0, -3, -6, -12, -20, -30, );
    
    private function DrawScale()
    {
        $h = new HtmlState();
        $h->Elem("table", array("class"=>"meterScale"));
        $last = 0;
        foreach (self::$kMarkers as $idx => $db)
        {
            if ($db == 0)
            {
                $trAttr = array("class"=>"dbZero");
            }
            elseif ($db > 0)
            {
                $trAttr = array("class"=>"dbPlus");
            }
            else
            {
                $trAttr = array("class"=>"dbMinus");                
            }
            $vpos = round(self::kMeterHeight * (self::kUpperDb - $db) / self::kDbRange);
            $trAttr["style"] = "height: ".($vpos - $last - self::kScaleMkHeight)."px";
            $last = $vpos;
            $h->Elem("tr", $trAttr);
            $h->LeafElem("td", array(), $db);
            $h->ElemEnd(); // tr
        }
        $trAttr["style"] = "height: ".(self::kMeterHeight - $last - self::kScaleMkHeight)."px";
        $trAttr["class"] = "scalePad";
        $h->Elem("tr", $trAttr);
        $h->LeafElem("td");
        $h->ElemEnd(); // tr
        
    }
    
    public function Exec($value =null)
    {
        $mxId = $value[0];
        SetSQLQuery(GetMetersQuery($mxId), false);
        $res = GetMultipleResult();
        if (!$res)
        {
            Debug("No meters for matrix $mxId, Query: ".GetMetersQuery($mxId));
            return;
        }
        $h = new HtmlState();
        if (!self::$scriptInjected)
        {
            self::$scriptInjected = true;
            $h->LeafElem("script", array("type" => "application/javascript"),
                         "var kMeterHeight = ".self::kMeterHeight.
                         ";var kLowerDb = ".self::kLowerDb.
                         ";var kDbRange = ".self::kDbRange.";");
            $h->Elem("script", array("type" => "application/javascript", "src"=>"meter.js"));
            $h->ElemEnd();
        }
        $h->Elem("table", array("class"=>"mxMeterBridge"));
        $h->Elem("tr");
        $client = $res[1];
        $name = "";
        $lines = array();
        $coltot =0;
        do
        {
            if ($res[0] != $name)
            {
                $h->LeafElem("td"); // Padding
                if ($name != "")
                {
                    $h->LeafElem("td"); // Padding                    
                }
                $name = $res[0];
                $lines[] = array($name,1);
            }
            else
            {
                ++$lines[count($lines)-1][1];
                $h->Elem("td", array("class"=>"meterScale"));
                $this->DrawScale();
                $h->ElemEnd(); // td
            }
            Debug($res);
            $h->Elem("td", array("class"=>"mxMeter"));
            $index = ltrim($res[3],"in-0");
            Debug("Index: ".$index);
            $name = $res[0];
            $h->Elem("table", array("class"=>"mxMeter"));
            $h->Elem("tr", array("class"=>"meterIdle"));
            $h->LeafElem("td");
            $h->ElemEnd(); // tr
            $h->Elem("tr", array("class"=>"meterMark"));
            $h->LeafElem("td");
            $h->ElemEnd(); // tr
            $h->Elem("tr", array("id"=>"meterPeak$index", "class"=>"meterPeak"));
            $h->LeafElem("td");
            $h->ElemEnd(); // tr
            $h->Elem("tr", array("id"=>"meterRms$index", "class"=>"meterRms"));
            $h->LeafElem("td");
            $h->ElemEnd(); // tr
            $h->ElemEnd(); // table
            $h->LeafElem("script", array("type" => "application/javascript"), "channels.push($index);");
            $h->ElemEnd(); // td
            
        } while (($res = GetMultipleResult()) && ($res[1] == $client));
        $h->LeafElem("td"); // Padding                    
        $h->ElemEnd(); // tr
        $h->Elem("tr"); // Padding                    
        foreach ($lines as $line)
        {
            $h->LeafElem("td", array("class"=>"meterName", "colspan"=>$line[1]+3), $line[0]);
            $coltot += $line[1]+3;
        }        
        $h->ElemEnd(); // tr
        $h->Elem("tr");
        $h->LeafElem("td", array("colspan"=>$coltot, "class"=>"tblUnderRule"));
    }
    public function ParamType()
    {
        return array(self::kIntParam);
    }    

    
};

?>
