<?php

class ChampAdminBase {
	protected $champId;
	
	public function __construct($champId) {
		$this->champId = $champId;
	}
	
	/**
	 * Adds all players of this championschip to the "Players"-Permission-Group 
	 */
	protected function setUserPermissions() {
				
	}
}