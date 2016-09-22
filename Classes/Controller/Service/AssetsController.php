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

use Flownative\Neos\Replicator\PropertyMappingConfiguration;
use TYPO3\Flow\Annotations as Flow;

/**
 * Rudimentary REST service for assets
 *
 * This enhances the Neos controller as needed, so the package is usable with Neos 2.1 and 2.2.
 * For Neos 2.3 the changes should hopefully be part of Neos and this controller can be dropped.
 *
 * @Flow\Scope("singleton")
 */
class AssetsController extends \TYPO3\Neos\Controller\Service\AssetsController
{
    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Resource\ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Property\PropertyMapper
     */
    protected $propertyMapper;

    /**
     * Creates a new asset
     *
     * @param array $asset
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function createAction(array $asset)
    {
        $existingAsset = $this->assetRepository->findByIdentifier($asset['__identity']);
        if ($existingAsset instanceof \TYPO3\Media\Domain\Model\AssetInterface) {
            $this->throwStatus(409, 'Asset already exists');
        }

        $asset = $this->propertyMapper->convert($asset, $asset['__type'], new PropertyMappingConfiguration());
        $this->assetRepository->add($asset);

        $this->throwStatus(201, 'Asset created');
    }
}
