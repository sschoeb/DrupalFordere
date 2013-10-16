<?php

class TeamFactory {
	private static $teams = array ();
	
	public static function getTeam($teamId) {
		if (count ( TeamFactory::$teams ) == 0) {
			TeamFactory::loadAll ();
		}
	}
	
	private static function loadAll() {
		
		$seasonId = Season::getCurrentSeasonId ();
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->join ( 'championschip', 'c', 'c.seasonid=?', $seasonId );
		$query->addField ( 'tic', 'teamid' );
		
		$teamResult = $query->execute ()->fetchAll ();
		
		$teamIds = array ();
		foreach ( $teamResult as $team ) {
			$teamIds [] = $team->teamid;
		}
	
	}
	
	public static function getTeamStates($champId) {
	
	}

}

class Team {
	//TODO create LeageTeam subclass
	public $locationId;
	public $playerId1;
	public $playerId2;
	public $id;
	public $league_wish = null;
	private $league_approved = null;
	private $league_group = null;
	private $league_approved_name = null;
	private $league_forfait = null;
	
	public $leaguePointCount = 0;
	public $leagueWin = 0;
	public $leagueDraw = 0;
	public $leagueLoose = 0;
	public $leagueSetWon = 0;
	public $leagueSetLoose = 0;
	
	public $homeGameCount = 0;
	public $guestGameCount = 0;
	
	public $name;
	public $player1;
	private $player2;
	private $location;
	
	private $championschips;
	
	public function __construct($teamId = null) {
		$this->id = $teamId;
		
		if ($this->id == - 1) {
			$this->name = "Freilos";
		}
	
	}
	
	public function getShortName()
	{
		return (substr($this -> name, 0,3));
	}
	
	public function getShortNamePointed($length)
	{
		if(strlen($this -> name) > $length)
		{
			return mb_substr($this -> name, 0,$length) . '...';
		}
		return $this -> name;
	}
	
	private static $teams = array ();
	
	public static function getTeamById($teamId) {
		if (! isset ( Team::$teams [$teamId] )) {
			Team::$teams [$teamId] = new Team ( $teamId );
		}
		
		return Team::$teams [$teamId];
	}
	
	public function getName() {
		if ($this->name == null) {
			$this->loadNodedata ();
		}
		return $this->name;
	}
	
	public function getLocationId() {
		if ($this->locationId == null) {
			$this->loadNodeData ();
		}
		return $this->locationId;
	}
	
	public function getLocation() {
		if ($this->location == null) {
			$this->location = new Location ( $this->getLocationId () );
		}
		
		return $this->location;
	}
	
	private function loadNodeData() {
		
		if($this -> id == -1){
			return;
		}
		
		$team = node_load ( $this->id );
		$this->name = $team->team_name [LANGUAGE_NONE] [0] ['value'];
		$this->locationId = $team->team_locationid [LANGUAGE_NONE] [0] ['value'];
		
		if (isset ( $team->field_team_league_forfait [LANGUAGE_NONE] )) {
			$this->league_forfait = $team->field_team_league_forfait [LANGUAGE_NONE] [0] ['value'] == 1;
		}
	}
	
	private function loadPlayers() {
		$query = db_select ( 'fordere_playerinteam', 'p' );
		$query->addField ( 'p', 'playerId' );
		$query->condition ( 'p.teamId', $this->id );
		$players = $query->execute ()->fetchAll ();
		
		$this->playerId1 = $players [0]->playerId;
		$this->playerId2 = $players [1]->playerId;
	}
	
	public function getPlayer1() {
		if ($this->playerId1 == null) {
			$this->loadPlayers ();
		}
		
		if ($this->player1 == null) {
			$this->player1 = Player::getPlayerByPlayerId ( $this->playerId1 );
		}
		
		return $this->player1;
	}
	
	public function getPlayer2() {
		if ($this->playerId2 == null) {
			$this->loadPlayers ();
		}
		
		if ($this->player2 == null) {
			$this->player2 = Player::getPlayerByPlayerId ( $this->playerId2 );
		}
		
		return $this->player2;
	}
	
	public function getLeagueApproved() {
		if ($this->league_approved == null) {
			$this->loadLeagueInfos ();
		}
		return $this->league_approved;
	}
	
	public function getLeagueForfait() {
		
		if ($this->league_forfait == null) {
			$this->loadNodeData ();
		}
		
		return $this->league_forfait;
	}
	
	public function getLeagueApprovedName() {
		if ($this->league_approved_name == null) {
			$this->league_approved_name = League::getLeagueName ( $this->getLeagueApproved () );
		}
		return $this->league_approved_name;
	}
	
	public function getLeagueGroup() {
		if ($this->league_group == null) {
			$this->loadLeagueInfos ();
		}
		return $this->league_group;
	}
	
	public function loadState($champId) {
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_teamguestid', 'value', $this->id, '=' );
		$query->fieldCondition ( 'game_type', 'value', $champId, '=' );
		
		$result = $query->execute ();
		if ($result) {
			foreach ( $result ['node'] as $gameStd ) {
				$game = GameFactory::getGame ( $gameStd->nid );
				
				if ($game->getHomeTeam ()->getLeagueForfait ()) {
					$this->setWinLooseState ( 4, 0 );
				} else if ($game->getGuestTeam ()->getLeagueForfait ()) {
					$this->setWinLooseState ( 0, 4 );
				} else if($game->pointsHomeTeam != null) {
					$this->setWinLooseState ( $game->pointsGuestTeam, $game->pointsHomeTeam );
				}
			}
			
			$this -> homeGameCount = count($result['node']);
		}
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_teamhomeid', 'value', $this->id, '=' );
		$query->fieldCondition ( 'game_type', 'value', $champId, '=' );
		
		$result = $query->execute ();
		if ($result) {
			foreach ( $result ['node'] as $gameStd ) {
				$game = GameFactory::getGame ( $gameStd->nid );
				if ($game->getHomeTeam ()->getLeagueForfait ()) {
					$this->setWinLooseState ( 0, 4 );
				} else if ($game->getGuestTeam ()->getLeagueForfait ()) {
					$this->setWinLooseState ( 4, 0 );
				} else if($game->pointsHomeTeam != null) {
					$this->setWinLooseState ( $game->pointsHomeTeam, $game->pointsGuestTeam );
				}
			}
			
			$this -> guestGameCount = count($result['node']);
		}
	}
	
	public function getChampionschips() {
		if ($this->championschips == null) {
			$this->loadChampionschips ();
		}
		return $this->championschips;
	}
	
	private function loadChampionschips() {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'teamid', $this->id );
		$query->addField ( 'tic', 'championschipid' );
		$champs = $query->execute ()->fetchAll ();
		foreach ( $champs as $champ ) {
			$this->championschips [] = Championschip::CreateChampionschip ( $champ->championschipid );
		}
	}
	
	private function loadLeagueInfos() {
		$teamquery = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$teamquery->condition ( 'tic.teamid', $this->id );
		$teamquery->addField ( 'tic', 'league_approved' );
		$teamquery->addField ( 'tic', 'league_group' );
		$tic = $teamquery->execute ()->fetch ();
		
		$this->league_approved = $tic->league_approved;
		$this->league_group = $tic->league_group;
	}
	
	private function setWinLooseState($me, $other) {
		//TODO point per game constants
		$this->leagueSetWon += $me;
		$this->leagueSetLoose += $other;
		
		if ($me < $other) {
			$this->leagueLoose ++;
		} elseif ($me > $other) {
			$this->leagueWin ++;
			$this->leaguePointCount += 3;
		} else {
			$this->leagueDraw ++;
			$this->leaguePointCount += 1;
		}
	}
	
	//TODO: keine statische Methode
	public static function getHomeLocation($teamId) {
		$team = node_load ( $teamId );
		$locationId = $team->team_locationid [LANGUAGE_NONE] [0] ['value'];
		
		$query = db_select ( 'fordere_location', 'l' );
		$query->condition ( 'l.id', $locationId );
		$query->addField ( 'l', 'name' );
		return $query->execute ()->fetchField ();
	}
	
	public function getAdditonalFields() {
		return $this->additonalFields;
	}
	
	public function getAdditionalFieldsKeys() {
		return array_keys ( $this->additonalFields );
	}
	
	public function resetAdditionalFields() {
		$this->additonalFields = array ();
	}
	
	public function loadAdditionalFields()
	{
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->join ( 'fordere_league', 'l', 'l.id=tic.league_wish' );
		$query->addField ( 'l', 'name' );
		$query->condition ( 'tic.championschipid', $this->championschips[0] );
		$query->condition ( 'tic.teamid', $this -> id );
		
		$this -> addAdditionalField('wishleague', $query->execute ()->fetchField ());
	}
	
	//TODO: Hack3000
	public function &__get($key) {
		
		if ($key == 'name') {
			$name = $this->getName ();
			return $name;
		}
		
		if (! isset ( $this->additonalFields [$key] )) {
			//throw new Exception ( 'Missing field: ' . $key );
			return $key . " Not found";
		}
		
		$var = $this->additonalFields [$key];
		return $var;
	}
	
	public function __set($key, $value) {
		$this->additonalFields [$key] = $value;
	}
	
	//TODO: gute Idee mit diesen additonal Fields?
	private $additonalFields = array ();
	public function addAdditionalField($key, $value) {
		$this->additonalFields [$key] = $value;
	}
	
	public function save() {
		global $user;
		
		$team = new stdClass ();
		$team->type = 'fordere_team';
		$team->title = $this->name;
		$team->team_name [LANGUAGE_NONE] [0] ['value'] = $this->name;
		$team->team_locationid [LANGUAGE_NONE] [0] ['value'] = $this->locationId;
		$team->uid = $user->uid;
		node_submit ( $team );
		node_save ( $team );
		
		$this->id = $team->nid;

		$this->insertPlayerInTeam ( $this->playerId1 );
		$this->insertPlayerInTeam ( $this->playerId2 );
		
		$this->loadPlayers ();
	}
	
	private function insertPlayerInTeam($playerId) {
		db_insert ( 'fordere_playerinteam' )->fields ( array (
				'teamid' => $this -> id, 
				'playerid' => $playerId 
		) )->execute ();
	}
	
	public static function getCurrentUserPlayerId() {
		global $user;
		return Team::getPlayerId ( $user->uid );
	}
	
	public static function getPlayerId($userId) {
		$seasonId = Season::getCurrentSeasonId ();
		
		$select = db_select ( 'fordere_player', 'p' );
		$select->addField ( 'p', 'Id' );
		$select->condition ( 'DrupalUserId', $userId );
		$select->condition ( 'seasonId', $seasonId );
		$playerId = $select->execute ()->fetchCol ();
		if (count ( $playerId ) > 0)
			return $playerId [0];
		
		return Team::createNewPlayer ( $seasonId, $userId );
	}
	
	public static function createNewPlayer($seasonId, $userId) {
		$playerId = db_insert ( 'fordere_player' )->fields ( array (
				'seasonId' => $seasonId, 
				'DrupalUserId' => $userId 
		) )->execute ();
		
		return $playerId;
	}
	
	public function removeTeam() {
		db_delete ( 'fordere_playerinteam' )->condition ( 'teamid', $this->id )->execute ();
	
		//TODO: remove team node
	}
	
	public static function getOtherPlayer($teamId, $playerId) {
		$query = db_select ( 'fordere_playerinteam', 'pit' );
		$query->condition ( 'pit.teamid', $teamId );
		$query->condition ( 'pit.playerId', $playerId, '!=' );
		$query->addField ( 'pit', 'playerId' );
		$wantedPlayer = $query->execute ()->fetchField ();
		
		return Player::getPlayerByPlayerId ( $wantedPlayer )->name;
	}
	
	public static function getTeamId($championschipId, $playerId) {
		$query = db_select ( 'fordere_playerinteam', 'pit' );
		$query->join ( 'fordere_teaminchampionschip', 'tic', 'pit.teamid=tic.teamid' );
		$query->condition ( 'tic.championschipid', $championschipId );
		$query->condition ( 'pit.playerid', $playerId );
		$query->addField ( 'pit', 'teamid' );
		return $query->execute ()->fetchField ();
	}
	
	public static function getTeamName($teamId) {
		$team = node_load ( $teamId );
		return $team->title;
	}
	
	public static function getTeamsForPlayer($playerId) {
		$teams = array ();
		$query = db_select ( 'fordere_playerinteam', 'pit' );
		$query->addField ( 'pit', 'teamid' );
		$query->condition ( 'playerid', $playerId );
		$result = $query->execute ()->fetchAll ();
		
		foreach ( $result as $teamStd ) {
			$teams [] = new Team ( $teamStd->teamid, null, false );
		}
		
		return $teams;
	}

}


class LeagueTeam extends Team
{
	
}
