<template>
  <div style="overflow:hidden;">
    <form action='#' @submit.prevent="submitForm">

      <field-group
        :content="inlay.initData.layout"
        group-class=""
        ></field-group>

      <div class="ifg-submit">
        <button
         @click="wantsToSubmit"
          >{{inlay.initData.submitButtonText}}</button>
        <inlay-progress ref="progress"></inlay-progress>
      </div>

    </form>
  </div>
</template>
<script>
import FieldGroup from './FieldGroup.vue';
import InlayProgress from './InlayProgress.vue';
export default {
  props: ['inlay'],
  components: {FieldGroup, InlayProgress},
  data() {
    return {};
  },
  computed: {
  },
  methods: {
    wantsToSubmit() {
      // validate all fields.
    },
    submitForm() {
      // Form is valid according to browser.
      const d = {};
      Object.keys(this.$root.values).forEach(fieldName => {
        if (this.$root.inlay.initData.fieldDefs[fieldName].include) {
          d[fieldName] = this.$root.values[fieldName];
        }
      });
      console.log("would submit: ", d);
      this.$refs.progress.startTimer(5, 20, 1);
    }
  }
}
</script>
