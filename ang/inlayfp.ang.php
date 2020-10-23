<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// \https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules/n
return array (
  'js' => 
  array (
    0 => 'ang/inlayfp.js',
    1 => 'ang/inlayfp/*.js',
    2 => 'ang/inlayfp/*/*.js',
  ),
  'css' => 
  array (
    0 => 'ang/inlayfp.css',
  ),
  'partials' => 
  array (
    0 => 'ang/inlayfp',
  ),
  'requires' => 
  array (
    0 => 'crmUi',
    1 => 'crmUtil',
    2 => 'ngRoute',
  ),
  'settings' => 
  array (
  ),
);
