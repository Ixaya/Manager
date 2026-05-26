<template>
  <fieldset>
    <div class="form-card text-start">
      <b-row>
        <div class="col-7">
          <h3 class="mb-4">{{ 'sidebar.account-info' }}:</h3>
        </div>
        <div class="col-5">
          <h2 class="steps">Step 1 - 4</h2>
        </div>
      </b-row>
      <b-row>
        <b-col md="6">
          <b-form-group label="Email: *">
            <b-form-input class="form-control" type="email" name="email" placeholder="Email Id" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Username: *">
            <b-form-input class="form-control" type="text" name="uname" placeholder="UserName" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Password: *">
            <b-form-input class="form-control" type="password" name="new-password" placeholder="Password" />
          </b-form-group>
        </b-col>
        <b-col md="6">
          <b-form-group label="Confirm Password: *">
            <b-form-input class="form-control" type="password" name="confirm-new-password" placeholder="Confirm Password" />
          </b-form-group>
        </b-col>
      </b-row>
    </div>
    <a class="btn btn-primary next action-button float-end" href="#personal" @click="changeTab(2)" value="Next">Next</a>
  </fieldset>
</template>
<script>
export default {
  name: 'AccountComponent',
  setup(props, { emit }) {
    const changeTab = (value) => {
      emit('onClick', value)
    }
    return { props, changeTab }
  }
}
</script>
