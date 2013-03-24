<?php

class Table {
	
	public $id;
	private $locationId;
	private $playTimes = array ();
	
	public function __construct($locationId, $tableId) {
		$this->id = $tableId;
		$this->locationId = $locationId;
		$this->load ();
	}
	
	private function load() {
	
	}
	
	public function getAvailablePlayTimes($date) {
		$query = db_select ( 'fordere_playtimes', 'pt' );
		$query->condition ( 'tableid', $this->id, '=' );
		$query->addField ( 'pt', 'weekday' );
		$query->addField ( 'pt', 'time' );
		$times = $query->execute ()->fetchAll ();
		
		if (! $times) {
			return array ();
		}
		
		$out = array ();
		foreach ( $times as $time ) {
			$timeSplit = explode ( ':', $time->time );
			$hour = $timeSplit [0];
			$minutes = $timeSplit [1];
			$day = date ( 'j', time () );
			$month = date ( 'm', time () );
			$year = date ( 'Y', time () );
			$out [] = date("H:i",mktime ( $hour, $minutes, 0, $month, $day, $year ));
		}
		
		return $out;
	}

}