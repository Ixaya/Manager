<template>
  <div class="table">
    <DataTable :value="columns">
      <Column v-for="header in props.headers" :key="header" :field="header.title" :header="header.title">
        <template #body>
          <div class="mt-3">
            <Skeleton></Skeleton>
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { defineProps, ref } from 'vue'
import Skeleton from 'primevue/skeleton'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

// Define props
const props = defineProps({
  column: { type: Number, default: 10 },
  headers: { type: Array, default: () => [] }
})

const columns = ref(new Array(props.column).fill({}))
</script>
