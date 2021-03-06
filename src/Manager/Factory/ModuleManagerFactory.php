<?php

namespace Adminaut\Manager\Factory;

use Adminaut\Manager\ModuleManager;
use Adminaut\Options\AdminautOptions;
use Adminaut\Service\AccessControlService;
use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Class ModuleManagerFactory
 * @package Adminaut\Manager\Factory
 */
class ModuleManagerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ModuleManager
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {

        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);

        /** @var AdminautOptions $adminautOptions */
        $adminautOptions = $container->get(AdminautOptions::class);

        /** @var AccessControlService $accessControlService */
        $accessControlService = $container->get(AccessControlService::class);

        /** @var TranslatorInterface|null $translator */
        $translator = null;
        if ($container->has('MvcTranslator')) {
            $translator = $container->get('MvcTranslator');
        } elseif ($container->has(TranslatorInterface::class)) {
            $translator = $container->get(TranslatorInterface::class);
        } elseif ($container->has('Translator')) {
            $translator = $container->get('Translator');
        }

        return new ModuleManager($entityManager, $accessControlService, $adminautOptions->getModules(), $translator);
    }
}
