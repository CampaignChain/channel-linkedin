<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\LinkedInBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Location\LinkedInBundle\Entity\LinkedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

class LinkedInController extends Controller
{
    const RESOURCE_OWNER = 'LinkedIn';

    private $applicationInfo = array(
        'key_labels' => array('key', 'App Key'),
        'secret_labels' => array('secret', 'App Secret'),
        'config_url' => 'https://www.linkedin.com/secure/developer',
        'parameters' => array(
            "force_login" => true,
        ),
    );

    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if(!$application){
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        }
        else {
            return $this->render(
                'CampaignChainChannelLinkedInBundle:Create:index.html.twig',
                array(
                    'page_title' => 'Connect with LinkedIn',
                    'app_id' => $application->getKey(),
                )
            );
        }               
    }

    public function loginAction(Request $request){
            $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
            $status = $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo);
            $profile = $oauth->getProfile();
            
            if($status){
                try {
                    $repository = $this->getDoctrine()->getManager();
                    $repository->getConnection()->beginTransaction();

                    $wizard = $this->get('campaignchain.core.channel.wizard');
                    $wizard->setName($profile->displayName);

                    // Get the location module.
                    $locationService = $this->get('campaignchain.core.location');
                    $locationModule = $locationService->getLocationModule('campaignchain/location-linkedin', 'campaignchain-linkedin-user');

                    $location = new Location();
                    $location->setIdentifier($profile->identifier);
                    $location->setName($profile->displayName);
                    $location->setLocationModule($locationModule);
                    // If no image, then use the ghost person instead.
                    if(!$profile->photoURL || strlen($profile->photoURL) == 0){
                        $profile->photoURL = $this->container->get('templating.helper.assets')
                            ->getUrl(
                                '/bundles/campaignchainchannellinkedin/ghost_person.png',
                                null
                            );
                    }
                    $location->setImage($profile->photoURL);
                    $location->setUrl($profile->profileURL);

                    $wizard->addLocation($location->getIdentifier(), $location);

                    $channel = $wizard->persist();
                    $wizard->end();
                    
                    $oauth->setLocation($channel->getLocations()[0]);

                    $linkedinUser = new LinkedInUser();
                    $linkedinUser->setLocation($channel->getLocations()[0]);
                    $linkedinUser->setIdentifier($profile->identifier);
                    $linkedinUser->setDisplayName($profile->displayName);
                    $linkedinUser->setProfileImageUrl($profile->photoURL);
                    $linkedinUser->setProfileUrl($profile->profileURL);

                    $repository->persist($linkedinUser);
                    $repository->flush();

                    $repository->getConnection()->commit();

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'The LinkedIn location <a href="#">'.$profile->displayName.'</a> was connected successfully.'
                    );
                } catch (\Exception $e) {
                    $repository->getConnection()->rollback();
                    throw $e;
                }
            } else {
                // A channel already exists that has been connected with this Facebook account
                $this->get('session')->getFlashBag()->add(
                    'warning',
                    'A location has already been connected for this LinkedIn account.'
                );
            }

        return $this->render(
            'CampaignChainChannelLinkedInBundle:Create:login.html.twig',
            array(
                'redirect' => $this->generateUrl('campaignchain_core_channel')
            )
        );
    }
}