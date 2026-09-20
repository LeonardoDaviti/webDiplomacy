<?php
/*
    Copyright (C) 2004-2010 Kestas J. Kuliukas

	This file is part of webDiplomacy.

    webDiplomacy is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    webDiplomacy is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with webDiplomacy.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('IN_CODE') or die('This script can not be run by itself.');

require_once(l_r('variants/variant.php'));

/**
 * This class performs variant related functions, most importantly loading the right Variant class
 * given a variety of inputs. Since these Variant classes are required to load anything game related
 * most game related code begins via a request to libVariant here.
 *
 * Also will serialize/cache newly loaded variants and load ones available via cache, preventing
 * unneeded database lookups.
 */
class libVariant {

	/**
	 * When a change in behavior is made to the variants system this is incremented to allow
	 * variants to react to changes in the variant system.
	 *
	 * 1: $WDVariant->codeVersion and ->cacheVersion added, allowing variant versioning and cache wipes.
	 *
	 * @var int
	 */
	public static $Version=1;

	public static $Variant;

	/**
	 * The variant IDs bots are able to play, from Config::$botVariantIDs.
	 *
	 * Bots are kept out of every other variant: their code only knows these maps, so a bot in e.g. a
	 * Modern game would sit in civil disorder until a moderator removed it. This was
	 * Config::$apiConfig['variantIDs'] until the API stopped being a bots-only thing, and a config.php
	 * still using that name is read so an install doesn't break on the deploy.
	 *
	 * @return array Variant IDs
	 */
	public static function botVariantIDs() {
		if( property_exists('Config', 'botVariantIDs') )
			return Config::$botVariantIDs;

		if( property_exists('Config', 'apiConfig') && isset(Config::$apiConfig['variantIDs']) )
			return Config::$apiConfig['variantIDs'];

		return array(1, 15, 23);
	}

	/**
	 * For everything in board/* (used by ajax.php and board.php) it can be assumed that only one variant
	 * will be loaded, so here that variant is defined where it will be globally accessible.
	 *
	 * @param Variant $Variant
	 * @return unknown_type
	 */
	public static function setGlobals(WDVariant $Variant) {
		if( isset(libVariant::$Variant) )
		{
			// In DATC tests this might get called twice
			if( libVariant::$Variant->id == $Variant->id )
				return;
			else
				trigger_error(l_t("Alternate variant being set as global"));
		}
		else
		{
			libVariant::$Variant=$Variant;
			define('VARIANTID',$Variant->id);
			define('MAPID',$Variant->mapID);
		}
	}

	/*
	 * LOCAL DEVIATION (sandbox): variant class files are only ever meant to be loaded one variant
	 * per request, and dozens of variants ship copies of the same helper classes under the same
	 * name (MoveFlags_drawMap, CustomIcons_drawmap, Transform_drawMap, CustomStartVariant_-
	 * adjudicatorPreGame...). gamecreateSandbox.php breaks that rule: it asks every enabled variant
	 * for its canvas board config in one request, which loads each variant's drawMap and
	 * adjudicatorPreGame classes, and the first duplicate name kills the page with
	 * "Cannot redeclare class ...". With this install's large variant list that happens at the
	 * 27th variant, so the page never renders.
	 *
	 * This predicts the collision instead of hitting it: it statically scans the class files a
	 * variant would load (following their require()s and their "extends SomethingVariant_class"
	 * autoloads) and reports whether any class they declare has already been declared from a
	 * different file. The sandbox page skips those variants, so they simply aren't offered there;
	 * every other page loads one variant and is unaffected.
	 *
	 * @param string $variantName
	 * @return string|false The conflicting class name, or false if the variant is safe to load
	 */
	public static function sandboxLoadConflict($variantName) {
		$classFiles = array(
			'variants/'.$variantName.'/classes/drawMap.php',
			'variants/'.$variantName.'/classes/adjudicatorPreGame.php'
		);

		$classes = array(); $scanned = array();
		foreach($classFiles as $classFile)
			self::sandboxScanClassFile($classFile, $classes, $scanned);

		foreach($classes as $className=>$classFile)
		{
			if( !class_exists($className, false) ) continue;

			// Already loaded from this same file; loading it again is a no-op, not a redeclaration
			$Reflection = new ReflectionClass($className);
			$declaredIn = $Reflection->getFileName();
			if( $declaredIn && realpath($declaredIn) === realpath($classFile) ) continue;

			return $className;
		}

		return false;
	}

	/**
	 * LOCAL DEVIATION (sandbox): helper for sandboxLoadConflict(); collects the classes $file and
	 * everything it pulls in would declare, keyed by the first file each name was seen in.
	 */
	private static function sandboxScanClassFile($file, array &$classes, array &$scanned) {
		if( isset($scanned[$file]) || !file_exists($file) ) return;
		$scanned[$file] = true;

		$code = file_get_contents($file);
		$dir = dirname($file);

		preg_match_all('/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z0-9_]+)/m', $code, $matches);
		foreach($matches[1] as $className)
			if( !isset($classes[$className]) ) $classes[$className] = $file;

		// Files the variant's classes require directly, relative to their own folder
		preg_match_all('/(?:require|include)(?:_once)?\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $matches);
		foreach($matches[1] as $relative)
			self::sandboxScanClassFile($dir.'/'.$relative, $classes, $scanned);

		// Parent classes from other variants, which the variant class autoloader will pull in
		preg_match_all('/extends\s+([A-Za-z0-9_]+)Variant_([A-Za-z0-9_]+)/', $code, $matches, PREG_SET_ORDER);
		foreach($matches as $match)
			self::sandboxScanClassFile('variants/'.$match[1].'/classes/'.$match[2].'.php', $classes, $scanned);
	}

	/**
	 * A variant's cache dir
	 * @param string $variantName
	 * @return string Location relative to root webDip folder
	 */
	public static function cacheDir($variantName) {
		return 'variants/'.$variantName.'/cache';
	}

	/**
	 * All loaded variants indexed by name
	 * @var array[$variantName]=$Variant
	 */
	private static $Variants=array();
	/**
	 * For looking up variants by gameID quickly
	 * @var array[$gameID]=$variantID
	 */
	private static $variantIDsByGameID=array();

	/**
	 * Return a Variant object given a variant ID
	 * @param int $variantID
	 * @return Variant
	 */
	public static function loadFromVariantID($variantID) {
		return self::loadFromVariantName( Config::$variants[$variantID] );
	}

	public static function installLock() {
		global $DB;

		static $locked;

		if( !isset($locked) )
			$DB->get_lock('VariantInstall');

		$locked=true;
	}

	public static function wipe($variantName) {
		self::installLock();

		if( file_exists(self::cacheDir($variantName).'/data.php') )
			unlink(self::cacheDir($variantName).'/data.php');
	}

	/**
	 * Return a Variant object given its short name (the preferred/quickest way)
	 * @param string $variantName
	 * @return Variant
	 */
	public static function loadFromVariantName($variantName) {
		global $DB, $Misc;

		if( !isset(self::$Variants[$variantName]) )
		{
			$variantCache=self::cacheDir($variantName).'/data.php';

			if( !file_exists($variantCache) )
			{
				self::installLock();

				if( file_exists($variantCache) )
					libHTML::notice(l_t("Installed variant"), l_t("Variant '%s' installed, please refresh.",$variantName));

				$classname = $variantName.'Variant';
				$Variant = new $classname(); // variants/variant.php __autoload() will find the class for this

				// The object will have loaded all the cacheable data and be ready to be saved for next time
				file_put_contents($variantCache, serialize($Variant));

				// The map has just been reinstalled, so the public variant.json made from it is out of date;
				// libGameFiles::variantURL() writes it again when it is next wanted
				if( file_exists(self::cacheDir($variantName).'/variant.json') )
					unlink(self::cacheDir($variantName).'/variant.json');
			}
			else
			{
				// This variant is saved, and doesn't need to waste database queries retreiving this data again
				$variantData = file_get_contents($variantCache);
				$Variant = unserialize($variantData);


				if( isset($Variant->codeVersion)
					&& $Variant->codeVersion !=null && $Variant->codeVersion != 0 )
				{
					// Cache version checking is enabled

					if( !isset($Variant->cacheVersion) || $Variant->cacheVersion==null
					|| $Variant->cacheVersion < $Variant->codeVersion || !$Variant->cacheVersion )
					{
						// An old cache version has been loaded; wipe this variant's cache and try again.
						self::wipe($variantName);
						$Variant = self::loadFromVariantName($variantName);
					}
				}
			}

			self::$Variants[$variantName]=$Variant;
		}

		return self::$Variants[$variantName];
	}

	/**
	 * Return a Variant object corresponding to a game ID. This has to
	 * @param unknown_type $gameID
	 * @return unknown_type
	 */
	public static function loadFromGameID($gameID) {
		global $DB, $Redis;

		if( !isset(self::$variantIDsByGameID[$gameID]) )
		{
			$gameID=(int)$gameID;

			// Check whether the variant value is cached outside of the database:
			$variantID = (int)$Redis->get('variantIDOfGameID'.$gameID);
			if( !$variantID )
			{
				list($variantID) = $DB->sql_row("SELECT variantID FROM wD_Games WHERE id=".$gameID);

				if( !isset($variantID) || !$variantID )
				{
					libHTML::error(l_t("Game not found, or has an invalid variant set; ensure a valid game ID has been given. Check that this game hasn't been canceled, you may have received a message about it on your <a href='index.php' class='light'>home page</a>."));
				}

				$Redis->set('variantIDOfGameID'.$gameID, $variantID); // Save the variant to avoid unnecessary queries if possible
			}

			self::$variantIDsByGameID[$gameID]=$variantID;;
		}

		return self::loadFromVariantID(self::$variantIDsByGameID[$gameID]);
	}
}

?>