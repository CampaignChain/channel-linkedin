<?php

namespace CampaignChain\Channel\LinkedInBundle;

use CampaignChain\Channel\LinkedInBundle\DependencyInjection\CampaignChainChannelLinkedInExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CampaignChainChannelLinkedInBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new CampaignChainChannelLinkedInExtension();
    }
}
