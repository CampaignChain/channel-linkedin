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

namespace CampaignChain\Channel\LinkedInBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Security\Authentication\Client\OAuthBundle\Authentication;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

class LinkedInController extends Controller
{
    const RESOURCE_OWNER = 'LinkedIn';

    private $applicationInfo = array(
        'key_labels' => array('id', 'App Key'),
        'secret_labels' => array('secret', 'App Secret'),
        'config_url' => 'https://www.linkedin.com/secure/developer',
        'parameters' => array(
            "force_login" => true,
            'scope' => 'rw_company_admin r_emailaddress r_basicprofile w_share',
        ),
    );

    /**
     * Connect to a LinkedIn account
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if(!$application){
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        }

        return $this->render(
            'CampaignChainChannelLinkedInBundle:Create:index.html.twig',
            array(
                'page_title' => 'Connect with LinkedIn',
                'app_id' => $application->getKey(),
            )
        );
    }

    /**
     * Perform the login into LinkedIn
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function loginAction(Request $request)
    {
        $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
        $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo, true);
        $wizard = $this->get('campaignchain.core.channel.wizard');
        $wizard->set('profile', $oauth->getProfile());

        // Allow to easily find the Facebook user's ID through the Wizard.
        $wizard->set('linkedin_user_id', $oauth->getProfile()->identifier);
        $tokens[$oauth->getProfile()->identifier] = $oauth->getToken();
        $wizard->set('tokens', $tokens);
        return $this->render('CampaignChainChannelLinkedInBundle:Create:closePopUp.html.twig');
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function addLocationAction(Request $request)
    {
        $linkedInService = $this->get('campaignchain_location_linked_in.service');
        $locations = $linkedInService
            ->getParsedLocationsFromLinkedIn();
        $form = $this->createFormBuilder();
        $repository = $this->getDoctrine()->getRepository('CampaignChainCoreBundle:Location');

        foreach ($locations as $identifier => $location) {
            // Has the page already been added as a location?
            $pageExists = $repository->findOneBy([
                'identifier' => $identifier,
                'locationModule' => $location->getLocationModule(),
            ]);

            /*
             * Workaround: Linkedin user IDs might start with a dash (-), which
             * Symfony does not accept as a form field name. Hence, we add the
             * letter "A" in front of every ID.
             */
            $formFieldName = 'A'.$identifier;

            // Compose the checkbox form field.
            $form->add($formFieldName, 'checkbox', [
                'label'     => '<img class="campaignchain-location-image-input-prepend" src="'.$location->getImage().'"> '.$location->getName(),
                'required'  => false,
                'data'     => true,
                'mapped' => false,
                'disabled' => $pageExists,
                'attr' => [
                    'align_with_widget' => true,
                ],
            ]);

            // If a location has already been added before, remove it from this process.
            if ($pageExists) {
                unset($locations[$identifier]);
            }
        }

        if (empty($locations)) {
            $tokens = $wizard = $this->get('campaignchain.core.channel.wizard')->get('tokens');
            $this->get('campaignchain.security.authentication.client.oauth.token')
                ->cleanUpUnassignedTokens($tokens);

            $this->addFlash(
                'warning',
                'All available Locations of this LinkedIn account are already connected.'
            );

            return $this->redirectToRoute('campaignchain_core_location');
        }

        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isValid()) {
            $wizard = $this->get('campaignchain.core.channel.wizard');
            // Find out which locations should be added, i.e. which respective checkbox is active.
            foreach($locations as $identifier => $location){
                // Here's the workaround again:
                $formFieldName = 'A'.$identifier;
                if(!$form->get($formFieldName)->getData()){
                    unset($locations[$identifier]);
                    $wizard->removeLocation($identifier);
                }
            }

            // If there's at least one location to be added, then have the user configure it.
            if(is_array($locations) && count($locations)){
                $wizard->setLocations($locations);

                return $this->redirectToRoute('campaignchain_channel_linkedin_location_configure', ['step' => 0]);
            }

            $this->addFlash(
                'warning',
                'No new location has been added.'
            );

            return $this->redirectToRoute('campaignchain_core_location');
        }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Add LinkedIn Locations',
                'form' => $form->createView(),
            ));

    }

    /**
     * @param Request $request
     * @param $step
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configureLocationAction(Request $request, $step)
    {
        $wizard = $this->get('campaignchain.core.channel.wizard');
        $locations = $wizard->getLocations();

        // Get the identifier of the first element in the locations array.
        $identifier = array_keys($locations)[$step];

        // Retrieve the current location object.
        $location = $locations[$identifier];

        $locationType = $this->get('campaignchain.core.form.type.location');
        $locationType->setBundleName($location->getLocationModule()->getBundle()->getName());
        $locationType->setModuleIdentifier($location->getLocationModule()->getIdentifier());
        $locationType->setView('hide_url');

        $form = $this->createForm($locationType, $location);
        $form->handleRequest($request);

        if ($form->isValid()) {
            if (!$wizard->has('flashBagMsg')) {
                $wizard->set('flashBagMsg', '');
            }

            if ($location->getLocationModule()->getIdentifier() == 'campaignchain-linkedin-user') {
                $this->get('campaignchain_location_linked_in.service')
                    ->handleUserPageCreation($location);
            } else {
                $this->get('campaignchain_location_linked_in.service')
                    ->handleCompanyPageCreation($location);
            }

            $wizard->addLocation($identifier, $location);

            // Are there still locations to be configured?
            if (count($locations) > ($step + 1) ) {
                return $this->redirectToRoute('campaignchain_channel_linkedin_location_configure', ['step' =>  $step + 1]);
            }

            // We are done with configuring the locations, so lets end the Wizard and persist the locations.
            // TODO: Wrap into DB transaction.
            $em = $this->getDoctrine()->getManager();

            foreach($locations as $identifier => $location){
                // Persist the Facebook user- and page-specific data.
                $em->persist($wizard->get($identifier));
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                'The following locations are now connected:'.
                '<ul>'.$wizard->get('flashBagMsg').'</ul>'
            );

            $tokens = $wizard->get('tokens');

            $wizard->persist();

            /*
             * Store all access tokens per location in the OAuth Client
             * bundle's Token entity, but only for the Facebook user
             * locations, not the page locations.
             */
            $tokenService = $this->get('campaignchain.security.authentication.client.oauth.token');
            foreach($tokens as $identifier => $token){
                if (isset($locations[$identifier])) {
                    $token = $em->merge($token);
                    $token->setLocation($locations[$identifier]);
                    $tokenService->setToken($token);
                }
            }

            $wizard->end();
            $em->flush();

            return $this->redirect($this->generateUrl('campaignchain_core_location'));
        }

        return $this->render(
            'CampaignChainLocationLinkedInBundle::new.html.twig',
            array(
                'page_title' => 'Configure LinkedIn Location',
                'form' => $form->createView(),
                'location' => $location,
            ));
    }
}