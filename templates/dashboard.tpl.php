<?php

function themeLeagueGames($formData) {
	$header = array (
			'Heimteam', 
			'Gastteam', 
			'' 
	);
	
	$rows = array ();
	
	foreach ( element_children ( $formData ['items'] ) as $gameId ) {
		$row = array ();
		$row [] = drupal_render ( $formData ['items'] [$gameId] ['#hometeam'] );
		$row [] = drupal_render ( $formData ['items'] [$gameId] ['#guestteam'] );
		$row [] = drupal_render ( $formData ['items'] [$gameId] ['#form'] );
		$rows [] = $row;
	}
	$output = theme ( 'table', array (
			'header' => $header, 
			'rows' => $rows 
	) );

	$output .= drupal_render_children ( $formData );
	
	return $output;
}

if ($running) {
	foreach ( $form as $championschip ) {
		?><h2><?php echo $championschip ['#title'];?></h2>
		<table>
		<?php
		
		
		print drupal_render ( $championschip );
		print drupal_render_children($championschip);
		?></table><?php 
	}
}