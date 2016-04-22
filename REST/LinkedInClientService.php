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
use Guzzle\Http\Client;

/**
 * Class LinkedInClientService
 * @package CampaignChain\Channel\LinkedInBundle\REST
 */
class LinkedInClientService
{
    /**
     * @var Client
     */
    private $connect;

    /**
     * LinkedInClientService constructor.
     * @param Client $connect
     */
    public function __construct(Client $connect)
    {
        $this->connect = $connect;
    }

    /**
     * Return the available companies
     *
     * @return array
     */
    public function getCompanies()
    {
        $request = $this->connect->get('companies', [], [
            'query' => [
                'is-company-admin' => 'true',
                'format' => 'json',
            ]
        ]);

        try {
            $response = $request->send()->json();
            //the logged in user is not admin for any company
            if (isset($response['_total']) && $response['_total'] > 0) {
                return $response['values'];
            }

            return [];
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the company's profile data
     *
     * @param integer $id
     *
     * @return array
     */
    public function getCompanyProfile($id)
    {
        $request = $this->connect->get('companies/'.$id.':(id,name,description,square-logo-url,website-url)', [], [
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
        $id = $activity->getLocation()->getIdentifier();

        $request = $this->connection->post(
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
     * @param string   $content
     *
     * @return array
     */
    public function shareOnUserPage($content)
    {
        $request = $this->connect->post(
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
        $id = $activity->getLocation()->getIdentifier();

        $request = $this->connect->get(
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