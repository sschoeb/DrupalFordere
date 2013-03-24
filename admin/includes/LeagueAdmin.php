<?php

class LeagueAdmin extends ChampAdminBase {
	
	
	public function __construct($champId) {
		parent::__construct($champId);
	}
	
	public function startSeason() {
		$this->generateGames ();
		$this->setUserPermissions ();
	}
	
	/**
	 * Generate all Game-Nodes which represents all the 
	 * games that must be played by all the teams
	 */
	private function generateGames() {
	
	}

}