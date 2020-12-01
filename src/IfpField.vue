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
    <template v-if="isSelectType">
    <select v-model="$root.values[def.name]">
        <option v-for="option in def.option_values" v-bind:value="option.value">{{option.label}}</option>
    </select>
    </template>
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
      if (['Date','Time','file'].includes(this.def.type.name)) {
        return this.def.type.name.toLowerCase();
      }
      // Select lists.
      if (['Select','OptionGroup'].includes(this.def.type.name)) {
        return 'select';
      }
    },
    isInputType() {
      return ['text','email','date','time','file'].includes(this.inputType);
    },
    isTextareaType() {
      return (this.inputType === 'textarea');
    },
    isSelectType() {
      return (this.inputType === 'select');
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

