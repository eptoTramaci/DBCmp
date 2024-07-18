<?php
/* ***
 * dbcmp
 * Copyright (C) 2012-2020 by mes3hacklab
 * 
 * dbcmp is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This source code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this source code; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * 
 * ***/
 
define('VERSION','1.1');
define('MAGIC_NUMBER','org.mes3hacklab.dbCmp/1.0');
define('MODIFIED_BY','mes3hacklab');

$myHash = hash_file('sha1',__FILE__);
$myHash = strtoupper($myHash);

$INI = null;

$opt = getopt('o:c:a:b:sijV');

if (!$opt) {
	echo "\ndbcmp ".VERSION." (C) 2012-2020 mes3hacklab\n\n";
	echo "dbcmp [ -j ] -c <configFile> -o <outputFile>\n";
	echo "\t    Salva la struttura del db per l'analisi.\n\n";
	echo "dbcmp -a <file1> -b <file2> [ -s ]\n";
	echo "\t    Confronta le strutture dei due file.\n\n";
	echo "Parametri:\n";
	echo "\t-i  Non essere pedante su db e versione.\n";
	echo "\t-j  Usa il file di configurazione in formato json.\n";
	echo "\t-s  Ignora l'errore di confronto sullo stesso server.\n";
	echo "\t-V  Visualizza la versione del programma.\n\n";
	exit(1);
	}

if (isset($opt['V'])) {
	
	$self = file_get_contents(__FILE__);
	$a = strpos($self,'/* ***');
	$b = strpos($self,'* ***/');
	$b-=$a;

	$ver = substr($self,$a,$b);
	$ver = str_replace(['/*','*/'], '  ',$ver);
	$ver = str_replace('*',' ',$ver);
	
	$ver = str_replace("\r\n","\n",$ver);
	$ver = ltrim($ver,"\t\r\n");
	$ver = rtrim($ver,"\t\r\n ");

	echo "$ver\n\n";
		
	list($a,$b)=explode('/',MAGIC_NUMBER);
	echo "Dati versione:\n";
	
	echo "\tVersione:          ".VERSION."\n";
	echo "\tMagic number:      ".MAGIC_NUMBER."\n";
	echo "\tModificato da:     ".MODIFIED_BY."\n";
	echo "\tHash programma:    $myHash\n";
	echo "\tVersione formato:  $b\n";
	exit("\n");
}

if (isset($opt['c'])) {
	
	if (isset($opt['j'])) {
		
		$INI = file_get_contents($opt['c']);	
		if ($INI) $INI = json_decode($INI,true);
		if (!is_array($INI)) $INI = false;
		
	} else {
		$INI = parse_ini_file($opt['c'],true);	
	}
		
	if (!$INI) {
		echo "\nErrore file conf!\n";
		exit(1);
	}
	
}

try {

	if (isset($opt['o']) and isset($opt['c'])) {
		
		dbopen($DBH);	
		$db = getDBStruct($DBH);
		$data = [
			'magic'		=>	MAGIC_NUMBER,
			'ver'		=>	$myHash,
			'id'		=>  '',
			'me'		=>	__FILE__,
			'time'		=>	time(),
			'server'	=>	gethostname(),
			'db'		=>	getDatabaseName($DBH),
			'struct'	=>	$db ] 
			;
			
		$data['id'] = hash('sha256', serialize($data) );
		file_put_contents($opt['o'],serialize($data));
		
		dbclose($DBH);
		$DBH=null;
		echo "Struttura analizzata su {$opt['o']}\n";
		exit(0);
		}
				
	if (isset($opt['a']) and isset($opt['b'])) {
		
		$fileA = $opt['a'];
		$fileB = $opt['b'];
		
		$A = file_get_contents($fileA);
		if (!$A) throw new Exception("Errore nel file $fileA");
		$A = unserialize($A);
		if (!is_array($A)) throw new Exception("Errore dati nel file $fileA");
		
		$B = file_get_contents($fileB);
		if (!$B) throw new Exception("Errore nel file $fileB");
		$B = unserialize($B);
		if (!is_array($B)) throw new Exception("Errore dati nel file $fileB");
		
		compareStructFiles($A,$B,$fileA,$fileB);
		exit(0);
		}
					
	} catch(Exception $err) {
		
		echo "\n\x07 Errore:\n";
		echo "\t".$err->getMessage();
		echo "\n\tBacktrace:\n";
		echo $err->getTraceAsString();
		echo "\n";
		
		if ($DBH) dbclose($DBH);;
		exit(1);
	}

echo "Nessuna operazione!\n";
exit(1);

////////////////////////////////////////////////////////////////////////
//// Funzioni principali  //////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////

function _compareStruct($ca,$cb,&$cmp) {
	foreach($ca['struct'] as $tableName => $table) {
		
		if (!isset($cmp[$tableName])) $cmp[$tableName] = array();
		
		if (!isset($cb['struct'][$tableName])) {
			$cmp[$tableName][] = "{$ca['server']}: C'è in più la tabella `$tableName`";
			continue;
			}
		
		foreach($table as $field => $struct) {
			
			if (!isset($cb['struct'][$tableName][$field])) {
				$cmp[$tableName][] = "{$ca['server']}: C'è in il campo in più `$tableName`.`$field`";
				continue;
				}
			
			if ($struct['Type']!=$cb['struct'][$tableName][$field]['Type']) {
				$cmp[$tableName][] = "Il campo `$tableName`.`$field` ha tipi diversi";
				}
			
			$ax = $struct['Null'].' '.$struct['Key'].' '.$struct['Extra'];
			$bx = $cb['struct'][$tableName][$field]['Null'].' '.$cb['struct'][$tableName][$field]['Key'].' '.$cb['struct'][$tableName][$field]['Extra'];
			
			if ($ax!=$bx) {
				$cmp[$tableName][] = "Il campo `$tableName`.`$field` ha parametri extra diversi";
				}
			}
		
		}
	}
	
function compareStructFiles($a,$b,$fileA,$fileB) {
	global $myHash;
	global $opt;
	
	if (@$a['magic']!=MAGIC_NUMBER) throw new Exception("Il file `$fileA` non è valido!");
	if (@$b['magic']!=MAGIC_NUMBER) throw new Exception("Il file `$fileB` non è valido!");
	
	if (!isset($opt['i']) and @$a['ver']!=$myHash) echo "Attenzione!!!: Il file `$fileA` non è stato fatto con questa versione del programma!\n\tIl file era {$a['me']}\n\n";
	if (!isset($opt['i']) and @$b['ver']!=$myHash) echo "Attenzione!!!: Il file `$fileB` non è stato fatto con questa versione del programma!\n\tIl file era {$b['me']}\n\n";
	if (!isset($opt['i']) and @$a['ver']!=$b['ver']) echo "Attenzione!!!: I due file devono essere fatti con lo stesso programma!\n";
	if (!isset($opt['i']) and $a['db']!=$b['db']) throw new Exception("Il due database sono diversi: {$a['db']} su {$a['server']} e {$b['db']} su {$b['server']}");
	if ($a['id']==$b['id']) throw new Exception("Stai confrontando lo stesso file");
	if (!isset($opt['s']) and $a['server']==$b['server']) throw new Exception("Stai confrontando lo stesso server");
	echo "Confronto di {$a['db']} tra {$a['server']} e {$b['server']}\n";
	echo "Data su {$a['server']}\t".date('m/d/Y H:i:s',$a['time'])."\n";
	echo "Data su {$b['server']}\t".date('m/d/Y H:i:s',$b['time'])."\n";
			
	$cmp = array();
	_compareStruct($a,$b,$cmp);
	_compareStruct($b,$a,$cmp);
	ksort($cmp);
	
	echo "\nDifferenze:\n";
	
	foreach($cmp as $table => $diff) {
		if (!$diff) continue;
		echo "Tabella: $table\n";
		$diff = array_unique($diff);
		foreach($diff as $line) {
			echo "\t$line\n";
			}
		echo "\n";
		}
	
	echo "\n.\n";
	}

////////////////////////////////////////////////////////////////////////
//// Altre funzioni e classi  //////////////////////////////////////////
////////////////////////////////////////////////////////////////////////

class LibMySQLIException extends Exception {
	
	public $mySqli = null;
	public $query = null;
	public $errNo = 0;
	public $error = null;
		
	const DBE_INI	= 0xdbe0;
	const DBE_CONN	= 0xdbe1;
	const DBE_QERY	= 0xdb01;
	const DBE_DBGET	= 0xdbe2;
	const DBE_ID	= 0xdbe3;
	
	
	function __construct() {
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this,$f='__construct'.$i)) {
            call_user_func_array(array($this,$f),$a);
        }
    } 
	
	public function __construct1($mysqli) {
		
		if ($mysqli->connect_errno) {
			
			$this->__construct5( 
				'['.$mysqli->connect_errno.'] '.$mysqli->connect_error, 
				$mysqli->connect_errno , 
				$mysqli,
				null)
				;
				
			return;
		}
		
		if ($mysqli->errno) {
			
			$this->__construct5( 
				'['.$mysqli->errno.'] '.$mysqli->error, 
				$mysqli->errno , 
				$mysqli,
				null)
				;
				
			return;
			}
	
		$this->__construct5("[0] Errore",0,$mysqli);
		}
	
	public function __construct2($message, $code) {
		$this->__construct5($message,$code,null,true);
		}
	
	public function __construct5($message, $code = 0, $mySqli = null ,$lerr=false ,$query=null ) {
		parent::__construct($message, $code, null);
		$this->errNo = $code;
		$this->mySqli=$mySqli;
		$this->query=$query;
		}	
	
	}

function dbopen(&$dbh,$iniD=false) {	//	Apre un db.
	global $INI;
	
	if (!$iniD) $iniD=$INI;
	
	if (!$iniD or !$iniD['db']) throw new LibMySQLIException("Configurazione non trovata",LibMySQLIException::DBE_INI);
	$c = new mysqli($iniD['db']['mysql'], $iniD['db']['dblog'], $iniD['db']['dbpas'], $iniD['db']['db']);
	if ($c->connect_errno) throw new LibMySQLIException($c);
	$dbh=array(
		'db'=>	$iniD['db']['db'],
		'pr'=>	$iniD['db']['prefix'] ? $iniD['db']['prefix'] : '',
		'c'	=>	&$c,
		'r' =>	null,
		's'	=>	1,
		'_'	=>	'')
		;
	
	if (isset($iniD['db']['charset'])) {
		if (!$dbh['c']->set_charset($iniD['db']['charset'])) throw new LibMySQLIException($dbh['c']); 
		}
		
	return $dbh;
	}

function dbcheck(&$dbh) {	//	Verifica l'handle al db. 
	if (!isset($dbh['c'])) throw new LibMySQLIException("Database non aperto",LibMySQLIException::DBE_CONN);
	}	
	
function dbfree(&$dbh) {	//	Free di un risultato.
	if (@$dbh['r']) @$dbh['r']->free();
	$dbh['r']=null;
	}
	
function dbclose(&$dbh) {	//	Chiude l'handel del db.
	if (@$dbh['r'] and $dbh['r'] instanceof mysqli_result ) @$dbh['r']->free();
	if (@$dbh['c'] and $dbh['c'] instanceof mysqli) @$dbh['c']->close();
	$dbh['r']=null;
	$dbh['c']=null;
	}
	
function dbquery(&$dbh,$qry) {	//	Esegue una query SQL ed introduce il risultato nel handle.
	dbcheck($dbh);
	
	if (isset($dbh['dbg'])) $dbh['dbg'][]=$qry;
	
	$dbh['r'] = $dbh['c']->query($qry);
	
	if (!$dbh['r']) throw new LibMySQLIException(
		"Errore query: ".$dbh['c']->error." Query: $qry",
		LibMySQLIException::DBE_QERY,
		$dbh['c'],
		true,
		$qry)
		;	
	}
	
function dbget(&$dbh) {		//	Preleva un array associativo come record del db da un handle con un risultato.
	if (!@$dbh['c']) throw new LibMySQLIException("Database non aperto",LibMySQLIException::DBE_CONN);
	if ($dbh['r']===false) throw new LibMySQLIException("Nessun risultato da leggere",LibMySQLIException::DBE_DBGET);
	if (!method_exists($dbh['r'],'fetch_assoc')) throw new LibMySQLIException("Nessun oggetto da leggere (manca dbquery) ",LibMySQLIException::DBE_DBGET); 
	$x = $dbh['r']->fetch_assoc();
	
	if (!$x) {
		dbfree($dbh);
		return false;
		}
		
	return $x;	
	}
	
function dbenc(&$dbh,$str) { 	//	Codifica le stringhe.
	if (!@$dbh['c']) throw new LibMySQLIException("Database non aperto",LibMySQLIException::DBE_CONN);
	return $dbh['c']->real_escape_string($str);
	}


function getTableStruct(&$DBH,$table) {
	$o = array();
	
	dbquery($DBH,'SHOW COLUMNS FROM `'.dbenc($DBH,$table).'`');
	while($db=dbget($DBH)) {
		$o[ $db['Field'] ] = $db;
		}

	return $o;
	
	}

function getTablesList(&$DBH) {
	$o = array();
	dbquery($DBH,'show tables');
	while($db=dbget($DBH)) {
		$db = array_shift($db);
		$o[] = $db;
		}

	return $o;
	}

function getDBStruct(&$DBH) {
	
	$db = array();
	$table = getTablesList($DBH);
	
	foreach($table as $item) {
		echo "Tabella $item\n";
		$db[ $item ] = getTableStruct($DBH,$item);
		}
	
	return $db;
	
	}

function getDatabaseName(&$DBH) {
	dbquery($DBH,'SELECT DATABASE()');
	$db = dbget($DBH);
	return array_shift($db);
	}

////////////////////////////////////////////////////////////////////////
//// SALT //////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////
/*
 * Y76I~`U}8"[08V!3"&Mw[z"V}q*Pw[}9yLBwJU{8_Lp:Nq*X<EgF-Ek5u&l)fdC.l8o,F
 * |M<IOU+RjjlHrL%jkt",D9R12QH!sSL$DePib&gJ:8K9NMqaMxw)e(a8~vhsAxSVV6uJp
 * H`dr$Wv'T_W>y|4Y=@iX/26=G7MKfq_X;D/mqzT]hrtf`(mC\:cGQ5I.4y,~eH#7u4PDM
 * F}.O].hTho9y<p(|jm<WZnm36)XL;e5!9gd%%!Ain/i.M'L'AG!,\M^[$#w,|bvr8_(g3
 * */
////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////
