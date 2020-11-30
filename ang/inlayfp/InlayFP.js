(function(angular, $, _) {

  angular.module('inlayfp').config(function($routeProvider) {
      $routeProvider.when('/inlays/formprocessor/:id', {
        controller: 'InlayfpInlayFP',
        templateUrl: '~/inlayfp/InlayFP.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          various: function($route, crmApi4, $route, crmApi) {
            const params = {
              inlayTypes: ['InlayType', 'get', {}, 'class']
            };
            if ($route.current.params.id > 0) {
              params.inlay = ['Inlay', 'get', {where: [["id", "=", $route.current.params.id]]}, 0];
            }
            return crmApi4(params)
            .then(r => {
              return crmApi('FormProcessorInstance', 'get', {sequential: 1})
                .then(r2 => {
                  r.formProcessors = r2.values;
                  return r;
                })
            });
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  angular.module('inlayfp').controller('InlayfpInlayFP', function($scope, crmApi, crmApi4, crmStatus, crmUiHelp, various) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('inlayfp');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/inlayfp/InlayFP'}); // See: templates/CRM/inlayfp/InlayFP.hlp

    $scope.inlayType = various.inlayTypes['Civi\\Inlay\\FormProcessor'];
    console.log({various}, $scope.inlayType);
    if (various.inlay) {
      $scope.inlay = various.inlay;
    }
    else {
      $scope.inlay = {
        'class' : 'Civi\\Inlay\\FormProcessor',
        name: 'New ' + $scope.inlayType.name,
        public_id: 'new',
        id: 0,
        config: JSON.parse(JSON.stringify($scope.inlayType.defaultConfig)),
      };
    }
    const inlay = $scope.inlay;
    $scope.formProcessors = various.formProcessors;
    console.log(various.formProcessors);

    $scope.save = function() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi4('Inlay', 'save', { records: [inlay] })
      ).then(r => {
        console.log("save result", r);
        window.location = CRM.url('civicrm/a?#inlays');
      });
    };

    $scope.fp = null;
    $scope.fpInputs = null;
    $scope.setFP = function() {
      $scope.fp = various.formProcessors.find(fp => fp.name === inlay.config.formProcessor);
      $scope.fpInputs = {};
      if ($scope.fp) {
        // name-indexed lookup of inputs.
        $scope.fp.inputs.forEach(i => { $scope.fpInputs[i.name] = i; });
      }
    };
    $scope.setFP();

    /**
     * Parse a layout like
     * .group-class
     *  field1
     *  field2~select
     * field3
     *
     */
    $scope.checkLayout = function checkLayout() {
      console.log("checkLayout running", inlay.config.layout);
      var html = '<div>', stack = ['</div>'], indent= '', errors= [], fieldsUsed = {};

      var items = inlay.config.layout.split(/[\r\n]+/);
      items.forEach(i => {
        var m = i.match(/^( *)(\.?[a-zA-Z_-][a-zA-Z0-9_-]*)(?:~([a-zA-Z0-9_-]+))*$/);
        if (!m) {
          errors.push(`The line '${i}' is invalid.`);
          return;
        }

        // @todo get field details.
        if (m[1].length < indent.length) {
          // decreased - close a group.
          html += stack.splice(0, indent.length - m[1].length).join('');
          indent = m[1];
        }
        else if (m[1].length > indent.length) {
          // increased indentation without starting a group.
          errors.push(`The line '${i}' is invalid - too much indentation. Expected ${indent.length} got ${m[1].length}`);
          return;
        }

        if (m[2].substr(0, 1) === '.') {
          // Start a new group.
          indent += ' ';
          html += `<div class="group group--${m[2].substr(1)}">${m[2]}<div class="items">`;
          stack.unshift('</div></div>');
        }
        else {
          // Is a field.
          if (m[2] in $scope.fpInputs) {
            // Check for a modifier.
            modifierText = '';
            if (m[3]) {
              modifierText = ' ' + fieldModifier(m[3]);
            }
            fieldHtml = `<div class="field">Field: ${m[2]}${modifierText}</div>`
            html += fieldHtml;
            fieldsUsed[m[2]] = 1;
          }
          else {
            errors.push(`Field ${m[2]} is not defined as an input in the selected Form Processor.`);
          }
        }
      });
      html += stack.join('');

      $scope.layoutParsed = {
        html, errors, fieldsUsed
      };
    }
    function fieldModifier(modifier) {
      switch (modifier) {
        case 'select':
          text = "(Format as select list)";
          break;
        case 'radios':
        case 'checkboxes':
          text = "(Format as radio buttons/checkboxes)";
          break;
        default:
          text = '';
      }
      return text;
    }
    $scope.checkLayout();

  });

})(angular, CRM.$, CRM._);
