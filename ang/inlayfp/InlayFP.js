console.log("hello");
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
              return crmApi('FormProcessorInstance', 'get', {})
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

  });

})(angular, CRM.$, CRM._);
