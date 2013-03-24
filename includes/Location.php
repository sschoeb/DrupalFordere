<?php

class Location {
	
	public $id;
	public $name = "";
	public $tables = array ();
	
	public function __construct($id = null) {
		if ($id == null) {
			return;
		}
		$this->id = $id;
		$this->load ();
	}
	
	private static $names = array ();
	
	private function load() {
		if (isset ( Location::$names [$this->id] )) {
			$this->name = Location::$names [$this->id];
		} else {
			$query = db_select ( 'fordere_location', 'l' );
			$query->condition ( 'l.id', $this->id );
			$query->addField ( 'l', 'name' );
			$this->name = $query->execute ()->fetchField ();
			Location::$names [$this->id] = $this->name;
		}
	}

	private function loadTables() {
		$tablequery = db_select('fordere_table', 't');
		$tablequery -> condition('t.locationid', $this -> id);
		$tablequery -> addField('t', 'id');
		$result = $tablequery -> execute()->fetchAll();
		if($result){
			foreach ($result as $tableStd) {
				$this->tables[] = new Table($this -> id, $tableStd->id);
			}
		}
	}

	
	public function getAvailablePlayTimes($date) {
		
		if($this -> tables == null){
			$this->loadTables ();
		}
		
		$out = array ();
		
		foreach ( $this->tables as $table ) {
			
			$out = array_merge ( $out, $table->getAvailablePlayTimes ($date) );
		}
		
		return $out;
	
	}

}

