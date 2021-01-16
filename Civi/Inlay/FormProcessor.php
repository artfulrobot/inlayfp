<?php

namespace Civi\Inlay;

use Civi\Inlay\Type as InlayType;
use Civi\Inlay\ApiRequest;
use Civi;
use Civi\Api4\Inlay;
use CRM_Inlayfp_ExtensionUtil as E;

class FormProcessor extends InlayType {

  public static $typeName = 'Form Processor Form';

  public static $defaultConfig = [
    'formProcessor'    => NULL,
    'publicTitle'      => '',
    'layout'           => '',
    'submitButtonText' => 'Submit',
    'webThanksHTML'    => '<p>Thank you</p>',
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
    // We use the function instead of FormProcessorInstance.get API because this returns the field types as objects.
    // This allows us to call getOptions() when building the field definitions.
    $fp = array_pop(\CRM_FormProcessor_BAO_FormProcessorInstance::getValues(['name' => $this->config['formProcessor']]));
    if (!$fp) {
      // aaaagh!
      throw new \RuntimeException("Cannot load form processor '{$this->config['formProcessor']}'");
    }

    // Parse the layout.
    $inputs = [];
    foreach ($fp['inputs'] as $_) {
      $inputs[$_['name']] = $_;
    }

    $init['layout'] = $init['fieldDefs'] = [];
    // First there's on item on the stack which is the top level thing.
    $stack = [&$init['layout']];
    $ptr = 0;
    $depth = 0;
    foreach (preg_split('/[\r\n]+/', $this->config['layout']) as $line) {
      if (!trim($line)) {
        continue;
      }
      $m = [];
      if (!preg_match('/^(\s*)(\.?)([a-zA-Z_-][a-zA-Z0-9_-]*)(?:~([a-zA-Z0-9_-]+))*$/', $line, $m)) {
        // Broken! @todo flag this somehow. Possibly abort the rebuild.
        continue;
      }

      $lineDepth = strlen($m[1]);
      $isGroup = $m[2] === '.';
      $name = $m[3];
      $modifier = $m[4] ?? NULL;
      while ($lineDepth < $depth) {
        array_pop($stack);
        $ptr--;
        $depth--;
      }

      // Nb. $item['tag'] is the Vue tag/component name.
      // $item['class'] is supposed to be just a CSS class; not valuable for fields (as
      //  there's other data to construct classes from), but I just liked the
      //  idea that you could specify a class for a group, as this makes CSS
      //  theming easier on the front end.
      // $item['content'] is either an array of items, or is the Form Processor input
      // $item['name'] This means that you can look up the metadata for a field from this key.

      if ($isGroup) {
        // new group.
        $item = ['tag' => 'FieldGroup', 'class' => $name, 'content' => []];
        // Add this item to the current collection.
        $stack[$ptr][] = &$item;
        // Add an item to the stack itself, so our new collection is the item's fields.
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
        // Export the field definitions, keyed by name.
        $init['fieldDefs'][$name] = $this->buildFieldDef($inputs[$name], $modifier);
      }
      unset($item);
    }

    return $init;
  }

  /**
   * Strip out unneeded data from the field definition; add in options for multiple-choice inputs.
   * Also apply any field modifiers.
   *
   * The field definition is the output of the FormProcessor.get API, but filtered and a bit mangled.
   *
   * e.g. {
   *   name: string,
   *   is_required: bool,
   *   type: {
   *      name: Checkbox|Radio|Select|...,
   *   },
   *   option_values: [ {label: string, value: string}, ... ],
   *   validators: [
   *   ],
   *   title: string
   * }
   */
  private function buildFieldDef(array $inputDef, ?string $modifier) : array {
    $fieldDef = [];
    // Let's only take data we're interested in, to minimize JS size and reduce info leakage.
    $usedElements = ['name', 'is_required', 'configuration', 'validators', 'title'];
    foreach ($usedElements as $element) {
      $fieldDef[$element] = $inputDef[$element];
    }
    $fieldDef['type']['name'] = $inputDef['type']->getName();
    // All option list (and boolean) fields should default to a type of "Select".
    if (is_subclass_of($inputDef['type'], '\Civi\FormProcessor\Type\OptionListInterface') ||
    $inputDef['type']->getName() === 'Boolean') {
      $fieldDef['type']['name'] = 'Select';
    }
    // Apply modifiers.
    switch ($modifier) {
      case 'radios':
      case 'checkboxes':
        if ($fieldDef['configuration']['multiple'] ?? FALSE) {
          $fieldDef['type']['name'] = 'Checkbox';
        }
        else {
          $fieldDef['type']['name'] = 'Radio';
        }
        break;

      case 'select':
        $fieldDef['type']['name'] = 'Select';
        break;
    }
    // Add option values if applicable.
    // Put a blank option at the top so select lists/radios can be deselected.
    if (in_array($fieldDef['type']['name'], ['Select', 'Radio']) && !$fieldDef['is_required']) {
      $fieldDef['option_values'][] = [['label' => '- '. ts('none') . ' -', 'value' => '']];
    }
    if (method_exists($inputDef['type'], 'getOptions')) {
      $optionValues = $inputDef['type']->getOptions([]);
      foreach ($optionValues as $key => $optionValue) {
        $fieldDef['option_values'][] = ['label' => $optionValue, 'value' => $key];
      }
    }
    // Booleans are a special case.
    if ($inputDef['type']->getName() === 'Boolean') {
      $fieldDef['option_values'][] = ['label' => E::ts('Yes'), 'value' => 1];
      $fieldDef['option_values'][] = ['label' => E::ts('No'), 'value' => 0];
    }
    return $fieldDef;
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

    // Here I would like to call the form processor, but only as far as
    // validating the inputs, not actually executing it.
    // However, the validation is all coded together with the
    // invokeFormProcessor() execute code, so that can't happen right now.
    //
    // Instead we'll just ensure that the only data we pass on is that which
    // correlates to the inputs of the form processor.
    $fp = civicrm_api3('FormProcessorInstance', 'get', ['sequential' => 1, 'name' => $this->config['formProcessor']])['values'][0] ?? NULL;
    if (!$fp) {
      Civi::log()->error("Inlay error FP1: failed to load form processor for the Inlay called " . $this->getName());
      throw new \Civi\Inlay\ApiException(500, "Sorry, this form has not been configured correctly. Error: FP1");
    }

    foreach ($fp['inputs'] as $_) {
      $inputName = $_['name'];
      if (isset($data[$inputName])) {
        $valid[$inputName] = $data[$inputName];
      }
      elseif ($_['is_required'] == 1) {
        // A required input is not present in the request. This is not going to work.
        // It's probably a configuration error - e.g. didn't add the field to the form.
        Civi::log()->error("Inlay error FP2: Form Processor in put $inputName is required but has not been sent with a request. Has the input been added to the form correctly? Inlay Name: " . $this->getName());
        throw new \Civi\Inlay\ApiException(500, "Sorry, this form has not been configured correctly. Error: FP2");
      }
    }

    // Ok, we don't know if the data is valid, but we do know that $valid now
    // only contains the inputs, and that none of the required inputs are
    // missing.

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
