<template>
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <div class="header-title">
        <h4 class="card-title">Custom Validation</h4>
      </div>
    </div>
    <div class="card-body">
      <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi vulputate, ex ac venenatis mollis, diam nibh finibus leo</p>
      <form :class="`row g-3 needs-validation ${valid ? 'was-validated' : ''}`" @submit.prevent="formSubmit" novalidate="">
        <b-col md="6">
          <label class="form-label" for="validationCustom01">First name</label>
          <input class="form-control" id="validationCustom01" type="text" required="" />
          <div class="valid-feedback">Looks good!</div>
        </b-col>
        <b-col md="6">
          <label class="form-label" for="validationCustom02">Last name</label>
          <input class="form-control" id="validationCustom02" type="text" required="" />
          <div class="valid-feedback">Looks good!</div>
        </b-col>
        <b-col md="6">
          <label class="form-label" for="validationCustomUsername01">Username</label>
          <div class="input-group has-validation">
            <span class="input-group-text" id="inputGroupPrepend">@</span>
            <input class="form-control" id="validationCustomUsername01" type="text" aria-describedby="inputGroupPrepend" required="" />
            <div class="invalid-feedback">Please choose a username.</div>
          </div>
        </b-col>
        <b-col md="6">
          <label class="form-label" for="validationCustom03">City</label>
          <input class="form-control" id="validationCustom03" type="text" required="" />
          <div class="invalid-feedback">Please provide a valid city.</div>
        </b-col>
        <b-col md="6">
          <label class="form-label" for="validationCustom04">State</label>
          <select class="form-select" id="validationCustom04" required="">
            <option selected="" disabled="" value="">Choose...</option>
            <option>...</option>
          </select>
          <div class="invalid-feedback">Please select a valid state.</div>
        </b-col>
        <b-col md="6">
          <label class="form-label" for="validationCustom05">Zip</label>
          <input class="form-control" id="validationCustom05" type="text" required="" />
          <div class="invalid-feedback">Please provide a valid zip.</div>
        </b-col>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" id="invalidCheck" type="checkbox" value="" required="" />
            <label class="form-check-label" for="invalidCheck">Agree to terms and conditions</label>
            <div class="invalid-feedback">You must agree before submitting.</div>
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-danger" type="reset" @click="resetForm">Reset</button>
          <button class="btn btn-primary ms-3" type="submit">Submit form</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { required } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
export default {
  setup() {
    const v = useVuelidate()
    return {
      v
    }
  },
  data() {
    return {
      valid: false,
      form: {
        firstName: '',
        lastName: '',
        username: '',
        city: '',
        state: '',
        zip: '',
        agree: false
      }
    }
  },
  validations() {
    return {
      form: {
        firstName: {
          required
        },
        lastName: {
          required
        },
        username: {
          required
        },
        city: {
          required
        },
        state: {
          required
        },
        zip: {
          required
        },
        agree: {
          required
        }
      }
    }
  },
  methods: {
    async formSubmit() {
      this.valid = true
      const result = await this.v.$validate()
      if (result) {
        // this.valid = true
      }
    },
    resetForm() {
      this.v.$reset()
      this.valid = false
    }
  }
}
</script>
