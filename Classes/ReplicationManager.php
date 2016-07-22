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

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Request as HttpRequest;
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3\Flow\Utility\TypeHandling;
use TYPO3\Media\Domain\Model\Asset;
use TYPO3\Media\Domain\Model\AssetInterface;
use TYPO3\Media\Domain\Model\ImageInterface;
use TYPO3\Media\Domain\Model\ImageVariant;
use TYPO3\Neos\Domain\Model\Site;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\Workspace;

/**
 * This orchestrates the replication.
 *
 * The class contains slots that are wired to the relevant signals coming from Neos or the Content Repository.
 * These slots trigger the needed replication processes, which are then carried out with the help of services
 * in this and other packages as needed.
 *
 * @Flow\Scope("singleton")
 */
class ReplicationManager
{
    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Http\Client\Browser
     */
    protected $browser;

    /**
     * @Flow\Inject
     * @var \TYPO3\Neos\Service\Mapping\NodePropertyConverterService
     */
    protected $nodePropertyConverterService;

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Property\PropertyMapper
     */
    protected $propertyMapper;

    /**
     * @var PropertyMappingConfiguration
     */
    protected $propertyMappingConfiguration;

    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings = [];

    /**
     * @var ReplicationConfiguration[]
     */
    protected $replicationConfigurations = [];

    /**
     * @var ReplicationTarget[]
     */
    protected $replicationTargets = [];

    /**
     * Serves as a simple cache to make sure each target/site is checked only once per request
     *
     * @var array
     */
    protected $firstLevelSitesCache = [];

    /**
     * Serves as a simple cache to make sure each target/workspace is checked only once per request
     *
     * @var array
     */
    protected $firstLevelWorkspacesCache = [];

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Log\SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     *
     *
     * @return void
     */
    public function initializeObject()
    {
        $this->propertyMappingConfiguration = new PropertyMappingConfiguration();

        $engine = new CurlEngine();
// used to watch the wire traffic
//$engine->setOption(CURLOPT_PROXY, 'localhost:8888');
        $this->browser->setRequestEngine($engine);
        $this->browser->addAutomaticRequestHeader('Content-Type', 'application/json');
        $this->browser->addAutomaticRequestHeader('Accept', 'application/json');

        foreach ($this->settings['replications'] as $identifier => $configuration) {
            $this->replicationConfigurations[$identifier] = new ReplicationConfiguration($identifier, $configuration);
        }
        foreach ($this->settings['targets'] as $identifier => $configuration) {
            $this->replicationTargets[$identifier] = new ReplicationTarget($identifier, $configuration);
        }
    }

    /**
     * This is used as a slot and wired to a signal emitted after node publication.
     *
     * It then looks for matching replications and makes sure they are done.
     *
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace
     * @return void
     */
    public function nodeHasBeenPublished(NodeInterface $node, Workspace $targetWorkspace)
    {
        $replicationConfigurations = $this->getMatchingReplicationConfigurations($targetWorkspace->getName(), ReplicationConfiguration::TRIGGER_PUBLISH);
        foreach ($replicationConfigurations as $replicationConfiguration) {
            foreach ($replicationConfiguration->getTargets() as $targetIdentifier) {
                $target = $this->replicationTargets[$targetIdentifier];

                try {
                    $site = $node->getContext()->getCurrentSite();
                    $this->replicateSite($site, $target);
                } catch (\RuntimeException $exception) {
                    $this->systemLogger->log(sprintf('Skipping replication to target "%s". %s', $target->getName(), $exception->getMessage()), LOG_WARNING);
                    continue;
                }

                try {
                    $this->replicateWorkspace($targetWorkspace, $target);
                } catch (\RuntimeException $exception) {
                    $this->systemLogger->log(sprintf('Skipping replication to target "%s". %s', $target->getName(), $exception->getMessage()), LOG_WARNING);
                    continue;
                }

                try {
                    $this->replicateNode($node, $targetWorkspace, $target);
                } catch (\RuntimeException $exception) {
                    $this->systemLogger->log(sprintf('Error during replication of node "%s" to target "%s". %s', (string)$node, $target->getName(), $exception->getMessage()), LOG_ERR);
                }
            }
            $this->systemLogger->log(sprintf('Replicated publication of "%s" to workspace "%s" using replication configuration "%s"', (string)$node, $targetWorkspace->getName(), $replicationConfiguration->getName()), LOG_DEBUG);
        }
    }

    /**
     * Returns an array with ReplicationConfiguration instances matching the $workspaceName and trigger
     *
     * @param $workspaceName
     * @param $trigger
     * @return ReplicationConfiguration[]
     */
    protected function getMatchingReplicationConfigurations($workspaceName, $trigger)
    {
        return array_filter($this->replicationConfigurations, function (ReplicationConfiguration $replicationConfiguration) use ($workspaceName, $trigger) {
            return $replicationConfiguration->getTrigger() === $trigger && $replicationConfiguration->matchesWorkspaceName($workspaceName);
        });
    }

    /**
     * Replicates the site to the target.
     *
     * @param Site $site
     * @param ReplicationTarget $replicationTarget
     * @return void
     */
    protected function replicateSite(Site $site, ReplicationTarget $replicationTarget)
    {
        if (isset($this->firstLevelSitesCache[$replicationTarget->getName()][$site->getNodeName()])) {
            return;
        }

        $response = $this->get('sites/' . $site->getNodeName(), $replicationTarget);
        switch ($response->getStatusCode()) {
            case 404:
                $arguments = [
                    'name' => $site->getName(),
                    'nodeName' => $site->getNodeName(),
                    'state' => $site->getState(),
                    'siteResourcesPackageKey' => $site->getSiteResourcesPackageKey()
                ];
                $response = $this->post('sites', $replicationTarget, $arguments);
                switch ($response->getStatusCode()) {
                    case 201:
                        $this->systemLogger->log(sprintf('Created site "%s" on target "%s"', $site->getName(), $replicationTarget->getName(), $response->getStatusCode()), LOG_NOTICE);
                    break;
                    default:
                        $message = sprintf('Could not create site "%s" on target "%s", got status %u', $site->getName(), $replicationTarget->getName(), $response->getStatusCode());
                        $this->systemLogger->log($message, LOG_ERR);

                        throw new \RuntimeException($message, 1469116430);
                }
            break;
            case 200:
                // update as needed (base first, rinse and repeat)
                $this->systemLogger->log(sprintf('TODO Updated site "%s" on target "%s"', $site->getName(), $replicationTarget->getName()), LOG_DEBUG);
            break;
            default:
                $message = sprintf('Could not check for site "%s" on target "%s", got status %u', $site->getName(), $replicationTarget->getName(), $response->getStatusCode());
                $this->systemLogger->log($message, LOG_ERR);

                throw new \RuntimeException($message, 1469116432);
        }

        $this->firstLevelSitesCache[$replicationTarget->getName()][$site->getNodeName()] = true;
    }

    /**
     * Replicates the workspace (as in the workspace entity) to the target.
     *
     * @param Workspace $workspace
     * @param ReplicationTarget $replicationTarget
     * @return void
     */
    protected function replicateWorkspace(Workspace $workspace, ReplicationTarget $replicationTarget)
    {
        if (isset($this->firstLevelWorkspacesCache[$replicationTarget->getName()][$workspace->getName()])) {
            return;
        }

        $response = $this->get('workspaces/' . $workspace->getName(), $replicationTarget);
        switch ($response->getStatusCode()) {
            case 404:
                $baseWorkspaces = $workspace->getBaseWorkspaces();
                foreach (array_reverse($baseWorkspaces) as $baseWorkspace) {
                    $this->replicateWorkspace($baseWorkspace, $replicationTarget);
                }

                $response = $this->post('workspaces/' . $workspace->getName(), $replicationTarget);
                switch ($response->getStatusCode()) {
                    case 201:
                        $this->systemLogger->log(sprintf('Created workspace "%s" on target "%s"', $workspace->getName(), $replicationTarget->getName(), $response->getStatusCode()), LOG_NOTICE);
                    break;
                    default:
                        $message = sprintf('Could not create workspace "%s" on target "%s", got status %u', $workspace->getName(), $replicationTarget->getName(), $response->getStatusCode());
                        $this->systemLogger->log($message, LOG_ERR);

                        throw new \RuntimeException($message, 1469111945);
                }
            break;
            case 200:
                // update as needed (base first, rinse and repeat)
                $this->systemLogger->log(sprintf('TODO Updated workspace "%s" on target "%s"', $workspace->getName(), $replicationTarget->getName()), LOG_DEBUG);
            break;
            default:
                $message = sprintf('Could not check for workspace "%s" on target "%s", got status %u', $workspace->getName(), $replicationTarget->getName(), $response->getStatusCode());
                $this->systemLogger->log($message, LOG_ERR);

                throw new \RuntimeException($message, 1461947407);
        }

        $this->firstLevelWorkspacesCache[$replicationTarget->getName()][$workspace->getName()] = true;
    }

    /**
     * For resource-based properties, replicate them as needed.
     *
     * @param NodeInterface $node
     * @param ReplicationTarget $replicationTarget
     * @return void
     */
    protected function processResourceBasedProperties(NodeInterface $node, ReplicationTarget $replicationTarget)
    {
        foreach ($node->getNodeType()->getProperties() as $propertyName => $propertyConfiguration) {
            if (!isset($propertyConfiguration['type'])) {
                continue;
            }

            $asset = $node->getProperty($propertyName);
            if (empty($asset)) {
                continue;
            }

            switch ($propertyConfiguration['type']) {
                case AssetInterface::class:
                case ImageInterface::class:
                    if ($asset instanceof ImageVariant) {
                        $image = $asset->getOriginalAsset();
                        $this->createOrUpdateAsset($image, $replicationTarget);
                    }

                    $this->createOrUpdateAsset($asset, $replicationTarget);
            }

        }
    }

    /**
     * Create or update the asset to the target, as needed.
     *
     * @param Asset $asset
     * @param ReplicationTarget $replicationTarget
     * @return void
     */
    protected function createOrUpdateAsset(Asset $asset, ReplicationTarget $replicationTarget)
    {
        $response = $this->get('assets/' . $asset->getIdentifier(), $replicationTarget);
        switch ($response->getStatusCode()) {
            case 200:
                $this->systemLogger->log(sprintf('TODO Update asset "%s" to target "%s"', $asset->getIdentifier(), $replicationTarget->getName()), LOG_DEBUG);
            break;
            case 404:
                $arguments['asset'] = $this->propertyMapper->convert($asset, 'array', new PropertyMappingConfiguration());
                $response = $this->post('assets', $replicationTarget, $arguments);
                switch ($response->getStatusCode()) {
                    case 200:
                    case 201:
                    case 303:
                        $this->systemLogger->log(sprintf('Created asset "%s" on target "%s"', $asset->getIdentifier(), $replicationTarget->getName()), LOG_DEBUG);
                    break;
                    default:
                        throw new \RuntimeException($response->getStatusLine(), 1462264637);
                }
            break;
            default:
                throw new \RuntimeException($response->getStatusLine(), 1462220142);
        }
    }

    /**
     * Replicates the node to the target.
     *
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace
     * @param ReplicationTarget $replicationTarget
     * @return void
     * @todo use property mapper to convert nodes?
     */
    protected function replicateNode(NodeInterface $node, Workspace $targetWorkspace, ReplicationTarget $replicationTarget)
    {
        if ($node->isRemoved() === true) {
            // fetch, check version, return 428 if newer on target?
            $response = $this->delete('nodes/' . $node->getIdentifier(), $replicationTarget, ['workspace' => $targetWorkspace->getName(), 'dimensions' => $node->getDimensions()]);
            switch ($response->getStatusCode()) {
                case 200:
                case 204:
                    $this->systemLogger->log(sprintf('"%s" removed on target "%"', (string)$node, $replicationTarget->getName()), LOG_NOTICE);
                break;
                case 404:
                    $this->systemLogger->log(sprintf('Did not find "%s" scheduled for removal on target "%s", status returned "%s"', (string)$node, $replicationTarget->getName(), $response->getStatusLine()), LOG_WARNING);
                break;
                default:
                    throw new \RuntimeException($response->getStatusLine(), 1462220142);
            }
        } else {
            $this->processResourceBasedProperties($node, $replicationTarget);

            $response = $this->get('nodes/' . $node->getIdentifier(), $replicationTarget, ['workspaceName' => $targetWorkspace->getName(), 'dimensions' => $node->getDimensions()]);
            switch ($response->getStatusCode()) {
                case 200:
                    // check version, return 428 if newer on target?
                    $arguments = [
                        'workspaceName' => $targetWorkspace->getName(),
                        'dimensions' => $node->getDimensions(),
                        'properties' => $this->getNodePropertiesArray($node)
                    ];

                    $response = $this->put('nodes/' . $node->getIdentifier(), $replicationTarget, $arguments);
                    switch ($response->getStatusCode()) {
                        case 200:
                        case 201:
                        case 303:
                            $this->systemLogger->log(sprintf('Updated node "%s" on target "%s", status returned "%s"', (string)$node, $replicationTarget->getName(), $response->getStatusLine()), LOG_DEBUG);
                        break;
                        default:
                            throw new \RuntimeException($response->getStatusLine(), 1462264637);
                    }
                break;
                case 404:
                    $parentNode = $node->getParent();
                    while ($parentNode instanceof NodeInterface) {
                        if ($parentNode->getDepth() > 0) {
                            $this->replicateNode($parentNode, $targetWorkspace, $replicationTarget);
                        }
                        $parentNode = $parentNode->getParent();
                    }

                    $arguments = [
                        'mode' => 'new',
                        'identifier' => $node->getIdentifier(),
                        'workspaceName' => $targetWorkspace->getName(),
                        'dimensions' => $node->getDimensions(),
                        'properties' => $this->getNodePropertiesArray($node)
                    ];
                    $response = $this->post('nodes', $replicationTarget, $arguments);
                    switch ($response->getStatusCode()) {
                        case 200:
                        case 201:
                        case 303:
                            $this->systemLogger->log(sprintf('Created node "%s" on target "%s", status returned "%s"', (string)$node, $replicationTarget->getName(), $response->getStatusLine()), LOG_DEBUG);
                        break;
                        default:
                            throw new \RuntimeException($response->getStatusLine(), 1462264637);
                    }
                break;
                default:
                    throw new \RuntimeException($response->getStatusLine(), 1462259077);
            }
        }
    }

    /**
     * Get all properties reduced to simple type representations in an array
     *
     * @param NodeInterface $node
     * @return array
     */
    protected function getNodePropertiesArray(NodeInterface $node)
    {
        $properties = $this->nodePropertyConverterService->getPropertiesArray($node);

        // include private properties for our case, we need them!
        foreach ($node->getNodeType()->getProperties() as $propertyName => $propertyConfiguration) {
            if ($propertyName[0] === '_' && $propertyName[1] === '_') {
                try {
                    $properties[$propertyName] = $this->nodePropertyConverterService->getProperty($node, $propertyName);
                } catch (PropertyNotAccessibleException $exception) {
                }
            }
        }

        // and include even more things we need
        $properties['_index'] = $node->getIndex();

        return $properties;
    }

    /**
     * Send a DELETE request to the $path at the $target system.
     *
     * @param string $path
     * @param ReplicationTarget $target
     * @param array $arguments
     * @return Response
     */
    protected function delete($path, ReplicationTarget $target, array $arguments = [])
    {
        $url = new Uri($target->getBaseUrl() . 'neos-replicator/' . $path);
        $request = HttpRequest::create($url, 'DELETE');
        $request->setHeader('X-Neos-Replicator-Api-Key', $target->getApiKey());
        $request->setContent(json_encode($arguments));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg(), 1469183994);
        }

        return $this->browser->sendRequest($request);
    }

    /**
     * Send a GET request to the $path at the $target system.
     *
     * @param string $path
     * @param ReplicationTarget $target
     * @param array $arguments
     * @return Response
     */
    protected function get($path, ReplicationTarget $target, array $arguments = [])
    {
        $url = new Uri($target->getBaseUrl() . 'neos-replicator/' . $path);
        $request = HttpRequest::create($url, 'GET');
        $request->setHeader('X-Neos-Replicator-Api-Key', $target->getApiKey());
        $request->setContent(json_encode($arguments));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg(), 1469183994);
        }

        return $this->browser->sendRequest($request);
    }

    /**
     * Send a POST request to the $path at the $target system.
     *
     * @param string $path
     * @param ReplicationTarget $target
     * @param array $arguments
     * @return Response
     */
    protected function post($path, ReplicationTarget $target, array $arguments = [])
    {
        $url = new Uri($target->getBaseUrl() . 'neos-replicator/' . $path);
        $request = HttpRequest::create($url, 'POST');
        $request->setHeader('X-Neos-Replicator-Api-Key', $target->getApiKey());
        $request->setContent(json_encode($arguments));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg(), 1469183994);
        }

        return $this->browser->sendRequest($request);
    }

    /**
     * Send a PUT request to the $path at the $target system.
     *
     * @param string $path
     * @param ReplicationTarget $target
     * @param array $arguments
     * @return Response
     */
    protected function put($path, ReplicationTarget $target, array $arguments = [])
    {
        $url = new Uri($target->getBaseUrl() . 'neos-replicator/' . $path);
        $request = HttpRequest::create($url, 'PUT');
        $request->setHeader('X-Neos-Replicator-Api-Key', $target->getApiKey());
        $request->setContent(json_encode($arguments));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg(), 1469183994);
        }

        return $this->browser->sendRequest($request);
    }

    /**
     * OUTDARED - was used before switching to the PropertyMapper
     *
     * Kept to show what was done, and to keep the hint about caption missing...
     *
     * @param Asset $asset
     * @return array
     */
    protected function getAssetProperties(Asset $asset)
    {
        $properties = [
            '__identity' => $asset->getIdentifier(),
            '__type' => TypeHandling::getTypeForValue($asset),
//            'caption' => $asset->getCaption(),
        ];

        if ($asset instanceof ImageVariant) {
            $properties['originalAsset'] = [
                '__identity' => $asset->getOriginalAsset()->getIdentifier(),
                '__type' => TypeHandling::getTypeForValue($asset->getOriginalAsset()),
                'adjustments' => $asset->getAdjustments()->toArray(),
            ];
        } else {
            $properties['title'] = $asset->getTitle();

            $resource = $asset->getResource();
            $properties['resource'] = [
                '__identity' => $this->persistenceManager->getIdentifierByObject($resource),
                '__type' => \TYPO3\Flow\Resource\Resource::class,
                'collectionName' => $resource->getCollectionName(),
                'filename' => $resource->getFilename(),
                'relativePublicationPath' => $resource->getRelativePublicationPath(),
                'mediaType' => $resource->getMediaType(),
                'sha1' => $resource->getSha1(),
                'content' => base64_encode(file_get_contents('resource://' . $resource->getSha1()))
            ];
            unset($resource);
        }

        return $properties;
    }
}
