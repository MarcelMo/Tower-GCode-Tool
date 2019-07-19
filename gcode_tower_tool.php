<?PHP
error_reporting(E_ALL);
session_start();

$post_list=array(
'gcode'=>'Insert GCode here',
'start_layer'=>1,
'stop_layer'=>'',
'start_value'=>'100',
'stop_value'=>'80',
'layer_regex'=>'~^; layer ([0-9]+), *Z *= [0-9\\.]+$~im',
'command'=>'M221 S$new_value\r\n',
'steps'=>10,
'step_size'=>1
);

foreach($post_list as $k=>$default) {
	if (isset($_POST[$k])) $_SESSION[$k]=$_POST[$k];
	if (!isset($_SESSION[$k])) $_SESSION[$k]=$default;
}

if (isset($_POST['detect'])) {
	echo "start autodetect<br/>";
	$auto_detect=array(
	's3d'=>'~\r\n?; layer ([0-9]+), *Z *= *[0-9\\.]+\r\n?~is'
	);
	$match=false;
	foreach($auto_detect as $name=>$regex) if (!$match) {
		echo "Trying $name - ".htmlentities($regex)."<br/>";
		$match=false;
		if (preg_match_all($regex,$_SESSION['gcode'],$hits)) {
			$match=true;
			if (count($hits[0])<3) $match=false;
			$last_layer=0;
			foreach($hits[1] as $layer_no) {
				if ((int)$layer_no<=$last_layer) $match=false;
				$last_layer=(int)$layer_no;
				$stop_layer=$last_layer;
			}
			if ($match) {
				$_SESSION['layer_regex']=$regex;
				$_SESSION['stop_layer']=$stop_layer;
			}
		}
	}
	echo "Detect ".($match ? 'OK': 'Failed');
}

if (isset($_POST['GO'])) {
	$old_value=false;
	$gcode=$_SESSION['gcode'];
	$matches=preg_match_all($_SESSION['layer_regex'],$gcode,$hits,PREG_OFFSET_CAPTURE);
	$offset=0;
	foreach($hits[0] as $hit_no=>$row) {
		$current_layer=(int)$hits[1][$hit_no][0];
		$new_value=calcValueForLayer($current_layer);
		if ($new_value!=$old_value || $old_value===false) {
			$cmd=$_SESSION['command'];
			eval('$s="'.$cmd.'";');
			$pos=$hits[0][$hit_no][1]+strlen($hits[0][$hit_no][0])+$offset;
			$gcode=substr($gcode,0,$pos).$s.substr($gcode,$pos);
			$offset+=strlen($s);
			$old_value=$new_value;
		}
	}
	
	header("Content-Type: application/gcode");
	header("Content-Disposition: attachment; filename=modified_gcode_".date('Ymd_His').".gco");
	echo ";tower_tool.php parameter:\r\n";	
	foreach($post_list as $k=>$default) if ($k!='gcode') echo "; $k=".$_SESSION[$k]."\r\n";
	echo ";begin of gcode:\r\n";
	echo $gcode;
	die();
}

function calcValueForLayer($layer){
	$start_value=(float)$_SESSION['start_value'];
	$start_layer=(float)$_SESSION['start_layer'];
	$stop_value=(float)$_SESSION['stop_value'];
	$stop_layer=(float)$_SESSION['stop_layer'];
	$step_size=(float)$_SESSION['step_size'];
	if ($layer<=$start_layer) return($start_value);
	if ($layer>=$stop_layer) return($stop_value);

	$value=$start_value+($stop_value-$start_value)/($stop_layer-$start_layer)*($layer-$start_layer);
	$value=((int)($value/$step_size))*$step_size;
	return($value);
}

	echo "<form method='post'>
GCode: <br/>";
echo "<textarea cols='160' rows='40' name='gcode'>".htmlspecialchars(@$_SESSION['gcode'])."</textarea><br/>";

echo "Start at layer: <input type='text' name='start_layer' value='".@$_SESSION['start_layer']."' /><br/>
Start layer value: <input type='text' name='start_value' value='".@$_SESSION['start_value']."' /><br/>
Stop at layer: <input type='text' name='stop_layer' value='".@$_SESSION['stop_layer']."' /><br/>
Stop layer value: <input type='text' name='stop_value' value='".@$_SESSION['stop_value']."' /><br/>
Steps : <input type='text' name='steps' value='".@$_SESSION['steps']."' /><br/>
Step Size : <input type='text' name='step_size' value='".@$_SESSION['step_size']."' /><br/>
Layer marker (RegEx): <input type='text' name='layer_regex' value='".htmlspecialchars(@$_SESSION['layer_regex'])."'/><br/>
Command: <input type='text' name='command' value='".@$_SESSION['command']."' /><br/>
<input type='submit' NAME='save' value='Save' /><br/>
<input type='submit' NAME='detect' value='Detect' /><br/>
<input type='submit' NAME='GO' value='GO' />	<br/>
</form>";	
