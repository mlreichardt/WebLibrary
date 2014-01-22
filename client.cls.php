<?php
// VERSION: 25 (07/11/2013)

if(!defined("CLIENT_LAYER")) {
	define("CLIENT_LAYER","client");

	//if(!defined("CONFIG_LAYER")) 	require_once(dirname(__FILE__) . "/config.cls.php");
	//if(!defined("DATABASE_LAYER")) 	require_once(dirname(__FILE__) . "/database.cls.php");
	if(!defined("DATUMTIJD_LAYER")) require_once(dirname(__FILE__) . "/datumtijd.cls.php");
	if(!defined("EMAIL_LAYER"))	require_once(dirname(__FILE__) . "/email.cls.php");

	class client {
		var $sid;
		var $ct;
		var $email;
		var $session;
		var $pin;
		var $agent;
		var $referer;
		var $ip;
		var $port;
		var $host;
		var $domain;
		var $subdomain;
		var $url;
		var $org;
		var $base_url;
		var $secure_url;
		var $request;
		var $params;
		var $hits;
		var $start;
		var $end;
		var $tp;
		var $timestamp;
		var $adminId;
		var $adminKey;
		var $adminLevel;
		var $adminEmail;
		var $adminMaster;
		var $adminEditor;
		var $pinExpire;
		var $customer;
		var $cookieSession = "cs";
		var $cookieCustomer = "ct";
		var $cookieToken = "token";
		var $cookieEmail = "email";
		
		function client($org = "?") {
			global $cfg,$db,$_SERVER;

			$this->org = $org;
			
			if(!is_object($cfg)) {
				$cfg = new config();
			}

			if(!is_object($db)) {
				$db = new database($this->org);
			}

			$this->timestamp = time();

			$this->agent = $_SERVER["HTTP_USER_AGENT"];
			$this->ip = $_SERVER["REMOTE_ADDR"];
			$this->port = intval($_SERVER["SERVER_PORT"]);

			$this->host = $_SERVER["HTTP_HOST"];
			$x = explode(".",$this->host);
			$this->subdomain = $x[0];
			$this->domain = substr($this->host,strlen($this->subdomain)+1);
			
			$this->request = str_replace($cfg->siteroot,"",$_SERVER["REQUEST_URI"]);
			$uri = explode("?",str_replace($cfg->siteroot,"",$_SERVER["REQUEST_URI"]));
			if(count($uri) > 1) {
				$this->request = $uri[0];
				$this->params = explode("&",$uri[1]); //this->getAllParams();
			} else {
				$this->params = array();
			}
//var_dump($this->request);
//var_dump($this->params);


			if($this->isSecure()) {
				$this->url ="https://" . $this->host;
			} else {
				$this->url ="http://" . $this->host;
			}
			
			$this->base_url ="http://" . $this->host;
			$this->secure_url ="https://" . $this->host;

			$this->adminLevel = 0;
			$this->adminMaster = false;
			$this->adminEditor = false;
			$this->adminId = 0;
			$this->adminPin = 0;
			$this->adminToken = "";
			$this->adminEmail = "";
			
			$this->sid = intval($_COOKIE[$this->cookieSession]);
			$this->ct = $_COOKIE[$this->cookieCustomer];

			$this->email = $_COOKIE[$this->cookieEmail];
			$token = $_COOKIE[$this->cookieToken];
			
			
			if((($cfg->use_https == false) || ($this->isSecure() == true)) && (strlen($token) > 20)) { 
				$where = "email='" . $this->email . "' AND token='" . $token . "'";
				$sql = "SELECT * FROM webaccess WHERE " . $where;
				//echo "SQL:"; var_dump($sql);

				if($result = $db->sql_query($sql)) {
					if($row = $db->sql_fetchrow($result)) {
						//echo "ROW:"; var_dump($row);

						$this->adminId =  intval($row['id']);
						$this->adminPin =  intval($row['pin']);
						$this->adminToken =  $row['token'];
						$this->adminEmail =  $row['email'];
						$this->adminLevel =  intval($row['level']);
						$this->pinExpire = intval($row['expire']);
						$this->pinExpire = $this->timestamp + intval($row['expire']) * 24 * 60 * 60;
						
						if(intval($row['active']) > 0) {
							if($this->adminLevel >= 6) {
								$this->adminMaster = true;
							}
							if($this->adminLevel > 0) {
								$this->adminEditor = true;
							}
						}
						
					} else {
						$this->adminEmail =  $this->email;
					}
				} else {
					//echo "SQL:"; var_dump($sql);
				}
			}

			if(($this->org == "index") && ($this->adminLevel == 0)) {
				$this->sid = intval($_COOKIE[$this->cookieSession]);
				if($this->sid == 0) {
					$sql = "INSERT INTO statsession (ip, start) VALUES (0," . $this->timestamp . ")";
					//$this->logMessage("SQL",$sql);
					if( $result = mysql_query($sql) ) {
						$this->sid = mysql_insert_id();
						setcookie( $this->cookieSession, $this->sid);	
						//$this->logMessage("COOKIE:cs",$this->sid);						
					}
				} else {
					$sql = "SELECT * FROM statsession WHERE id=" . $this->sid;
					if($result = $db->sql_query($sql)) {
						if($cfg->log_type == "debug") {
							$this->logMessage("SQL",$sql);
						}
						if($row = $db->sql_fetchrow($result)) {
							$this->hits = intval($row[hits]);
							//$this->logMessage("HITS",$this->hits);	
						}
					}
				}
			} 
		}

		function getToken() {
			return md5(time() . $this->email . $this->pin);
		}

		function login($email,$pin) {
			global $cfg, $db;

			$where = "email='" . $email . "' AND pin=" . $pin;
			$sql = "SELECT * FROM webaccess WHERE " . $where;
			//echo "SQL:"; var_dump($sql);

			if($result = $db->sql_query($sql)) {
				if($row = $db->sql_fetchrow($result)) {

					$this->adminId =  intval($row['id']);
					$this->adminPin =  intval($row['pin']);
					$this->adminKey =  $row['admkey'];
					$this->adminEmail =  $row['email'];
					$this->adminLevel =  intval($row['level']);
					$this->pinExpire = intval($row['expire']);
					$this->pinExpire = $this->timestamp + intval($row['expire']) * 24 * 60 * 60;

					$this->adminToken =  $this->getToken();

					setcookie ( $this->cookieToken, $this->adminToken, time() + 7 * 24 * 60 * 60);
					setcookie ( $this->cookieEmail, $this->adminEmail, time() + 365 * 24 * 60 * 60 ); 
					
					$sql = "UPDATE webaccess SET token = '" . $this->adminToken . "', ip = '" . $this->ip . "' WHERE id = " . $this->adminId;
					$result = $db->sql_query($sql);
					if($result) {
						if($cfg->log_type == "debug") {
							$this->logMessage("ERR",$sql);
						}
					}
					
					$this->setActive(true);
					
					return true;
				} else {			
					return false;
				}
			} else {
				if($cfg->log_type == "debug") {
					$this->logMessage("ERR",$sql);
				}
			}

			return false;
		}

		function recover($email) {
			global $db,$cfg;

			$where = "email='" . $email . "' AND active = 1";
			$sql = "SELECT * FROM webaccess WHERE " . $where;

			if($result = $db->sql_query($sql)) {

				if($row = $db->sql_fetchrow($result)) {

					$pin = $row[pin];
					$subject  = "Wachtwoord vergeten";
                	$message .= "Uw wachtwoord: " . $pin . "<br>";

					$msg = new email();

					$msg->subject($subject);
					$msg->message($message);
					$msg->sender($cfg->email_info);
					$msg->receiver($email);

					if($msg->send()) {
						$result = 1;
					} else {
						$result = 0;
					}
				}
			} else {
				$result = 0;
			}

			return $result;
		}

		function setActive($state) {
			global $cfg, $db;

			$active = 0;
			if($state) $active = 1;
			$sql = "UPDATE webaccess SET active = " . $active . " WHERE id = " . $this->adminId;
			$result = $db->sql_query($sql);
			if($result) {
				if($cfg->log_type == "debug") {
					$this->logMessage("ERR",$sql);
				}
			}
		}
		
		function getParam($name) {
			if(is_array($this->params)) {
				foreach($this->params as $param) {
					$arg = explode("=",$param);
					if($arg[0] == $name) {
						return $arg[1];
					}
				}
			} else {
				$arg = explode("=",$this->params);
				if($arg[0] == $name) {
					return $arg[1];
				}
			}
		
			return null;
		}
		
		function isAdmin()
		{
			if($this->adminEditor) return true;

			return false;
		}

		function isSecure()
		{
			if($this->port == 443) return true;

			return false;
		}

		function clientType()
		{
			$tp = 0;

			if(isTrusted($this->ip))
			{
				$tp = 2;
			}
			else
			{
				$agent = getAgentInfo($this->agent);

				if($agent["bot"]) 
				{
					$tp = 1; 
				}
			}

			return $tp;
		}

		function log($page) {
			global $cfg, $db;
			//echo "<hr />session:" . $this->sid;

			$end = $this->timestamp;

			$sql = "UPDATE statsession SET end = " . $this->timestamp . ", hits = " . ($this->hits+1) . " WHERE id = " . $this->sid;

			$result = $db->sql_query($sql);
			if(!$result) {
				if($cfg->log_type == "debug") {
					$this->logMessage("ERR",$sql);
				}
			} else {
				//$this->logMessage("SQL",$sql);
			}
			
			$id = intval($this->timestamp / (24*60*60)) * 1000 + $page->id;
			
			$hits = 0;
			$sql = "SELECT * FROM statpage WHERE id=" . $id;
			if($result = $db->sql_query($sql)) {
				if($row = $db->sql_fetchrow($result)) {
					$hits = intval($row[hits]);
				}
			} else {
				if($cfg->log_type == "debug") {			
					$this->logMessage("ERR",$sql); 
				}
			}
			
			if($hits > 0) {
				$hits++;
				$sql = "UPDATE statpage SET hits = " . $hits . " WHERE id = " . $id;
				$result = $db->sql_query($sql);
				if(!$result) {
					if($cfg->log_type == "debug") {
						$this->logMessage("ERR",$sql); 
					}
				} else {
					//$this->logMessage("SQL",$sql);
				}
			} else {
				$sql = "INSERT INTO statpage (id, hits) VALUES (" . $id . ", 1)";
				$result = $db->sql_query($sql);
				if(!$result) {
					if($cfg->log_type == "debug") {
						$this->logMessage("ERR",$sql);
					}
				} else {
					//$this->logMessage("SQL",$sql);
				}
			}
		}
		
		function logMessage($name,$msg) {
			global $cfg;
			
			$filename = $cfg->log_path . "/client.log";
			$fp = fopen($filename, 'a');
			if($fp) {
				fwrite($fp, $name);
				fwrite($fp, '(');
				fwrite($fp, $this->org);
				fwrite($fp, '):=[');
				fwrite($fp, $msg);
				fwrite($fp, ']');
				fwrite($fp, chr(13));
				fwrite($fp, chr(10));

				fclose($fp);
			}
		}

	} // client

	function getGlobalVar($name) {
       	$value = "";

       	if (isset($_GET[$name])) {
			$value = $_GET[$name];
		} else {
			if(isset($_POST[$name])) {
               	$value = $_POST[$name];
			}
       	}

       	return $value;
	}

	function getSessionInfo($sid, $loadhits = false) {
		global $cfg,$db;

		$info = array();

		$sql = "SELECT * FROM statsession WHERE id=" . $sid;
		if($result = $db->sql_query($sql)) {
			if($row = $db->sql_fetchrow($result)) {
				$info[id] = $sid;
				$info[ip] = long2ip($row[ip]);
				$info[hits] = intval($row[hits]);
				$info[start] = new datumtijd(intval($row[start]));
				$info[end] = new datumtijd(intval($row[end]));
			}
		}
/*
		if($loadhits) {
			$hitlist = array();
			$cntlist = array();

			$sql = "SELECT * FROM client_hit WHERE session = " . $sid . " ORDER BY id";
			//echo "<hr>SQL:"; var_dump($sql);
			if($result = $db->sql_query($sql)) {
				$nr = 1;

				while($row = $db->sql_fetchrow($result)) {
					$id = $row[id];

					$rec = array();

					$rec[id] = $id;
					$rec[first] = 0;
					if($nr == 1) $rec[first] = 1;
					$rec[visits] = 1;
					$rec[hits] = 1;
					$rec[tag] = $row[tag];
					$rec[pid] = $row[pid];
					$rec[mod] = $row[pmod];
					$rec[op] = $row[op];
					$rec[words] = $row[words];
					$rec[datetime] = new datumtijd(intval($row[datetime]));
					//$rec[archive] = intval($row[archive]);

					$hitlist[$id] = $rec;

					$idx = 0;
					foreach ($cntlist as $hit) {
						if($hit[tag] == $row[tag]) {
							$idx = intval($hit[id]);
						}
					}

					if($idx > 0) {
						$hit = $cntlist[$id];
						$hit[hits]++;
					} else {
						$cntlist[$idx] = $rec;
					}

					$nr++;
				}
			}

			$info[hitlist] = $hitlist;
			$info[cntlist] = $cntlist;
		}
*/
		return $info;
	}

	function isTrusted($ip) {
		global $cfg,$db;

		$trusted = false;

		$sql = "SELECT * from webaccess WHERE ip = '$ip' AND active > 0 ORDER BY id";
		$result = $db->sql_query($sql);
		//echo "<hr>sql $result $sql<br>";

		if( $row = $db->sql_fetchrow($result) ) {
			$trusted = true;
		}

		return $trusted;
	}

	function getRefererInfo($ref) {
		$info = array();

		$info[all] = $ref;
		$info[extern] = false;

		$part = explode('?',$ref);
		
		if(strpos($part[0],$_SERVER["SERVER_NAME"]) === false) {
			$info[extern] = true;

			if(count($part) == 2) {
				$info[domain] = getUrlDomain($part[0]);

				$arg_list = getUrlParams($part[1]);
				$info[params] = getSearchArg($info[domain],$arg_list);
			} else {
				$info[domain] = getUrlDomain($ref);
			}
		}

		return $info;
	}

	function getUrlDomain($str) {
		$domain = "";

		$str = str_replace("http://","",$str);
       	$part = explode("/",$str);
       	$part = explode(".",$part[0]);

       	switch(count($part)) {
			case 3:
				$domain = $part[1];
				break;
			
			case 4:
				$domain = $part[1] . "." . $part[2];
				break;

			case 5:
				$domain = $part[1] . "." . $part[2] . "." . $part[3];
				break;
			default:
				break;
       	}

		return $domain;
	}

	function getUrlParams($url) {
		$list = array();

		$vars = explode('&',$url);

		foreach ($vars as $v) {
			$a = explode("=",$v);
			if(count($a) == 2) {
				$label = $a[0];
				$str = $a[1];
				$list[$label] = $str;
			}
		}

		return $list;
	}

	function getSearchArg($domain,$list) {	
		$str = "";

		switch($domain) {
			case "google":
			case "startpagina":
			case "startpagina.nl":
			case "bing":
			case "ask":
			case "altavista":
			case "vinden":
				$str .= $list["q"];
				break;

			case "images.google":
			case "images.google.be":
			case "images.google.de":
			case "images.google.ch":
			case "images.google.co.uk":
			case "images.google.com":
			case "images.google.com.lb":
				$str .= $list["imgrefurl"];
				break;

			case "yahoo":
			case "yahoo.com":
			case "search.yahoo":
			case "search.yahoo.com":
				$str .= $list["p"];
				break;

			case "upc":
			case "upc.nl":
			case "www.upc.nl":
				$str .= $list["searchform_simple3_q1"];
				break;

			case "detelefoongids":
			case "detelefoongids.nl":
			case "www.detelefoongids.nl":
				$str .= $list["searchTerms"];
				break;

			case "zoeken":
				$str .= $list["query"];
					break;
		
			default:
				$str = "unkown: " . $domain;
				break;
		}

		return convertParamsString($str);
		//return $str;
	}

	function getAgentInfo($agent) {
		$agent = strtolower($agent);

		$info = array();

		$browsers = array('msie', 'firefox', 'safari', 'webkit', 'opera', 'netscape', 'konqueror', 'gecko', 'vagabondo');
		$webbots = array('googlebot', 'yandexbot', 'slurp', 'msnbot', 'dotbot', 'bingbot');
		$known = array_merge($browsers,$webbots);


		$info["identity"] = $agent;

		$pattern = '#(?<browser>' . join('|', $known) .  ')[/ ]+(?<version>[0-9]+(?:\.[0-9]+)?)#';

		$info["name"] = "?";
		$info["bot"] = false;
		if(strlen($agent) == 0) $info = "-";

		if (preg_match_all($pattern, $agent, $matches)) {
			$i = count($matches['browser'])-1;

			if($i >= 0) {
				$name = $matches['browser'][$i];
				$info["name"] = $name . " " .  $matches['version'][$i];
				$info["bot"] = in_array($name,$webbots);
			}
		}

		return $info;
	}

	function convertParamsString($str) {
		$str = str_replace("%20"," ",$str);
		$str = str_replace("%26","&",$str);
		$str = str_replace("%2F","/",$str);
		$str = str_replace("%3D","=",$str);
		$str = str_replace("%3F","?",$str);

		return $str;
	}
/*
	function archiveSessions($limit = 1000) {
		global $db;

		$max = $limit;

		$nu = new datumtijd();

		$end = $nu->dtm - (8 * 60 * 60);

		$sql = "SELECT * from client_session WHERE end < $end AND archive = 0 ORDER BY id";
		$result = $db->sql_query($sql);
		//echo "<hr>sql($result = $sql<br>";

		while ( $row = $db->sql_fetchrow($result) ) {
			$session = $row[id];
			$info = getSessionInfo($session,true);

			$agent = $info[agent];

			if(count($info[hitlist]) == 0) {
				$sql = "DELETE FROM client_session WHERE id=" .  $info[id];
				//echo "<hr>REMOVE NO-HITS:"; var_dump($info);
  				$db->sql_query($sql);
			}
			
			if(($agent[bot]) || (isTrusted($info[ip]))) {
				//echo "<hr>REMOVE:"; var_dump($info);
				//if(isTrusted($info[ip])) echo "IP=" . $info[ip];

				$sql2 = "DELETE FROM client_hit WHERE session=" . $session;
  				$result2 = $db->sql_query($sql2);
				//echo "sql($result2) = $sql2<br>";
	
				$sql3 = "DELETE FROM client_session WHERE id=" .  $session;
  				$result3= $db->sql_query($sql3);
				//echo "<br>sql($result3) = $sql3<br>";
	
				$sql4 = "DELETE FROM shop_cart WHERE session=" . $session;
  				$result4= $db->sql_query($sql4);
				//echo "<br>sql($result4) = $sql4<br>";
	
				//$max--;
			} else {
				//echo "<hr>ARCHIVE:"; var_dump($info);

				$visits = 0;
				$hits = 0;

				foreach ($info[cntlist] as $hit) {
					if($hit[archive] == 0) {
						$hits += $hit[hits];

						archiveHit($session,$hit);
					}
				}

				$dagnr = $info[start]->dagNummer(); 
				updateLogCount($dagnr,1,$hits);

				$sql = "UPDATE client_session SET archive=1 WHERE id=" . $session;
				$result2 = $db->sql_query($sql);

				if(!$result2) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
				//echo "<hr>SQL:"; var_dump($sql);

				$max--;
			}
	
			if($max <= 0) break;
		}
	}

	function archiveHit($session,$hit)
	{
		global $db;

		//echo "<hr>HIT-ORG: "; var_dump($hit);

		$dagnr = $hit[datetime]->dagNummer(); 

		$res = 0;

		if(($hit[mod] == "")||($hit[mod] == "0"))
		{

			if($hit[tag] == "::")
			{
				$hit[mod] = "cnt";
				$hit[op] = "page";
				$hit[pid] = 1;
			}
			else
			{
				$arg = split(":",$hit[tag]);
				if(count($arg) == 1)
				{
					$id = intval($arg[0]);
					if($id > 0)
					{
						$hit[mod] = "cnt";
						$hit[op] = "page";
						$hit[pid] = $id;
					}
				}

				if(count($arg) == 2)
				{
					$hit[mod] = $arg[0];
					$hit[pid] = $arg[1];
				}

				if(count($arg) == 3)
				{
					$hit[mod] = $arg[0];
					$hit[op] = $arg[1];
					$hit[pid] = $arg[2];
				}
			}
		}

		$first = intval($hit[first]);
		$visits = intval($hit[visits]);
		$hits = intval($hit[hits]);

		switch($hit[mod])
		{
			case "cnt":
				if($hit[op] == "page")
				{
					//echo "<hr>HIT $nr : "; var_dump($hit);	
					$id = intval($hit[pid]);
					if($id == 0) $id = 1;
					$res = updateLog("log_page","page",$id,$dagnr,$first,$visits,$hits);
				}
				break;
						
			case "set":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$id = intval($hit[pid]);
				$res = updateLog("log_artset","artset",$id,$dagnr,$first,$visits,$hits);
				break;
						
			case "cat":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$id = intval($hit[pid]);
				$res = updateLog("log_category","catno",$id,$dagnr,$first,$visits,$hits);
				break;

			case "art":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$id = intval($hit[pid]);
				$res = updateLog("log_article","artcode",$id,$dagnr,$first,$visits,$hits);
				break;

			case "doc":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$id = intval($hit[pid]);
				$res = updateLog("log_doc","doc",$id,$dagnr,$first,$visits,$hits);
				break;

			case "prblm":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$id = intval($hit[pid]);
				$res = updateLog("log_problem","problem",$id,$dagnr,$first,$visits,$hits);
				break;

			case "shop":
				//echo "<hr>HIT $nr : "; var_dump($hit);	
				$res = 1; // Geen logging !!!
				break;

			default:
				$id = intval($hit[mod]);
				if($id > 0)
				{
					$res = updateLog("log_page","page",$id,$dagnr,$first,$visits,$hits);
				}
				else
				{
					
					echo "<hr>HIT-UNKNOWN:"; var_dump($hit);
				}
				break;
		}
					
		if($res)
		{	
			$sql = "UPDATE client_hit SET archive=1 WHERE session=" . $session . " AND tag='" . $hit[tag] . "'";
			$result = $db->sql_query($sql);
			if(!$result) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
			//echo "<hr>SQL-UPDATE:"; var_dump($sql);
		}
	}

	function updateLog($table,$key,$id,$dagnr,$first,$visits,$hits)
	{
		global $db;

		$sql = "SELECT * FROM $table WHERE " . $key . " = " . $id . " AND day = " . $dagnr;
		//echo "<hr>SQL:"; var_dump($sql);
		if($result = $db->sql_query($sql))
		{
			if($row = $db->sql_fetchrow($result))
			{
				$first = $row[first] + $first;
				$hits = $row[hits] + $hits;
				$visits = $row[hits] + $visits;

				$sql = "UPDATE $table SET first=$first, visits=$visits, hits=$hits WHERE day=" . $dagnr . " AND " . $key . "=" . $id ;
				$result = $db->sql_query($sql);
				if(!$result) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
			}
			else
			{
				$sql = "INSERT INTO $table (day,$key,first,visits,hits) VALUES ($dagnr,$id,$first,$visits,$hits)";
				$result = $db->sql_query($sql);
				if(!$result) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
			}
		}
		else
		{
			if(TESTMODE) { echo "<hr>SQL-ERR:"; var_dump($sql); }
		}

		//echo "<hr>SQL:"; var_dump($sql);

		return $result;
	}

	function updateLogCount($dagnr,$visits,$hits)
	{
		global $db;

		$sql = "SELECT * FROM log_count WHERE day = " . $dagnr;
		//echo "<hr>SQL:"; var_dump($sql);
		if($result = $db->sql_query($sql))
		{
			if($row = $db->sql_fetchrow($result))
			{
				$visits += intval($row[visits]);
				$hits += intval($row[hits]);
			
				$sql = "UPDATE log_count SET visits=$visits, hits=$hits WHERE day=" . $dagnr;
				$result = $db->sql_query($sql);
				if(!$result) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
			}
			else
			{
				$sql = "INSERT INTO log_count (day,visits,hits) VALUES ($dagnr,1,1)";
				$result = $db->sql_query($sql);
				if(!$result) { if(TESTMODE) { echo "<hr>SQL:"; var_dump($sql); } }
			}
		}
		else
		{
			if(TESTMODE) { echo "<hr>SQL-ERR:"; var_dump($sql); }
		}

		//echo "<hr>SQL:"; var_dump($sql);

		return $result;
	}
*/

} // defined

?>