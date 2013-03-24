<?php

class GameFactory {
	
	public static function createGame($homeTeam, $guestTeam, $champId, $round = null, $title, $bracket = null) {
		global $user;
		$team = new stdClass ();
		$team->type = 'fordere_game';
		$team->title = $title;
		$team->game_teamhomeid [LANGUAGE_NONE] [0] ['value'] = $homeTeam;
		$team->game_teamguestid [LANGUAGE_NONE] [0] ['value'] = $guestTeam;
		$team->game_type [LANGUAGE_NONE] [0] ['value'] = $champId;
		$team->field_game_cup_round [LANGUAGE_NONE] [0] ['value'] = $round;
		$team->field_game_bracket [LANGUAGE_NONE] [0] ['value'] = $bracket;
		$team->uid = $user->uid;
		node_submit ( $team );
		node_save ( $team );
	}
	
	public static $games = array ();
	
	public static function getGame($gameId) {
		
		if (! isset ( GameFactory::$games [$gameId] )) {
			GameFactory::$games [$gameId] = GameFactory::loadGame ( $gameId );
		}
		
		return GameFactory::$games [$gameId];
	}
	
	private static function loadGame($gameId) {
		return new Game ( $gameId, false, false );
	}
	
	public static function loadGames($championschipId) {
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $championschipId, '=' );
		
		$result = $query->execute ();
		if ($result) {
			foreach ( $result ['node'] as $gameStd ) {
				GameFactory::$games [$gameStd->nid] = new Game ( $gameStd->nid, false, false );
			}
		}
	}
	
	public static function getGamebyTeam($team1Id, $team2Id, $champId) {
		foreach ( GameFactory::$games as $game ) {
			if (($game->hometeamid == $team1Id || $game->hometeamid == $team2Id) && ($game->guestteamid == $team1Id || $game->guestteamid == $team2Id)) {
				if ($game->championschipid == $champId) {
					return $game;
				}
			}
		}
		return new Game();
	}
}

class GameState {
	public static $created = 1;
	public static $planned = 2;
	public static $finished = 3;
}

class Game {
	const WinnerBracket = 1;
	const LooserBracket = 0;
	
	public $id = null;
	public $hometeam = null;
	public $hometeamid = null;
	public $guestteam = null;
	public $guestteamid = null;
	public $registerDate = null;
	public $enterResultDate = null;
	public $playDate = null;
	private $location = null;
	private $locationId = null;
	public $pointsHomeTeam = null;
	public $pointsGuestTeam = null;
	public $championschipid;
	public $cupround = null;
	public $bracket = Game::WinnerBracket;
	public $title = "Game";
	
	public $state = 1;
	
	public function __construct($gameId = null, $nodeObj = null) {
		if ($gameId != null) {
			$this->id = $gameId;
			$this->load ( $nodeObj );
		
		}
	
	}
	
	public function create()
	{
		GameFactory::createGame($this -> hometeam -> id, $this -> guestteam -> id, $this -> championschipid, null, $this -> title);	
	}
	
	public function reset() {
		$gameStd = node_load ( $this->id );
		$gameStd->game_pointteamhome [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->game_pointteamguest [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->game_dateregistered [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->game_dateplay [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->game_locationid [LANGUAGE_NONE] [0] ['value'] = null;
		node_submit ( $gameStd );
		node_save ( $gameStd );
	}
	
	public function getLocation() {
		
		if ($this->location == null) {
			$this->location = new Location ( $this->locationId );
		}
		
		return $this->location;
	}
	
	public function getHomeTeam() {
		if ($this->hometeam == null) {
			$this->hometeam = Team::getTeamById ( $this->hometeamid );
		}
		return $this->hometeam;
	}
	
	public function getInfos() {
		$date = date ( 'd.m.Y H:i', $this->playDate );
		$location = $this->getLocation ()->name;
		return "Datum: " . $date . "<br />Lokal: " . $location . "<br/>";
	}
	
	public function getGuestTeam() {
		if ($this->guestteam == null) {
			$this->guestteam = Team::getTeamById ( $this->guestteamid );
		}
		
		return $this->guestteam;
	}
	
	static $ii = 0;
	
	private function load($gameNode = null) {
		
		if ($gameNode == null) {
			$gameNode = node_load ( $this->id );
		}
		
		$this->hometeamid = $gameNode->game_teamhomeid [LANGUAGE_NONE] [0] ['value'];
		$this->guestteamid = $gameNode->game_teamguestid [LANGUAGE_NONE] [0] ['value'];
		
		if (isset ( $gameNode->game_dateregistered [LANGUAGE_NONE] )) {
			$this->registerDate = $gameNode->game_dateregistered [LANGUAGE_NONE] [0] ['value'];
		}
		
		if (isset ( $gameNode->game_dateplay [LANGUAGE_NONE] )) {
			$this->playDate = $gameNode->game_dateplay [LANGUAGE_NONE] [0] ['value'];
		}
		if (isset ( $gameNode->game_pointteamguest [LANGUAGE_NONE] )) {
			$this->pointsGuestTeam = $gameNode->game_pointteamguest [LANGUAGE_NONE] [0] ['value'];
		}
		if (isset ( $gameNode->game_pointteamhome [LANGUAGE_NONE] )) {
			$this->pointsHomeTeam = $gameNode->game_pointteamhome [LANGUAGE_NONE] [0] ['value'];
		}
		
		if (isset ( $gameNode->field_game_result_enter [LANGUAGE_NONE] )) {
			$this->enterResultDate = $gameNode->field_game_result_enter [LANGUAGE_NONE] [0] ['value'];
		}
		
		if (isset ( $gameNode->field_game_bracket [LANGUAGE_NONE] )) {
			$this->bracket = $gameNode->field_game_bracket [LANGUAGE_NONE] [0] ['value'];
		}
		
		if (isset ( $gameNode->field_game_cup_round [LANGUAGE_NONE] )) {
			$this->cupround = $gameNode->field_game_cup_round [LANGUAGE_NONE] [0] ['value'];
		}
		
		$this->championschipid = $gameNode->game_type [LANGUAGE_NONE] [0] ['value'];
		
		if ($this->registerDate != null) {
			$this->state = 2;
		}
		
		if ($this->pointsGuestTeam != null && $this->pointsHomeTeam != null) {
			$this->state = 3;
		}
		
		if (isset ( $gameNode->game_locationid [LANGUAGE_NONE] [0] ['value'] )) {
			$this->locationId = $gameNode->game_locationid [LANGUAGE_NONE] [0] ['value'];
		}
		
		if ($this->isForfaitGame ()) {
			$this->state = 3;
		}
	}
	
	public function isForfaitGame() {
		return $this->championschipid != 2 && ($this->getHomeTeam ()->getLeagueForfait () || $this->getGuestTeam ()->getLeagueForfait ());
	}
	
	public function isForfaitLoose($teamId) {
		if ($this->getHomeTeam ()->getLeagueForfait () && $this->getHomeTeam ()->id == $teamId) {
			return true;
		}
		
		if ($this->getGuestTeam ()->getLeagueForfait () && $this->getGuestTeam ()->id == $teamId) {
			return true;
		}
		
		return false;
	}
	
	public function reload() {
		$this->load ( true );
	}
	
	public function enterResult($pointsTeamHome, $pointsTeamGuest, $sets = array()) {
		//TODO sets(S�tze pro Spiel) for each game
		$gameStd = node_load ( $this->id );
		$gameStd->game_pointteamhome [LANGUAGE_NONE] [0] ['value'] = $pointsTeamHome;
		$gameStd->game_pointteamguest [LANGUAGE_NONE] [0] ['value'] = $pointsTeamGuest;
		$gameStd->field_game_result_enter [LANGUAGE_NONE] [0] ['value'] = time ();
		
		node_submit ( $gameStd );
		node_save ( $gameStd );
	}
	
	public function getResult() {
		
		if ($this->state < 3) {
			return "-";
		}
		
		if ($this->isForfaitGame ()) {
			return "F";
		}
		
		$gameStd = node_load ( $this->id );
		return $gameStd->game_pointteamhome [LANGUAGE_NONE] [0] ['value'] . ":" . $gameStd->game_pointteamguest [LANGUAGE_NONE] [0] ['value'];
	}
	
	public function getInvertedResult() {
		if ($this->state < 3) {
			return "-";
		}
		
		$gameStd = node_load ( $this->id );
		return $gameStd->game_pointteamguest [LANGUAGE_NONE] [0] ['value'] . ':' . $gameStd->game_pointteamhome [LANGUAGE_NONE] [0] ['value'];
	}
	
	private function getTimeForId($id) {
		$times = Championschip::getGameTimes ();
		return $times [$id];
	}
	
	public function reservate($locationId, $date, $time) {
		$gameStd = node_load ( $this->id );
		
		$dateParts = explode ( '-', $date );
		$timeParts = explode ( ':', $this->getTimeForId ( $time ) );
		$dateplay = mktime ( $timeParts [0], $timeParts [1], 0, $dateParts [1], $dateParts [2], $dateParts [0] );
		
		$gameStd->game_dateregistered [LANGUAGE_NONE] [0] ['value'] = time ();
		$gameStd->game_locationid [LANGUAGE_NONE] [0] ['value'] = $locationId;
		$gameStd->game_dateplay [LANGUAGE_NONE] [0] ['value'] = $dateplay;
		node_submit ( $gameStd );
		node_save ( $gameStd );
	}
	
	public function getDescription() {
		$champ = Championschip::CreateChampionschip ( $this->championschipid );
		return $champ->getGameDescription ( $this );
	}
	
	public function shouldShowCancelResultButton() {
		return time () - 600 <= $this->enterResultDate;
	}
	
	public function removeResult() {
		$gameStd = node_load ( $this->id );
		$gameStd->game_pointteamhome [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->game_pointteamguest [LANGUAGE_NONE] [0] ['value'] = null;
		$gameStd->field_game_result_enter [LANGUAGE_NONE] [0] ['value'] = null;
		node_submit ( $gameStd );
		node_save ( $gameStd );
	}
}


