<?php

class CupAdmin extends ChampAdminBase {
	
	private $currentCupRound;
	
	public function __construct($champId) {
		parent::__construct ( $this->champId );
		
		$currentCupRound = variable_get ( $this->getCurrentCupRoundSettingsKey (), 1 );
	}
	
	public function generateNextRound() {
		$teams = $this->getWinnerLastRound ();
		
		$this->increaseCurrentCupRound ();
		
		// When not even we add a Wildcard to generate a full tableau
		if (count ( $teams ) % 2 != 0) {
			$teams [] = new Freilos ();
		}
		
		// Randomize array -> this is like the real draw
		shuffle ( $teams );
		
		// Generate new cup games
		for($i = 0; $i < count ( $teams ); $i += 2) {
			GameFactory::createGame ( $teams [$i]->id, $teams [$i + 1]->id, $this->champId, $this->currentCupRound );
		}
		
		$this->updatePlayerPermissions ();
	}
	
	private function updatePlayerPermissions() {
	
	}
	
	private function getCurrentCupRoundSettingsKey() {
		return 'fordere_champ_cup' . $this->champId;
	}
	
	private function increaseCurrentCupRound() {
		// Change current cup nr in drupal settings
		$varKey = $this->getCurrentCupRoundSettingsKey ();
		$currentRoundNr = variable_get ( $varKey, 0 );
		$newRoundNr = $currentRoundNr + 1;
		variable_set ( $varKey, $newRoundNr );
		
		// Change current cup nr in this instance
		$this->currentCupRound = $newRoundNr;
	}
	
	/**
	 * Returns an array of Team-Objects which represents all the winnters
	 * from the last round
	 */
	private function getWinnerLastRound() {
	
	}
}