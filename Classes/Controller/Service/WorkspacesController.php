<?php
namespace Flownative\Neos\Replicator\Controller\Service;

/*
 * This file is part of the Flownative.Neos.Replicator package.
 *
 * (c) 2016 Flownative
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Workspace;

/**
 * Rudimentary REST service for workspaces
 *
 * This enhances the Neos controller as needed, so the package is usable with Neos 2.1 and 2.2.
 * For Neos 2.3 the changes should hopefully be part of Neos and this controller can be dropped.
 *
 * @Flow\Scope("singleton")
 */
class WorkspacesController extends \TYPO3\Neos\Controller\Service\WorkspacesController
{
    /**
     * Creates a workspace without base workspace (a "live" workspace)
     *
     * @param string $workspaceName
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function createLiveAction($workspaceName)
    {
        $existingWorkspace = $this->workspaceRepository->findByIdentifier($workspaceName);
        if ($existingWorkspace !== null) {
            $this->throwStatus(409, 'Workspace already exists', '');
        }

        $workspace = new Workspace($workspaceName);
        $this->workspaceRepository->add($workspace);
        $this->throwStatus(201, 'Workspace created', '');
    }
}
