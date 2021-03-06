<?php

namespace Adminaut\Controller;

use Adminaut\Controller\Plugin\TranslatePlugin;
use Adminaut\Form\UserForm;
use Adminaut\Form\InputFilter\UserInputFilter;
use Adminaut\Manager\UserManager;
use Zend\Http\PhpEnvironment\Request;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Zend\View\Model\ViewModel;

/**
 * Class InstallController
 * @package Adminaut\Controller
 * @method FlashMessenger flashMessenger()
 * @method TranslatePlugin translate($message, $textDomain = 'default', $locale = null)
 */
class InstallController extends AbstractActionController
{
    const ROUTE_INDEX = 'adminaut/install';

    /**
     * @var UserManager
     */
    private $userManager;

    //-------------------------------------------------------------------------

    /**
     * InstallController constructor.
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    //-------------------------------------------------------------------------

    /**
     * @return UserManager
     */
    private function getUserManager()
    {
        return $this->userManager;
    }

    //-------------------------------------------------------------------------

    /**
     * @return Response|ViewModel
     */
    public function indexAction()
    {
        if (0 !== $this->getUserManager()->countAll()) {
            return $this->redirect()->toRoute(DashboardController::ROUTE_INDEX);
        }

        $form = new UserForm(UserForm::STATUS_INSTALL);
        $form->setInputFilter(new UserInputFilter());

        /** @var Request $request */
        $request = $this->getRequest();

        if ($request->isPost()) {

            $post = $request->getPost()->toArray();

            $form->setData($post);

            if ($form->isValid()) {
                try {

                    $this->userManager->createSuperUser($form->getData());

                    $this->flashMessenger()->addSuccessMessage($this->translate('User has been successfully created.', 'adminaut'));

                    return $this->redirect()->toRoute(AuthController::ROUTE_LOGIN);
                } catch (\Exception $e) {

                    $this->flashMessenger()->addErrorMessage(sprintf($this->translate('Error: %s', 'adminaut'), $e->getMessage()));

                    return $this->redirect()->toRoute(self::ROUTE_INDEX);
                }
            }
        }

        $this->layout()->setVariables([
            'bodyClasses' => ['login-page'],
        ]);

        $this->layout('layout/admin-blank');

        return new ViewModel([
            'form' => $form,
        ]);
    }
}
