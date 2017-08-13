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

/**
 * Class LinkedInClientService
 * @package CampaignChain\Channel\LinkedInBundle\REST
 */
class LinkedInClientService
{
    /**
     * @var LinkedInClient
     */
    private $connect;

    /**
     * LinkedInClientService constructor.
     * @param LinkedInClient $connect
     */
    public function __construct(LinkedInClient $connect)
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
        $response = $this->connect->request('GET','companies', [
            'query' => [
                'is-company-admin' => 'true',
                'format' => 'json',
            ]
        ]);

        //the logged in user is not admin for any company
        if (isset($response['_total']) && $response['_total'] > 0) {
            return $response['values'];
        }

        return [];
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
        return $this->connect->request(
            'GET',
            'companies/'.$id.':(id,name,description,square-logo-url,website-url)', [
            'query' => [
                'format' => 'json',
            ]
        ]);
    }

    /**
     * Share a news on a company page
     *
     * @param Activity $activity
     * @param string   $content
     *
     * @return array
     */
    public function shareOnCompanyPage(Activity $activity, array $content)
    {
        $id = $activity->getLocation()->getIdentifier();

        return $this->connect->request(
            'POST',
            'companies/'.$id.'/shares',
            [
                'headers' => [
                    'x-li-format' => 'json',
                    ],
                'body' => json_encode($content),
                'query' => [
                    'format' => 'json',
                ]
            ]
        );
    }

    /**
     * Share a news on an user page
     *
     * @param string   $content
     *
     * @return array
     */
    public function shareOnUserPage(array $content)
    {
        return $this->connect->request(
            'POST',
            'people/~/shares',
            [
                'headers' => [
                    'x-li-format' => 'json',
                ],
                'body' => json_encode($content),
                'query' => [
                    'format' => 'json',
                ]
            ]
        );
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

        return $this->connect->request(
            'GET',
            'companies/'.$id.'/updates/key='.$newsItem->getUpdateKey(),
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );
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
        //LinkedIn API seems broken, re-enable when it works again.
        //return [];

        return $this->connect->request(
            'GET',
            'people/~/network/updates/key='.$newsItem->getUpdateKey(),
            [
                'query' => [
                    'format' => 'json',
                ]
            ]
        );
    }
}