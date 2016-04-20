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
     * @return Client
     */
    public function connectByActivity(Activity $activity){
        $application = $this->oauthApp
            ->getApplication(self::RESOURCE_OWNER);

        $token = $this->oauthToken
            ->getToken($activity->getLocation());

        return $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $accessToken
     * @param string $tokenSecret
     * @return Client
     */
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
        } catch(\Exception $e){
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Return a connection based on the suplied Token
     *
     * @param Token $token
     *
     * @return Client
     */
    private function getConnectionByToken(Token $token)
    {
        $application = $this->oauthApp
            ->getApplication(self::RESOURCE_OWNER);

        return $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());
    }

    /**
     * Return the available companies
     *
     * @param Token $token
     *
     * @return array
     */
    public function getCompanies(Token $token)
    {
        $connect = $this->getConnectionByToken($token);

        if (!$connect) {
            return [];
        }

        $request = $connect->get('companies', [], [
            'query' => [
                'is-company-admin' => 'true',
                'format' => 'json',
            ]
        ]);

        try {
            $response = $request->send()->json();

            return $response['values'];
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the company's profile data
     *
     * @param Token   $token
     * @param integer $id
     *
     * @return array
     */
    public function getCompanyProfile(Token $token, $id)
    {
        $connect = $this->getConnectionByToken($token);

        if (!$connect) {
            return [];
        }

        $request = $connect->get('companies/'.$id.':(id,name,description,square-logo-url,website-url)', [], [
            'query' => [
                'format' => 'json',
            ]
        ]);

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Share a news on a company page
     *
     * @param Activity $activity
     * @param string   $content
     *
     * @return array
     */
    public function shareOnCompanyPage(Activity $activity, $content)
    {
        $connection = $this->connectByActivity($activity);

        if (!$connection) {
            return [];
        }
        $id = $activity->getLocation()->getIdentifier();

        $request = $connection->post(
            'companies/'.$id.'/shares',
            [
                'x-li-format' => 'json',
            ],
            json_encode($content),
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Share a news on an user page
     *
     * @param Activity $activity
     * @param string   $content
     *
     * @return array
     */
    public function shareOnUserPage(Activity $activity, $content)
    {
        $connection = $this->connectByActivity($activity);

        if (!$connection) {
            return [];
        }

        $request = $connection->post(
            'people/~/shares',
            [
                'x-li-format' => 'json',
            ],
            json_encode($content),
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get a company update statistics
     *
     * @param Activity $activity
     * @param NewsItem $newsItem
     * @return array
     */
    public function getCompanyUpdate(Activity $activity, NewsItem $newsItem)
    {
        $connection = $this->connectByActivity($activity);

        if (!$connection) {
            return [];
        }
        $id = $activity->getLocation()->getIdentifier();

        $request = $connection->get(
            'companies/'.$id.'/updates/key='.$newsItem->getUpdateKey(),
            [],
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get a user update statistics
     *
     * @param Activity $activity
     * @param NewsItem $newsItem
     * @return array
     */
    public function getUserUpdate(Activity $activity, NewsItem $newsItem)
    {
        //LinkedIn API seems broken, reenable when it works again.
        return [];

        $connection = $this->connectByActivity($activity);

        if (!$connection) {
            return [];
        }

        $request = $connection->get(
            'people/~/network/updates/key='.$newsItem->getUpdateKey(),
            [],
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );

        try {
            return $request->send()->json();
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
