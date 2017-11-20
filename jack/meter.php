<!DOCTYPE html>
<html>
<head>
<meta http-equiv="cache-control" content="no-cache"/>
<script>

var myVar=setInterval(function(){loadXMLDoc()},100);
var idle = true;

function loadXMLDoc()
{
    if (!idle)
    {
        return;
    }
    idle = false;
    var xmlhttp;
    if (window.XMLHttpRequest)
    {// code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp=new XMLHttpRequest();
    }
    else
    {// code for IE6, IE5
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange=function()
    {
        idle = xmlhttp.readyState == 4;
        if (xmlhttp.readyState==4 && xmlhttp.status==200)
        {
            var values = String(xmlhttp.responseText).split(',');
            
            // document.getElementById("myDiv").innerHTML=xmlhttp.responseText;
            var rms = parseFloat(values[0]);
            var pk = parseFloat(values[1]);
            document.getElementById("myDiv").innerHTML = rms + "/" + pk;
            rms = (rms + 40) * 5;
            pk = (pk + 40) * 5;
            rms = rms < 0 ? 0 : rms;
            pk = (pk < rms) ? 0 : pk - rms;
            document.getElementById("lev1on").style.height = rms + "px";
            document.getElementById("lev1pk").style.height = pk + "px";
        }
    }
    var dummy = Math.floor((Math.random()*10000));
    xmlhttp.open("GET","levels.txt?x=" + dummy, true);
    xmlhttp.send();
}
</script>
</head>
<body>
<!--
<pre style="font-family: monospace;">   -18   -15   -12   -9    -6    -3     0    +3    +6    +9    +12</pre>
-->
<pre id="myDiv" style="font-family: monospace;">Insert Metering here</pre>


<table style="width: 100%; border: hidden; background-color: black">
    <tr style="height: 1em">
        <td id="lev0on" style="background-color: lime; width: 1%"/>
        <td id="lev0pk" style="background-color: black; width: 1%"/>
        <td id="lev0mk" style="background-color: red; width: 2px"/>
        <td id="lev0off" style="background-color: black;"/>
    </tr>
</table>

<br>

<table style="width: 20px; height: 200px; border-spacing: 0; margin: 0; border: none; background-color: black">
    <tr>
        <td/>
    </tr>
    <tr style="height: 1px">
        <td style="background-color: red"/>
    </tr>
    <tr id="lev1pk" style="height: 0px">
        <td style="background-color: black"/>
    </tr>
    <tr id="lev1on" style="height: 1px">
        <td style="background-color: lime"/>
    </tr>
</table>

</body>
</html>
