<?php
/**
 * pages/scan/alli_highscore.php
 * Allianz Highscore einscannen
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

foreach($_POST['allidata'] as $key=>$data) {
 $_POST['allidata'][$key]['id'] = (int)$data['id'];
 $_POST['allidata'][$key]['atag'] = escape(html_entity_decode($data['aname'], ENT_QUOTES, 'utf-8'));
 $_POST['allidata'][$key]['aname'] = escape(html_entity_decode($data['aname'], ENT_QUOTES, 'utf-8'));
 $_POST['allidata'][$key]['mitglieder'] = (int)$data['mitglieder'];
}


foreach($_POST['allidata'] as $data) {
   query("
                REPLACE INTO oddballianzen
                SET
                    allianzenID = ".$data['id'].",
                    allianzenTag = '".$data['atag']."',
                    allianzenName = '".$data['aname']."',
                    allianzenMember = ".$data['mitglieder'].",
                    allianzenUpdate = 1
            ") OR dieTransaction("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error()); 
}

// Ausgabe
$tmpl->content = 'Allianzen Highscore gescannt!';



?>