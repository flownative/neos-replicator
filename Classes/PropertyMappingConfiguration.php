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

use TYPO3\Flow\Property\PropertyMappingConfigurationInterface;
use TYPO3\Flow\Property\TypeConverter\ObjectConverter;
use TYPO3\Flow\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\Flow\Resource\ResourceTypeConverter;
use TYPO3\Media\TypeConverter\AssetInterfaceConverter;

/**
 * Property mapping configuration which is used for import / export:
 *
 * - works for all levels of the PropertyMapping (recursively)
 * - sets the correct export and import configuration for the type converters
 */
class PropertyMappingConfiguration implements PropertyMappingConfigurationInterface
{
    /**
     * The sub-configuration to be used is the current one.
     *
     * @param string $propertyName
     * @return PropertyMappingConfigurationInterface the property mapping configuration for the given $propertyName.
     * @api
     */
    public function getConfigurationFor($propertyName)
    {
        return $this;
    }

    /**
     * @param string $typeConverterClassName
     * @param string $key
     * @return mixed configuration value for the specific $typeConverterClassName. Can be used by Type Converters to fetch converter-specific configuration
     * @api
     */
    public function getConfigurationValue($typeConverterClassName, $key)
    {
        if ($typeConverterClassName === PersistentObjectConverter::class && $key === PersistentObjectConverter::CONFIGURATION_IDENTITY_CREATION_ALLOWED) {
            return true;
        }
        if ($typeConverterClassName === ResourceTypeConverter::class && $key === ResourceTypeConverter::CONFIGURATION_IDENTITY_CREATION_ALLOWED) {
            return true;
        }
        if ($typeConverterClassName === ObjectConverter::class && $key === ObjectConverter::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED) {
            return true;
        }
        if ($typeConverterClassName === AssetInterfaceConverter::class && $key === AssetInterfaceConverter::CONFIGURATION_CREATION_ALLOWED) {
            return true;
        }
        if ($typeConverterClassName === AssetInterfaceConverter::class && $key === AssetInterfaceConverter::CONFIGURATION_ONE_PER_RESOURCE) {
            return true;
        }

        // fallback
        return null;
    }


    // starting from here, we just implement the interface in the "default" way without modifying things

    /**
     * @param string $propertyName
     * @return boolean TRUE if the given propertyName should be mapped, FALSE otherwise.
     * @api
     */
    public function shouldMap($propertyName)
    {
        return true;
    }

    /**
     * Check if the given $propertyName should be skipped during mapping.
     *
     * @param string $propertyName
     * @return boolean
     * @api
     */
    public function shouldSkip($propertyName)
    {
        return false;
    }

    /**
     * Whether unknown (unconfigured) properties should be skipped during
     * mapping, instead if causing an error.
     *
     * @return boolean
     * @api
     */
    public function shouldSkipUnknownProperties()
    {
        return false;
    }

    /**
     * Maps the given $sourcePropertyName to a target property name.
     * Can be used to rename properties from source to target.
     *
     * @param string $sourcePropertyName
     * @return string property name of target
     * @api
     */
    public function getTargetPropertyName($sourcePropertyName)
    {
        return $sourcePropertyName;
    }

    /**
     * This method can be used to explicitly force a TypeConverter to be used for this Configuration.
     *
     * @return \TYPO3\Flow\Property\TypeConverterInterface The type converter to be used for this particular PropertyMappingConfiguration, or NULL if the system-wide configured type converter should be used.
     * @api
     */
    public function getTypeConverter()
    {
        return null;
    }
}
