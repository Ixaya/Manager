<template>
  <div class="modal fade" id="UploadPhotoModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalLabel">Recortar imagen</h5>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div v-show="pic != ''">
          <VuePictureCropper
            :boxStyle="{
              width: '70%',
              height: '70%',
              backgroundColor: '#f8f8f8',
              margin: 'auto'
            }"
            :img="pic"
            :options="{
              viewMode: 1,
              dragMode: 'crop',
              aspectRatio: 1 / 1
            }" />
        </div>

        <div class="px-4 py-4" v-show="pic == ''">
          <Skeleton width="100%" height="150px"></Skeleton>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" @click="reset">Resetear Imagen</button>
          <button class="btn btn-primary" type="button" data-bs-dismiss="modal" @click="getResult">Aceptar</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
// Import required modules
import { ref, reactive, defineProps, defineEmits, watchEffect } from 'vue'
import VuePictureCropper, { cropper } from 'vue-picture-cropper'
import Skeleton from 'primevue/skeleton'

// Define props
const props = defineProps({
  pic: { type: String, default: '' },
  isShowModal: { type: Boolean, default: false }
})

// Define emits
const emit = defineEmits(['result', 'file'])

const pic = ref('')
const result = reactive({ dataURL: '', blobURL: '' })
let isShowModal = ref(false)

watchEffect(() => {
  pic.value = props.pic
  isShowModal.value = props.isShowModal
})

const getResult = async () => {
  if (!cropper) return
  const base64 = cropper.getDataURL()
  const blob = await cropper.getBlob()
  if (!blob) return

  const file = await cropper.getFile()
  result.dataURL = base64
  result.blobURL = URL.createObjectURL(blob)
  isShowModal.value = false
  emit('result', result)
  emit('file', file)
}

const reset = () => {
  if (!cropper) return
  cropper.reset()
}
</script>
<style scoped></style>
