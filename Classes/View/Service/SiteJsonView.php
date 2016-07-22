<?php
namespace Flownative\Neos\Replicator\View\Service;

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
use TYPO3\Flow\Mvc\View\JsonView;

/**
 * A view specialised on a JSON representation of Sites.
 *
 * @Flow\Scope("prototype")
 */
class SiteJsonView extends JsonView
{
    /**
     * Configures rendering according to the set variable(s) and calls
     * render on the parent.
     *
     * @return string
     */
    public function render()
    {
        if (isset($this->variables['sites'])) {
            $this->setConfiguration(
                array(
                    'sites' => array(
                        '_descendAll' => array()
                    )
                )
            );
            $this->setVariablesToRender(array('sites'));
        } else {
            $this->setConfiguration(
                array(
                    'site' => array()
                )
            );
            $this->setVariablesToRender(array('site'));
        }

        return parent::render();
    }
}
