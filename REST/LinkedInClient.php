<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\LinkedInBundle\REST;

use Symfony\Component\HttpFoundation\Session\Session;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;

class LinkedInClient
{
    const RESOURCE_OWNER = 'LinkedIn';
    const BASE_URL   = 'https://api.linkedin.com/v1';

    protected $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function connectByActivity($activity){
        $oauthApp = $this->container->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        $oauthToken = $this->container->get('campaignchain.security.authentication.client.oauth.token');
        $token = $oauthToken->getToken($activity->getLocation());

        return $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());
    }

    public function connect($appKey, $appSecret, $accessToken, $tokenSecret){
        try {
            $client = new Client(self::BASE_URL.'/');
            $oauth  = new OauthPlugin(array(
                'consumer_key'    => $appKey,
                'consumer_secret' => $appSecret,
                'token'           => $accessToken,
                'token_secret'    => $tokenSecret,
            ));

            return $client->addSubscriber($oauth);
        }
        catch (ClientErrorResponseException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            print_r($response);
        }
        catch (ServerErrorResponseException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            print_r($response);
        }
        catch (BadResponseException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            print_r($response);
        }
        catch(Exception $e){
          print_r($e->getMessage());
        }
    }
}