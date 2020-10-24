<template>
  <div style="overflow:hidden;">

    <form action='#' @submit.prevent="submitForm" v-if="stage === 'form'">

      <field-group
        :content="inlay.initData.layout"
        group-class=""
        ></field-group>

      <div class="ifg-submit">
        <button
         @click="wantsToSubmit"
         :disabled="submissionRunning"
          >{{ submissionRunning ? "Please wait.." : inlay.initData.submitButtonText }}</button>
        <inlay-progress ref="progress"></inlay-progress>
      </div>

    </form>

    <div v-if="stage === 'thanks'" v-html="inlay.initData.webThanksHTML"></div>
  </div>
</template>
<script>
import FieldGroup from './FieldGroup.vue';
import InlayProgress from './InlayProgress.vue';
export default {
  props: ['inlay'],
  components: {FieldGroup, InlayProgress},
  data() {
    return {
      stage: 'form'
    };
  },
  computed: {
    submissionRunning() {
      return this.$root.submissionRunning;
    }
  },
  methods: {
    wantsToSubmit() {
      // validate all fields.
    },
    submitForm() {
      // Form is valid according to browser.
      this.$root.submissionRunning = true;
      const d = {};
      Object.keys(this.$root.values).forEach(fieldName => {
        if (this.$root.inlay.initData.fieldDefs[fieldName].include) {
          d[fieldName] = this.$root.values[fieldName];
        }
      });
      const progress = this.$refs.progress;
      progress.startTimer(5, 20, 1);
      this.inlay.request({method: 'post', body: d})
        .then(r => {
          if (r.token) {
            d.token = r.token;
            progress.startTimer(6, 80);
            // Force 5s wait for the token to become valid
            return new Promise((resolve, reject) => {
              window.setTimeout(resolve, 5000);
            });
          }
          else {
            console.warn("unexpected resonse", r);
            throw (r.error || 'Unknown error');
          }
        })
        .then(() => {
          progress.startTimer(2, 100);
          return this.inlay.request({method: 'post', body: d});
        })
        .then(r => {
          if (r.error) {
            throw (r.error);
          }
          this.stage = 'thanks';
          progress.cancelTimer();
        })
        .catch(e => {
          console.error(e);
          if (typeof e === 'String') {
            alert(e);
          }
          else {
            alert("Unexpected error");
          }
          this.$root.submissionRunning = false;
          progress.cancelTimer();
        });
    }
  }
}
</script>
