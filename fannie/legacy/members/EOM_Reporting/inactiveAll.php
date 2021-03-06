<?php
include('../../../config.php');
include(__DIR__  . '/../../../classlib2.0/FannieAPI.php');
if (!class_exists("SQLManager")) require_once(__DIR__ . "/../../../src/SQLManager.php");
include('../../db.php');

if (isset($_GET['excel'])){
    header('Content-Type: application/ms-excel');
    header('Content-Disposition: attachment; filename="InactiveLastMonth.xls"');
    $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
}

$cached_output = \COREPOS\Fannie\API\data\DataCache::getFile("monthly");
if ($cached_output){
    echo $cached_output;
    return;
}

ob_start();

$query = "select s.cardno,r.mask from
    suspensions as s left join reasoncodes as r
    on s.reasoncode & r.mask <> 0
    where s.memtype2 = 'PC' and
    ".$sql->monthdiff($sql->now(),'s.suspDate')." = 1
    order by s.cardno";

echo "<table border=1 cellpadding=4 cellspacing=0>\n";
$headers = array('Mem Num','Overdue A/R','Equity Lapse','NSF Check','Contact Info','Equity Trans','Other');
echo "<tr>";
foreach($headers as $h)
    echo "<th ><font size=2>$h</font></th>";
echo "</tr>";

function printLine($reasons, $curMem, $b)
{
    $backgrounds = array('#ffffcc','#ffffff');
    echo "<tr>";
    echo "<td bgcolor=$backgrounds[$b]>$curMem</td>";
    foreach ($reasons as $r){
        echo "<td bgcolor=$backgrounds[$b] align=center>$r</td>";
    }
    echo "</tr>";
    $b = ($b+1)%2;
    return $b;
}
$b = 0;

$result = $sql->query($query);
$curMem=-1;
$firstBuy=0;
$reasons = array("&nbsp;","&nbsp;","&nbsp;","&nbsp;","&nbsp;","&nbsp;");
$rsums = array(0,0,0,0,0,0);
while($row = $sql->fetch_row($result)){
    if ($curMem != $row[0]){
        if ($curMem != -1){
            $b = printLine($reasons, $curMem, $b);
        }
        $curMem = $row[0];
        $reasons = array("&nbsp;","&nbsp;","&nbsp;","&nbsp;","&nbsp;","&nbsp;");
    }
    switch($row[1]){
    case '1':
        $reasons[0] = "1";
        $rsums[0] += 1;
        break;
    case '2':
    case '4':
        $reasons[1] = "1";
        $rsums[1] += 1;
        break;
    case '8':
        $reasons[2] = "1";
        $rsums[2] += 1;
        break;
    case '16':
        $reasons[3] = "1";
        $rsums[3] += 1;
        break;
    case '32':
        $reasons[4] = "1";
        $rsums[4] += 1;
        break;
    default:
        $reasons[5] = "1";
        $rsums[5] += 1;
    }
}
$b = printLine($reasons, $curMem, $b);
$b = printLine($rsums, 'Totals', $b);
echo "</table>";

$output = ob_get_contents();
ob_end_clean();
\COREPOS\Fannie\API\data\DataCache::putFile('monthly',$output);
echo $output;

