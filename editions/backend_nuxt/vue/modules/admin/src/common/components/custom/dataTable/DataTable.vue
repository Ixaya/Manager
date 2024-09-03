<template>
  <div>
    <div class="row align-items-center dataTables_wrapper dt-bootstrap5 no-footer mb-2">
      <div class="col-sm-12 col-md-6">
        <div class="dataTables_length" id="DataTables_Table_length">
          <label
            >Show
            <select name="DataTables_Table_length" aria-controls="DataTables_Table" class="form-select form-select-sm" v-model="pageSize">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
            entries</label
          >
        </div>
      </div>
      <div class="col-sm-12 col-md-6 mt-2">
        <div id="DataTables_Table_filter" class="dataTables_filter">
          <label>Search:<input type="search" v-model="searchQuery" class="form-control form-control-sm" placeholder="Buscar..." aria-controls="DataTables_Table_13" /></label>
        </div>
      </div>
    </div>
    <div class="table-responsive dataTables_wrapper dt-bootstrap5 no-footer">
      <table ref="table" :class="'table dataTable no-footer' + className" style="width: 100%">
        <thead>
          <tr>
            <th v-for="column in columns" :key="column.title">{{ column.title }}</th>
            <th v-if="action">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!data.length">
            <td :colspan="columns.length + (action ? 1 : 0)" class="text-center">No hay datos disponibles</td>
          </tr>
          <tr v-else v-for="row in data" :key="row[0]">
            <td v-for="(cell, index) in row" :key="index">{{ cell }}</td>
            <td v-if="action">
              <button @click="editRow(row[0])" class="btn btn-primary btn-sm me-2">Editar</button>
              <button @click="deleteRow(row[0], row[1])" class="btn btn-danger btn-sm">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="props.customPagination && data.length" class="row align-items-center mt-2 dataTables_wrapper dt-bootstrap5 no-footer">
      <div class="col-sm-12 col-md-6">
        <div class="dataTables_info" role="status" aria-live="polite">Showing {{ (currentPage - 1) * pageSize + 1 }} to {{ Math.min(currentPage * pageSize, totalRecords) }} of {{ totalRecords }} entries</div>
      </div>
      <div class="col-sm-12 col-md-6">
        <div class="dataTables_paginate paging_simple_numbers">
          <ul class="pagination">
            <li class="paginate_button page-item" :class="{ disabled: currentPage === 1 }">
              <a @click.prevent="prevPage" aria-controls="dataTable" role="link" data-dt-idx="previous" class="page-link">Previous</a>
            </li>
            <li v-for="page in totalPages" :key="page" class="paginate_button page-item" :class="{ active: page === currentPage }">
              <a @click.prevent="goToPage(page)" aria-controls="dataTable" role="link" :data-dt-idx="page - 1" class="page-link">{{ page }}</a>
            </li>
            <li class="paginate_button page-item" :class="{ disabled: currentPage === totalPages }">
              <a @click.prevent="nextPage" aria-controls="dataTable" role="link" data-dt-idx="next" class="page-link">Next</a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, watch, onMounted, defineProps, defineEmits } from 'vue'
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css'
import 'datatables.net-bs5/js/dataTables.bootstrap5.min.js'
import 'datatables.net-bs5'

const emit = defineEmits(['delete', 'edit'])

const props = defineProps({
  data: Array,
  columns: Array,
  options: Object,
  action: { type: Boolean, default: false },
  className: { type: String, default: 'table-striped' },
  isFooter: { type: String },
  isColumnFilter: { type: String },
  isToggleFilter: { type: String },
  isLanguageFilter: { type: String },
  totalRecords: { type: Number, default: 0 },
  customPagination: { type: Boolean, default: false },
  onPageChange: { type: Function, default: () => {} } // Prop to handle page change
})

let searchTimeout = null
const searchQuery = ref('')
const currentPage = ref(1)
const pageSize = ref(10) // Fixed page size
const totalRecords = ref(0)
const totalPages = ref(1)

watch(
  () => props.data,
  () => {
    if (totalRecords.value != props.totalRecords) {
      currentPage.value = 1
    }
    if (props.totalRecords > 0) {
      totalRecords.value = props.totalRecords
      totalPages.value = Math.ceil(totalRecords.value / pageSize.value)
    }
  }
)

watch(
  () => searchQuery.value,
  () => {
    if (searchTimeout) {
      clearTimeout(searchTimeout)
    }
    searchTimeout = setTimeout(() => {
      currentPage.value = 1
      emitPageChange()
    }, 300)
  }
)

watch(
  () => pageSize.value,
  () => {
    currentPage.value = 1
    emitPageChange()
  }
)

const deleteRow = (id, name) => {
  emit('delete', id, name)
}

const editRow = (id) => {
  emit('edit', id)
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
    emitPageChange()
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
    emitPageChange()
  }
}

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    emitPageChange()
  }
}

const emitPageChange = () => {
  if (props.onPageChange) {
    props.onPageChange(currentPage.value, searchQuery.value, pageSize.value)
  }
}

onMounted(() => {
  if (props.customPagination) {
    emitPageChange()
  }
})
</script>
<style scoped>
.pagination .page-item {
  cursor: pointer;
}

.pagination .page-item.disabled {
  cursor: default;
}

.pagination .page-item.active .page-link {
  cursor: default;
}
</style>
