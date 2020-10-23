<?php

namespace Civi\Inlay;

use Civi\Inlay\Type as InlayType;
use Civi\Inlay\ApiRequest;
use Civi;
use CRM_Inlayfp_ExtensionUtil as E;

class FormProcessor extends InlayType {

  public static $typeName = 'Form Processor Form';

  public static $defaultConfig = [
    'formProcessor'   => NULL,
    'layout' => '',
    'submitButtonText' => 'Submit',
  ];

  /**
   * Note: because of the way CRM.url works, you MUST put a ? before the #
   *
   * @var string
   */
  public static $editURLTemplate = 'civicrm/a?#/inlays/formprocessor/{id}';

  /**
   * Sets the config ensuring it's valid.
   *
   * This implementation simply ensures all the defaults exist, and that no
   * other keys exist, but you could do other things, especially if you need to
   * coerce some old config into a new style.
   *
   * @param array $config
   *
   * @return \Civi\Inlay\Type (this)
   */
  public function setConfig(array $config) {
    $this->config = array_intersect_key($config + static::$defaultConfig, static::$defaultConfig);
  }

  /**
   * Generates data to be served with the Javascript application code bundle.
   *
   * @return array
   */
  public function getInitData() {
    $init = [
      // Name of global Javascript function used to boot this app.
      'init'             => 'inlayFPInit',
      'submitButtonText' => $this->config['submitButtonText'],
    ];

    $fp = civicrm_api3('FormProcessorInstance', 'get', ['sequential' => 1, 'name' => $this->config['formProcessor']])['values'][0] ?? NULL;
    if (!$fp) {
      // aaaagh!
      throw new RuntimeException("Cannot load form processor '{$this->config['formProcessor']}'");
    }

    // Parse the layout.
    $inputs = [];
    foreach ($fp['inputs'] as $_) {
      $inputs[$_['name']] = $_;
    }

    $init['layout'] = [];
    // First there's on item on the stack which is the top level thing.
    $stack = [&$init['layout']];
    $ptr = 0;
    $depth = 0;
    foreach (preg_split('/[\r\n]+/', $this->config['layout']) as $line) {
      if (!trim($line)) {
        continue;
      }
      $m = [];
      if (!preg_match('/^(\s*)(\.?)([a-zA-Z_-][a-zA-Z0-9_-]*)$/', $line, $m)) {
        // Broken! @todo flag this somehow. Possibly abort the rebuild.
        continue;
      }

      $lineDepth = strlen($m[1]);
      $isGroup = $m[2] === '.';
      $name = $m[3];

      while ($lineDepth < $depth) {
        array_pop($stack);
        $ptr--;
        $depth--;
      }

      if ($isGroup) {
        // new group.
        $item = ['tag' => 'FieldGroup', 'class' => $name, 'content' => []];
        // Add this item to the current collection.
        $stack[$ptr][] = &$item;
        // Add an item to the stck itself, do our new collection is the item's fields.
        $stack[] = &$item['content'];
        $ptr++;
        $depth++;
      }
      else {
        // field.
        if (!isset($inputs[$name])) {
          continue;
        }
        $item = ['tag' => 'IfpField', 'class' => $name, 'content' => $name];
        $stack[$ptr][] = $item;
      }
      unset($item);
    }

    // Export the field definitions, keyed by name.
    $init['fieldDefs'] = [];
    foreach ($fp['inputs'] as $_) {
      $init['fieldDefs'][$_['name']] = $_;
    }

    return $init;
  }

  /**
   * Process a request
   *
   * Request data is just key, value pairs from the form data. If it does not
   * have 'token' field then a token is generated and returned. Otherwise the
   * token is checked and processing continues.
   *
   * @param \Civi\Inlay\Request $request
   * @return array
   *
   * @throws \Civi\Inlay\ApiException;
   */
  public function processRequest(ApiRequest $request) {

    $data = $this->cleanupInput($request->getBody());

    if (empty($data['token'])) {
      // Unsigned request. Issue a token that will be valid in 5s time and lasts 2mins max.
      return ['token' => $this->getCSRFToken(['data' => $data, 'validFrom' => 5, 'validTo' => 120])];
    }

    // Hand over to the form processor.
    $result = civicrm_api3('FormProcessor', $this->config['formProcessor'], $data);

    // @todo handle failure
    return [ 'success' => 1 ];
  }

  /**
   * Validate and clean up input data.
   *
   * @param array $data
   *
   * @return array
   */
  public function cleanupInput($data) {
    $errors = [];
    $valid = [];
    // Check we have what we need.
    foreach (['first_name', 'last_name', 'email'] as $field) {
      $val = trim($data[$field] ?? '');
      if (empty($val)) {
        $errors[] = str_replace('_', ' ', $field) . " required.";
      }
      else {
        if ($field === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "invalid email address";
        }
        else {
          $valid[$field] = $val;
        }
      }
    }
    if ($errors) {
      throw new \Civi\Inlay\ApiException(400, implode(', ', $errors));
    }

    // Data is valid.
    if (!empty($data['token'])) {
      // There is a token, check that now.
      try {
        $this->checkCSRFToken($data['token'], $valid);
        $valid['token'] = TRUE;
      }
      catch (\InvalidArgumentException $e) {
        // Token failed. Issue a public friendly message, though this should
        // never be seen by anyone legit.
        Civi::log()->notice("Token error: " . $e->getMessage . "\n" . $e->getTraceAsString());
        watchdog('inlay', $e->getMessage() . "\n" . $e->getTraceAsString, array(), WATCHDOG_ERROR);
        throw new \Civi\Inlay\ApiException(400,
          "Mysterious problem, sorry! Code " . substr($e->getMessage(), 0, 3));
      }
    }


    return $valid;
  }

  /**
   * Returns a URL to a page that lets an admin user configure this Inlay.
   *
   * @return string URL
   */
  public function getAdminURL() {

  }

  /**
   * Get the Javascript app script.
   *
   * This will be bundled with getInitData() and some other helpers into a file
   * that will be sourced by the client website.
   *
   * @return string Content of a Javascript file.
   */
  public function getExternalScript() {
    return file_get_contents(E::path('dist/inlayfp.js'));
  }

}
