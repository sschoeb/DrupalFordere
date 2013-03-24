<?php

if ($type == 'league') {
	
	// -1 vor
	// 0 gleich
	// 1 nach
	function teamcompare($team1, $team2) {
		
		if ($team1->getLeagueApproved () == $team2->getLeagueApproved ()) {
			
			if ($team1->getLeagueGroup () == $team2->getLeagueGroup ()) {
				$result = strcmp ( $team1->getName (), $team2->getName () );
				if ($result == 0) {
					return 1;
				}
				return $result;
			} elseif ($team1->getLeagueGroup () > $team2->getLeagueGroup ()) {
				// Ligagruppe grösser und Liga gleich
				return 1;
			}
			
			// Ligagruppe kleiner und Liga gleich
			return - 1;
		}
		
		if ($team1->getLeagueApproved () < $team2->getLeagueApproved ()) {
			// Liga kleiner
			return - 1;
		}
		
		// Liga grösser
		return 1;
	}
	
	foreach ( $teams as $team ) {
		$team->getLeagueGroup ();
		$team->getLeagueApproved ();
		$team->getLeagueApprovedName ();
	}
	
	usort ( $teams, 'teamcompare' );
	$currLeague = 0;
	$currLeagueName = '';
	$currGroup = -1;
	$isOpen = false;
	foreach ( $teams as $team ) {
		if ($currLeague != $team->getLeagueApproved ()) {
			if ($isOpen) {
				echo "</table>";
				$isOpen = false;
			}
			$currLeague = $team->getLeagueApproved ();
			$currLeagueName = $team->getLeagueApprovedName ();
			$currGroup = -1;
			?>
<h2><?php echo $currLeagueName; ?></h2>
<?php
		}

		if ($currGroup != $team->getLeagueGroup ()) {
			if ($isOpen) {
				echo "</table>";
				$isOpen = false;
			}
			$currGroup = $team->getLeagueGroup ();
			
			if($currGroup != 0)
			{
				
			?>
<h3><?php echo $currGroup; ?>. Gruppe</h3>

<?php }?>

<table class="leaguetable">
	<tr>
		<th>Teamname</th>
		<th>Heimlokal</th>
		<th class="playercol">Spieler 1</th>
		<th class="playercol">Spieler 2</th>
	</tr>

<?php
			$isOpen = true;
		}
		
		?>

<tr>
		<td><a href="<?php echo url('node/' . $team -> id); ?>"><?php echo $team -> getName();?></a></td>
		<td><?php echo $team -> getLocation() -> name;?></td>
		<td><?php echo $team -> getPlayer1() -> name;?></td>
		<td><?php echo $team -> getPlayer2() -> name;?></td>
	</tr>
<?php
	
	}
	
	?></table><?php 
	
} elseif ($type == 'cup') {
	
	?>
	<table class="leaguetable">
		<tr>
			<th>Teamname</th>
			<th>Heimlokal</th>
			<th class="playercol">Spieler 1</th>
			<th class="playercol">Spieler 2</th>
		</tr>
	<?php
	foreach ( $teams as $team ) {
		?>
		<tr>
			<td><a href="<?php echo url('node/' . $team -> id); ?>"><?php echo $team -> getName();?></a></td>
			<td><?php echo $team -> getLocation() -> name;?></td>
			<td><?php echo $team -> getPlayer1() -> name;?></td>
			<td><?php echo $team -> getPlayer2() -> name;?></td>
		</tr>
		<?php
	}
	?>
	</table>
	<?php
} else {
	echo $type;
}
