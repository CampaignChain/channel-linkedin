<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Channel\LinkedInBundle\REST;

use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Exception\ExternalApiException;
use CampaignChain\Operation\LinkedInBundle\Entity\NewsItem;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\ApplicationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\TokenService;
use Symfony\Component\HttpFoundation\Session\Session;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;
use CampaignChain\Security\Authentication\Client\OAuthBundle\Entity\Token;

class LinkedInClient
{
    const RESOURCE_OWNER = 'LinkedIn';
    const BASE_URL   = 'https://api.linkedin.com/v1';

    /**
     * @var ApplicationService
     */
    private $oauthApp;

    /**
     * @var TokenService
     */
    private $oauthToken;

    public function __construct(ApplicationService $oauthApp, TokenService $oauthToken)
    {
        $this->oauthApp = $oauthApp;
        $this->oauthToken = $oauthToken;
    }

    /**
     * @param Activity $activity
     *
     * @return LinkedInClientService
     */
    public function getConnectionByActivity(Activity $activity){
        $application = $this->oauthApp
            ->getApplication(self::RESOURCE_OWNER);

        $token = $this->oauthToken
            ->getToken($activity->getLocation());

        $connection = $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());

        return new LinkedInClientService($connection);
    }

    /**
     * Return a connection based on the suplied Token
     *
     * @param Token $token
     *
     * @return LinkedInClientService
     */
    public function getConnectionByToken(Token $token)
    {
        $application = $this->oauthApp
            ->getApplication(self::RESOURCE_OWNER);

        $connection = $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());

        return new LinkedInClientService($connection);
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $accessToken
     * @param string $tokenSecret
     *
     * @return Client
     */
    private function connect($appKey, $appSecret, $accessToken, $tokenSecret){
        try {
            $client = new Client(self::BASE_URL.'/');
            $oauth  = new OauthPlugin(array(
                'consumer_key'    => $appKey,
                'consumer_secret' => $appSecret,
                'token'           => $accessToken,
                'token_secret'    => $tokenSecret,
            ));

            return $client->addSubscriber($oauth);
        } catch(\Exception $e){
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
