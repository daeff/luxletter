<?php
declare(strict_types=1);
namespace In2code\Luxletter\Controller;

use In2code\Luxletter\Domain\Model\Newsletter;
use In2code\Luxletter\Domain\Model\User;
use In2code\Luxletter\Domain\Model\Usergroup;
use In2code\Luxletter\Domain\Repository\UsergroupRepository;
use In2code\Luxletter\Domain\Repository\UserRepository;
use In2code\Luxletter\Domain\Service\LogService;
use In2code\Luxletter\Domain\Service\ParseNewsletterUrlService;
use In2code\Luxletter\Exception\UserValuesAreMissingException;
use In2code\Luxletter\Utility\BackendUserUtility;
use In2code\Luxletter\Utility\LocalizationUtility;
use In2code\Luxletter\Utility\ObjectUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

/**
 * Class FrontendController
 */
class FrontendController extends ActionController
{
    /**
     * @var UserRepository
     */
    protected $userRepository = null;

    /**
     * @var UsergroupRepository
     */
    protected $usergroupRepository = null;

    /**
     * @var LogService
     */
    protected $logService = null;

    /**
     * @return void
     */
    public function initializePreviewAction(): void
    {
        if (BackendUserUtility::isBackendUserAuthenticated() === false) {
            throw new \LogicException('You are not authenticated to see this view', 1560778826);
        }
    }

    /**
     * @param string $origin
     * @return string
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     * @throws InvalidConfigurationTypeException
     */
    public function previewAction(string $origin): string
    {
        try {
            $urlService = ObjectUtility::getObjectManager()->get(ParseNewsletterUrlService::class, $origin);
            return $urlService->getParsedContent();
        } catch (\Exception $exception) {
            return 'Origin ' . htmlspecialchars($origin) . ' could not be converted into a valid url!';
        }
    }

    /**
     * Render a transparent gif and track the access as email-opening
     *
     * @param Newsletter|null $newsletter
     * @param User|null $user
     * @return string
     * @throws IllegalObjectTypeException
     */
    public function trackingPixelAction(Newsletter $newsletter = null, User $user = null): string
    {
        if ($newsletter !== null && $user !== null) {
            $this->logService->logNewsletterOpening($newsletter, $user);
        }
        return base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
    }

    /**
     * @param User|null $user
     * @param Newsletter $newsletter
     * @param string $hash
     * @return void
     */
    public function unsubscribeAction(User $user = null, Newsletter $newsletter = null, string $hash = ''): void
    {
        try {
            $this->checkArgumentsForUnsubscribeAction($user, $newsletter, $hash);
            /** @var Usergroup $usergroupToRemove */
            $usergroupToRemove = $newsletter->getReceiver();
            $user->removeUsergroup($usergroupToRemove);
            $this->userRepository->update($user);
            $this->userRepository->persistAll();
            $this->view->assignMultiple([
                'success' => true,
                'user' => $user,
                'hash' => $hash,
                'usergroupToRemove' => $usergroupToRemove
            ]);
            if ($newsletter !== null) {
                $this->logService->logUnsubscribe($newsletter, $user);
            }
        } catch (\Exception $exception) {
            $languageKey = 'fe.unsubscribe.message.' . $exception->getCode();
            $message = LocalizationUtility::translate($languageKey);
            $this->addFlashMessage(($languageKey !== $message) ? $message : $exception->getMessage());
        }
    }

    /**
     * @param User|null $user
     * @param Newsletter|null $newsletter
     * @param string $hash
     * @return void
     * @throws UserValuesAreMissingException
     */
    protected function checkArgumentsForUnsubscribeAction(
        User $user = null,
        Newsletter $newsletter = null,
        string $hash = ''
    ): void {
        if ($user === null) {
            throw new \InvalidArgumentException('User not given', 1562050511);
        }
        if ($newsletter === null) {
            throw new \InvalidArgumentException('Newsletter not given', 1562267031);
        }
        if ($hash === '') {
            throw new \InvalidArgumentException('Hash not given', 1562050533);
        }
        $usergroupToRemove = $this->usergroupRepository->findByUid((int)$this->settings['removeusergroup']);
        if ($user->getUsergroup()->contains($usergroupToRemove) === false) {
            throw new \LogicException('Usergroup not assigned to user', 1562066292);
        }
        if ($user->getUnsubscribeHash() !== $hash) {
            throw new \LogicException('Given hash is incorrect', 1562069583);
        }
    }

    /**
     * @param UserRepository $userRepository
     * @return void
     */
    public function injectUserRepository(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param UsergroupRepository $usergroupRepository
     * @return void
     */
    public function injectUsergroupRepository(UsergroupRepository $usergroupRepository)
    {
        $this->usergroupRepository = $usergroupRepository;
    }

    /**
     * @param LogService $logService
     * @return void
     */
    public function injectLogService(LogService $logService)
    {
        $this->logService = $logService;
    }
}
