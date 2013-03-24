<?php

class Cup extends Championschip {
	
	public function getGameDescription($gameId) {
		return $this->getName ();
	}
	public function getTeamAdminForm($form, &$form_state) {
		$formout = array ();
		$formout ['#theme'] = 'adminteam';
		$formout ['championschip'] = array (
				'#type' => 'hidden', 
				'#value' => $this->id 
		);
		return $formout;
	}
	
	public function GetAdminInstance()
	{
		return new CupAdmin();
	}
	
	public function getFixGameForm($form, $form_state) {
	
	}
	
	public function getCurrentStateDescription() {
		return "Runden";
	}
	
	public function teamAdminAjaxCallback($form, $form_state) {
		// nicht ben�tigt da wir die Teams nicht hier einteilen
	}
	
	public function themeTeamAdmin($vars) {
		$header = array (
				'Nr. ', 
				'Teamname', 
				'Spieler 1', 
				'Spieler 2', 
				'Team-Node-ID' 
		);
		
		$teams = $this->getTeams ();
		$rows = array ();
		$i = 1;
		foreach ( $teams as $team ) {
			$row = array ();
			$row [] = $i;
			$row [] = $team->getName ();
			$row [] = $team->getPlayer1 ()->name;
			$row [] = $team->getPlayer2 ()->name;
			$row [] = $team->id;
			$rows [] = $row;
			$i ++;
		}
		
		$output = theme ( 'table', array (
				'header' => $header, 
				'rows' => $rows 
		) );
		
		return $output;
	}
	
	public function createGames($form, $form_state) {
	
	}
	
	public function getRules() {
	
	}
	
	public function getAdditionalTeamFields($team) {
		return array ();
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
		$form ['player2'] = array (
				'#type' => 'select', 
				'#description' => $this->playerNotAvailable . $this->getOtherPlayerNotAvailableDescription (), 
				'#title' => t ( 'Spieler 2' ), 
				'#options' => $playerList 
		);
		$form ['submit'] = array (
				'#type' => 'submit', 
				'#value' => 'Anmelden' 
		);
		return $form;
	}
	
	public function register($values) {
		$name = $values ['teamname'] ['#value'];
		$locationId = $values ['location'] ['#value'];
		$userId1 = Team::getCurrentUserPlayerId ();
		$userId2 = $values ['player2'] ['#value'];
		
		// Create Team
		$team = new Team ();
		$team->name = $name;
		$team->locationId = $locationId;
		$team->playerId1 = $userId1;
		$team->playerId2 = $userId2;
		$team->save ();
		
		// Add Team to this Championschip
		$this->addTeam ( $team );
		
		return true;
	}
	
	public function getAdditionalRegisterInformation() {
		return array ();
	}
	
	public function validateRegisterForm($values) {
		$name = $values ['teamname'] ['#value'];
		if (! $this->isTeamNameAvailable ( $name )) {
			throw new Exception ( 'Teamname ist bereits vergeben!' );
		}
	
	}
	
	public function getTeamThemeType() {
		return "cup";
	}
	protected function getTeamCssClass($pointsTeam1, $pointsTeam2) {
		
		if ($pointsTeam1 == null || $pointsTeam2 == null) {
			return 'notplayed';
		}
		
		
		if ($pointsTeam1 > $pointsTeam2) {
			return 'teamwin';
		}
		
		return 'teamlose';
	}
	
	public function getOpenGames()
	{
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$result = $query->execute ();
		
		
		$games = array();
		foreach ( $result ['node'] as $gameStd ) {
			$game = new Game ( $gameStd->nid );
			if($game -> state < 3)
			{
				$games  [] = $game;
			}
			
		}
		return $games;
	}
	
	public function getCurrentState() {
		
		drupal_add_library ( 'system', 'ui.tabs' );
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
		$result = $query->execute ();
		
		if ($result) {
			
			$games = array ();
			foreach ( $result ['node'] as $gameStd ) {
				$game = new Game ( $gameStd->nid );
				if ($game->cupround == null) {
					$game->cupround = 1;
				}
				if (! isset ( $games [$game->cupround] )) {
					
					$game->cupround;
					$games [$game->cupround] = array ();
				}
				$games [$game->cupround] [] = $game;
			}
			
			$output = '<div id="roundtabs"><ul>';
			
			foreach ( $games as $round => $gameList ) {
				$output .= '<li><a href="#round-tab-' . $round . '">Runde ' . $round . '</a></li>';
			}
			
			$output .= '</ul>';
			
			foreach ( $games as $round => $gameList ) {
				$output .= '<div id="round-tab-' . $round . '">' . $this->getGamesTable ( $gameList ) . '</div>';
			
			}
			
			$selected = count ( $games ) - 1;
			drupal_add_js ( 'jQuery(document).ready(function(){jQuery("#roundtabs").tabs({ selected: ' . $selected . ' });});', 'inline' );
			
			return $output .= '</ul></div>';
		
		} else {
			return "Keine Spiele im Cup angesagt/gespielt!";
		}
		
		return $output;
	}
	
	protected function getGamesTable($games) {
		$output = '<table class="cuptable"><tr><th class="team1">Team 1</th><th></th><th class="team2">Team2</th></tr>';
		
		foreach ( $games as $game ) {
			
			if ($game->hometeamid == - 1) {
				$output .= '<tr><td  class="team1 ' . $this->getTeamCssClass ( $game->pointsHomeTeam, $game -> pointsGuestTeam ) . '">' . $game->getHomeTeam ()->getName () . '</td>';
			
			} else {
				$output .= '<tr><td  class="team1 ' . $this->getTeamCssClass ( $game->pointsHomeTeam, $game->pointsGuestTeam ) . '">(' . $game->getHomeTeam ()->getLeagueApprovedName () . ') ' . $game->getHomeTeam ()->getName () . '</td>';
			
			}
			
			if ($game->getHomeTeam ()->id == - 1 || $game->getGuestTeam ()->id == - 1) {
				$output .= '<td class="vs">vs.</td>';
			
			} else {
				$output .= '<td class="vs"> <a href="' . url ( 'node/' . $game->id ) . '">vs.</a> </td>';
			
			}
			
			if ($game->guestteamid == - 1) {
				$output .= '<td class="team2 ' . $this->getTeamCssClass ( $game->pointsGuestTeam, $game -> pointsHomeTeam ) . '">' . $game->getGuestTeam ()->getName () . '</td></tr>';
			
			} else {
				
				$output .= '<td class="team2 ' . $this->getTeamCssClass ( $game->pointsGuestTeam, $game->pointsHomeTeam ) . '">' . $game->getGuestTeam ()->getName () . ' (' . $game->getGuestTeam ()->getLeagueApprovedName () . ')</td></tr>';
			}
		}
		
		return $output .= '</table>';
	}
	
	public function getMatchAdminForm($form, &$form_state) {
	}
	
	public function getGamesToPlayForm(&$form_state) {
		$out = array ();
		$games = $this->getGamesToPlay ();
		$i = 0;
		$out ['#prefix'] = '<div id="champform_' . $this->id . '"><table class="dashboardtable">';
		
		if (count ( $games ) > 0) {
			$out ['#prefix'] .= '<tr><th class="hometeamcol">Heimteam</th><th class="guestteamcol">Gastteam</th><th></th></tr>';
		} else {
			$out ['#prefix'] .= '<i>Momentan keine Spiele vorhanden</i>';
		}
		
		$out ['#suffix'] = '</table></div>';
		$out ['#tree'] = TRUE;
		$out ['championschip'] = array (
				'#type' => 'hidden', 
				'#value' => $this->id 
		);
		
		foreach ( $games as $game ) {
			
			$step = empty ( $form_state ['storage'] [$game->id] ['step'] ) ? 1 : $form_state ['storage'] [$game->id] ['step'];
			
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
							$out [$game->id] ['form'] = $this->getPlayableTimesForLocation ( $game->id, 'champform_' . $this->id, $values ['location'], $values ['date'] );
							break;
						default :
							$out [$game->id] ['form'] = array (
									'#prefix' => '<td>', 
									'#suffix' => '</td></tr>', 
									'#markup' => "cr" . $step 
							);
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
	
	protected function getGameFinished($game) {
		//TODO same wie in league
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
	
	protected function getPossiblePointsArray() {
		$array = array ();
		
		for($i = 0; $i < 11; $i ++) {
			$array [] = $i;
		}
		
		return $array;
	}
	
	protected function getPlannedGameForm($game) {
		return array (
				'#prefix' => '<td>' . $game->getInfos (), 
				
				'resulthometeam' => array (
						'#type' => 'select', 
						'#options' => $this->getPossiblePointsArray (), 
						'#prefix' => '<div class="result">Punkte ' . $game->getHomeTeam ()->getName () . ':', 
						'#suffix' => '</div><br />' 
				), 
				'resultguestteam' => array (
						'#type' => 'select', 
						'#options' => $this->getPossiblePointsArray (), 
						'#prefix' => '<br /><div class="result">Punkte ' . $game->getGuestTeam ()->getName () . ':', 
						'#suffix' => '</div><br /><br />' 
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
	
	public function validateEnterResult($form_state) {
		
		$gameId = $form_state ['triggering_element'] ['#name'];
		$pointHome = $form_state ['values'] ['children'] [$gameId] ['form'] ['resulthometeam'];
		$pointGuest = $form_state ['values'] ['children'] [$gameId] ['form'] ['resultguestteam'];
		
		if ($pointHome == $pointGuest) {
			form_set_error ( 'form', 'Unentschieden ist nicht möglich!' );
			return;
		}
		
		if ($pointHome < $this->getMinPointsToWin () && $pointGuest < $this->getMinPointsToWin ()) {
			form_set_error ( 'form', 'Mindestens ein Team muss die minimal nötige Punktzahl erreichen!' );
			return;
		}
	}
	
	function enterResult($form) {
		
		// TODO: Hack 
		if (! isset ( $form ['triggering_element'] ['#name'] )) {
			return;
		}
		
		$gameId = $form ['triggering_element'] ['#name'];
		$pointHome = $form ['values'] ['children'] [$gameId] ['form'] ['resulthometeam'];
		$pointGuest = $form ['values'] ['children'] [$gameId] ['form'] ['resultguestteam'];
		
		$game = new Game ( $gameId );
		$game->enterResult ( $pointHome, $pointGuest );
	}
	
	protected function getMinPointsToWin() {
		//TODO Move to setting
		return 10;
	}
	
	function themeGamesToPlay($variables) {
	
	}
	
	public function gameStateAjaxCallback($form, $form_state) {
	}
	
	public function updateRole($roleId, $round) {
		$this->removePlayerFromRole ( $roleId );
		
		$query = new EntityFieldQuery ();
		$query->entityCondition ( 'entity_type', 'node' );
		$query->propertyCondition ( 'type', 'fordere_game', '=' );
		$query->fieldCondition ( 'game_type', 'value', $this->id, '=' );
// 		$query->fieldCondition ( 'field_game_cup_round', 'value', $round, '=' );
		$result = $query->execute ();
		
		$userIds = array ();
		foreach ( $result ['node'] as $gameStd ) {
			$game = GameFactory::getGame ( $gameStd->nid );
			if ($game->hometeamid != - 1) {
				$userIds [] = $game->getHomeTeam ()->getPlayer1 ()->getUser ()->uid;
				$userIds [] = $game->getHomeTeam ()->getPlayer2 ()->getUser ()->uid;
			}
			
			if ($game->guestteamid != - 1) {
				$userIds [] = $game->getGuestTeam ()->getPlayer1 ()->getUser ()->uid;
				$userIds [] = $game->getGuestTeam ()->getPlayer2 ()->getUser ()->uid;
			}
		}
		
		user_multiple_role_edit ( $userIds, 'add_role', $roleId );
	}

}