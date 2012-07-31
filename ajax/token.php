<?php
/**
* @package shorty an ownCloud url shortener plugin
* @category internet
* @author Christian Reiner
* @copyright 2011-2012 Christian Reiner <foss@christian-reiner.info>
* @license GNU Affero General Public license (AGPL)
* @link information http://apps.owncloud.com/content/show.php/Shorty?content=150401
* @link repository https://svn.christian-reiner.info/svn/app/oc/shorty
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the license, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.
* If not, see <http://www.gnu.org/licenses/>.
*
*/

/**
 * @file ajax/token.php
 * @brief Ajax method to retrieve a fresh request token for ajax calls
 * @return json: success/error state indicator
 * @return json: Associative array of counts
 * @author Christian Reiner
 */

// swallow any accidential output generated by php notices and stuff to preserve a clean JSON reply structure
OC_Shorty_Tools::ob_control ( TRUE );

//no apps or filesystem
$RUNTIME_NOSETUPFS = TRUE;

// Sanity checks
// we do NOT perform a validity check by using OCP\JSON::callCheck ( );
OCP\JSON::checkLoggedIn ( );
OCP\JSON::checkAppEnabled ( 'shorty' );

try
{
	// generate a fresh token
	$token = OCP\Util::callRegister ( );

	// swallow any accidential output generated by php notices and stuff to preserve a clean JSON reply structure
	OC_Shorty_Tools::ob_control ( FALSE );
	OCP\JSON::success ( array ( 'token'   => $token,
								'message' => OC_Shorty_L10n::t('Created fresh token for ajax requests') ) );
} catch ( Exception $e ) { OC_Shorty_Exception::JSONerror($e); }
?>
