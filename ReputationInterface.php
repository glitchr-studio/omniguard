<?php

namespace Omniguard;

use Omniguard\Model\Identity;
use Omniguard\Model\Reputation;

/**
 * Are this IP address, this e-mail, this name known for abuse? A list,
 * local or someone else's, asked about whoever submits a form.
 */
interface ReputationInterface extends GatewayInterface
{
    /**
     * What the list knows of the parts of the identity it reads (its
     * Capabilities say which); the parts it does not read are ignored.
     *
     * @throws Exception\UnreachableException when the provider did not answer
     */
    public function lookup(Identity $identity): Reputation;
}
