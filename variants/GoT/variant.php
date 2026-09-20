<?php
/*
	Copyright (C) 2015 Oliver Auth
	This file is part of the Ancient Mediterranean variant for webDiplomacy
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
	---
	Rules for the the Ancient Mediterranean Variant by Don Hessong:
	http://www.variantbank.org/results/rules/a/ancient_med.htm
	This is Version: 1.7
	Changelog:
	1.0: initial version
	1.1: fixed some graphic issues on the maps
	1.5: new webdip0.97 variant code
	1.6: minor fixes
	1.6.1: Added color-function to avoid black flags and stars
	1.6.2: some adjustments to the new variant.php funtionality
	1.6.3: borderfix
	1.6.4: smallmap color-fix
	1.7:   new: rules.html added to explain the rule-change for the Baleares.
*/

defined('IN_CODE') or die('This script can not be run by itself.');

class GoTVariant extends WDVariant {
	public $id=45;
	public $mapID=45;
	public $name='GoT';
	public $fullName='Game of Thrones';
	public $description='A Game of Thrones inspired variant.';
	public $author='Dario Mitchell';
	public $adapter='same';
	public $version='0.9';
	public $homepage='http://www.github.com/mcoirad';

	public $countries=array('House-Stark', 'House-Arryn', 'House-Greyjoy', 'House-Tully', 'House-Lannister', 'House-Baratheon', 'House-Tyrell', 'House-Martell');
	// WDVariant::initialize() recomputes both of these from wD_Territories (51 supply
	// centres on this map) and sets the target to round(18/34 * count) = 27, so the
	// variant's own solo target is restored in initialize() below, as ColdWar does.
	public $supplyCenterCount = 51;
	public $supplyCenterTarget = 35;

	public function initialize() {
		parent::initialize();
		$this->supplyCenterTarget = 35;
	}

	public function __construct() {
		parent::__construct();

		$this->variantClasses['drawMap'] = 'GoT';
		$this->variantClasses['adjudicatorPreGame'] = 'GoT';

	}

	public function turnAsDate($turn) {
		if ( $turn==-1 ) return l_t("Pre-game");
		else return ( $turn % 2 ? l_t("Autumn").", " : l_t("Spring").", " ).(floor($turn/2) + 1);
	}

	public function turnAsDateJS() {
		return 'function(turn) {
			if( turn==-1 ) return l_t("Pre-game");
			else return ( turn%2 ? l_t("Autumn")+", " : l_t("Spring")+", " )+(Math.floor(turn/2) + 1);
		};';
	}
}

?>
