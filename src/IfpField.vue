<template>
  <div :class="content">
    <label >{{label}}</label>
    <input
      v-if="isInputType"
      :name="def.name"
      :type="inputType"
      :ref="def.name"
      :required="def.is_required == 1"
      :disabled="submissionRunning"
      v-model="$root.values[def.name]"
      />
    <textarea
      v-if="isTextareaType"
      rows=4
      cols=40
      v-model="$root.values[def.name]"
      :required="def.is_required == 1"
      :name="def.name"
      :disabled="submissionRunning"
      :ref="def.name"
      />
  </div>
</template>
<script>

import IfpField from './IfpField.vue';

export default {
  props: ['content'],
  computed: {
    def() {
      return this.$root.inlay.initData.fieldDefs[this.content];
    },
    submissionRunning() {
      return this.$root.submissionRunning;
    },
    inputType() {
      if (this.def.type.name === 'String') {
        // Could be text or email.
        if (this.def.validators.find(v => v.validator.name === 'email')) {
          return 'email';
        }
        return 'text';
      }
      if (this.def.type.name === 'Text') {
        return 'textarea';
      }
      // Handle simple HTML5 validation types.
      if (['Date','Time','Password'].includes(this.def.type.name)) {
        return this.def.type.name.toLowerCase();
      }
    },
    isInputType() {
      return ['text','email','date','time','password'].includes(this.inputType);
    },
    isTextareaType() {
      return (this.inputType === 'textarea');
    },
    label() {
      return this.def.title;
    }
  },
  created() {
    this.$root.inlay.initData.fieldDefs[this.content].include = true;
  }
}

</script>

