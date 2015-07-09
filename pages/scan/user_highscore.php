<?php
/**
 * pages/scan/user_highscore.php
 * User Highscore einscannen
 */

// Sicherheitsabfrage
if(!defined('ODDB')) die('unerlaubter Zugriff!');



// Flooding-Schutz 10 Minuten
/*if($cache->get('scaneinst'.$_POST['uid']) AND !isset($_GET['force'])) {
	$tmpl->error = 'Die Einstellungen wurden in den letzten 10 Minuten schon eingescannt!';
	$tmpl->output();
	die();
}
// Flooding-Schutz in den Cache laden
$cache->set('scaneinst'.$_POST['uid'], 1, 600);
*/
// Daten sichern

$_POST['uid'] = (int)$_POST['uid'];

foreach($_POST['userdata'] as $key=>$data) {
 $_POST['userdata'][$key]['id'] = (int)$data['id'];
 $_POST['userdata'][$key]['pname'] = escape(html_entity_decode($data['pname'], ENT_QUOTES, 'utf-8'));
 $_POST['userdata'][$key]['rasse'] = (int)$data['rasse'];
 $_POST['userdata'][$key]['alli'] = (int)$data['alli'];
 $_POST['userdata'][$key]['punkte'] = (int)$data['punkte'];
}


foreach($_POST['userdata'] as $data) {
   query("
                REPLACE INTO oddbplayer
                SET
                    playerID = ".$data['id'].",
                    playerName = '".$data['pname']."',
                    player_allianzenID = ".$data['alli'].",
                    playerRasse = ".$data['rasse'].",
                    playerGesamtpunkte = ".$data['punkte'].",
                    playerUpdate = 1
            ") OR dieTransaction("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error()); 

   query("
                UPDATE ".PREFIX."user
                SET
                    user_allianzenID = ".$data['alli']."
                WHERE user_playerID = ".$data['id']."
            ") OR dieTransaction("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error()); 
}

// Ausgabe
$tmpl->content = 'User Highscore gescannt!';



?>