<?php

return [
    'migrations' => [
        'default' => [
            'dir' => dirname(__FILE__) . '/../../../../migrations',
            'namespace' => 'ZfSimpleMigrations\Migrations',
            'show_log' => true,
            'adapter' => 'Zend\Db\Adapter\Adapter'
        ],
    ],
    'console' => [
        'router' => [
            'routes' => [
                'migration-version' => [
                    'type' => 'simple',
                    'options' => [
                        'route' => 'migration version [<source>] [<name>] [--env=]',
                        'defaults' => [
                            'controller' => 'ZfSimpleMigrations\Controller\Migrate',
                            'action' => 'version',
                            'name' => 'default'
                        ]
                    ]
                ],
                'migration-list' => [
                    'type' => 'simple',
                    'options' => [
                        'route' => 'migration list [<source>] [<name>] [--env=] [--all]',
                        'defaults' => [
                            'controller' => 'ZfSimpleMigrations\Controller\Migrate',
                            'action' => 'list',
                            'name' => 'default'
                        ]
                    ]
                ],
                'migration-apply' => [
                    'type' => 'simple',
                    'options' => [
                        'route' => 'migration apply [<source>] [<name>] [<version>] [--env=] [--force] [--down] [--fake]',
                        'defaults' => [
                            'controller' => 'ZfSimpleMigrations\Controller\Migrate',
                            'action' => 'apply',
                            'name' => 'default'
                        ]
                    ]
                ],
                'migration-generate' => [
                    'type' => 'simple',
                    'options' => [
                        'route' => 'migration generate [<source>] [<name>] [--env=]',
                        'defaults' => [
                            'controller' => 'ZfSimpleMigrations\Controller\Migrate',
                            'action' => 'generateSkeleton',
                            'name' => 'default'
                        ]
                    ]
                ],
                'migration-init' => [
                    'type' => 'simple',
                    'options' => [
                        'route' => 'migration init',
                        'defaults' => [
                            'controller' => 'ZfSimpleMigrations\Controller\Migrate',
                            'action' => 'init',
                            'name' => 'default'
                        ]
                    ]
                ]
            ]
        ]
    ],
    'controllers' => [
        'factories' => [
            'ZfSimpleMigrations\Controller\Migrate' => 'ZfSimpleMigrations\\Controller\\MigrateControllerFactory'
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
