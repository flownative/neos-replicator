<?php
namespace Flownative\Neos\Replicator;

/*
 * This file is part of the Flownative.Neos.Replicator package.
 *
 * (c) 2016 Flownative
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Package\Package as BasePackage;

/**
 * The Flownative.Neos.Replicator Package
 */
class Package extends BasePackage
{

    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect('TYPO3\TYPO3CR\Domain\Model\Workspace', 'afterNodePublishing', 'Flownative\Neos\Replicator\ReplicationManager', 'nodeHasBeenPublished');
    }
}
