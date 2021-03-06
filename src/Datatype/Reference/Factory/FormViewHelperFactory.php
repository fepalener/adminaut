<?php

namespace Adminaut\Datatype\Reference\Factory;

use Adminaut\Datatype\Reference\FormViewHelper;
use Adminaut\Manager\AdminautModulesManager;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Class FormViewHelperFactory
 * @package Adminaut\Datatype\Reference\Factory
 */
class FormViewHelperFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return FormViewHelper
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {

        /** @var AdminautModulesManager $adminModuleManager */
        $adminModuleManager = $container->get(AdminautModulesManager::class);

        return new FormViewHelper($adminModuleManager);
    }
}
