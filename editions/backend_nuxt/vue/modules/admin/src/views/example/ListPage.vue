<template>
  <div>
    <!-- Sub Header Component Start Here-->
    <sub-header title="Example" description="" :button="true" button_text="Crear" button_icon="bi-plus-circle-fill" button_class="btn-success" button_link="default.example-add"></sub-header>
    <!-- Sub Header Component End Here-->
  </div>
  <div class="container-fluid px-2 px-md-4">
    <div class="row">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <div class="header-title">
              <h4 class="card-title">Lista</h4>
            </div>
          </div>
          <div class="card-body">
            <div v-if="!isLoading && showData">
              <data-table :data="DataTableOptions.data" :columns="DataTableOptions.columns" isFooter="bootstrap-data-table" :customPagination="true" :onPageChange="handlePageChange" :totalRecords="totalRecords" @delete="onDeleteSubmit" @edit="onEditShow" :action="true" />
            </div>
            <div v-else>
              <data-table-skeleton :column="10" :headers="header" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <button class="d-none" ref="deleteModal" type="hidden" data-bs-toggle="modal" data-bs-target="#deleteModal"></button>
  <!-- Modal de confirmación -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header text-center">
          <h5 class="modal-title" id="deleteModalLabel">Advertencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center">
            <i class="bi bi-trash3" style="font-size: 3rem; color: red"></i>
          </div>
          ¿Estás seguro de que deseas eliminar a <strong>{{ exampleName }}</strong
          >?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger" @click="onDeleteSubmit(exampleId, exampleName, 1)" data-bs-dismiss="modal">Eliminar</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
// ===========================
// Imports
// ===========================
// Import required modules
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

// Import custom composables
import { useExamples } from '@/common/composables/example/useExample'
import { useDeleteExample } from '@/common/composables/example/useDeleteExample'

// Import custom components
import DataTable from '@/common/components/custom/dataTable/DataTable.vue'
import SubHeader from '@/common/components/custom/header/SubHeader.vue'
import DataTableSkeleton from '@/common/components/custom/skeleton/DataTableSkeleton.vue'

// ===========================
// Variables
// ===========================
let showData = ref(false)
let formattedData = ref([])
let exampleName = ref('')
let exampleId = ref(null)
let totalRecords = ref(0)
const router = useRouter()
const { deleteExample, isLoading: isDeleting } = useDeleteExample()
const { data, params, isLoading, refetch } = useExamples()
const deleteModal = ref(null)
const DataTableOptions = ref({
  columns: [{ title: 'ID' }, { title: 'Titulo' }, { title: 'Example' }, { title: 'Creado en' }, { title: 'Última actividad' }],
  data: formattedData.value
})
const header = [{ title: 'ID' }, { title: 'Titulo' }, { title: 'Example' }, { title: 'Creado en' }, { title: 'Última actividad' }, { title: 'Acción' }]

// ===========================
// Methods
// ===========================
const handlePageChange = async (page, searchQuery, limit = 10) => {
  params.value = { page, searchQuery, limit }
  await refetch()

  formattedData.value = Object.values(data.value.examples).map((item) => {
    return [item.id, item.title, item.example, item.create_date, item.last_update]
  })

  DataTableOptions.value.data = formattedData.value
  totalRecords.value = parseInt(data.value.recordsTotal, 10)
  await new Promise((resolve) => setTimeout(resolve, 1000))
  showData.value = true
}

const onEditShow = (id) => {
  router.push({ name: 'default.example-edit', params: { id } })
}

const onDeleteSubmit = async (id, name, action = 0) => {
  if (action === 0) {
    exampleName.value = name
    exampleId.value = id
    return deleteModal.value.click()
  } else {
    await deleteExample(id)
    if (!isDeleting) {
      handlePageChange(0)
    }
  }
}

// ===========================
// Lifecycle Hooks
// ===========================
onMounted(() => {
  handlePageChange(0)
})
</script>
