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
      v-if="inputType === 'textarea'"
      rows=4
      cols=40
      v-model="$root.values[def.name]"
      :required="def.is_required == 1"
      :name="def.name"
      :disabled="submissionRunning"
      :ref="def.name"
      />
    <select
      v-if="inputType === 'select'"
      v-model="$root.values[def.name]">
        <option v-for="option in def.option_values" :value="option.value">{{option.label}}</option>
    </select>

    <div v-if="inputType === 'radio'" class="ifp-radios">
      <div v-for="option in def.option_values" class="ifp-radio">
        <label :for="myId + option.value"><input
          type="radio"
          :name="def.name"
          :required="def.is_required == 1"
          :disabled="submissionRunning"
          :value="option.value"
          v-model="$root.values[def.name]"
          :id="myId + option.value" />
        {{option.label}}</label>
      </div>
    </div>

    <div v-if="inputType === 'checkbox'" class="ifp-checkboxes">
      <div v-for="(option, i) in checkboxes" class="ifp-checkbox">
        <label :for="myId + i"><input
          type="checkbox"
          :id="myId + i"
          :name="def.name"
          :required="def.is_required == 1"
          :disabled="submissionRunning"
          :value="option.value"
          v-model="option.selected"
          @change="updateValue()"
           />
        {{option.label}}</label>
      </div>
    </div>
  </div>
</template>
<style lang="scss">
// Create nice hanging indent with no dead, unclickable whitespace.
.ifp-radio>label {
  position: relative;
  $spaceForRadio: 1.6rem;
  padding-left: $spaceForRadio;
  &>input {
    position: absolute;
    margin-left: -$spaceForRadio;
  }
}
</style>
<script>

import IfpField from './IfpField.vue';

export default {
  // 'content' (String) form processor input name.
  props: ['content'],
  data() {
    const d = {
      myId: this.$root.getNextId(),
      checkboxes: []
    };

    // If we're handling checkboxes then we have a composite situation going on.
    if (this.$root.inlay.initData.fieldDefs[this.content].type.name === 'Checkbox') {
      this.$root.inlay.initData.fieldDefs[this.content].option_values.forEach(v => {
        d.checkboxes.push(Object.assign({'selected': false}, v));
      });
    }

    return d;
  },
  methods: {
    updateValue() {
      // Copy just the values that are selected.
      this.$root.values[this.def.name] = this.checkboxes
        .filter(option => option.selected)
        .map(option => option.value);
    }
  },
  computed: {
    def() {
      // The field definition for the input we're handling.
      return this.$root.inlay.initData.fieldDefs[this.content];
    },
    submissionRunning() {
      return this.$root.submissionRunning;
    },
    inputType() {
      var typeName = this.def.type.name;

      // Email is a special case.
      if (typeName === 'String' && this.def.validators.find(v => v.validator.name === 'email')) {
        return 'email';
      }

      // Map the rest to lower case, except the following oddballs.
      return {
        'String'      : 'text',
        'Text'        : 'textarea',
        'OptionGroup' : 'select',
      }[typeName] || typeName.toLowerCase();

    },
    isInputType() {
      return ['text','email','date','time','file'].includes(this.inputType);
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

