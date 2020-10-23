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
    return {
      fields: this.content,
    }
  }
}

</script>
<style lang="scss">
.ifp-group {
  margin-left: -1rem;
  margin-right: -1rem;
  display: flex;
  &>* {
    padding:0 1rem;
    flex: 1 1 auto;
  }
}
</style>

