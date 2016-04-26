<?php
class mysql{
	/*
	MySQL Public Class. Function names mostly compatible with AB2.5x
	*/
	public $db = false;
	public $queries = 0;
	public $pqueries = 0;
	public $querytime = 0;
	public $querylist = array();
	
	// The install script required a split
	public function connect($host,$user,$pass,$persist){
		$start = microtime(true);
		try{
			$this->db = new PDO('mysql:host='.$host, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', PDO::ATTR_PERSISTENT => $persist ? true : false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)); //throw warning blah
			$this->querytime += (microtime(true) - $start);			
			return $this->db;
			}
		catch (PDOException $durr){
			/*	switch($durr->getCode()){
				case 1045: $msg = "Access denied (invalid username/password)."; break;
				case 2002: $msg = "Couldn't connect to the MySQL server."; break;
				
				default: $msg = "Unspecified reason";
			}*/
			die("An error occurred during MySQL connection.<br/><br/>".$durr->getMessage());
		}
	}
	public function selectdb($db, $is_install = false){
		try{
			$this->query("USE $db");
		}
		catch (PDOException $durr){
			if ($is_install) return false;
			else die("Couldn't select the database $db<br/><br/>".$durr->getMessage());
		}
	}
	
	// Transactions
	public function start(){
		$start = microtime(true);
		try{
			$result = $this->db->beginTransaction();
		}
		catch (PDOException $x){
			trigger_error("Could not start transaction", E_USER_ERROR);
			$result = false;
		}
		$this->querytime += (microtime(true) - $start);
		return $result;
	}
	
	public function end(){
		$start = microtime(true);
		try{
			$result = $this->db->commit();
		}
		catch (PDOException $x){
			trigger_error("Could not end transaction", E_USER_ERROR);
			$result = false;			
		}
		$this->querytime += (microtime(true) - $start);
		return $result;
	}
	
	public function finish($list = array(true)){
		foreach ($list as $queryres)
			if ($queryres === false && $queryres !== 0){
				$this->undo();
				return false;
			}
		$this->end();
		return true;
	}
	
	public function undo(){
		$start = microtime(true);
		try{
			$result = $this->db->rollBack(); //false on failure
		}
		catch (PDOException $x){
			trigger_error("Could not undo transaction", E_USER_ERROR);
			$result = false;
		}		
		$this->querytime += microtime(true) - $start;
		return $result;
	}
	
	// Helpers
	public function num_rows($ref) { //Exception code also here just in case
		try{
			$result = $ref->rowCount();
			return $result;
		}
		catch (PDOException $x){
			trigger_error("RowCount failure", E_USER_ERROR);
			return false;
		}
		}
	public function quote($string) {return $this->db->quote($string);}
	public function escape($string) {return $string;}
	
	// Standard Query/Fetch/Result Functions
	public function query($query){
		$start = microtime(true);
		
		try{
			$ref = $this->db->query($query);
			$result = $this->num_rows($ref);
		}
		catch (PDOException $x){
			trigger_error("Query failure: $query | ".str_replace("You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near", "Error near: ", $this->db->errorInfo()[2])."</small>", E_USER_ERROR);
			$result = false;// true;					
		}	
		
		$this->querytime += (microtime(true) - $start);
		$this->queries++;
		$this->querylist[] = array($query, 0);
		
		if ($result != false) return $ref;
		else return false;
	}
	
	public function exec($query){
		$start = microtime(true);
		try{
			$result = $this->db->exec($query);
		}
		catch (PDOException $x){
			trigger_error("Query (Exec) failure: $query", E_USER_ERROR);
			$result = false;			
		}
		$this->querytime += (microtime(true) - $start);
		$this->queries++;
		$this->querylist[] = array($query, 0);
		return $result;
	}
	
	public function fetch($ref, $all = false, $style = PDO::FETCH_ASSOC){
		if ($ref == false){
			//trigger_error("Called fetch without checking NULL value", E_USER_WARNING);
			return false;
		}
		$start = microtime(true);
		try{
			$res = $all ? $ref->fetchAll($style) : $ref->fetch($style);
		}
		catch (PDOException $x){
			trigger_error("Fetch failure", E_USER_ERROR);
			$res = false;			
		}
		$this->querytime += (microtime(true) - $start);
		return $res;	
	}
	
	public function result($ref, $col = 0){
		if ($ref == false){
			return false;
			//trigger_error("Called result without checking NULL value", E_USER_WARNING);
		}
		$start = microtime(true);
		try{
			$res = $ref->fetchColumn($col);
		}
		catch (PDOException $x){
			trigger_error("Result failure", E_USER_ERROR);		
			$res = false;
		}
		$this->querytime += (microtime(true) - $start);
		return $res;
	}
	// Stubs
	public function resultq($query, $col = 0){
		$q = $this->query($query);
		if (!$q) return false;
		$res = $this->result($q);
		return $res;
	}
	
	public function fetchq($query, $all = false, $style = PDO::FETCH_ASSOC){
		$q = $this->query($query);
		if (!$q) return false;
		$res = $this->fetch($q, $all, $style);
		return $res;
	}
	

	public function prepare($query, $option=false){
		$start = microtime(true);
		try{
			$res = $this->db->prepare($query);
		}
		catch (PDOException $x){
			trigger_error("Prepare failure", E_USER_ERROR);		
			$res = false;
		}
		$this->querytime += (microtime(true) - $start);
		return $res;
	}
	
	public function execute($ref, $cmd_array = NULL){
		$start = microtime(true);
		try{
			$status = $ref->execute($cmd_array);
		}
		catch (PDOException $x){
			trigger_error("Execute failure |$x", E_USER_ERROR);		
			$status = false;
		}
		$this->querytime += (microtime(true) - $start);
		$this->pqueries++;
		$this->querylist[] = array($ref->queryString." | Values:(".implode(", ", $cmd_array).")", 1);
		return $status;
	}
	
	//simple stub
	public function queryp($query, $vars){ //vars array
		$ref = $this->prepare($query);
		$res = $this->execute($ref, $vars);
		if ($res !== false) return $ref;
		else return false;
	}
	
	public function fetchp($query, $vars, $all = false){
		$ref = $this->queryp($query, $vars);
		if (!$ref) return false;
		$res = $this->fetch($ref);
		return $res;
	}
	
	public function resultp($query, $vars, $col = 0){
		$ref = $this->queryp($query, $vars);
		if (!$ref) return false;
		$res = $this->result($ref, $col);
		return $res;			
	}
}
	
?>