<?php
/*
Plugin Name: TablePress Extension: genTeamStats
Plugin URI: http://the-fam.us/MatthewBlog/extensions/genTeamStats
Description: Custom Extension for TablePress to look at a given table as a SCHEDULE, then extract dates, runs, and baseball-relate stats; and keep cumulative values
Version: 1.0
Author: Matthew George
Author URI: http://the-fam.us/MatthewBlog
*/
/*  Copyright 2014 Matthew D. George  (email : matthewdgeorge@the-fam.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * The format in a SCHEDULE table is very specific, but it is still just an ordinary TablePress table:
 * There can be anything at all in a SCHEDULE table, but this plugin will look in each row for some specific information:
 *    Day-of-week, date, opponent, location, time, runs-for, runs-against
 *  Anything else is simply ignored. Therefore, by definition the SCHEDULE table must have at least 2 rows (a header row and one or more data rows);
  * and dats-rows must have at least 7 columns (corresponding to the column-headers described above). It is not uncommonn (especially at the beginning
  * of a season) to have blank data in the 'runs-for' and 'runs-against' columns. Without valid data in the date and time columns however, the logic contained 
  * in this extension is largely useless.
 *  The 'date' & 'time' are converted into a UNIX-time format (call it 'date-time'), and if they are malformed; then the entire row is ignored.
 *
 *  date-time is compared against the present time to determine if the game occurred in the past or is 'next up'. Statistics on wins, losses, runs, etc.
 *   are only gathered on past games -- and the extension returns as soon as the next future-game is determined.
 *  One of the strange by-products of the original SCHEDULE table 'date' field is that it does NOT contain a year-value. 2014 is assumed by default; but this can be specified with the
 *  'gtsyear' input shortcode option.
 *
 * Depending on what output options are selected via the 'gtsgamestats' shortcode -- either 'nextGame', 'lastGame', or 'teamStats', this extension TRANSFORMS (mostly through deletion)
 * the SCHEDULE table into different output formats.
 *  All of these formats, are however, just ordinary TablePress tables, and the extension uses the TablePress 'render hooks' to display them. The original input TablePress table is not
 *   modified at all, and can be used at any time on your site with standard (or extended) Tablepress options.
 *
 *  For all intents and purposes, this plugin extension uses the core Tablepress plugin as a) a datastore, and then b) as a polished display engine. While I take sole responsibility for the infalibility
 *  of this approach, and any resultant shortcomings or errors; I would VERY MUCH like to acknowledge the enthusiastic, knowledgeable, and welcome support of the Tablepress author
 *  Tobias Bathge. Without this support, I could never have attempted this project. Tobias responded to my every request (both public and private) with unfailing courtesy, and a helpful
 * 'can do' attitide that was very much appreciated.
 */

/**
 * Register necessary Plugin Filters
 */
add_filter( 'tablepress_shortcode_table_default_shortcode_atts', 'tablepress_add_shortcode_parameters_teamStats' );
add_filter( 'tablepress_table_render_data', 'genTeamStats_function', 10, 3 );

/**
 * Add "genTeamStats" as a valid parameter to the [table /] Shortcode
 */
function tablepress_add_shortcode_parameters_teamStats( $default_atts ) {
	$default_atts['genteamstats'] = '';
	$default_atts['gtsteamname']='Home Team';
	$default_atts['gtsyear']='2014';  // default year
	return $default_atts;
}

function isEventLine( $line, $year ) {
  /*
   * This functiion basically validates formats -- it does not use them
   * if all validation tests are good (is a a REAL game event), then return event-time in date-time format else return FALSE
   *
   * if a) $line is an array of size 11, and
   * $line[1] .= "2014" (test for date) -- possibhle combine with Time $line[4]
   */
   if (count( $line ) >= 5) {
      $myDate = $line[1] . "-". $year. " " . $line[4]; // this should concatenate a year, and then a time; resulting in a full date-time
      if ($myDateTime = strtotime($myDate)) {
         // extraact and examine hour of day
         $hour = date("h", $myDateTime);

         // if the hour is > 7 AND the hour is NOT 12, then it's an AM hour, otherwise it's PM
         $myDate .= (($hour > 7) && ($hour != 12) ) ? 'am' : 'pm';

         return strtotime( $myDate );
      } // endif strtotime
   } // endif there are enough elements in our line

   return false;  // default case is NOT an event
} // end isDateLine

function hideTableRows( $table, $startRow, $numRows ) {
  /*
   * Hide 'hiddenRows' from the table, return (curtailed) table
   *
   */
  // Remove the rows that shall be hidden from table data and table visibility
  for ($i=$startRow; $i < ($startRow + $numRows); $i++) {
      unset( $table['data'][$i] );
      unset( $table['visibility']['rows'][$i] );
  } // end for each row to be hiddeb
  // Reset array keys
  $table['data'] = array_merge( $table['data'] );
  $table['visibility']['rows'] = array_merge( $table['visibility']['rows'] );

  return $table;
} // end hideTableRows

function isHomeGame( $eventLine ) {
  /*
   * $event line contains an array of strings, representing a line from the SCHEDULE table, in the form:
   *
   * Day-of-week, date, opponent-name, location, time
   *
   * if location string contains a "*", then it is a home-game
   */
   if ( strpos( $eventLine[3], "*" ) )
      return true;
   else
       return false;
} // end isHomeGame

function resizeTable( $table, $nrows, $ncols ) {
  /*
   * Resize $table to $nrows x $ncols, return resultant table
   *
   * if the table is not big enough in either dimension, return false
  */
   $totalLines = count( $table['data'] );
   $numHidden = $totalLines - $nrows; // this number may change dependin on how many we want to display

   // the table maust be at least this long, otherwise we cannot 'transform' it to something smaller
   if ($numHidden < 0)
      return false;

      // and tabke must be at least 2 columns
   $totalCols = count( $table['data'][0] );
   if ($totalCols < $ncols)
      return false;

   $colList = "0"; // it makes no sense that we should EVER have less than one column
   for ($i=1; $i<$ncols; $i++)
     $colList .= ", $i";
   $table['data'] = remove_not_filtered_columns( $table['data'], $colList ); // remove all but the first $ncols columns
   $table = hideTableRows( $table, $nrows, $numHidden ); // hide all but the rows we want to display

   return $table;
} // end resizeTable

function displayNextGame( $table, $event, $eventTime, $teamName ) {
  /*
   * returns a table containing two lines:
   *   the 'next game played' in the following format:
   *  line 1:
   *    "TeamName vs. Oppenent" or "Opponent vs TeamName" depending on Home-team
   *  line 2:
   *   day-of-week, date, time, "at" location
   */
   // first let's re-size our output table:
   if (!($resizedTable = resizeTable( $table, 2, 4 )) )
      return $table;
   else
       $table = $resizedTable;

   if (!$event)
      {
      // put a new heading on our table
      $table['data'][0][0] = "<center><strong>No Game Scheduled</strong>";
      for ($i=1; $i<5; $i++)
        $table['data'][0][$i] = "#colspan#";
    
      // then put the rest of the event-info in the next line
      $table['data'][1][0] = " ";
      $table['data'][1][1] = " ";
      $table['data'][1][2] = " ";
      $table['data'][1][3] = " ";
      } // end if there is NO 'next' event
   else
      {
      // put a new heading on our table
      $heading = ( isHomeGame( $event ) ) ? "<center><strong>". $teamName. " vs. ". $event[2]. "</strong>" : "<center><strong>". $event[2]. " vs. ". $teamName. "</strong>";
      $table['data'][0][0] = $heading;
      for ($i=1; $i<5; $i++)
        $table['data'][0][$i] = "#colspan#";
    
      // then put the rest of the event-info in the next line
      $table['data'][1][0] = $event[0]; // day of week
      $table['data'][1][1] = date( "m-d-Y", $eventTime); // date
      $table['data'][1][2] = date( "h:ia", $eventTime ); // time
      $table['data'][1][3] = "at ". $event[3]; // location
      } // otherwise, display the last event

  return $table;
} // end displayNextGame

function displayLastGame( $table, $event, $eventTime, $teamName ) {
  /*
   * returns a table containing two lines:
   *   line 1:
   *      "Win" or "Loss", or "Tie"; with score in X-X formae depending upon score of last-game played
   *  line 2:
   *   the 'last game played' in the following format:
   *   Opponent, date, time, "at " location
   *
   * if the original table is not big enough to hold our transformed table, return the original table
   */
   $thisGame = array();
   $thisGame['winloss'] = '';
   $thisGame['rf'] = '';
   $thisGame['ra'] = '';

   // first let's re-size our output table:
   if (!($resizedTable = resizeTable( $table, 3, 4 )) )
      return $table;
   else
       $table = $resizedTable;

   if (!$event )
        {
        $table['data'][0][0] = "No Games Played Yet";
        for ($i=1; $i<4; $i++)
            $table['data'][0][$i] = "#colspan#";
        $table['data'][1][0] = " ";
        for ($i=1; $i<4; $i++)
            $table['data'][1][$i] = "#colspan#";

        for ($i=0; $i<4; $i++)
            $table['data'][2][$i] = " ";

        return $table;
        }

   // now set the Win/Loss/Tie line
   if ($thisGame = gameResult( $thisGame, $event ))
        $result = "<center><strong>". $thisGame['winloss']. "  --  ". $thisGame['rf']. "-". $thisGame['ra']."</strong>";
   else
       $result = "<center>No Result Posted";

   $table['data'][0][0] = $result;
   for ($i=1; $i<4; $i++)
     $table['data'][0][$i] = "#colspan#";

   // now set the opponents line
   $heading = ( isHomeGame( $event ) ) ? "<center>". $teamName. " vs. ". $event[2] : "<center>". $event[2]. " vs. ". $teamName;
   $table['data'][1][0] = $heading;
   for ($i=1; $i<4; $i++)
     $table['data'][1][$i] = "#colspan#";



   // now set the time, date and location line
   $table['data'][2][0] = $event[0]; // day of week
   $table['data'][2][1] = date( "m-d-Y", $eventTime); // date
   $table['data'][2][2] = date( "h:ia", $eventTime ); // time
   $table['data'][2][3] = "at ". $event[3]; // location

   return $table;
} // end displayLastGame

function displayWinLossStats( $table, $stats, $teamName ) {
  /*
   * This function returns a table containing several lines:
   *  this is a 2-row x 7 line table with the following format"
   *  Record W-L
   *  Win % .xxx
   *  Home W-L
   *  Away W-L
   *  Runs For XX
   *  Runs Against XX
   *  Last 10   W-L-T
   *  Streak (W/L) XX
   */
   // first let's re-size our output table:
   if (!($resizedTable = resizeTable( $table, 9, 2 )) )
      return $table;
   else
       $table = $resizedTable;

   // line 1 is just 'team name' Standings
   $table['data'][0][0] = "<center><strong>". $teamName. " Standings</strong>";
   $table['data'][0][1] = "#colspan#";

   // line 2 is Record W-L-T
   $table['data'][1][0] = "Record";
   $table['data'][1][1] = $stats['totalWins']."-".$stats['totalLosses']."-".$stats['totalTies'];

   // line 3 is  Win %
   $table['data'][2][0] = "Win %";
   $record = (($stats['totalWins']+$stats['totalLosses']) == 0) ? 0: $stats['totalWins']/($stats['totalWins']+$stats['totalLosses']);
   $format = "%03f";
   $table['data'][2][1] = number_format( $record, 3 );
//   $table['data'][2][1] = sprintf( "%.3f", $record );

   // line 4 is  Home W-L-T
   $table['data'][3][0] = "Home";
   $table['data'][3][1] = $stats['homeWins']."-".$stats['homeLosses']."-".$stats['homeTies'];

   // line 5 is  Away W-L-T
   $table['data'][4][0] = "Away";
   $table['data'][4][1] = $stats['awayWins']."-".$stats['awayLosses']."-".$stats['awayTies'];

   // line 6 ia RF XXX
   $table['data'][5][0] = "RF";
   $table['data'][5][1] = $stats['totalRunsFor'];

   // line 7 is RA XXX
   $table['data'][6][0] = "RA";
   $table['data'][6][1] = $stats['totalRunsAgainst'];

   // line 8 is 'last 10  W-L"
   $table['data'][7][0] = "Last 10";
   $last10Wins = 0;
   $last10Losses = 0;
   $last10Ties = 0;

   $gs = $stats['last10'];
   $games = $gs->count();
   if ($games > 10)
      $games = 10;

   for ($i=1; $i<=$games; $i++) {
     if ( $gs->isEmpty() )
        break;

     $thisGame = $gs->pop();
     switch ( $thisGame ) {
       case 'Win':
         $last10Wins++;
         break;
       case 'Loss':
         $last10Losses++;
         break;
       case 'Tie':
         $last10Ties++;
       default:
         $break; // this should never happen -- but do nothing if it does!
     } // end switch on $thisGame
   } // end for each enqueued game
   $table['data'][7][1] = $last10Wins."-".$last10Losses."-".$last10Ties;

   // line 9 is Streak Won X (or Lost X) (or Tied X)
   $table['data'][8][0] = "Streak";
   switch ( $stats['streakResult'] ) {
     case 'Win' :
       $result = "Won ".$stats['streakValue'];
       break;
     case 'Loss' :
       $result = "Lost ".$stats['streakValue'];
       break;
     case 'Tie' :
       $result = "Tied ".$stats['streakValue'];
       break;
     default :
       $result = "No games played";
       break;
   } // end switch on streakResult

   $table['data'][8][1] = $result;


   return $table;
} // end displayWinLossStats

function isDivGame( $eventLine ) {
  return (strpos($eventLine[2], '*'));
} // end isDivGame

function gameResult( $game, $eventLine ) {
  /*
   *
   * returns an associate array containing three elements:
   * ['winloss'] (Win, Loss, or Tie -- but blank if scores are malformed
   * [rf] Runs For
   * [ra] Runs Against
   */

   // first -- test to see if our line contains a valid score
   // Runs for is line column 6 ($line[5]) and Runs Against in column 7 ($line[6])
   $thisGameRunsFor = $eventLine[5];
   $thisGameRunsAgainst = $eventLine[6];

   if (is_numeric( $thisGameRunsFor ) && is_numeric( $thisGameRunsAgainst ) ) {
     if ($thisGameRunsFor > $thisGameRunsAgainst)
        $game['winloss'] = "Win";
     elseif ($thisGameRunsFor < $thisGameRunsAgainst)
            $game['winloss'] = "Loss";
     else
         $game['winloss'] = "Tie";

     $game['rf'] = $thisGameRunsFor;
     $game['ra'] = $thisGameRunsAgainst;

     return $game;
   } // endif we have numbers in the RunsFor and RunsAgainst columns

   return false; // if non-numberic values in runs-columns
} // end gameResult

function collateStats ($gameStats, $line ) {
  /*
   * modify game-stats  structure, then return new stats based upon values in $line (as game line)
   */

   // if this is a scrimmage game, then do not aggregate results
   if( strstr( $line[2], "Scrimmage" ) )
       return $gameStats;

   $thisGame = array();
   $thisGame['winloss'] = '';
   $thisGame['rf'] = 0;
   $thisGame['ra'] = 0;

   if ($thisGameResult = gameResult( $thisGame, $line ) ) {
     // check for divsional game
     $divGame = isDivGame( $line );

     // check for home games
     $homeGame = isHomeGame( $line );

     // test for W/L/T
     // change to case
     switch ($thisGameResult['winloss']) {
       case 'Win':
         $gameStats['totalWins']++;
         $gameStats['divWins'] += ($divGame ) ?  1:0;
         $gameStats['homeWins'] += ($homeGame ) ?  1:0;
         $gameStats['awayWins'] += ($homeGame ) ?  0:1;
         break;
       case 'Loss':
         $gameStats['totalLosses']++;
         $gameStats['divLosses'] += ($divGame) ? 1:0;
         $gameStats['homeLosses'] += ($homeGame ) ?  1:0;
         $gameStats['awayLosses'] += ($homeGame ) ?  0:1;
         break;
       case 'Tie':
         $gameStats['totalTies']++;
         $gameStats['divTies'] += ($divGame ) ?  1:0;
         $gameStats['homeTies'] += ($homeGame ) ?  1:0;
         $gameStats['awayTies'] += ($homeGame ) ?  0:1;
         break;
       default:
         return $gameStats; // no modification -- this is not a validly scored game
         break;
     } // end switch on gameResult

     // check streak
     if($thisGameResult['winloss'] == $gameStats[ 'streakResult' ])
        $gameStats['streakValue']++; // the streak continues!
     else {
       $gameStats['streakValue'] = 1;
       $gameStats['streakResult'] = $thisGameResult['winloss'];
     } // end alse starting a new streak

     $gameStats['totalRunsFor'] += $thisGameResult['rf'];
     $gameStats['totalRunsAgainst'] += $thisGameResult['ra'];
     
     // queue up the result for later display
     $gameStats['last10']->push( $thisGameResult['winloss'] );
   } // end if we have valid scores

   return $gameStats;
} // end collateStats;

/**
 * Remove columns that shall not be filtered from the dataset
 *
 * @since 1.0
 *
 * @param array $table_data Full table data for the table to be filtered
 * @param string $filter_columns List of columns that shall be searched by the filter
 * @return array Reduced table data, that only contains the columns that shall be searched
 *
 * This code was 'borrowed' verbatim from Tobias's 'row filter' plugin, where it was a protected static function in a class
 */
function remove_not_filtered_columns( $table_data, $filter_columns ) {
	// add all columns to array if "all" value set for the filter_columns parameter
	if ( 'all' == $filter_columns )
		return $table_data;

	// we have a list of columns (possibly with ranges in it)
	$filter_columns = explode( ',', $filter_columns );
	// support for ranges like 3-6 or A-BA
	$range_cells = array();
	foreach ( $filter_columns as $key => $value ) {
		$range_dash = strpos( $value, '-' );
		if ( false !== $range_dash ) {
			unset( $filter_columns[ $key ] );
			$start = substr( $value, 0, $range_dash );
			if ( ! is_numeric( $start ) )
				$start = TablePress::letter_to_number( $start );
			$end = substr( $value, $range_dash + 1 );
			if ( ! is_numeric( $end ) )
				$end = TablePress::letter_to_number( $end );
			$current_range = range( $start, $end );
			$range_cells = array_merge( $range_cells, $current_range );
		}
	}
	$filter_columns = array_merge( $filter_columns, $range_cells );
	// parse single letters
	foreach ( $filter_columns as $key => $value ) {
		if ( ! is_numeric( $value ) )
			$filter_columns[ $key ] = TablePress::letter_to_number( $value );
		$filter_columns[ $key ] = (int)$filter_columns[ $key ];
	}
	// remove duplicate entries and sort the array
	$filter_columns = array_unique( $filter_columns );
	sort( $filter_columns, SORT_NUMERIC );
	// remove columns that shall not be filtered from the data
	$dont_filter_columns = array_diff( range( 1, count( $table_data[0] ) ), $filter_columns );
	foreach ( $table_data as $row_idx => $row ) {
		foreach ( $dont_filter_columns as $col_idx ) {
			unset( $row[ $col_idx - 1 ] ); // -1 due to zero based indexing
		}
		$table_data[$row_idx] = array_merge( $row );
	}

	return $table_data;
}  // end remove_not_filtered_columns

function genTeamStats_function( $table, $orig_table, $render_options ) {
  $stats = array();
  $stats['totalRunsFor'] = 0;
  $stats['totalRunsAgainst'] = 0;
  $stats['totalWins'] = 0;
  $stats['totalLosses'] = 0;
  $stats['totalTies'] = 0;
  $stats['streakResult'] = 'no result';
  $stats['streakValue'] = 0;
  $stats['divWins'] = 0;
  $stats['divLosses'] = 0;
  $stats['divTies'] = 0;
  $stats['homeWins'] = 0;
  $stats['homeLosses'] = 0;
  $stats['homeTies'] = 0;
  $stats['awayWins'] = 0;
  $stats['awayLosses'] = 0;
  $stats['awayTies'] = 0;
  $stats['last10'] = new SplStack();

  $statOptions = $render_options['genteamstats'];
  $teamName = $render_options['gtsteamname'];
  $year = $render_options['gtsyear'];

  if ( empty($statOptions) ) {
              // if the genTeamStats option not selected, then just leave
	return $table;
    } // endif genteamstats not selected as option

     // do some basic 'table size' checking to make sure we can output the required number of lines
    $linesInTable = count( $table['data'] );
    switch($statOptions)
    {
     case 'nextGame':
         if ($linesInTable < 3)
             return $table;
         break;
     case 'lastGame':
         if ($linesInTable < 3)
             return $table;
         break;
     case 'winlossStats':
         if ($linesInTable < 11)
             return $table;
        break;
     default:
          return $table;
        break; // if the option is not recognized, just leave with un-modified table
    break;
    }   // end case on statOptions




// do something with $table['data'] here  -- genTeamStats functionality, (Tobias') example:
//  echo $table['id'];
//  var_dump( $table['data'] );

  // put REAL code here
  $tableData = $table['data'];
  $nextEvent = false;    // nextEvent happens only once
  $lastEvent = false; // lastEvent has not happened yet

  // we should NOT have to set the time-zone, BUT...
  // $tz = get_option('timezone_string');
  // date_default_timezone_set( $tz );

  foreach ($tableData as $line) {

    // check for a valid event format in each line
    if ($event = isEventLine($line, $year) ) {

       // check for event past or future (from current WordPress time)
       if ($event > current_time( "timestamp" )) {
         if (!$nextEvent) {
            $nextEvent = $line;  // set nextEvent only if there has not already been one
            $nextEventTime = $event;
            break; // we don't need to continue looking for games past this
         } // endif nextEvent did not happen yet
       } // endif event in the future
       else  {
         $lastEvent = $line;
         $lastEventTime = $event;

         // past events should have W/L stats, so collate them into totals
         $stats = collateStats( $stats, $line );
       } // endelse event is in the past
    }  // endif we have a valid event
  } // end for each line in tableData

 /*
  * What this plugin extension truly does is TRANSFORM the original table into one of two types of table:
  *      a) table type one is a single-row table containing information representing either the 'most recent',
  *      or 'next played' game  -- genTeamStats = "lastGame", or genTeamStats = "nextGame" respectively.
  *
  *      b)table type two is a two-column x 8-row table containing win-loss team statistics
  */

  // clear table title and description for all genteamstats functions
  $table['name'] = '';
  $table['description'] = '';
   switch($statOptions)
    {
     case 'nextGame':
         $table = displayNextGame( $table, $nextEvent, $nextEventTime, $teamName );
         break;
     case 'lastGame':
         $table = displayLastGame( $table, $lastEvent, $lastEventTime, $teamName );
         break;
     case 'winlossStats':
        $table = displayWinLossStats( $table, $stats, $teamName );
        break;
     default:
        break; // if the option is not recognized, just leave with un-modified table
    break;
    }   // end case on statOtions
  return $table;
} // end genTeamStats_function