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
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Neos\Domain\Model\Site;

/**
 * Rudimentary REST service for sites
 *
 * For Neos 2.3 this should hopefully be part of Neos and this controller can be dropped.
 *
 * @Flow\Scope("singleton")
 */
class SitesController extends ActionController
{
    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Package\PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var \TYPO3\Neos\Domain\Repository\SiteRepository
     */
    protected $siteRepository;

    /**
     * @see https://github.com/neos/neos-development-collection/pull/494
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => 'TYPO3\Fluid\View\TemplateView',
        'json' => 'Flownative\Neos\Replicator\View\Service\SiteJsonView'
    ];

    /**
     * A list of IANA media types which are supported by this controller
     *
     * @var array
     * @see http://www.iana.org/assignments/media-types/index.html
     */
    protected $supportedMediaTypes = [
        'text/html',
        'application/json'
    ];

    /**
     * Shows a list of existing sites
     *
     * @return string
     */
    public function indexAction()
    {
        $sitesArray = [];
        /** @var Site $site */
        foreach ($this->siteRepository->findAll() as $site) {
            $siteArray = [
                'name' => $site->getName(),
                'nodeName' => $site->getNodeName(),
                'siteResourcesPackageKey' => $site->getSiteResourcesPackageKey(),
                'state' => $site->getState()
            ];
            $sitesArray[] = $siteArray;
        }

        $this->view->assign('sites', $sitesArray);
    }

    /**
     * Shows details of the given site
     *
     * Just using a $site parameter with Site doesn't work, probably because Site has just Flow\Identity but not ORM\Id.
     *
     * @param string $nodeName
     * @return string
     */
    public function showAction($nodeName)
    {
        $site = $this->siteRepository->findOneByNodeName($nodeName);
        if ($site === null) {
            $this->throwStatus(404, 'Site not found', '');
        }

        $this->view->assign('site', $site);
    }

    /**
     * Creates a new site
     *
     * @param string $name
     * @param string $nodeName
     * @param integer $state
     * @param string $siteResourcesPackageKey
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function createAction($name, $nodeName, $state, $siteResourcesPackageKey)
    {
        $existingSite = $this->siteRepository->findOneByNodeName($nodeName);
        if ($existingSite !== null) {
            $this->throwStatus(409, 'Site already exists', '');
        }
        if (!$this->packageManager->isPackageAvailable($siteResourcesPackageKey)) {
            $this->throwStatus(424, sprintf('Package "%s" specified as site resources package does not exist.', $siteResourcesPackageKey), '');
        }
        if (!$this->packageManager->isPackageActive($siteResourcesPackageKey)) {
            $this->throwStatus(424, sprintf('Package "%s" specified as site resources package is not active.', $siteResourcesPackageKey), '');
        }

        $site = new Site($nodeName);
        $site->setName($name);
        $site->setState((integer)$state);
        $site->setSiteResourcesPackageKey($siteResourcesPackageKey);

        $this->siteRepository->add($site);
        $this->throwStatus(201, 'Site created', '');
    }
}