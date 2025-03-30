<!DOCTYPE html>
<?php
$cfg = array();
$defaults = array();
$fmodule = $furcall = '';
$cfgdir = '/usr/local/etc';

function ParseKVFile(string $filename, &$kvarray)
{
	if ($lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line[0] == '#') continue;
			if (! strpos($line, '=')) continue;
			list( $key, $value ) = explode('=', $line);
			if ("'" == $value[0])
				list ( $value ) = explode("'", substr($value, 1));
			else
				list ( $value ) = explode(' ', $value);
			$value = trim($value);
			$kvarray[$key] = $value;
		}
	}
}

function GetCFGValue(string $key)
{
	global $cfg, $defaults;
	if (array_key_exists($key, $cfg))
		return $cfg[$key];
	if ('module_' == substr($key, 0, 7)) {
		$mod = substr($key, 0, 8);
		if (array_key_exists($mod, $cfg)) {
			$key = $cfg[$mod].substr($key, 8);
			if (array_key_exists($key, $defaults))
				return $defaults[$key];
		}
	} else {
		if (array_key_exists($key.'_d', $defaults))
			return $defaults[$key.'_d'];
	}
	return '';
}

function GetIP(string $type)
{
	if ('internal' == $type) {
		$iplist = explode(' ', `hostname -I`);
		foreach ($iplist as $ip) {
			if (strpos($ip, '.')) break;
		}
	} else if ('ipv6' == $type)
		$ip = trim(`curl ifconfig.co`);
	else if ('ipv4' == $type)
		$ip = trim(`curl ipinfo.io/ip`);
	else
		$ip = '';
	return $ip;
}

function SecToString(int $sec) {
	if ($sec >= 86400)
		return sprintf("%0.2f days", $sec/86400);
	$hrs = intdiv($sec, 3600);
	$sec %= 3600;
	$min = intdiv($sec, 60);
	$sec %= 60;
	if ($hrs) return sprintf("%2d hr  %2d min", $hrs, $min);
	if ($min) return sprintf("%2d min %2d sec", $min, $sec);
	return sprintf("%2d sec", $sec);
}

function MyAndSfxToQrz(string $my, string $sfx)
{
	$my = trim($my);
	$sfx = trim($sfx);
	if (0 == strlen($my)) {
		$my = 'Empty MYCall ';
	} else {
		if (strpos($my, ' '))
			$link = strstr($my, ' ', true);
		else
			$link = $my;
		if (strlen($sfx))
			$my .= '/'.$sfx;
		$len = strlen($my);
		$my = '<a*target="_blank"*href="https://www.qrz.com/db/'.$link.'">'.$my.'</a>';
		while ($len < 13) {
			$my .= ' ';
			$len += 1;
		}
	}
	return $my;
}
//example URL: https://www.google.com/maps?q=+52.37745,+001.99960
function Maidenhead(string $maid, float $lat, float $lon)
{
	$str = trim($maid);
	if (6 > strlen($str))
		return $maid;
	if ($lat >= 0.0)
		$slat = '+'.$lat;
	else
		$slat = $lat;
	if ($lon >= 0.0)
		$slon = '+'.$lon;
	else
		$slon = $lon;
	$str = '<a*target="_blank"*href="https://www.google.com/maps?q='.$slat.','.$slon.'">'.$maid.'</a>';
	return $str;
}

ParseKVFile($cfgdir.'/qn.cfg', $cfg);
ParseKVFile($cfgdir.'/defaults', $defaults);
?>

<html>
<head>
<title>QnetGateway Dashboard</title>
<link rel=stylesheet href=style.css>
</head>
<body>
<h2>QnetGateway <?php echo GetCFGValue('ircddb_login'); ?> Dashboard</h2>

<?php
$showorder = GetCFGValue('dash_show_order');
$showlist = explode(',', trim($showorder));
foreach($showlist as $section) {
	switch ($section) {
		case 'PS':
			if (`ps -aux | grep -e qn -e MMDVMHost | wc -l` > 2) {
				echo 'Processes:<br><code>', "\n";
				echo str_replace(' ', '&nbsp;', 'USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND<br>'), "\n";
				$lines = explode("\n", `ps -aux | grep -e qngateway -e qnlink -e qndtmf -e qndvap -e qnitap -e qnrelay -e qndvrptr -e qnmodem -e MMDVMHost | grep -v -e grep -e journal`);
				foreach ($lines as $line) {
					echo str_replace(' ', '&nbsp;', $line), "<br>\n";
				}
				echo '</code>', "\n";
			}
			break;
		case 'SY':
			echo 'System Info:<br>', "\n";
			$hn = trim(`uname -n`);
			$kn = trim(`uname -rmo`);
			$osinfo = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($osinfo as $line) {
				list( $key, $value ) = explode('=', $line);
				if ($key == 'PRETTY_NAME') {
					$os = trim($value, '"');
					break;
				}
			}
			$cu = trim(`cat /proc/cpuinfo | grep Model`);
			if (0 == strlen($cu))
				$cu = trim(`cat /proc/cpuinfo | grep "model name"`);
			$culist = explode("\n", $cu);
			$mnlist = explode(':', $culist[0]);
			$cu = trim($mnlist[1]);
			if (count($culist) > 1)
				$cu .= ' ' . count($culist) . ' Threads';
			if (file_exists('/opt/vc/bin/vcgencmd'))
				$cu .= ' ' . str_replace("'", '&deg;', trim(`/opt/vc/bin/vcgencmd measure_temp`));
			echo '<table cellpadding="1" border="1">', "\n";
			echo '<th>CPU</th><th>Kernel</th><th>OS</th><th>Hostname</th>', "\n";
			echo '<tr><td style="text-align:center">', $cu, '</td><td style="text-align:center">', $kn, '</td><td style="text-align:center">', $os, '</td><td style="text-align:center">', $hn, '</td></tr></table><br>', "\n";
			break;
		case 'LH':
			echo "Last Heard:<br>\n";
			show_last_heard();
			echo "<br>\n";
			break;
		case 'IP':
			$hasv6 = stristr(GetCFGValue('ircddb0_host'), 'v6');
			if (! $hasv6) $hasv6 = stristr(GetCFGValue('ircddb1_host'), 'v6');
			echo 'IP Addresses:<br>', "\n";
			echo '<table cellpadding="1" border="1">', "\n";
			echo '<tr><th>Internal</th><th>IPv4</th>';
			if ($hasv6) echo '<th>IPv6</th></tr>';
			echo "\n";
			echo '<tr><td>', GetIP('internal'), '</td><td>', GetIP('ipv4'), '</td>';
			if ($hasv6) echo '<td>', GetIP('ipv6'), '</td></tr>';
			echo "\n", '</table><br>', "\n";
			break;
		case 'MO':
			echo "Modules:<br>\n";
			show_modules();
			echo "<br>\n";
			break;
		case 'UR':
			echo 'Send URCall:<br>', "\n";
			echo '<form method="post">', "\n";
			$mods = array();
			foreach (array('a', 'b', 'c') as $mod) {
				$module = 'module_'.$mod;
				if (array_key_exists($module, $cfg)) {
					$mods[] = strtoupper($mod);
				}
			}
			if (count($mods) > 1) {
				echo 'Module: ', "\n";
				foreach ($mods as $mod) {
					echo '<input type="radio" name="fmodule"', (isset($fmodule) && $fmodule==$mod) ? '"checked"' : '', ' value="$mod">', $mod, '<br>', "\n";
				}
			} else
				$fmodule = $mods[0];
			echo 'URCall: <input type="text" name="furcall" value="', $furcall, '">', "\n";
			echo '<input type="submit" name="sendurcall" value="Send URCall"><br>', "\n";
			echo '</form>', "\n";
			if (isset($_POST['sendurcall'])) {
				$furcall = $_POST['furcall'];
				if (empty($_POST['fmodule'])) {
					if (1==count($mods)) {
						$fmodule = $mods[0];
					}
				} else {
					$fmodule = $_POST['fmodule'];
				}
			}
			$furcall = str_replace(' ', '_', trim(preg_replace('/[^0-9a-z_ ]/', '', strtolower($furcall))));

			if (strlen($furcall)>0 && strlen($fmodule)>0) {
				$command = 'qnremote '.strtolower($fmodule).' '.strtolower($cfg['ircddb_login']).' '.$furcall;
				echo $command, "<br>\n";
				$unused = `$command`;
			}
			break;
		default:
			echo 'Section "', $section, '" was not found!<br>', "\n";
			break;
	}
}
?>
<br>
<p align="right">QnetGateway Dashboard Version 10208 Copyright &copy; by Thomas A. Early, N7TAE.</p>
<script>
function $(id)
{
  return document.getElementById(id);
}

var seq=0;
var req=[];
var json=[];
req.data=new XMLHttpRequest();
req.data.onreadystatechange=function() {
  if(this.readyState == 4) {
    if(this.status == 200 && this.responseText.length) {
      json=JSON.parse(this.responseText);

      updateLastHeard();
      updateModules();
      seq=json.seq;
    }

    /* Run update again in 1 second */
    setTimeout(getUpdate, 1000);
  }
}

window.onload=getUpdate;
setInterval(updateTimes, 1000);

function getUpdate()
{
  req.data.open('GET', 'getUpdate.php?last_time='+seq, true);
  req.data.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
  req.data.send();
}

function updateLastHeard()
{
  const lh=$('LH');
  var count, array=json.last_heard, secs=json.cur_time;

  /* Delete old table */
  while(lh.rows.length > 1)
    lh.deleteRow(1);

  /* Populate new data up to some limit of entries */
   for(count=0;count < <?=GetCFGValue('dash_lastheard_count')?> && count < array.length;count++) {
    let row=lh.insertRow(-1);

    row.insertCell().innerHTML=array[count].qrz;
    row.insertCell().textContent=array[count].message;
    row.insertCell().textContent=array[count].module;
    row.insertCell().textContent=array[count].via;
    row.insertCell().innerHTML=array[count].maidenhead;

    /* Calculate # seconds ago */
    var text, time=secs-array[count].last;
    if(time < 60)
      text=time+' sec';
    else if(time < 3600)
      text=((time/60)|0)+' min, '+((time%60)|0)+' sec';
    else
      text=((time/3600)|0)+' hr, '+((time%3600/60)|0)+' min';

    row.insertCell().textContent=text;
  }

  /* Make table visible (needed for first run) */
  lh.style.display='table';
}

function updateModules()
{
  const mo=$('MO');
  var count, array=json.modules, secs=json.cur_time;

  /* Delete old table */
  while(mo.rows.length > 1)
    mo.deleteRow(1);

  /* Populate new data up to some limit of entries */
  for(count=0;count < array.length;count++) {
    let row=mo.insertRow(-1);

    row.insertCell().textContent=array[count].module;
    row.insertCell().textContent=array[count].modem;
    row.insertCell().textContent=array[count].frequency;
    row.insertCell().textContent=array[count].linkstatus;

    /* Calculate # seconds ago */
    if(!array[count].ctime)
      row.insertCell().textContent='';
    else {
      var text, time=secs-array[count].ctime;
      if(time < 60)
        text=time+' sec';
      else if(time < 3600)
        text=((time/60)|0)+' min, '+((time%60)|0)+' sec';
      else
        text=((time/3600)|0)+' hr, '+((time%3600/60)|0)+' min';

      row.insertCell().textContent=text;
    }
    row.insertCell().textContent=array[count].address;
  }

  /* Make table visible (needed for first run) */
  mo.style.display='table';
}

function updateTimes()
{
  ++json.cur_time;
  updateLastHeardTimes();
  updateModuleTimes();
}

function updateLastHeardTimes()
{
  const lh=$('LH');

  if(typeof json.last_heard === 'undefined' || !json.last_heard.length)
    return;

  var array=json.last_heard, secs=json.cur_time;

  for(count=0;count < 10 && count < array.length;count++) {
    /* Calculate # seconds ago */
    var text, time=secs-array[count].last;
    if(time < 60)
      text=time+' sec';
    else if(time < 3600)
      text=((time/60)|0)+' min, '+((time%60)|0)+' sec';
    else
      text=((time/3600)|0)+' hr, '+((time%3600/60)|0)+' min';

    lh.rows[count+1].cells[5].textContent=text;
  }
}

function updateModuleTimes()
{
  const mo=$('MO');

  if(typeof json.modules === 'undefined' || !json.modules.length)
    return;

  var array=json.modules, secs=json.cur_time;

  for(count=0;count < array.length;count++) {
    if(!array[count].ctime)
      mo.rows[count+1].cells[4].textContent='';
    else {
      /* Calculate # seconds ago */
      var text, time=secs-array[count].ctime;
      if(time < 60)
        text=time+' sec';
      else if(time < 3600)
        text=((time/60)|0)+' min, '+((time%60)|0)+' sec';
      else
        text=((time/3600)|0)+' hr, '+((time%3600/60)|0)+' min';

      mo.rows[count+1].cells[4].textContent=text;
    }
  }
}
</script>
</body>
</html>

<?php
function show_last_heard()
{
?>
  <table id=LH style="display:none">
    <tr>
      <th>MyCall/Sfx</th>
      <th>Message</th>
      <th>Mod</th>
      <th>Via</th>
      <th>Maidenhead</th>
      <th>Last</th>
    </tr>
  </table>
<?php
}

function show_modules()
{
?>
  <table id=MO style="display:none">
    <tr>
      <th>Module</th>
      <th>Modem</th>
      <th>Frequency</th>
      <th>Link</th>
      <th>Linked Time</th>
      <th>Link IP</th>
    </tr>
  </table>
<?php
}
