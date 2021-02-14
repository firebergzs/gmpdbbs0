<?php
class Db {
	private $encrypted_data = "c4fYYa7xTUrRqsVRwfh/bhL7uqOKOLXncPpPno08bdU/jgwRGGT08q/vDciwcUgXtv8umn10dUQ4/Iel/wAOGg==";
	private $db_host = "";
	private $db_name = "";
	private $db_user = "";
	private $db_pw = "";

	function __construct() {
		$db_data = openssl_decrypt($this->encrypted_data, "AES-256-CBC", gethostname());
		$db_data_array = explode(";", "$db_data");
		$this->db_host = $db_data_array[0];
		$this->db_name = $db_data_array[1];
		$this->db_user = $db_data_array[2];
		$this->db_pw = $db_data_array[3];
	}

	function query($sql){
		$mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pw, $this->db_name);
		$result_array = array();
		if ($result = $mysqli->query($sql)) {
		    while ($row = $result->fetch_assoc()) {
				array_push($result_array, $row);    	
		    }
		    $result->close();
		}
		$mysqli->close();
		return $result_array;
	}

	function execute($sql){
		$mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pw, $this->db_name);
		$result_array = array();
		$result = $mysqli->query($sql);
		$mysqli->close();
		return $result;
	}
}
