<?php
namespace Flownative\Neos\Replicator\Security;

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
use TYPO3\Flow\Security\Authentication\Token\AbstractToken;
use TYPO3\Flow\Security\Authentication\Token\SessionlessTokenInterface;

class ApiKeyToken extends AbstractToken implements SessionlessTokenInterface
{

    /**
     * The api key credentials
     *
     * @var array
     * @Flow\Transient
     */
    protected $credentials = ['apiKey' => ''];

    /**
     * Updates the authentication credentials, the authentication manager needs to authenticate this token.
     *
     * @param \TYPO3\Flow\Mvc\ActionRequest $actionRequest The current request instance
     * @return boolean TRUE if this token needs to be (re-)authenticated
     */
    public function updateCredentials(\TYPO3\Flow\Mvc\ActionRequest $actionRequest)
    {
        $authorizationHeader = $actionRequest->getHttpRequest()->getHeaders()->get('X-Neos-Replicator-Api-Key');
        if (!empty($authorizationHeader)) {
            $this->credentials['apiKey'] = $authorizationHeader;
            $this->setAuthenticationStatus(self::AUTHENTICATION_NEEDED);
        } else {
            $this->credentials['apiKey'] = null;
            $this->authenticationStatus = self::NO_CREDENTIALS_GIVEN;
        }
    }
}