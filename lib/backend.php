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
 * @file lib/backend.php
 * Routines to use remote (online) shortening services as backends in a local workflow
 * @author Christian Reiner
 */

/**
 * @class OC_Shorty_Backend
 * @brief Library to register urls using backends, typically remote (online) url shortening services
 * @access public
 * @author Christian Reiner
 */
class OC_Shorty_Backend
{
	/**
	* @method OC_Shorty_Backend::registerUrl
	* @brief Wrapper function around the specific backend routines
	* @param string id: Internal shorty id used to reference a shorty upon usage.
	* @return string: The shortened url as generated by a specific backend.
	* @throws OC_Shorty_Exception taking over the explaining of the failure from the specific backend
	* @access public
	* @author Christian Reiner
	*/
	static function registerUrl ( $id )
	{
		try
		{
			// construct the $relay, the url to be called to reach THIS service (ownclouds shorty plugin)
			$relay = OC_Shorty_Tools::relayUrl ( $id );
			// call backend specific work horse
			switch ( $type=OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-type','none') )
			{
				default:
					return OC_Shorty_Backend::registerUrl_default ( $id, $relay );

				case 'static':
					return OC_Shorty_Backend::registerUrl_static  ( $id, $relay );

				case 'bitly':
					return OC_Shorty_Backend::registerUrl_bitly   ( $id, $relay );

				case 'cligs':
					return OC_Shorty_Backend::registerUrl_cligs   ( $id, $relay );

				case 'google':
					return OC_Shorty_Backend::registerUrl_google  ( $id, $relay );

				case 'isgd':
					return OC_Shorty_Backend::registerUrl_isgd    ( $id, $relay );

				case 'tinyurl':
					return OC_Shorty_Backend::registerUrl_tinyurl ( $id, $relay );

				case 'tinycc':
					return OC_Shorty_Backend::registerUrl_tinycc  ( $id, $relay );
			} // switch
		} // try
		catch (OC_Shorty_Exception $e)
		{
			throw $e;
		} // catch
		catch (Exception $e)
		{
			throw new OC_Shorty_Exception ( "Failed to register url '%s' at '%s' backend.", array($relay,$type) );
		} // catch
	} // OC_Shorty_Backend::registerUrl

	/**
	* @method OC_Shorty_Backend::registerUrl_default
	* @brief Pseudo-registers a given local relay url
	* @param string id
	* @param url relay
	* @return url: Validated and pseudo-registered relay
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_default ( $id, $relay )
	{
		return OC_Shorty_Type::validate ( $relay, OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_default

	/**
	* @method OC_Shorty_Backend::registerUrl_static
	* @brief Registers a given local relay url as local static shorty
	* @param string id
	* @param url relay
	* @return url: Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_static ( $id, $relay )
	{
		if (  (FALSE===($base=trim ( OCP\Config::getAppValue('shorty','backend-static-base',FALSE))))
			||(empty($base)) )
			throw new OC_Shorty_Exception ( 'No base url defined for the static backend.' );
		return OC_Shorty_Type::validate ( $base.$id, OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_static

	/**
	* @method OC_Shorty_Backend::registerUrl_bitly
	* @brief Registers a given local relay url at the bit.ly shortening service
	* @param string id
	* @param url relay
	* @return: Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_bitly ( $id, $relay )
	{
		$bitly_api_user = OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-bitly-user','');
		$bitly_api_key  = OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-bitly-key', '');
		if ( ! $bitly_api_key || ! $bitly_api_user )
			throw new OC_Shorty_Exception ( 'No API user or key configured.' );
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, 'https://api-ssl.bit.ly/shorten' );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_POST, TRUE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json') );
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, json_encode(array(	'version'=>'2.0.1',
																	'longUrl'=>$relay,
																	'format'=>'json',
																	'login'=>$bitly_api_user,
																	'apiKey'=>$bitly_api_key) ) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||(NULL===($payload=json_decode($reply)))
			||(!is_object($payload))
			||(!property_exists($payload,'id')) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'bit.ly'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl))  );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $payload->id, OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_bitly

	/**
	* @method OC_Shorty_Backend::registerUrl_cligs
	* @brief Registers a given local relay url at the cli.gs shortening service
	* @param string id
	* @param url relay
	* @return Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_cligs ( $id, $relay )
	{
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, sprintf('http://cli.gs/api/v2/cligs/create?url=%s&appid=owncloud_shorty&test=1', urlencode(trim($relay))) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||( ! preg_match( '/^(.+)$/', $reply, $match )) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'cli.gs'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl))  );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $match[1], OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_cligs

	/**
	* @method OC_Shorty_Backend::registerUrl_isgd
	* @brief Registers a given local relay url at the is.gd shortening service
	* @param string id
	* @param url relay
	* @return Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_isgd ( $id, $relay )
	{
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, sprintf('http://is.gd/create.php?format=simple&url=%s', urlencode(trim($relay))) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||( ! preg_match( '/^(.+)$/', $reply, $match )) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'is.gd'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl))  );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $match[1], OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_isgd

	/**
	* @method OC_Shorty_Backend::registerUrl_google
	* @brief Registers a given local relay url at the google shortening service
	* @param string id
	* @param url relay
	* @return Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_google ( $id, $relay )
	{
		$api_key = OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-google-key','');
		if ( ! $api_key )
			throw new OC_Shorty_Exception ( 'No goo.gl API key configured' );
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, 'https://www.googleapis.com/urlshortener/v1/url' );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_POST, TRUE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json') );
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, json_encode(array('longUrl'=>$relay,
																'key'=>$api_key) ) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||(NULL===($payload=json_decode($reply)))
			||(!is_object($payload))
			||(!property_exists($payload,'id')) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'goo.gl'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl)) );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $payload->id, OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_google

	/**
	* @method OC_Shorty_Backend::registerUrl_tinycc
	* @brief Registers a given local relay url at the tiny.cc shortening service
	* @param string id
	* @param url relay
	* @return Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_tinycc ( $id, $relay )
	{
		$api_user = OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-tinycc-user','');
		$api_key  = OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-tinycc-key','');
		if ( ! $api_key || ! $api_user )
			throw new OC_Shorty_Exception ( 'No goo.gl API key configured' );
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, 'http://tiny.cc/?c=shorten' );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, (OCP\Config::getUserValue(OCP\User::getUser(),'shorty','backend-ssl-verify')) );
		curl_setopt ( $curl, CURLOPT_POST, TRUE );
		curl_setopt ( $curl, CURLOPT_HEADER, TRUE );
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, array(	'longUrl'=>$relay,
														'version'=>'2.0.3',
														'format'=>'json',
														'login'=>$api_user,
														'apiKey'=>$api_key) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||(NULL===($payload=json_decode($reply)))
			||(!is_object($payload))
			||(!property_exists($payload,'id')) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'tiny.cc'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl))  );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $payload->id, OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_google

	/**
	* @method OC_Shorty_Backend::registerUrl_tinyurl
	* @brief Registers a given local relay url at the tinyURL shortening service
	* @param string id
	* @param url relay
	* @return Registered and validated relay url
	* @access public
	* @author Chrisian Reiner
	*/
	static function registerUrl_tinyurl ( $id, $relay )
	{
		$curl = curl_init ( );
		curl_setopt ( $curl, CURLOPT_URL, sprintf('http://tinyurl.com/api-create.php?url=%s', urlencode(trim($relay))) );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		if (  (FALSE===($reply=curl_exec($curl)))
			||( ! preg_match( '/^(.+)$/', $reply, $match )) )
		{
			throw new OC_Shorty_Exception ( "Failed to register url at backend 'tinyUrl'. \nError %s: %s",
											array(curl_errno($curl),curl_error($curl))  );
		}
		curl_close ( $curl );
		return OC_Shorty_Type::validate ( $match[1], OC_Shorty_Type::URL );
	} // OC_Shorty_Backend::registerUrl_tinyurl

} // class OC_Shorty_Backend
