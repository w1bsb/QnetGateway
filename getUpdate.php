<?php
header('Content-type: application/json');
ini_set('display_errors', false);

require __DIR__.'/lib.php';

$dbname = $cfgdir.'/qn.db';
$db = new SQLite3($dbname, SQLITE3_OPEN_READONLY);
$db->busyTimeout(60000);

function get_sequence()
{
  global $db;

  $ss = 'SELECT seq FROM SEQUENCE';
  $json=[];

  if(!($stmt=$db->prepare($ss)) || !($result=$stmt->execute()))
    exit;

  if(!($row=$result->FetchArray(SQLITE3_NUM)))
    exit;

  $seq=$row[0];
  $result->finalize();
  $stmt->close();

  return $seq;
}

function get_lastHeard()
{
  global $db;

  $ss = 'SELECT callsign,sfx,message,module,reflector,maidenhead,latitude,'
      . 'longitude,lasttime FROM lheard ORDER BY lasttime DESC';
  $json=[];

  if(!($stmt=$db->prepare($ss)) || !($result=$stmt->execute()))
    exit;

  while(($row=$result->FetchArray(SQLITE3_NUM))) {
    $array=[];
    $array['qrz']=str_replace('*', ' ', MyAndSfxToQrz($row[0], $row[1]));
    $array['message']=trim($row[2]);
    $array['module']=trim($row[3]);
    $array['via']=trim($row[4]);
    $array['maidenhead']=str_replace('*', ' ', trim(Maidenhead($row[5], $row[6], $row[7])));
    $array['last']=$row[8];

    /* Append to json[] array */
    $json[]=$array;
  }

  $result->finalize();
  $stmt->close();
  return $json;
}

function get_modules()
{
  global $db, $cfg;

  $json=[];

  foreach(['a', 'b', 'c'] as $mod) {
    $module = "module_$mod";
    if(array_key_exists($module, $cfg)) {
      $array['module'] = strtoupper($mod);
      $array['modem'] = $cfg[$module];
      $array['linkstatus'] = 'Unlinked';
      $array['address'] = '';
      $array['ctime'] = '';

      $freq = 0.0;
      if (array_key_exists($module.'_tx_frequency', $cfg))
        $freq = $cfg[$module.'_tx_frequency'];
      else if (array_key_exists($module.'_frequency', $cfg))
        $freq = $cfg[$module.'_frequency'];
      $array['frequency'] = $freq;

      $ss = 'SELECT ip_address,to_callsign,to_mod,linked_time FROM linkstatus WHERE from_mod=' . "'" . STRTOUPPER($mod) . "';";
      if(!($stmt=$db->prepare($ss)))
        exit;
      if(!($result=$stmt->execute()))
        exit;
      if(($row=$result->FetchArray(SQLITE3_NUM))) {
        $array['linkstatus'] = trim($row[1]).' '.$row[2];
        $array['address'] = $row[0];
        $array['ctime'] = $row[3];
      }

      $result->finalize();
      $stmt->close();

      /* Append to json[] array */
      $json[]=$array;
    }
  }

  return $json;
}

$last_time=$_REQUEST['last_time'] ?? 0;
$expire=time()+175;
$json=[];

while(time() < $expire) {
  $json['seq']=get_sequence();
  $json['last_heard']=get_lastHeard();
  $json['modules']=get_modules();
  if($json['seq'] > $last_time)
    break;
  sleep(1);
}

$db->Close();

$json['cur_time']=time();
echo json_encode($json);
exit;
