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

/**
 * This represents the configuration of a specific replication.
 *
 */
class ReplicationConfiguration
{
    const TRIGGER_PUBLISH = 'publish';
    const TRIGGER_MANUAL = 'manual';

    const TYPE_NODES = 'nodes';
    const TYPE_ASSETS = 'assets';
    const TYPE_USERS = 'users';

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var string
     */
    protected $trigger = self::TRIGGER_MANUAL;

    /**
     * @var array
     */
    protected $types = [self::TYPE_NODES];

    /**
     * @var array
     */
    protected $sites = [];

    /**
     * @var array
     */
    protected $workspaces = [];

    /**
     * @var string
     */
    protected $source = '__self__';

    /**
     * @var array
     */
    protected $targets = [];

    /**
     * ReplicationConfiguration constructor.
     *
     * @param string $identifier
     * @param array $configuration
     */
    public function __construct($identifier, array $configuration)
    {
        $this->identifier = $identifier;
        foreach ($configuration as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name ?: $this->identifier;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @return string[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @return string[]
     */
    public function getSites()
    {
        return $this->sites;
    }

    /**
     * @return string[]
     */
    public function getWorkspaces()
    {
        return $this->workspaces;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return string[]
     */
    public function getTargets()
    {
        return $this->targets;
    }

    /**
     * Returns true if the given $siteNodeName matches against a source site of this ReplicationConfiguration.
     *
     * @param $siteNodeName
     * @return boolean
     */
    public function matchesSiteNodeName($siteNodeName)
    {
        return $this->sites === [] || (array_search($siteNodeName, $this->sites, true) !== false);
    }

    /**
     * Returns true if the given $workspaceName matches against a source workspace of this ReplicationConfiguration.
     *
     * @todo implement wildcards for workspaces
     * @todo implement source:target workspace notation
     *
     * @param $workspaceName
     * @return boolean
     */
    public function matchesWorkspaceName($workspaceName)
    {
        return array_search($workspaceName, $this->workspaces, true) !== false;
    }
}