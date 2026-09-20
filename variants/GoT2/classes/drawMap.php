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

class GoT2Variant_drawMap extends drawMap {

	protected $countryColors = array(
		0 =>  array(226, 198, 158), // Neutral
		1 =>  array(50, 58, 165), // Tully
		2 =>  array(165, 26, 26),  // Lannister
	);

	protected function resources() {
		if( $this->smallmap )
		{
			return array(
				'map'     =>l_s('variants/GoT2/resources/smallmap.png'),
				'army'    =>l_s('variants/GoT2/resources/smallarmy.png'),
				'fleet'   =>l_s('variants/GoT2/resources/smallfleet.png'),
				'names'   =>l_s('variants/GoT2/resources/smallmapNames.png'),
				'standoff'=>l_s('images/icons/cross.png')
			);
		}
		else
		{
			return array(
				'map'     =>l_s('variants/GoT2/resources/map.png'),
				'army'    =>l_s('variants/GoT2/resources/army.png'),
				'fleet'   =>l_s('variants/GoT2/resources/fleet.png'),
				'names'   =>l_s('variants/GoT2/resources/mapNames.png'),
				'standoff'=>l_s('images/icons/cross.png')
			);
		}
	}

}

?>
