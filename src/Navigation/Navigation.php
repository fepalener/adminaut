<?php

namespace Adminaut\Navigation;

use Adminaut\Service\AccessControlService;
use Interop\Container\ContainerInterface;
use Zend\Navigation\Service\DefaultNavigationFactory;

/**
 * Class Navigation
 * @package Adminaut\Navigation
 */
class Navigation extends DefaultNavigationFactory
{
    /**
     * @param ContainerInterface $container
     * @return array
     * @throws \Zend\Navigation\Exception\InvalidArgumentException
     */
    protected function getPages(ContainerInterface $container)
    {

        /** @var AccessControlService $accessControl */
        $accessControl = $container->get(AccessControlService::class);

        $config = $container->get('config');
        if (isset($config['adminaut']['modules'])) {
            $modules = $config['adminaut']['modules'];
        } else {
            $modules = [];
        }

        $pages[] = [
            'label' => _('Dashboard'),
            'route' => 'adminaut/dashboard',
            'icon' => 'fa fa-fw fa-dashboard',
        ];

        foreach ($modules as $key => $item) {
            if ($item['type'] == 'section') {
                $pages[] = [
                    'label' => $item['label'],
                    'uri' => '#',
                    'section' => true,
                ];
            } elseif ($item['type'] == 'module') {
                if ($accessControl->isAllowed($key, AccessControlService::READ)) {
                    if (isset($item['module_icon'])) {
                        $icon = 'fa fa-fw ' . $item['module_icon'];
                    } else {
                        $icon = 'fa fa-fw fa-list-alt';
                    }
                    $pages[] = [
                        'label' => $item['module_name'],
                        'route' => 'adminaut/module/list',
                        'params' => [
                            'module_id' => $key,
                        ],
                        'icon' => $icon,
                        'pages' => [
                            [
                                'route' => 'adminaut/module/action',
                                'visible' => false,
                                'params' => [
                                    'module_id' => $key,
                                ],
                            ],
                        ],
                    ];
                }
            } elseif ($item['type'] == 'link') {
                if ($accessControl->isAllowed($key, AccessControlService::READ)) {
                    if (isset($item['icon'])) {
                        $icon = 'fa fa-fw ' . $item['icon'];
                    } else {
                        $icon = 'fa fa-fw fa-link';
                    }

                    if(isset($item['route'])) {
                        $uriRouteKey = 'route';
                        $uriRoute = '#';
                        $params = [];

                        if(is_array($item['route'])) {
                            $uriRoute = $item['route'][0];

                            if(isset($item['route'][1])) {
                                $params = $item['route'][1];
                            }
                        } else {
                            $uriRoute = $item['route'];
                        }
                    } elseif(isset($item['uri'])) {
                        $uriRouteKey = 'uri';
                        $uriRoute = $item['uri'];
                    }

                    $pages[] = [
                        'label' => $item['name'],
                        $uriRouteKey => $uriRoute,
                        'params' => $params,
                        'icon' => $icon,
                        'pages' => [],
                    ];
                }
            }
        }

        if ($accessControl->isAllowed('users', AccessControlService::READ)) {
            if(!(isset($modules['system-section']) || (isset($modules['system']) && $modules['system']['type'] === 'section'))) {
                $pages[] = [
                    'label' => _('System'),
                    'uri' => '#',
                    'section' => true,
                ];
            }
            if ($accessControl->isAllowed('users', AccessControlService::READ)) {
                $pages[] = [
                    'label' => _('Users'),
                    'route' => 'adminaut/users',
                    'icon' => 'fa fa-fw fa-users',
                    'pages' => [
                    ],
                ];
            }
        }

        if (null === $this->pages) {
            $mvcEvent = $container->get('Application')->getMvcEvent();
            $routeMatch = $mvcEvent->getRouteMatch();
            $router = $mvcEvent->getRouter();
            $pages = $this->getPagesFromConfig($pages);
            $this->pages = $this->injectComponents($pages, $routeMatch, $router);
        }
        return $this->pages;
    }
}