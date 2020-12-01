<template>
  <div :class="groupClass">
    <div v-for="(item, idx) in fields"
         :is="item.tag"
         :key="idx"
         :content="item.content"
         :groupClass="(item.tag === 'FieldGroup') ? 'ifp-group' : 'ifp-field'"
         ></div>
    </div>
  </div>
</template>
<script>

import IfpField from './IfpField.vue';

export default {
  name: 'FieldGroup',
  props: ['content', 'groupClass'],
  components: { IfpField},
  data() {
    // content is an array of items that describe other components.
    // This allows nested FieldGroup items as well as various types of form
    // input, currently all handled by the IfpField component (as there's a lot of
    // crossover between types).
    return {
      fields: this.content,
    }
  }
}

</script>
<style lang="scss">
// Responsive 2 column layout
.ifp-group {
  margin-left: -1rem;
  margin-right: -1rem;
  display: flex;
  flex-wrap: wrap;
  &>* {
    padding:0 1rem;
    flex: 1 0 14rem;
  }
}
</style>

