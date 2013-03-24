<?php

class Player {
	public $id;

	/**
	 * Kompletter Name des Spielers aus dem Drupal User Profil ausgelesen
	 * Felder:
	 * - field_firstname
	 * - field_lastname
	 */
	public $name;
	public $drupalUserId;

	/**
	 * Instanz des Drupal-User-Objekts welches den Drupal-User hinter dem Spieler
	 * representiert.
	 * Nicht direkt über diese Variable auf den Benutzer zugreifen, sondern über
	 * die Funktion getUser() um sicherzustellen, dass der User geladen wurde!
	 */
	private $user;

	public $remarks;
	public $payed;

	public function getUser() {
		if ($this->user == null) {
			$this->loadDrupalUser ();
		}
		return $this->user;
	}

	private function loadDrupalUser() {

		if (! isset ( Player::$users [$this->drupalUserId] )) {
			Player::$users [$this->drupalUserId] = user_load ( $this->drupalUserId );
		}
		$this->user = Player::$users [$this->drupalUserId];
	}

	private static $users = array ();

	/**
	 * Player hat folgende Factory-Methods:
	 * - getCurrentUserPlayer()
	 * - getPlayerbyDrupalUserId($drupalUserId)
	 * - getPlayerByPlayerId($playerId1)
	*/
	private function __construct() {
	}

	private function loadName() {
		$user = $this->getUser ();
		$this->name = $user->field_firstname [LANGUAGE_NONE] [0] ['value'] . ' ' . $user->field_lastname [LANGUAGE_NONE] [0] ['value'];
	}

	/**
	 * Gibt den Player für den aktuell angemeldeten Benutzer zurück
	 * Es wird nur die aktuelle Saison berücksichtigt
	 */
	public static function getCurrentUserPlayer() {
		global $user;
		return Player::getPlayerByDrupalUserId ( $user->uid );
	}

	public static function getPlayerByDrupalUserId($drupalUserId) {
		$player = new Player ();
		$player->drupalUserId = $drupalUserId;

		$seasonId = Season::getCurrentSeasonId ();
		$select = db_select ( 'fordere_player', 'p' );
		$select->addField ( 'p', 'Id' );
		$select->addField ( 'p', 'remarks' );
		$select->addField ( 'p', 'payed' );
		$select->condition ( 'DrupalUserId', $drupalUserId );
		$select->condition ( 'seasonId', $seasonId );
		$result = $select->execute ()->fetchAssoc ();


		$player->id = $result ['Id'];
		$player->remarks = $result ['remarks'];
		$player->payed = $result ['payed'];
		// Wenn noch kein Player für diesen Drupal User vorhanden ist wird ein neuer erstellt
		if (count ( $player->id ) == 0)
			$player->id = Team::createNewPlayer ( $seasonId, $drupalUserId );

			
		$player->loadName ();
		return $player;
	}

	public static function loadPlayerForChampionschip($champId) {
		$select = db_select ( 'fordere_player', 'p' );
		$select->join ( 'fordere_playerinteam', 'pit', 'p.id=pit.playerid' );
		$select->join ( 'fordere_teaminchampionschip', 'tic', 'tic.teamid=pit.teamid AND tic.championschipid=?', array (
				$champId
		) );
		$select->addField ( 'p', 'id' );
		$result = $select->execute ()->fetchAll ();

		$uid = array ();
		foreach ( $result as $elem ) {
			$player = Player::getPlayerByPlayerId($elem -> id);
			Player::$players [$player->id] = $player;
			$uid [] = $player->drupalUserId;
		}

		$users = user_load_multiple ( $uid );
		foreach ( $users as $id => $user ) {
			Player::$users [$id] = $user;
		}

		foreach ( Player::$players as $p ) {
			$p->loadName ();
		}
	}

	private static $players = array ();

	public static function getPlayerByPlayerId($playerId) {

		if (! isset ( Player::$players [$playerId] )) {
			$player = new Player ();
			$player->id = $playerId;
				
			$select = db_select ( 'fordere_player', 'p' );
			$select->addField ( 'p', 'drupalUserId' );
			$select->addField ( 'p', 'remarks' );
			$select->addField ( 'p', 'payed' );
			$select->condition ( 'id', $playerId );
			$result = $select->execute ()->fetchAssoc ();
				
			$player->id = $playerId;
			$player->drupalUserId = $result ['drupalUserId'];
			$player->remarks = $result ['remarks'];
			$player->payed = $result ['payed'];
				
			$player->loadName ();
				
			Player::$players [$playerId] = $player;
		}

		return Player::$players [$playerId];
	}

	public function getContactEmail() {
		if (! isset ( $this->getUser ()->field_phone )) {
			return "";
		}
		return $this->getUser ()->mail;
	}

	public function getPlayerPhotoPath() {
		$nopic = "/drupal/sites/all/themes/fordere_theme/images/noUserImg.png";

		if ($this->getUser ()->picture == null) {
			return $nopic;
		}

		$player2Image = $this->getUser ()->picture->filename;
		if (empty ( $player2Image ) || ! user_access ( 'fordere showuserimage' )) {
			return $nopic;
		}

		if(file_exists('public://' . "/styles/thumbnail/public/media/$player2Image"))
		{
			$filename = "../" . variable_get ( 'file_public_path', conf_path () . '/files' ) . "/styles/thumbnail/public/media/$player2Image";
			return $filename;
		}

		if(file_exists('public://' . "/styles/thumbnail/public/pictures/$player2Image"))
		{
			$filename = "../" . variable_get ( 'file_public_path', conf_path () . '/files' ) . "/styles/thumbnail/public/pictures/$player2Image";
			return $filename;
		
		}
		return $nopic;
	}

	public function getPhone() {

		if (! isset ( $this->getUser ()->field_phone )) {
			echo "J";
			return "";
		}

		return $this->getUser ()->field_phone [LANGUAGE_NONE] [0] ['value'];
	}

	public function getDrupalUserName() {
		if (! isset ( $this->getUser ()->name )) {
			return "";
		}
		return $this->getUser ()->name;
	}

}
