<?php
return array(
    'modules' => array(
	    'DpAsynchronJob',
	    'DpOsmParser',
	    'DpOpenGis',
        'DpSpatialIndex',
        'DpDoctrineExtensions',
        'DpZFExtensions',
        'DpPHPUnitExtensions',
        'DoctrineModule',
        'DoctrineORMModule',
    ),
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            '../../../config/autoload/{,*.}{global,local}.php',
        ),
        'module_paths' => array(
            '../../../module',
            '../../../vendor',
        ),
    ),
);
