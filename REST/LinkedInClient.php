<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
