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
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\Context;

/**
 * Rudimentary REST service for nodes
 *
 * This enhances the Neos controller as needed, so the package is usable with Neos 2.3.
 * For Neos 3.0 the changes should hopefully be part of Neos and this controller can be dropped.
 *
 * @Flow\Scope("singleton")
 */
class NodesController extends \TYPO3\Neos\Controller\Service\NodesController
{
    /**
     * Create a new node
     *
     * The "mode" property defines the basic mode of operation. Currently supported modes:
     *
     * 'new': Create a new node
     *   - $identifier, $workspaceName, $dimensions specify the new node
     *   - $properties is required
     *   - $sourceDimensions is optional
     *
     * 'adoptFromAnotherDimension': Adopts the single node from another dimension
     *   - $identifier, $workspaceName and $sourceDimensions specify the source node
     *   - $identifier, $workspaceName and $dimensions specify the target node
     *   - $properties is optional
     *
     * @param string $mode
     * @param string $identifier Specifies the identifier of the node to be created
     * @param string $workspaceName Name of the workspace where to create the node in
     * @param array $dimensions Optional list of dimensions and their values in which the node should be created
     * @param array $sourceDimensions
     * @param array $properties
     * @return string
     * @Flow\SkipCsrfProtection
     */
    public function createAction($mode, $identifier, $workspaceName = 'live', array $dimensions = [], array $sourceDimensions = [], array $properties = [])
    {
        switch ($mode) {
            case 'new':
                $contentContext = $this->createContentContext($workspaceName, $dimensions);
                $parentNode = $contentContext->getNode($properties['__parentPath']);

                if ($parentNode === null) {
                    $this->throwStatus(424, 'Parent node was not found.');
                }

                if (isset($properties['_nodeType'])) {
                    $nodeType = $this->nodeTypeManager->getNodeType($properties['_nodeType']);
                    unset($properties['_nodeType']);
                } else {
                    $nodeType = $this->nodeTypeManager->getNodeType('unstructured');
                }

                $existingChildNode = $parentNode->getNode($properties['_name']);
                if ($existingChildNode === null) {
                    $newNode = $parentNode->createNode($properties['_name'], $nodeType, $identifier);
                    unset($properties['_name']);

                    try {
                        $this->setNodeProperties($newNode, $nodeType, $properties, $contentContext);
                    } catch (\Exception $exception) {
                        $this->throwStatus(500, 'Error when setting properties. ' . $exception->getMessage());
                    }
                }

                $this->redirect('show', null, null, [
                    'identifier' => $identifier,
                    'workspaceName' => $workspaceName,
                    'dimensions' => $dimensions
                ]);
            break;
            default:
                parent::createAction($mode, $identifier, $workspaceName, $dimensions, $sourceDimensions);
        }
    }

    /**
     * Updates a node
     *
     * @param string $identifier Specifies the identifier of the node to be updated
     * @param string $workspaceName Name of the workspace of the node
     * @param array $dimensions List of dimensions and their values of the node
     * @param array $properties
     * @return string
     * @Flow\SkipCsrfProtection
     */
    public function updateAction($identifier, $workspaceName = 'live', array $dimensions = [], array $properties = [])
    {
        $contentContext = $this->createContentContext($workspaceName, $dimensions);
        $node = $contentContext->getNodeByIdentifier($identifier);

        if ($node === null) {
            $this->throwStatus(404, 'Node was not found.');
        }

        if (isset($properties['_nodeType'])) {
            $nodeType = $this->nodeTypeManager->getNodeType($properties['_nodeType']);
            unset($properties['_nodeType']);
        } else {
            $nodeType = $this->nodeTypeManager->getNodeType('unstructured');
        }

        try {
            $this->setNodeProperties($node, $nodeType, $properties, $contentContext);
        } catch (\Exception $exception) {
            $this->throwStatus(500, 'Error when setting properties. ' . $exception->getMessage());
        }

        $this->redirect('show', null, null, [
            'identifier' => $identifier,
            'workspaceName' => $workspaceName,
            'dimensions' => $dimensions
        ]);
    }

    /**
     * Deletes the specified node
     *
     * @param string $identifier Specifies the node to look up
     * @param string $workspaceName Name of the workspace to use for querying the node
     * @param array $dimensions Optional list of dimensions and their values which should be used for querying the specified node
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function deleteAction($identifier, $workspaceName = 'live', array $dimensions = [])
    {
        $contentContext = $this->createContentContext($workspaceName, $dimensions);
        $node = $contentContext->getNodeByIdentifier($identifier);

        if ($node === null) {
            $this->throwStatus(404, sprintf('Unknown node with identifier "%s"', $identifier));
        } else {
            $node->remove();
            $this->throwStatus(204);
        }
    }

    /**
     * Iterates through the given $properties setting them on the specified $node using the appropriate TypeConverters.
     *
     * @param object $nodeLike
     * @param NodeType $nodeType
     * @param array $properties
     * @param Context $context
     * @return void
     * @throws \TYPO3\Flow\Property\Exception\TypeConverterException
     * @todo taken from NodeConverter in TYPO3CR, move to some common place?
     */
    protected function setNodeProperties($nodeLike, NodeType $nodeType, array $properties, Context $context)
    {
        $configuration = New PropertyMappingConfiguration();

        $nodeTypeProperties = $nodeType->getProperties();
        unset($properties['_lastPublicationDateTime']);
        foreach ($properties as $nodePropertyName => $nodePropertyValue) {
            if (substr($nodePropertyName, 0, 2) === '__') {
                continue;
            }
            $nodePropertyType = isset($nodeTypeProperties[$nodePropertyName]['type']) ? $nodeTypeProperties[$nodePropertyName]['type'] : null;
            switch ($nodePropertyType) {
                case 'reference':
                    $nodePropertyValue = $context->getNodeByIdentifier($nodePropertyValue);
                break;
                case 'references':
                    $nodeIdentifiers = $nodePropertyValue;
                    $nodePropertyValue = [];
                    if (is_array($nodeIdentifiers)) {
                        foreach ($nodeIdentifiers as $nodeIdentifier) {
                            $referencedNode = $context->getNodeByIdentifier($nodeIdentifier);
                            if ($referencedNode !== null) {
                                $nodePropertyValue[] = $referencedNode;
                            }
                        }
                    } else {
                        throw new \TYPO3\Flow\Property\Exception\TypeConverterException(sprintf('node type "%s" expects an array of identifiers for its property "%s"', $nodeType->getName(), $nodePropertyName), 1462291981);
                    }
                break;
                case 'DateTime':
                    if ($nodePropertyValue !== '' && ($nodePropertyValue = \DateTime::createFromFormat(\DateTime::W3C, $nodePropertyValue)) !== false) {
                        $nodePropertyValue->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    } else {
                        $nodePropertyValue = null;
                    }
                break;
                case 'integer':
                    $nodePropertyValue = intval($nodePropertyValue);
                break;
                case 'boolean':
                    if (is_string($nodePropertyValue)) {
                        $nodePropertyValue = ($nodePropertyValue === 'true' || $nodePropertyValue === '1') ? true : false;
                    }
                break;
                case 'array':
                    $nodePropertyValue = json_decode($nodePropertyValue, true);
                break;
            }
            if (substr($nodePropertyName, 0, 1) === '_') {
                $nodePropertyName = substr($nodePropertyName, 1);
                ObjectAccess::setProperty($nodeLike, $nodePropertyName, $nodePropertyValue);
                continue;
            }

            if (!isset($nodeTypeProperties[$nodePropertyName])) {
                if ($configuration !== null && $configuration->shouldSkipUnknownProperties()) {
                    continue;
                } else {
                    throw new \TYPO3\Flow\Property\Exception\TypeConverterException(sprintf('Node type "%s" does not have a property "%s" according to the schema', $nodeType->getName(), $nodePropertyName), 1462266711);
                }
            }
            $innerType = $nodePropertyType;
            if ($nodePropertyType !== null) {
                try {
                    $parsedType = \TYPO3\Flow\Utility\TypeHandling::parseType($nodePropertyType);
                    $innerType = $parsedType['elementType'] ?: $parsedType['type'];
                } catch (\TYPO3\Flow\Utility\Exception\InvalidTypeException $exception) {
                }
            }

            if (is_string($nodePropertyValue) && $this->objectManager->isRegistered($innerType) && $nodePropertyValue !== '') {
                $nodePropertyValue = $this->propertyMapper->convert(json_decode($nodePropertyValue, true), $nodePropertyType, $configuration);
            }
            $nodeLike->setProperty($nodePropertyName, $nodePropertyValue);
        }
    }
}
