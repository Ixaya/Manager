<!-- TermsModal.vue -->
<template>
  <div>
    <!-- Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: #f5f6fa">
          <div class="modal-header">
            <h5 class="modal-title" id="termsModalLabel">Términos y Condiciones</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <terms-and-conditions />
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// ===========================
// Imports
// ===========================
// Import custom components
import TermsAndConditions from '@/common/components/modules/landing-pages/TermsAndConditions.vue'
</script>

<style scoped></style>
