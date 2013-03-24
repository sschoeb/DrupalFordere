<?php

class DoppelKoCup extends Cup {
	
	public function getGamesTable($games) {
		$output = '';
		$wbcount = 0;
		foreach ( $games as $game ) {
			if ($game->bracket == Game::LooserBracket) {
				continue;
			}
			
			if ($output == '') {
				$output .= '<h2>Winner Bracket</h2><table class="cuptable"><tr><th class="team1">Team 1</th><th></th><th class="team2">Team2</th></tr>';
			
			}
			
			$wbcount ++;
			
			$output .= '<tr><td  class="team1 ' . $this->getTeamCssClass ( $game->pointsHomeTeam, $game->pointsGuestTeam ) . '">' . $game->getHomeTeam ()->getName () . '</td>';
			if ($game->getHomeTeam ()->id == - 1 || $game->getGuestTeam ()->id == - 1) {
				$output .= '<td class="vs">vs.</td>';
			} else {
				$output .= '<td class="vs"> <a href="' . url ( 'node/' . $game->id ) . '">vs.</a> </td>';
			}
			$output .= '<td class="team2 ' . $this->getTeamCssClass ( $game->pointsGuestTeam, $game->pointsHomeTeam ) . '">' . $game->getGuestTeam ()->getName () . '</td></tr>';
		}
		if ($output != '') {
			$output .= '</table>';
		}
		if ($wbcount == count ( $games )) {
			return $output;
		}
		
		$output .= '<h2>Looser Bracket</h2><table class="cuptable"><tr><th class="team1">Team 1</th><th></th><th class="team2">Team2</th></tr>';
		
		//TODO Copy Paste FTW
		foreach ( $games as $game ) {
			
			if ($game->bracket == Game::WinnerBracket) {
				continue;
			}
			
			$output .= '<tr><td  class="team1 ' . $this->getTeamCssClass ( $game->pointsHomeTeam, $game->pointsGuestTeam ) . '">' . $game->getHomeTeam ()->getName () . '</td>';
			$output .= '<td class="vs"> <a href="' . url ( 'node/' . $game->id ) . '">vs.</a> </td>';
			$output .= '<td class="team2 ' . $this->getTeamCssClass ( $game->pointsGuestTeam, $game->pointsHomeTeam ) . '">' . $game->getGuestTeam ()->getName () . '</td></tr>';
		}
		
		return $output .= '</table>';
	}
	
	protected function getPossiblePointsArray() {
		$array = array ();
		
		for($i = 0; $i < 6; $i ++) {
			$array [] = $i;
		}
		
		return $array;
	}
	
	protected function getMinPointsToWin() {
		return 3;
	}
}

