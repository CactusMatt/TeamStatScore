
Plugin Name: TablePress Extension: genTeamStats
Plugin URI: http://the-fam.us/MatthewBlog/extensions/genTeamStats
Description: Custom Extension for TablePress to look at a given table as a SCHEDULE, then extract dates, runs, and baseball-relate stats; and keep cumulative values
Version: 1.0
Author: Matthew George
Author URI: http://the-fam.us/MatthewBlog

  Copyright 2014 Matthew D. George  (email : matthewdgeorge@the-fam.us)

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

    This extension reads an ordinary TABLEPRESS table, hereafter referred to simply as a SCHEDULE table. TablePress was developed by Tobias Bathge, and Tobias was kind enough to 
    collaborate with me on some of the mechanics for access and display used in this plugin. TablePress is a GREAT addition to ANY WordPress web-site, and is absolutely
    recommended! You can read more about this really nice plugin here: http://tablepress.org/.


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

 To install this plugin:

 Simply copy the teamstatscore.php file into your WordPress site by one of two methods:
 1.
  a. Make sure you have TablePress installed (see the link above for installing TablePress).
  b. In your wp-content/plugins folder, create a folder called tablepress-teamstats.
  c. Download and copy teamstatscore.php into the tablepress-teamstats directory.
 2. 
 a. download a copy of teamstatscore.zip to your local hard-drive.
 b. Install the plugin by 'Upload a copy of teamtstatscore.zip' using the WordPress plugin installer. When prompted for the