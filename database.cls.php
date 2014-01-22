<?php
// VERSION: 11 (04/07/2013)

if(!defined("DATABASE_LAYER")) {
    define("DATABASE_LAYER","database");

    if(!defined("CONFIG_LAYER")) {
echo "REDEFINED<hr>";
//		require_once( dirname(__FILE__) . "/config.cls.php");
	}
	
    class database {
		var $db_connect_id;
		var $dbname;
		var $dbhost;
		var $query_result;
		var $row = array();
		var $rowset = array();
		var $num_queries = 0;
		var $org;
		
		//
		// Constructor
		//
		function database($org = "?") {
			global $cfg;

			$this->org = $org;
			
			if(!is_object($cfg)) {
				$cfg = new config();
				//$this->logMessage("NEW","config");
			} else {
				//$this->logMessage("USE","config");
			}
			
			$this->dbname = $cfg->dbname;
			$this->dbuname = $cfg->dbuname;
			$this->dbhost = $cfg->dbhost;

			$this->query_result = "";

			if($cfg->dbpersistency)
			{
				$this->db_connect_id = @mysql_pconnect($this->dbhost, $this->dbuname, $cfg->dbpass);
			}
			else
			{
				$this->db_connect_id = @mysql_connect($this->dbhost, $this->dbuname, $cfg->dbpass);
			}

			if($this->db_connect_id)
			{
				
				$dbselect = @mysql_select_db($this->dbname);
				if(!$dbselect)
				{
					@mysql_close($this->db_connect_id);
					$this->db_connect_id = false;
				}
				else
				{
					$this->sql_query("SET NAMES " . $cfg->charset);	
					$this->sql_query("SET character_set_results = " . $cfg->charset);
					$this->sql_query("SET character_set_client = " . $cfg->charset);
					$this->sql_query("SET character_set_connection = " . $cfg->charset);
	//				$this->sql_query("SET character_set_database = " . $cfg->charset);
	//				$this->sql_query("SET character_set_server = " . $cfg->charset);
					$this->sql_query("SET collation_server = " . $cfg->collation);
					$this->sql_query("SET collation_database = " . $cfg->collation);
				}
			}
			else
			{
				$this->db_connect_id = false;
			}
		}

		//
		// Other base methods
		//
		function sql_close() {
			global $cfg;
			
			if($this->db_connect_id)
			{
				if($this->query_result)
				{
					@mysql_free_result($this->query_result);
				}
				$result = @mysql_close($this->db_connect_id);
				return $result;
			}
			else
			{
				return false;
			}
		}

		//
		// Base query method
		//
		function sql_query($query = "", $transaction = FALSE) {
			global $cfg;
			// Remove any pre-existing queries
			unset($this->row[$this->query_result]);
			unset($this->rowset[$this->query_result]);
			unset($this->query_result);

			if($query != "") {
				$this->query_result = @mysql_query($query, $this->db_connect_id);
			}

			if(! $this->query_result) {
				if($cfg->log_type == "debug") {
					$this->logMessage($this->query_result,$query);
				}
			}

			return $this->query_result;
		}

		function sql_update($table, $where, $set) {
			global $cfg;
			$sql = "UPDATE " . $table . " SET " . $set . " WHERE " . $where;
			$result = array("errno" => 0, "sql" => $sql);
			
			$res = $this->sql_query($sql);
			if(!res) {
				$result['errno'] = 12;
			}
			
			return $result;
		}

		//
		// Other query methods
		//
		function sql_numrows($query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$result = @mysql_num_rows($query_id);
				return $result;
			} else {
				return false;
			}
		}

		function sql_affectedrows() {
			if($this->db_connect_id) {
				$result = @mysql_affected_rows($this->db_connect_id);
				return $result;
			} else {
				return false;
			}
		}

		function sql_numfields($query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$result = @mysql_num_fields($query_id);
				return $result;
			} else {
				return false;
			}
		}

		function sql_fieldname($offset, $query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$result = @mysql_field_name($query_id, $offset);
				return $result;
			} else {
				return false;
			}
		}

		function sql_fieldtype($offset, $query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$result = @mysql_field_type($query_id, $offset);
				return $result;
			} else {
				return false;
			}
		}

		function sql_fetchrow($query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$this->row[intval($query_id)] = @mysql_fetch_array($query_id);
				return $this->row[intval($query_id)];
			} else {
				return false;
			}
		}

		function sql_fetchrowset($query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				unset($this->rowset[$query_id]);
				unset($this->row[$query_id]);
				while($this->rowset[$query_id] = @mysql_fetch_array($query_id)) {
					$result[] = $this->rowset[$query_id];
				}
				return $result;
			} else {
				return false;
			}
		}

		function sql_fetchfield($field, $rownum = -1, $query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				if($rownum > -1) {
					$result = @mysql_result($query_id, $rownum, $field);
				} else {
					if(empty($this->row[$query_id]) && empty($this->rowset[$query_id])) {
						if($this->sql_fetchrow()) {
							$result = $this->row[$query_id][$field];
						}
					} else {
						if($this->rowset[$query_id]) {
							$result = $this->rowset[$query_id][$field];
						} else {
							if($this->row[$query_id]) {
								$result = $this->row[$query_id][$field];
							}
						}
					}
				}
				return $result;
			} else {
				return false;
			}
		}

		function sql_rowseek($rownum, $query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}
			if($query_id) {
				$result = @mysql_data_seek($query_id, $rownum);
				return $result;
			} else {
				return false;
			}
		}

		function sql_nextid() {
			global $cfg;
			if($this->db_connect_id) {
				$result = @mysql_insert_id($this->db_connect_id);
				return $result;
			} else {
				return false;
			}
		}

		function sql_freeresult($query_id = 0) {
			global $cfg;
			if(!$query_id) {
				$query_id = $this->query_result;
			}

			if ( $query_id ) {
				unset($this->row[$query_id]);
				unset($this->rowset[$query_id]);

				@mysql_free_result($query_id);

				return true;
			} else {
				return false;
			}
		}
		
		function sql_error($query_id = 0) {
			global $cfg;
			$result["message"] = @mysql_error($this->db_connect_id);
			$result["code"] = @mysql_errno($this->db_connect_id);
			return $result;
		}

		function tableColumns($name) {
			global $cfg;
			
			$tblcols = array();
			
			$sql = "SHOW COLUMNS FROM $name";
			
			if( ($result = $this->sql_query($sql)) ) {
				while ( $col = $this->sql_fetchrow($result) ) {
					array_push($tblcols, $col);
				}
			} 
			
			return $tblcols;
		}
		
		function backup() {
			global $cfg;
			
			$message = "";
			$tempdir = "tmp";
			$message .= "<br><br>Database backup: " . $this->dbname  . "<br />\n";

			$now = getdate(); 
			$dtmstr = sprintf("%04d%02d%02d", $now[year], $now[mon], $now[mday]);
			$bckfile = $tempdir . "/" . $this->dbname . "-db-" . $dtmstr . ".sql";
			if( file_exists($bckfile) ) unlink($bckfile);

			$tables = Array();
			$i = 0;

			$sql = "SHOW TABLE STATUS";
			if( ($result = $this->sql_query($sql)) ) {
				while ( $tbl = $this->sql_fetchrow($result) ) {
					if(strncmp($tbl[Name],"stat_",5)) {
						$columns = Array();
						$j = 0;

						$sql2 = "SHOW COLUMNS FROM $tbl[Name]";
						if( ($result2 = $this->sql_query($sql2)) ) {
							while ( $col = $this->sql_fetchrow($result2) ) {
								$columns[$j] = $col;
								$j += 1;
							}
							$tbl[Columns] = $columns;
						}
						$tables[$i] = $tbl;
						$i += 1;
					}
				}
			}
			
//var_dump($tables);
			$message .= "Table list:<br>";
			foreach ( $tables as $table ) {
				$message .= $table["Name"] . "<br>";
			}
			
			$now = getdate(); 
			$date = $now['mday'] . "-" . $now['mon'] . "-" . $now['year'];
			$time = sprintf("%02d:%02d",$now['hours'],$now['minutes']);

			$fp = fopen($bckfile,"w");
			if ( $fp )
			{
				$head = "# WebPhocus SQL Dump\n";
				$head .= "# Backup tijd: " . $date . " " . $time . "\n";
				$head .= "# Database : `" . $this->dbname . "` \n";
				fwrite($fp,$head);

				$drop = "# Verwijder '#' in de DROP TABLE regels bij de restore van een bestaande database\n";
				foreach ( $tables as $table ) {
					$name = $table["Name"];
					$drop .= "#DROP TABLE `$name`;\n";
				}
				
				$drop .= "#\n";
				$drop .= "#\n";
				fwrite($fp,$drop);

				foreach ( $tables as $table ) {
					$this->backupTableDef($table,$fp);
					$this->backupTableData($table,$fp);
				}

				fclose($fp);

				if( file_exists($bckfile) ) {
					$message .= "Database backup " . $bckfile . " OK. (bestand grootte: " . filesize($bckfile) . " bytes)<br>";
					chmod($bckfile,0777);
				} else {
					$message .= "<br> $bckfile ... Error.<br>";
					$message .= "Database backup " . $bckfile . " FOUT.<br>";
				}

			}
			else
			{
				$message .= "<br> $bckfile ... Error.<br>";
				if( file_exists($bckfile) ) unlink($bckfile);
				$message .= "Database backup " . $bckfile . " FOUT.<br>";
			}

			if( file_exists($bckfile) ) {
				chmod($bckfile,0666);
			}

			return $message;
		}

/*
		function format_date($dtm) {
			$s = split("/",$dtm);

			return $s[1] . "-" . $s[0] . "-" . $s[2];
		}
*/
		function backupTableDef($tbldef,$fp) {
			global $cfg, $db;

			$keys = "";
			$name = $tbldef[Name];

			$block = "# Tabel : $name\n";
			$block .= "CREATE TABLE `$name` ( ";
			$fld = 0;

			foreach( $tbldef[Columns] as $column ) {
				if($fld > 0) $block .= ",\n";
				$fld += 1;

				$block .= "`" . $column[Field] . "`";
				$block .= " " . $column[Type];
				if($column[Null] == "") $block .= " NOT NULL";


				if($column[Extra] != "auto_increment" ) {
					$n = strpos($column[Type],"(");
					if($n == 0) $n = strlen($column[Type]);
					$type = substr($column[Type],0,$n);

					switch($type) {
						case "int":
							$block .= " default '" . intval($column['Default']) . "'";
							break;

						default:
							$block .= " default '" . $column['Default'] . "'";
							break;
					}
				}

				if($column[Extra] != "") {
					$block .= " $column[Extra]";
				}

				if( $column[Key] == "PRI" ) {
					if($column[Extra] == "auto_increment" ) {
						$block .= " PRIMARY KEY";
					} else {
						if(strlen($keys) > 0) {
							$keys .= ",\n";
						}
						$keys .= "`$column[Field]`";
					}
				}
			}

			if(strlen($keys) > 0) {
				$block .= ", PRIMARY KEY ($keys)";
			}

		//	$block .= ") TYPE=$tbldef[Type] COMMENT='$tbldef[Comment]';\n";
			$block .= ") COMMENT='$tbldef[Comment]';\n";

			fwrite($fp,$block);
		}

		function backupTableData($tbldef,$fp) {
			global $cfg, $db;

			$keys = "";
			$name = $tbldef[Name];

			$block = "# Gegevens uit tabel : $name\n";

			$sql = "SELECT * from $name";
			if( ($result = $this->sql_query($sql)) )
			{
				while ( $row = $this->sql_fetchrow($result) ) {
					$fld = 0;
					$block .= "INSERT INTO `$name` VALUES (";
					foreach( $tbldef[Columns] as $column )
					{
						if($fld > 0) $block .= ",";
						$data = $this->fieldFormat($column[Type],$row[$fld]);
						$block .= $data ;
						$fld += 1;
					}

					$block .= ");\n";
				}
			}

			$block .= "# END:" . $name . "\n\n";

			fwrite($fp,$block);
		}

		function fieldFormat($type,$data) {
			global $cfg;
			
			if( is_integer(strpos($type,"int"))) {
				$dbfield = $data;
			} else {
				if( is_integer(strpos($type,"decimal"))) {
					$dbfield = $data;
				} else {
		//			$data = mysql_real_escape_string($data);
					$data = mysql_escape_string($data);
					$dbfield = "'" . $data . "'";
				}
			}

			return $dbfield;
		}		
		
		function logMessage($name,$msg) {
			global $cfg;
			
			$filename = $cfg->log_path . "/database.log";
			$fp = fopen($filename, 'a');
			if($fp) {
				fwrite($fp, time());
				fwrite($fp, ':');
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
		
    } // class database

} // if ... define

?>
