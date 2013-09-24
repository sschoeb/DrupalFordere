<?php

class League extends Championschip {
	private $leagueMaxTeamCount = 10;

	public function getTeamThemeType() {
		return "league";
	}

	public function GetAdminInstance() {
		return new LeagueAdmin ();
	}

	public function createGames($form, $form_state) {
		$query = db_select ( 'fordere_league', 'l' );
		$query->condition ( 'championschipid', $this->id );
		$query->addField ( 'l', 'id' );
		$query->addField ( 'l', 'teamspergroup' );
		$leagues = $query->execute ()->fetchAll ();
		
		foreach ( $leagues as $league ) {
			
			$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
			$query->condition ( 'league_approved', $league->id );
			$query->addField ( 'tic', 'teamid' );
			$teams = $query->execute ()->fetchAll ();
			
			$teamCount = count ( $teams );
			
			$groupCount = $league->teamspergroup;
			shuffle ( $teams );
			
			// Split in Groups
			for($i = 0; $i < count ( $teams ); $i ++) {
				$this->setGroup ( $teams [$i], $i % $groupCount + 1 );
			}
			
			for($i = 1; $i <= $groupCount; $i ++) {
				// Create Games for every Group
				$this->createGamesForGroup ( $league, $i );
			}
		}
	}

	private function createGamesForGroup($league, $group) {
		
		// Select all Teams in this Group
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'league_approved', $league->id );
		$query->condition ( 'league_group', $group );
		$query->addField ( 'tic', 'teamid' );
		$teamsStd = $query->execute ()->fetchAll ();
		
		shuffle ( $teamsStd );
		$teamCount = count ( $teamsStd );
		
		$teams = array ();
		foreach ( $teamsStd as $teamStd ) {
			$teams [] = $teamStd->teamid; // new Team ( );
		}
		
		// From old fordere.ch...
		$half = intval ( count ( $teams ) / 2 );
		
		$matchPlayed = array ();
		foreach ( $teams as $i => $team ) {
			for($j = 1; $j <= $half; $j ++) {
				$opponentIndex = ($i + $j) % $teamCount;
				$opponent = $teams [$opponentIndex];
				
				if (! isset ( $matchPlayed ["$opponent/$team"] )) {
					$matchPlayed ["$team/$opponent"] = 1;
					
					$game = new Game ();
					$game->hometeam = new Team ( $team );
					$game->guestteam = new Team ( $opponent );
					;
					$game->championschipid = $this->id;
					
					if ($team == null || $opponent == null) {
						$game->title = "Game";
					} else {
						$game->title = $game->hometeam->getName () . ' vs. ' . $game->guestteam->getName ();
					}
					
					$game->create ();
				}
			}
		}
	}

	private function setGroup($team, $group) {
		$query = db_update ( 'fordere_teaminchampionschip' );
		$query->fields ( array (
				'league_group' => $group 
		) );
		$query->condition ( 'championschipid', $this->id );
		$query->condition ( 'teamid', $team->teamid );
		$query->execute ();
	}

	public function getCurrentState() {
		drupal_add_js ( drupal_get_path ( 'theme', 'fordere_theme' ) . '/tablesorter.js' );
		$output = '';
		$leagues = $this->getLeaguesObj ();
		$i = 0;
		foreach ( $leagues as $leagueid => $league ) {
			
			$leaguename = $league->name;
			$leaguestd = $this->getLeague ( $leagueid );
			$groups = $this->getLeagueGroups ( $leagueid );
			
			if (count ( $groups ) > 0) {
				$output .= '<h2>' . $leaguename . '</h2>';
			}
			$finalplaces = $league->teamsfinalpergroup;
			sort ( $groups );
			
			foreach ( $groups as $group ) {
				if (count ( $groups ) > 1) {
					$output .= '<h3>' . $group . '. Gruppe</h3>';
				}
				$teams = $this->getTeamsForGroup ( $leagueid, $group );
				
				usort ( $teams, array (
						'League',
						'teamsort' 
				) );
				
				$output .= '<table id="leaguetable-' . $i . '"  class="leaguetable"><col width="20" /><col width="220" /><col width="30" /><col width="10" /><col width="10" /><col width="10" /><col width="20" /><col width="20" /><col width="20" /><col width="20" /><thead><tr><th>Rang</th><th>Team</th><th>Gesp.</th><th>S</th><th>U</th><th>N</th><th>Punkte</th><th>SV</th><th>SV</th><th>ER</th></tr></thead><tbody>';
				
				$teamstatscores = array ();
				$rang = 0;
				foreach ( $teams as $team ) {
					$gameCount = $team->leagueDraw + $team->leagueLoose + $team->leagueWin;
					$teamstatscores [] = 10000 * intval ( 10000 * $this->lowerbound ( $team->leaguePointCount / 3, $gameCount ) ) + count ( $teams ) - $rang;
					$rang ++;
				}
				
				arsort ( $teamstatscores, SORT_NUMERIC );
				$expectedrankings = array_flip ( array_keys ( $teamstatscores ) );
				
				$rang = 1;
				$class = '';
				foreach ( $teams as $team ) {
					if ($rang > $finalplaces) {
						$class = ' class="leaguetableline"';
					}
					
					$sv = ($team->leagueSetWon - $team->leagueSetLoose);
					if ($sv > 0) {
						$sv = '+' . $sv;
					}
					
					if ($team->getLeagueForfait ()) {
						$output .= '<tr><td ' . $class . '>' . $rang . '</td><td ' . $class . '><a href="' . url ( '/node/' . $team->id ) . '">' . $team->getName () . '</a></td><td ' . $class . ' colspan="8">Forfait</td></tr>';
					} else {
						$gameCount = $team->leagueDraw + $team->leagueLoose + $team->leagueWin;
						$er = $expectedrankings [$rang - 1] + 1;
						$output .= '<tr><td ' . $class . '>' . $rang . '</td><td ' . $class . '><a href="' . url ( '/node/' . $team->id ) . '">' . $team->getName () . '</a></td><td ' . $class . '>' . $gameCount . '</td><td ' . $class . '>' . $team->leagueWin . '</td><td ' . $class . '>' . $team->leagueDraw . '</td><td ' . $class . '>' . $team->leagueLoose . '</td><td ' . $class . '>' . $team->leaguePointCount . '</td><td ' . $class . '>' . $team->leagueSetWon . ':' . $team->leagueSetLoose . '</td><td  ' . $class . '>' . $sv . '</td><td  ' . $class . '>' . $er . '</td></tr>';
					}
					$rang ++;
				}
				$output .= '</tbody></table>';
				drupal_add_js ( 'jQuery(document).ready(function(){ jQuery("#leaguetable-' . $i . '").tablesorter(); } );', 'inline' );
				$i ++;
			}
		}
		return $output;
	}

	private function lowerbound($pos, $n) {
		if ($n) {
			$z = 1.95996397158435;
			$phat = 1.0 * $pos / $n;
			$result = ($phat + $z * $z / (3 * $n) - $z * sqrt ( ($phat * (1 - $phat) + $z * $z / (4 * $n)) / $n )) / (1 + $z * $z / $n);
		}
		
		return $result;
	}

	public static function teamsort($team1, $team2) {
		if ($team1->leaguePointCount == $team2->leaguePointCount) {
			
			$sv1 = $team1->leagueSetWon - $team1->leagueSetLoose;
			$sv2 = $team2->leagueSetWon - $team2->leagueSetLoose;
			if ($sv1 < $sv2) {
				return 1;
			} elseif ($sv1 > $sv2) {
				return - 1;
			}
			return strcmp ( $team1->getName (), $team2->getName () );
		}
		
		if ($team1->leaguePointCount < $team2->leaguePointCount) {
			return 1;
		}
		
		return - 1;
	}

	public function getLeagueGroups($leagueId) {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->condition ( 'tic.league_approved', $leagueId );
		$query->addField ( 'tic', 'league_group' );
		$result = $query->execute ()->fetchAll ();
		
		$groups = array ();
		foreach ( $result as $groupStd ) {
			$groups [] = $groupStd->league_group;
		}
		return array_unique ( $groups );
	}

	public function getTeamsForGroup($leagueid, $groupid) {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->condition ( 'tic.league_approved', $leagueid );
		$query->condition ( 'tic.league_group', $groupid );
		$query->addField ( 'tic', 'teamid' );
		$result = $query->execute ()->fetchAll ();
		
		$teams = array ();
		foreach ( $result as $teamStd ) {
			$team = Team::getTeamById ( $teamStd->teamid );
			$team->loadState ( $this->id );
			$team->getName ();
			$teams [] = $team;
		}
		return $teams;
	}

	public function getTeams() {
		$leagues = $this->getLeagues ();
		$teams = array ();
		foreach ( $leagues as $leagueId => $leagueName ) {
			$groups = $this->getLeagueGroups ( $leagueId );
			foreach ( $groups as $group ) {
				$teams = array_merge ( $teams, $this->getTeamsForGroup ( $leagueId, $group ) );
			}
		}
		
		return $teams;
	}

	public function getRegisteredTeams() {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->addField ( 'tic', 'teamid' );
		$result = $query->execute ()->fetchAll ();
		
		$teams = array ();
		foreach ( $result as $teamStd ) {
			$team = Team::getTeamById ( $teamStd->teamid );
			$team->loadState ( $this->id );
			$team->getName ();
			$teams [] = $team;
		}
		
		return $teams;
	}

	public function getRules() {
		return "Hallo das sind die Regeln der Saision 2011/2012";
	}

	protected function getAdditionalTeamFields($teamStd) {
		$data = array (
				'wishleague' => array (
						'#type' => 'item',
						'#title' => t ( $this->getWishLeagueForTeam ( $teamStd->nid ) ) 
				) 
		);
		$data ['league'] = array (
				'#type' => 'select',
				'#options' => $this->getLeagues (),
				'#default_value' => $this->getWishLeagueForTeam ( $teamStd->nid ) 
		);
		return $data;
	}

	public function isTeamInLeague($team) {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->condition ( 'teamid', $team->id );
		$query->condition ( 'championschipid', $this->id );
		$query->addField ( 'tic', 'league_approved' );
		$league = $query->execute ()->fetchField ();
		
		return $league != 0;
	}

	public function setLeague($teamId, $league) {
		db_update ( 'fordere_teaminchampionschip' )->fields ( array (
				'league_approved' => $league 
		) )->condition ( 'teamid', $teamId )->condition ( 'championschipid', $this->id )->execute ();
	}

	public function getRegisterForm() {
		$champInfo = $this->getChampionschipInfo ();
		$playerList = $this->getPossiblePlayerList ();
		if (count ( $playerList ) == 0) {
			return $this->getNoPlayerAvailableForm ();
		}
		
		$form ['title'] = array (
				'#type' => 'item',
				'#title' => t ( $champInfo->name ),
				'#description' => $champInfo->description 
		);
		$form ['teamname'] = array (
				'#type' => 'textfield',
				'#title' => t ( 'Teamname' ),
				'#required' => true 
		);
		$form ['location'] = array (
				'#type' => 'select',
				'#title' => t ( 'Heimlokal' ),
				'#options' => $this->getPlayableLocations () 
		);
		$form ['player1Name'] = array (
				'#type' => 'textfield',
				'#title' => t ( 'Spieler 1' ),
				'#default_value' => $this->GetCurrentPlayerName (),
				'#disabled' => TRUE 
		);
		$form ['player1Name'] = array (
				'#type' => 'textfield',
				'#title' => t ( 'Spieler 1' ),
				'#default_value' => $this->GetCurrentPlayerName (),
				'#disabled' => TRUE 
		);
		$form ['player2'] = array (
				'#type' => 'select',
				'#description' => $this->playerNotAvailable . $this->getOtherPlayerNotAvailableDescription (),
				'#title' => t ( 'Spieler 2' ),
				'#options' => $playerList 
		);
		$form ['league'] = array (
				'#type' => 'select',
				'#title' => t ( 'Wunschliga' ),
				'#options' => $this->getLeagues (),
				'#description' => 'Euer Team wird nach St&auml;rke in die entsprechende Liga eingeteilt. Hier kannst du angeben, in welcher Liga du dich f&uuml;r diese Saison anmelden willst. In der Anmeldungsbest&auml;tigung erh&auml;lst du dann weitere Informationen zur definitiven Einteilung.' 
		);
		$form ['submit'] = array (
				'#type' => 'submit',
				'#value' => 'Team anmelden' 
		);
		return $form;
	}

	public function validateRegisterForm($values) {
		$name = $values ['teamname'] ['#value'];
		if (! $this->isTeamNameAvailable ( $name )) {
			throw new Exception ( 'Teamname ist bereits vergeben!' );
		}
	}

	public function register($values) {
		// Get Data from Register-Form
		$name = $values ['teamname'] ['#value'];
		$locationId = $values ['location'] ['#value'];
		$userId1 = Team::getCurrentUserPlayerId ();
		$userId2 = $values ['player2'] ['#value'];
		$league = $values ['league'] ['#value'];
		
		// Create Team
		$team = new Team ();
		$team->name = $name;
		$team->locationId = $locationId;
		$team->playerId1 = $userId1;
		$team->playerId2 = $userId2;
		$team->league_wish = $league;
		$team->league = $league;
		$team->addAdditionalField ( 'Wunschliga', $this->getLeagueName ( $league ) );
		
		$team->save ();
		
		// Add Team to this Championschip
		$this->addTeam ( $team );
		
		return true;
	}
	private static $leagueNames = array ();

	public static function getLeagueName($league) {
		if (! isset ( League::$leagueNames [$league] )) {
			
			$select = db_select ( 'fordere_league', 'l' );
			$select->addField ( 'l', 'name' );
			$select->condition ( 'id', $league );
			League::$leagueNames [$league] = $select->execute ()->fetchField ();
		}
		return League::$leagueNames [$league];
	}

	public function getLeague($teamId) {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->join ( 'fordere_league', 'l', 'l.id=tic.league_approved' );
		$query->condition ( 'tic.teamid', $teamId );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->addField ( 'l', 'name' );
		$league = $query->execute ()->fetchField ();
		return $league;
	}

	public function getLeagues() {
		// TODO DEPrec
		$select = db_select ( 'fordere_league', 'l' );
		$select->addField ( 'l', 'name' );
		$select->addField ( 'l', 'id' );
		
		$select->condition ( 'championschipid', $this->id );
		$select->orderby ( 'name' );
		
		$leagues = $select->execute ()->fetchAll ();
		
		$out = array ();
		foreach ( $leagues as $league ) {
			$out [$league->id] = $league->name;
		}
		return $out;
	}

	public function createGamesLateTeam($teamId) {
		$team = new Team ( $teamId );
		$teams = $this->getTeamsForGroup ( $team->getLeagueApproved (), $team->getLeagueGroup () );
		
		$count = count ( $teams ) - 1;
		$half = ceil ( $count / 2 );
		for($i = 0; $i < $half; $i ++) {
			// Heimspiele
			$op = $this->getTeamWithMinHomeGameCount ( $teams, $teamId );
			$id = array_search ( $op, $teams );
			unset ( $teams [$id] );
			GameFactory::createGame ( $teamId, $op->id, $this->id, "Title" );
		}
		
		for($i = $half; $i < $count; $i ++) {
			// Gastspiele
			$op = $this->getTeamWithMinGuestGameCount ( $teams, $teamId );
			$id = array_search ( $op, $teams );
			unset ( $teams [$id] );
			GameFactory::createGame ( $op->id, $teamId, $this->id, "Title" );
		}
	}

	private function getTeamWithMinHomeGameCount($teams, $teamId) {
		$currTeam = null;
		foreach ( $teams as $team ) {
			if ($team->id == $teamId) {
				continue;
			}
			if ($currTeam == null) {
				$currTeam = $team;
				continue;
			}
			
			if ($team->homeGameCount < $currTeam->homeGameCount) {
				$currTeam = $team;
			}
		}
		
		return $currTeam;
	}

	private function getTeamWithMinGuestGameCount($teams, $teamId) {
		$currTeam = null;
		foreach ( $teams as $team ) {
			if ($team->id == $teamId) {
				continue;
			}
			
			if ($currTeam == null) {
				$currTeam = $team;
				continue;
			}
			
			if ($team->guestGameCount < $currTeam->guestGameCount) {
				$currTeam = $team;
			}
		}
		
		return $currTeam;
	}

	public function getLeaguesObj() {
		$select = db_select ( 'fordere_league', 'l' );
		$select->addField ( 'l', 'name' );
		$select->addField ( 'l', 'id' );
		$select->addField ( 'l', 'teamsfinalpergroup' );
		$select->condition ( 'championschipid', $this->id );
		$select->orderby ( 'name' );
		
		$leagues = $select->execute ()->fetchAll ();
		
		$out = array ();
		foreach ( $leagues as $league ) {
			$out [$league->id] = $league;
		}
		return $out;
	}

	public function getAdditionalRegisterInformation() {
		$league = $this->getWishLeagueForTeam ( Team::getTeamId ( $this->id, Team::getCurrentUserPlayerId () ) );
		return array (
				'league_wish' => $league 
		);
	}

	public function getWishLeagueForTeam($teamId) {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->join ( 'fordere_league', 'l', 'l.id=tic.league_wish' );
		$query->addField ( 'l', 'name' );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->condition ( 'tic.teamid', $teamId );
		return $query->execute ()->fetchField ();
	}

	static function sortbyleague($a, $b) {
		if ($a->getLeagueApproved () == null) {
			return - 1;
		}
		
		if ($b->getLeagueApproved () == null) {
			return 1;
		}
		
		if ($a->getLeagueApproved () < $b->getLeagueApproved ()) {
			return - 1;
		} else if ($a->getLeagueApproved () > $b->getLeagueApproved ()) {
			return 1;
		}
		return 0;
	}

	public function getTeamAdminForm($form, &$form_state) {
		$teams = $this->getRegisteredTeams ();
		
		usort ( $teams, array (
				'League',
				'sortbyleague' 
		) );
		
		$i = 1;
		foreach ( $teams as $team ) {
			$formout ['items'] [$team->id] ['nr'] = array (
					'#markup' => $i 
			);
			$formout ['items'] [$team->id] ['teamname'] = array (
					'#markup' => '<a href="mailto:' . $team->getPlayer1 ()->getContactEmail () . ';' . $team->getPlayer2 ()->getContactEmail () . '">' . $team->getName () . '</a><br /><b>' . $team->id . '</b>' 
			);
			$formout ['items'] [$team->id] ['player1'] = array (
					'#markup' => $team->getPlayer1 ()->name . "(" . $team->getPlayer1 ()->drupalUserId . ")<br />" . $team->getPlayer1 ()->getContactEmail () . '<br />' . $team->getPlayer1 ()->getPhone () 
			);
			$formout ['items'] [$team->id] ['player2'] = array (
					'#markup' => $team->getPlayer2 ()->name . "(" . $team->getPlayer2 ()->drupalUserId . ")<br />" . $team->getPlayer2 ()->getContactEmail () . '<br />' . $team->getPlayer2 ()->getPhone () 
			);
			
			$formout ['items'] [$team->id] ['wishleague'] = array (
					'#markup' => $team->wishleague 
			);
			
			$formout ['items'] [$team->id] ['leagueselect'] = array (
					'#prefix' => '<div id="leagueselect_' . $team->id . '">',
					'#suffix' => '</div>' 
			);
			
			if (! $this->isTeamInLeague ( $team ) && ! $this->isLeagueNewSet ( $form_state, $team->id )) {
				
				$formout ['items'] [$team->id] ['leagueselect'] ['league' . $team->id] = array (
						'#markup' => $team->league 
				);
				$formout ['items'] [$team->id] ['leagueselect'] ['submit' . $team->id] = array (
						'#type' => 'button',
						'#tree' => true,
						'#value' => t ( 'Eintragen' ),
						'#name' => $team->id,
						'#ajax' => array (
								'callback' => 'confirmleague_callback',
								'wrapper' => 'leagueselect_' . $team->id 
						) 
				);
			} else {
				
				if ($this->isLeagueNewSet ( $form_state, $team->id )) {
					$league = $form_state ['values'] ['league' . $team->id];
					$this->setLeague ( $team->id, $league );
				}
				
				$formout ['items'] [$team->id] ['leagueselect'] ['league' . $team->id] = array (
						'#markup' => $this->getLeague ( $team->id ) 
				);
			}
			
			$i ++;
		}
		
		$formout ['championschip'] = array (
				'#type' => 'hidden',
				'#value' => $this->id 
		);
		
		$formout ['#theme'] = 'adminteam';
		
		return $formout;
	}

	public function teamAdminAjaxCallback($form, $form_state) {
		$teamid = $form_state ['triggering_element'] ['#name'];
		return $form ['items'] [$teamid] ['leagueselect'] ['league' . $teamid];
	}

	public function getFixGameForm($form, $form_state) {
		$gameId = $form_state ['triggering_element'] ['#name'];
		return $form ['children'] [$gameId] ['form'];
	}

	private function isLeagueNewSet($form_state, $teamid) {
		if (empty ( $form_state ['triggering_element'] ['#name'] )) {
			return false;
		}
		
		if ($form_state ['triggering_element'] ['#name'] == $teamid) {
			return true;
		}
		
		return false;
	}

	public function themeTeamAdmin($vars) {
		$header = array (
				'Nr. ',
				'Teamname',
				'Spieler 1',
				'Spieler 2',
				'Wunschliga',
				'Liga' 
		);
		$form = $vars ['form'];
		$rows = array ();
		foreach ( element_children ( $form ['items'] ) as $teamid ) {
			$row = array ();
			$row [] = drupal_render ( $form ['items'] [$teamid] ['nr'] );
			$row [] = drupal_render ( $form ['items'] [$teamid] ['teamname'] );
			$row [] = drupal_render ( $form ['items'] [$teamid] ['player1'] );
			$row [] = drupal_render ( $form ['items'] [$teamid] ['player2'] );
			$row [] = drupal_render ( $form ['items'] [$teamid] ['wishleague'] );
			$row [] = drupal_render ( $form ['items'] [$teamid] ['leagueselect'] );
			$rows [] = $row;
		}
		
		$output = theme ( 'table', array (
				'header' => $header,
				'rows' => $rows 
		) );
		
		$output .= drupal_render_children ( $form );
		
		return $output;
	}

	function getGamesToPlayForm(&$form_state) {
		$out = array ();
		$games = $this->getGamesToPlay ();
		$i = 0;
		
		// TODO dont show when no games
		$out ['#prefix'] = '<div id="champform_' . $this->id . '"><table class="dashboardtable"><tr><th class="hometeamcol">Heimteam</th><th class="guestteamcol">Gastteam</th><th></th></tr>';
		$out ['#suffix'] = '</table></div>';
		$out ['#tree'] = TRUE;
		$out ['championschip'] = array (
				'#type' => 'hidden',
				'#value' => $this->id 
		);
		
		foreach ( $games as $game ) {
			
			$step = 1;
			if (! empty ( $form_state ['storage'] [$game->id] ['step'] )) {
				$step = $form_state ['storage'] [$game->id] ['step'];
			}
			
			if (isset ( $form_state ['triggering_element'] ['#name'] ) && $form_state ['triggering_element'] ['#name'] == $game->id) {
				$step ++;
			} else if (isset ( $form_state ['triggering_element'] ['#name'] ) && $form_state ['triggering_element'] ['#name'] == $game->id . '_back') {
				$step --;
			} else {
				$step = 1;
			}
			
			$form_state ['storage'] [$game->id] ['step'] = $step;
			
			$i ++;
			$out [$game->id] ['hometeam'] = array (
					'#prefix' => '<tr><td>',
					'#suffix' => '</td>',
					'#markup' => '<a href="' . url ( 'node/' . $game->getHomeTeam ()->id ) . '">' . $game->getHomeTeam ()->getName () . '</a>' 
			);
			$out [$game->id] ['guestteam'] = array (
					'#markup' => '<a href="' . url ( 'node/' . $game->getGuestTeam ()->id ) . '"> ' . $game->getGuestTeam ()->getName () . '</a>',
					'#prefix' => '<td>',
					'#suffix' => '</td>' 
			);
			
			switch ($game->state) {
				case GameState::$created :
					switch ($step) {
						case 1 :
							$out [$game->id] ['form'] = $this->getStartButton ( $game->id, 'champform_' . $this->id );
							break;
						case 2 :
							$out [$game->id] ['form'] = $this->getReserveForm ( $game->id, 'champform_' . $this->id );
							break;
						case 3 :
							$values = $form_state ['values'] ['children'] [$game->id] ['form'];
							$form_state ['storage'] [$game->id] ['location'] = $values ['location'];
							$form_state ['storage'] [$game->id] ['date'] = $values ['date'];
							$out [$game->id] ['form'] = $this->getPlayableTimes ( $game->id, 'champform_' . $this->id );
							break;
					}
					break;
				case GameState::$planned :
					
					switch ($step) {
						case 1 :
							$out [$game->id] ['form'] = $this->getPlannedGameForm ( $game );
							break;
						case 2 :
						case 3 :
						case 4 :
							$this->enterResult ( $form_state );
							$out [$game->id] ['form'] = $this->getGameFinished ( $game );
							break;
					}
					
					break;
				case GameState::$finished :
					$out [$game->id] ['form'] = $this->getGameFinished ( $game );
					break;
			}
		}
		
		return $out;
	}

	private function getCreateGameWithLocation($gameId, $formState) {
		$locationId = '';
		$date = '';
		
		// $query = db_select('')
	}

	private function getGameFinished($game) {
		$out = array (
				'result' => array (
						'#prefix' => '<td>',
						'#markup' => $game->getResult () 
				) 
		);
		
		if ($game->shouldShowCancelResultButton ()) {
			$out ['cancelButton'] = array (
					'#type' => 'submit',
					'#value' => 'Resultat loeschen',
					'#name' => $game->id,
					'#suffix' => '</td></tr>' 
			);
		} else {
			$out ['result'] ['#suffix'] = '</td></tr>';
		}
		
		return $out;
	}

	public function getCurrentStateDescription() {
		return "Tabelle";
	}

	private function getPlannedGameForm($game) {
		$homename = $game->getHomeTeam ()->getName ();
		$guestname = $game->getGuestTeam ()->getName ();
		
		return array (
				'#prefix' => '<td>',
				
				'result' => array (
						'#type' => 'select',
						'#options' => array (
								$homename . ' > 4:0 < ' . $guestname,
								$homename . ' > 3:1 < ' . $guestname,
								$homename . ' > 2:2 < ' . $guestname,
								$homename . ' > 1:3 < ' . $guestname,
								$homename . ' > 0:4 < ' . $guestname 
						),
						'#prefix' => '<div class="result">',
						'#suffix' => '</div><br />' 
				),
				'enter' => array (
						'#type' => 'submit',
						'#name' => $game->id,
						'#value' => 'Eintragen',
						'#prefix' => '<div class="resultenter">',
						'#suffix' => '</div>' 
				),
				'cancel' => array (
						'#type' => 'submit',
						'#name' => $game->id,
						'#value' => 'Absagen',
						'#prefix' => '<div class="resultenter">',
						'#suffix' => '</div>' 
				),
				
				'#suffix' => '</td></tr>' 
		);
	}

	public function enterResult($form) {
		$gameId = $form ['triggering_element'] ['#name'];
		$result = $form ['values'] ['children'] [$gameId] ['form'] ['result'];
		
		$game = new Game ( $gameId );
		switch ($result) {
			case 0 :
				$pointsTeamHome = 4;
				$pointsTeamGuest = 0;
				break;
			case 1 :
				$pointsTeamHome = 3;
				$pointsTeamGuest = 1;
				break;
			case 2 :
				$pointsTeamHome = 2;
				$pointsTeamGuest = 2;
				break;
			case 3 :
				$pointsTeamHome = 1;
				$pointsTeamGuest = 3;
				break;
			case 4 :
				$pointsTeamHome = 0;
				$pointsTeamGuest = 4;
				break;
		}
		$game->enterResult ( $pointsTeamHome, $pointsTeamGuest );
	}

	function themeGamesToPlay($variables) {
	}

	private function hasNotApprovedTeams() {
		$query = db_select ( 'fordere_teaminchampionschip', 'tic' );
		$query->addField ( 'tic', 'id' );
		$query->condition ( 'tic.championschipid', $this->id );
		$query->condition ( 'tic.league_approved', '0' );
		$result = $query->execute ()->fetchAll ();
		
		return count ( $result ) != 0;
	}

	public function getMatchAdminForm($form, &$form_state) {
		if (! $this->hasNotApprovedTeams ()) {
			$formout ['createbutton'] = array (
					'#type' => 'submit',
					'#value' => 'Spiele generieren' 
			);
		} else {
			drupal_set_message ( 'Es sind noch Teams vorhanden, welche nicht einer Liga zugeteilt wurden. Liga kann so nicht gestartet werden!', 'error', false );
			$formout ['fail'] = array (
					'#markup' => 'Bitte alle Teams zuweisen!' 
			);
		}
		
		$formout ['championschip'] = array (
				'#type' => 'hidden',
				'#value' => $this->id 
		);
		return $formout;
	}

	public function gameStateAjaxCallback($form, $form_state) {
	}

	public function getGameDescription($game) {
		return $game->getHomeTeam ()->getLeagueApprovedName ();
	}

	public function validateEnterResult($form_state) {
	}

	public function updateRole($roleId, $league) {
		$this->removePlayerFromRole ( $roleId );
		
		$query = db_select ( 'fordere_player', 'p' );
		$query->join ( 'fordere_playerinteam', 'pit', 'pit.playerid = p.id' );
		$query->join ( 'fordere_teaminchampionschip', 'tic', 'tic.teamid = pit.teamid' );
		$query->condition ( 'tic.league_approved', $league, '=' );
		$query->addField ( 'p', 'drupalUserId' );
		$usersToAdd = $query->execute ()->fetchAll ();
		
		$this->addPlayersToRole ( $roleId, $usersToAdd );
	}
}

