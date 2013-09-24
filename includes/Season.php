<?php

class Season {
	
	public static function getCurrentSeason() {
		return new Season ( Season::getCurrentSeasonId () );
	}
	
	public static function getCurrentSeasonId() {
		return variable_get('fordere_currentseason', 1);
	}
	
	public static function getAllSeasons()
	{
		$season = array();
		$seasonsDb = db_select('fordere_season','s') -> fields('s', array('id')) -> execute() -> fetchAll();
		foreach ($seasonsDb as $seasonStd)
		{
			$season[$seasonStd->id] = new Season($seasonStd->id);
		}
		
		return $season;
	}
	
	public $phase = null;
	public $name = 'No name';
	public $championschips = array ();
	public $seasonId = null;
	
	public function __construct($seasonId) {
		$this->seasonId = $seasonId;
		$this->load ();
	}
	
	private function load() {
		$champs = db_select ( 'fordere_championschip' )->fields ( 'fordere_championschip' )->condition ( 'seasonid', $this->seasonId )->execute ()->fetchAll ();
		
		foreach ( $champs as $champ ) {
			$this->championschips [] = Championschip::CreateChampionschip ( $champ->id );
		}
		$season = db_select('fordere_season', 's') -> fields('s', array('name','phase'))->condition('id', $this -> seasonId)->execute()->fetch();
		$this -> name = $season -> name;
 		$this -> phase = $season -> phase;
	}
	
	public static function sortGames($game1, $game2) {
		if ($game1->playDate < $game2->playDate) {
			return - 1;
		} else if ($game1->playDate > $game2->playDate) {
			return 1;
		}
		return 0;
	}
	
	public static function sortGamesReverse($game1, $game2) {
		if ($game1->playDate > $game2->playDate) {
			return - 1;
		} else if ($game1->playDate < $game2->playDate) {
			return 1;
		}
		return 0;
	}
	
	public function getUpcommingGames($teamId = null) {
		$games = array ();
		
		foreach ( $this->championschips as $cs ) {
			$addGames = $cs->getUpcommingGames ( $teamId );
			$games = array_merge ( $games, $addGames );
		}
		
		uasort ( $games, array (
				'Season', 
				'sortGames' 
		) );
		
		if (count ( $games ) == 0) {
			return "";
		}
		
		$gameOut = '<div class="comminggamescontainer">';
		$lastDate = '';
		if (! setlocale ( LC_TIME, 'de_CH.UTF-8', 'de_DE', 'deu_deu', 'de', 'ge', 'de_DE.UTF8' )) {
		}
		foreach ( $games as $game ) {
			$nextDate = strftime ( '%A %d.%m.%y', $game->playDate );
			if ($nextDate != $lastDate) {
				$gameOut .= '<h3>' . $nextDate . '</h3>';
				$lastDate = $nextDate;
			}
			$time = date ( 'H:i', $game->playDate );
			$gameOut .= "<div class=\"comminggame\"><div class=\"commingloc\">" . $game->getLocation ()->name . "</div><div class=\"commingtime\">" . $time . "</div><span class=\"commingteam\"><a href='" . url ( 'node/' . $game->getHomeTeam ()->id ) . "'>" . $game->getHomeTeam ()->getName () . "</a> VS. <a href='" . url ( 'node/' . $game->getGuestTeam ()->id ) . "'>" . $game->getGuestTeam ()->name . "</a></span><br /><span class=\"commingcomment\">" . $game->getDescription () . ", Eingetragen am " . date ( 'd.m.y H:i', $game->registerDate ) . "</span></div>";
		}
		
		$gameOut .= '</div>';
		
		return $gameOut;
	}
	
	public function getShortlyPlayedGames() {
		$games = array ();
		
		foreach ( $this->championschips as $cs ) {
			$addGames = $cs->getShortlyPlayedGames ();
			$games = array_merge ( $games, $addGames );
		}
		
		uasort ( $games, array (
				'Season', 
				'sortGamesReverse' 
		) );
		
		if (count ( $games ) == 0) {
			return "";
		}
		
		//TODO: global setzen
		if (! setlocale ( LC_TIME, 'de_CH.UTF-8', 'de_DE', 'deu_deu', 'de', 'ge', 'de_DE.UTF8' )) {
		}
		$gameOut = '<h2>K&uuml;rzlich gespielte Spiele</h2><div class="comminggamescontainer">';
		
		$count = 0;
		$lastDate = '';
		foreach ( $games as $game ) {
			$nextDate = strftime ( '%A %d.%m.%y', $game->playDate );
			if ($nextDate != $lastDate) {
				
				if ($count > 30) {
					break;
				}
				
				$gameOut .= '<h3>' . $nextDate . '</h3>';
				$lastDate = $nextDate;
			}
			
			$count ++;
			$winhome = '';
			$winguest = '';
			if ($game->pointsHomeTeam > $game->pointsGuestTeam) {
				$winhome = 'win';
			} elseif ($game->pointsHomeTeam < $game->pointsGuestTeam) {
				$winguest = 'win';
			}
			
			$time = date ( 'H:i', $game->playDate );
			$gameOut .= "<div class=\"comminggame\"><div class=\"doneloc\"><a class='" . $winhome . "' href='" . url ( 'node/' . $game->getHomeTeam ()->id ) . "'>" . $game->getHomeTeam ()->getName () . "</a>&nbsp;&nbsp;&nbsp;" . $game->getResult () . "&nbsp;&nbsp;&nbsp;<a  class='" . $winguest . "' href='" . url ( 'node/' . $game->getGuestTeam ()->id ) . "'>" . $game->getGuestTeam ()->getName () . "</a></div><div class=\"commingtime\"></div><br /><span class=\"commingcomment\">" . $game->getDescription () . ", Gespielt am " . date ( 'd.m.y H:i', $game->playDate ) . ", " . $game->getLocation ()->name . "</span></div>";
		}
		
		$gameOut .= '</div>';
		
		//TODO: Weg in theme
		return $gameOut;
	}
	
	public function getRunningSeasonDashboard() {
		$out = array ();
		foreach ( $this->championschips as $cs ) {
			$out [$cs->getId ()] = drupal_get_form ( 'fordere_dashboard_' . $cs->getId (), $cs->getId () );
		}
		return $out;
	}
	
	public function getRegisterInformation() {
		
		$out = array ();
		
		foreach ( $this->championschips as $cs ) {
			$out [$cs->getId ()] = $cs->getRegisterInformation ();
		}
		
		$infos ['register'] = array (
				'#theme' => 'themeregisterinfo', 
				'child' => $out, 
				'#title' => t ( 'Anmeldephase' ), 
				'#description' => '
				<div class="row">
				 <div class="twelve columns">
    <div class="panel">
      <h3>T-Shirt Aktion 2013</h3>
      <p>Der Verein Fordere wird 10-j&auml;hrig! Und wir wollen dieses historische Ereignis w&auml;hrend der ganzen Saison feiern. </p><p>Wir legen los mit einem Jubil&auml;ums-T-Shirt, das du mit nur einem Klick w&auml;hrend der Registrierung f&uuml;r die Saison kaufen kannst.<br /> F&uuml;r l&auml;ppische 10 Franken geh&ouml;rt das T-Shirt dir - sehr wahrscheinlich der beste Deal aller Zeiten! 
	  </br>
	  <a class="button" href="/drupal/season/orderShirt"/>Shirt bestellen</a></p>
    </div>
  </div>
				</div>
				<div class="row"><div class="twelve columns">
				<p>Wir befinden uns im Moment in der Anmeldephase f&uuml;r die aktuelle Saison.<br />
				Hier kannst du dich f&uuml;r die einzelnen Wettbewerbe registrieren. Prop Person kostet dies 25.-. Dabei ist egal bei wievielen Disziplinen du teilnimmst.</p>
</div></div>' 
		);
		
		return $infos;
	}
	
	public function startSeason() {
		
		foreach ( $this->championschips as $cs ) {
			$cs->createGames ();
		}
	}

}
