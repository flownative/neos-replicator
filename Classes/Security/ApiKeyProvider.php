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
use TYPO3\Flow\Security\Account;
use TYPO3\Flow\Security\Authentication\Provider\AbstractProvider;
use TYPO3\Flow\Security\Authentication\TokenInterface;
use TYPO3\Flow\Security\Exception\UnsupportedAuthenticationTokenException;
use TYPO3\Flow\Security\Policy\PolicyService;

/**
 * An authentication provider that authenticates ApiKeyToken tokens.
 *
 * The roles set in authenticateRoles will be added to the authenticated
 * token, but will not be persisted in the database.
 */
class ApiKeyProvider extends AbstractProvider
{
    /**
     * @Flow\InjectConfiguration(path="apiKey")
     * @var array
     */
    protected $apiKey;

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * Returns the class names of the tokens this provider can authenticate.
     *
     * @return array
     */
    public function getTokenClassNames()
    {
        return [ApiKeyToken::class];
    }

    /**
     * Sets isAuthenticated to TRUE for all tokens.
     *
     * @param TokenInterface $authenticationToken The token to be authenticated
     * @return void
     * @throws UnsupportedAuthenticationTokenException
     */
    public function authenticate(TokenInterface $authenticationToken)
    {
        if (!($authenticationToken instanceof ApiKeyToken)) {
            throw new UnsupportedAuthenticationTokenException('This provider cannot authenticate the given token.', 1461857494);
        }

        $credentials = $authenticationToken->getCredentials();
        if (is_array($credentials) && isset($credentials['apiKey'])) {
            if ($credentials['apiKey'] === $this->apiKey) {
                $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
                $account = new Account();
                $roles = [];
                foreach ($this->options['authenticateRoles'] as $roleIdentifier) {
                    $roles[] = $this->policyService->getRole($roleIdentifier);
                }
                $account->setRoles($roles);
                $authenticationToken->setAccount($account);
            } else {
                $authenticationToken->setAuthenticationStatus(TokenInterface::WRONG_CREDENTIALS);
            }
        } elseif ($authenticationToken->getAuthenticationStatus() !== TokenInterface::AUTHENTICATION_SUCCESSFUL) {
            $authenticationToken->setAuthenticationStatus(TokenInterface::NO_CREDENTIALS_GIVEN);
        }
    }
}
