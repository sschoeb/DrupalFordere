<?php

abstract class Championschip {
	
	public abstract function getTeamThemeType();
	
	public abstract function GetAdminInstance();
	
	public function getAdditionalTabs() {
		return array ();
	}
	
	private static $champs = array ();
	
	public static function CreateChampionschip($championschipId) {
		
		if ($championschipId == null || $championschipId == '') {
			throw new Exception ( "No Championschipid" );
		}
		
		if (isset ( Championschip::$champs [$championschipId] )) {
			return Championschip::$champs [$championschipId];
		}
		
		$select = db_select ( 'fordere_championschip', 'c' );
		$select->addField ( 'c', 'modus' );
		$select->condition ( 'id', $championschipId );
		$info = $select->execute ()->fetch ();
		
		$instance = new $info->modus ( $championschipId );
		Championschip::$champs [$championschipId] = $instance;
		return $instance;
	}
	
	protected $id = null;
	
	private function __construct($championschipId) {
		$this->id = $championschipId;
	}
	
	public function getId() {
		return $this->id;
	}
	
	public function getTeams() {
		
		Player::loadPlayerForChampionschip ( $this->id );
		
		$select = db_select ( 'node', 'n' );
		$select->join ( 'fordere_teaminchampionschip', 'tic', 'tic.teamid = n.nid' );
		
		$select->addField ( 'n', 'nid' );
		
		$select->condition ( 'n.type', 'fordere_team' );
		$select->condition ( 'tic.championschipid', $this->id );
		
		$teamNids = $select->execute ()->fetchCol ();
		
		$teams = node_load_multiple ( $teamNids );
		
		$build = array ();
		foreach ( $teams as $teamStd ) {
			$team = Team::getTeamById ( $teamStd->nid );
			$additional = $this->getAdditionalTeamFields ( $teamStd );
			foreach ( $additional as $key => $field ) {
				$team->addAdditionalField ( $key, $field );
			}
			$build [] = $team;
		}
		
		return $build;
	}
	
	public function suspendTeam($teamId) {
	
	}
	
	protected $users = null;
	
	protected function getPossiblePlayerList() {
		if ($this->users != null) {
			return $this->users;
		}
		
		//TODO: Bessere Lösung zur Abfrage suchen
		//TODO: Spielername (Vorname/Nachname) abfragen
		$query = db_select ( 'users', 'u' );
		$query->join ( 'fordere_player', 'pl', 'pl.drupalUserId = u.uid' );
		$query->join ( 'fordere_playerinteam', 'pli', 'pli.playerid = pl.id' );
		$query->join ( 'node', 't', 't.nid = pli.teamid' );
		$query->join ( 'fordere_teaminchampionschip', 'tc', 'tc.teamid = t.nid' );
		$query->condition ( 't.type', 'fordere_team' );
		$query->condition ( 'tc.championschipid', $this->id );
		$query->addField ( 'u', 'uid' );
		
		$drupalUsersPlaying = $query->execute ()->fetchAll ();
		// Ignore Admin-Account
		$drupalUsersPlaying [] = 1;
		
		$query = db_select ( 'users', 'u' );
		$query->addField ( 'u', 'name' );
		$query->addField ( 'u', 'uid' );
		
		if (count ( $drupalUsersPlaying ) > 0) {
			$uids = array ();
			foreach ( $drupalUsersPlaying as $uid ) {
				if (is_object ( $uid )) {
					$uids [] = $uid->uid;
				} else {
					$uids [] = $uid;
				}
			
			}
			$query->condition ( 'uid', $uids, 'NOT IN' );
		}
		
		$query->orderby ( 'name' );
		$drupalUsers = $query->execute ()->fetchAll ();
		
		global $user;
		$users = array ();
		foreach ( $drupalUsers as $useritem ) {
			
			// Eigener Benutzer nicht in Liste anzeigen
			if ($user->uid == $useritem->uid) {
				continue;
			}
			$userObj = user_load ( $useritem->uid );
			
			if($this -> user_has_role('Nicht Authentifizierte Benutzer', $userObj))
			{
				continue;
			}
			
			// TODO: Show User with no name?
			if (count ( $userObj->field_firstname ) > 0 && count ( $userObj->field_lastname ) > 0) {
				$playerid = Team::getPlayerId ( $useritem->uid );
				$users [$playerid] = $userObj->field_firstname ['und'] [0] ['value'] . ' ' . $userObj->field_lastname ['und'] [0] ['value'];
			}
		}
		
		asort($users);
		$this->users = $users;
		
		return $users;
	}
	
	
	//TODO Helper-Modul oder so erstellen
	function user_has_role($role, $user = NULL) {
		if ($user == NULL) {
			global $user;
		}
	
		if (is_array($user->roles) && in_array($role, array_values($user->roles))) {
			return TRUE;
		}
	
		return FALSE;
	}
	
	protected function GetCurrentPlayerName() {
		return Player::getCurrentUserPlayer ()->name;
	}
	
	protected function getPlayableLocations() {
		$select = db_select ( 'fordere_location', 'l' );
		$select->join ( 'fordere_table', 't', 'l.id=t.locationid' );
		$select->join ( 'fordere_tabletype', 'tt', 'tt.id=t.tabletype' );
		$select->join ( 'fordere_tabletypeinchampionschip', 'ttic', 'ttic.tabletypeid=tt.id' );
		$select->addField ( 'l', 'name' );
		$select->addField ( 'l', 'id' );
		$select->condition ( 'ttic.championschipid', $this->id );
		$select->orderby ( 'name' );
		$locations = $select->execute ()->fetchAll ();
		
		$out = array ();
		foreach ( $locations as $location ) {
			$out [$location->id] = $location->name;
		}
		return $out;
	}
	
	protected function getPlayableTimesForLocation($gameId, $callbackWrapper, $locationId, $date) {
		
		$location = new Location ( $locationId );
		//$location -> getAvailablePlayTimes($date)
		

		$out = array (
				'time' => array (
						'#prefix' => '<td>', 
						'#type' => 'select', 
						'#options' => Championschip::getGameTimes () 
				), 
				'back' => array (
						'#type' => 'button', 
						'#value' => 'Zurueck', 
						'#name' => $gameId . '_back', 
						'#ajax' => array (
								'callback' => 'league_callback', 
								'wrapper' => $callbackWrapper 
						) 
				), 
				'commit' => array (
						'#type' => 'submit', 
						'#value' => 'Spiel eintragen', 
						'#name' => $gameId, 
						'#suffix' => '</td></tr>' 
				) 
		);
		return $out;
	}
	
	protected function getPlayableTimes($gameId, $callbackWrapper) {
		
		$game = new Game ();
		
		$out = array (
				'time' => array (
						'#title' => t('Zeit'),
						'#prefix' => '<td>', 
						'#type' => 'select', 
						'#options' => Championschip::getGameTimes () 
				), 
				'back' => array (
						'#type' => 'button', 
						'#value' => 'Zurueck', 
						'#name' => $gameId . '_back', 
						'#ajax' => array (
								'callback' => 'league_callback', 
								'wrapper' => $callbackWrapper 
						) 
				), 
				'commit' => array (
						'#type' => 'submit', 
						'#value' => 'Spiel eintragen', 
						'#name' => $gameId, 
						'#suffix' => '</td></tr>' 
				) 
		);
		
		return $out;
	}
	
	public static function getGameTimes() {
		$times = array ();
		$times [] = '14:00';
		$times [] = '14:30';
		$times [] = '15:00';
		$times [] = '15:30';
		$times [] = '16:00';
		$times [] = '16:30';
		$times [] = '17:00';
		$times [] = '17:30';
		$times [] = '18:00';
		$times [] = '18:30';
		$times [] = '19:00';
		$times [] = '19:30';
		$times [] = '20:00';
		$times [] = '20:30';
		$times [] = '21:00';
		$times [] = '21:30';
		$times [] = '22:00';
		$times [] = '22:30';
		$times [] = '23:00';
		$times [] = '23:30';
		return $times;
	}
	
	protected function addTeam($team) {
		db_insert ( 'fordere_teaminchampionschip' )->fields ( array (
				'teamid' => $team->id, 
				'championschipid' => $this->id, 
				'league_wish' => $team->league_wish 
		) )->execute ();
		
		$query = db_select ( 'fordere_championschip', 'c' );
		$query->addField ( 'c', 'id', 'cid' );
		$query->addField ( 'c', 'modus', 'modus' );
		$query->condition ( 'c.registerOverChampionschip', $this->id );
		$result = $query->execute ()->fetchAll ();
		
		$this->sendConfirmation ( $team );
		
		if (count ( $result ) == 0)
			return;
		
		foreach ( $result as $championschip ) {
			$team->resetAdditionalFields ();
			$champ = new $championschip->modus ( $championschip->cid );
			$champ->addTeam ( $team );
		}
	}
	
	private function sendConfirmation($team) {
		$info = $this->getChampionschipInfo ();
		$params1 = array (
				'championschip' => $info->name, 
				'team' => $team, 
				'player1' => $team->getPlayer1 (), 
				'player2' => $team->getPlayer2 () 
		);
		$player1Mail = $team->player1->getContactEmail ();
		drupal_mail ( 'fordere', 'confirmation', $player1Mail, language_default (), $params1 );
		
		// TODO: info@fordere.ch ist hier hardcodiert 
		drupal_mail ( 'fordere', 'confirmation', 'info@fordere.ch', language_default (), $params1 );
		
		$params2 = array (
				'championschip' => $info->name, 
				'team' => $team, 
				'player1' => $team->getPlayer2 (), 
				'player2' => $team->getPlayer1 () 
		);
		$player2Mail = $team->getPlayer2 ()->getContactEmail ();
		drupal_mail ( 'fordere', 'confirmation', $player2Mail, language_default (), $params2 );
	}
	
	private function getCurrentUserTeam() {
		$teams = Team::getTeamsForPlayer ( Team::getCurrentUserPlayerId () );
		
		if (count ( $teams ) == 0) {
			return null;
		}
		
		$teamIds = array ();
		foreach ( $teams as $team ) {
			$teamIds [] = $team->id;
		}
		
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'teamid', $teamIds, 'IN' );
		$query->condition ( 'championschipid', $this->id, '=' );
		$query->addField ( 'tic', 'teamid' );
		$teamId = $query->execute ()->fetchField ();
		return Team::getTeamById ( $teamId );
	}
	
	private function getOpenGames($field, $teamId) {
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$query->fieldCondition ( $field, 'value', $teamId );
		$result = $query->execute ();
		if (! $result) {
			return array ();
		}
		
		return $result ['node'];
	}
	
	public function getOpenGameMails() {
		$team = $this->getCurrentUserTeam ();
		if ($team == null) {
			return "";
		}
		
		$games = $this->getOpenGames ( 'game_teamhomeid', $team->id );
		$games = array_merge ( $games, $this->getOpenGames ( 'game_teamguestid', $team->id ) );
		
		$mails = '';
		foreach ( $games as $gameStd ) {
			$game = new Game ( $gameStd->nid );
			
			if ($game->state > 1) {
				continue;
			}
			
			$teamout = null;
			if ($team->id == $game->getGuestTeam ()->id) {
				$teamout = $game->getHomeTeam ();
			} else if ($team->id == $game->getHomeTeam ()->id) {
				$teamout = $game->getGuestTeam ();
			}
			
			$mails .= $teamout->getPlayer1 ()->getContactEmail () . ',';
			$mails .= $teamout->getPlayer2 ()->getContactEmail () . ',';
		}
		
		return $mails;
	}
	
	public function getUpcommingGames($teamId = null) {
		//TODO just for a teamid
		

		$time = mktime ( 0, 0, 0, date ( 'm', time () ), date ( 'd', time () ), date ( 'Y', time () ) );
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_dateregistered', 'value', '', '<>' );
		$query->fieldCondition ( 'game_dateplay', 'value', $time, '>' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$query->fieldOrderBy ( 'game_dateplay', 'value' );
		$result = $query->execute ();
		if (! $result) {
			return array ();
		}
		
		$games = array ();
		$nodeIds = array ();
		foreach ( $result ['node'] as $gameId ) {
			$nodeIds [] = $gameId->nid;
		}
		
		$nodes = node_load_multiple ( $nodeIds );
		
		foreach ( $nodes as $nid => $obj ) {
			$game = new Game ( $nid, $obj );
			if ($game->pointsHomeTeam != null) {
				continue;
			}
			
			$games [] = $game;
		}
		
		return $games;
	}
	
	//TODO am falschen Ort
	protected function addPlayersToRole($roleId, $players) {
		$u = array ();
		foreach ( $players as $user ) {
			$u [] = $user->drupalUserId;
		}
		
		user_multiple_role_edit ( $u, 'add_role', $roleId );
	}
	
	//TODO am falschen Ort
	protected function removePlayerFromRole($roleId) {
		$result = db_query ( 'SELECT drupal_users.uid FROM drupal_users_roles LEFT JOIN drupal_users  ON drupal_users_roles.uid = drupal_users.uid WHERE drupal_users_roles.rid=:s', array (
				':s' => $roleId 
		) );
		$data = $result->fetchAll ();
		$uids = array ();
		foreach ( $data as $user ) {
			$uids [] = $user->uid;
		}
		
		user_multiple_role_edit ( $uids, 'remove_role', $roleId );
	}
	
	public function getShortlyPlayedGames() {
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$query->fieldOrderBy ( 'game_dateplay', 'value' );
		$result = $query->execute ();
		if (! $result) {
			return array ();
		}
		
		$nodeIds = array ();
		$games = array ();
		foreach ( $result ['node'] as $gameId ) {
			$nodeIds [] = $gameId->nid;
		}
		
		$nodes = node_load_multiple ( $nodeIds );
		
		foreach ( $nodes as $nid => $obj ) {
			$game = new Game ( $nid, $obj );
			if ($game->pointsHomeTeam == null) {
				continue;
			}
			
			$games [] = $game;
		}
		
		return $games;
	}
	
	public function getUserMails() {
		
		$teams = $this->getTeams ();
		
		$mails = '';
		foreach ( $teams as $team ) {
			$mails .= $team->getPlayer1 ()->getContactEmail () . ",";
			$mails .= $team->getPlayer2 ()->getContactEmail ();
		}
		
		return $mails;
	}
	
	public function getGamesForTeam($teamId) {
		$games = array ();
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_teamhomeid', 'value', $teamId, '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$result = $query->execute ();
		if ($result) {
			foreach ( $result ['node'] as $gameId ) {
				$games [] = new Game ( $gameId->nid );
			}
		}
		
		//TODO: Copy & Pase FTW
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_teamguestid', 'value', $teamId, '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$result = $query->execute ();
		
		if (! $result) {
			return $games;
		}
		foreach ( $result ['node'] as $gameId ) {
			$games [] = new Game ( $gameId->nid );
		}
		return $games;
	}
	
	protected function getGamesToPlay() {
		$games = array ();
		$currentPlayerId = Team::getCurrentUserPlayerId ();
		$teams = Team::getTeamsForPlayer ( $currentPlayerId );
		
		foreach ( $teams as $team ) {
			
			$newgames = $this->getGamesForTeam ( $team->id );
			$games = array_merge ( $games, $newgames );
		}
		
		return $games;
	}
	
	protected function getStartButton($gameId, $callbackWrapper) {
		return array (
				'#type' => 'button', 
				'#value' => 'Spiel abmachen' , 
				'#name' => $gameId, 
				'#limit_validation_errors' => array (), 
				'#prefix' => '<td>', 
				'#suffix' => '</td></tr>', 
				'#ajax' => array (
						'callback' => 'league_callback', 
						'wrapper' => $callbackWrapper 
				) 
		);
	}
	
	public abstract function getCurrentStateDescription();
	
	protected function getReserveForm($gameId, $callbackWrapper) {
		return array (
				'location' => array (
						'#title' => "Lokal", 
						'#type' => 'select', 
						'#options' => $this->getPlayableLocations (), 
						'#prefix' => '<td>', 
						'#required' => true 
				), 
				'date' => array (
						'#type' => 'date_popup', 
						'#date_format' => 'd.m.Y', 
						'#date_year_range' => '-0:+1', 
						'#label' => 'a', 
						'#title' => 'Datum',
						'#required' => true 
				), 
				'commit' => array (
						'#type' => 'button', 
						'#value' => 'Zeit auswaehlen ' , 
						'#name' => $gameId, 
						'#suffix' => '</td></tr>', 
						'#ajax' => array (
								'callback' => 'league_callback', 
								'wrapper' => $callbackWrapper 
						) 
				) 
		);
	}
	
	public abstract function getRules();
	
	public function getRegisterInformation() {
		$info = $this->getChampionschipInfo ();
		
		if ($this->isUserRegisteredForChampionschip ()) {
			
			$playerId = Team::getCurrentUserPlayerId ();
			$teamId = Team::getTeamId ( $this->id, $playerId );
			$homelocation = Team::getHomeLocation ( $teamId );
			return $out [] = array_merge ( array (
					'title' => $info->name, 
					'canregister' => false, 
					'nodirectregister' => true, 
					'team' => Team::getTeamName ( $teamId ), 
					'description' => $info->description, 
					'homelocation' => $homelocation, 
					'player2' => Team::getOtherPlayer ( $teamId, $playerId ) 
			), $this->getAdditionalRegisterInformation () );
		}
		
		if ($info->regover != 0) {
			
			//TODO: Output where to register
			return $out [] = array (
					'title' => $info->name, 
					'canregister' => true, 
					'nodirectregister' => true, 
					'description' => $info->description 
			);
		}
		
		return $out [] = array (
				'title' => $info->name, 
				'canregister' => true, 
				'nodirectregister' => false, 
				'description' => $info->description, 
				'link' => url ( 'season/registerForm/' . $this->id ) 
		);
	}
	
	protected $playerNotAvailable = 'Falls dein gew&uuml;nschter Mitspieler in dieser Liste nicht ausw&auml;hlbar ist, kann das mehrere Gr&uuml;nde haben: ';
	
	protected function getOtherPlayerNotAvailableDescription() {
		return '<ul><li>Er hat seine Account nicht per E-Mail best&auml;tigt. </li><li>Er ist nicht bei fordere.ch registriert</li><li>Er hat sein Profil nicht komplett ausgef&uuml;llt (Vorname oder Nachname leer)</li><li>Er hat sich bereits f&uuml;r diesen Wettbewerb angemeldet.</li></ul>';
	}
	
	protected function getNoPlayerAvailableForm() {
		$champInfo = $this->getChampionschipInfo ();
		$form ['title'] = array (
				'#type' => 'item', 
				'#title' => t ( $champInfo->name ), 
				'#description' => 'Anmeldung leider nicht m&ouml;glich da keine Mitspieler vorhanden sind. Dies kann passieren, wenn s&auml;mtliche bei fordere.ch registrierten Benutzer sich bereits einem Team angeschlossen haben. Wenn du dich mit einem Spieler abgesprochen hast, stelle sicher, dass er sich bei fordere.ch bereits registriert hat und sein Profil komplett (Vor- und Nachname) ausgef&uuml;llt ist.' 
		);
		return $form;
	}
	
	public abstract function getAdditionalRegisterInformation();
	
	private function isUserRegisteredForChampionschip() {
		global $user;
		$query = db_select ( 'fordere_player', 'p' );
		$query->join ( 'fordere_playerinteam', 'pit', 'p.id=pit.playerid' );
		$query->join ( 'fordere_teaminchampionschip', 'tic', 'pit.teamid=tic.teamid' );
		$query->condition ( 'p.drupaluserid', $user->uid );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->addField ( 'pit', 'teamid' );
		$result = $query->execute ()->fetchAll ();
		
		return count ( $result ) > 0;
	}
	
	public function getName() {
		$info = $this->getChampionschipInfo ();
		return $info->name;
	}
	
	public function getRegisterOver()
	{
		$info = $this -> getChampionschipInfo();
		return $info -> regover;
	}
	
	protected function getChampionschipInfo() {
		
		$select = db_select ( 'fordere_championschip', 'c' );
		$select->addField ( 'c', 'name' );
		$select->addField ( 'c', 'description' );
		$select->addField ( 'c', 'registerOverChampionschip', 'regover' );
		$select->condition ( 'id', $this->id );
		return $select->execute ()->fetch ();
	}
	
	protected function isTeamNameAvailable($name) {
		$sql = 'SELECT nid FROM {node} n, {fordere_teaminchampionschip} tic, {fordere_championschip} c WHERE tic.teamid = n.nid AND tic.championschipId=c.id AND c.seasonid=:sid AND tic.championschipId=\'' . $this->id . "'";
		$result = db_query ( $sql, array (
				':sid' => Season::getCurrentSeasonId () 
		) );
		
		foreach ( $result as $row ) {
			$team = node_load ( $row->nid );
			if ($team->title == $name) {
				return false;
			}
		}
		
		return true;
	}
	
	public abstract function getFixGameForm($form, $form_state);
	
	public function validateRegisterGame($form) {
	
		//form_set_error ( '', 'Zu diesem Zeitpunkt ist im angegebenen Lokal kein Tisch verf&uuml;gbar!' );
	}
	
	public function registerGame($form) {
		$gameId = $form ['triggering_element'] ['#name'];
		$values = $form ['storage'] [$gameId];
		$time = $form ['values'] ['children'] [$gameId] ['form'] ['time'];
		
		$game = new Game ( $gameId );
		$game->reservate ( $values ['location'], $values ['date'], $time );
		
		$game->reload ();
		$info = $this->getChampionschipInfo ();
		$params1 = array (
				'championschip' => $info->name, 
				'team1' => $game->getHomeTeam ()->getName (), 
				'team2' => $game->getGuestTeam ()->getName (), 
				'location' => $game->getLocation ()->name, 
				'date' => date ( 'Y.m.d H:i', $game->playDate ) 
		);
		
		$mails = array (
				'opendix@gmail.com', 
				$game->getHomeTeam ()->getPlayer1 ()->getContactEmail (), 
				$game->getHomeTeam ()->getPlayer2 ()->getContactEmail (), 
				$game->getGuestTeam ()->getPlayer1 ()->getContactEmail (), 
				$game->getGuestTeam ()->getPlayer2 ()->getContactEmail () 
		);
	
		//drupal_mail ( 'fordere', 'gamereservation', $mails, language_default (), $params1 );
	}
	
	public function cancelGame($form) {
		$gameId = $form ['triggering_element'] ['#name'];
		$game = new Game ( $gameId );
		$game->reset ();
	}
	
	public function removeResult($form) {
		$gameId = $form ['triggering_element'] ['#name'];
		$game = new Game ( $gameId );
		$game->removeResult ();
	}
	
	public abstract function themeGamesToPlay($variables);
	public abstract function enterResult($form);
	public abstract function getGamesToPlayForm(&$form_state);
	public abstract function validateEnterResult($form_state);
	public abstract function themeTeamAdmin($vars);
	
	public abstract function teamAdminAjaxCallback($form, $form_state);
	
	public abstract function getTeamAdminForm($form, &$form_state);
	
	public abstract function getMatchAdminForm($form, &$form_state);
	
	protected abstract function getAdditionalTeamFields($team);
	
	public abstract function getRegisterForm();
	
	public abstract function register($values);
	
	public abstract function validateRegisterForm($values);
	
	public abstract function getCurrentState();
	
	public abstract function createGames($form, $form_state);
	
	public abstract function gameStateAjaxCallback($form, $form_state);
	
	public abstract function getGameDescription($game);
	
	public abstract function updateRole($roleId, $round);
}