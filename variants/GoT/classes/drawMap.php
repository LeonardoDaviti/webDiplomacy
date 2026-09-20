<?php
/*
	Copyright (C) 2015 Oliver Auth
	This file is part of the the Ancient Mediterranean variant for webDiplomacy
	The Ancient Mediterranean variant for webDiplomacy is free software: 
	you can redistribute it and/or modify it under the terms of the GNU Affero
	General Public License as published by the Free Software Foundation, either 
	version 3 of the License, or (at your option) any later version.
    The Ancient Mediterranean variant for webDiplomacy is distributed in the hope
	that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
	warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the GNU General Public License for more details.
	You should have received a copy of the GNU Affero General Public License
	along with webDiplomacy. If not, see <http://www.gnu.org/licenses/>.
*/

defined('IN_CODE') or die('This script can not be run by itself.');

class GoTVariant_drawMap extends drawMap {

	protected $countryColors = array(
		0 =>  array(226, 198, 158), // Neutral
		1 =>  array(235, 235, 235), // Stark
		2 =>  array(0, 123, 165), // Arryn
		3 =>  array(135, 135, 135), // Greyjoy
		4 =>  array(50, 58, 165), // Tully
		5 =>  array(165, 26, 26),  // Lannister
		6 =>  array(162, 165, 0),  // Baratheon
		7 =>  array(21, 165, 23),  // Tyrell
		8 =>  array(166, 132, 30)  // Martell

	);

	protected function resources() {
		if( $this->smallmap )
		{
			return array(
				'map'     =>l_s('variants/GoT/resources/smallmap.png'),
				'army'    =>l_s('variants/GoT/resources/smallarmy.png'),
				'fleet'   =>l_s('variants/GoT/resources/smallfleet.png'),
				'names'   =>l_s('variants/GoT/resources/smallmapNames.png'),
				'standoff'=>l_s('images/icons/cross.png')
			);
		}
		else
		{
			return array(
				'map'     =>l_s('variants/GoT/resources/map.png'),
				'army'    =>l_s('variants/GoT/resources/army.png'),
				'fleet'   =>l_s('variants/GoT/resources/fleet.png'),
				'names'   =>l_s('variants/GoT/resources/mapNames.png'),
				'standoff'=>l_s('images/icons/cross.png')
			);
		}
	}

}

?>
