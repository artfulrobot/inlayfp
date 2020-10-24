import Vue from 'vue';
import InlayFormProcessor from './InlayFormProcessor.vue';

(() => {
  if (!window.inlayFPInit) {
    // This is the first time this *type* of Inlay has been encountered.
    // We need to define anything global here.

    // Create the boot function.
    window.inlayFPInit = inlay => {
      const inlayFPNode = document.createElement('div');
      inlay.script.insertAdjacentElement('afterend', inlayFPNode);
      /* eslint no-unused-vars: 0 */
      const app = new Vue({
        el: inlayFPNode,
        data() {
          const values = {};
          Object.keys(inlay.initData.fieldDefs).forEach(fieldName => {
            values[fieldName] = '';
            inlay.initData.fieldDefs[fieldName].include = false;
          });
          var d = {inlay, values, submissionRunning: false};
          return d;
        },
        render: h => h(InlayFormProcessor, {props: {inlay}})
      });
    };
  }
})();
