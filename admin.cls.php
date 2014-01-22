<?php
// VERSION: 1 (01/10/2011)

if(!defined("ADMIN_LAYER"))
{
	define("ADMIN_LAYER","admin");

	class admin
	{
		var $id;
		var $username;
		var $realname;
		var $email;
		var $pwd;
		var $md5key;
		var $blocked;
		var $level;
		var $firstlogin;
		var $lastlogin;
		var $logins;
		var $session;
		var $expire;

		var $cookieId = "id";
		var $cookieKey = "key";
		var $cookieSession = "session";
		var $dberr = true;

		function admin()
		{
			global $db, $_SERVER, $_COOKIE;

			$this->id = 0; // Unknown ID
			$this->session = $_COOKIE[$this->cookieSession];
			$timestamp = time();

			if(strlen($this->session) > 10)
			{
				$sql = "SELECT * FROM adm_user WHERE session = '" . $this->session . "' AND expire < " . $timestamp;
echo "sql:" . $sql . "<hr>\n";
				if($result = $db->sql_query($sql))
				{
					if($row = $db->sql_fetchrow($result))
					{
						$this->ip = $row[ip];
						$this->username = $row[username];
						$this->realname = $row[realname];
						$this->blocked = intval($row[blocked]);
						$this->logins = intval($row[logins]);
						$this->level = intval($row[level]);
						$this->email = $row[email];
						$this->firstlogin = intval($row[firstlogin]);
						$this->lastlogin = intval($row[lastlogin]);
						$this->hits++;
						$this->firstlogin = new datumtijd(intval($row[firstlogin]));
						$this->lastlogin = new datumtijd(intval($row[lastlogin]));
					}
					else
					{
						//if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); }
					}
				}
				else
				{
					//if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); }
				}
			}	

                       	//if(time() < mktime(0, 0, 0, 10, 1, 2012)) $this->vat = 0.19;
		}

		function login($name,$pwd)
		{
			global $db, $_SERVER;

			$this->id = 0;
			$this->ip = $_SERVER["REMOTE_ADDR"];

			$sql = "SELECT * FROM adm_user WHERE username = '" . $name . "'";
			if($result = $db->sql_query($sql))
			{
				if($row = $db->sql_fetchrow($result))
				{
					$this->id = intval($row[id]);
					$this->username = $row[username];
					$this->email = $row[email];
					$this->pwd = $row[pwd];
					$this->md5key = $row[md5key];
					$this->level = intval($row[level]);
					$this->logins = intval($row[logins]) + 1;
					$this->firstlogin = intval($row[firstlogin]);
					$this->lastlogin = intval($row[lastlogin]);
					$this->blocked = (intval($row[blocked]) > 0) ? true : false;
					$this->session = "";
					$this->expire = 0;
		
					$key = md5($this->username . $pwd . $this->email);
// echo "<hr>"; var_dump($this); echo "<hr>"; echo "KEY:"; var_dump($key); echo "<hr>";
					if(($key == $this->md5key) && ($this->blocked == false))
					{
						return $this->newSession();	
					}


					return false;
				}
				else
				{
					$this->id = 0; // Unknown ID
					if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); echo "<hr>"; }
				}
			}
			else
			{
				$this->id = 0; // Unknown Session
				if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); echo "X"; }
			}
		}

		function createUser($name,$email,$pwd)
		{
			global $db, $_SERVER;

			$ip = $_SERVER["REMOTE_ADDR"];
			$key = md5($name . $pwd . $email);
			$this->keyExpire = $this->timestamp + (365 * 24 * 60 * 60);

			$sql = "INSERT INTO adm_user (username, email, pwd, md5key, ip) VALUES ('" . $name . "', '" . $email . "', '" . $pwd . "', '"  . $key . "', '" . $ip . "')";
			echo "<hr>SQL:"; 
			var_dump($sql);

			$result = $db->sql_query($sql);
			if(!$result)
			{
				if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); echo "<hr>\n"; }

				return false;
			}

			return true;
		}

		function newSession()
		{
			global $db, $_SERVER;

			$agent = $_SERVER["HTTP_USER_AGENT"];

			$this->expire = time() + 14*24*60*60;
			$this->session = md5($this->username . $this->ip . $this->agent . $this->id . $this->expire);


			$sql = "UPDATE adm_user SET ip='" . $this->ip . "' WHERE id=" . $this->id;
			$result = $db->sql_query($sql);
			if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }

			$sql = "UPDATE adm_user SET session='" . $this->session . "' WHERE id=" . $this->id;
			$result = $db->sql_query($sql);
			if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }

			$sql = "UPDATE adm_user SET expire=" . $this->expire . " WHERE id=" . $this->id;
			$result = $db->sql_query($sql);
			if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }

			$sql = "UPDATE adm_user SET logins=" . ($this->logins + 1) . " WHERE id=" . $this->id;
			$result = $db->sql_query($sql);
			if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }

			if($this->firstlogin == 0)
			{
				$this->firstlogin = time();
				$sql = "UPDATE adm_user SET firstlogin=" . $this->firstlogin . " WHERE id=" . $this->id;
				$result = $db->sql_query($sql);
				if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }
			}

			$this->lastlogin = time();
			$sql = "UPDATE adm_user SET lastlogin=" . $this->lastlogin . " WHERE id=" . $this->id;
			$result = $db->sql_query($sql);
			if(!$result) { if($this->dberr) { echo "<hr>SQL:"; var_dump($sql); } return false; }

			return true;
		}

		function listAll()
		{
			global $db;

			$list = array();

			$sql = "SELECT id, username, email, pwd, md5key, realname, session FROM adm_user";
			if($result = $db->sql_query($sql))
			{
				while($row = $db->sql_fetchrow($result))
				{
					$user = array();

					$user[id] = $row[id];
					$user[username] = $row[username];
					$user[email] = $row[email];
					$user[pwd] = $row[pwd];
					$user[md5key] = $row[md5key];
					$user[realname] = $row[realname];
					$user[session] = $row[session];

					array_push($list,$user);
				}
			}
			else
			{
				if($this->dberr) { echo "<hr>SQL-ERR:"; var_dump($sql); }
			}

			return $list;
		}

		function setCookies()
		{
			$expire = time() + 14*24*3600;

			setcookie($this->cookieId, $this->id, $expire); 
			setcookie($this->cookieKey, $this->md5key, $expire); 
		}

		function resetCookies()
		{
			$expire = time() + 5;

			setcookie ( $this->cookieId, 0, $expire ); 
			setcookie ( $this->cookieKey, "", $expire ); 
		}

	} // admin

} // defined

?>
