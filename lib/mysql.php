<?php
class mysql{
	/*
		MySQL Class.
	*/
	public $db = false;
	public $queries = 0;
	public $pqueries = 0;
	public $querytime = 0;
	public $querylist = array();
	
	
	public function connect($host, $user, $pass, $dbname, $persist){
		$start = microtime(true);
		try {
			$dsn 		= "mysql:host=$host;dbname=$dbname;charset=utf8"; // set the charset directly here
			$options 	= array(
				PDO::ATTR_PERSISTENT 			=> $persist ? true : false,
				PDO::ATTR_ERRMODE 				=> PDO::ERRMODE_EXCEPTION, //throw warning blah
				PDO::ATTR_EMULATE_PREPARES   	=> false, // Disable this shit
			);
			
			$this->db = new PDO($dsn, $user, $pass, $options); 
			$this->querytime += (microtime(true) - $start);			
			return $this->db;
		}
		catch (PDOException $durr) {
			// TODO: the getMessage is for debug purposes only. remove it on the final version
			die("<body bgcolor=0 text=ffea><font face=arial color=white><br><center><b>Couldn't connect to the MySQL server</b><br><br><small>".$durr->getMessage());
		}
	}
	
	// Transactions
	public function start(){
		$start = microtime(true);
		try{
			$result = $this->db->beginTransaction();
		}
		catch (PDOException $x){
			trigger_error("Could not start transaction", E_USER_WARNING);
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
			trigger_error("Could not end transaction", E_USER_WARNING);
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
			trigger_error("Could not undo transaction", E_USER_WARNING);
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
			trigger_error("RowCount failure", E_USER_WARNING);
			return false;
		}
	}
	public function quote($string) {return $this->db->quote($string);}
	public function escape($string) {return $string;}
	public function lastInsertId($obj = NULL) {return $this->db->lastInsertId($obj);}
	
	// Standard Query/Fetch/Result Functions
	public function query($query, $mode = PDO::FETCH_ASSOC){
		$calledfrom = $this->getqueryfile(); // We only need to get file and line number
		$start = microtime(true);
		
		try{
			$ref = $this->db->query($query, $mode);
			//$result = $this->num_rows($ref);
		}
		catch (PDOException $x){
			trigger_error("Query failure: $query | ".str_replace("You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near", "Error near: ", $this->db->errorInfo()[2]), E_USER_WARNING);
			//$result = false;// true;
			$q_error = true;
			$ref = false;
		}	
		
		$timetaken = microtime(true) - $start;
		$this->querytime += $timetaken;
		$this->queries++;
		$this->querylist[] = array($query, 0, $timetaken, isset($q_error), $calledfrom['file'], $calledfrom['line']);
		
		return $ref;
	}
	
	public function exec($query){
		$calledfrom = $this->getqueryfile();
		$start = microtime(true);
		try{
			$result = $this->db->exec($query);
		}
		catch (PDOException $x){
			trigger_error("Query (Exec) failure: $query", E_USER_WARNING);
			$result = false;
			$q_error = true;			
		}
		$timetaken = microtime(true) - $start;
		$this->querytime += $timetaken;
		$this->queries++;
		$this->querylist[] = array($query, 0, $timetaken, isset($q_error), $calledfrom['file'], $calledfrom['line']);
		
		return $result;
	}
	
	public function fetch($ref, $all = false, $style = PDO::FETCH_ASSOC){
		if (!$ref) return false;
		$start = microtime(true);
		try{
			$res = $all ? $ref->fetchAll($style) : $ref->fetch($style);
		}
		catch (PDOException $x){
			trigger_error("Fetch failure", E_USER_WARNING);
			$res = false;			
		}
		$this->querytime += (microtime(true) - $start);
		return $res;	
	}
	
	public function result($ref, $col = 0){
		if (!$ref) return false;
		$start = microtime(true);
		try{
			$res = $ref->fetchColumn($col);
		}
		catch (PDOException $x){
			trigger_error("Result failure", E_USER_WARNING);		
			$res = false;
		}
		$this->querytime += (microtime(true) - $start);
		return $res;
	}
	// Stubs
	public function resultq($query, $col = 0){
		$q = $this->query($query);
		$res = $this->result($q);
		return $res;
	}
	
	public function fetchq($query, $all = false, $style = PDO::FETCH_ASSOC){
		$q = $this->query($query);
		$res = $this->fetch($q, $all, $style);
		return $res;
	}
	

	public function prepare($query, $option=false){
		$calledfrom = $this->getqueryfile();
		$start = microtime(true);
		try{
			$res = $this->db->prepare($query);
		}
		catch (PDOException $x){
			trigger_error("Prepare failure | $x", E_USER_WARNING);		
			$res = false;
			$q_error = true;
		}
		$timetaken = microtime(true) - $start;
		$this->querytime += $timetaken;
		//$this->pqueries++; We don't count the initial prepare statement
		$this->querylist[] = array(
			$query,
			1,
			$timetaken,
			isset($q_error),
			$calledfrom['file'],
			$calledfrom['line'],
			true
		);
		return $res;
	}
	
	public function execute($ref, $cmd_array = NULL){
		if (!$ref){
			/*
				EXPLAINATION:
				
				Previously the board used emulated prepares, meaning that pquery errors would show up here.
				Now it doesn't, so the errors show up while preparing the query, and attempting to execute a broken $ref
				causes a fatal error.
				
				To prevent this mess that can't be caught in the try block (thanks PHP!), we just return false in the case.
			*/
			trigger_error("Execute failure for bad pquery | Values:(".implode(", ", $cmd_array).")", E_USER_WARNING);	
			return false;
		}
		
		$calledfrom = $this->getqueryfile();
		$start = microtime(true);
		try{
			$status = $ref->execute($cmd_array);
		}
		catch (PDOException $x){
			trigger_error("Execute failure | $x", E_USER_WARNING);		
			$status = false;
			$q_error = true;
		}
		$timetaken = microtime(true) - $start;
		$this->querytime += $timetaken;
		$this->pqueries++;
		$this->querylist[] = array(
			$ref->queryString." | Values:(".implode(", ", $cmd_array).")",
			1,
			$timetaken,
			isset($q_error),
			$calledfrom['file'],
			$calledfrom['line']
		);
		return $status;
	}
	
	//simple stub
	public function queryp($query, $vars){ //vars array
		$ref = $this->prepare($query);
		$res = $this->execute($ref, $vars);
		return $ref;
	}
	
	public function fetchp($query, $vars, $all = false){
		$ref = $this->queryp($query, $vars);
		$res = $this->fetch($ref);
		return $res;
	}
	
	public function resultp($query, $vars, $col = 0){
		$ref = $this->queryp($query, $vars);
		$res = $this->result($ref, $col);
		return $res;			
	}
	
	// Get file and line number for the query
	private function getqueryfile(){
		$x = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
		for ($i = 1; strpos($x['file'], "mysql.php"); $i++){
			$x = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $i+1)[$i];
		}
		return $x;
	}
}
	
?>